<?php

/*
 * Двери модуля ИИ-собеседования.
 *
 * Каждая страница ведёт кандидата туда, где он нужен, — но адреса остаются
 * достижимыми и после того, как человек с них ушёл. Здесь проверяется, что
 * запросом по сохранившемуся адресу нельзя ни начать разговор без согласия, ни
 * дослать решение после объявленного итога, ни запустить разбор документов
 * второй раз, ни завести собеседование по вакансии, на которую не откликался.
 *
 * Всё это было возможно и починено; тесты стоят здесь, чтобы не вернулось.
 */

use App\Models\AiCandidateDocument;
use App\Models\AiInterview;
use App\Models\AiInterviewTurn;
use App\Models\AiTestSubmission;
use App\Models\AiTestTask;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\VacancyResponse;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');
});

/**
 * Экзаменатор, который не может составить задание.
 *
 * Свой, а не общий из AiTestTaskTest: файл должен проходить и запущенный
 * отдельно, а классы из чужого тестового файла тогда не загружены.
 */
class GuardExaminer implements App\Services\Ai\TaskExaminer
{
    public function __construct(private readonly bool $gradeFails = false)
    {
    }

    public function available(): bool
    {
        return true;
    }

    public function compose(AiInterview $interview, $criteria): array
    {
        throw new App\Services\Ai\AiInvalidAnswerException('дважды негодно', ['statement' => ['пусто']]);
    }

    public function grade(AiTestTask $task, string $solution): array
    {
        if ($this->gradeFails) {
            throw new App\Services\Ai\AiInvalidAnswerException('дважды негодно', ['score' => ['вне шкалы']]);
        }

        return ['score' => 0, 'review' => [], 'summary' => '', 'executed' => false, 'execution' => []];
    }
}

/** Формулировщик, который отдаёт готовый шаблон как есть. */
class GuardWriter implements App\Services\Ai\DecisionWriter
{
    public function available(): bool
    {
        return true;
    }

    public function write(AiInterview $interview, string $outcome, array $reasons, string $fallback): string
    {
        return $fallback;
    }
}

/** Готовое к разговору собеседование: согласие дано, документы разобраны. */
function talkable(array $state = []): array
{
    [$vacancy] = makeAiVacancy();

    [$interview, , $applicantUser] = makeAiInterview($vacancy, $state + [
        'consent_at' => now(),
        'consent_ip' => '127.0.0.1',
        'stage' => 'interview',
        'analysis_status' => 'ready',
        'documents_score' => 70,
    ]);

    return [$vacancy, $interview, $applicantUser];
}

// ==================== вход: собеседование растёт из отклика ====================

test('без отклика собеседование не заводится', function () {
    [$vacancy] = makeAiVacancy();

    $user = User::factory()->applicant()->create();
    makeResume(makeApplicant($user));

    $this->actingAs($user)
        ->get(route('applicant.ai.start', $vacancy))
        ->assertRedirect(route('public.vacancies.show', $vacancy))
        ->assertSessionHas('error');

    /*
     * Иначе работодатель видел бы в отчёте кандидата, который на вакансию не
     * откликался, а отклика у записи не было бы вовсе — статус по итогам
     * решения ставить было бы некуда.
     */
    expect(AiInterview::count())->toBe(0);
});

test('с откликом заводится и помнит отклик', function () {
    [$vacancy] = makeAiVacancy();

    $user = User::factory()->applicant()->create();
    $applicant = makeApplicant($user);
    makeResume($applicant);

    $response = VacancyResponse::create([
        'applicant_id' => $applicant->id,
        'vacancy_id' => $vacancy->id,
    ]);

    $this->actingAs($user)->get(route('applicant.ai.start', $vacancy))->assertRedirect();

    expect(AiInterview::sole()->vacancy_response_id)->toBe($response->id);
});

// ==================== разбор документов делается один раз ====================

test('разобранные документы не разбираются заново', function () {
    [, $interview, $applicantUser] = talkable();

    $this->actingAs($applicantUser)
        ->post(route('applicant.ai.proceed', $interview))
        ->assertRedirect(route('applicant.ai.interview', $interview));

    /*
     * Повторный разбор не просто тратил бы квоту: он переписывает сверку
     * требований и балл за документы, и у решённого собеседования баллы в
     * отчёте разошлись бы с объявленным решением.
     */
    expect($interview->fresh()->analysis_status)->toBe('ready')
        ->and(Queue::pushedJobs())->toBe([]);
});

test('после объявленного решения разбор не запускается', function () {
    [, $interview, $applicantUser] = talkable([
        'stage' => 'done', 'outcome' => 'rejected', 'decided_at' => now(),
    ]);

    $this->actingAs($applicantUser)->post(route('applicant.ai.proceed', $interview));

    expect($interview->fresh()->analysis_status)->toBe('ready')
        ->and(Queue::pushedJobs())->toBe([]);
});

test('неудавшийся разбор пробуется снова', function () {
    [, $interview, $applicantUser] = talkable([
        'stage' => 'documents', 'analysis_status' => 'failed', 'documents_score' => null,
    ]);

    // сбой сети не должен означать тупик — эту кнопку жмут именно для того,
    // чтобы попробовать ещё раз
    $this->actingAs($applicantUser)
        ->post(route('applicant.ai.proceed', $interview))
        ->assertRedirect(route('applicant.ai.documents', $interview));

    expect($interview->fresh()->analysis_status)->toBe('pending')
        ->and(Queue::pushedJobs())->not->toBe([]);
});

// ==================== разговор не начинается без согласия ====================

test('без согласия вопрос не задаётся', function () {
    [, $interview, $applicantUser] = talkable([
        'consent_at' => null, 'stage' => 'consent', 'analysis_status' => 'idle',
        'documents_score' => null,
    ]);

    $this->actingAs($applicantUser)
        ->post(route('applicant.ai.chat.begin', $interview))
        ->assertStatus(422)
        ->assertJsonPath('reload', true);

    // обещание «без согласия не происходит ничего» должно выполняться и для
    // запроса, отправленного мимо страницы
    expect(AiInterviewTurn::count())->toBe(0);
});

test('до разбора документов вопрос не задаётся', function () {
    [, $interview, $applicantUser] = talkable([
        'stage' => 'documents', 'analysis_status' => 'pending', 'documents_score' => null,
    ]);

    $this->actingAs($applicantUser)
        ->post(route('applicant.ai.chat.begin', $interview))
        ->assertStatus(422);

    expect(AiInterviewTurn::count())->toBe(0);
});

test('после собеседования вопрос не задаётся', function () {
    [, $interview, $applicantUser] = talkable(['stage' => 'test']);

    $this->actingAs($applicantUser)
        ->post(route('applicant.ai.chat.begin', $interview))
        ->assertStatus(422);

    expect(AiInterviewTurn::count())->toBe(0);
});

test('ответ вне стадии разговора не принимается', function () {
    [, $interview, $applicantUser] = talkable(['stage' => 'test']);

    AiInterviewTurn::create([
        'ai_interview_id' => $interview->id, 'position' => 1, 'role' => 'ai',
        'text' => 'вопрос', 'question_kind' => 'technical',
    ]);

    $this->actingAs($applicantUser)
        ->post(route('applicant.ai.chat.answer', $interview), ['text' => 'ответ'])
        ->assertStatus(422);

    expect(AiInterviewTurn::where('role', 'candidate')->count())->toBe(0);
});

// ==================== решение задания только на своей стадии ====================

/** Составленное задание, срок по нему ещё не пошёл. */
function composedTask(AiInterview $interview): AiTestTask
{
    return AiTestTask::create([
        'ai_interview_id' => $interview->id,
        'kind' => AiTestTask::KIND_MAIN,
        'statement' => 'Напишите функцию нормализации телефона.',
        'format' => 'code',
        'language' => 'PHP',
        'reference_solution' => 'эталон',
        'rubric' => [['point' => 'Убирает лишние символы', 'weight' => 100]],
        'time_limit_minutes' => 40,
    ]);
}

test('решение не принимается до конца собеседования', function () {
    [, $interview, $applicantUser] = talkable(['stage' => 'interview']);

    composedTask($interview);

    $this->actingAs($applicantUser)
        ->post(route('applicant.ai.task.submit', $interview), ['solution' => 'echo 1;'])
        ->assertRedirect(route('applicant.ai.interview', $interview))
        ->assertSessionHas('error');

    expect(AiTestSubmission::count())->toBe(0);
});

test('решение не дописывается к объявленному решению', function () {
    [, $interview, $applicantUser] = talkable([
        'stage' => 'done', 'outcome' => 'rejected', 'decided_at' => now(),
    ]);

    composedTask($interview);

    $this->actingAs($applicantUser)
        ->post(route('applicant.ai.task.submit', $interview), ['solution' => 'echo 1;'])
        ->assertSessionHas('error');

    // итог от этого не изменился бы, а в отчёте появился бы ответ,
    // которого при решении не было
    expect(AiTestSubmission::count())->toBe(0);
});

test('на своей стадии решение принимается', function () {
    [, $interview, $applicantUser] = talkable(['stage' => 'test']);

    composedTask($interview);

    $this->actingAs($applicantUser)
        ->post(route('applicant.ai.task.submit', $interview), ['solution' => 'echo 1;'])
        ->assertSessionHas('status');

    expect(AiTestSubmission::count())->toBe(1);
});

// ==================== отказ от собеседования ====================

test('отказ уносит файлы документов с диска', function () {
    [, $interview, $applicantUser] = talkable(['stage' => 'documents']);

    $path = 'ai/documents/'.$interview->id.'/diplom.pdf';
    Storage::disk('local')->put($path, 'содержимое');

    AiCandidateDocument::create([
        'ai_interview_id' => $interview->id, 'kind' => 'diploma',
        'path' => $path, 'original_name' => 'diplom.pdf',
        'mime' => 'application/pdf', 'status' => 'pending',
    ]);

    $this->actingAs($applicantUser)->delete(route('applicant.ai.decline', $interview));

    /*
     * Строки уносил каскад базы, а файл оставался на диске без единой ссылки
     * на него. Это хуже, чем не удалять вовсе: человек считает, что его
     * диплома у нас нет.
     */
    Storage::disk('local')->assertMissing($path);

    expect(AiInterview::count())->toBe(0);
});

test('после объявленного решения отказаться поздно', function () {
    [, $interview, $applicantUser] = talkable([
        'stage' => 'done', 'outcome' => 'passed', 'decided_at' => now(),
    ]);

    // решение объявлено: запись есть, кандидат текст получил
    App\Models\AiDecision::create([
        'ai_interview_id' => $interview->id, 'round' => 1,
        'documents_score' => 80, 'interview_score' => 80, 'test_score' => 80, 'total' => 80,
        'outcome' => 'passed', 'gate_reason' => 'above_accept_threshold',
        'reasons' => [], 'message_to_candidate' => 'Вы прошли отбор.',
    ]);

    $this->actingAs($applicantUser)
        ->delete(route('applicant.ai.decline', $interview))
        ->assertRedirect(route('applicant.ai.result', $interview))
        ->assertSessionHas('error');

    // иначе работодатель лишился бы отчёта по кандидату, которому сам ответил
    expect(AiInterview::count())->toBe(1);
});

test('от несостоявшегося разбора отказаться можно', function () {
    [, $interview, $applicantUser] = talkable([
        // разбор провалился дважды: исход «решает человек», но никакого
        // решения не объявлено и кандидат ничего не получил
        'stage' => 'documents', 'analysis_status' => 'failed',
        'requires_review' => true, 'outcome' => 'manual_review', 'documents_score' => null,
    ]);

    $this->actingAs($applicantUser)
        ->delete(route('applicant.ai.decline', $interview))
        ->assertRedirect(route('public.responses.index'));

    /*
     * Иначе человек оказался бы запертым: отказаться нельзя, а страница
     * результата без записи решения не открывается — значит и кнопки
     * «удалить мои данные» он не увидит.
     */
    expect(AiInterview::count())->toBe(0);
});

test('удалить свои данные можно и после решения', function () {
    [, $interview, $applicantUser] = talkable([
        'stage' => 'done', 'outcome' => 'passed', 'decided_at' => now(),
    ]);

    $path = 'ai/documents/'.$interview->id.'/d.pdf';
    Storage::disk('local')->put($path, 'содержимое');

    AiCandidateDocument::create([
        'ai_interview_id' => $interview->id, 'kind' => 'diploma',
        'path' => $path, 'original_name' => 'd.pdf',
        'mime' => 'application/pdf', 'status' => 'analysed',
    ]);

    // право удалить свои данные у человека остаётся — оно просто живёт
    // отдельной кнопкой с честным сообщением
    $this->actingAs($applicantUser)
        ->delete(route('applicant.ai.purge', $interview))
        ->assertRedirect(route('applicant.ai.index'));

    Storage::disk('local')->assertMissing($path);

    expect(AiInterview::count())->toBe(0);
});

// ==================== балл берётся с основного задания ====================

test('решение считает балл по основному заданию, а не по последнему', function () {
    [, $interview] = talkable(['stage' => 'decision']);

    $main = composedTask($interview);

    AiTestSubmission::create([
        'ai_test_task_id' => $main->id, 'content' => 'решение',
        'score' => 90, 'review' => [], 'submitted_at' => now(), 'graded_at' => now(),
    ]);

    // задание другого вида с более свежим номером
    $later = AiTestTask::create([
        'ai_interview_id' => $interview->id,
        'kind' => AiTestTask::KIND_FOLLOW_UP,
        'statement' => 'уточняющее',
        'format' => 'case',
        'reference_solution' => 'эталон',
        'rubric' => [],
        'time_limit_minutes' => 20,
    ]);

    AiTestSubmission::create([
        'ai_test_task_id' => $later->id, 'content' => 'другое',
        'score' => 10, 'review' => [], 'submitted_at' => now(), 'graded_at' => now(),
    ]);

    AiInterviewTurn::create([
        'ai_interview_id' => $interview->id, 'position' => 1, 'role' => 'ai',
        'text' => 'вопрос', 'question_kind' => 'technical', 'criterion_key' => 'php',
    ]);
    AiInterviewTurn::create([
        'ai_interview_id' => $interview->id, 'position' => 2, 'role' => 'candidate',
        'text' => 'ответ', 'criterion_key' => 'php', 'score' => 5, 'max_score' => 5,
    ]);

    (new App\Jobs\MakeHiringDecision($interview->id, App\Jobs\MakeHiringDecision::MAX_WAITS))
        ->handle(app(App\Services\Ai\DecisionWriter::class));

    // все остальные места модуля спрашивают main — решение обязано спрашивать то же
    expect($interview->fresh()->test_score)->toBe(90);
});

// ==================== несоставленное задание не вешает кандидата ====================

test('несоставленное задание уходит на ручную проверку, а не в вечное ожидание', function () {
    [, $interview, $applicantUser] = talkable(['stage' => 'test']);

    AiInterviewTurn::create([
        'ai_interview_id' => $interview->id, 'position' => 1, 'role' => 'ai',
        'text' => 'вопрос', 'question_kind' => 'technical', 'criterion_key' => 'php',
    ]);
    AiInterviewTurn::create([
        'ai_interview_id' => $interview->id, 'position' => 2, 'role' => 'candidate',
        'text' => 'ответ', 'criterion_key' => 'php', 'score' => 4, 'max_score' => 5,
    ]);

    app()->bind(App\Services\Ai\TaskExaminer::class, fn () => new GuardExaminer());
    app()->bind(App\Services\Ai\DecisionWriter::class, fn () => new GuardWriter());

    (new App\Jobs\ComposeTestTask($interview->id))->handle(app(App\Services\Ai\TaskExaminer::class));

    // решение идёт следом, в очереди — здесь исполняем его сразу
    (new App\Jobs\MakeHiringDecision($interview->id))
        ->handle(app(App\Services\Ai\DecisionWriter::class));

    $interview->refresh();

    /*
     * Раньше здесь ставилась одна пометка requires_review, и всё: собеседование
     * оставалось на стадии задания, которого не будет, решение никто не
     * запускал, страница кандидата перезагружалась каждые пять секунд вечно,
     * и ни он, ни работодатель ничего не узнавали.
     */
    expect($interview->requires_review)->toBeTrue()
        ->and($interview->stage)->toBe('done')
        ->and($interview->outcome)->toBe('manual_review')
        ->and($interview->latestDecision)->not->toBeNull();

    // обе стороны узнали
    expect(UserNotification::where('user_id', $applicantUser->id)->count())->toBeGreaterThan(0)
        ->and(UserNotification::where('user_id', $interview->vacancy->employer->user_id)
            ->where('title', 'Кандидат ждёт вашего решения')->count())->toBe(1);
});

test('провал составления ставит решение в очередь', function () {
    [, $interview] = talkable(['stage' => 'test']);

    app()->bind(App\Services\Ai\TaskExaminer::class, fn () => new GuardExaminer());

    (new App\Jobs\ComposeTestTask($interview->id))->handle(app(App\Services\Ai\TaskExaminer::class));

    Queue::assertPushed(App\Jobs\MakeHiringDecision::class);
});

test('непроверенное задание тоже уходит на решение, а не в тишину', function () {
    [, $interview] = talkable(['stage' => 'test']);

    $task = composedTask($interview);

    $submission = AiTestSubmission::create([
        'ai_test_task_id' => $task->id,
        'content' => 'решение',
        'submitted_at' => now(),
    ]);

    app()->bind(App\Services\Ai\TaskExaminer::class, fn () => new GuardExaminer(gradeFails: true));

    (new App\Jobs\GradeTestSubmission($submission->id))
        ->handle(app(App\Services\Ai\TaskExaminer::class));

    /*
     * Раньше здесь ставилась пометка и всё: решение никто не запускал,
     * кандидат ждал ответа, которого никто не собирался давать, а работодатель
     * не знал, что кандидат ждёт.
     */
    expect($interview->fresh()->requires_review)->toBeTrue();

    Queue::assertPushed(App\Jobs\MakeHiringDecision::class);
});

test('страница задания перестаёт перезагружаться, когда ждать нечего', function () {
    [, $interview, $applicantUser] = talkable([
        'stage' => 'decision', 'requires_review' => true, 'outcome' => 'manual_review',
        'decided_at' => now(),
    ]);

    $this->actingAs($applicantUser)
        ->get(route('applicant.ai.task', $interview))
        ->assertOk()
        ->assertSee('Задание не понадобится', false)
        ->assertDontSee('window.location.reload', false);
});

test('пока задание составляется, страница ждёт', function () {
    [, $interview, $applicantUser] = talkable(['stage' => 'test']);

    $this->actingAs($applicantUser)
        ->get(route('applicant.ai.task', $interview))
        ->assertOk()
        ->assertSee('Тестовое задание готовится', false)
        ->assertSee('window.location.reload', false);
});

// ==================== выбор файла и самоперезагрузка ====================

test('пока разбор идёт, формы загрузки нет', function () {
    [$vacancy] = makeAiVacancy();
    [$interview, , $applicantUser] = makeAiInterview($vacancy, [
        'consent_at' => now(), 'stage' => 'documents', 'analysis_status' => 'pending',
    ]);

    $response = $this->actingAs($applicantUser)
        ->get(route('applicant.ai.documents', $interview))
        ->assertOk();

    /*
     * Страница в это время перезагружается каждые пять секунд, и диалог выбора
     * файла закрывался под руками — выбрать ничего не удавалось. А если бы и
     * удалось, толку нет: цепочка разбора собрана из тех документов, что были
     * на момент нажатия «Продолжить», и новый в неё уже не попадёт.
     */
    $response->assertDontSee('type="file"', false)
        ->assertDontSee('Добавить документ', false)
        ->assertSee('Документы отправлены на разбор', false)
        // а перезагрузка при этом идёт — одно с другим и сталкивалось
        ->assertSee('window.location.reload', false);
});

test('до начала разбора файл выбрать можно', function () {
    [$vacancy] = makeAiVacancy();
    [$interview, , $applicantUser] = makeAiInterview($vacancy, [
        'consent_at' => now(), 'stage' => 'documents', 'analysis_status' => 'none',
    ]);

    $this->actingAs($applicantUser)
        ->get(route('applicant.ai.documents', $interview))
        ->assertOk()
        ->assertSee('type="file"', false)
        ->assertSee('Добавить документ', false)
        // страница стоит на месте: диалогу выбора ничто не мешает
        ->assertDontSee('window.location.reload', false);
});

test('у зависшего разбора форма возвращается', function () {
    [$vacancy] = makeAiVacancy();
    [$interview, , $applicantUser] = makeAiInterview($vacancy, [
        'consent_at' => now(), 'stage' => 'documents', 'analysis_status' => 'pending',
    ]);

    $interview->forceFill([
        'updated_at' => now()->subMinutes(AiInterview::ANALYSIS_PATIENCE_MINUTES + 1),
    ])->saveQuietly();

    // разбор можно запустить заново, и новый документ в цепочку попадёт
    $this->actingAs($applicantUser)
        ->get(route('applicant.ai.documents', $interview))
        ->assertOk()
        ->assertSee('type="file"', false)
        ->assertSee('Разбор затянулся', false)
        ->assertDontSee('window.location.reload', false);
});

test('страница, которая сама перезагружается, не просит ничего заполнить', function () {
    [$vacancy] = makeAiVacancy();
    [$interview, , $applicantUser] = makeAiInterview($vacancy, [
        'consent_at' => now(), 'stage' => 'documents', 'analysis_status' => 'pending',
    ]);

    $html = $this->actingAs($applicantUser)
        ->get(route('applicant.ai.documents', $interview))->getContent();

    /*
     * Общее правило, ради которого всё это и проверяется: ввод пользователя и
     * самоперезагрузка на одной странице несовместимы.
     */
    if (str_contains($html, 'window.location.reload')) {
        expect($html)->not->toContain('type="file"')
            ->and($html)->not->toContain('<textarea');
    }
});
