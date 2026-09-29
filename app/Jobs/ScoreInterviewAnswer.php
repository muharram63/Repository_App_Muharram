<?php

namespace App\Jobs;

use App\Models\AiInterviewCriterion;
use App\Models\AiInterviewTurn;
use App\Models\AiRequirementCheck;
use App\Services\Ai\AiInvalidAnswerException;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\Interviewer;
use App\Services\Scoring\InterviewScore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Оценка одного ответа кандидата.
 *
 * В очереди, а не в запросе: оценка идёт на модели посильнее и занимает до
 * десяти секунд, а кандидату балл всё равно не показывают. Ждать его — значит
 * тормозить разговор без всякой пользы для человека.
 *
 * Здесь же требование может подняться до «подтверждено на собеседовании»:
 * навык, которого не нашлось в резюме, кандидат вполне мог доказать словами, и
 * не учесть этого было бы несправедливо.
 */
class ScoreInterviewAnswer implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public array $backoff = [20, 90];

    /**
     * С какого балла считаем требование подтверждённым устно.
     *
     * Четыре из пяти: «конкретно и с результатом». Тройка — это «понятно, что
     * делал», и для замены отсутствующего доказательства в документах её мало.
     */
    public const CONFIRMS_FROM = 4;

    public function __construct(public readonly int $turnId)
    {
    }

    public function handle(Interviewer $interviewer): void
    {
        $turn = AiInterviewTurn::with('interview')->find($this->turnId);

        // реплику могли удалить вместе с собеседованием
        if (! $turn || ! $turn->interview || ! $turn->isFromCandidate()) {
            return;
        }

        // уже оценён — повторно не платим
        if ($turn->isScored()) {
            return;
        }

        $question = $this->questionFor($turn);

        if ($question === null) {
            // ответ без вопроса — такого быть не должно, но оценивать нечего
            Log::warning('Ответ без вопроса', ['turn' => $turn->id]);

            return;
        }

        $criterion = $turn->criterion_key
            ? AiInterviewCriterion::where('vacancy_id', $turn->interview->vacancy_id)
                ->where('key', $turn->criterion_key)->first()
            : null;

        try {
            $verdict = $interviewer->scoreAnswer(
                $turn->interview,
                (string) $question->text,
                (string) $turn->text,
                $criterion,
            );
        } catch (AiInvalidAnswerException $e) {
            /*
             * Оценку выдумывать нельзя. Ставим собеседование на ручную
             * проверку: недосчитанный балл занижает итог, и кандидат получил бы
             * отказ из-за того, что модель дважды ответила негодно.
             */
            $turn->interview->update(['requires_review' => true]);

            Log::warning('Ответ не оценён', [
                'turn' => $turn->id,
                'reason' => $e->summary(),
            ]);

            return;
        } catch (AiUnavailableException $e) {
            if ($this->attempts() >= $this->tries) {
                $turn->interview->update(['requires_review' => true]);

                return;
            }

            throw $e;
        }

        $turn->update([
            'score' => $verdict['score'],
            'max_score' => $verdict['max'],
            'rationale' => $verdict['rationale'],
        ]);

        $this->maybeConfirmRequirement($turn, $criterion, $verdict['score']);
        $this->refreshScore($turn->interview);
    }

    /**
     * Вопрос, на который отвечал кандидат, — ближайшая реплика ИИ перед ответом.
     */
    private function questionFor(AiInterviewTurn $turn): ?AiInterviewTurn
    {
        return AiInterviewTurn::where('ai_interview_id', $turn->ai_interview_id)
            ->where('role', AiInterviewTurn::ROLE_AI)
            ->where('position', '<', $turn->position)
            ->orderByDesc('position')
            ->first();
    }

    /**
     * Сильный ответ закрывает требование, которого не нашлось в документах.
     *
     * Ворота решения смотрят на финальный статус требования, и это как раз тот
     * случай, ради которого статус сделан меняющимся: навык у человека есть,
     * просто в резюме о нём не написано.
     */
    private function maybeConfirmRequirement(
        AiInterviewTurn $turn,
        ?AiInterviewCriterion $criterion,
        int $score,
    ): void {
        if (! $criterion || $score < self::CONFIRMS_FROM) {
            return;
        }

        $check = AiRequirementCheck::where('ai_interview_id', $turn->ai_interview_id)
            ->where('criterion_id', $criterion->id)
            ->first();

        // уже закрыто документами — понижать или переписывать незачем
        if ($check && $check->isSatisfied()) {
            return;
        }

        $evidence = \Illuminate\Support\Str::limit((string) $turn->text, 400, '');

        if ($check) {
            $check->update([
                'status' => 'confirmed_in_interview',
                'evidence_quote' => $evidence,
                'confidence' => (int) round($score / AiInterviewTurn::MAX_SCORE * 100),
                'origin' => 'interview',
            ]);

            return;
        }

        AiRequirementCheck::create([
            'ai_interview_id' => $turn->ai_interview_id,
            'criterion_id' => $criterion->id,
            'requirement' => $criterion->label,
            'kind' => $criterion->kind,
            'status' => 'confirmed_in_interview',
            'evidence_quote' => $evidence,
            'confidence' => (int) round($score / AiInterviewTurn::MAX_SCORE * 100),
            'origin' => 'interview',
        ]);
    }

    /**
     * Пересчёт балла за собеседование после каждой оценки: так работодатель
     * видит цифру, не дожидаясь конца разговора.
     */
    private function refreshScore($interview): void
    {
        $criteria = AiInterviewCriterion::where('vacancy_id', $interview->vacancy_id)
            ->confirmed()->get();

        $interview->update([
            'interview_score' => InterviewScore::compute($interview->turns()->get(), $criteria),
        ]);
    }

    public function failed(?\Throwable $e): void
    {
        $turn = AiInterviewTurn::with('interview')->find($this->turnId);

        // Неоценённый ответ — повод позвать человека, а не решать без него.
        $turn?->interview?->update(['requires_review' => true]);
    }
}
