<?php

/*
 * Итоговое решение по кандидату.
 *
 * Самый ответственный слой модуля: здесь людям отказывают. Поэтому проверяется
 * не «работает ли», а порядок правил и границы:
 *  — решение считает код, а не модель, и оно воспроизводимо;
 *  — невыполненное обязательное требование — отказ, сколько бы ни набралось;
 *  — недосчитанная оценка означает «данных нет», а не «плохо»;
 *  — пограничный балл сначала доспрашивает, и только потом зовёт человека;
 *  — кандидату никогда не показывают внутренние цифры;
 *  — при сбое модели письмо всё равно уходит.
 */

use App\Jobs\MakeHiringDecision;
use App\Models\AiDecision;
use App\Models\AiInterview;
use App\Models\AiInterviewConfig;
use App\Models\AiInterviewCriterion;
use App\Models\AiInterviewTurn;
use App\Models\AiRequirementCheck;
use App\Models\AiTestSubmission;
use App\Models\AiTestTask;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\VacancyResponse;
use App\Services\Ai\AiInvalidAnswerException;
use App\Services\Ai\AiJournal;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\DecisionWriter;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\GeminiDecisionWriter;
use App\Services\Scoring\DecisionEngine;
use App\Services\Scoring\DecisionMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/** Подменный автор письма. */
class FakeWriter implements DecisionWriter
{
    public int $calls = 0;

    public function __construct(
        private readonly bool $available = true,
        private readonly ?string $fail = null,
        private readonly ?string $text = null,
    ) {
    }

    public function available(): bool
    {
        return $this->available;
    }

    public function write(AiInterview $interview, string $outcome, array $reasons, string $fallback): string
    {
        if ($this->fail === 'invalid') {
            throw new AiInvalidAnswerException('негодно', []);
        }

        if ($this->fail === 'unavailable') {
            throw new AiUnavailableException('квота исчерпана', 30);
        }

        $this->calls++;

        return $this->text ?? 'Живое письмо про «'.($interview->vacancy?->title ?? '—').'».';
    }
}

/**
 * Собеседование, готовое к решению: разбор готов, ответы оценены, задание сдано.
 *
 * @return array{0:AiInterview,1:User,2:\Illuminate\Support\Collection}
 */
function decisionReady(array $config = [], int $answerScore = 4, ?int $testScore = 80): array
{
    app()->instance(DecisionWriter::class, new FakeWriter);

    [$vacancy] = makeAiVacancy([
        ['key' => 'php', 'label' => 'PHP', 'kind' => 'must', 'weight' => 60],
        ['key' => 'sql', 'label' => 'SQL', 'kind' => 'nice', 'weight' => 40],
    ], $config + [
        'weight_documents' => 30, 'weight_interview' => 40, 'weight_test' => 30,
        'threshold_reject' => 45, 'threshold_accept' => 70,
    ]);

    [$interview, , $user] = makeAiInterview($vacancy, [
        'consent_at' => now(), 'stage' => 'decision', 'analysis_status' => 'ready',
        'documents_score' => 80,
    ]);

    $criteria = AiInterviewCriterion::where('vacancy_id', $vacancy->id)->confirmed()->get();

    // все требования закрыты документами
    foreach ($criteria as $criterion) {
        AiRequirementCheck::create([
            'ai_interview_id' => $interview->id,
            'criterion_id' => $criterion->id,
            'requirement' => $criterion->label,
            'kind' => $criterion->kind,
            'status' => 'found',
            'evidence_quote' => 'из резюме',
            'origin' => 'documents',
        ]);
    }

    // оценённая пара «вопрос — ответ»
    AiInterviewTurn::create([
        'ai_interview_id' => $interview->id, 'position' => 1,
        'role' => 'ai', 'text' => 'вопрос', 'criterion_key' => 'php',
    ]);
    AiInterviewTurn::create([
        'ai_interview_id' => $interview->id, 'position' => 2,
        'role' => 'candidate', 'text' => 'ответ', 'criterion_key' => 'php',
        'score' => $answerScore, 'max_score' => 5,
    ]);

    if ($testScore !== null) {
        $task = AiTestTask::create([
            'ai_interview_id' => $interview->id, 'kind' => 'main',
            'statement' => 'задание', 'format' => 'case',
            'reference_solution' => 'эталон', 'rubric' => [['point' => 'пункт', 'weight' => 100]],
            'issued_at' => now(), 'deadline_at' => now()->addHour(),
        ]);

        AiTestSubmission::create([
            'ai_test_task_id' => $task->id, 'content' => 'решение',
            'score' => $testScore, 'review' => [], 'submitted_at' => now(), 'graded_at' => now(),
        ]);
    }

    return [$interview->fresh(), $user, $criteria];
}

/** Короткий вызов движка на готовом собеседовании. */
function decide(AiInterview $interview, $criteria, ?int $testScore = 80): array
{
    return DecisionEngine::decide(
        $interview,
        $interview->config,
        $criteria,
        $interview->requirementChecks()->get(),
        $interview->turns()->get(),
        $testScore,
    );
}

// ==================== арифметика ====================

test('итог — взвешенная сумма трёх частей', function () {
    [$interview, , $criteria] = decisionReady();

    $verdict = decide($interview, $criteria);

    /*
     * документы 80 (вес 30), интервью 80 — четыре из пяти (вес 40),
     * задание 80 (вес 30) => 80
     */
    expect($verdict['parts']['documents'])->toBe(80)
        ->and($verdict['parts']['interview'])->toBe(80)
        ->and($verdict['parts']['test'])->toBe(80)
        ->and($verdict['total'])->toBe(80)
        ->and($verdict['outcome'])->toBe(DecisionEngine::PASSED);
});

test('нулевой вес части не занижает итог', function () {
    // работодатель решил обойтись без задания
    [$interview, , $criteria] = decisionReady([
        'weight_documents' => 50, 'weight_interview' => 50, 'weight_test' => 0,
    ], answerScore: 5, testScore: 0);

    $verdict = decide($interview, $criteria, 0);

    // 50*80 + 50*100 из 100 = 90, а не 60 с делением на все три веса
    expect($verdict['total'])->toBe(90)
        ->and($verdict['outcome'])->toBe(DecisionEngine::PASSED);
});

test('решение воспроизводимо: те же данные — тот же исход', function () {
    [$interview, , $criteria] = decisionReady();

    $first = decide($interview, $criteria);
    $second = decide($interview, $criteria);

    // Здесь нет ни одного обращения к модели, поэтому пересчёт обязан давать
    // то же самое — иначе объяснить решение было бы нечем.
    expect($first)->toBe($second);
});

// ==================== ворота ====================

test('невыполненное обязательное требование — отказ, сколько бы ни набралось', function () {
    [$interview, , $criteria] = decisionReady(answerScore: 5, testScore: 100);

    AiRequirementCheck::where('ai_interview_id', $interview->id)
        ->where('kind', 'must')->update(['status' => 'missing']);

    $verdict = decide($interview, $criteria, 100);

    /*
     * Иначе вакансия «нужен электрик» пропускала бы повара с красивыми
     * ответами: по разговору и заданию он наберёт много, а работать не сможет.
     */
    expect($verdict['outcome'])->toBe(DecisionEngine::REJECTED)
        ->and($verdict['gate_reason'])->toBe('missing_must_have')
        ->and($verdict['total'])->toBeGreaterThan(70)
        ->and($verdict['unmet'])->toBe(['PHP']);
});

test('частично закрытое обязательное требование ворот не открывает, но и не отказ', function () {
    [$interview, , $criteria] = decisionReady();

    AiRequirementCheck::where('ai_interview_id', $interview->id)
        ->where('kind', 'must')->update(['status' => 'partial']);

    $verdict = decide($interview, $criteria);

    /*
     * «Нашлось наполовину» — не «не нашлось».
     *
     * Partial означает неполное доказательство, и отказывать по нему нечестно:
     * ровно эту неясность собеседование и существует разрешать. Если и разговор
     * не поднял требование до подтверждённого, правильный исход — «решает
     * человек», как и всюду, где системе не хватает данных.
     *
     * Живой прогон показал цену прежнего поведения: кандидат с резюме
     * «PHP-разработчик, 4 года, Laravel, MySQL» получал автоматический отказ,
     * потому что модель поставила php и sql статус partial.
     */
    expect($verdict['outcome'])->toBe(DecisionEngine::MANUAL)
        ->and($verdict['gate_reason'])->toBe('unconfirmed_must_have')
        ->and($verdict['requires_manual_review'])->toBeTrue()
        // пропускать его тоже нельзя — ворота закрыты
        ->and($verdict['outcome'])->not->toBe(DecisionEngine::PASSED)
        ->and($verdict['unmet'])->toBe(['PHP']);
});

test('ничего не найденное обязательное требование — отказ, а частичное рядом не спасает', function () {
    [$interview, , $criteria] = decisionReady(answerScore: 5, testScore: 100);

    // одно требование не подтверждено вовсе — этого достаточно для отказа
    AiRequirementCheck::where('ai_interview_id', $interview->id)
        ->where('kind', 'must')->update(['status' => 'missing']);

    $verdict = decide($interview, $criteria, 100);

    expect($verdict['outcome'])->toBe(DecisionEngine::REJECTED)
        ->and($verdict['gate_reason'])->toBe('missing_must_have');
});

test('подтверждённое на собеседовании требование ворота открывает', function () {
    [$interview, , $criteria] = decisionReady();

    AiRequirementCheck::where('ai_interview_id', $interview->id)
        ->where('kind', 'must')->update(['status' => 'confirmed_in_interview']);

    // навык у человека есть, просто в резюме о нём не написано
    expect(decide($interview, $criteria)['outcome'])->toBe(DecisionEngine::PASSED);
});

test('незакрытое желательное требование отказом не является', function () {
    [$interview, , $criteria] = decisionReady();

    AiRequirementCheck::where('ai_interview_id', $interview->id)
        ->where('kind', 'nice')->update(['status' => 'missing']);

    expect(decide($interview, $criteria)['outcome'])->toBe(DecisionEngine::PASSED);
});

// ==================== пороги ====================

test('балл ниже порога отказа — отказ с названной причиной', function () {
    [$interview, , $criteria] = decisionReady(answerScore: 1, testScore: 10);
    $interview->update(['documents_score' => 20]);

    AiRequirementCheck::where('ai_interview_id', $interview->id)
        ->where('kind', 'nice')->update(['status' => 'missing']);

    $verdict = decide($interview->fresh(), $criteria, 10);

    expect($verdict['outcome'])->toBe(DecisionEngine::REJECTED)
        ->and($verdict['gate_reason'])->toBe('below_reject_threshold')
        ->and($verdict['reasons'])->not->toBeEmpty();
});

test('балл выше порога приёма — кандидат прошёл', function () {
    [$interview, , $criteria] = decisionReady(answerScore: 5, testScore: 95);

    $verdict = decide($interview, $criteria, 95);

    expect($verdict['outcome'])->toBe(DecisionEngine::PASSED)
        ->and($verdict['gate_reason'])->toBe('above_accept_threshold')
        ->and($verdict['reasons'])->toBe([]);
});

// ==================== пограничная зона ====================

test('пограничный балл сначала доспрашивает', function () {
    // 30*60 + 40*60 + 30*60 = 60: между 45 и 70
    [$interview, , $criteria] = decisionReady(answerScore: 3, testScore: 60);
    $interview->update(['documents_score' => 60]);

    $verdict = decide($interview->fresh(), $criteria, 60);

    expect($verdict['outcome'])->toBe(DecisionEngine::FOLLOW_UP)
        ->and($verdict['gate_reason'])->toBe('borderline')
        ->and($verdict['requires_manual_review'])->toBeFalse();
});

test('после дораунда пограничный балл уходит человеку', function () {
    [$interview, , $criteria] = decisionReady(answerScore: 3, testScore: 60);
    $interview->update(['documents_score' => 60, 'follow_up_round' => 1]);

    $verdict = decide($interview->fresh(), $criteria, 60);

    // Дальше спрашивать бессмысленно: если и после уточнений неясно,
    // это работа для человека.
    expect($verdict['outcome'])->toBe(DecisionEngine::MANUAL)
        ->and($verdict['gate_reason'])->toBe('borderline_after_follow_up')
        ->and($verdict['requires_manual_review'])->toBeTrue();
});

// ==================== «данных нет» ====================

test('неоценённый ответ означает «данных нет», а не «плохо»', function () {
    [$interview, , $criteria] = decisionReady();

    AiInterviewTurn::create([
        'ai_interview_id' => $interview->id, 'position' => 3,
        'role' => 'candidate', 'text' => 'ещё ответ',
    ]);

    $verdict = decide($interview->fresh(), $criteria);

    /*
     * Решить по недосчитанному баллу — значит отказать человеку из-за того,
     * что очередь не успела, а не из-за его ответов.
     */
    expect($verdict['outcome'])->toBe(DecisionEngine::MANUAL)
        ->and($verdict['gate_reason'])->toBe('invalid_ai_response')
        ->and($verdict['reasons'][0])->toContain('Оценены не все ответы');
});

test('непроверенное задание тоже останавливает решение', function () {
    [$interview, , $criteria] = decisionReady();

    $verdict = decide($interview, $criteria, null);

    expect($verdict['outcome'])->toBe(DecisionEngine::MANUAL)
        ->and($verdict['reasons'][0])->toContain('не проверено');
});

test('неразобранные документы останавливают решение', function () {
    [$interview, , $criteria] = decisionReady();
    $interview->update(['analysis_status' => 'failed', 'documents_score' => null]);

    $verdict = decide($interview->fresh(), $criteria);

    expect($verdict['outcome'])->toBe(DecisionEngine::MANUAL)
        ->and($verdict['gate_reason'])->toBe('empty_documents');
});

test('помеченное на ручную проверку решается человеком без разговоров', function () {
    [$interview, , $criteria] = decisionReady();
    $interview->update(['requires_review' => true]);

    expect(decide($interview->fresh(), $criteria)['outcome'])->toBe(DecisionEngine::MANUAL);
});

// ==================== объявление решения ====================

test('решение объявляется и пишется снимком', function () {
    Queue::fake();
    [$interview, $user, $criteria] = decisionReady(answerScore: 5, testScore: 95);

    (new MakeHiringDecision($interview->id))->handle(new FakeWriter);

    $interview->refresh();
    $decision = AiDecision::sole();

    expect($interview->outcome)->toBe('passed')
        ->and($interview->stage)->toBe('done')
        ->and($interview->decided_at)->not->toBeNull()
        ->and($decision->round)->toBe(1)
        ->and($decision->total)->toBe($interview->total_score)
        ->and($decision->gate_reason)->toBe('above_accept_threshold')
        ->and($decision->message_to_candidate)->toContain('Живое письмо');
});

test('статус отклика ставится в режиме auto', function () {
    Queue::fake();
    [$interview] = decisionReady(answerScore: 5, testScore: 95);

    (new MakeHiringDecision($interview->id))->handle(new FakeWriter);

    expect(VacancyResponse::find($interview->vacancy_response_id)->status)->toBe('accepted');
});

test('в режиме advisory статус отклика не трогают', function () {
    Queue::fake();
    [$interview] = decisionReady(['decision_mode' => 'advisory'], answerScore: 5, testScore: 95);

    (new MakeHiringDecision($interview->id))->handle(new FakeWriter);

    // ИИ здесь советчик: переписывать отклик за работодателя — обман
    // договорённости
    expect(VacancyResponse::find($interview->vacancy_response_id)->status)->toBe('new')
        ->and($interview->fresh()->outcome)->toBe('passed');
});

test('ручная проверка отклик не трогает ни в каком режиме', function () {
    Queue::fake();
    [$interview] = decisionReady();
    $interview->update(['requires_review' => true]);

    (new MakeHiringDecision($interview->id))->handle(new FakeWriter);

    expect(VacancyResponse::find($interview->vacancy_response_id)->status)->toBe('new')
        ->and($interview->fresh()->outcome)->toBe('manual_review');
});

test('обе стороны получают уведомление', function () {
    Queue::fake();
    [$interview] = decisionReady(answerScore: 5, testScore: 95);

    (new MakeHiringDecision($interview->id))->handle(new FakeWriter);

    expect(UserNotification::where('user_id', $interview->applicant->user_id)
        ->where('title', 'Вы прошли отбор')->exists())->toBeTrue()
        ->and(UserNotification::where('user_id', $interview->vacancy->employer->user_id)
            ->where('title', 'ИИ принял решение по кандидату')->exists())->toBeTrue();
});

test('при ручной проверке работодателя зовут прямо', function () {
    Queue::fake();
    [$interview] = decisionReady();
    $interview->update(['requires_review' => true]);

    (new MakeHiringDecision($interview->id))->handle(new FakeWriter);

    expect(UserNotification::where('title', 'Кандидат ждёт вашего решения')->exists())->toBeTrue();
});

test('решение принимается один раз', function () {
    Queue::fake();
    [$interview] = decisionReady(answerScore: 5, testScore: 95);

    $writer = new FakeWriter;
    (new MakeHiringDecision($interview->id))->handle($writer);
    (new MakeHiringDecision($interview->id))->handle($writer);

    expect($writer->calls)->toBe(1)
        ->and(AiDecision::count())->toBe(1);
});

test('пограничный балл возвращает кандидата в разговор', function () {
    Queue::fake();
    [$interview] = decisionReady(answerScore: 3, testScore: 60);
    $interview->update(['documents_score' => 60]);

    (new MakeHiringDecision($interview->id))->handle(new FakeWriter);

    $interview->refresh();

    expect($interview->stage)->toBe('interview')
        ->and($interview->follow_up_round)->toBe(1)
        ->and($interview->outcome)->toBeNull()
        // решение не объявлено: ни отказа, ни приглашения
        ->and(AiDecision::count())->toBe(0);

    // и цель по вопросам выросла, иначе уточнять негде
    expect($interview->questionTarget())
        ->toBe($interview->config->questions_count + MakeHiringDecision::FOLLOW_UP_QUESTIONS);
});

test('очередь не успела — ждём, а не зовём человека', function () {
    Queue::fake();
    [$interview] = decisionReady();

    // ответ ещё не оценён
    AiInterviewTurn::create([
        'ai_interview_id' => $interview->id, 'position' => 3,
        'role' => 'candidate', 'text' => 'ещё ответ',
    ]);

    (new MakeHiringDecision($interview->id))->handle(new FakeWriter);

    // Позвать человека из-за того, что очередь на секунду отстала, было бы
    // напрасной тревогой: задача переставляет себя на потом.
    expect($interview->fresh()->outcome)->toBeNull()
        ->and(AiDecision::count())->toBe(0);

    Queue::assertPushed(MakeHiringDecision::class);
});

test('после долгого ожидания решение всё-таки принимается', function () {
    Queue::fake();
    [$interview] = decisionReady();

    AiInterviewTurn::create([
        'ai_interview_id' => $interview->id, 'position' => 3,
        'role' => 'candidate', 'text' => 'ещё ответ',
    ]);

    // терпение исчерпано
    (new MakeHiringDecision($interview->id, MakeHiringDecision::MAX_WAITS))->handle(new FakeWriter);

    expect($interview->fresh()->outcome)->toBe('manual_review');
});

// ==================== письмо кандидату ====================

test('без модели письмо всё равно уходит', function () {
    Queue::fake();
    [$interview] = decisionReady(answerScore: 5, testScore: 95);

    (new MakeHiringDecision($interview->id))->handle(new FakeWriter(available: false));

    $decision = AiDecision::sole();

    // Кандидат не должен остаться без ответа из-за того, что кончилась квота.
    expect($decision->message_to_candidate)->toContain('Поздравляем')
        ->and($decision->message_to_candidate)->toContain($interview->vacancy->title);
});

test('сбой модели не оставляет кандидата без письма', function () {
    Queue::fake();

    foreach (['invalid', 'unavailable'] as $failure) {
        [$interview] = decisionReady(answerScore: 5, testScore: 95);

        (new MakeHiringDecision($interview->id))->handle(new FakeWriter(fail: $failure));

        // второй аргумент toContain — это ещё одна искомая строка, а не
        // пояснение к провалу, поэтому проверяем по одной
        expect($interview->fresh()->latestDecision->message_to_candidate)
            ->toContain('Поздравляем');
    }
});

test('шаблон отказа называет причину и не грубит', function () {
    [$interview] = decisionReady();

    $text = DecisionMessage::template(DecisionEngine::REJECTED, $interview, ['PHP', 'SQL']);

    expect($text)->toContain('Спасибо')
        ->toContain('php и sql')
        ->toContain('не оценка вас как специалиста')
        ->toContain('Посмотрите другие предложения')
        // внутренних цифр в письме нет
        ->and(preg_match('/\d+\s*(%|балл)/u', $text))->toBe(0);
});

test('шаблон приёма обещает срок, заданный работодателем', function () {
    [$interview] = decisionReady(['response_sla' => 'в течение двух дней']);

    $text = DecisionMessage::template(DecisionEngine::PASSED, $interview);

    expect($text)->toContain('Поздравляем')
        ->toContain('в течение двух дней')
        ->toContain($interview->vacancy->title);
});

test('при ручной проверке письмо не обещает ни отказа, ни приёма', function () {
    [$interview] = decisionReady();

    $text = DecisionMessage::template(DecisionEngine::MANUAL, $interview);

    expect($text)->toContain('решение принимает человек')
        ->not->toContain('Поздравляем')
        ->not->toContain('остановились на других');
});

test('перечисление причин звучит по-человечески', function () {
    [$interview] = decisionReady();

    $one = DecisionMessage::template(DecisionEngine::REJECTED, $interview, ['SQL']);
    $three = DecisionMessage::template(DecisionEngine::REJECTED, $interview, ['SQL', 'Docker', 'английскому']);

    expect($one)->toContain('по sql')
        ->and($three)->toContain('sql, docker и английскому');
});

// ==================== автор письма ====================

function realWriter(): GeminiDecisionWriter
{
    return new GeminiDecisionWriter(new AiJournal(new GeminiClient([
        'key' => 'k', 'model' => 'gemini-3.5-flash-lite',
        'model_scoring' => 'gemini-3.6-flash', 'model_vision' => 'gemini-3.6-flash',
        'revision' => 'r', 'timeout' => 5, 'connect_timeout' => 3,
    ])));
}

function writerAnswer(string $message): array
{
    return ['steps' => [['type' => 'model_output', 'content' => [
        ['type' => 'text', 'text' => json_encode(['message' => $message], JSON_UNESCAPED_UNICODE)],
    ]]]];
}

test('модель не получает ни баллов, ни порогов', function () {
    [$interview] = decisionReady();

    Http::fake(['*' => Http::response(writerAnswer(
        'Спасибо за разговор. К сожалению, в этот раз мы остановились на других кандидатах.'
    ))]);

    realWriter()->write($interview, DecisionEngine::REJECTED, ['SQL'],
        DecisionMessage::template(DecisionEngine::REJECTED, $interview, ['SQL']));

    Http::assertSent(function ($request) {
        $data = $request['input'][0]['content'][0]['text'];
        $all = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

        /*
         * Лишние данные в промпте рано или поздно просачиваются в ответ, а
         * кандидату внутренние цифры не показывают. Поэтому их туда и не кладут.
         *
         * Ищем именно значения: слово «баллов» в инструкции есть — она их и
         * запрещает, — а вот самих чисел 80, 70 и 45 быть не должно нигде.
         */
        return ! str_contains($all, 'threshold')
            && ! str_contains($all, '"total"')
            && ! str_contains($data, '80')
            && ! str_contains($data, '70')
            && ! str_contains($data, '45');
    });
});

test('просочившийся в письмо процент откатывает к шаблону', function () {
    [$interview] = decisionReady();

    // запрет в промпте — просьба, а не гарантия
    Http::fake(['*' => Http::response(writerAnswer(
        'Спасибо за разговор! К сожалению, вы набрали 58 баллов, и этого не хватило для прохождения отбора по вакансии.'
    ))]);

    $fallback = DecisionMessage::template(DecisionEngine::REJECTED, $interview, ['SQL']);
    $text = realWriter()->write($interview, DecisionEngine::REJECTED, ['SQL'], $fallback);

    expect($text)->toBe($fallback)
        ->and($text)->not->toContain('58');
});

test('промпт отказа требует назвать причину и запрещает жалость', function () {
    [$interview] = decisionReady();

    Http::fake(['*' => Http::response(writerAnswer(
        'Спасибо за разговор. В этот раз мы остановились на других кандидатах.'
    ))]);

    realWriter()->write($interview, DecisionEngine::REJECTED, ['SQL'], 'образец текста письма');

    Http::assertSent(function ($request) {
        $prompt = $request['system_instruction'];

        return str_contains($prompt, 'Причина')
            && str_contains($prompt, 'НИКАКИХ ЦИФР')
            && str_contains($prompt, 'Сочувствие в трёх абзацах');
    });
});

test('образец письма уходит модели как опора', function () {
    [$interview] = decisionReady();

    Http::fake(['*' => Http::response(writerAnswer('Живое письмо про вакансию и следующие шаги.'))]);

    realWriter()->write($interview, DecisionEngine::PASSED, [], 'ОБРАЗЕЦ ДЛЯ ПРОВЕРКИ');

    Http::assertSent(fn ($r) => str_contains($r['input'][0]['content'][0]['text'], 'ОБРАЗЕЦ ДЛЯ ПРОВЕРКИ'));
});

// ==================== страница результата ====================

test('кандидат видит решение и не видит баллов', function () {
    Queue::fake();
    [$interview, $user] = decisionReady(answerScore: 5, testScore: 95);

    (new MakeHiringDecision($interview->id))->handle(new FakeWriter);

    $response = $this->actingAs($user)->get(route('applicant.ai.result', $interview->fresh()))
        ->assertOk()
        ->assertSee('Прошёл отбор', false)
        ->assertSee('Живое письмо', false);

    // ни итогового балла, ни частей на странице нет
    $html = $response->getContent();

    expect($html)->not->toContain('total_score')
        ->and($html)->not->toContain('documents_score');
});

test('до решения страница результата отправляет назад', function () {
    Queue::fake();
    [$interview, $user] = decisionReady();

    $this->actingAs($user)->get(route('applicant.ai.result', $interview))
        ->assertRedirect(route('applicant.ai.interview', $interview));
});

test('после решения кандидата ведут на результат', function () {
    Queue::fake();
    [$interview, $user] = decisionReady(answerScore: 5, testScore: 95);

    (new MakeHiringDecision($interview->id))->handle(new FakeWriter);

    $this->actingAs($user)->get(route('applicant.ai.interview', $interview->fresh()))
        ->assertRedirect(route('applicant.ai.result', $interview));
});

test('чужой результат не посмотреть', function () {
    Queue::fake();
    [$interview] = decisionReady(answerScore: 5, testScore: 95);
    (new MakeHiringDecision($interview->id))->handle(new FakeWriter);

    $stranger = User::factory()->applicant()->create();
    makeApplicant($stranger);

    $this->actingAs($stranger)->get(route('applicant.ai.result', $interview->fresh()))
        ->assertForbidden();
});

test('письмо кандидату в базе зашифровано', function () {
    Queue::fake();
    [$interview] = decisionReady(answerScore: 5, testScore: 95);

    (new MakeHiringDecision($interview->id))->handle(new FakeWriter(text: 'Поздравляем, Далер!'));

    $decision = AiDecision::sole();

    expect($decision->message_to_candidate)->toContain('Далер')
        ->and(rawColumn('ai_decisions', $decision->id, 'message_to_candidate'))->not->toContain('Далер');
});
