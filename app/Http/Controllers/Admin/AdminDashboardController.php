<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Employer;
use App\Models\Interview;
use App\Models\Message;
use App\Models\Resume;
use App\Models\ResumeResponse;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyResponse;
use App\Support\AdminNumbers;
use Illuminate\Support\Carbon;

class AdminDashboardController extends Controller
{
    public function index()
    {
        // итоги считает общий источник — те же формулы использует
        // живое обновление цифр, иначе значения разошлись бы
        $totals = (new AdminNumbers)->totals();

        // ===== регистрации по месяцам (6 месяцев) =====
        $months = collect(range(5, 0))->map(function (int $back) {
            $date = now()->copy()->startOfMonth()->subMonths($back);

            return [
                'label' => $this->monthLabel($date),
                'start' => $date->copy(),
                'end' => $date->copy()->endOfMonth(),
            ];
        });

        $registrations = $months->map(function (array $month) {
            return [
                'label' => $month['label'],
                'applicants' => User::where('role', 'applicant')
                    ->whereBetween('created_at', [$month['start'], $month['end']])->count(),
                'employers' => User::where('role', 'employer')
                    ->whereBetween('created_at', [$month['start'], $month['end']])->count(),
            ];
        });

        // ===== вакансии по категориям =====
        $categories = Category::withCount('vacancies')
            ->orderByDesc('vacancies_count')
            ->get()
            ->filter(fn ($category) => $category->vacancies_count > 0)
            ->values();

        $categoriesSum = (int) $categories->sum('vacancies_count');

        // ===== свежие данные для таблиц =====
        $latestVacancies = Vacancy::with('employer', 'city')
            ->withCount('responses')
            ->latest()
            ->take(5)
            ->get();

        $latestResponses = VacancyResponse::with('applicant.user', 'vacancy.employer')
            ->latest()
            ->take(5)
            ->get();

        return view('admin.pages.dashboard', $totals + [
            'responsesTotal' => $totals['obligationsTotal'],
            'registrations' => $registrations,
            'categories' => $categories,
            'categoriesSum' => $categoriesSum,
            'latestVacancies' => $latestVacancies,
            'latestResponses' => $latestResponses,
        ]);
    }

    private function monthLabel(Carbon $date): string
    {
        $months = [1 => 'Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'];

        return $months[(int) $date->format('n')];
    }
}
