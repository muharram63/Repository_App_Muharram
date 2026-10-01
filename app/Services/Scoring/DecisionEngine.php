<?php

namespace App\Services\Scoring;

use App\Models\AiInterview;
use App\Models\AiInterviewConfig;
use App\Models\AiRequirementCheck;

/**
 * Решение по кандидату.
 *
 * Здесь нет ни одного обращения к модели, и это главное свойство класса.
 * Модель оценивает документы, ответы и задание по отдельности и обосновывает
 * каждую оценку; складывает их и применяет правила код. Поэтому решение
 * воспроизводимо: те же данные дают тот же исход, сколько раз ни пересчитывай,
 * и объяснить его можно построчно.
 *
 * Порядок правил важнее их содержания. Сначала проверяется, можно ли вообще
 * решать: недосчитанная оценка или неразобранные документы означают не «плохо»,
 * а «данных нет», и в этом случае решает человек. Только потом — ворота по
 * обязательным требованиям, и лишь в конце арифметика порогов.
 */
class DecisionEngine
{
    /**
     * Исходы. follow_up — не исход, а указание: балл пограничный, и стоит
     * задать ещё вопросов. Решением он не становится и в базу не пишется.
     */
    public const PASSED = 'passed';

    public const REJECTED = 'rejected';

    public const MANUAL = 'manual_review';

    public const FOLLOW_UP = 'follow_up';

    /**
     * Принять решение.
     *
     * @param  \Illuminate\Support\Collection<int,AiInterviewCriterion>  $criteria
     * @param  \Illuminate\Support\Collection<int,AiRequirementCheck>  $checks
     * @param  \Illuminate\Support\Collection<int,\App\Models\AiInterviewTurn>  $turns
     * @return array{
     *     outcome:string, total:?int, gate_reason:?string,
     *     parts:array{documents:?int,interview:?int,test:?int},
     *     reasons:array<int,string>, unmet:array<int,string>,
     *     requires_manual_review:bool
     * }
     */
    public static function decide(
        AiInterview $interview,
        AiInterviewConfig $config,
        $criteria,
        $checks,
        $turns,
        ?int $testScore,
    ): array {
        $parts = [
            'documents' => $interview->documents_score,
            'interview' => InterviewScore::compute($turns, $criteria),
            'test' => $testScore,
        ];

        // ===== 1. можно ли вообще решать =====

        if ($blocker = self::notReady($interview, $parts, $turns)) {
            return self::manual($parts, $blocker['gate'], [$blocker['reason']]);
        }

        // ===== 2. ворота: обязательные требования =====

        $unsatisfied = collect($checks)
            ->filter(fn (AiRequirementCheck $c) => $c->isMust() && ! $c->isSatisfied());

        $unmet = $unsatisfied->map(fn (AiRequirementCheck $c) => $c->requirement)->values()->all();

        $total = self::total($parts, $config);

        /*
         * «Ничего не нашлось» и «нашлось наполовину» — разные вещи.
         *
         * Требование со статусом missing означает, что подтверждения нет вовсе:
         * это отказ, сколько бы ни набралось в остальном, иначе вакансия «нужен
         * электрик» пропускала бы повара с красивыми ответами.
         *
         * А partial — это «что-то есть, но доказательство неполное», и отказывать
         * по нему нечестно: ровно эту неясность собеседование и существует
         * разрешать. Если и разговор не поднял требование до подтверждённого,
         * правильный исход не «нет», а «решает человек» — тот же, что система
         * выбирает всюду, где данных не хватает.
         *
         * Поводом стал живой прогон: у кандидата с резюме «PHP-разработчик,
         * 4 года, Laravel, MySQL» модель поставила php и sql статус partial, и
         * крепкий средний получал автоматический отказ при итоге 20.
         */
        $missing = $unsatisfied
            ->filter(fn (AiRequirementCheck $c) => $c->status !== 'partial')
            ->map(fn (AiRequirementCheck $c) => $c->requirement)
            ->values()
            ->all();

        if ($missing !== []) {
            return [
                'outcome' => self::REJECTED,
                'total' => $total,
                'gate_reason' => 'missing_must_have',
                'parts' => $parts,
                'reasons' => $missing,
                'unmet' => $unmet,
                'requires_manual_review' => false,
            ];
        }

        if ($unmet !== []) {
            return [
                'outcome' => self::MANUAL,
                'total' => $total,
                'gate_reason' => 'unconfirmed_must_have',
                'parts' => $parts,
                'reasons' => $unmet,
                'unmet' => $unmet,
                'requires_manual_review' => true,
            ];
        }

        // ===== 3. пороги =====

        if ($total === null) {
            return self::manual($parts, 'empty_documents',
                ['Итоговый балл посчитать не из чего.']);
        }

        if ($total < $config->threshold_reject) {
            return [
                'outcome' => self::REJECTED,
                'total' => $total,
                'gate_reason' => 'below_reject_threshold',
                'parts' => $parts,
                'reasons' => self::weakSpots($checks, $parts, $config),
                'unmet' => [],
                'requires_manual_review' => false,
            ];
        }

        if ($total >= $config->threshold_accept) {
            return [
                'outcome' => self::PASSED,
                'total' => $total,
                'gate_reason' => 'above_accept_threshold',
                'parts' => $parts,
                'reasons' => [],
                'unmet' => [],
                'requires_manual_review' => false,
            ];
        }

        // ===== 4. пограничная зона =====

        /*
         * Балл между порогами. Первый раз — доспрашиваем: между «почти» и
         * «почти» разница часто в одном невыясненном месте, и отказать или
         * принять человека по такому баллу нечестно.
         *
         * Второй раз уже не доспрашиваем: если после уточнений всё равно
         * неясно, дальше спрашивать бессмысленно — это работа для человека.
         */
        if ($interview->canAskFollowUp()) {
            return [
                'outcome' => self::FOLLOW_UP,
                'total' => $total,
                'gate_reason' => 'borderline',
                'parts' => $parts,
                'reasons' => self::weakSpots($checks, $parts, $config),
                'unmet' => [],
                'requires_manual_review' => false,
            ];
        }

        return self::manual($parts, 'borderline_after_follow_up',
            ['Балл остался пограничным и после уточняющих вопросов.'], $total);
    }

    /**
     * Что мешает решать.
     *
     * @return array{gate:string,reason:string}|null
     */
    private static function notReady(AiInterview $interview, array $parts, $turns): ?array
    {
        if ($interview->requires_review) {
            return [
                'gate' => 'invalid_ai_response',
                'reason' => 'В ходе собеседования что-то не удалось разобрать автоматически.',
            ];
        }

        if ($interview->analysis_status !== 'ready' || $parts['documents'] === null) {
            return [
                'gate' => 'empty_documents',
                'reason' => 'Документы и резюме не разобраны.',
            ];
        }

        /*
         * Неоценённый ответ занижает балл за интервью, а значит и итог. Решить
         * по такому баллу — значит отказать человеку из-за того, что очередь не
         * успела, а не из-за его ответов.
         */
        if (! InterviewScore::allScored($turns)) {
            return [
                'gate' => 'invalid_ai_response',
                'reason' => 'Оценены не все ответы: осталось '.InterviewScore::pending($turns).'.',
            ];
        }

        if ($parts['interview'] === null) {
            return [
                'gate' => 'invalid_ai_response',
                'reason' => 'Балл за собеседование не посчитан.',
            ];
        }

        if ($parts['test'] === null) {
            return [
                'gate' => 'invalid_ai_response',
                'reason' => 'Тестовое задание не проверено.',
            ];
        }

        return null;
    }

    /**
     * Взвешенная сумма частей.
     *
     * Веса нормируются по тем частям, которые есть. Это важно для вакансий, где
     * работодатель поставил нулевой вес заданию: делить на сто, когда одна из
     * частей не в счёт, значило бы занижать итог у всех.
     */
    private static function total(array $parts, AiInterviewConfig $config): ?int
    {
        $weights = [
            'documents' => (int) $config->weight_documents,
            'interview' => (int) $config->weight_interview,
            'test' => (int) $config->weight_test,
        ];

        $sum = 0.0;
        $used = 0;

        foreach ($parts as $key => $value) {
            $weight = $weights[$key] ?? 0;

            if ($value === null || $weight === 0) {
                continue;
            }

            $used += $weight;
            $sum += $weight * $value;
        }

        return $used > 0 ? (int) round($sum / $used) : null;
    }

    /**
     * Где именно кандидат недобрал — для объяснения отказа.
     *
     * Только то, что можно сказать человеку вслух: какие требования закрыты
     * частично или не закрыты и какая часть отбора вышла слабой. Внутренние
     * баллы сюда не попадают — кандидату их не сообщают.
     *
     * @return array<int,string>
     */
    private static function weakSpots($checks, array $parts, AiInterviewConfig $config): array
    {
        $reasons = collect($checks)
            ->filter(fn (AiRequirementCheck $c) => ! $c->isSatisfied())
            ->sortByDesc(fn (AiRequirementCheck $c) => $c->isMust() ? 1 : 0)
            ->map(fn (AiRequirementCheck $c) => $c->requirement)
            ->take(4)
            ->values()
            ->all();

        if ($reasons !== []) {
            return $reasons;
        }

        // Требования закрыты, а балла не хватило — значит дело в глубине
        // ответов или в задании. Называем часть, а не цифру.
        $parts = array_filter($parts, fn ($value) => $value !== null);
        asort($parts);

        $labels = [
            'documents' => 'подтверждение опыта документами',
            'interview' => 'подробность ответов на собеседовании',
            'test' => 'результат тестового задания',
        ];

        $weakest = array_key_first($parts);

        return $weakest ? [$labels[$weakest]] : [];
    }

    /**
     * @param  array<int,string>  $reasons
     */
    private static function manual(array $parts, string $gate, array $reasons, ?int $total = null): array
    {
        return [
            'outcome' => self::MANUAL,
            'total' => $total,
            'gate_reason' => $gate,
            'parts' => $parts,
            'reasons' => $reasons,
            'unmet' => [],
            'requires_manual_review' => true,
        ];
    }
}
