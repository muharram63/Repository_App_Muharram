<?php

namespace App\Notifications;

use App\Models\VacancyResponse;
use App\Models\UserNotification;

/**
 * Новый кандидат откликнулся на вакансию.
 *
 * Ведёт на страницу откликов работодателя: там новый отклик наверху, с
 * резюме и кнопками ответа.
 */
class NewCandidateAlert extends EmployerAlert
{
    public function __construct(public readonly VacancyResponse $response)
    {
    }

    public function setting(): string
    {
        return 'notify_new_candidates';
    }

    protected function title(): array
    {
        return UserNotification::text('Новый кандидат');
    }

    protected function body(): array
    {
        return UserNotification::text('Новый кандидат :name откликнулся на вакансию «:title»', [
            'name' => $this->response->applicant?->user?->name ?? UserNotification::text('Кандидат'),
            'title' => $this->response->vacancy?->title ?? '—',
        ]);
    }

    protected function url(): string
    {
        return route('employer.responses.index');
    }
}
