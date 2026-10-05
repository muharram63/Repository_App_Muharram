<?php

namespace App\Services\Ai;

use App\Models\AiInterview;

/**
 * Разговор работодателя с ИИ о кандидате.
 *
 * ИИ провёл собеседование и теперь докладывает: кто кандидат, насколько
 * подходит, почему такая рекомендация. Работодатель спрашивает голосом или
 * текстом и может так же решить — принять или отказать. Решение ИИ сам не
 * исполняет: он только предлагает его, а отправляет кандидату ответ страница
 * после явного «да» работодателя.
 *
 * Интерфейс, а не класс: в тестах подменяется заглушкой.
 */
interface HiringAdvisor
{
    public function available(): bool;

    /**
     * @param  array<int,array{role:string,text:string}>  $history  прошлые реплики разговора
     * @param  string  $message  пустая строка — «доложи о кандидате»
     * @return array{reply:string,action:string} action: none | accept | reject
     *
     * @throws AiUnavailableException
     * @throws AiInvalidAnswerException
     */
    public function advise(AiInterview $interview, array $history, string $message, string $locale): array;
}
