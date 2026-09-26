<?php

namespace App\Jobs;

use App\Models\AiInterviewTurn;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\SpeechLibrary;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Озвучка вопроса в фоне.
 *
 * Синтез занимает около четырёх секунд — держать из-за него ответ чата
 * неправильно: кандидат должен увидеть вопрос сразу и начать думать, пока
 * готовится звук. Поэтому реплика сохраняется со статусом pending, страница
 * показывает её текстом и ставит аватара в состояние «думает», а звук догоняет.
 *
 * Провал озвучки не является провалом собеседования: статус становится failed,
 * и страница читает тот же вопрос голосом браузера.
 */
class SynthesizeTurnSpeech implements ShouldQueue
{
    use Queueable;

    /**
     * Три попытки с растущей паузой: бесплатный ключ отдаёт 429 при двадцати
     * запросах в минуту, и через минуту та же озвучка обычно проходит.
     */
    public $tries = 3;

    public array $backoff = [10, 60];

    public function __construct(public readonly int $turnId)
    {
    }

    public function handle(SpeechLibrary $library): void
    {
        $turn = AiInterviewTurn::find($this->turnId);

        // реплику могли удалить вместе с собеседованием, пока задача ждала
        if (! $turn || ! $turn->needsSpeech()) {
            return;
        }

        if (! $library->available()) {
            $turn->update(['speech_status' => 'failed']);

            return;
        }

        try {
            $made = $library->make($turn->text, $turn->interview);
        } catch (AiUnavailableException $e) {
            // последняя попытка исчерпана — отдаём страницу браузерному голосу
            if ($this->attempts() >= $this->tries) {
                $turn->update(['speech_status' => 'failed']);

                Log::info('Озвучка вопроса не удалась, страница прочитает сама', [
                    'turn' => $turn->id,
                    'error' => $e->getMessage(),
                ]);

                return;
            }

            throw $e;
        }

        $turn->update([
            'speech_status' => 'ready',
            'speech_path' => $made['path'],
            'speech_voice' => config('services.gemini.tts_voice'),
            'speech_ms' => $made['ms'],
        ]);
    }

    /**
     * Очередь сдалась окончательно — вопрос остаётся, голос берёт браузер.
     */
    public function failed(?\Throwable $e): void
    {
        AiInterviewTurn::whereKey($this->turnId)->update(['speech_status' => 'failed']);
    }
}
