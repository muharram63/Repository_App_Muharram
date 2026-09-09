<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\Employer;
use App\Models\Resume;
use App\Models\ResumeResponse;
use App\Models\Vacancy;
use App\Models\VacancyResponse;

class PublicHomeController extends Controller
{
    public function index()
    {
        // ===== цифры для блока статистики =====
        $activeVacancies = Vacancy::where('status', 'active')->count();
        $resumesCount = Resume::count();
        $companiesCount = Employer::count();

        $responsesToday = VacancyResponse::whereDate('created_at', today())->count()
            + ResumeResponse::whereDate('created_at', today())->count();

        $newApplicantsMonth = Applicant::where('created_at', '>=', now()->subMonth())->count();

        // ===== подборки =====
        $latestVacancies = Vacancy::with('employer.user', 'city')
            ->withCount('responses')
            ->where('status', 'active')
            ->whereHas('employer.user', fn ($u) => $u->activeAccount())
            ->latest()
            ->take(6)
            ->get();

        $latestResumes = Resume::with('applicant.user')
            ->whereHas('applicant.user', fn ($q) => $q->activeAccount()->whereDoesntHave(
                'settings',
                fn ($s) => $s->where('hide_profile', true)
            ))
            ->whereHas('applicant.user')
            ->latest()
            ->take(6)
            ->get();

        $topCompanies = Employer::with('city', 'industry', 'user')
            ->whereHas('user', fn ($u) => $u->activeAccount())
            ->whereDoesntHave('user.settings', fn ($s) => $s->where('hide_company', true))
            ->withCount('vacancies')
            ->whereNotNull('company_name')
            ->where('company_name', '<>', '')
            ->orderByDesc('vacancies_count')
            ->take(10)
            ->get();

        return view('public.pages.dashboard', compact(
            'activeVacancies',
            'resumesCount',
            'companiesCount',
            'responsesToday',
            'newApplicantsMonth',
            'latestVacancies',
            'latestResumes',
            'topCompanies',
        ));
    }
}
