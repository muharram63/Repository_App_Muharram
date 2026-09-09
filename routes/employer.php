<?php

use Illuminate\Support\Facades\Route;

Route::get('/profile',[\App\Http\Controllers\Employer\ProfileController::class,'profile'])->name('dashboard');
Route::post('/profile',[\App\Http\Controllers\Employer\ProfileController::class,'profileUpdate'])->name('profile.update');


Route::resource('vacancies' , \App\Http\Controllers\Employer\VacancyController::class);

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
