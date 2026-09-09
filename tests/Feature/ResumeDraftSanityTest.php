<?php

use App\Models\ResumeDraft;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\GeminiResumeAssistant;
use Illuminate\Support\Facades\Http;

/**
 * Помощник поверх поддельного HTTP: проверяем не сеть, а то, что попадает
 * в черновик. Модель однажды сорвалась в повтор одной фразы и забила ею всё
 * резюме — с тех пор ответ провайдера считается недоверенным.
 */
function assistantReturning(array $payload): GeminiResumeAssistant
{
    Http::fake(['*' => Http::response([
        'steps' => [['type' => 'model_output', 'content' => [
            ['type' => 'text', 'text' => json_encode($payload, JSON_UNESCAPED_UNICODE)],
        ]]],
    ])]);

    return new GeminiResumeAssistant(new GeminiClient([
        'key' => 'test-key',
        'model' => 'gemini-3.5-flash-lite',
        'revision' => '2026-05-20',
        'timeout' => 5,
        'connect_timeout' => 3,
    ]));
}

function emptyDraft(): ResumeDraft
{
    return new ResumeDraft(['messages' => [], 'data' => [], 'stage' => 'identity']);
}

test('a looping model does not fill the draft with one repeated phrase', function () {
    $loop = str_repeat('Жду ответа! Спасибо! ', 200);

    $answer = assistantReturning([
        'reply' => 'Расскажите о последнем месте работы. '.$loop,
        'stage' => 'experience',
        'done' => false,
        'updates' => ['headline' => 'Менеджер проектов', 'summary' => $loop],
    ])->next(emptyDraft(), 'меня зовут Далер');

    // повтор схлопывается в одно вхождение, а не обрезается по середине каши
    expect(mb_strlen($answer['resume']['summary']))->toBeLessThanOrEqual(600)
        ->and(mb_substr_count($answer['resume']['summary'], 'Жду ответа'))->toBe(1)
        ->and(mb_strlen($answer['reply']))->toBeLessThanOrEqual(600)
        ->and($answer['reply'])->toStartWith('Расскажите о последнем месте работы.');
});

test('lists lose duplicates and stay within a sane size', function () {
    $answer = assistantReturning([
        'reply' => 'Понял.',
        'stage' => 'skills',
        'done' => false,
        'updates' => [
            'headline' => 'Менеджер',
            'skills' => array_merge(['PHP', 'PHP', 'Laravel'], array_fill(0, 80, 'Agile')),
            'positions' => array_merge(
                // одно и то же место, названное двадцать раз, — это одно место
                array_fill(0, 20, ['company' => 'ООО Тест', 'role' => 'Менеджер']),
                // а двенадцать разных обрежутся до разумных восьми
                array_map(fn (int $i) => ['company' => 'Компания '.$i, 'role' => 'Менеджер'], range(1, 12)),
            ),
        ],
    ])->next(emptyDraft(), 'мои навыки');

    expect($answer['resume']['skills'])->toBe(['PHP', 'Laravel', 'Agile'])
        ->and($answer['resume']['positions'])->toHaveCount(8)
        ->and($answer['resume']['positions'][0]['company'])->toBe('ООО Тест');
});

test('empty and junk fields do not reach the draft', function () {
    $answer = assistantReturning([
        'reply' => 'Хорошо.',
        'stage' => 'identity',
        'done' => false,
        'updates' => [
            'headline' => 'Менеджер проектов',
            'summary' => '   ',
            'city' => '',
            'experience_years' => 999,
            'education' => 'не массив',
            'extras' => [['title' => 'Проекты', 'items' => []]],
        ],
    ])->next(emptyDraft(), 'привет');

    $resume = $answer['resume'];

    expect($resume)->toHaveKey('headline')
        ->and($resume)->not->toHaveKey('summary')
        ->and($resume)->not->toHaveKey('city')
        // блок без содержимого не нужен, как и заведомо неверный тип
        ->and($resume)->not->toHaveKey('extras')
        ->and($resume)->not->toHaveKey('education')
        ->and($resume['experience_years'])->toBe(70);
});

test('an empty answer keeps what was already collected', function () {
    $draft = new ResumeDraft([
        'messages' => [], 'stage' => 'skills',
        'data' => ['headline' => 'Менеджер проектов', 'skills' => ['Agile']],
    ]);

    $answer = assistantReturning([
        'reply' => 'Понял.', 'stage' => 'skills', 'done' => false, 'updates' => [],
    ])->next($draft, 'пропустим');

    expect($answer['resume'])->toBe(['headline' => 'Менеджер проектов', 'skills' => ['Agile']]);
});

test('new facts are added to the collected ones, not instead of them', function () {
    $draft = new ResumeDraft([
        'messages' => [], 'stage' => 'skills',
        'data' => [
            'name' => 'Далер', 'headline' => 'Менеджер проектов', 'skills' => ['Jira'],
            'positions' => [['company' => 'ООО Ориён', 'role' => 'Менеджер']],
        ],
    ]);

    $answer = assistantReturning([
        'reply' => 'Записал.', 'stage' => 'skills', 'done' => false,
        'updates' => [
            // новый навык, уточнение к прежнему месту работы и новое место
            'skills' => ['Scrum'],
            'city' => 'Худжанд',
            'positions' => [
                ['company' => 'ООО Ориён', 'role' => 'Менеджер', 'period' => '2023–2026'],
                ['company' => 'Банк «Эсхата»', 'role' => 'Аналитик'],
            ],
        ],
    ])->next($draft, 'ещё владею Scrum');

    $resume = $answer['resume'];

    expect($resume['name'])->toBe('Далер')
        ->and($resume['skills'])->toBe(['Jira', 'Scrum'])
        ->and($resume['city'])->toBe('Худжанд')
        ->and($resume['positions'])->toHaveCount(2)
        // прежняя запись дополнена периодом, а не продублирована
        ->and($resume['positions'][0]['period'])->toBe('2023–2026');
});

test('a corrected answer replaces the earlier value', function () {
    $draft = new ResumeDraft([
        'messages' => [], 'stage' => 'identity',
        'data' => ['headline' => 'Менеджер', 'city' => 'Душанбе'],
    ]);

    $answer = assistantReturning([
        'reply' => 'Исправил.', 'stage' => 'identity', 'done' => false,
        'updates' => ['city' => 'Худжанд'],
    ])->next($draft, 'я перепутал, живу в Худжанде');

    expect($answer['resume']['city'])->toBe('Худжанд')
        ->and($answer['resume']['headline'])->toBe('Менеджер');
});

test('the collected resume goes to the model as an instruction, not as human speech', function () {
    $draft = new ResumeDraft([
        'messages' => [], 'stage' => 'identity',
        'data' => ['headline' => 'Менеджер проектов'],
    ]);

    assistantReturning(['reply' => 'Ок', 'stage' => 'identity', 'done' => false, 'updates' => []])
        ->next($draft, 'меня зовут Далер');

    Http::assertSent(function ($request) {
        $body = $request->data();

        // состояние — в системной инструкции; реплика человека остаётся чистой,
        // иначе модель пересказывает служебный текст обратно в резюме
        return str_contains($body['system_instruction'], 'Менеджер проектов')
            && $body['input'][0]['content'][0]['text'] === 'меня зовут Далер';
    });
});
