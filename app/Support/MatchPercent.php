<?php

namespace App\Support;

use App\Models\AiInterviewCriterion;
use App\Models\AiRequirementCheck;
use Illuminate\Support\Collection;

/**
 * Насколько кандидат соответствует вакансии — в процентах, по сверке требований.
 *
 * Три числа для отчёта HR: по профессии, по навыкам и общее. Считает код, а
 * не модель: каждое требование вакансии сверено с документами и ответами,
 * и статус переводится в долю — подтверждено целиком, частично наполовину,
 * не найдено — ноль, — с весом, который работодатель дал критерию.
 *
 * «Профессия» — критерии, выросшие из должности, описания и опыта; «навыки» —
 * из навыков вакансии (поле basis критерия). Если таких критериев нет, число
 * не выдумывается: отчёт пишет, что их не размечено.
 */
class MatchPercent
{
    /** Основы критерия, которые говорят о самой профессии. */
    public const PROFESSION_BASES = ['title', 'description', 'experience'];

    public const SKILL_BASES = ['skills'];

    /**
     * @param  Collection<int,AiInterviewCriterion>  $criteria  подтверждённые критерии вакансии
     * @param  Collection<int,AiRequirementCheck>  $checks  сверка требований кандидата
     * @return array{profession:?int,skills:?int,overall:?int}
     */
    public static function compute(Collection $criteria, Collection $checks): array
    {
        $byCriterion = $checks->keyBy('criterion_id');

        $part = function (Collection $subset) use ($byCriterion): ?int {
            $weights = 0;
            $sum = 0.0;

            foreach ($subset as $criterion) {
                $weight = max(1, (int) $criterion->weight);
                $weights += $weight;
                $sum += $weight * self::share($byCriterion->get($criterion->id)?->status);
            }

            return $weights > 0 ? (int) round($sum / $weights * 100) : null;
        };

        return [
            'profession' => $part($criteria->filter(fn ($c) => in_array($c->basis, self::PROFESSION_BASES, true))),
            'skills' => $part($criteria->filter(fn ($c) => in_array($c->basis, self::SKILL_BASES, true))),
            'overall' => $part($criteria),
        ];
    }

    /** Доля, которую даёт статус требования. */
    public static function share(?string $status): float
    {
        return match ($status) {
            'found', 'confirmed_in_interview' => 1.0,
            'partial' => 0.5,
            default => 0.0,
        };
    }
}
