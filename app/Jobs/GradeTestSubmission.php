<?php

namespace App\Jobs;

use App\Models\AiTestSubmission;
use App\Jobs\MakeHiringDecision;
use App\Services\Ai\AiInvalidAnswerException;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\TaskExaminer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

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

        if (! $submission || ! $submission->task) {
            return;
        }

        // уже проверено — повторно не платим
        if ($submission->isGraded()) {
            return;
        }

        $interview = $submission->task->interview;

        try {
            $verdict = $examiner->grade($submission->task, (string) $submission->content);
        } catch (AiInvalidAnswerException $e) {
            /*
             * Балл за задание выдумывать нельзя: он идёт в итоговое решение с
             * весом, который задал работодатель. Непроверенное задание — повод
             * позвать человека, а не занижать результат.
             */
            $this->handOver($interview, 'Модель дважды ответила негодно: '.$e->summary());

            return;
        } catch (AiUnavailableException $e) {
            if ($this->attempts() >= $this->tries) {
                $this->handOver($interview, $e->getMessage());

                return;
            }

            throw $e;
        }

        $submission->update([
            'score' => $verdict['score'],
            'review' => $verdict['review'],
            'execution' => $verdict['execution'],
            'graded_at' => now(),
        ]);

        $interview?->update([
            'test_score' => $verdict['score'],
            // разговор и задание позади — дальше решение
            ...($interview->stage === 'test' ? ['stage' => 'decision'] : []),
        ]);

        // все части собраны — можно решать
        if ($interview) {
            MakeHiringDecision::dispatch($interview->id);
        }

        /*
         * Расхождение факта и мнения стоит отметить в журнале.
         *
         * Код не запустился или упал, а балл высокий — это ровно тот случай,
         * ради которого исполнение и заводилось. Решение мы не меняем: пусть
         * работодатель увидит оба числа в отчёте и рассудит сам.
         */
        if ($submission->task->isCode() && $verdict['score'] >= 70 && ! $this->ranCleanly($verdict)) {
            Log::info('Балл за код высокий, а запуск неудачный', [
                'submission' => $submission->id,
                'score' => $verdict['score'],
                'executed' => $verdict['executed'],
            ]);
        }
    }

    /**
     * Код запускался и ни один запуск не завершился ошибкой.
     *
     * @param  array{executed:bool,execution:array<int,array>}  $verdict
     */
    private function ranCleanly(array $verdict): bool
    {
        if (! $verdict['executed']) {
            return false;
        }

        foreach ($verdict['execution'] as $run) {
            if ($run['is_error'] ?? false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Проверить не удалось — дальше решает человек.
     *
     * Пометки requires_review было мало: она никого не извещала. Решение
     * никто не запускал, и собеседование застывало навсегда — кандидат ждал
     * ответа, которого никто не собирался давать, а работодатель не знал, что
     * кандидат ждёт. Решение мы здесь не выдумываем: та же пометка заставит
     * движок объявить ручную проверку и написать обеим сторонам.
     */
    private function handOver(?\App\Models\AiInterview $interview, string $reason): void
    {
        if (! $interview) {
            return;
        }

        $interview->update(['requires_review' => true]);

        Log::warning('Задание не проверено', [
            'interview' => $interview->id,
            'reason' => $reason,
        ]);

        MakeHiringDecision::dispatch($interview->id);
    }

    public function failed(?\Throwable $e): void
    {
        $interview = AiTestSubmission::with('task.interview')
            ->find($this->submissionId)?->task?->interview;

        $this->handOver($interview, 'Проверка не удалась после нескольких попыток.');
    }
}
