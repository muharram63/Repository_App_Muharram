<?php

namespace App\Http\Controllers;

use App\Models\AiInterview;
use App\Models\AiInterviewConfig;
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
            'aiInterviews' => $this->aiInterviews($user, $myResponses),
        ]);
    }

    /**
     * Где у отклика ждёт ИИ-собеседование.
     *
     * Без этого соискателю некуда идти. Собеседование предлагалось только в
     * момент отклика — редиректом и уведомлением, — а дальше все ссылки на него
     * жили внутри самих страниц собеседования. Тот, кто откликнулся до того, как
     * работодатель включил ИИ (а это обычный случай: сперва вакансия, потом
     * настройка), не мог попасть внутрь никак: отклик лежит, и ничего не
     * происходит. С той же проблемой сталкивался всякий, кто закрыл вкладку и
     * потерял уведомление.
     *
     * @param  \Illuminate\Support\Collection<int,VacancyResponse>  $responses
     * @return array<int,array{state:string,interview:?AiInterview}>  ключ — id вакансии
     */
    private function aiInterviews($user, $responses): array
    {
        if ($user->role !== 'applicant' || ! $user->applicant || $responses->isEmpty()) {
            return [];
        }

        $vacancyIds = $responses->pluck('vacancy_id')->filter()->unique();

        // только вакансии, где собеседование действительно можно пройти:
        // включённое с противоречивыми настройками никого не собеседует
        $usable = AiInterviewConfig::whereIn('vacancy_id', $vacancyIds)
            ->where('enabled', true)
            ->get()
            ->filter(fn (AiInterviewConfig $config) => $config->isUsable())
            ->keyBy('vacancy_id');

        if ($usable->isEmpty()) {
            return [];
        }

        $started = AiInterview::where('applicant_id', $user->applicant->id)
            ->whereIn('vacancy_id', $usable->keys())
            ->get()
            ->keyBy('vacancy_id');

        $map = [];

        foreach ($usable->keys() as $vacancyId) {
            $interview = $started->get($vacancyId);

            $map[$vacancyId] = [
                'state' => match (true) {
                    $interview === null => 'invited',
                    $interview->isDecided() => 'done',
                    default => 'running',
                },
                'interview' => $interview,
            ];
        }

        return $map;
    }
}
