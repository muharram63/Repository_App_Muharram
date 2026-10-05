<?php

namespace App\Jobs;

use App\Models\AiTestSubmission;
use App\Jobs\MakeHiringDecision;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\TaskExaminer;
use App\Services\Ai\TestGrader;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Проверка решения тестового задания.
 *
 * Для кода модель запускает решение в песочнице провайдера. Вывод программы
 * сохраняется отдельно от оценки: это факт, а балл — мнение. Если они
 * разойдутся, в отчёте будут видны оба, и работодатель решит сам.
 */
class GradeTestSubmission implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public readonly int $submissionId)
    {
    }

    public function handle(TaskExaminer $examiner): void
    {
        $submission = AiTestSubmission::with('task.interview')->find($this->submissionId);

        if (! $submission || ! $submission->task || $submission->isGraded()) {
            return;
        }

        $grader = new TestGrader($examiner);

        try {
            $grader->gradeAll($submission);
        } catch (AiUnavailableException $e) {
            if ($this->attempts() >= $this->tries) {
                $grader->handOver($submission, $e->getMessage());
                $this->decide($submission);

                return;
            }

            throw $e;
        }

        // проверено или передано человеку — дальше решение
        $this->decide($submission);
    }

    private function decide(AiTestSubmission $submission): void
    {
        $interview = $submission->task?->interview;

        if ($interview) {
            MakeHiringDecision::dispatch($interview->id);
        }
    }

    public function failed(?\Throwable $e): void
    {
        $submission = AiTestSubmission::with('task.interview')->find($this->submissionId);

        if ($submission) {
            app(TestGrader::class)->handOver($submission, 'Проверка не удалась после нескольких попыток.');
            $this->decide($submission);
        }
    }
}
