<?php

namespace App\Observers;

use App\Models\AiInterview;
use App\Services\Trainer\TrainerSessions;

/**
 * Тренажёр узнаёт о конце тренировки, глядя со стороны: движок
 * ИИ-собеседования объявляет решение как обычно, а этот наблюдатель замечает,
 * что решение пришло по учебному собеседованию, и закрывает тренировку.
 * Сам движок о тренажёре ничего не знает.
 */
class TrainerInterviewObserver
{
    public function updated(AiInterview $interview): void
    {
        if (! $interview->wasChanged('decided_at') || $interview->decided_at === null) {
            return;
        }

        $session = $interview->trainerSession()->first();

        if ($session) {
            app(TrainerSessions::class)->finish($session, $interview);
        }
    }
}
