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

        /*
         * Журнал обращений к модели — единственный вход к ней для модуля
         * собеседования: проверка ответа, повтор при негодном, запись в аудит.
         * Singleton, потому что состояния не держит, а клиент внутри и так один.
         */
        $this->app->singleton(\App\Services\Ai\AiJournal::class);

        // Критерии оценки: модель предлагает, работодатель подтверждает.
        $this->app->bind(
            \App\Services\Ai\CriteriaAdvisor::class,
            \App\Services\Ai\GeminiCriteriaAdvisor::class,
        );

        // Разбор документов кандидата: резюме в структурированный вид,
        // проверка дипломов и сверка требований вакансии.
        $this->app->bind(
            \App\Services\Ai\DocumentAuditor::class,
            \App\Services\Ai\GeminiDocumentAuditor::class,
        );

        // Собеседующий: вопросы ведёт быстрая модель, оценку ставит сильная.
        $this->app->bind(
            \App\Services\Ai\Interviewer::class,
            \App\Services\Ai\GeminiInterviewer::class,
        );

        // Тестовое задание: составление и проверка. Для кода модель
        // запускает решение в песочнице провайдера.
        $this->app->bind(
            \App\Services\Ai\TaskExaminer::class,
            \App\Services\Ai\GeminiTaskExaminer::class,
        );

        // Формулировка решения. Исход считает DecisionEngine без всякой
        // модели, здесь модель лишь подбирает слова.
        $this->app->bind(
            \App\Services\Ai\DecisionWriter::class,
            \App\Services\Ai\GeminiDecisionWriter::class,
        );

        // Распознавание устных ответов — для голосового режима там, где
        // браузер не распознаёт речь сам.
        $this->app->bind(
            \App\Services\Ai\SpeechRecognizer::class,
            \App\Services\Ai\GeminiRecognizer::class,
        );

        // Оплата пакетов тренажёра. Сейчас — ручной перевод по реквизитам;
        // банк подключается заменой этой строки на свою реализацию интерфейса.
        $this->app->bind(
            \App\Services\Payments\PaymentGateway::class,
            \App\Services\Payments\ManualTransferGateway::class,
        );

        // Анна обсуждает кандидата с работодателем — голосом или текстом.
        $this->app->bind(
            \App\Services\Ai\HiringAdvisor::class,
            \App\Services\Ai\GeminiHiringAdvisor::class,
        );

        // Озвучка вопросов на ИИ-собеседовании. Настройки передаём явно:
        // синтез умеет выключаться отдельно от остального Gemini, и знать
        // об этом должна реализация, а не вызывающий код.
        $this->app->bind(
            \App\Services\Ai\SpeechSynthesizer::class,
            fn ($app) => new \App\Services\Ai\GeminiSpeech(
                $app->make(\App\Services\Ai\GeminiClient::class),
                config('services.gemini', []),
            ),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Напоминания работодателям о кандидатах, ждущих ответа больше суток.
         *
         * Обычно их отправляет планировщик (ai:sweep), но он запущен не всегда,
         * а кандидат после собеседования на сайт может и не заходить. Поэтому
         * проверяем и после обычных запросов к сайту — уже отданных клиенту,
         * не чаще раза в пять минут. Это один лёгкий запрос к базе.
         */
        $this->app->terminating(function () {
            if ($this->app->runningUnitTests() || $this->app->runningInConsole()) {
                return;
            }

            try {
                if (\Illuminate\Support\Facades\Cache::add('ai-release-overdue', true, now()->addMinutes(5))) {
                    app(\App\Services\Scoring\DecisionRelease::class)->remindOverdue();
                }
            } catch (\Throwable $e) {
                // проверка — подстраховка: её сбой не должен ломать ответ сайта
                report($e);
            }
        });

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
