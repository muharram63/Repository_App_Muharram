<?php

namespace App\Notifications;

use App\Models\UserNotification;

/**
 * Напоминание: кандидаты ждут решения больше суток.
 *
 * Одно уведомление на всех ждущих, а не по одному на каждого: у занятого
 * работодателя десять отдельных напоминаний превращаются в шум. Ведёт в
 * раздел ИИ-собеседований, где ждущие помечены «нужно ваше решение».
 */
class AwaitingDecisionAlert extends EmployerAlert
{
    public function __construct(public readonly int $count)
    {
    }

    public function setting(): string
    {
        return 'notify_reminders';
    }

    protected function title(): array
    {
        return UserNotification::text('Кандидаты ждут решения');
    }

    protected function body(): array
    {
        // «1 кандидат ждёт», «3 кандидата ждут», «5 кандидатов ждут»
        $n = $this->count % 100;
        $key = match (true) {
            $n % 10 === 1 && $n !== 11 => 'У вас :count кандидат ждёт решения',
            in_array($n % 10, [2, 3, 4], true) && ! in_array($n, [12, 13, 14], true) => 'У вас :count кандидата ждут решения',
            default => 'У вас :count кандидатов ждут решения',
        };

        return UserNotification::text($key, ['count' => $this->count]);
    }

    protected function url(): string
    {
        return route('employer.ai.index');
    }
}
