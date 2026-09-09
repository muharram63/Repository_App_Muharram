<?php

use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminCityController;
use App\Http\Controllers\Admin\AdminIndustryController;
use Illuminate\Support\Facades\Route;

Route::get('/', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('dashboard');

Route::resource('categories' , AdminCategoryController::class);
Route::resource('cities' , AdminCityController::class);
Route::resource('industries' , AdminIndustryController::class);

Route::get('users', [\App\Http\Controllers\Admin\AdminUserController::class, 'index'])->name('users');
Route::get('users/{user}/edit', [\App\Http\Controllers\Admin\AdminUserController::class, 'edit'])->name('users.edit');
Route::post('users/resign', [\App\Http\Controllers\Admin\AdminUserController::class, 'resign'])->name('users.resign');
Route::patch('users/{user}', [\App\Http\Controllers\Admin\AdminUserController::class, 'update'])->name('users.update');
Route::delete('users/{user}', [\App\Http\Controllers\Admin\AdminUserController::class, 'destroy'])->name('users.destroy');
Route::get('vacancy', [\App\Http\Controllers\Admin\AdminVacancyController::class, 'index'])->name('vacancy');
Route::get('vacancy/{vacancy}/show', [\App\Http\Controllers\Admin\AdminVacancyController::class, 'show'])->name('vacancy.show');
Route::get('companies', [\App\Http\Controllers\Admin\AdminCompanyController::class, 'index'])->name('companies');
Route::get('companies/{company}/show', [\App\Http\Controllers\Admin\AdminCompanyController::class, 'show'])->name('companies.show');
Route::get('resumes', [\App\Http\Controllers\Admin\AdminResumeController::class, 'index'])->name('resumes');
Route::get('resumes/{resume}/show', [\App\Http\Controllers\Admin\AdminResumeController::class, 'show'])->name('resumes.show');
Route::get('responses', [\App\Http\Controllers\Admin\AdminResponseController::class, 'index'])->name('responses');
Route::get('analytics', [\App\Http\Controllers\Admin\AdminAnalyticsController::class, 'index'])->name('analytics');
Route::get('complaints', [\App\Http\Controllers\Admin\AdminComplaintController::class, 'index'])->name('complaints');
Route::post('complaints/{complaint}/act', [\App\Http\Controllers\Admin\AdminComplaintController::class, 'act'])->name('complaints.act');
Route::delete('complaints/{complaint}', [\App\Http\Controllers\Admin\AdminComplaintController::class, 'destroy'])->name('complaints.destroy');
Route::get('applicants', [\App\Http\Controllers\Admin\AdminApplicantController::class, 'index'])->name('applicants');
Route::get('applicants/{applicant}/show', [\App\Http\Controllers\Admin\AdminApplicantController::class, 'show'])->name('applicants.show');
Route::get('comments', [\App\Http\Controllers\Admin\AdminCommentController::class, 'index'])->name('comments');
Route::post('comments/{comment}/reply', [\App\Http\Controllers\Admin\AdminCommentController::class, 'reply'])->name('comments.reply');
Route::patch('comments/{comment}/status', [\App\Http\Controllers\Admin\AdminCommentController::class, 'updateStatus'])->name('comments.status');
Route::delete('comments/{comment}', [\App\Http\Controllers\Admin\AdminCommentController::class, 'destroy'])->name('comments.destroy');
Route::post('complaints/{complaint}/restore', [\App\Http\Controllers\Admin\AdminComplaintController::class, 'restore'])->name('complaints.restore');
Route::get('live-counters', [\App\Http\Controllers\LiveCounterController::class, 'admin'])->name('live.counters');
