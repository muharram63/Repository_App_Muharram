<?php

namespace App\Jobs;

use App\Models\AiInterview;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\TaskExaminer;
use App\Services\Ai\TaskPreparer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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

        $preparer = new TaskPreparer($examiner);

        try {
            // ready, busy (составляет соседний запрос), skipped, failed —
            // во всех случаях очереди делать больше нечего
            $preparer->prepare($interview);
        } catch (AiUnavailableException $e) {
            if ($this->attempts() >= $this->tries) {
                $preparer->giveUp($interview, $e->getMessage());

                return;
            }

            throw $e;
        }
    }

    public function failed(?\Throwable $e): void
    {
        $interview = AiInterview::find($this->interviewId);

        // тем же путём, что и при негодном ответе: иначе кандидат остаётся
        // ждать задания, которого не будет, и никто об этом не узнаёт
        if ($interview) {
            app(TaskPreparer::class)->giveUp($interview, 'Задание не составлено после нескольких попыток.');
        }
    }
}
