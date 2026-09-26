<?php

/*
 * Голос ИИ-собеседования и его отказоустойчивость.
 *
 * Главное, что здесь проверяется, — что отсутствие голоса не ломает
 * собеседование. Синтез платный и капризный: кончилась квота, отвалилась сеть,
 * выключили ключ. В каждом таком случае вопрос должен остаться на экране, а
 * страница — перейти на голос браузера, а не показать ошибку.
 *
 * Сеть не трогаем: синтезатор подменяется фейком.
 */

use App\Jobs\SynthesizeTurnSpeech;
use App\Models\AiInteraction;
use App\Models\AiInterviewTurn;
use App\Models\User;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\GeminiSpeech;
use App\Services\Ai\SpeechLibrary;
use App\Services\Ai\SpeechSynthesizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Подменный синтезатор: считает вызовы, умеет притворяться выключенным
 * и падать с человеческой ошибкой.
 */
class FakeSynthesizer implements SpeechSynthesizer
{
    public int $calls = 0;

    public function __construct(
        private readonly bool $available = true,
        private readonly ?string $failure = null,
        private readonly string $voice = 'Kore',
    ) {
    }

    public function available(): bool
    {
        return $this->available;
    }

    public function voice(): string
    {
        return $this->voice;
    }

    public function speak(string $text): array
    {
        if ($this->failure) {
            throw new AiUnavailableException($this->failure, 17);
        }

        $this->calls++;

        return [
            // заголовок RIFF и немного «звука»: содержимое тестам не важно,
            // важно что это байты и они доезжают до диска
            'bytes' => 'RIFF'.str_repeat("\0", 40).str_repeat('ab', 4000),
            'mime' => 'audio/wav',
            'tokens' => 223,
        ];
    }
}

/** Ставит фейк в контейнер и возвращает его. */
function fakeVoice(bool $available = true, ?string $failure = null): FakeSynthesizer
{
    $fake = new FakeSynthesizer($available, $failure);
    app()->instance(SpeechSynthesizer::class, $fake);

    return $fake;
}

beforeEach(function () {
    Storage::fake('local');
    config()->set('services.gemini.tts_model', 'gemini-3.8-flash-lite-tts');
    config()->set('services.gemini.tts_voice', 'Kore');
});

// ==================== фонотека и кэш ====================

test('один и тот же текст озвучивается один раз', function () {
    $fake = fakeVoice();
    $library = app(SpeechLibrary::class);

    $first = $library->make('Расскажите о последнем проекте.');
    $second = $library->make('Расскажите о последнем проекте.');

    expect($first['cached'])->toBeFalse()
        ->and($second['cached'])->toBeTrue()
        // второй раз в сеть не ходили
        ->and($fake->calls)->toBe(1)
        ->and($second['path'])->toBe($first['path']);

    Storage::disk('local')->assertExists($first['path']);
});

test('смена голоса обесценивает прежнюю озвучку сама', function () {
    fakeVoice();
    $text = 'Какими инструментами вы пользовались?';

    $withKore = app(SpeechLibrary::class)->hash($text);

    // тот же текст, другой голос — другой отпечаток, значит другой файл
    app()->instance(SpeechSynthesizer::class, new FakeSynthesizer(voice: 'Puck'));
    $withPuck = app(SpeechLibrary::class)->hash($text);

    expect($withPuck)->not->toBe($withKore);
});

test('версия входит в отпечаток, иначе правка подготовки текста не обновит кэш', function () {
    fakeVoice();

    // Отпечаток считается по исходному тексту, а произносится подготовленный.
    // Без версии правка подготовки оставляла бы в фонотеке верные по хешу, но
    // неверные по звуку файлы — именно так кэш продолжал отдавать запись с
    // произнесённой вслух ремаркой о манере.
    $reflection = new ReflectionClass(SpeechLibrary::class);

    expect($reflection->getConstant('VERSION'))->toBeInt()
        ->and($reflection->getConstant('VERSION'))->toBeGreaterThanOrEqual(2);

    // отпечаток зависит и от модели: сменили модель речи — переозвучиваем
    $library = app(SpeechLibrary::class);
    $withLite = $library->hash('вопрос');

    config()->set('services.gemini.tts_model', 'gemini-3.8-flash-tts');

    expect(app(SpeechLibrary::class)->hash('вопрос'))->not->toBe($withLite);
});

test('файлы раскладываются по подкаталогам, а не в одну папку', function () {
    fakeVoice();
    $library = app(SpeechLibrary::class);

    $hash = $library->hash('вопрос');

    expect($library->path($hash))->toBe('ai/speech/'.substr($hash, 0, 2).'/'.$hash.'.wav');
});

test('длительность считается по размеру файла', function () {
    fakeVoice();

    // 24000 отсчётов по 2 байта — ровно секунда при 24 кГц
    $made = app(SpeechLibrary::class)->make('вопрос');

    expect($made['ms'])->toBeGreaterThan(0)
        ->and($made['bytes'])->toBe(8044);
});

// ==================== аудит ====================

test('озвучка попадает в аудит вместе с расходом токенов', function () {
    fakeVoice();

    app(SpeechLibrary::class)->make('Расскажите о себе.');

    $log = AiInteraction::where('purpose', 'speech_synthesis')->sole();

    expect($log->status)->toBe('ok')
        ->and($log->model)->toBe('gemini-3.8-flash-lite-tts')
        ->and($log->tokens_out)->toBe(223)
        ->and($log->request)->toBe('Расскажите о себе.')
        // в ответе байты аудио, в логе — описание, а не сам звук
        ->and($log->response)->toContain('audio/wav')
        ->and($log->latency_ms)->not->toBeNull();
});

test('неудачная озвучка тоже попадает в аудит', function () {
    fakeVoice(failure: 'Бесплатный лимит исчерпан.');

    expect(fn () => app(SpeechLibrary::class)->make('вопрос'))
        ->toThrow(AiUnavailableException::class);

    $log = AiInteraction::where('purpose', 'speech_synthesis')->sole();

    expect($log->status)->toBe('failed')
        ->and($log->error)->toBe('Бесплатный лимит исчерпан.');

    // мусорного файла на диске не осталось
    expect(Storage::disk('local')->allFiles('ai/speech'))->toBe([]);
});

test('аудит привязывается к собеседованию, когда оно известно', function () {
    fakeVoice();
    [$interview] = makeAiInterview();

    app(SpeechLibrary::class)->make('вопрос', $interview);

    $log = AiInteraction::where('purpose', 'speech_synthesis')->sole();

    expect($log->ai_interview_id)->toBe($interview->id)
        ->and($log->vacancy_id)->toBe($interview->vacancy_id);
});

// ==================== задача в очереди ====================

test('задача озвучки доводит реплику до состояния ready', function () {
    fakeVoice();
    [$interview] = makeAiInterview();

    $turn = AiInterviewTurn::create([
        'ai_interview_id' => $interview->id,
        'position' => 1,
        'role' => 'ai',
        'text' => 'Расскажите, чем занимались на прошлом месте.',
        'speech_status' => 'pending',
    ]);

    (new SynthesizeTurnSpeech($turn->id))->handle(app(SpeechLibrary::class));

    $turn->refresh();

    expect($turn->speech_status)->toBe('ready')
        ->and($turn->speechReady())->toBeTrue()
        ->and($turn->speech_voice)->toBe('Kore')
        ->and($turn->speech_ms)->toBeGreaterThan(0);

    Storage::disk('local')->assertExists($turn->speech_path);
});

test('выключенный синтез помечает реплику failed, а не роняет задачу', function () {
    fakeVoice(available: false);
    [$interview] = makeAiInterview();

    $turn = AiInterviewTurn::create([
        'ai_interview_id' => $interview->id,
        'position' => 1, 'role' => 'ai', 'text' => 'вопрос', 'speech_status' => 'pending',
    ]);

    (new SynthesizeTurnSpeech($turn->id))->handle(app(SpeechLibrary::class));

    // failed для страницы означает «читай сама», а не «всё сломалось»
    expect($turn->fresh()->speech_status)->toBe('failed')
        ->and($turn->fresh()->text)->toBe('вопрос');
});

test('реплики кандидата не озвучиваются', function () {
    $fake = fakeVoice();
    [$interview] = makeAiInterview();

    $turn = AiInterviewTurn::create([
        'ai_interview_id' => $interview->id,
        'position' => 2, 'role' => 'candidate', 'text' => 'Я вёл платёжный шлюз.',
    ]);

    (new SynthesizeTurnSpeech($turn->id))->handle(app(SpeechLibrary::class));

    expect($fake->calls)->toBe(0)
        ->and($turn->fresh()->speech_status)->toBe('none')
        ->and($turn->needsSpeech())->toBeFalse();
});

test('удалённая реплика не роняет задачу', function () {
    fakeVoice();

    // собеседование удалили, пока задача ждала в очереди
    (new SynthesizeTurnSpeech(999999))->handle(app(SpeechLibrary::class));
})->throwsNoExceptions();

// ==================== отдача файла ====================

test('кандидат получает озвучку своей реплики', function () {
    fakeVoice();
    [$interview, , $applicantUser] = makeAiInterview();

    $turn = AiInterviewTurn::create([
        'ai_interview_id' => $interview->id,
        'position' => 1, 'role' => 'ai', 'text' => 'вопрос', 'speech_status' => 'pending',
    ]);

    (new SynthesizeTurnSpeech($turn->id))->handle(app(SpeechLibrary::class));

    $this->actingAs($applicantUser)
        ->get(route('applicant.ai.speech.turn', $turn->fresh()))
        ->assertOk()
        ->assertHeader('Content-Type', 'audio/wav');
});

test('чужую озвучку не получить, даже зная адрес', function () {
    fakeVoice();
    [$interview] = makeAiInterview();

    $turn = AiInterviewTurn::create([
        'ai_interview_id' => $interview->id,
        'position' => 1, 'role' => 'ai', 'text' => 'вопрос', 'speech_status' => 'pending',
    ]);

    (new SynthesizeTurnSpeech($turn->id))->handle(app(SpeechLibrary::class));

    // другой соискатель со своей анкетой
    $stranger = User::factory()->applicant()->create();
    makeApplicant($stranger);

    $this->actingAs($stranger)
        ->get(route('applicant.ai.speech.turn', $turn->fresh()))
        ->assertForbidden();
});

test('неготовая озвучка отдаёт 404, а состояние — pending', function () {
    fakeVoice();
    [$interview, , $applicantUser] = makeAiInterview();

    $turn = AiInterviewTurn::create([
        'ai_interview_id' => $interview->id,
        'position' => 1, 'role' => 'ai', 'text' => 'вопрос', 'speech_status' => 'pending',
    ]);

    $this->actingAs($applicantUser)
        ->get(route('applicant.ai.speech.turn', $turn))
        ->assertNotFound();

    $this->actingAs($applicantUser)
        ->getJson(route('applicant.ai.speech.status', $turn))
        ->assertOk()
        ->assertJson(['status' => 'pending', 'url' => null]);
});

test('состояние готовой озвучки отдаёт ссылку на файл', function () {
    fakeVoice();
    [$interview, , $applicantUser] = makeAiInterview();

    $turn = AiInterviewTurn::create([
        'ai_interview_id' => $interview->id,
        'position' => 1, 'role' => 'ai', 'text' => 'вопрос', 'speech_status' => 'pending',
    ]);

    (new SynthesizeTurnSpeech($turn->id))->handle(app(SpeechLibrary::class));

    $this->actingAs($applicantUser)
        ->getJson(route('applicant.ai.speech.status', $turn->fresh()))
        ->assertOk()
        ->assertJsonPath('status', 'ready')
        ->assertJsonPath('url', route('applicant.ai.speech.turn', $turn));
});

test('пропавший с диска файл не отдаётся как битый звук', function () {
    fakeVoice();
    [$interview, , $applicantUser] = makeAiInterview();

    $turn = AiInterviewTurn::create([
        'ai_interview_id' => $interview->id,
        'position' => 1, 'role' => 'ai', 'text' => 'вопрос',
        'speech_status' => 'ready',
        'speech_path' => 'ai/speech/ab/нет-такого.wav',
    ]);

    // для страницы это не ошибка: она прочитает вопрос голосом браузера
    $this->actingAs($applicantUser)
        ->get(route('applicant.ai.speech.turn', $turn))
        ->assertNotFound();
});

// ==================== озвучка произвольного текста ====================

test('страница проверки получает WAV и признак попадания в кэш', function () {
    fakeVoice();
    $user = User::factory()->applicant()->create();
    makeApplicant($user);

    $first = $this->actingAs($user)->post(route('applicant.ai.speech.preview'), [
        'text' => 'Проверка голоса.',
    ]);

    $first->assertOk()
        ->assertHeader('Content-Type', 'audio/wav')
        ->assertHeader('X-Speech-Cached', '0');

    $this->actingAs($user)
        ->post(route('applicant.ai.speech.preview'), ['text' => 'Проверка голоса.'])
        ->assertOk()
        ->assertHeader('X-Speech-Cached', '1');
});

test('пустой и слишком длинный текст не озвучиваются', function () {
    fakeVoice();
    $user = User::factory()->applicant()->create();
    makeApplicant($user);

    $this->actingAs($user)
        ->postJson(route('applicant.ai.speech.preview'), ['text' => ''])
        ->assertStatus(422);

    $this->actingAs($user)
        ->postJson(route('applicant.ai.speech.preview'), [
            'text' => str_repeat('а', GeminiSpeech::MAX_CHARS + 1),
        ])
        ->assertStatus(422);
});

test('отказ синтеза отдаёт странице указание читать браузером', function () {
    fakeVoice(failure: 'Бесплатный лимит исчерпан.');
    $user = User::factory()->applicant()->create();
    makeApplicant($user);

    $this->actingAs($user)
        ->postJson(route('applicant.ai.speech.preview'), ['text' => 'вопрос'])
        ->assertStatus(503)
        ->assertJsonPath('fallback', 'browser')
        ->assertJsonPath('retry_after', 17);
});

test('выключенный синтез тоже отправляет страницу к голосу браузера', function () {
    fakeVoice(available: false);
    $user = User::factory()->applicant()->create();
    makeApplicant($user);

    $this->actingAs($user)
        ->postJson(route('applicant.ai.speech.preview'), ['text' => 'вопрос'])
        ->assertStatus(503)
        ->assertJsonPath('fallback', 'browser');
});

test('работодателю страница аватара недоступна', function () {
    $employer = User::factory()->employer()->create();

    $this->actingAs($employer)->get(route('applicant.ai.avatar'))->assertForbidden();
});

test('гостя страница аватара отправляет на вход', function () {
    // отдельным тестом, а не следом за предыдущим: actingAs действует до конца
    // теста, и гостевой запрос в том же тесте ушёл бы от имени работодателя
    $this->get(route('applicant.ai.avatar'))->assertRedirect(route('login'));
});

test('страница аватара открывается и содержит компонент', function () {
    fakeVoice();
    $user = User::factory()->applicant()->create();
    makeApplicant($user);

    $this->actingAs($user)->get(route('applicant.ai.avatar'))
        ->assertOk()
        ->assertSee('avFigure', false)
        ->assertSee('AiAvatar', false)
        // текст вопроса на экране есть всегда — голос лишь дополнение
        ->assertSee('Расскажите', false);
});

test('страница честно предупреждает, когда голоса нет', function () {
    fakeVoice(available: false);
    $user = User::factory()->applicant()->create();
    makeApplicant($user);

    $this->actingAs($user)->get(route('applicant.ai.avatar'))
        ->assertOk()
        ->assertSee('голосом браузера', false);
});

// ==================== разбор ответа Gemini ====================

test('клиент достаёт аудио из ответа модели речи', function () {
    Http::fake(['*' => Http::response([
        'candidates' => [[
            'content' => ['parts' => [[
                'inlineData' => ['mimeType' => 'audio/wav', 'data' => base64_encode('RIFF-звук')],
            ]]],
            'finishReason' => 'STOP',
        ]],
        'usageMetadata' => ['candidatesTokenCount' => 223],
    ])]);

    $client = new GeminiClient([
        'key' => 'test-key', 'model' => 'gemini-3.5-flash-lite', 'revision' => '2026-05-20',
        'timeout' => 5, 'connect_timeout' => 3,
        'tts_model' => 'gemini-3.8-flash-lite-tts', 'tts_timeout' => 90,
    ]);

    $audio = $client->speech('вопрос', 'Kore');

    expect($audio['bytes'])->toBe('RIFF-звук')
        ->and($audio['mime'])->toBe('audio/wav')
        ->and($audio['tokens'])->toBe(223);

    // запрос ушёл на эндпоинт модели, а не на Interactions API
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'models/gemini-3.8-flash-lite-tts:generateContent')
            && $request['generationConfig']['responseModalities'] === ['AUDIO']
            && $request['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName'] === 'Kore';
    });
});

test('ответ без аудио превращается в понятную ошибку', function () {
    Http::fake(['*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => 'я не умею петь']]], 'finishReason' => 'STOP']],
    ])]);

    $client = new GeminiClient([
        'key' => 'k', 'model' => 'm', 'revision' => 'r', 'timeout' => 5, 'connect_timeout' => 3,
        'tts_model' => 'gemini-3.8-flash-lite-tts',
    ]);

    expect(fn () => $client->speech('вопрос', 'Kore'))
        ->toThrow(AiUnavailableException::class, 'Не удалось озвучить');
});

test('без настроенной модели речи в сеть не ходим', function () {
    Http::fake();

    $client = new GeminiClient([
        'key' => 'k', 'model' => 'm', 'revision' => 'r', 'timeout' => 5, 'connect_timeout' => 3,
    ]);

    expect(fn () => $client->speech('вопрос', 'Kore'))
        ->toThrow(AiUnavailableException::class, 'не настроена');

    Http::assertNothingSent();
});

// ==================== подготовка текста ====================

test('разметка не попадает в речь', function () {
    Http::fake(['*' => Http::response([
        'candidates' => [['content' => ['parts' => [[
            'inlineData' => ['mimeType' => 'audio/wav', 'data' => base64_encode('звук')],
        ]]]]],
    ])]);

    $config = [
        'key' => 'k', 'model' => 'm', 'revision' => 'r', 'timeout' => 5, 'connect_timeout' => 3,
        'tts_enabled' => true, 'tts_model' => 'gemini-3.8-flash-lite-tts', 'tts_voice' => 'Kore',
    ];

    $speech = new GeminiSpeech(new GeminiClient($config), $config);

    $speech->speak("Расскажите про **Laravel**   и  `SQL`.\n\nЧто именно делали?");

    Http::assertSent(function ($request) {
        $text = $request['contents'][0]['parts'][0]['text'];

        // звёздочки и обратные кавычки модель речи читает как слова
        return ! str_contains($text, '*')
            && ! str_contains($text, '`')
            && ! str_contains($text, '  ')
            && str_contains($text, 'Laravel');
    });
});

test('в речь уходит только вопрос, без указаний о манере', function () {
    /*
     * Защита от соблазна вернуть ремарку вроде «произнеси спокойно, как
     * HR-специалист: …». Модель речи произносит её вслух: то же предложение
     * с ремаркой звучит 11,3 секунды вместо 4, и кандидат слышит указание
     * перед вопросом. Канала systemInstruction у модели речи нет — на него
     * приходит 400.
     */
    Http::fake(['*' => Http::response([
        'candidates' => [['content' => ['parts' => [[
            'inlineData' => ['mimeType' => 'audio/wav', 'data' => base64_encode('звук')],
        ]]]]],
    ])]);

    $config = [
        'key' => 'k', 'model' => 'm', 'revision' => 'r', 'timeout' => 5, 'connect_timeout' => 3,
        'tts_enabled' => true, 'tts_model' => 'gemini-3.8-flash-lite-tts', 'tts_voice' => 'Kore',
    ];

    $question = 'Расскажите о задаче, которой вы довольны.';

    (new GeminiSpeech(new GeminiClient($config), $config))->speak($question);

    Http::assertSent(function ($request) use ($question) {
        // ровно вопрос и ничего больше
        return $request['contents'][0]['parts'][0]['text'] === $question
            && ! isset($request['systemInstruction']);
    });
});

test('слишком длинный вопрос обрезается до предела', function () {
    $config = [
        'key' => 'k', 'model' => 'm', 'revision' => 'r', 'timeout' => 5, 'connect_timeout' => 3,
        'tts_enabled' => true, 'tts_model' => 'gemini-3.8-flash-lite-tts', 'tts_voice' => 'Kore',
    ];

    Http::fake(['*' => Http::response([
        'candidates' => [['content' => ['parts' => [[
            'inlineData' => ['mimeType' => 'audio/wav', 'data' => base64_encode('звук')],
        ]]]]],
    ])]);

    (new GeminiSpeech(new GeminiClient($config), $config))
        ->speak(str_repeat('очень длинный вопрос ', 100));

    Http::assertSent(function ($request) {
        return mb_strlen($request['contents'][0]['parts'][0]['text']) <= GeminiSpeech::MAX_CHARS;
    });
});

test('пустой текст в сеть не уходит', function () {
    Http::fake();

    $config = [
        'key' => 'k', 'model' => 'm', 'revision' => 'r', 'timeout' => 5, 'connect_timeout' => 3,
        'tts_enabled' => true, 'tts_model' => 'gemini-3.8-flash-lite-tts', 'tts_voice' => 'Kore',
    ];

    expect(fn () => (new GeminiSpeech(new GeminiClient($config), $config))->speak('  **  ** '))
        ->toThrow(AiUnavailableException::class, 'пуст');

    Http::assertNothingSent();
});

test('синтез выключается отдельно от остального Gemini', function () {
    $base = [
        'key' => 'k', 'model' => 'm', 'revision' => 'r', 'timeout' => 5, 'connect_timeout' => 3,
        'tts_model' => 'gemini-3.8-flash-lite-tts', 'tts_voice' => 'Kore',
    ];

    $on = new GeminiSpeech(new GeminiClient($base + ['tts_enabled' => true]), $base + ['tts_enabled' => true]);
    $off = new GeminiSpeech(new GeminiClient($base + ['tts_enabled' => false]), $base + ['tts_enabled' => false]);

    // ключ есть и текстовый помощник работает, а голос выключен настройкой
    expect($on->available())->toBeTrue()
        ->and($off->available())->toBeFalse()
        ->and($on->voice())->toBe('Kore');

    // без ключа голоса нет тем более
    $noKey = ['key' => null] + $base + ['tts_enabled' => true];
    expect((new GeminiSpeech(new GeminiClient($noKey), $noKey))->available())->toBeFalse();
});
