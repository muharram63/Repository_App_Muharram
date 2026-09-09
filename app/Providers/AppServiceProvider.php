<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Помощник по резюме. Контроллер зависит от интерфейса, поэтому в тестах
        // реализация подменяется заглушкой и сеть не требуется.
        $this->app->singleton(
            \App\Services\Ai\GeminiClient::class,
            fn () => new \App\Services\Ai\GeminiClient(config('services.gemini', [])),
        );

        $this->app->bind(
            \App\Services\Ai\ResumeAssistant::class,
            \App\Services\Ai\GeminiResumeAssistant::class,
        );

        $this->app->bind(
            \App\Services\Ai\MatchAnalyst::class,
            \App\Services\Ai\GeminiMatchAnalyst::class,
        );

        $this->app->bind(
            \App\Services\Ai\SkillExaminer::class,
            \App\Services\Ai\GeminiSkillExaminer::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // дефолтная разметка у Laravel рассчитана на Tailwind, у проекта своя вёрстка
        Paginator::defaultView('vendor.pagination.site');
        Paginator::defaultSimpleView('vendor.pagination.site');

        // auth перед ролевой проверкой: иначе гость получает 403 вместо страницы входа
        Route::middleware(['web','auth','is_admin'])
            ->prefix('superadmin')
            ->name('superadmin.')
            ->group(base_path('routes/admin.php'));

        Route::middleware(['web','auth','is_employer'])
            ->prefix('employer')
            ->name('employer.')
            ->group(base_path('routes/employer.php'));

        Route::middleware(['web','auth','is_applicant'])
            ->prefix('applicant')
            ->name('applicant.')
            ->group(base_path('routes/applicant.php'));

    }
}
