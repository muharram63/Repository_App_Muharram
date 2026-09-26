<?php

namespace App\Services\Scoring;

use App\Models\AiInterviewCriterion;
use App\Models\AiRequirementCheck;

/**
 * Балл за документы: насколько требования вакансии закрыты резюме и дипломами.
 *
 * Считает код, а не модель. Модель говорит по каждому требованию «закрыто,
 * частично или нет» и приводит цитату — этого достаточно, чтобы число вывел
 * сервер. Так балл объясним: видно, из чего он сложился, и пересчёт даст то же
 * самое.
 *
 * Веса нормируются, а не требуют суммы 100. Работодатель ставит их как считает
 * нужным, а важно соотношение — тот же приём, что в MatchCriteria у разбора
 * совпадения.
 */
class RequirementScore
{
    /**
     * Балл 0–100 по проверкам требований.
     *
     * Учитываются только подтверждённые работодателем критерии: неподтверждённый
     * в оценке не участвует, и включать его в знаменатель тоже нельзя — иначе
     * черновик от модели занижал бы результат кандидата.
     *
     * @param  \Illuminate\Support\Collection<int,AiRequirementCheck>|array  $checks
     * @param  \Illuminate\Support\Collection<int,AiInterviewCriterion>|array  $criteria
     */
    public static function compute($checks, $criteria): ?int
    {
        $weights = collect($criteria)
            ->filter(fn (AiInterviewCriterion $c) => $c->isConfirmed())
            ->mapWithKeys(fn (AiInterviewCriterion $c) => [$c->key => max(1, (int) $c->weight)]);

        if ($weights->isEmpty()) {
            return null;
        }

        $sum = 0.0;
        $total = 0;

        foreach (collect($checks) as $check) {
            $key = self::keyOf($check, $criteria);

            if ($key === null || ! $weights->has($key)) {
                continue;
            }

            $weight = $weights->get($key);
            $total += $weight;
            $sum += $weight * $check->weightShare();
        }

        // Ни одной проверки по подтверждённым критериям — балла нет вовсе.
        // Ноль здесь означал бы «кандидат ничего не закрыл», а правда в том,
        // что сверка не состоялась.
        if ($total === 0) {
            return null;
        }

        return (int) round($sum / $total * 100);
    }

    /**
     * Ключ критерия, к которому относится проверка.
     *
     * Проверка ссылается на критерий по идентификатору, но критерий могли
     * удалить — тогда ссылка обнулена, а сама проверка осталась в отчёте
     * кандидата. В таком случае в оценку она не идёт: веса у удалённого
     * критерия больше нет.
     */
    private static function keyOf(AiRequirementCheck $check, $criteria): ?string
    {
        if (! $check->criterion_id) {
            return null;
        }

        return collect($criteria)->firstWhere('id', $check->criterion_id)?->key;
    }

    /**
     * Все обязательные требования закрыты.
     *
     * Ворота решения смотрят на это, а не на балл: не выполненное обязательное
     * требование — отказ, сколько бы ни набралось в остальном.
     *
     * @param  \Illuminate\Support\Collection<int,AiRequirementCheck>|array  $checks
     */
    public static function mustHavesSatisfied($checks): bool
    {
        foreach (collect($checks) as $check) {
            if ($check->isMust() && ! $check->isSatisfied()) {
                return false;
            }
        }

        return true;
    }
}
