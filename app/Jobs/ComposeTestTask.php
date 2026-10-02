<?php

namespace App\Jobs;

use App\Models\AiInterview;
use App\Models\AiInterviewCriterion;
use App\Models\AiTestTask;
use App\Services\Ai\AiInvalidAnswerException;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\TaskExaminer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
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
class ComposeTestTask implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public array $backoff = [30, 120];

    /**
     * Одно задание на собеседование — больше одной такой задачи в очереди не
     * бывает.
     *
     * Без этого очередь заполнялась копиями. Страница задания перезагружается
     * каждые пять секунд, пока задания нет, и при каждой загрузке ставила
     * составление в очередь. Проверка перед постановкой смотрела, есть ли
     * готовое задание, — а оно появляется только когда задача выполнится.
     * Задача, стоящая в очереди, этой проверке не видна вовсе. Живой случай:
     * за час в очереди накопилось 236 копий.
     *
     * Сами копии работы не делали — handle() выходит, увидев готовое задание,
     * — но очередь они забивали и задерживали всё остальное.
     */
    public function uniqueId(): string
    {
        return (string) $this->interviewId;
    }

    /**
     * На сколько держится замок, если задача пропадёт, не доработав.
     *
     * Десять минут: составление занимает до минуты, а потерянная задача не
     * должна запирать собеседование навсегда.
     */
    public int $uniqueFor = 600;

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
        $interview->update([
            'requires_review' => true,
            // стадия решения: ждать больше нечего, задания не будет
            ...($interview->stage === 'test' ? ['stage' => 'decision'] : []),
        ]);

        Log::warning('Тестовое задание не составлено', [
            'interview' => $interview->id,
            'reason' => $reason,
        ]);

        /*
         * Дальше — решение, а не тишина.
         *
         * Одной пометки requires_review было мало: собеседование оставалось на
         * стадии задания, которого не будет, решение никто не запускал, и
         * кандидат до конца дней смотрел на «задание готовится», а страница
         * перезагружалась каждые пять секунд. Ни он, ни работодатель не узнавали,
         * что случилось.
         *
         * Своих уведомлений здесь нет намеренно: пометка requires_review
         * заставит движок объявить ручную проверку, и тот же код, что обычно,
         * напишет кандидату про обещанный срок ответа и позовёт работодателя.
         */
        MakeHiringDecision::dispatch($interview->id);
    }

    public function failed(?\Throwable $e): void
    {
        $interview = AiInterview::find($this->interviewId);

        // тем же путём, что и при негодном ответе: иначе кандидат остаётся
        // ждать задания, которого не будет, и никто об этом не узнаёт
        if ($interview) {
            $this->giveUp($interview, 'Задание не составлено после нескольких попыток.');
        }
    }
}
