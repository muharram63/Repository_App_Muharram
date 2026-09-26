<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Модель ответила, но ответу нельзя верить: он не прошёл проверку схемой ни с
 * первого раза, ни со второго.
 *
 * Отличается от AiUnavailableException по смыслу, и это различие принципиально.
 * «Сервис недоступен» — беда временная: подождали, повторили, пошли дальше.
 * «Ответ негодный» — беда содержательная: модель что-то вернула, но строить на
 * этом решение о человеке нельзя. Такой случай переводит собеседование на
 * ручную проверку, и кандидату не уходит ни отказ, ни приглашение.
 *
 * Поэтому у исключения нет retryAfter: ждать тут нечего.
 */
class AiInvalidAnswerException extends RuntimeException
{
    /**
     * @param  array<string,array<int,string>>  $errors  что именно не сошлось — в аудит
     */
    public function __construct(
        string $message,
        public readonly array $errors = [],
        public readonly ?string $purpose = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Короткая выжимка для записи в аудит и в лог.
     */
    public function summary(): string
    {
        $first = [];

        foreach ($this->errors as $field => $messages) {
            $first[] = $field.': '.(is_array($messages) ? reset($messages) : $messages);
        }

        return implode('; ', array_slice($first, 0, 5)) ?: $this->getMessage();
    }
}
