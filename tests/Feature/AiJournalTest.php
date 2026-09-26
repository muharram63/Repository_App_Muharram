<?php

/*
 * Журнал обращений к модели: проверка ответа, повтор и аудит.
 *
 * Это тот слой, на котором держится обещание «без случайных решений». Поэтому
 * проверяется не «вызвалось ли», а поведение на плохих ответах: негодный ответ
 * повторяется один раз, второй негодный переводит дело на ручную проверку, и
 * ни в одном из случаев наружу не уходит выдуманное значение.
 *
 * Сеть замокана через Http::fake — настоящих вызовов нет.
 */

use App\Models\AiInteraction;
use App\Services\Ai\AiInvalidAnswerException;
use App\Services\Ai\AiJournal;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\GeminiClient;
use Illuminate\Support\Facades\Http;

/** Ответ Interactions API с расходом токенов. */
function journalAnswer(array $payload, array $usage = []): array
{
    return [
        'id' => 'int_1',
        'model' => 'gemini-3.8-flash',
        'usage' => $usage + [
            'total_input_tokens' => 120,
            'total_output_tokens' => 40,
            'total_thought_tokens' => 15,
            'total_tokens' => 175,
        ],
        'steps' => [
            ['type' => 'model_output', 'content' => [
                ['type' => 'text', 'text' => json_encode($payload, JSON_UNESCAPED_UNICODE)],
            ]],
        ],
    ];
}

function journal(): AiJournal
{
    return new AiJournal(new GeminiClient([
        'key' => 'test-key',
        'model' => 'gemini-3.5-flash-lite',
        'model_scoring' => 'gemini-3.8-flash',
        'model_vision' => 'gemini-3.8-flash',
        'revision' => '2026-05-20',
        'timeout' => 5,
        'connect_timeout' => 3,
    ]));
}

/** Простые правила: балл в границах шкалы и непустое обоснование. */
function scoreRules(): array
{
    return ['score' => 'required|integer|min:0|max:5', 'why' => 'required|string|min:3'];
}

// ==================== годный ответ ====================

test('проверенный ответ возвращается как есть', function () {
    Http::fake(['*' => Http::response(journalAnswer(['score' => 4, 'why' => 'назван стек и результат']))]);

    $answer = journal()->ask(
        purpose: 'answer_score',
        system: 'Оцени ответ.',
        turns: [['role' => 'user', 'text' => 'ответ кандидата']],
        schema: ['type' => 'object'],
        rules: scoreRules(),
    );

    expect($answer)->toBe(['score' => 4, 'why' => 'назван стек и результат']);

    Http::assertSentCount(1);
});

test('поля без правил не теряются', function () {
    // validated() оставил бы только score и why, а вложенный разбор исчез бы
    Http::fake(['*' => Http::response(journalAnswer([
        'score' => 3,
        'why' => 'частично',
        'criteria' => [['key' => 'php', 'state' => 'partial']],
    ]))]);

    $answer = journal()->ask(
        purpose: 'answer_score',
        system: 'Оцени.',
        turns: [['role' => 'user', 'text' => 'ответ']],
        schema: ['type' => 'object'],
        rules: scoreRules(),
    );

    expect($answer)->toHaveKey('criteria')
        ->and($answer['criteria'][0]['key'])->toBe('php');
});

// ==================== негодный ответ ====================

test('негодный ответ повторяется один раз и второй попытки хватает', function () {
    $calls = 0;

    Http::fake(function () use (&$calls) {
        $calls++;

        // сначала балл вне шкалы, потом правильный
        return Http::response($calls === 1
            ? journalAnswer(['score' => 9, 'why' => 'слишком хорошо'])
            : journalAnswer(['score' => 5, 'why' => 'исчерпывающий ответ']));
    });

    $answer = journal()->ask(
        purpose: 'answer_score',
        system: 'Оцени.',
        turns: [['role' => 'user', 'text' => 'ответ']],
        schema: ['type' => 'object'],
        rules: scoreRules(),
    );

    expect($answer['score'])->toBe(5)
        ->and($calls)->toBe(2);

    // в аудите обе попытки: сначала негодная, потом успешная
    expect(AiInteraction::where('purpose', 'answer_score')->count())->toBe(2)
        ->and(AiInteraction::orderBy('id')->first()->status)->toBe('invalid')
        ->and(AiInteraction::orderBy('id')->first()->error)->toContain('score')
        ->and(AiInteraction::orderByDesc('id')->first()->status)->toBe('ok');
});

test('дважды негодный ответ ведёт к ручной проверке, а не к выдуманному баллу', function () {
    Http::fake(['*' => Http::response(journalAnswer(['score' => 42, 'why' => '']))]);

    $thrown = null;

    try {
        journal()->ask(
            purpose: 'answer_score',
            system: 'Оцени.',
            turns: [['role' => 'user', 'text' => 'ответ']],
            schema: ['type' => 'object'],
            rules: scoreRules(),
        );
    } catch (AiInvalidAnswerException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull()
        ->and($thrown->purpose)->toBe('answer_score')
        ->and($thrown->errors)->toHaveKeys(['score', 'why'])
        ->and($thrown->summary())->toContain('score');

    // ровно две попытки, третьей нет: она дала бы то же самое, только дороже
    Http::assertSentCount(AiJournal::ATTEMPTS);

    expect(AiInteraction::where('status', 'invalid')->count())->toBe(2)
        ->and(AiInteraction::where('status', 'ok')->count())->toBe(0);
});

test('повтор просит ровно то же, без подсказки о прошлой ошибке', function () {
    $sent = [];

    Http::fake(function ($request) use (&$sent) {
        $sent[] = $request->data();

        return Http::response(journalAnswer(['score' => 99, 'why' => 'x']));
    });

    try {
        journal()->ask(
            purpose: 'answer_score',
            system: 'Оцени.',
            turns: [['role' => 'user', 'text' => 'ответ кандидата']],
            schema: ['type' => 'object'],
            rules: scoreRules(),
        );
    } catch (AiInvalidAnswerException) {
        // ожидаемо
    }

    // Подсказка вида «в прошлый раз ты ошибся» подталкивала бы модель угадывать
    // ожидаемое значение вместо того, чтобы оценивать ответ.
    expect($sent)->toHaveCount(2)
        ->and($sent[0]['input'])->toBe($sent[1]['input'])
        ->and($sent[0]['system_instruction'])->toBe($sent[1]['system_instruction']);
});

test('без правил ответ не проверяется и повторов не бывает', function () {
    Http::fake(['*' => Http::response(journalAnswer(['что угодно' => true]))]);

    $answer = journal()->ask(
        purpose: 'criteria_advice',
        system: 'Предложи критерии.',
        turns: [['role' => 'user', 'text' => 'вакансия']],
        schema: ['type' => 'object'],
    );

    expect($answer)->toBe(['что угодно' => true]);
    Http::assertSentCount(1);
});

// ==================== сбой сервиса ====================

test('недоступность сервиса не выдаётся за негодный ответ', function () {
    Http::fake(['*' => Http::response(['error' => ['status' => 'RESOURCE_EXHAUSTED']], 429)]);

    expect(fn () => journal()->ask(
        purpose: 'answer_score',
        system: 'Оцени.',
        turns: [['role' => 'user', 'text' => 'ответ']],
        schema: ['type' => 'object'],
        rules: scoreRules(),
    ))->toThrow(AiUnavailableException::class);

    // лимит исчерпан — повторять сейчас бессмысленно, попытка одна
    Http::assertSentCount(1);

    $log = AiInteraction::sole();
    expect($log->status)->toBe('failed')
        ->and($log->error)->toContain('лимит');
});

test('оборванный JSON повторяется, а не выдаётся за сбой связи', function () {
    $calls = 0;

    Http::fake(function () use (&$calls) {
        $calls++;

        // первый ответ обрезан на первой скобке — так выглядит исчерпанный
        // потолок длины; второй приходит целым
        return Http::response($calls === 1
            ? ['steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => '{']]]]]
            : journalAnswer(['score' => 4, 'why' => 'конкретно и по делу']));
    });

    $answer = journal()->ask(
        purpose: 'answer_score',
        system: 'Оцени.',
        turns: [['role' => 'user', 'text' => 'ответ']],
        schema: ['type' => 'object'],
        rules: scoreRules(),
    );

    expect($answer['score'])->toBe(4)
        ->and($calls)->toBe(2)
        ->and(AiInteraction::orderBy('id')->first()->status)->toBe('invalid');
});

test('дважды нечитаемый ответ ведёт к ручной проверке', function () {
    Http::fake(['*' => Http::response([
        'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'просто текст']]]],
    ])]);

    expect(fn () => journal()->ask(
        purpose: 'answer_score',
        system: 'Оцени.',
        turns: [['role' => 'user', 'text' => 'ответ']],
        schema: ['type' => 'object'],
        rules: scoreRules(),
    ))->toThrow(AiInvalidAnswerException::class);

    Http::assertSentCount(AiJournal::ATTEMPTS);
});

test('прежние сервисы по-прежнему видят обычную недоступность', function () {
    // помощник по резюме ловит AiUnavailableException — нечитаемый ответ
    // остаётся его подвидом, поэтому их поведение не меняется
    Http::fake(['*' => Http::response([
        'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'не json']]]],
    ])]);

    $client = new GeminiClient([
        'key' => 'k', 'model' => 'm', 'revision' => 'r', 'timeout' => 5, 'connect_timeout' => 3,
    ]);

    expect(fn () => $client->structured('s', [['role' => 'user', 'text' => 'x']], []))
        ->toThrow(AiUnavailableException::class, 'неразборчиво');
});

test('поднятый уровень размышления получает запас токенов', function () {
    /*
     * Потолок max_output_tokens считает и размышления. На живом вызове с
     * thinking_level = medium и потолком 400 модель истратила бюджет на
     * размышление, а JSON оборвался на первой скобке. Запас добавляется в
     * журнале, чтобы каждый новый сервис не натыкался на это заново.
     */
    Http::fake(['*' => Http::response(journalAnswer(['score' => 4, 'why' => 'по делу']))]);

    journal()->ask(
        purpose: 'answer_score',
        system: 'Оцени.',
        turns: [['role' => 'user', 'text' => 'ответ']],
        schema: ['type' => 'object'],
        rules: scoreRules(),
        options: ['thinking_level' => 'medium'],
        maxTokens: 400,
    );

    Http::assertSent(fn ($request) => $request['generation_config']['max_output_tokens'] === 400 + 1024);
});

test('без размышлений бюджет остаётся тем, что попросили', function () {
    Http::fake(['*' => Http::response(journalAnswer(['ok' => true]))]);

    journal()->ask(
        purpose: 'interview_question',
        system: 'Задай вопрос.',
        turns: [['role' => 'user', 'text' => 'x']],
        schema: ['type' => 'object'],
        options: ['thinking_level' => 'low'],
        maxTokens: 600,
    );

    Http::assertSent(fn ($request) => $request['generation_config']['max_output_tokens'] === 600);
});

// ==================== аудит ====================

test('в аудит попадает промпт, ответ, расход токенов и модель', function () {
    Http::fake(['*' => Http::response(journalAnswer(['score' => 4, 'why' => 'хорошо']))]);
    [$interview] = makeAiInterview();

    journal()->ask(
        purpose: 'answer_score',
        system: 'Оцени ответ по рубрике.',
        turns: [['role' => 'user', 'text' => 'я вёл платёжный шлюз']],
        schema: ['type' => 'object'],
        rules: scoreRules(),
        interview: $interview,
        options: AiJournal::options('scoring'),
    );

    $log = AiInteraction::where('purpose', 'answer_score')->sole();

    expect($log->ai_interview_id)->toBe($interview->id)
        ->and($log->vacancy_id)->toBe($interview->vacancy_id)
        ->and($log->status)->toBe('ok')
        ->and($log->model)->toBe('gemini-3.8-flash')
        // промпт и реплики читаются глазами, без разбора JSON
        ->and($log->request)->toContain('[инструкция] Оцени ответ по рубрике.')
        ->and($log->request)->toContain('[вход] я вёл платёжный шлюз')
        ->and($log->response)->toContain('хорошо')
        ->and($log->tokens_in)->toBe(120)
        // размышления входят в выход: 40 + 15, иначе отчёт занижал бы стоимость
        ->and($log->tokens_out)->toBe(55)
        ->and($log->latency_ms)->not->toBeNull();
});

test('промпт и ответ в аудите зашифрованы', function () {
    Http::fake(['*' => Http::response(journalAnswer(['score' => 4, 'why' => 'назван Алиф Банк']))]);

    journal()->ask(
        purpose: 'answer_score',
        system: 'Оцени.',
        turns: [['role' => 'user', 'text' => 'я работал в Алиф Банке']],
        schema: ['type' => 'object'],
        rules: scoreRules(),
    );

    $log = AiInteraction::sole();

    expect(rawColumn('ai_interactions', $log->id, 'request'))->not->toContain('Алиф')
        ->and(rawColumn('ai_interactions', $log->id, 'response'))->not->toContain('Алиф');
});

// ==================== модель под задачу ====================

test('разговор и оценка идут разными моделями с разной температурой', function () {
    config()->set('services.gemini.model', 'gemini-3.5-flash-lite');
    config()->set('services.gemini.model_scoring', 'gemini-3.8-flash');
    config()->set('services.gemini.model_vision', 'gemini-3.8-flash');

    $chat = AiJournal::options('chat');
    $scoring = AiJournal::options('scoring');

    expect($chat['model'])->toBe('gemini-3.5-flash-lite')
        ->and($scoring['model'])->toBe('gemini-3.8-flash')
        // оценка должна быть воспроизводимой, разговор — живым
        ->and($scoring['temperature'])->toBeLessThan($chat['temperature'])
        ->and($scoring['temperature'])->toBe(0.1)
        // на оценку не жалеем размышления: поверхностный разбор и есть та ошибка,
        // которой мы боимся
        ->and($scoring['thinking_level'])->toBe('medium')
        ->and($chat['thinking_level'])->toBe('low');

    expect(AiJournal::options('vision')['model'])->toBe('gemini-3.8-flash');
});

test('выбранная модель и температура действительно уходят в запрос', function () {
    Http::fake(['*' => Http::response(journalAnswer(['score' => 3, 'why' => 'средне']))]);
    config()->set('services.gemini.model_scoring', 'gemini-3.8-flash');

    journal()->ask(
        purpose: 'answer_score',
        system: 'Оцени.',
        turns: [['role' => 'user', 'text' => 'ответ']],
        schema: ['type' => 'object'],
        rules: scoreRules(),
        options: AiJournal::options('scoring'),
        maxTokens: 400,
    );

    Http::assertSent(function ($request) {
        return $request['model'] === 'gemini-3.8-flash'
            && $request['generation_config']['temperature'] === 0.1
            && $request['generation_config']['thinking_level'] === 'medium'
            // к запрошенной длине ответа добавлен запас на размышления
            && $request['generation_config']['max_output_tokens'] === 400 + 1024;
    });
});

test('без указания модели работает прежняя, разговорная', function () {
    Http::fake(['*' => Http::response(journalAnswer(['ok' => true]))]);

    journal()->ask(
        purpose: 'interview_question',
        system: 'Задай вопрос.',
        turns: [['role' => 'user', 'text' => 'начнём']],
        schema: ['type' => 'object'],
    );

    // помощник по резюме и разбор совпадения зовут клиент без options —
    // их поведение меняться не должно
    Http::assertSent(function ($request) {
        return $request['model'] === 'gemini-3.5-flash-lite'
            && $request['generation_config']['temperature'] === 0.4;
    });
});

test('прежний structured продолжает отдавать только данные', function () {
    Http::fake(['*' => Http::response(journalAnswer(['a' => 1]))]);

    $client = new GeminiClient([
        'key' => 'k', 'model' => 'm', 'revision' => 'r', 'timeout' => 5, 'connect_timeout' => 3,
    ]);

    expect($client->structured('s', [['role' => 'user', 'text' => 'x']], []))->toBe(['a' => 1]);
});
