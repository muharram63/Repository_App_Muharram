<?php

namespace App\Services\Ai;

use App\Models\ResumeDraft;

/**
 * Помощник, собирающий резюме в диалоге.
 *
 * Отдельный интерфейс нужен, чтобы контроллер не зависел от Gemini, а тесты
 * шли без сети: в контейнере подменяется реализация, и всё остальное работает.
 */
interface ResumeAssistant
{
    /**
     * Готов ли помощник к работе (задан ключ и настройки).
     */
    public function available(): bool;

    /**
     * Следующий ход диалога.
     *
     * @return array{reply:string,stage:string,done:bool,resume:array}
     *
     * @throws AiUnavailableException
     */
    public function next(ResumeDraft $draft, string $message): array;

    /**
     * Финальная версия: причёсанный текст и поля будущего резюме.
     *
     * @return array<string,mixed> поля резюме, готовые к правке и сохранению
     *
     * @throws AiUnavailableException
     */
    public function finalize(ResumeDraft $draft): array;
}
