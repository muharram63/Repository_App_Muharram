<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Сбой помощника, о котором не стыдно рассказать пользователю: текст
 * сообщения уже человеческий и готов к показу в интерфейсе.
 *
 * $retryAfter заполняется, когда провайдер сам сказал, через сколько секунд
 * повторять — на бесплатном ключе это обычное дело, и интерфейс превращает
 * такую паузу в обратный отсчёт вместо тупиковой ошибки.
 */
class AiUnavailableException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $retryAfter = null)
    {
        parent::__construct($message);
    }
}
