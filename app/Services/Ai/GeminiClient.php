<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Тонкая обёртка над Interactions API Gemini — единственное место в проекте,
 * которое ходит в сеть за ответом модели.
 *
 * Наружу отдаёт только расшифрованный массив либо AiUnavailableException
 * с готовым текстом для пользователя: контроллерам не нужно знать ни про
 * HTTP-коды Google, ни про форму его ответа.
 */
class GeminiClient
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/interactions';

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
     * @return array расшифрованный ответ модели
     *
     * @throws AiUnavailableException
     */
    public function structured(string $system, array $turns, array $schema, int $maxTokens = 2000): array
    {
        $answer = $this->extractText($this->send([
            'model' => $this->config['model'],
            'system_instruction' => $system,
            'input' => array_map(fn (array $turn) => [
                // в Interactions API реплики размечены типом шага, а не полем role
                'type' => $turn['role'] === 'model' ? 'model_output' : 'user_input',
                'content' => [['type' => 'text', 'text' => $turn['text']]],
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
                // разговор должен быть предсказуемым, а не изобретательным
                'temperature' => 0.4,
                // ответ нужен быстро, глубокие размышления тут ничего не добавляют
                'thinking_level' => 'low',
            ],
        ]));

        $data = json_decode($answer, true);

        if (! is_array($data)) {
            Log::warning('Gemini вернул не JSON', ['answer' => mb_substr($answer, 0, 500)]);

            throw new AiUnavailableException(
                'Помощник ответил неразборчиво. Отправьте сообщение ещё раз.'
            );
        }

        return $data;
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
     * @throws AiUnavailableException
     */
    private function send(array $payload): array
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
                ->timeout($this->config['timeout'])
                // Повторяем только неудачу дозвона. Истёкший таймаут ответа
                // повторять нельзя: пользователь ждал бы два таймаута подряд
                // вместо одного. На исчерпанный лимит повтор тоже бессмыслен.
                ->retry(2, 400, fn ($e) => $e instanceof ConnectionException
                    && str_contains($e->getMessage(), 'Connection timeout'), throw: false)
                ->post(self::ENDPOINT, $payload);
        } catch (ConnectionException $e) {
            Log::warning('Gemini недоступен', ['error' => $e->getMessage()]);

            // «не смогли дозвониться» и «ответ не пришёл вовремя» — разные беды,
            // и советы пользователю у них тоже разные
            throw new AiUnavailableException(
                str_contains($e->getMessage(), 'Connection timeout')
                    ? 'Не удалось соединиться с Gemini. Проверьте интернет и попробуйте ещё раз — диалог сохранён.'
                    : 'Помощник не успел ответить. Попробуйте ещё раз — диалог сохранён.'
            );
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
