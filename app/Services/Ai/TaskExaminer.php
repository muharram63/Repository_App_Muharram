<?php

namespace App\Services\Ai;

use App\Models\AiInterview;
use App\Models\AiTestTask;

/**
 * Тестовое задание: составление и проверка.
 *
 * Два метода в одном интерфейсе, как у проверки навыков (SkillExaminer): это
 * одна работа, разнесённая во времени. Составить задание, не зная, как его
 * потом проверять, нельзя — эталон и критерии рождаются вместе с условием.
 *
 * Эталонное решение и рубрика создаются заранее и кандидату не показываются
 * никогда. Иначе задание проверяло бы не умение, а внимательность к исходному
 * коду страницы.
 */
interface TaskExaminer
{
    public function available(): bool;

    /**
     * Составить задание под вакансию и уровень.
     *
     * @param  \Illuminate\Support\Collection  $criteria
     * @return array{
     *     statement:string, format:string, language:?string,
     *     reference_solution:string, rubric:array<int,array{point:string,weight:int}>,
     *     minutes:int
     * }
     *
     * @throws AiUnavailableException
     * @throws AiInvalidAnswerException
     */
    public function compose(AiInterview $interview, $criteria): array;

    /**
     * Проверить решение.
     *
     * Для кода модель запускает его в песочнице провайдера, и результат запуска
     * возвращается отдельно от оценки: это факт, а мнение — рядом. При
     * расхождении верить надо факту.
     *
     * @return array{
     *     score:int, review:array<int,array{point:string,passed:bool,note:string}>,
     *     summary:string, executed:bool, execution:array<int,array>
     * }
     *
     * @throws AiUnavailableException
     * @throws AiInvalidAnswerException
     */
    public function grade(AiTestTask $task, string $solution): array;
}
