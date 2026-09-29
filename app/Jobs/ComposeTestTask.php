<?php

namespace App\Jobs;

use App\Models\AiInterview;
use App\Models\AiInterviewCriterion;
use App\Models\AiTestTask;
use App\Services\Ai\AiInvalidAnswerException;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\TaskExaminer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Составление тестового задания.
 *
 * В очереди: модель пишет условие, эталон и рубрику разом, и это самый долгий
 * вызов во всём модуле. Кандидат в это время видит «задание готовится», а не
 * пустой экран с крутилкой.
 *
 * Отсчёт времени начинается не здесь, а когда кандидат впервые откроет готовое
 * задание. Иначе минуты утекали бы, пока он ходит за чаем после собеседования.
 */
class ComposeTestTask implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public readonly int $interviewId)
    {
    }

    public function handle(TaskExaminer $examiner): void
    {
        $interview = AiInterview::find($this->interviewId);

        if (! $interview) {
            return;
        }

        // задание уже составлено — второй раз не платим
        if ($interview->testTasks()->where('kind', AiTestTask::KIND_MAIN)->exists()) {
            return;
        }

        $criteria = AiInterviewCriterion::where('vacancy_id', $interview->vacancy_id)
            ->confirmed()->ordered()->get();

        try {
            $task = $examiner->compose($interview, $criteria);
        } catch (AiInvalidAnswerException $e) {
            /*
             * Задание выдумывать нельзя. Без него решение придётся принимать по
             * документам и разговору, а это уже не то, о чём договаривались с
             * работодателем, — зовём человека.
             */
            $this->giveUp($interview, 'Не удалось составить задание: '.$e->summary());

            return;
        } catch (AiUnavailableException $e) {
            if ($this->attempts() >= $this->tries) {
                $this->giveUp($interview, $e->getMessage());

                return;
            }

            throw $e;
        }

        AiTestTask::create([
            'ai_interview_id' => $interview->id,
            'kind' => AiTestTask::KIND_MAIN,
            'statement' => $task['statement'],
            'format' => $task['format'],
            'language' => $task['language'],
            'reference_solution' => $task['reference_solution'],
            'rubric' => $task['rubric'],
            'time_limit_minutes' => $task['minutes'],
            // issued_at и deadline_at ставит первый показ задания кандидату
        ]);
    }

    private function giveUp(AiInterview $interview, string $reason): void
    {
        $interview->update(['requires_review' => true]);

        Log::warning('Тестовое задание не составлено', [
            'interview' => $interview->id,
            'reason' => $reason,
        ]);
    }

    public function failed(?\Throwable $e): void
    {
        AiInterview::whereKey($this->interviewId)->update(['requires_review' => true]);
    }
}
