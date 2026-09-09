<?php

namespace App\Http\Controllers;

use App\Models\ResumeResponse;
use App\Models\VacancyResponse;

class PublicResponseController extends Controller
{
    /**
     * Страница «Отклики». Содержимое зависит от роли пользователя.
     */
    public function index()
    {
        $user = auth()->user();

        $myResponses = collect();       // куда я откликнулся
        $incomingResponses = collect(); // кто откликнулся ко мне

        if ($user->role === 'applicant' && $user->applicant) {
            $applicant = $user->applicant;

            // мои отклики на вакансии
            $myResponses = VacancyResponse::with('vacancy.employer.user', 'vacancy.city')
                ->where('applicant_id', $applicant->id)
                ->latest()
                ->get();

            // приглашения работодателей на мои резюме
            $incomingResponses = ResumeResponse::with('employer.user', 'employer.city', 'resume')
                ->whereIn('resume_id', $applicant->resume()->pluck('id'))
                ->latest()
                ->get();
        }

        if ($user->role === 'employer' && $user->employer) {
            $employer = $user->employer;

            // мои приглашения соискателям
            $myResponses = ResumeResponse::with('resume.applicant.user')
                ->where('employer_id', $employer->id)
                ->latest()
                ->get();

            // отклики соискателей на мои вакансии
            $incomingResponses = VacancyResponse::with('applicant.user', 'vacancy')
                ->whereIn('vacancy_id', $employer->vacancies()->pluck('id'))
                ->latest()
                ->get();
        }

        return view('public.pages.responses.index', [
            'user' => $user,
            'myResponses' => $myResponses,
            'incomingResponses' => $incomingResponses,
        ]);
    }
}
