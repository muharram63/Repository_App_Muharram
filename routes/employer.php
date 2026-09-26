<?php

use Illuminate\Support\Facades\Route;

Route::get('/profile',[\App\Http\Controllers\Employer\ProfileController::class,'profile'])->name('dashboard');
Route::post('/profile',[\App\Http\Controllers\Employer\ProfileController::class,'profileUpdate'])->name('profile.update');


Route::resource('vacancies' , \App\Http\Controllers\Employer\VacancyController::class);

// ИИ-собеседование: включение у вакансии и критерии оценки
Route::prefix('ai-screening')->name('ai.')->group(function () {
    $screening = \App\Http\Controllers\Employer\AiScreeningController::class;

    Route::get('/', [$screening, 'index'])->name('index');
    Route::get('{vacancy}', [$screening, 'show'])->whereNumber('vacancy')->name('show');
    Route::patch('{vacancy}/config', [$screening, 'updateConfig'])->whereNumber('vacancy')->name('config');

    // предложение критериев идёт через модель — ограничиваем частоту, чтобы
    // десять раз нажатая кнопка не сожгла дневную квоту бесплатного ключа
    Route::post('{vacancy}/criteria/suggest', [$screening, 'suggestCriteria'])
        ->whereNumber('vacancy')->middleware('throttle:10,1')->name('criteria.suggest');

    Route::patch('{vacancy}/criteria', [$screening, 'saveCriteria'])
        ->whereNumber('vacancy')->name('criteria.save');
    Route::post('{vacancy}/criteria', [$screening, 'addCriterion'])
        ->whereNumber('vacancy')->name('criteria.add');
    Route::delete('{vacancy}/criteria/{criterion}', [$screening, 'destroyCriterion'])
        ->whereNumber('vacancy')->whereNumber('criterion')->name('criteria.destroy');
});

// сюда же позже добавите отклики:
Route::get('responses', [\App\Http\Controllers\Employer\ResponseController::class, 'index'])->name('responses.index');


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
