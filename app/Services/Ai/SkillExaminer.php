<?php

namespace App\Services\Ai;

use App\Models\Skill;

/**
 * Экзаменатор: составляет задание по навыку и проверяет свободные ответы.
 *
 * Варианты ответа сверяет сам сервер по ключу — это быстро, бесплатно и не
 * зависит от настроения модели. К ИИ обращаемся только там, где человек
 * ответил своими словами: это и есть то, чего не умеет обычный тест.
 */
interface SkillExaminer
{
    public function available(): bool;

    /**
     * Составить задание: вопросы с вариантами, верным ответом и пояснением.
     *
     * @return array<int,array{text:string,options:array<int,string>,answer:int,explain:string}>
     *
     * @throws AiUnavailableException
     */
    public function compose(Skill $skill, string $level, int $variant): array;

    /**
     * Проверить свободные ответы кандидата.
     *
     * @param  array<int,array{question:string,expected:string,answer:string}>  $answers
     * @return array<int,array{correct:bool,comment:string}> в том же порядке
     *
     * @throws AiUnavailableException
     */
    public function grade(Skill $skill, array $answers): array;
}
