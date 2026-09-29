<?php

namespace App\Jobs;

use App\Models\AiDecision;
use App\Models\AiInterview;
use App\Models\AiInterviewCriterion;
use App\Models\UserNotification;
use App\Services\Ai\AiInvalidAnswerException;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\DecisionWriter;
use App\Services\Scoring\DecisionEngine;
use App\Services\Scoring\DecisionMessage;
use App\Services\Scoring\InterviewScore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Итоговое решение по кандидату.
 *
 * Решение принимает DecisionEngine — чистый код без единого обращения к модели.
 * Задача лишь исполняет решённое: пишет снимок в ai_decisions, объявляет исход
 * кандидату, ставит статус отклику и зовёт человека, когда система не берётся
 * решать сама.
 *
 * Пограничный случай возвращает кандидата в разговор: между «почти прошёл» и
 * «почти не прошёл» разница часто в одном невыясненном месте, и решать такое
 * баллом нечестно.
 */
class MakeHiringDecision implements ShouldQueue
{
    use Queueable;

    public $tries = 2;

    public array $backoff = [30];

    /** Сколько дополнительных вопросов добавляет пограничный дораунд. */
    public const FOLLOW_UP_QUESTIONS = 3;

    /**
     * Сколько раз ждём догоняющие оценки, прежде чем решать.
     *
     * Ответы оцениваются в очереди параллельно, и последняя оценка может
     * прийти позже, чем задание будет проверено. Объявить в этот момент
     * «ручную проверку» значило бы позвать человека из-за того, что очередь на
     * секунду отстала.
     */
    public const MAX_WAITS = 6;

    public const WAIT_SECONDS = 20;

    public function __construct(
        public readonly int $interviewId,
        public readonly int $waited = 0,
    ) {
    }

    public function handle(DecisionWriter $writer): void
    {
        $interview = AiInterview::with('vacancy.employer', 'applicant', 'config')
            ->find($this->interviewId);

        if (! $interview) {
            return;
        }

        // решение уже объявлено — второй раз не решаем
        if ($interview->isDecided()) {
            return;
        }

        // очередь ещё не досчитала оценки — подождём, а не позовём человека
        if ($this->shouldWait($interview)) {
            self::dispatch($this->interviewId, $this->waited + 1)
                ->delay(now()->addSeconds(self::WAIT_SECONDS));

            return;
        }

        $criteria = AiInterviewCriterion::where('vacancy_id', $interview->vacancy_id)
            ->confirmed()->get();

        $verdict = DecisionEngine::decide(
            $interview,
            $interview->config,
            $criteria,
            $interview->requirementChecks()->get(),
            $interview->turns()->get(),
            $this->testScore($interview),
        );

        if ($verdict['outcome'] === DecisionEngine::FOLLOW_UP) {
            $this->askMore($interview, $verdict);

            return;
        }

        $this->announce($interview, $verdict, $writer);
    }

    /**
     * Стоит ли подождать, а не решать сейчас.
     *
     * Ждём только того, что вот-вот придёт само: неоценённых ответов и
     * непроверенного задания. Помеченное на ручную проверку собеседование не
     * ждём — там ждать нечего, решение всё равно человеку.
     */
    private function shouldWait(AiInterview $interview): bool
    {
        if ($this->waited >= self::MAX_WAITS || $interview->requires_review) {
            return false;
        }

        if (! InterviewScore::allScored($interview->turns()->get())) {
            return true;
        }

        // задание выдано, но ещё не проверено
        $task = $interview->testTasks()->latest('id')->first();

        return $task !== null
            && $task->submission()->first() !== null
            && ! $task->submission()->first()->isGraded();
    }

    /**
     * Пограничный балл: возвращаем кандидата в разговор.
     */
    private function askMore(AiInterview $interview, array $verdict): void
    {
        $interview->update([
            'follow_up_round' => $interview->follow_up_round + 1,
            'stage' => 'interview',
            'documents_score' => $verdict['parts']['documents'],
            'interview_score' => $verdict['parts']['interview'],
            'total_score' => $verdict['total'],
        ]);

        UserNotification::deliver(
            $interview->applicant?->user_id,
            'interview',
            'Ещё несколько вопросов по вакансии',
            'По вакансии «'.($interview->vacancy?->title ?? '—').'» ассистент хочет кое-что уточнить.',
            route('applicant.ai.chat', $interview),
        );
    }

    /**
     * Объявить решение.
     */
    private function announce(AiInterview $interview, array $verdict, DecisionWriter $writer): void
    {
        $outcome = $verdict['outcome'];
        $round = $interview->follow_up_round + 1;

        // Гарантированный текст собирается кодом. Модель может сделать его
        // живее, но не может оставить человека без ответа.
        $fallback = DecisionMessage::template($outcome, $interview, $verdict['reasons']);
        $message = $this->polish($writer, $interview, $outcome, $verdict['reasons'], $fallback);

        $decision = AiDecision::updateOrCreate(
            ['ai_interview_id' => $interview->id, 'round' => $round],
            [
                'documents_score' => $verdict['parts']['documents'],
                'interview_score' => $verdict['parts']['interview'],
                'test_score' => $verdict['parts']['test'],
                'total' => $verdict['total'],
                'outcome' => $outcome,
                'gate_reason' => $verdict['gate_reason'],
                'reasons' => $verdict['reasons'],
                'message_to_candidate' => $message,
                'requires_manual_review' => $verdict['requires_manual_review'],
            ],
        );

        $interview->update([
            'outcome' => $outcome,
            'total_score' => $verdict['total'],
            'documents_score' => $verdict['parts']['documents'],
            'interview_score' => $verdict['parts']['interview'],
            'test_score' => $verdict['parts']['test'],
            'requires_review' => $verdict['requires_manual_review'],
            'decided_at' => now(),
            'stage' => 'done',
        ]);

        $this->applyToResponse($interview, $outcome);
        $this->notifyCandidate($interview, $outcome, $message);
        $this->notifyEmployer($interview, $decision);
    }

    /**
     * Статус отклика.
     *
     * Только в режиме auto и только при объявленном исходе. В режиме advisory
     * работодатель ставит статус сам — ИИ для него советчик, и переписывать за
     * него отклик было бы обманом договорённости.
     */
    private function applyToResponse(AiInterview $interview, string $outcome): void
    {
        if (! $interview->config?->decidesItself()) {
            return;
        }

        if (! in_array($outcome, [DecisionEngine::PASSED, DecisionEngine::REJECTED], true)) {
            return;
        }

        $interview->response?->update([
            'status' => $outcome === DecisionEngine::PASSED ? 'accepted' : 'rejected',
        ]);
    }

    /**
     * Живая формулировка. Любой сбой — берём шаблон и идём дальше: кандидат не
     * должен остаться без ответа из-за того, что кончилась квота.
     *
     * @param  array<int,string>  $reasons
     */
    private function polish(
        DecisionWriter $writer,
        AiInterview $interview,
        string $outcome,
        array $reasons,
        string $fallback,
    ): string {
        if (! $writer->available()) {
            return $fallback;
        }

        try {
            return $writer->write($interview, $outcome, $reasons, $fallback);
        } catch (AiInvalidAnswerException|AiUnavailableException $e) {
            Log::info('Письмо кандидату оставлено шаблонным', [
                'interview' => $interview->id,
                'reason' => $e->getMessage(),
            ]);

            return $fallback;
        }
    }

    private function notifyCandidate(AiInterview $interview, string $outcome, string $message): void
    {
        UserNotification::deliver(
            $interview->applicant?->user_id,
            'response_status',
            DecisionMessage::headline($outcome),
            \Illuminate\Support\Str::limit($message, 160),
            route('applicant.ai.result', $interview),
        );
    }

    private function notifyEmployer(AiInterview $interview, AiDecision $decision): void
    {
        $manual = $decision->requires_manual_review;
        $name = $interview->applicant?->user?->name ?? 'Кандидат';

        UserNotification::deliver(
            $interview->vacancy?->employer?->user_id,
            'interview',
            $manual ? 'Кандидат ждёт вашего решения' : 'ИИ принял решение по кандидату',
            $name.' · «'.($interview->vacancy?->title ?? '—').'» · '.$decision->outcomeLabel(),
            route('employer.ai.candidate', [$interview->vacancy_id, $interview->id]),
        );
    }

    /**
     * Балл за задание. Непроверенное задание оставляет null — и решение уйдёт
     * человеку, а не занизит итог.
     */
    private function testScore(AiInterview $interview): ?int
    {
        $task = $interview->testTasks()->latest('id')->first();

        if (! $task) {
            // Задания нет вовсе. Возможно, работодатель дал ему нулевой вес —
            // тогда движок просто не учтёт эту часть.
            return $interview->config?->weight_test === 0 ? 0 : null;
        }

        $submission = $task->submission()->first();

        return $submission?->isGraded() ? (int) $submission->score : null;
    }

    public function failed(?\Throwable $e): void
    {
        AiInterview::whereKey($this->interviewId)->update(['requires_review' => true]);
    }
}
