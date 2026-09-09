<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResumeResponse;
use App\Models\VacancyResponse;

class AdminResponseController extends Controller
{
    /**
     * Все отклики платформы: соискатели -> вакансии и работодатели -> резюме.
     */
    public function index()
    {
        $vacancyResponses = VacancyResponse::with('applicant.user', 'vacancy.employer')
            ->latest()
            ->get();

        $resumeResponses = ResumeResponse::with('employer.user', 'resume.applicant.user')
            ->latest()
            ->get();

        // приводим оба типа к одному виду, чтобы показать одной таблицей
        $rows = $vacancyResponses->map(function (VacancyResponse $response) {
            return [
                'type' => 'vacancy',
                'type_label' => 'Отклик на вакансию',
                'from_name' => $response->applicant?->user?->name ?? 'Соискатель удалён',
                'from_role' => 'Соискатель',
                'from_email' => $response->applicant?->user?->email,
                'from_avatar' => $response->applicant?->user?->avatar,
                'target_title' => $response->vacancy?->title ?? 'Вакансия удалена',
                'target_sub' => $response->vacancy?->employer?->company_name,
                'target_link' => $response->vacancy
                    ? route('superadmin.vacancy.show', $response->vacancy)
                    : null,
                'message' => $response->message,
                'status' => $response->status,
                'created_at' => $response->created_at,
            ];
        })->concat($resumeResponses->map(function (ResumeResponse $response) {
            return [
                'type' => 'resume',
                'type_label' => 'Приглашение на резюме',
                'from_name' => $response->employer?->company_name ?? 'Компания удалена',
                'from_role' => 'Работодатель',
                'from_email' => $response->employer?->email_company,
                'from_avatar' => $response->employer?->user?->avatar,
                'target_title' => $response->resume?->profession ?? 'Резюме удалено',
                'target_sub' => $response->resume?->applicant?->user?->name,
                'target_link' => $response->resume
                    ? route('superadmin.resumes.show', $response->resume)
                    : null,
                'message' => $response->message,
                'status' => $response->status,
                'created_at' => $response->created_at,
            ];
        }))->sortByDesc('created_at')->values();

        return view('admin.pages.responses.index', [
            'rows' => $rows,
            'vacancyCount' => $vacancyResponses->count(),
            'resumeCount' => $resumeResponses->count(),
        ]);
    }
}
