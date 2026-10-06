<?php

use App\Models\ResumeResponse;
use App\Models\User;
use App\Models\VacancyResponse;

/*
 * Найдено аудитом: скрытые и закрытые аккаунты были недоступны в каталогах,
 * но по прямому запросу их всё ещё можно было пригласить или откликнуться.
 */

test('соискателя со скрытым профилем нельзя пригласить по номеру резюме', function () {
    $applicantUser = User::factory()->applicant()->create();
    $resume = makeResume(makeApplicant($applicantUser));
    $applicantUser->settings()->create(['hide_profile' => true]);

    $employerUser = User::factory()->employer()->create();
    makeEmployer($employerUser);

    $this->actingAs($employerUser)->post(route('public.resumes.respond', $resume))->assertNotFound();
    expect(ResumeResponse::count())->toBe(0);
});

test('заблокированного соискателя нельзя пригласить', function () {
    $applicantUser = User::factory()->applicant()->create();
    $resume = makeResume(makeApplicant($applicantUser));
    $applicantUser->forceFill(['status' => 'blocked'])->save();

    $employerUser = User::factory()->employer()->create();
    makeEmployer($employerUser);

    $this->actingAs($employerUser)->post(route('public.resumes.respond', $resume))->assertNotFound();
    expect(ResumeResponse::count())->toBe(0);
});

test('видимого соискателя пригласить можно', function () {
    $resume = makeResume(makeApplicant(User::factory()->applicant()->create()));
    $employerUser = User::factory()->employer()->create();
    makeEmployer($employerUser);

    $this->actingAs($employerUser)->post(route('public.resumes.respond', $resume))->assertRedirect();
    expect(ResumeResponse::count())->toBe(1);
});

test('на вакансию заблокированной компании откликнуться нельзя', function () {
    $employerUser = User::factory()->employer()->create();
    $vacancy = makeVacancy(makeEmployer($employerUser));
    $employerUser->forceFill(['status' => 'blocked'])->save();

    $applicantUser = User::factory()->applicant()->create();
    makeApplicant($applicantUser);

    $this->actingAs($applicantUser)->post(route('public.vacancies.respond', $vacancy))->assertSessionHas('error');
    expect(VacancyResponse::count())->toBe(0);
});

test('старые вложения из открытой папки переносятся в закрытую и остаются доступны участникам', function () {
    Illuminate\Support\Facades\Storage::fake('public');
    Illuminate\Support\Facades\Storage::fake('local');

    Illuminate\Support\Facades\Storage::disk('public')->put('chat/7/old.txt', 'секрет');
    Illuminate\Support\Facades\Storage::disk('public')->put('resumes/cv.pdf', 'резюме');
    // уже перенесённый ранее файл: открытая копия лишняя
    Illuminate\Support\Facades\Storage::disk('public')->put('chat/7/dup.txt', 'одно и то же');
    Illuminate\Support\Facades\Storage::disk('local')->put('chat/7/dup.txt', 'одно и то же');

    (require database_path('migrations/2026_10_11_000002_move_legacy_public_attachments_to_private.php'))->up();

    expect(Illuminate\Support\Facades\Storage::disk('public')->allFiles())->toBe([])
        ->and(Illuminate\Support\Facades\Storage::disk('local')->get('chat/7/old.txt'))->toBe('секрет')
        ->and(Illuminate\Support\Facades\Storage::disk('local')->get('resumes/cv.pdf'))->toBe('резюме');
});
