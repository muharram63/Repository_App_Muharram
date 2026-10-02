<?php

/*
 * Как кандидат вообще попадает в ИИ-собеседование.
 *
 * Самое важное здесь — что путь внутрь есть не только в ту секунду, когда
 * человек нажал «откликнуться». Собеседование предлагалось редиректом и
 * уведомлением, а дальше все ссылки на него жили внутри его же страниц.
 * Откликнувшийся до того, как работодатель включил ИИ — обычный случай:
 * сперва вакансия, потом настройка, — попасть внутрь не мог никак. Отклик
 * лежит, и ничего не происходит; со стороны это и выглядит как «ИИ-
 * собеседование не работает».
 *
 * Здесь же — настройки, при которых собеседование формально включено, а по
 * смыслу не работает: пороги без пограничной зоны и критерии, забытые
 * неподтверждёнными.
 */

use App\Models\AiInterview;
use App\Models\AiInterviewConfig;
use App\Models\AiInterviewCriterion;
use App\Models\User;
use App\Models\VacancyResponse;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

/**
 * Соискатель, уже откликнувшийся на вакансию с рабочим ИИ-собеседованием.
 *
 * @return array{0:App\Models\Vacancy,1:User,2:App\Models\Applicant}
 */
function respondedTo(array $config = []): array
{
    [$vacancy] = makeAiVacancy(null, $config);

    $user = User::factory()->applicant()->create();
    $applicant = makeApplicant($user);
    makeResume($applicant);

    VacancyResponse::create([
        'applicant_id' => $applicant->id,
        'vacancy_id' => $vacancy->id,
    ]);

    return [$vacancy, $user, $applicant];
}

// ==================== путь внутрь со страницы откликов ====================

test('откликнувшемуся предлагают пройти собеседование со страницы откликов', function () {
    [$vacancy, $user] = respondedTo();

    /*
     * Отклик сделан до того, как работодатель включил ИИ, — значит ни редиректа,
     * ни уведомления у человека не было. Единственное место, куда он придёт
     * сам, — «Отклики».
     */
    $this->actingAs($user)->get(route('public.responses.index'))
        ->assertOk()
        ->assertSee('По этой вакансии собеседование проводит ИИ', false)
        ->assertSee(route('applicant.ai.start', $vacancy), false);
});

test('по этой ссылке собеседование и начинается', function () {
    [$vacancy, $user] = respondedTo();

    $this->actingAs($user)->get(route('applicant.ai.start', $vacancy))->assertRedirect();

    expect(AiInterview::count())->toBe(1);
});

test('начатое собеседование предлагают продолжить, а не начать заново', function () {
    [$vacancy, $user, $applicant] = respondedTo();

    $interview = AiInterview::create([
        'vacancy_id' => $vacancy->id,
        'applicant_id' => $applicant->id,
        'consent_at' => now(),
        'stage' => 'interview',
        'analysis_status' => 'ready',
    ]);

    $this->actingAs($user)->get(route('public.responses.index'))
        ->assertOk()
        ->assertSee('Продолжить собеседование', false)
        ->assertSee(route('applicant.ai.interview', $interview), false)
        ->assertDontSee('Пройти ИИ-собеседование', false);
});

test('пройденное собеседование ведёт к результату', function () {
    [$vacancy, $user, $applicant] = respondedTo();

    $interview = AiInterview::create([
        'vacancy_id' => $vacancy->id,
        'applicant_id' => $applicant->id,
        'consent_at' => now(),
        'stage' => 'done',
        'outcome' => 'rejected',
        'decided_at' => now(),
    ]);

    $this->actingAs($user)->get(route('public.responses.index'))
        ->assertOk()
        ->assertSee('Собеседование пройдено', false)
        ->assertSee(route('applicant.ai.result', $interview), false);
});

test('выключенное собеседование не предлагают', function () {
    [$vacancy, $user] = respondedTo();

    AiInterviewConfig::where('vacancy_id', $vacancy->id)->update(['enabled' => false]);

    $this->actingAs($user)->get(route('public.responses.index'))
        ->assertOk()
        ->assertDontSee('Пройти ИИ-собеседование', false);
});

test('включённое с негодными настройками тоже не предлагают', function () {
    // иначе кандидата вели бы в тупик: собеседование не сможет его оценить
    [$vacancy, $user] = respondedTo(['threshold_reject' => 50, 'threshold_accept' => 55]);

    $this->actingAs($user)->get(route('public.responses.index'))
        ->assertOk()
        ->assertDontSee('Пройти ИИ-собеседование', false);
});

test('на чужой отклик приглашение не попадает', function () {
    [$vacancy] = respondedTo();

    $stranger = User::factory()->applicant()->create();
    $strangerApplicant = makeApplicant($stranger);
    makeResume($strangerApplicant);

    // откликнулся на ту же вакансию, но собеседования у него нет
    VacancyResponse::create([
        'applicant_id' => $strangerApplicant->id,
        'vacancy_id' => $vacancy->id,
    ]);

    $response = $this->actingAs($stranger)->get(route('public.responses.index'))->assertOk();

    // приглашение видит, а чужое собеседование — нет
    $response->assertSee('Пройти ИИ-собеседование', false)
        ->assertDontSee('Продолжить собеседование', false);
});

test('страница откликов работодателя не падает от нового блока', function () {
    [$vacancy, , $employerUser] = makeAiVacancy();

    $this->actingAs($employerUser)->get(route('public.responses.index'))->assertOk();
});

// ==================== пороги, при которых отбор не отбирает ====================

test('пороги без пограничной зоны не дают включить собеседование', function () {
    [$vacancy] = makeAiVacancy();

    $config = AiInterviewConfig::where('vacancy_id', $vacancy->id)->sole();

    /*
     * Живой случай: пороги 3 и 5 на шкале 0–100. Формально зазор есть, по
     * смыслу — «пропускай всех»: кандидат с одной наполовину закрытой строкой
     * требований уже набирает больше пяти. И пограничной зоны в два балла не
     * бывает — уточняющие вопросы не случатся никогда.
     */
    $config->update(['threshold_reject' => 3, 'threshold_accept' => 5]);

    expect($config->fresh()->thresholdsAreValid())->toBeFalse()
        ->and($config->fresh()->isUsable())->toBeFalse()
        ->and($config->fresh()->problems()[0])->toContain('не меньше чем на 10');
});

test('ровно минимальный зазор проходит', function () {
    [$vacancy] = makeAiVacancy();

    $config = AiInterviewConfig::where('vacancy_id', $vacancy->id)->sole();
    $config->update([
        'threshold_reject' => 40,
        'threshold_accept' => 40 + AiInterviewConfig::MIN_THRESHOLD_GAP,
    ]);

    expect($config->fresh()->thresholdsAreValid())->toBeTrue();
});

test('низкий порог приёма не блокирует, но о нём говорят', function () {
    [$vacancy, , $employerUser] = makeAiVacancy();

    $config = AiInterviewConfig::where('vacancy_id', $vacancy->id)->sole();
    $config->update(['threshold_reject' => 5, 'threshold_accept' => 20]);

    // зазор есть — включить можно, решение работодателя мы не отменяем
    expect($config->fresh()->thresholdsAreValid())->toBeTrue()
        ->and($config->fresh()->acceptIsWeak())->toBeTrue();

    // но вслух говорим, что отбор почти никого не отсеет
    $this->actingAs($employerUser)->get(route('employer.ai.show', $vacancy))
        ->assertOk()
        ->assertSee('Порог приёма очень низкий', false);
});

// ==================== забытые неподтверждённые критерии ====================

test('неподтверждённые критерии видно, а не прячутся за пунктиром', function () {
    [$vacancy, , $employerUser] = makeAiVacancy([
        ['key' => 'php', 'label' => 'PHP', 'kind' => 'must', 'weight' => 90],
    ]);

    // четыре предложенных моделью, ни один не подтверждён
    foreach (['sql', 'api', 'team', 'english'] as $position => $key) {
        AiInterviewCriterion::create([
            'vacancy_id' => $vacancy->id, 'key' => $key, 'label' => mb_strtoupper($key),
            'kind' => 'nice', 'weight' => 50, 'source' => 'ai',
            'confirmed_at' => null, 'position' => $position + 1,
        ]);
    }

    /*
     * Живой случай: из пяти критериев подтверждён был один — и отбор проверял
     * только его. Выглядело всё готовым: критерии на месте, собеседование
     * включено.
     */
    $this->actingAs($employerUser)->get(route('employer.ai.show', $vacancy))
        ->assertOk()
        ->assertSee('Ждут подтверждения:', false)
        ->assertSee('в оценке не участвует', false)
        ->assertSee('не подтверждён', false);
});

test('когда всё подтверждено, плашки нет', function () {
    [$vacancy, , $employerUser] = makeAiVacancy();

    $this->actingAs($employerUser)->get(route('employer.ai.show', $vacancy))
        ->assertOk()
        ->assertDontSee('Ждут подтверждения:', false);
});

// ==================== модель не может сделать обязательным всё ====================

test('из предложенного моделью обязательными остаются не больше четырёх', function () {
    $suggested = [];

    foreach ([90, 85, 70, 60, 40, 30] as $i => $weight) {
        $suggested[] = [
            'key' => 'k'.$i,
            'label' => 'Требование '.$i,
            'description' => 'что проверяем',
            'kind' => 'must',
            'weight' => $weight,
        ];
    }

    Illuminate\Support\Facades\Http::fake(['*' => Illuminate\Support\Facades\Http::response([
        'id' => 'int_1',
        'model' => 'gemini-3.6-flash',
        'usage' => ['total_input_tokens' => 10, 'total_output_tokens' => 10, 'total_thought_tokens' => 0],
        'steps' => [[
            'type' => 'model_output',
            'content' => [['type' => 'text', 'text' => json_encode([
                'criteria' => $suggested,
                'enough_data' => true,
            ], JSON_UNESCAPED_UNICODE)]],
        ]],
    ])]);

    [$vacancy] = makeAiVacancy([]);

    $result = app(App\Services\Ai\GeminiCriteriaAdvisor::class)->suggest($vacancy);

    $kinds = collect($result);

    /*
     * Промпт просит «обязательных не больше четырёх», но живой вызов вернул
     * обязательными все. Правило, от которого зависит исход, должно исполняться
     * кодом: пять обязательных — это отказ по любому незакрытому пункту мимо
     * всей арифметики, то есть лотерея.
     */
    expect($kinds->where('kind', 'must')->count())
        ->toBe(App\Services\Ai\GeminiCriteriaAdvisor::MAX_MUST);

    // понижены наименее важные — вес ставила сама модель
    expect($kinds->where('kind', 'must')->pluck('weight')->sort()->values()->all())
        ->toBe([60, 70, 85, 90]);
});

test('четыре обязательных проходят без понижения', function () {
    Illuminate\Support\Facades\Http::fake(['*' => Illuminate\Support\Facades\Http::response([
        'id' => 'int_1',
        'model' => 'gemini-3.6-flash',
        'usage' => ['total_input_tokens' => 10, 'total_output_tokens' => 10, 'total_thought_tokens' => 0],
        'steps' => [[
            'type' => 'model_output',
            'content' => [['type' => 'text', 'text' => json_encode([
                'criteria' => [
                    ['key' => 'a', 'label' => 'Первое требование', 'description' => '', 'kind' => 'must', 'weight' => 50],
                    ['key' => 'b', 'label' => 'Второе требование', 'description' => '', 'kind' => 'must', 'weight' => 40],
                    ['key' => 'c', 'label' => 'Третье требование', 'description' => '', 'kind' => 'must', 'weight' => 30],
                    ['key' => 'd', 'label' => 'Четвёртое требование', 'description' => '', 'kind' => 'must', 'weight' => 20],
                    ['key' => 'e', 'label' => 'Пятое требование', 'description' => '', 'kind' => 'nice', 'weight' => 10],
                ],
                'enough_data' => true,
            ], JSON_UNESCAPED_UNICODE)]],
        ]],
    ])]);

    [$vacancy] = makeAiVacancy([]);

    $result = app(App\Services\Ai\GeminiCriteriaAdvisor::class)->suggest($vacancy);

    expect(collect($result)->where('kind', 'must')->count())->toBe(4);
});

// ==================== зависший разбор не вешает кандидата ====================

test('разбор, помеченный идущим слишком давно, считается зависшим', function () {
    [$vacancy] = makeAiVacancy();
    [$interview] = makeAiInterview($vacancy, [
        'consent_at' => now(), 'stage' => 'documents', 'analysis_status' => 'pending',
    ]);

    expect($interview->analysisStalled())->toBeFalse();

    // задача ушла в очередь, а обработчика нет — типичный случай, когда
    // не запущен queue:work
    $interview->forceFill([
        'updated_at' => now()->subMinutes(AiInterview::ANALYSIS_PATIENCE_MINUTES + 1),
    ])->saveQuietly();

    expect($interview->fresh()->analysisStalled())->toBeTrue();
});

test('зависший разбор говорит правду и не перезагружает страницу', function () {
    [$vacancy] = makeAiVacancy();
    [$interview, , $applicantUser] = makeAiInterview($vacancy, [
        'consent_at' => now(), 'stage' => 'documents', 'analysis_status' => 'pending',
    ]);

    $interview->forceFill([
        'updated_at' => now()->subMinutes(AiInterview::ANALYSIS_PATIENCE_MINUTES + 1),
    ])->saveQuietly();

    $this->actingAs($applicantUser)->get(route('applicant.ai.documents', $interview))
        ->assertOk()
        ->assertSee('Разбор затянулся', false)
        // кнопка называется тем, что делает: разбором человек и идёт дальше
        ->assertSee('Запустить разбор и продолжить', false)
        // вечная перезагрузка каждые пять секунд — то, от чего и уходим
        ->assertDontSee('window.location.reload', false);
});

test('зависший разбор можно запустить заново', function () {
    [$vacancy] = makeAiVacancy();
    [$interview, , $applicantUser] = makeAiInterview($vacancy, [
        'consent_at' => now(), 'stage' => 'documents', 'analysis_status' => 'pending',
    ]);

    $interview->forceFill([
        'updated_at' => now()->subMinutes(AiInterview::ANALYSIS_PATIENCE_MINUTES + 1),
    ])->saveQuietly();

    $this->actingAs($applicantUser)
        ->post(route('applicant.ai.proceed', $interview))
        ->assertRedirect(route('applicant.ai.documents', $interview));

    expect(Queue::pushedJobs())->not->toBe([]);
});

test('идущий разбор повторно не запускается', function () {
    [$vacancy] = makeAiVacancy();
    [$interview, , $applicantUser] = makeAiInterview($vacancy, [
        'consent_at' => now(), 'stage' => 'documents', 'analysis_status' => 'pending',
    ]);

    // только что поставили в очередь — ждём, а не плодим задачи
    $this->actingAs($applicantUser)->post(route('applicant.ai.proceed', $interview));

    expect(Queue::pushedJobs())->toBe([]);
});

// ==================== как соискатель узнаёт, что пора ====================

test('приглашение на собеседование приходит непрочитанным', function () {
    [$vacancy] = makeAiVacancy();

    $user = User::factory()->applicant()->create();
    makeResume(makeApplicant($user));

    $this->actingAs($user)->post(route('public.vacancies.respond', $vacancy));

    $invite = App\Models\UserNotification::where('user_id', $user->id)
        ->where('title', 'По этой вакансии собеседование проводит ИИ')
        ->sole();

    /*
     * «Вы откликнулись» приходит прочитанным — это запись о собственном
     * действии. Приглашение так помечать нельзя: вкладку закроют, и
     * непрочитанная отметка остаётся единственным, что напомнит. Пометка была
     * скопирована у соседа, и закрывший вкладку не получал ни одного сигнала.
     */
    expect($invite->read_at)->toBeNull();

    // а соседнее — по-прежнему прочитанным
    expect(App\Models\UserNotification::where('user_id', $user->id)
        ->where('title', 'Вы откликнулись на вакансию')->sole()->read_at)->not->toBeNull();
});

test('страница «Мои ИИ-собеседования» показывает и ждущие', function () {
    [$vacancy, $user] = respondedTo();

    /*
     * Пункт меню врал названием: показывал только начатые, и человек, которого
     * собеседование ждёт, заходил туда и видел пустую страницу — ровно там,
     * куда он пойдёт искать.
     */
    $this->actingAs($user)->get(route('applicant.ai.index'))
        ->assertOk()
        ->assertSee($vacancy->title, false)
        ->assertSee('Ждёт вас', false)
        ->assertSee(route('applicant.ai.start', $vacancy), false)
        ->assertDontSee('Собеседований пока нет', false);
});

test('начатое собеседование в ждущих не повторяется', function () {
    [$vacancy, $user, $applicant] = respondedTo();

    AiInterview::create([
        'vacancy_id' => $vacancy->id,
        'applicant_id' => $applicant->id,
        'consent_at' => now(),
        'stage' => 'interview',
        'analysis_status' => 'ready',
    ]);

    $this->actingAs($user)->get(route('applicant.ai.index'))
        ->assertOk()
        ->assertDontSee('Ждёт вас', false);
});

test('без отклика ничего не ждёт', function () {
    [$vacancy] = makeAiVacancy();

    $user = User::factory()->applicant()->create();
    makeResume(makeApplicant($user));

    $this->actingAs($user)->get(route('applicant.ai.index'))
        ->assertOk()
        ->assertSee('Собеседований пока нет', false)
        ->assertDontSee('Ждёт вас', false);
});
