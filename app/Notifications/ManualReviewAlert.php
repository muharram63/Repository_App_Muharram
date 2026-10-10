<?php

namespace App\Notifications;

use App\Models\AiInterview;
use App\Models\UserNotification;

/**
 * Документы кандидата разобрать не удалось — решать работодателю.
 *
 * Раньше это уведомление шло мимо настроек: только на сайт, без почты и без
 * выключателя. Теперь оно относится к виду «Отчёт ИИ-собеседования готов»
 * (то же место в работе работодателя — карточка кандидата) и уходит по тем же
 * каналам, что и остальные уведомления о кандидатах.
 */
class ManualReviewAlert extends EmployerAlert
{
    public function __construct(
        public readonly AiInterview $interview,
        public readonly string $reason,
    ) {
    }

    public function setting(): string
    {
        return 'notify_reports';
    }

    protected function title(): array
    {
        return UserNotification::text('Кандидат ждёт ручной проверки');
    }

    protected function body(): array
    {
        return UserNotification::text('По вакансии «:title» не удалось разобрать документы: :reason', [
            'title' => $this->interview->vacancy?->title ?? '—',
            'reason' => $this->reason,
        ]);
    }

    protected function url(): string
    {
        // в отчёт по кандидату: решать надо там, а не в настройках вакансии
        return route('employer.ai.candidate', [$this->interview->vacancy_id, $this->interview->id]);
    }
}
