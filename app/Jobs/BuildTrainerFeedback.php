<?php

namespace App\Jobs;

use App\Models\Trainer\TrainerSession;
use App\Models\UserNotification;
use App\Services\Trainer\TrainerCoach;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Разбор тренировки: собирается после решения движка и приходит кандидату
 * уведомлением. Модель может не ответить — тогда разбор собирается кодом из
 * оценок, и кандидат всё равно его получает.
 */
class BuildTrainerFeedback implements ShouldQueue
{
    use Queueable;

    public $tries = 2;

    public array $backoff = [30];

    public function __construct(public readonly int $sessionId)
    {
    }

    public function handle(TrainerCoach $coach): void
    {
        $session = TrainerSession::find($this->sessionId);

        // разбор уже готов или тренировка возвращена — делать нечего
        if (! $session || $session->feedback_status === 'ready' || $session->isRefunded()) {
            return;
        }

        $session->update(['feedback' => $coach->build($session), 'feedback_status' => 'ready']);

        UserNotification::deliver(
            $session->user_id,
            'trainer',
            UserNotification::text('Разбор тренировки готов'),
            UserNotification::text('«:track» — смотрите, что получилось и как сделать лучше.', ['track' => $session->track_title]),
            route('applicant.trainer.show', $session),
        );
    }

    public function failed(?\Throwable $e): void
    {
        // последняя попытка не удалась: хотя бы разбор от кода, без модели
        $session = TrainerSession::find($this->sessionId);

        if ($session && $session->feedback_status !== 'ready') {
            $session->update(['feedback' => app(TrainerCoach::class)->basic($session), 'feedback_status' => 'ready']);
        }
    }
}
