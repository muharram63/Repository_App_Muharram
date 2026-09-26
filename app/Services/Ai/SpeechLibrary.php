<?php

namespace App\Services\Ai;

use App\Models\AiInteraction;
use App\Models\AiInterview;
use Illuminate\Support\Facades\Storage;

/**
 * Фонотека озвучек: один текст озвучивается один раз.
 *
 * Синтез стоит около четырёх секунд и двухсот аудио-токенов на вопрос, а
 * кандидат возвращается к прежним репликам постоянно — перечитывает, включает
 * повтор, обновляет страницу. Без кэша каждое такое действие уходило бы в сеть.
 *
 * Отпечаток считается по тексту вместе с голосом и моделью: сменили голос в
 * настройках — прежние файлы просто перестают находиться, чистить ничего не
 * нужно. Приём тот же, что у разбора совпадения (MatchAnalysis::hash) и у
 * разбора документов.
 */
class SpeechLibrary
{
    /** Приватный диск: озвучка вопроса отдаётся только через контроллер. */
    public const DISK = 'local';

    /**
     * Версия того, что уходит в модель речи.
     *
     * Отпечаток считается по исходному тексту вопроса, а произносится текст
     * подготовленный — без разметки и лишних пробелов. Значит при любой правке
     * подготовки прежние файлы становятся неверными, оставаясь при этом
     * «свежими» по отпечатку: текст-то не менялся.
     *
     * Так и вышло, когда из речи убрали ремарку о манере: кэш продолжал
     * отдавать запись, в которой модель произносила указание вслух. Версия
     * решает это одним числом — поднимите её, и вся фонотека переозвучится.
     *
     * Приём тот же, что у разбора совпадения (MatchAnalysis::VERSION).
     */
    private const VERSION = 2;

    public function __construct(private readonly SpeechSynthesizer $synthesizer)
    {
    }

    public function available(): bool
    {
        return $this->synthesizer->available();
    }

    /**
     * Отпечаток озвучки. Голос входит в него, поэтому смена голоса
     * обесценивает кэш сама.
     */
    public function hash(string $text): string
    {
        return md5(implode('|', [
            self::VERSION,
            trim($text),
            $this->synthesizer->voice(),
            config('services.gemini.tts_model', ''),
        ]));
    }

    /**
     * Путь файла. Раскладываем по подкаталогам из первых двух знаков хеша:
     * за год собеседований в одной папке оказались бы десятки тысяч файлов,
     * а такой каталог медленно читается на любой файловой системе.
     */
    public function path(string $hash): string
    {
        return 'ai/speech/'.substr($hash, 0, 2).'/'.$hash.'.wav';
    }

    /**
     * Готовая озвучка, если она уже есть.
     */
    public function cached(string $text): ?string
    {
        $path = $this->path($this->hash($text));

        return Storage::disk(self::DISK)->exists($path) ? $path : null;
    }

    /**
     * Озвучить текст или отдать готовое.
     *
     * @return array{path:string,cached:bool,ms:int,bytes:int}
     *
     * @throws AiUnavailableException
     */
    public function make(string $text, ?AiInterview $interview = null): array
    {
        $path = $this->path($this->hash($text));
        $disk = Storage::disk(self::DISK);

        if ($disk->exists($path)) {
            return [
                'path' => $path,
                'cached' => true,
                'ms' => $this->duration($disk->size($path)),
                'bytes' => $disk->size($path),
            ];
        }

        $startedAt = microtime(true);

        try {
            $audio = $this->synthesizer->speak($text);
        } catch (AiUnavailableException $e) {
            $this->audit($interview, $text, null, $startedAt, 'failed', $e->getMessage());

            throw $e;
        }

        $disk->put($path, $audio['bytes']);

        $this->audit($interview, $text, $audio, $startedAt, 'ok');

        return [
            'path' => $path,
            'cached' => false,
            'ms' => $this->duration(strlen($audio['bytes'])),
            'bytes' => strlen($audio['bytes']),
        ];
    }

    /**
     * Длительность по размеру файла: WAV от Gemini приходит несжатым,
     * 16 бит на отсчёт при 24 кГц моно. Читать заголовок ради этого излишне —
     * число нужно интерфейсу для полосы воспроизведения, а не для монтажа.
     */
    private function duration(int $bytes): int
    {
        // 44 байта заголовка RIFF в расчёт не берём: на семи секундах речи
        // это ошибка меньше миллисекунды
        return (int) round($bytes / 2 / 24000 * 1000);
    }

    /**
     * Запись в аудит. Озвучка — такое же обращение к модели, как остальные:
     * по этим строкам виден расход аудио-токенов по модулю.
     *
     * @param  array{bytes:string,mime:string,tokens:int}|null  $audio
     */
    private function audit(
        ?AiInterview $interview,
        string $text,
        ?array $audio,
        float $startedAt,
        string $status,
        ?string $error = null,
    ): void {
        AiInteraction::create([
            'ai_interview_id' => $interview?->id,
            'vacancy_id' => $interview?->vacancy_id,
            'purpose' => 'speech_synthesis',
            'model' => (string) config('services.gemini.tts_model', ''),
            'request' => $text,
            // в ответе байты аудио, в лог они не нужны — храним описание
            'response' => $audio ? $audio['mime'].', '.strlen($audio['bytes']).' Б' : null,
            'tokens_out' => $audio['tokens'] ?? null,
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'status' => $status,
            'error' => $error,
        ]);
    }
}
