<?php

use App\Models\Interview;
use App\Models\User;
use App\Models\VacancyResponse;

// ===== B1 =====
test('a scheduled interview is not marked answered when the employer opens it', function () {
    $employerUser = User::factory()->employer()->create();
    $applicantUser = User::factory()->applicant()->create();

    $employer = makeEmployer($employerUser);
    $applicant = makeApplicant($applicantUser);

    $interview = Interview::create([
        'employer_id' => $employer->id,
        'applicant_id' => $applicant->id,
        'scheduled_at' => now(),
        'duration_minutes' => 30,
        'room' => 'workio-'.uniqid(),
    ]);

    // инициатор встречи — работодатель, его заход в комнату ничего не подтверждает
    $this->actingAs($employerUser)->get('/interviews/'.$interview->id)->assertOk();
    expect($interview->fresh()->answered_at)->toBeNull();

    // а вход соискателя означает, что встреча состоялась
    $this->actingAs($applicantUser)->get('/interviews/'.$interview->id)->assertOk();
    expect($interview->fresh()->answered_at)->not->toBeNull();
});

test('an interview is not marked answered before the room opens', function () {
    $employerUser = User::factory()->employer()->create();
    $applicantUser = User::factory()->applicant()->create();

    $interview = Interview::create([
        'employer_id' => makeEmployer($employerUser)->id,
        'applicant_id' => makeApplicant($applicantUser)->id,
        'scheduled_at' => now()->addDays(3),
        'duration_minutes' => 30,
        'room' => 'workio-'.uniqid(),
    ]);

    $this->actingAs($applicantUser)->get('/interviews/'.$interview->id)->assertOk();
    expect($interview->fresh()->answered_at)->toBeNull();
});

// ===== B2 =====
test('an employer cannot schedule an interview with an unrelated applicant', function () {
    $employerUser = User::factory()->employer()->create();
    $applicantUser = User::factory()->applicant()->create();

    makeEmployer($employerUser);
    $applicant = makeApplicant($applicantUser);

    $this->actingAs($employerUser)->post('/interviews', [
        'applicant_id' => $applicant->id,
        'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'duration_minutes' => 30,
    ])->assertForbidden();

    expect(Interview::count())->toBe(0);
});

test('an employer can schedule an interview with an applicant who responded', function () {
    $employerUser = User::factory()->employer()->create();
    $applicantUser = User::factory()->applicant()->create();

    $employer = makeEmployer($employerUser);
    $applicant = makeApplicant($applicantUser);
    $vacancy = makeVacancy($employer);

    VacancyResponse::create([
        'applicant_id' => $applicant->id,
        'vacancy_id' => $vacancy->id,
    ]);

    $this->actingAs($employerUser)->post('/interviews', [
        'applicant_id' => $applicant->id,
        'vacancy_id' => $vacancy->id,
        'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'duration_minutes' => 30,
    ])->assertRedirect();

    expect(Interview::count())->toBe(1);
});

test('an employer cannot attach someone elses response to an interview', function () {
    $employerUser = User::factory()->employer()->create();
    $applicantUser = User::factory()->applicant()->create();
    $otherApplicantUser = User::factory()->applicant()->create();

    $employer = makeEmployer($employerUser);
    $applicant = makeApplicant($applicantUser);
    $other = makeApplicant($otherApplicantUser);
    $vacancy = makeVacancy($employer);

    // связь с компанией у обоих есть
    VacancyResponse::create(['applicant_id' => $applicant->id, 'vacancy_id' => $vacancy->id]);
    $foreign = VacancyResponse::create(['applicant_id' => $other->id, 'vacancy_id' => $vacancy->id]);

    $this->actingAs($employerUser)->post('/interviews', [
        'applicant_id' => $applicant->id,
        'vacancy_response_id' => $foreign->id,
        'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'duration_minutes' => 30,
    ])->assertForbidden();

    expect(Interview::count())->toBe(0);
});
