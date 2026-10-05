<?php

namespace App\Services\Ai;

use App\Models\AiInterview;

/**
 * Распознавание устного ответа кандидата.
 *
 * Нужно голосовому режиму собеседования там, где браузер не умеет
 * распознавать речь сам: Firefox, Safari, таджикский язык. В Chrome и Edge
 * речь распознаёт браузер, и сервер не участвует.
 *
 * Интерфейс, а не класс: в тестах подменяется заглушкой, сеть не нужна.
 */
interface SpeechRecognizer
{
    public function available(): bool;

    /**
     * @param  string  $audio  байты записи (WAV)
     * @param  string|null  $language  код языка речи (ru, en, tg); по умолчанию — язык собеседования
     * @return string расшифровка; пустая строка — речи в записи нет
     *
     * @throws AiUnavailableException
     * @throws AiInvalidAnswerException
     */
    public function transcribe(AiInterview $interview, string $audio, string $mime, ?string $language = null): string;
}
