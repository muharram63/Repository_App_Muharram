<?php

namespace App\Notifications;

use App\Models\AiDecision;
use App\Models\AiInterview;
use App\Models\UserNotification;

/**
 * Кандидат прошёл ИИ-собеседование — отчёт готов.
 *
 * В тексте сразу то, ради чего работодатель откроет отчёт: процент
 * совпадения с вакансией и рекомендация ИИ. Ведёт прямо на карточку
 * кандидата с отчётом.
 */
class InterviewReportAlert extends EmployerAlert
{
    public const RECOMMENDATIONS = [
        'passed' => 'Принять',
        'rejected' => 'Отказать',
        'manual_review' => 'Доп. собеседование',
    ];

    public function __construct(
        public readonly AiInterview $interview,
        public readonly AiDecision $decision,
        public readonly ?int $match,
    ) {
    }

    public function setting(): string
    {
        return 'notify_reports';
    }

    protected function title(): array
    {
        return UserNotification::text('Отчёт ИИ-собеседования готов');
    }

    protected function body(): array
    {
        return UserNotification::text('ИИ-собеседование с :name завершено. Совпадение: :match%. Рекомендация: :recommendation', [
            'name' => $this->interview->applicant?->user?->name ?? UserNotification::text('Кандидат'),
            'match' => $this->match ?? '—',
            'recommendation' => UserNotification::text(
                self::RECOMMENDATIONS[$this->decision->outcome] ?? self::RECOMMENDATIONS['manual_review']
            ),
        ]);
    }

    protected function url(): string
    {
        return route('employer.ai.candidate', [$this->interview->vacancy_id, $this->interview->id]);
    }
}
