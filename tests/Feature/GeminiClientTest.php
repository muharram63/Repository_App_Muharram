<?php

use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\GeminiClient;
use Illuminate\Support\Facades\Http;

/**
 * Клиент проверяется без сети: Http::fake отдаёт заранее заготовленные
 * ответы, поэтому форма запроса и разбор ответа проверяемы и бесплатны.
 */
function gemini(array $overrides = []): GeminiClient
{
    // переданное значение важнее умолчания, поэтому $overrides слева
    return new GeminiClient($overrides + [
        'key' => 'test-key',
        'model' => 'gemini-3.5-flash',
        'revision' => '2026-05-20',
        'timeout' => 5,
        'connect_timeout' => 3,
    ]);
}

/** Ответ Interactions API: текст лежит в шагах, а не в плоском поле. */
function geminiAnswer(array $payload): array
{
    return [
        'id' => 'int_1',
        'steps' => [
            ['type' => 'user_input', 'content' => [['type' => 'text', 'text' => 'вопрос']]],
            ['type' => 'model_output', 'content' => [
                ['type' => 'text', 'text' => json_encode($payload, JSON_UNESCAPED_UNICODE)],
            ]],
        ],
    ];
}

test('the request carries the model, the system instruction and the dialogue roles', function () {
    Http::fake(['*' => Http::response(geminiAnswer(['reply' => 'ок']))]);

    $result = gemini()->structured(
        'Ты карьерный консультант.',
        [
            ['role' => 'model', 'text' => 'Как вас зовут?'],
            ['role' => 'user', 'text' => 'Далер, backend-разработчик'],
        ],
        ['type' => 'object', 'properties' => ['reply' => ['type' => 'string']]],
    );

    expect($result)->toBe(['reply' => 'ок']);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/interactions'
            && $request->hasHeader('x-goog-api-key', 'test-key')
            && $request->hasHeader('Api-Revision', '2026-05-20')
            && $body['model'] === 'gemini-3.5-flash'
            && $body['system_instruction'] === 'Ты карьерный консультант.'
            // реплики размечены типом шага: ответы модели и слова человека
            && $body['input'][0]['type'] === 'model_output'
            && $body['input'][1]['type'] === 'user_input'
            && $body['input'][1]['content'][0]['text'] === 'Далер, backend-разработчик'
            // ответ обязан прийти строго по схеме
            && $body['response_format']['mime_type'] === 'application/json'
            && isset($body['response_format']['schema']);
    });
});

test('the request caps the answer length', function () {
    Http::fake(['*' => Http::response(geminiAnswer(['reply' => 'ок']))]);

    gemini()->structured('s', [['role' => 'user', 'text' => 'привет']], [], maxTokens: 1234);

    Http::assertSent(function ($request) {
        $config = $request->data()['generation_config'];

        // без потолка модель однажды писала одну фразу до самого предела
        return $config['max_output_tokens'] === 1234
            && $config['temperature'] === 0.4
            && $config['thinking_level'] === 'low';
    });
});

test('the request is pinned to ipv4', function () {
    // хост отдаёт AAAA-записи, маршрута к ним нет: без этой настройки запрос
    // виснет до таймаута и помощник «не отвечает» через раз
    expect(GeminiClient::httpOptions()['curl'][CURLOPT_IPRESOLVE])->toBe(CURL_IPRESOLVE_V4);
});

test('an exhausted free limit turns into a human explanation', function () {
    Http::fake(['*' => Http::response(['error' => ['status' => 'RESOURCE_EXHAUSTED']], 429)]);

    expect(fn () => gemini()->structured('s', [['role' => 'user', 'text' => 'привет']], []))
        ->toThrow(AiUnavailableException::class, 'Бесплатный лимит запросов исчерпан.');
});

test('the pause named by the provider is picked up and rounded up', function () {
    // Google называет паузу словами внутри текста ошибки, отдельного поля нет
    Http::fake(['*' => Http::response(['error' => [
        'message' => 'Quota exceeded for metric: generate_content_free_tier_requests, limit: 20. '
            .'Please retry in 16.635441278s.',
    ]], 429)]);

    try {
        gemini()->structured('s', [['role' => 'user', 'text' => 'привет']], []);
        $this->fail('ожидали отказ по лимиту');
    } catch (AiUnavailableException $e) {
        expect($e->retryAfter)->toBe(17)
            ->and($e->getMessage())->toContain('через 17 с');
    }
});

test('an overload also gets a pause so the interface can retry itself', function () {
    Http::fake(['*' => Http::response('high demand', 503)]);

    try {
        gemini()->structured('s', [['role' => 'user', 'text' => 'привет']], []);
        $this->fail('ожидали отказ');
    } catch (AiUnavailableException $e) {
        expect($e->retryAfter)->toBe(30);
    }
});

test('errors that waiting will not fix carry no pause', function () {
    Http::fake(['*' => Http::response(['error' => ['status' => 'PERMISSION_DENIED']], 403)]);

    try {
        gemini()->structured('s', [['role' => 'user', 'text' => 'привет']], []);
        $this->fail('ожидали отказ');
    } catch (AiUnavailableException $e) {
        // повторять с тем же ключом бессмысленно, отсчёт был бы обманом
        expect($e->retryAfter)->toBeNull();
    }
});

test('a rejected key names the setting to check', function () {
    Http::fake(['*' => Http::response(['error' => ['status' => 'PERMISSION_DENIED']], 403)]);

    expect(fn () => gemini()->structured('s', [['role' => 'user', 'text' => 'привет']], []))
        ->toThrow(AiUnavailableException::class, 'GEMINI_API_KEY');
});

test('an overloaded service asks to try again later', function () {
    Http::fake(['*' => Http::response('service unavailable', 503)]);

    expect(fn () => gemini()->structured('s', [['role' => 'user', 'text' => 'привет']], []))
        ->toThrow(AiUnavailableException::class, 'перегружен');
});

test('an answer that is not json does not reach the user as a crash', function () {
    Http::fake(['*' => Http::response([
        'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'просто текст']]]],
    ])]);

    expect(fn () => gemini()->structured('s', [['role' => 'user', 'text' => 'привет']], []))
        ->toThrow(AiUnavailableException::class, 'неразборчиво');
});

test('without a key the client reports itself as switched off and sends nothing', function () {
    Http::fake();

    $client = gemini(['key' => null]);

    expect($client->configured())->toBeFalse();
    expect(fn () => $client->structured('s', [], []))
        ->toThrow(AiUnavailableException::class, 'GEMINI_API_KEY');

    Http::assertNothingSent();
});
