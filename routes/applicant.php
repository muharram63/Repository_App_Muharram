<?php


use Illuminate\Support\Facades\Route;


Route::get('/profile',[\App\Http\Controllers\Applicant\ProfileController::class,'profile'])->name('dashboard');
Route::post('/profile',[\App\Http\Controllers\Applicant\ProfileController::class,'profileUpdate'])->name('profile.update');


// Помощник объявлен ДО ресурса: иначе resume.show поймает /resume/assistant
// первым и «assistant» уедет в параметр вместо своего маршрута.
Route::prefix('resume/assistant')->name('resume.assistant')->group(function () {
    $controller = \App\Http\Controllers\Applicant\ResumeAssistantController::class;

    Route::get('/', [$controller, 'index']);
    // новый разговор объявлен до {draft}, иначе «new» ушло бы в параметр
    Route::post('new', [$controller, 'start'])->name('.start');
    Route::get('{draft}', [$controller, 'index'])->whereNumber('draft')->name('.open');

    // ходы диалога дороже остальных запросов — ограничиваем частоту,
    // чтобы одна вкладка не сожгла дневную квоту бесплатного ключа
    Route::middleware('throttle:30,1')->group(function () use ($controller) {
        Route::post('{draft}/message', [$controller, 'message'])->whereNumber('draft')->name('.message');
        Route::post('{draft}/finalize', [$controller, 'finalize'])->whereNumber('draft')->name('.finalize');
    });
    Route::post('{draft}/save', [$controller, 'save'])->whereNumber('draft')->name('.save');
    Route::delete('{draft}', [$controller, 'destroy'])->whereNumber('draft')->name('.destroy');
});

// Проверка навыков делом: справочник, прохождение задания и разбор
Route::prefix('skills')->name('skills')->group(function () {
    $skills = \App\Http\Controllers\Applicant\SkillCheckController::class;

    Route::get('/', [$skills, 'index']);
    // составление задания и проверка идут через модель — ограничиваем частоту
    Route::post('start', [$skills, 'start'])->middleware('throttle:20,1')->name('.start');
    Route::get('attempt/{attempt}', [$skills, 'attempt'])->whereNumber('attempt')->name('.attempt');
    Route::post('attempt/{attempt}', [$skills, 'submit'])
        ->whereNumber('attempt')->middleware('throttle:20,1')->name('.submit');
});

Route::resource('resume', \App\Http\Controllers\Applicant\ResumeController::class);
Route::get('responses', [\App\Http\Controllers\Applicant\ResponseController::class, 'index'])->name('responses.index');


Route::get('calls', [\App\Http\Controllers\CallLogController::class, 'index'])->name('calls.index');

Route::get('settings', [\App\Http\Controllers\SettingsController::class, 'index'])->name('settings.index');
Route::patch('settings/account', [\App\Http\Controllers\SettingsController::class, 'account'])->name('settings.account');
Route::patch('settings/password', [\App\Http\Controllers\SettingsController::class, 'password'])->name('settings.password');
Route::patch('settings/devices', [\App\Http\Controllers\SettingsController::class, 'logoutOthers'])->name('settings.devices');
Route::patch('settings/interface', [\App\Http\Controllers\SettingsController::class, 'interface'])->name('settings.interface');
Route::patch('settings/notifications', [\App\Http\Controllers\SettingsController::class, 'notifications'])->name('settings.notifications');
Route::patch('settings/privacy', [\App\Http\Controllers\SettingsController::class, 'privacy'])->name('settings.privacy');
Route::delete('settings', [\App\Http\Controllers\SettingsController::class, 'destroy'])->name('settings.destroy');

Route::get('complaints', [\App\Http\Controllers\ComplaintLogController::class, 'index'])->name('complaints.index');

Route::get('support', [\App\Http\Controllers\SupportController::class, 'index'])->name('support.index');
