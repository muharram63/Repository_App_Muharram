<?php

namespace App\Console\Commands;

use App\Models\AiTestTask;
use Illuminate\Console\Command;

/**
 * Фоновая уборка ИИ-собеседований.
 *
 * Страница задания закрывает просроченную попытку сама, но только если
 * кандидат на неё вернётся. Кто закрыл вкладку и не пришёл, оставлял
 * собеседование на стадии задания навсегда: решения нет, работодатель ждёт,
 * кандидат не получает ответа. Эта команда закрывает такие задания нулём и
 * передаёт собеседование решению — тем же путём, что и страница.
 *
 * Запускается планировщиком каждую минуту (routes/console.php).
 *
 *   php artisan ai:sweep
 */
class SweepAiInterviews extends Command
{
    protected $signature = 'ai:sweep';

    protected $description = 'Закрыть просроченные тестовые задания ИИ-собеседований и передать их решению';

    public function handle(): int
    {
        $closed = 0;

        AiTestTask::query()
            ->where('kind', AiTestTask::KIND_MAIN)
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<', now()->subSeconds(AiTestTask::GRACE_SECONDS))
            ->whereDoesntHave('submissions')
            ->whereHas('interview', fn ($q) => $q->whereNull('outcome'))
            ->each(function (AiTestTask $task) use (&$closed) {
                if ($task->closeIfExpired()) {
                    $closed++;
                }
            });

        $this->info('Закрыто просроченных заданий: '.$closed);

        // кандидаты ждут ответа больше суток — напоминаем работодателю;
        // сам ИИ ответ кандидату не отправляет
        $reminded = app(\App\Services\Scoring\DecisionRelease::class)->remindOverdue();
        $this->info('Напоминаний работодателям: '.$reminded);

        return self::SUCCESS;
    }
}
