<?php

namespace App\Services\Scoring;

use App\Models\AiInterviewCriterion;
use App\Models\AiInterviewTurn;

/**
 * Балл за собеседование: взвешенное среднее оценок ответов.
 *
 * Считает код. Модель ставит балл каждому ответу отдельно и обосновывает его, а
 * складывать — работа арифметики: так итог воспроизводим и объясним построчно.
 *
 * Ответ, привязанный к критерию, получает его вес. Ответ без критерия (общий
 * вопрос, не относящийся ни к одному требованию) считается со средним весом:
 * выбрасывать его нельзя — он тоже характеризует кандидата, — но и давать ему
 * вес обязательного требования не за что.
 */
class InterviewScore
{
    /**
     * Балл 0–100 или null, если оценивать нечего.
     *
     * @param  \Illuminate\Support\Collection<int,AiInterviewTurn>|array  $turns
     * @param  \Illuminate\Support\Collection<int,AiInterviewCriterion>|array  $criteria
     */
    public static function compute($turns, $criteria): ?int
    {
        $weights = collect($criteria)
            ->filter(fn (AiInterviewCriterion $c) => $c->isConfirmed())
            ->mapWithKeys(fn (AiInterviewCriterion $c) => [$c->key => max(1, (int) $c->weight)]);

        // вес по умолчанию для ответов вне критериев
        $average = $weights->isEmpty() ? 10 : (int) round($weights->avg());

        $sum = 0.0;
        $total = 0;

        foreach (collect($turns) as $turn) {
            if (! $turn->isFromCandidate() || ! $turn->isScored()) {
                continue;
            }

            $ratio = $turn->ratio();

            if ($ratio === null) {
                continue;
            }

            $weight = $turn->criterion_key
                ? (int) $weights->get($turn->criterion_key, $average)
                : $average;

            $total += $weight;
            $sum += $weight * $ratio;
        }

        // Ни одного оценённого ответа — балла нет. Ноль означал бы, что кандидат
        // отвечал плохо, а правда в том, что он ещё не отвечал.
        return $total > 0 ? (int) round($sum / $total * 100) : null;
    }

    /**
     * Все ответы кандидата оценены.
     *
     * Решение без этого принимать нельзя: недосчитанный балл занижает итог, и
     * кандидат получил бы отказ из-за того, что очередь не успела.
     *
     * @param  \Illuminate\Support\Collection<int,AiInterviewTurn>|array  $turns
     */
    public static function allScored($turns): bool
    {
        foreach (collect($turns) as $turn) {
            if ($turn->isFromCandidate() && ! $turn->isScored()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ответы, которые ещё ждут оценки, — по ним видно, чего ждёт решение.
     *
     * @param  \Illuminate\Support\Collection<int,AiInterviewTurn>|array  $turns
     */
    public static function pending($turns): int
    {
        return collect($turns)
            ->filter(fn (AiInterviewTurn $t) => $t->isFromCandidate() && ! $t->isScored())
            ->count();
    }
}
