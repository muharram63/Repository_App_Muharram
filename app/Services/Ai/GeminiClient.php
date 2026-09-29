<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Тонкая обёртка над Gemini — единственное место в проекте, которое ходит
 * в сеть за ответом модели.
 *
 * Наружу отдаёт только расшифрованный массив либо AiUnavailableException
 * с готовым текстом для пользователя: контроллерам не нужно знать ни про
 * HTTP-коды Google, ни про форму его ответа.
 *
 * Эндпоинтов два, и это не небрежность: текстовые ответы идут через
 * Interactions API, а синтез речи там не делается вовсе — он живёт на
 * /v1beta/models/{model}:generateContent. Оба запроса собраны здесь, чтобы
 * точка выхода в сеть осталась одна.
 */
class GeminiClient
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/interactions';

    /** Синтез речи: {model} подставляется перед запросом. */
    private const SPEECH_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function __construct(private readonly array $config)
    {
    }

    /**
     * Настроен ли помощник. Без ключа страница показывает подсказку,
     * а не белый экран с ошибкой.
     */
    public function configured(): bool
    {
        return filled($this->config['key'] ?? null);
    }

    /**
     * Один запрос к модели с ответом строго по JSON-схеме.
     *
     * @param  string  $system  системная инструкция
     * @param  array<int,array{role:string,text:string}>  $turns  история: role = user|model
     * @param  array  $schema  схема ожидаемого ответа
     * @param  int  $maxTokens  потолок длины ответа
     * @param  array{model?:string,temperature?:float,thinking_level?:string}  $options  чем отвечать и насколько ровно
     * @return array расшифрованный ответ модели
     *
     * @throws AiUnavailableException
     */
    public function structured(
        string $system,
        array $turns,
        array $schema,
        int $maxTokens = 2000,
        array $options = [],
    ): array {
        return $this->structuredDetailed($system, $turns, $schema, $maxTokens, $options)['data'];
    }

    /**
     * То же, но с расходом токенов и названием модели.
     *
     * Нужно аудиту: по этим числам видно, сколько стоило решение по кандидату.
     * structured() остаётся тонкой обёрткой, чтобы прежние вызовы помощника по
     * резюме и разбора совпадения ничего не заметили.
     *
     * @param  array{model?:string,temperature?:float,thinking_level?:string}  $options
     * @return array{data:array,usage:array{input:int,output:int,total:int,thought:int},model:string}
     *
     * @throws AiUnavailableException
     */
    public function structuredDetailed(
        string $system,
        array $turns,
        array $schema,
        int $maxTokens = 2000,
        array $options = [],
    ): array {
        $model = $options['model'] ?? $this->config['model'];

        $json = $this->send(array_filter([
            'model' => $model,
            /*
             * Инструменты модели. Нужен ровно один — исполнение кода: без него
             * проверка решения кандидата остаётся мнением, а с ним появляется
             * факт. Проверено, что исполнение и строгий JSON-ответ уживаются в
             * одном вызове: модель сначала запускает код, потом отвечает по
             * схеме.
             */
            'tools' => $options['tools'] ?? null,
            'system_instruction' => $system,
            'input' => array_map(fn (array $turn) => [
                // в Interactions API реплики размечены типом шага, а не полем role
                'type' => ($turn['role'] ?? 'user') === 'model' ? 'model_output' : 'user_input',
                'content' => $this->content($turn),
            ], $turns),
            'response_format' => [
                'type' => 'text',
                'mime_type' => 'application/json',
                'schema' => $schema,
            ],
            'generation_config' => [
                // Жёсткий потолок длины. Без него модель однажды сорвалась в
                // повтор одной фразы и писала её, пока не упёрлась в свой предел:
                // черновик наполнился мусором, а запрос не уложился в таймаут.
                'max_output_tokens' => $maxTokens,
                // Разговор должен быть предсказуемым, а не изобретательным.
                // Оценки просят ещё ровнее — им передают 0.1: один и тот же
                // ответ кандидата не должен получать разный балл от настроения.
                'temperature' => $options['temperature'] ?? 0.4,
                // реплике в чате размышления ничего не добавляют, а оценке и
                // разбору документов — добавляют, поэтому уровень задаётся извне
                'thinking_level' => $options['thinking_level'] ?? 'low',
            ],
        ], fn ($value) => $value !== null));

        $answer = $this->extractText($json);
        $data = json_decode($answer, true);

        if (! is_array($data)) {
            Log::warning('Gemini вернул не JSON', ['answer' => mb_substr($answer, 0, 500)]);

            // Отдельный класс, а не общая недоступность: журнал собеседования
            // повторяет такой ответ, а остальные сервисы ловят родителя и
            // ведут себя как раньше. Текст сообщения тот же.
            throw new AiUnreadableAnswerException(
                'Помощник ответил неразборчиво. Отправьте сообщение ещё раз.'
            );
        }

        return [
            'data' => $data,
            'usage' => $this->usage($json),
            'model' => (string) ($json['model'] ?? $model),
            'execution' => $this->execution($json),
        ];
    }

    /**
     * Что модель на самом деле запускала и что получила.
     *
     * Это факт, а не мнение: код исполнялся в песочнице провайдера, и вывод
     * пришёл оттуда. Храним отдельно от оценки, потому что при расхождении
     * между «вывод программы» и «модель считает, что решение хорошее» верить
     * надо выводу.
     *
     * Форма шагов выяснена живым вызовом: у code_execution_call аргументы
     * лежат в arguments, у code_execution_result вывод — в result.
     *
     * @return array<int,array{language:string,code:string,result:string,is_error:bool}>
     */
    private function execution(array $json): array
    {
        $calls = [];
        $runs = [];

        foreach ($json['steps'] ?? [] as $step) {
            $type = $step['type'] ?? null;

            if ($type === 'code_execution_call') {
                $calls[$step['id'] ?? count($calls)] = [
                    'language' => (string) ($step['arguments']['language'] ?? ''),
                    'code' => (string) ($step['arguments']['code'] ?? ''),
                ];
            }

            if ($type === 'code_execution_result') {
                $runs[] = [
                    'call' => $step['call_id'] ?? null,
                    'result' => (string) ($step['result'] ?? ''),
                    'is_error' => (bool) ($step['is_error'] ?? false),
                ];
            }
        }

        $execution = [];

        foreach ($runs as $run) {
            $call = $calls[$run['call']] ?? ['language' => '', 'code' => ''];

            $execution[] = [
                'language' => $call['language'],
                'code' => $call['code'],
                'result' => $run['result'],
                'is_error' => $run['is_error'],
            ];
        }

        return $execution;
    }

    /**
     * Содержимое реплики: приложенные файлы и текст.
     *
     * Файл передаётся блоком типа document — это выяснено перебором, а не из
     * документации: input_image, input_file и image отвергаются с 400, а
     * document принимается, и модель читает и текстовый PDF, и фотографию
     * документа. Файлы идут перед текстом: так модель сначала видит документ, а
     * потом указание, что с ним делать.
     *
     * @param  array{role?:string,text?:string,files?:array<int,array{mime:string,data:string}>}  $turn
     */
    private function content(array $turn): array
    {
        $blocks = [];

        foreach ($turn['files'] ?? [] as $file) {
            if (blank($file['data'] ?? null) || blank($file['mime'] ?? null)) {
                continue;
            }

            $blocks[] = [
                'type' => 'document',
                'mime_type' => $file['mime'],
                'data' => $file['data'],
            ];
        }

        // Пустой текст всё равно отправляем, когда файлов нет: реплика без
        // содержимого — это ошибка формата, а не «нечего сказать».
        if (filled($turn['text'] ?? null) || $blocks === []) {
            $blocks[] = ['type' => 'text', 'text' => (string) ($turn['text'] ?? '')];
        }

        return $blocks;
    }

    /**
     * Расход токенов. Interactions API отдаёт его в поле usage, и имена там
     * свои — не такие, как у generateContent с его usageMetadata.
     *
     * @return array{input:int,output:int,total:int,thought:int}
     */
    private function usage(array $json): array
    {
        $usage = $json['usage'] ?? [];

        return [
            'input' => (int) ($usage['total_input_tokens'] ?? 0),
            'output' => (int) ($usage['total_output_tokens'] ?? 0),
            'total' => (int) ($usage['total_tokens'] ?? 0),
            // размышления считаются отдельно от ответа: по ним видно, сколько
            // стоил поднятый thinking_level
            'thought' => (int) ($usage['total_thought_tokens'] ?? 0),
        ];
    }

    /**
     * Озвучить текст. Возвращает готовый WAV и его тип.
     *
     * Ответ Google приходит одним куском в base64, поэтому результат — байты,
     * а не поток: резать нечего, а класть промежуточный файл на диск здесь
     * рано, этим занимается вызывающий код.
     *
     * @return array{bytes:string,mime:string,tokens:int}
     *
     * @throws AiUnavailableException
     */
    public function speech(string $text, string $voice, ?string $model = null): array
    {
        $model ??= $this->config['tts_model'] ?? '';

        if (blank($model)) {
            throw new AiUnavailableException('Модель синтеза речи не настроена.');
        }

        $json = $this->send(
            [
                'contents' => [['parts' => [['text' => $text]]]],
                'generationConfig' => [
                    'responseModalities' => ['AUDIO'],
                    'speechConfig' => [
                        'voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => $voice]],
                    ],
                ],
            ],
            sprintf(self::SPEECH_ENDPOINT, $model),
            // синтез длиннее текстового ответа, поэтому свой таймаут
            (int) ($this->config['tts_timeout'] ?? $this->config['timeout'] ?? 90),
        );

        $inline = $json['candidates'][0]['content']['parts'][0]['inlineData'] ?? null;
        $encoded = $inline['data'] ?? null;

        if (! is_string($encoded) || $encoded === '') {
            Log::warning('Gemini не вернул аудио', [
                'finish' => $json['candidates'][0]['finishReason'] ?? null,
            ]);

            throw new AiUnavailableException('Не удалось озвучить вопрос. Попробуйте ещё раз.');
        }

        $bytes = base64_decode($encoded, true);

        if ($bytes === false || $bytes === '') {
            throw new AiUnavailableException('Озвучка пришла повреждённой. Попробуйте ещё раз.');
        }

        return [
            'bytes' => $bytes,
            // на проверке приходил audio/wav с заголовком RIFF, но полагаться
            // на это не станем: тип берём из ответа, а разбирается с ним
            // вызывающий код
            'mime' => (string) ($inline['mimeType'] ?? 'audio/wav'),
            // аудио-токены считаются отдельно от текстовых — по ним виден расход
            'tokens' => (int) ($json['usageMetadata']['candidatesTokenCount'] ?? 0),
        ];
    }

    /**
     * Настройки транспорта.
     *
     * Только IPv4: DNS отдаёт для этого хоста AAAA-записи, но маршрута к ним
     * нет — cURL уходит в IPv6 и либо теряет лишние секунды на откате, либо
     * висит до таймаута, и запрос падает «помощник не отвечает». Метод
     * публичный, чтобы это условие было закреплено тестом: без него ошибка
     * возвращается молча и воспроизводится не каждый раз.
     */
    public static function httpOptions(): array
    {
        return ['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]];
    }

    /**
     * Адрес сервера не нашёлся (cURL error 6). Так выглядит пропавший
     * интернет, сбой резолвера или VPN, который не пропускает DNS, — и это
     * самый частый способ «сломать» помощника на домашней сети.
     */
    public static function unresolved(ConnectionException $e): bool
    {
        return str_contains($e->getMessage(), 'Could not resolve host');
    }

    /**
     * До сервера не дозвонились: адрес не нашёлся или соединение не
     * открылось за отведённое время. Такой сбой обычно короткий, и повтор
     * через полсекунды часто спасает запрос. Не путать с таймаутом ответа:
     * там соединение есть, просто модель думает дольше отведённого.
     */
    public static function unreachable(ConnectionException $e): bool
    {
        $message = $e->getMessage();

        return self::unresolved($e)
            || str_contains($message, 'Connection timeout')
            || str_contains($message, 'Failed to connect');
    }

    /**
     * Эндпоинт и таймаут — параметры, а не константы: синтезу речи нужен и
     * другой адрес, и запас по времени.
     *
     * @throws AiUnavailableException
     */
    private function send(array $payload, ?string $endpoint = null, ?int $timeout = null): array
    {
        if (! $this->configured()) {
            throw new AiUnavailableException('Помощник выключен: в .env не задан GEMINI_API_KEY.');
        }

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $this->config['key'],
                'Api-Revision' => $this->config['revision'],
            ])
                ->withOptions(self::httpOptions())
                // соединение либо устанавливается быстро, либо не установится вовсе:
                // незачем ждать общего таймаута, чтобы сообщить об обрыве связи
                ->connectTimeout($this->config['connect_timeout'])
                ->timeout($timeout ?? $this->config['timeout'])
                // Повторяем только неудачу дозвона: не нашёлся адрес или не
                // открылось соединение. Истёкший таймаут ответа повторять
                // нельзя: пользователь ждал бы два таймаута подряд вместо
                // одного. На исчерпанный лимит повтор тоже бессмыслен.
                ->retry(3, 500, fn ($e) => $e instanceof ConnectionException
                    && self::unreachable($e), throw: false)
                ->post($endpoint ?? self::ENDPOINT, $payload);
        } catch (ConnectionException $e) {
            Log::warning('Gemini недоступен', ['error' => $e->getMessage()]);

            // «не нашли адрес», «не смогли дозвониться» и «ответ не пришёл
            // вовремя» — разные беды, и советы пользователю у них тоже разные.
            // Раньше сбой DNS показывался как «не успел ответить», и человек
            // жал «повторить» вместо того, чтобы проверить сеть или VPN.
            throw new AiUnavailableException(match (true) {
                self::unresolved($e) => 'Не удалось найти сервер Gemini: нет доступа в интернет или сбоит DNS. Проверьте сеть или VPN и попробуйте ещё раз — диалог сохранён.',
                self::unreachable($e) => 'Не удалось соединиться с Gemini. Проверьте интернет и попробуйте ещё раз — диалог сохранён.',
                default => 'Помощник не успел ответить. Попробуйте ещё раз — диалог сохранён.',
            });
        }

        if ($response->failed()) {
            throw $this->failure($response->status(), (string) $response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Код ответа Google — в понятную пользователю фразу.
     */
    private function failure(int $status, string $body): AiUnavailableException
    {
        Log::warning('Gemini вернул ошибку', [
            'status' => $status,
            // тело режем: в логе не нужен весь ответ, а ключ в него не попадает
            'body' => mb_substr($body, 0, 500),
        ]);

        if ($status === 429) {
            $wait = $this->retryAfter($body);

            return new AiUnavailableException(
                $wait
                    ? 'Бесплатный лимит запросов исчерпан. Продолжим через '.$wait.' с — диалог сохранён.'
                    : 'Бесплатный лимит запросов исчерпан. Подождите минуту и продолжите — диалог сохранён.',
                $wait,
            );
        }

        return new AiUnavailableException(match (true) {
            $status === 400 => 'Помощник не принял запрос. Попробуйте переформулировать ответ.',
            in_array($status, [401, 403], true) => 'Ключ Gemini не принят. Проверьте GEMINI_API_KEY в .env.',
            // перегрузку модели повторять имеет смысл, но не сию секунду
            $status >= 500 => 'Gemini сейчас перегружен. Попробуем ещё раз через минуту — диалог сохранён.',
            default => 'Помощник временно недоступен. Попробуйте ещё раз.',
        }, $status >= 500 ? 30 : null);
    }

    /**
     * Сколько секунд ждать после отказа по лимиту. Google пишет это словами
     * в тексте ошибки («Please retry in 16.635441278s»), отдельного поля нет.
     */
    private function retryAfter(string $body): ?int
    {
        if (! preg_match('/retry in ([\d.]+)s/i', $body, $found)) {
            return null;
        }

        // округляем вверх и держим в разумных рамках: ждать дольше минуты
        // человек всё равно не станет, а ноль превратил бы паузу в мгновенный повтор
        return max(1, min(60, (int) ceil((float) $found[1])));
    }

    /**
     * Текст ответа лежит в шагах: steps[].content[].text у шага model_output.
     * Ключ output_text даёт официальный SDK; в сыром HTTP его нет, но если
     * появится — возьмём его, так код переживёт смену формата.
     *
     * @throws AiUnavailableException
     */
    private function extractText(array $json): string
    {
        if (is_string($json['output_text'] ?? null) && $json['output_text'] !== '') {
            return $json['output_text'];
        }

        $text = '';

        foreach ($json['steps'] ?? [] as $step) {
            if (($step['type'] ?? null) !== 'model_output') {
                continue;
            }

            foreach ($step['content'] ?? [] as $block) {
                if (($block['type'] ?? null) === 'text') {
                    $text .= $block['text'] ?? '';
                }
            }
        }

        if ($text === '') {
            Log::warning('Gemini вернул ответ без текста', ['keys' => array_keys($json)]);

            throw new AiUnavailableException('Помощник вернул пустой ответ. Попробуйте ещё раз.');
        }

        return $text;
    }
}
