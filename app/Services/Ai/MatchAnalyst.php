<?php

namespace App\Services\Ai;

use App\Models\Resume;
use App\Models\Vacancy;

/**
 * Разбор совпадения кандидата и вакансии по навыкам.
 *
 * Отдельный интерфейс — по той же причине, что и у помощника по резюме:
 * контроллер не должен знать о провайдере, а тесты — ходить в сеть.
 */
interface MatchAnalyst
{
    public function available(): bool;

    /**
     * @return array{
     *     matched_skills: array<int,string>,
     *     missing_skills: array<int,string>,
     *     partial_matches: array<int,array{title:string,note:string}>,
     *     verdict: string,
     *     score: int,
     *     enough_data: bool
     * }
     *
     * @throws AiUnavailableException
     */
    public function analyse(Vacancy $vacancy, Resume $resume): array;
}
