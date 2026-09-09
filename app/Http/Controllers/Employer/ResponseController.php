<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\VacancyResponse;

class ResponseController extends Controller
{
    /**
     * Соискатели, откликнувшиеся на вакансии работодателя.
     */
    public function index()
    {
        $user = auth()->user();
        $employer = $user->employer;

        if (! $employer) {
            return redirect()->route('public.employers.create')
                ->with('error', 'Сначала заполните анкету работодателя.');
        }

        $responses = VacancyResponse::with('applicant.user', 'applicant.resume', 'vacancy.city')
            ->whereIn('vacancy_id', $employer->vacancies()->pluck('id'))
            ->latest()
            ->get();

        return view('employer.pages.responses.index', [
            'user' => $user,
            'employer' => $employer,
            'responses' => $responses,
        ]);
    }
}
