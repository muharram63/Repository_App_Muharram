<?php

/*
 * Настройка ИИ-собеседования работодателем.
 *
 * Главное, что здесь защищается: критерии предлагает модель, а утверждает
 * человек, и собеседование нельзя включить с настройками, при которых решение
 * выйдет бессмысленным. Второе — что чужую вакансию настроить нельзя.
 *
 * Сеть не трогаем: советчик подменяется фейком.
 */

use App\Models\AiInterviewConfig;
use App\Models\AiInterviewCriterion;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\Ai\AiInvalidAnswerException;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\CriteriaAdvisor;

/**
 * Подменный советчик: считает вызовы, умеет молчать и падать.
 */
class FakeAdvisor implements CriteriaAdvisor
{
    public int $calls = 0;

    public function __construct(
        private readonly bool $available = true,
        private readonly ?string $failure = null,
        private readonly ?array $answer = null,
        private readonly bool $invalid = false,
    ) {
    }

    public function available(): bool
    {
        return $this->available;
    }

    public function suggest(Vacancy $vacancy): array
    {
        if ($this->invalid) {
            throw new AiInvalidAnswerException('дважды негодно', ['criteria' => ['пусто']]);
        }

        if ($this->failure) {
            throw new AiUnavailableException($this->failure, 17);
        }

        $this->calls++;

        return $this->answer ?? [
            ['key' => 'php_laravel', 'label' => 'PHP и Laravel', 'description' => 'Проверяем работу с очередями',
                'kind' => 'must', 'weight' => 40, 'position' => 0],
            ['key' => 'sql', 'label' => 'SQL и индексы', 'description' => 'Чтение плана запроса',
                'kind' => 'must', 'weight' => 30, 'position' => 1],
            ['key' => 'docker', 'label' => 'Docker', 'description' => 'Сборка образа',
                'kind' => 'nice', 'weight' => 10, 'position' => 2],
        ];
    }
}

/** Работодатель со своей вакансией и подменённым советчиком. */
function screeningSetup(?CriteriaAdvisor $advisor = null): array
{
    app()->instance(CriteriaAdvisor::class, $advisor ?? new FakeAdvisor);

    $user = User::factory()->employer()->create();
    $vacancy = makeVacancy(makeEmployer($user));

    return [$user, $vacancy];
}

// ==================== доступ ====================

test('соискателю и гостю настройка недоступна', function () {
    $applicant = User::factory()->applicant()->create();

    $this->actingAs($applicant)->get(route('employer.ai.index'))->assertForbidden();
});

test('гостя отправляет на вход', function () {
    $this->get(route('employer.ai.index'))->assertRedirect(route('login'));
});

test('чужую вакансию настроить нельзя', function () {
    [, $vacancy] = screeningSetup();

    $stranger = User::factory()->employer()->create();
    makeEmployer($stranger);

    $this->actingAs($stranger)->get(route('employer.ai.show', $vacancy))->assertForbidden();

    $this->actingAs($stranger)
        ->patch(route('employer.ai.config', $vacancy), ['level' => 'middle'])
        ->assertForbidden();

    $this->actingAs($stranger)
        ->post(route('employer.ai.criteria.suggest', $vacancy))
        ->assertForbidden();
});

test('работодателя без анкеты ведут её заполнять', function () {
    app()->instance(CriteriaAdvisor::class, new FakeAdvisor);
    $user = User::factory()->employer()->create();

    $this->actingAs($user)->get(route('employer.ai.index'))
        ->assertRedirect(route('public.employers.create'));
});

// ==================== список ====================

test('список показывает состояние по каждой вакансии', function () {
    [$user, $vacancy] = screeningSetup();

    $this->actingAs($user)->get(route('employer.ai.index'))
        ->assertOk()
        ->assertSee($vacancy->title)
        // настроек ещё нет — считается выключенным
        ->assertSee('Выключено');
});

test('список открывается и без вакансий', function () {
    app()->instance(CriteriaAdvisor::class, new FakeAdvisor);
    $user = User::factory()->employer()->create();
    makeEmployer($user);

    $this->actingAs($user)->get(route('employer.ai.index'))
        ->assertOk()
        ->assertSee('пока нет вакансий', false);
});

// ==================== страница настройки ====================

test('первый заход создаёт настройки со значениями по умолчанию и выключенными', function () {
    [$user, $vacancy] = screeningSetup();

    expect(AiInterviewConfig::where('vacancy_id', $vacancy->id)->exists())->toBeFalse();

    $this->actingAs($user)->get(route('employer.ai.show', $vacancy))->assertOk();

    $config = AiInterviewConfig::where('vacancy_id', $vacancy->id)->sole();

    expect($config->enabled)->toBeFalse()
        ->and($config->weightsSum())->toBe(100)
        ->and($config->decision_mode)->toBe('auto');
});

test('страница честно перечисляет, что мешает включить', function () {
    [$user, $vacancy] = screeningSetup();

    // критериев нет — это и есть препятствие
    $this->actingAs($user)->get(route('employer.ai.show', $vacancy))
        ->assertOk()
        ->assertSee('Пока собеседование включить нельзя', false)
        ->assertSee('обязательный критерий', false);
});

// ==================== сохранение настроек ====================

test('настройки сохраняются', function () {
    [$user, $vacancy] = screeningSetup();

    $this->actingAs($user)->patch(route('employer.ai.config', $vacancy), [
        'level' => 'senior',
        'language' => 'ru',
        'questions_count' => 10,
        'weight_documents' => 20,
        'weight_interview' => 50,
        'weight_test' => 30,
        'threshold_reject' => 40,
        'threshold_accept' => 75,
        'test_time_limit_minutes' => 90,
        'response_sla' => 'в течение двух дней',
        'decision_mode' => 'advisory',
    ])->assertRedirect();

    $config = AiInterviewConfig::where('vacancy_id', $vacancy->id)->sole();

    expect($config->level)->toBe('senior')
        ->and($config->questions_count)->toBe(10)
        ->and($config->weight_interview)->toBe(50)
        ->and($config->threshold_accept)->toBe(75)
        ->and($config->response_sla)->toBe('в течение двух дней')
        ->and($config->decidesItself())->toBeFalse()
        ->and($config->enabled)->toBeFalse();
});

test('веса, не дающие сотню, не принимаются', function () {
    [$user, $vacancy] = screeningSetup();

    // порознь каждый вес допустим, а вместе они бессмысленны — это ловит
    // проверка готовности, а не правила валидации
    $this->actingAs($user)->patch(route('employer.ai.config', $vacancy), configPayload([
        'weight_documents' => 50, 'weight_interview' => 50, 'weight_test' => 50,
        'enabled' => 1,
    ]))->assertRedirect();

    $config = AiInterviewConfig::where('vacancy_id', $vacancy->id)->sole();

    expect($config->enabled)->toBeFalse()
        ->and($config->weightsSum())->toBe(150);

    $this->get(route('employer.ai.show', $vacancy))->assertSee('в сумме давать 100', false);
});

test('собеседование не включается без подтверждённого обязательного критерия', function () {
    [$user, $vacancy] = screeningSetup();

    // критерий есть, но не подтверждён
    AiInterviewCriterion::factory()->pending()->create([
        'vacancy_id' => $vacancy->id, 'key' => 'php', 'kind' => 'must',
    ]);

    $this->actingAs($user)
        ->patch(route('employer.ai.config', $vacancy), configPayload(['enabled' => 1]))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(AiInterviewConfig::where('vacancy_id', $vacancy->id)->sole()->enabled)->toBeFalse();
});

test('с подтверждённым обязательным критерием собеседование включается', function () {
    [$user, $vacancy] = screeningSetup();

    AiInterviewCriterion::factory()->create([
        'vacancy_id' => $vacancy->id, 'key' => 'php', 'kind' => 'must',
    ]);

    $this->actingAs($user)
        ->patch(route('employer.ai.config', $vacancy), configPayload(['enabled' => 1]))
        ->assertRedirect()
        ->assertSessionHas('status');

    $config = AiInterviewConfig::where('vacancy_id', $vacancy->id)->sole();

    expect($config->enabled)->toBeTrue()
        ->and($config->isUsable())->toBeTrue()
        ->and($config->problems())->toBe([]);
});

test('порог приёма ниже порога отказа не даёт включить', function () {
    [$user, $vacancy] = screeningSetup();

    AiInterviewCriterion::factory()->create(['vacancy_id' => $vacancy->id, 'key' => 'php', 'kind' => 'must']);

    $this->actingAs($user)->patch(route('employer.ai.config', $vacancy), configPayload([
        'threshold_reject' => 80, 'threshold_accept' => 50, 'enabled' => 1,
    ]))->assertSessionHas('error');

    expect(AiInterviewConfig::where('vacancy_id', $vacancy->id)->sole()->enabled)->toBeFalse();
});

test('неверные значения отклоняются валидацией', function () {
    [$user, $vacancy] = screeningSetup();

    $this->actingAs($user)->patch(route('employer.ai.config', $vacancy), configPayload([
        'questions_count' => 99, 'decision_mode' => 'сам решу', 'level' => 'god',
    ]))->assertSessionHasErrors(['questions_count', 'decision_mode', 'level']);
});

// ==================== критерии от модели ====================

test('модель предлагает критерии, и они ждут подтверждения', function () {
    [$user, $vacancy] = screeningSetup();

    $this->actingAs($user)
        ->post(route('employer.ai.criteria.suggest', $vacancy))
        ->assertRedirect()
        ->assertSessionHas('status');

    $criteria = AiInterviewCriterion::where('vacancy_id', $vacancy->id)->get();

    expect($criteria)->toHaveCount(3)
        // ни один не подтверждён: в оценке они пока не участвуют
        ->and($criteria->whereNotNull('confirmed_at'))->toHaveCount(0)
        ->and($criteria->pluck('source')->unique()->all())->toBe(['ai'])
        ->and(AiInterviewCriterion::where('vacancy_id', $vacancy->id)->confirmed()->count())->toBe(0);
});

test('повторное предложение не затирает подтверждённое работодателем', function () {
    [$user, $vacancy] = screeningSetup();

    // работодатель подтвердил свой критерий и поправил формулировку
    $mine = AiInterviewCriterion::factory()->create([
        'vacancy_id' => $vacancy->id,
        'key' => 'sql',
        'label' => 'SQL — моя формулировка',
        'weight' => 55,
    ]);

    // и есть черновик от прошлого предложения
    AiInterviewCriterion::factory()->pending()->create([
        'vacancy_id' => $vacancy->id, 'key' => 'old_draft', 'label' => 'Старый черновик',
    ]);

    $this->actingAs($user)->post(route('employer.ai.criteria.suggest', $vacancy))->assertRedirect();

    $mine->refresh();

    // подтверждённый цел вместе с правками
    expect($mine->label)->toBe('SQL — моя формулировка')
        ->and($mine->weight)->toBe(55)
        // старый черновик заменён новым предложением
        ->and(AiInterviewCriterion::where('vacancy_id', $vacancy->id)->where('key', 'old_draft')->exists())
        ->toBeFalse()
        // совпавший по ключу sql заново не создан
        ->and(AiInterviewCriterion::where('vacancy_id', $vacancy->id)->where('key', 'sql')->count())->toBe(1);
});

test('недоступный ИИ не ломает страницу и говорит почему', function () {
    [$user, $vacancy] = screeningSetup(new FakeAdvisor(available: false));

    $this->actingAs($user)->post(route('employer.ai.criteria.suggest', $vacancy))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(AiInterviewCriterion::count())->toBe(0);

    // а на странице кнопка выключена и сказано, что делать
    $this->get(route('employer.ai.show', $vacancy))
        ->assertOk()
        ->assertSee('добавьте критерии вручную', false);
});

test('исчерпанная квота показывается словами провайдера', function () {
    [$user, $vacancy] = screeningSetup(new FakeAdvisor(failure: 'Бесплатный лимит запросов исчерпан.'));

    $this->actingAs($user)->post(route('employer.ai.criteria.suggest', $vacancy))
        ->assertSessionHas('error', 'Бесплатный лимит запросов исчерпан.');
});

test('дважды негодный ответ предлагает добавить критерии руками', function () {
    [$user, $vacancy] = screeningSetup(new FakeAdvisor(invalid: true));

    $this->actingAs($user)->post(route('employer.ai.criteria.suggest', $vacancy))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(session('error'))->toContain('вручную');
});

test('пустое предложение не оставляет пользователя в неведении', function () {
    [$user, $vacancy] = screeningSetup(new FakeAdvisor(answer: []));

    $this->actingAs($user)->post(route('employer.ai.criteria.suggest', $vacancy))
        ->assertSessionHas('error');

    expect(session('error'))->toContain('Дополните описание');
});

// ==================== подтверждение критериев ====================

test('работодатель правит и подтверждает критерии одной формой', function () {
    [$user, $vacancy] = screeningSetup();

    $first = AiInterviewCriterion::factory()->pending()->create([
        'vacancy_id' => $vacancy->id, 'key' => 'php', 'label' => 'PHP', 'weight' => 10, 'kind' => 'nice',
    ]);
    $second = AiInterviewCriterion::factory()->pending()->create([
        'vacancy_id' => $vacancy->id, 'key' => 'sql', 'label' => 'SQL', 'weight' => 10,
    ]);

    $this->actingAs($user)->patch(route('employer.ai.criteria.save', $vacancy), [
        'criteria' => [
            $first->id => ['label' => 'PHP и Laravel', 'description' => 'очереди', 'kind' => 'must',
                'weight' => 45, 'confirmed' => 1],
            $second->id => ['label' => 'SQL', 'description' => '', 'kind' => 'nice',
                'weight' => 15, 'confirmed' => 0],
        ],
    ])->assertRedirect()->assertSessionHas('status');

    $first->refresh();
    $second->refresh();

    expect($first->label)->toBe('PHP и Laravel')
        ->and($first->kind)->toBe('must')
        ->and($first->weight)->toBe(45)
        ->and($first->isConfirmed())->toBeTrue()
        // второй остался черновиком
        ->and($second->isConfirmed())->toBeFalse()
        ->and($second->weight)->toBe(15);
});

test('снятая отметка возвращает критерий в черновик, а не удаляет', function () {
    [$user, $vacancy] = screeningSetup();

    $criterion = AiInterviewCriterion::factory()->create([
        'vacancy_id' => $vacancy->id, 'key' => 'php', 'label' => 'PHP', 'weight' => 30,
    ]);

    $this->actingAs($user)->patch(route('employer.ai.criteria.save', $vacancy), [
        'criteria' => [
            $criterion->id => ['label' => 'PHP', 'kind' => 'must', 'weight' => 30, 'confirmed' => 0],
        ],
    ])->assertRedirect();

    $criterion->refresh();

    // формулировка на месте, просто в оценке не участвует
    expect($criterion->exists)->toBeTrue()
        ->and($criterion->label)->toBe('PHP')
        ->and($criterion->isConfirmed())->toBeFalse();
});

test('чужой критерий через форму не поправить', function () {
    [$user, $vacancy] = screeningSetup();

    // критерий другой вакансии другого работодателя
    [$otherVacancy] = makeAiVacancy([]);
    $alien = AiInterviewCriterion::factory()->create([
        'vacancy_id' => $otherVacancy->id, 'key' => 'alien', 'label' => 'Чужой', 'weight' => 10,
    ]);

    $this->actingAs($user)->patch(route('employer.ai.criteria.save', $vacancy), [
        'criteria' => [
            $alien->id => ['label' => 'Подменённый', 'kind' => 'must', 'weight' => 99, 'confirmed' => 1],
        ],
    ])->assertRedirect();

    // подменённый идентификатор просто проигнорирован
    expect($alien->fresh()->label)->toBe('Чужой')
        ->and($alien->fresh()->weight)->toBe(10);
});

test('пустое название критерия не сохраняется', function () {
    [$user, $vacancy] = screeningSetup();

    $criterion = AiInterviewCriterion::factory()->create([
        'vacancy_id' => $vacancy->id, 'key' => 'php', 'label' => 'PHP', 'weight' => 30,
    ]);

    $this->actingAs($user)->patch(route('employer.ai.criteria.save', $vacancy), [
        'criteria' => [$criterion->id => ['label' => '', 'kind' => 'must', 'weight' => 30]],
    ])->assertSessionHasErrors();

    expect($criterion->fresh()->label)->toBe('PHP');
});

// ==================== свои критерии ====================

test('свой критерий добавляется уже подтверждённым', function () {
    [$user, $vacancy] = screeningSetup();

    $this->actingAs($user)->post(route('employer.ai.criteria.add', $vacancy), [
        'label' => 'Работа с чужим кодом',
        'description' => 'Как разбирается в незнакомом проекте',
        'kind' => 'must',
        'weight' => 35,
    ])->assertRedirect()->assertSessionHas('status');

    $criterion = AiInterviewCriterion::where('vacancy_id', $vacancy->id)->sole();

    // работодатель его сам написал — подтверждать нечего
    expect($criterion->isConfirmed())->toBeTrue()
        ->and($criterion->source)->toBe('employer')
        ->and($criterion->key)->not->toBeEmpty();
});

test('русское название превращается в устойчивый ключ', function () {
    [$user, $vacancy] = screeningSetup();

    // Str::slug вырезал бы кириллицу целиком и оставил пустую строку,
    // а ключ обязателен и уникален
    foreach (['Работа с возражениями', 'Работа с возражениями'] as $label) {
        $this->actingAs($user)->post(route('employer.ai.criteria.add', $vacancy), [
            'label' => $label, 'kind' => 'must', 'weight' => 20,
        ])->assertRedirect();
    }

    $keys = AiInterviewCriterion::where('vacancy_id', $vacancy->id)->pluck('key');

    expect($keys)->toHaveCount(2)
        ->and($keys->unique())->toHaveCount(2)
        ->and($keys->filter(fn ($k) => $k === '')->count())->toBe(0);
});

test('критерий удаляется, а проверки кандидатов остаются', function () {
    [$user, $vacancy] = screeningSetup();

    $criterion = AiInterviewCriterion::factory()->create([
        'vacancy_id' => $vacancy->id, 'key' => 'php', 'label' => 'PHP', 'weight' => 30,
    ]);

    [$interview] = makeAiInterview($vacancy);

    $check = App\Models\AiRequirementCheck::create([
        'ai_interview_id' => $interview->id,
        'criterion_id' => $criterion->id,
        'requirement' => 'PHP',
        'status' => 'found',
    ]);

    $this->actingAs($user)
        ->patch(route('employer.ai.criteria.save', $vacancy), ['remove' => $criterion->id])
        ->assertRedirect()->assertSessionHas('status');

    expect(AiInterviewCriterion::whereKey($criterion->id)->exists())->toBeFalse()
        // оценка кандидата в его отчёте сохранилась
        ->and($check->fresh())->not->toBeNull()
        ->and($check->fresh()->criterion_id)->toBeNull();
});

test('критерий чужой вакансии не удалить', function () {
    [$user, $vacancy] = screeningSetup();

    [$otherVacancy] = makeAiVacancy([]);
    $alien = AiInterviewCriterion::factory()->create([
        'vacancy_id' => $otherVacancy->id, 'key' => 'alien', 'label' => 'Чужой', 'weight' => 10,
    ]);

    /*
     * Критерий чужой вакансии не удаляется и не выдаёт себя ошибкой: метод
     * ищет его среди критериев своей вакансии и просто не находит.
     */
    $this->actingAs($user)
        ->patch(route('employer.ai.criteria.save', $vacancy), ['remove' => $alien->id])
        ->assertRedirect();

    expect($alien->fresh())->not->toBeNull();
});

/**
 * Полный набор настроек со заменой отдельных значений: в тестах меняется
 * обычно одно поле, а валидация требует все.
 */
function configPayload(array $overrides = []): array
{
    return $overrides + [
        'level' => 'middle',
        'language' => 'ru',
        'questions_count' => 8,
        'weight_documents' => 30,
        'weight_interview' => 45,
        'weight_test' => 25,
        'threshold_reject' => 45,
        'threshold_accept' => 70,
        'test_time_limit_minutes' => 60,
        'response_sla' => 'в течение 3 дней',
        'decision_mode' => 'auto',
    ];
}

// ==================== сам советчик ====================

test('ключ критерия не оканчивается подчёркиванием и не пустой на кириллице', function () {
    Illuminate\Support\Facades\Http::fake(['*' => Illuminate\Support\Facades\Http::response([
        'model' => 'gemini-3.6-flash',
        'usage' => ['total_input_tokens' => 800, 'total_output_tokens' => 1900],
        'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => json_encode([
            'criteria' => [
                // длинное русское название: обрезка попадает в середину слова
                ['label' => 'Методика преподавания всеобщей истории в старших классах',
                    'kind' => 'must', 'weight' => 90, 'description' => 'планирование уроков'],
                // два одинаковых названия — ключи обязаны разойтись
                ['label' => 'Работа с возражениями', 'kind' => 'must', 'weight' => 40],
                ['label' => 'Работа с возражениями', 'kind' => 'nice', 'weight' => 20],
            ],
        ], JSON_UNESCAPED_UNICODE)]]]],
    ])]);

    [, $vacancy] = screeningSetup();

    $advisor = new App\Services\Ai\GeminiCriteriaAdvisor(app(App\Services\Ai\AiJournal::class));
    $criteria = $advisor->suggest($vacancy);

    $keys = array_column($criteria, 'key');

    expect($criteria)->toHaveCount(3)
        ->and(array_unique($keys))->toHaveCount(3);

    foreach ($keys as $key) {
        expect($key)->not->toBe('')
            ->and($key)->not->toEndWith('_')
            ->and(mb_strlen($key))->toBeLessThanOrEqual(40);
    }
});

test('бюджет запроса даёт место восьми критериям с описаниями', function () {
    Illuminate\Support\Facades\Http::fake(['*' => Illuminate\Support\Facades\Http::response([
        'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => json_encode([
            'criteria' => [['label' => 'PHP', 'kind' => 'must', 'weight' => 50]],
        ])]]]],
    ])]);

    [, $vacancy] = screeningSetup();

    (new App\Services\Ai\GeminiCriteriaAdvisor(app(App\Services\Ai\AiJournal::class)))->suggest($vacancy);

    /*
     * Замерено: пять критериев с описаниями заняли 1986 токенов выхода. При
     * прежнем потолке 1800 первая попытка обрывалась на полуслове, повтор
     * стоил лишних 25 секунд. Здесь проверяется, что места хватает с запасом,
     * включая добавку на размышления.
     */
    Illuminate\Support\Facades\Http::assertSent(
        fn ($request) => $request['generation_config']['max_output_tokens'] >= 3200 + 1024
    );
});

test('язык критериев — язык интерфейса работодателя, а не вакансии', function () {
    Illuminate\Support\Facades\Http::fake(['*' => Illuminate\Support\Facades\Http::response([
        'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => json_encode([
            'criteria' => [['label' => 'PHP', 'kind' => 'must', 'weight' => 50]],
        ])]]]],
    ])]);

    [, $vacancy] = screeningSetup();
    $advisor = new App\Services\Ai\GeminiCriteriaAdvisor(app(App\Services\Ai\AiJournal::class));

    app()->setLocale('tg');
    $advisor->suggest($vacancy);

    Illuminate\Support\Facades\Http::assertSent(
        fn ($request) => str_contains($request['system_instruction'], 'таджикском')
    );

    app()->setLocale('ru');
});

test('модель не должна предлагать критериев про пол и возраст', function () {
    // требование записано в промпте прямым текстом: это не косметика, а
    // условие законности отбора
    $advisor = new App\Services\Ai\GeminiCriteriaAdvisor(app(App\Services\Ai\AiJournal::class));

    $prompt = (new ReflectionClass($advisor))->getMethod('prompt');
    $prompt->setAccessible(true);
    $text = $prompt->invoke($advisor);

    expect($text)->toContain('пол, возраст')
        ->toContain('оценивать по ним запрещено')
        // и про «корочку» вместо умения
        ->toContain('диплом');
});

// ==================== с чего начинает новая вакансия ====================

test('новая вакансия открывается с рабочими значениями, а не с нулями', function () {
    [$user, $vacancy] = screeningSetup();

    /*
     * Первый заход, не второй.
     *
     * Настройки заводятся через firstOrCreate(): строка в базе получала
     * значения колонок, а объект в памяти оставался пустым — и работодатель
     * видел нули во всех полях, сумму весов 0 и три красных пункта. После
     * перезагрузки страницы всё «само чинилось», поэтому ошибку легко было
     * не заметить.
     */
    $response = $this->actingAs($user)->get(route('employer.ai.show', $vacancy))->assertOk();

    $config = AiInterviewConfig::sole();

    expect($config->weight_documents)->toBe(40)
        ->and($config->weight_interview)->toBe(40)
        ->and($config->weight_test)->toBe(20)
        ->and($config->weightsSum())->toBe(100)
        ->and($config->threshold_reject)->toBe(40)
        ->and($config->threshold_accept)->toBe(70)
        ->and($config->questions_count)->toBe(5)
        ->and($config->test_time_limit_minutes)->toBe(30);

    // и это видно в полях формы, а не только в базе
    $response->assertSee('value="40"', false)
        ->assertSee('value="20"', false)
        ->assertSee('value="70"', false);
});

test('у новой вакансии остаётся одно невыполненное условие — критерий', function () {
    [$user, $vacancy] = screeningSetup();

    $this->actingAs($user)->get(route('employer.ai.show', $vacancy));

    $config = AiInterviewConfig::sole();

    // веса и пороги в порядке сразу; подтвердить критерий — единственное,
    // что за работодателя решить нельзя
    expect($config->problems())->toHaveCount(1)
        ->and($config->problems()[0])->toContain('обязательный критерий');

    $checklist = collect($config->checklist())->keyBy('key');

    expect($checklist['weights']['met'])->toBeTrue()
        ->and($checklist['thresholds']['met'])->toBeTrue()
        ->and($checklist['criteria']['met'])->toBeFalse();
});

test('чеклист показывает все три условия и текущую сумму весов', function () {
    [$user, $vacancy] = screeningSetup();

    $this->actingAs($user)->get(route('employer.ai.show', $vacancy))
        ->assertOk()
        // все три пункта видны всегда, а не только невыполненные
        ->assertSee('Сумма весов документов, собеседования и задания равна 100', false)
        ->assertSee('Между порогами есть пограничная зона', false)
        ->assertSee('Подтверждён хотя бы один обязательный критерий', false)
        // сумма выводится числом и обновляется на лету
        ->assertSee('data-sum', false)
        ->assertSee('Пока собеседование включить нельзя', false);
});

test('когда условия выполнены, чеклист зеленеет целиком', function () {
    [$user, $vacancy] = screeningSetup();

    AiInterviewCriterion::create([
        'vacancy_id' => $vacancy->id, 'key' => 'php', 'label' => 'PHP',
        'kind' => 'must', 'weight' => 100, 'source' => 'employer',
        'confirmed_at' => now(), 'position' => 0,
    ]);

    $this->actingAs($user)->get(route('employer.ai.show', $vacancy))
        ->assertOk()
        ->assertSee('Все условия выполнены', false)
        // блок стал зелёным целиком
        ->assertSee('sc-note sc-good', false)
        ->assertDontSee('sc-note sc-warn', false);

    /*
     * На отсутствие слов «включить нельзя» здесь не проверяем: они остаются в
     * data-атрибуте — оттуда их берёт скрипт, когда работодатель правит веса и
     * условие снова перестаёт выполняться.
     */
    expect(AiInterviewConfig::sole()->problems())->toBe([]);
});

test('чеклист и серверная проверка не расходятся', function () {
    [$user, $vacancy] = screeningSetup();

    $config = AiInterviewConfig::create(['vacancy_id' => $vacancy->id, 'weight_test' => 30]);

    /*
     * Оба списка выводятся из одного набора условий. Держать их врозь значило
     * бы однажды получить страницу с зелёной галочкой там, где сохранение
     * отказывает.
     */
    $unmet = collect($config->checklist())->reject(fn ($item) => $item['met']);

    expect($config->problems())->toBe($unmet->pluck('problem')->values()->all())
        ->and($config->problems())->not->toBe([]);
});

test('дефолты не открывают дорогу мимо серверной проверки', function () {
    [$user, $vacancy] = screeningSetup();

    // веса и пороги хороши по умолчанию, но критерия нет — включить нельзя
    $this->actingAs($user)->patch(route('employer.ai.config', $vacancy), [
        'level' => 'middle', 'language' => 'ru', 'questions_count' => 5,
        'weight_documents' => 40, 'weight_interview' => 40, 'weight_test' => 20,
        'threshold_reject' => 40, 'threshold_accept' => 70,
        'test_time_limit_minutes' => 30, 'response_sla' => 'в течение 3 дней',
        'decision_mode' => 'auto', 'enabled' => '1',
    ])->assertSessionHas('error');

    expect(AiInterviewConfig::sole()->enabled)->toBeFalse();
});

// ==================== удаление критерия кнопкой в форме ====================

test('кнопка «Удалить» в форме критериев удаляет критерий', function () {
    [$user, $vacancy] = screeningSetup();

    $keep = AiInterviewCriterion::create([
        'vacancy_id' => $vacancy->id, 'key' => 'php', 'label' => 'PHP',
        'kind' => 'must', 'weight' => 50, 'source' => 'ai', 'position' => 0,
    ]);
    $drop = AiInterviewCriterion::create([
        'vacancy_id' => $vacancy->id, 'key' => 'sql', 'label' => 'SQL',
        'kind' => 'nice', 'weight' => 50, 'source' => 'ai', 'position' => 1,
    ]);

    /*
     * Кнопка принадлежит форме критериев и шлёт её же адрес. Раньше у неё был
     * атрибут form с указанием на отдельную спрятанную форму: вложить форму в
     * форму нельзя, а кнопка стоит внутри карточки. Привязка через чужой
     * идентификатор — лишнее звено, которое ломается молча.
     */
    $this->actingAs($user)->patch(route('employer.ai.criteria.save', $vacancy), [
        'remove' => $drop->id,
    ])->assertSessionHas('status');

    expect(AiInterviewCriterion::pluck('key')->all())->toBe(['php'])
        ->and($keep->fresh())->not->toBeNull();
});

test('удаление проходит, даже когда соседний критерий заполнен негодно', function () {
    [$user, $vacancy] = screeningSetup();

    $drop = AiInterviewCriterion::create([
        'vacancy_id' => $vacancy->id, 'key' => 'sql', 'label' => 'SQL',
        'kind' => 'nice', 'weight' => 50, 'source' => 'ai', 'position' => 0,
    ]);

    // у соседа стёрли название: форма целиком негодна, но удалять это не мешает
    $this->actingAs($user)->patch(route('employer.ai.criteria.save', $vacancy), [
        'remove' => $drop->id,
        'criteria' => [$drop->id => ['label' => '', 'kind' => 'nice', 'weight' => 50]],
    ])->assertSessionHas('status');

    expect(AiInterviewCriterion::count())->toBe(0);
});

test('чужой критерий кнопкой не удалить', function () {
    [$user, $vacancy] = screeningSetup();

    [, $other] = screeningSetup();
    $alien = AiInterviewCriterion::create([
        'vacancy_id' => $other->id, 'key' => 'php', 'label' => 'PHP',
        'kind' => 'must', 'weight' => 50, 'source' => 'ai', 'position' => 0,
    ]);

    $this->actingAs($user)->patch(route('employer.ai.criteria.save', $vacancy), [
        'remove' => $alien->id,
    ]);

    expect($alien->fresh())->not->toBeNull();
});

test('повторное удаление с открытой вкладки не ломает страницу', function () {
    [$user, $vacancy] = screeningSetup();

    $drop = AiInterviewCriterion::create([
        'vacancy_id' => $vacancy->id, 'key' => 'sql', 'label' => 'SQL',
        'kind' => 'nice', 'weight' => 50, 'source' => 'ai', 'position' => 0,
    ]);

    $id = $drop->id;
    $drop->delete();

    $this->actingAs($user)->patch(route('employer.ai.criteria.save', $vacancy), [
        'remove' => $id,
    ])->assertSessionHas('status');
});

test('кнопка удаления не ссылается на чужую форму', function () {
    [$user, $vacancy] = screeningSetup();

    AiInterviewCriterion::create([
        'vacancy_id' => $vacancy->id, 'key' => 'php', 'label' => 'PHP',
        'kind' => 'must', 'weight' => 50, 'source' => 'ai', 'position' => 0,
    ]);

    $html = $this->actingAs($user)->get(route('employer.ai.show', $vacancy))
        ->assertOk()->getContent();

    // ни привязки по идентификатору, ни спрятанных форм-спутников
    expect($html)->not->toContain('form="drop-')
        ->and($html)->not->toContain('id="drop-')
        ->and($html)->toContain('name="remove"');
});
