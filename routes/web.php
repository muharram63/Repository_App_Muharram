<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Employer\EmployerController;
use App\Models\Company;
use App\Models\Employer;
use App\Models\Vacancy;
use Illuminate\Support\Facades\Route;

Route::get('/', [\App\Http\Controllers\PublicHomeController::class, 'index'])->name('public.home');

Route::get('/locale/{locale}', [\App\Http\Controllers\LocaleController::class, 'switch'])->name('locale.switch');

/*
 * Запасная отдача аватаров.
 *
 * Обычно public/storage — симлинк, и файл отдаёт веб-сервер, сюда запрос даже
 * не доходит. Но на хостингах, где симлинка нет, файл существует на диске и
 * недоступен по прямой ссылке — фото выглядит как незагрузившееся. Этот
 * маршрут подхватывает такие запросы.
 *
 * Открыты ТОЛЬКО аватары: на том же диске остались старые вложения чатов и
 * документы резюме, их отдаёт SecureFileController с проверкой прав.
 */
Route::get('/storage/avatars/{name}', function (string $name) {
    $path = 'avatars/'.$name;

    abort_unless(\Illuminate\Support\Facades\Storage::disk('public')->exists($path), 404);

    return \Illuminate\Support\Facades\Storage::disk('public')->response($path);
})->where('name', '[A-Za-z0-9._-]+')->name('public.files.avatar');



Route::middleware('auth')->group(function () {
        // ===== Отклики =====
        Route::get('/responses', [\App\Http\Controllers\PublicResponseController::class, 'index'])
            ->name('public.responses.index');

        Route::post('/vacancies/{vacancy}/respond', [\App\Http\Controllers\ApplicantResponseController::class, 'store'])
            ->name('public.vacancies.respond');
        Route::delete('/vacancies/{vacancy}/respond', [\App\Http\Controllers\ApplicantResponseController::class, 'destroy'])
            ->name('public.vacancies.respond.destroy');

        Route::post('/resume/{resume}/respond', [\App\Http\Controllers\EmployerResponseController::class, 'store'])
            ->name('public.resumes.respond');
        Route::delete('/resume/{resume}/respond', [\App\Http\Controllers\EmployerResponseController::class, 'destroy'])
            ->name('public.resumes.respond.destroy');

        // смена статуса отклика владельцем вакансии / резюме
        Route::patch('/vacancy-responses/{vacancyResponse}/status', [\App\Http\Controllers\ApplicantResponseController::class, 'updateStatus'])
            ->name('public.vacancy_responses.status');
        Route::patch('/resume-responses/{resumeResponse}/status', [\App\Http\Controllers\EmployerResponseController::class, 'updateStatus'])
            ->name('public.resume_responses.status');

        // ===== Онлайн-собеседования =====
        Route::get('/interviews', [\App\Http\Controllers\InterviewController::class, 'index'])
            ->name('public.interviews.index');
        Route::post('/interviews', [\App\Http\Controllers\InterviewController::class, 'store'])
            ->name('public.interviews.store');
        Route::get('/interviews/{interview}', [\App\Http\Controllers\InterviewController::class, 'show'])
            ->name('public.interviews.show');
        Route::patch('/interviews/{interview}/status', [\App\Http\Controllers\InterviewController::class, 'updateStatus'])
            ->name('public.interviews.status');

        // ===== Жалобы =====
        Route::post('/moderation-notices/seen', [\App\Http\Controllers\ModerationNoticeController::class, 'seen'])
        ->name('public.notices.seen');

    Route::delete('/complaints/{complaint}', [\App\Http\Controllers\ComplaintController::class, 'destroy'])
        ->name('public.complaints.destroy');

    Route::post('/complaints', [\App\Http\Controllers\ComplaintController::class, 'store'])
            ->name('public.complaints.store');

    // владелец объекта закрывает замечание сам
    Route::post('/complaints/{complaint}/status', [\App\Http\Controllers\ComplaintController::class, 'updateStatus'])
            ->name('public.complaints.status');

        // ===== Обращения к администрации =====
        Route::post('/support', [\App\Http\Controllers\SupportController::class, 'store'])
            ->name('public.support.store');
        Route::delete('/support/{comment}', [\App\Http\Controllers\SupportController::class, 'destroy'])
            ->name('public.support.destroy');

        // ===== Разбор совпадения кандидата и вакансии =====
        Route::get('/match/{vacancy}/{resume}', [\App\Http\Controllers\MatchController::class, 'show'])
            ->middleware('throttle:20,1')
            ->name('public.match');

        // ===== Живые счётчики кабинета =====
        Route::get('/live-counters', [\App\Http\Controllers\LiveCounterController::class, 'cabinet'])
            ->name('public.live.counters');

        // ===== Ящик уведомлений =====
        Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])
            ->name('public.notifications.index');
        Route::get('/notifications/{notification}', [\App\Http\Controllers\NotificationController::class, 'open'])
            ->name('public.notifications.open');
        Route::post('/notifications/read', [\App\Http\Controllers\NotificationController::class, 'readAll'])
            ->name('public.notifications.read');
        Route::get('/notifications-count', [\App\Http\Controllers\NotificationController::class, 'unreadCount'])
            ->name('public.notifications.count');

        // ===== Приватные файлы =====
        Route::get('/chats/{conversation}/files/{message}', [\App\Http\Controllers\SecureFileController::class, 'chatAttachment'])
            ->name('public.files.chat');
        Route::get('/resume/{resume}/document', [\App\Http\Controllers\SecureFileController::class, 'resumeDocument'])
            ->name('public.files.resume');

        // ===== Чат =====
        Route::get('/chats', [\App\Http\Controllers\ChatController::class, 'index'])
            ->name('public.chats.index');
        Route::post('/chats', [\App\Http\Controllers\ChatController::class, 'start'])
            ->name('public.chats.start');
        Route::get('/chats/{conversation}', [\App\Http\Controllers\ChatController::class, 'show'])
            ->name('public.chats.show');
        Route::post('/chats/{conversation}/messages', [\App\Http\Controllers\ChatController::class, 'send'])
            ->name('public.chats.send');
        Route::get('/chats/{conversation}/poll', [\App\Http\Controllers\ChatController::class, 'poll'])
            ->name('public.chats.poll');
        Route::post('/chats/{conversation}/call', [\App\Http\Controllers\ChatController::class, 'startCall'])
            ->name('public.chats.call');
        Route::get('/calls/incoming', [\App\Http\Controllers\CallController::class, 'incoming'])
            ->name('public.calls.incoming');
        Route::post('/calls/{interview}/decline', [\App\Http\Controllers\CallController::class, 'decline'])
            ->name('public.calls.decline');
        Route::get('/calls/{interview}/status', [\App\Http\Controllers\CallController::class, 'status'])
            ->name('public.calls.status');
        Route::patch('/chats/{conversation}/messages/{message}', [\App\Http\Controllers\ChatController::class, 'updateMessage'])
            ->name('public.chats.message.update');
        Route::delete('/chats/{conversation}/messages/{message}', [\App\Http\Controllers\ChatController::class, 'destroyMessage'])
            ->name('public.chats.message.destroy');
    });

    require __DIR__.'/auth.php';


// Анкеты компании и соискателя — только для авторизованных и только нужные действия:
// публичные каталоги живут отдельно (/companies и /resume).
Route::middleware('auth')->group(function () {
    // update и destroy убраны: анкету правят через *.profile.update,
    // а удаляет её админ из своей панели — на эти роуты не ссылался ни один шаблон
    Route::resource('/employers', EmployerController::class)
        ->names('public.employers')
        ->only(['create', 'store']);

    Route::resource('/applicants', \App\Http\Controllers\Applicant\ApplicantController::class)
        ->names('public.applicants')
        ->only(['create', 'store']);
});
// ===== Публичная статистика платформы =====
Route::get('/statistics', [\App\Http\Controllers\PublicStatsController::class, 'index'])
    ->name('public.stats');
Route::get('/statistics/hired', [\App\Http\Controllers\PublicStatsController::class, 'hired'])
    ->name('public.stats.hired');

Route::resource('/vacancies', \App\Http\Controllers\PublicVacancyController::class)->names('public.vacancies')->only(['index', 'show' ]);
Route::resource('/resume' , \App\Http\Controllers\PublicResumeController::class)->names('public.resumes')->only(['index', 'show' ]);
Route::resource('/companies' , \App\Http\Controllers\PublicCompanyController::class)->names('public.companies')->only(['index' , 'show']);

// общий вход в кабинет: у каждой роли он свой
Route::get('/dashboard', function () {
    $user = auth()->user();

    return match ($user?->role) {
        'admin' => redirect()->route('superadmin.dashboard'),
        'employer' => redirect()->route('employer.dashboard'),
        'applicant' => redirect()->route('applicant.dashboard'),
        default => redirect()->route('login'),
    };
})->middleware('auth')->name('dashboard');
