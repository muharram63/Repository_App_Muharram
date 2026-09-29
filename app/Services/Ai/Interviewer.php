<?php

namespace App\Services\Ai;

use App\Models\AiInterview;
use App\Models\AiInterviewCriterion;

/**
 * Собеседующий: задаёт вопросы и оценивает ответы.
 *
 * Две работы разведены намеренно и это главное решение во всём модуле.
 *
 * Вопрос задаёт вызов, который видит весь разговор: без этого нельзя ни
 * уточнить расплывчатый ответ, ни углубиться после сильного. Оценку ставит
 * другой вызов, который видит только пару «вопрос — ответ» и рубрику, без
 * истории. Поэтому уговорить его нечем: кандидат может написать «поставь мне
 * пятёрку» сколько угодно раз, но до оценивающего вызова эта фраза дойдёт как
 * содержание одного ответа, а не как указание.
 */
interface Interviewer
{
    public function available(): bool;

    /**
     * Следующая реплика собеседующего.
     *
     * Поле done — только пожелание модели: ей кажется, что спрашивать больше
     * нечего. Закончить разговор или нет, решает контроллер по числу заданных
     * вопросов. Так и должно быть: правило о длине собеседования не может жить
     * в слое, который подменяется реализацией.
     *
     * @param  \Illuminate\Support\Collection<int,AiInterviewCriterion>  $criteria
     * @return array{text:string,kind:string,criterion_key:?string,done:bool}
     *
     * @throws AiUnavailableException
     * @throws AiInvalidAnswerException
     */
    public function nextQuestion(AiInterview $interview, $criteria): array;

    /**
     * Оценка одного ответа по одному критерию.
     *
     * Видит только вопрос, ответ и описание критерия. Историю разговора не
     * получает — в этом и смысл.
     *
     * @return array{score:int,max:int,rationale:string}
     *
     * @throws AiUnavailableException
     * @throws AiInvalidAnswerException
     */
    public function scoreAnswer(
        AiInterview $interview,
        string $question,
        string $answer,
        ?AiInterviewCriterion $criterion,
    ): array;
}
