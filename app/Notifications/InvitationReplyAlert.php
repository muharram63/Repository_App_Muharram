<?php

namespace App\Notifications;

use App\Models\ResumeResponse;
use App\Models\UserNotification;

/**
 * Соискатель ответил на приглашение работодателя: просмотрел, принял, отклонил.
 *
 * Раньше это уведомление шло мимо настроек кандидатских уведомлений — только
 * на сайт, без почты. Теперь оно относится к виду «Новый кандидат» (человек
 * откликнулся — на этот раз на приглашение) и уходит по выбранным каналам.
 */
class InvitationReplyAlert extends EmployerAlert
{
    public const STATUSES = [
        'viewed' => 'Приглашение отмечено просмотренным.',
        'accepted' => 'Приглашение принято.',
        'rejected' => 'Приглашение отклонено.',
    ];

    public function __construct(
        public readonly ResumeResponse $invitation,
        public readonly string $status,
    ) {
    }

    public function setting(): string
    {
        return 'notify_new_candidates';
    }

    protected function title(): array
    {
        return UserNotification::text('Ответ на ваше приглашение');
    }

    protected function body(): array
    {
        return UserNotification::text(':title: :status', [
            'title' => $this->invitation->resume?->profession ?? UserNotification::text('Резюме'),
            'status' => UserNotification::text(self::STATUSES[$this->status] ?? $this->status),
        ]);
    }

    protected function url(): string
    {
        return route('public.responses.index');
    }
}
