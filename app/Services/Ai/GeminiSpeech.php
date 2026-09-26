<?php

namespace App\Services\Ai;

use Illuminate\Support\Str;

/**
 * Озвучка на Gemini.
 *
 * В модель уходит ровно текст вопроса и ничего больше.
 *
 * Указаний о манере речи здесь намеренно нет, и это проверено, а не
 * предположено. Ремарка в начале текста («произнеси спокойно, как
 * HR-специалист: …») произносится вслух: то же предложение с ней звучит 11,3
 * секунды вместо 4, и кандидат слышит указание перед вопросом. Отдельный канал
 * systemInstruction модель речи не принимает вовсе — отвечает 400 «Developer
 * instruction is not enabled for this model».
 *
 * Поэтому интонацию задаёт только выбор голоса в настройках. Если кому-то
 * захочется вернуть ремарку — сначала послушайте результат.
 */
class GeminiSpeech implements SpeechSynthesizer
{
    /**
     * Потолок длины. Озвучка секунды речи стоит примерно тридцать
     * аудио-токенов, и вопрос длиннее этого предела — сам по себе признак, что
     * модель ушла в пересказ вместо вопроса.
     */
    public const MAX_CHARS = 700;

    public function __construct(
        private readonly GeminiClient $client,
        private readonly array $config,
    ) {
    }

    public function available(): bool
    {
        return ($this->config['tts_enabled'] ?? false)
            && $this->client->configured()
            && filled($this->config['tts_model'] ?? null);
    }

    public function voice(): string
    {
        return (string) ($this->config['tts_voice'] ?? 'Kore');
    }

    public function speak(string $text): array
    {
        $clean = $this->prepare($text);

        if ($clean === '') {
            throw new AiUnavailableException('Озвучивать нечего: текст вопроса пуст.');
        }

        return $this->client->speech($clean, $this->voice());
    }

    /**
     * Приводит вопрос к тому, что стоит произносить вслух.
     *
     * Убираем разметку и служебные скобки: модель речи читает звёздочки и
     * подчёркивания как слова, и вопрос звучал бы «звёздочка Laravel
     * звёздочка». Пробелы схлопываем — иначе в речи появляются рваные паузы.
     */
    private function prepare(string $text): string
    {
        $text = preg_replace('/[*_`#>]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return Str::limit(trim($text), self::MAX_CHARS, '');
    }
}
