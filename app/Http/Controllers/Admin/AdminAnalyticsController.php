<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\Employer;
use App\Models\Resume;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyResponse;
use App\Support\AdminNumbers;

class AdminAnalyticsController extends Controller
{
    private array $monthNames = [1 => 'Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'];

    public function index()
    {
        // все скалярные числа считает общий источник — те же формулы
        // использует живое обновление, поэтому значения не расходятся
        $numbers = new AdminNumbers;
        $totals = $numbers->totals();
        $conversion = $numbers->conversion();
        $complaints = $numbers->complaints();

        // ===== динамика по месяцам =====
        $months = collect(range(11, 0))->map(function (int $back) {
            $date = now()->copy()->startOfMonth()->subMonths($back);

            return [
                'label' => $this->monthNames[(int) $date->format('n')],
                'year' => $date->format('Y'),
                'start' => $date->copy(),
                'end' => $date->copy()->endOfMonth(),
            ];
        });

        $timeline = $months->map(fn (array $m) => [
            'label' => $m['label'],
            'vacancies' => Vacancy::whereBetween('created_at', [$m['start'], $m['end']])->count(),
            'resumes' => Resume::whereBetween('created_at', [$m['start'], $m['end']])->count(),
            'responses' => VacancyResponse::whereBetween('created_at', [$m['start'], $m['end']])->count(),
            'users' => User::whereBetween('created_at', [$m['start'], $m['end']])->count(),
        ]);

        // ===== распределения по вакансиям =====
        // подписи и ключи живых счётчиков задаёт общий источник
        $distributions = $numbers->distributions();

        // ===== топы =====
        $topCategories = Category::withCount('vacancies')
            ->orderByDesc('vacancies_count')->take(8)->get()
            ->filter(fn ($c) => $c->vacancies_count > 0)->values();

        $topCities = City::withCount('vacancies')
            ->orderByDesc('vacancies_count')->take(8)->get()
            ->filter(fn ($c) => $c->vacancies_count > 0)->values();

        $topCompanies = Employer::withCount(['vacancies'])
            ->with('user')
            ->orderByDesc('vacancies_count')->take(8)->get();

        $topVacancies = Vacancy::with('employer')
            ->withCount('responses')
            ->orderByDesc('responses_count')->take(8)->get();

        $topResumes = Resume::with('applicant.user')
            ->withCount('responses')
            ->orderByDesc('responses_count')->take(8)->get();

        return view('admin.pages.analytics.index', [
            'complaintsTotal' => $complaints['total'],
            'complaintsNew' => $complaints['new'],
            'complaintsResolved' => $complaints['resolved'],
            'complaintsRejected' => $complaints['rejected'],
            'complaintsCur' => $complaints['cur'],
            'complaintsPrev' => $complaints['prev'],
            'complaintsDelta' => $complaints['delta'],
            'complaintsShare' => $complaints['share'],
            'complaintsByReason' => $complaints['byReason'],
            'complaintsByTarget' => $complaints['byTarget'],
            'dynamics' => array_values($numbers->dynamics()),
            'dynamicKeys' => array_keys($numbers->dynamics()),
            'conversion30' => $conversion['conversion30'],
            'conversionPrev' => $conversion['conversionPrev'],
            'responses30' => $conversion['responses30'],
            'accepted30' => $conversion['accepted30'],
            'usersTotal' => $totals['usersTotal'],
            'applicantsTotal' => $totals['applicantsTotal'],
            'employersTotal' => $totals['employersTotal'],
            'vacanciesTotal' => $totals['vacanciesTotal'],
            'vacanciesMonth' => $totals['vacanciesMonth'],
            'activeVacancies' => $totals['activeVacancies'],
            'resumesTotal' => $totals['resumesTotal'],
            'responsesTotal' => $totals['responsesTotal'],
            'invitesTotal' => $totals['invitesTotal'],
            'acceptedTotal' => $totals['acceptedTotal'],
            'conversion' => $totals['conversion'],
            'responsesOnActive' => $totals['responsesOnActive'],
            'responsesActiveShare' => $totals['responsesActiveShare'],
            'avgExperience' => $totals['avgExperience'],
            'interviewsTotal' => $totals['interviewsTotal'],
            'timeline' => $timeline,
            'byEmployment' => $distributions['employment'],
            'bySchedule' => $distributions['schedule'],
            'byExperience' => $distributions['experience'],
            'byStatus' => $distributions['status'],
            'byResponseStatus' => $distributions['response'],
            'topCategories' => $topCategories,
            'topCities' => $topCities,
            'topCompanies' => $topCompanies,
            'topVacancies' => $topVacancies,
            'topResumes' => $topResumes,
        ]);
    }
}
