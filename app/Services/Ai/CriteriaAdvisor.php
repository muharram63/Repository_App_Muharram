<?php

namespace App\Services\Ai;

use App\Models\Vacancy;

/**
 * Советчик по критериям оценки.
 *
 * Разбирает описание вакансии и предлагает, по чему оценивать кандидата: что
 * обязательно, что желательно и с каким весом. Работодателю остаётся проверить
 * и подтвердить — предложенное в оценке не участвует, пока он этого не сделал.
 *
 * Почему именно так, а не «ИИ сам решил». Критерии — это правила, по которым
 * потом отказывают людям. Правила должен утверждать человек, иначе объяснить
 * отказ будет нечем: «модель так посчитала» не объяснение.
 */
interface CriteriaAdvisor
{
    public function available(): bool;

    /**
     * Предложить критерии по описанию вакансии.
     *
     * @return array<int,array{key:string,label:string,description:string,kind:string,weight:int}>
     *
     * @throws AiUnavailableException  сервис недоступен, можно повторить
     * @throws AiInvalidAnswerException  ответу нельзя верить, нужен человек
     */
    public function suggest(Vacancy $vacancy): array;
}
