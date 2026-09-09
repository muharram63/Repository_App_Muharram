<?php

use App\Models\Interview;
use App\Models\User;

function interviewFor(array $attributes): Interview
{
    return Interview::create($attributes + [
        'duration_minutes' => 30,
        'room' => 'workio-'.uniqid(),
    ]);
}

test('past confirmed and held interviews are counted, not only future ones', function () {
    $employerUser = User::factory()->employer()->create();
    $employer = makeEmployer($employerUser);
    $applicant = makeApplicant(User::factory()->applicant()->create());

    $base = ['employer_id' => $employer->id, 'applicant_id' => $applicant->id];

    // прошедшая, подтверждённая, в комнату заходили — состоялась
    interviewFor($base + [
        'scheduled_at' => now()->subDays(3),
        'status' => 'confirmed',
        'answered_at' => now()->subDays(3),
    ]);

    // прошедшая, никто не заходил — не состоялась
    interviewFor($base + ['scheduled_at' => now()->subDays(2), 'status' => 'scheduled']);

    // отменённая в прошлом — не считается проведённой, даже если заходили
    interviewFor($base + [
        'scheduled_at' => now()->subDay(),
        'status' => 'canceled',
        'answered_at' => now()->subDay(),
    ]);

    // будущая, ждёт подтверждения
    interviewFor($base + ['scheduled_at' => now()->addDays(2), 'status' => 'scheduled']);

    // будущая, подтверждена
    interviewFor($base + ['scheduled_at' => now()->addDays(3), 'status' => 'confirmed']);

    // будущая, но отменённая — в «Ближайшие» не попадёт, а в отменённые должна
    interviewFor($base + ['scheduled_at' => now()->addDays(4), 'status' => 'canceled']);

    // сегодняшняя, уже прошла и состоялась
    interviewFor($base + [
        'scheduled_at' => now()->subHours(2),
        'status' => 'confirmed',
        'answered_at' => now()->subHours(2),
    ]);

    // сегодняшняя, но ещё впереди — сегодня она пока не проведена
    interviewFor($base + ['scheduled_at' => now()->addHours(3), 'status' => 'confirmed']);

    $stats = $this->actingAs($employerUser)->get('/interviews')->assertOk()->viewData('stats');

    // за всё время
    expect($stats['total'])->toBe(8);
    expect($stats['canceled'])->toBe(2);   // прошедшая и будущая отменённые
    expect($stats['held'])->toBe(2);       // трёхдневной давности и сегодняшняя

    // сейчас
    expect($stats['upcoming'])->toBe(3);
    expect($stats['upcomingCanceled'])->toBe(1);
    // из сегодняшних состоялась только та, что уже прошла
    expect($stats['heldToday'])->toBe(1);
});

test('a finished interview counts as held even without a room visit', function () {
    $employerUser = User::factory()->employer()->create();
    $employer = makeEmployer($employerUser);
    $applicant = makeApplicant(User::factory()->applicant()->create());

    interviewFor([
        'employer_id' => $employer->id,
        'applicant_id' => $applicant->id,
        'scheduled_at' => now()->subDays(4),
        'status' => 'finished',
    ]);

    $stats = $this->actingAs($employerUser)->get('/interviews')->viewData('stats');

    expect($stats['held'])->toBe(1);
});

test('the page shows both rows of numbers', function () {
    $employerUser = User::factory()->employer()->create();
    makeEmployer($employerUser);

    $this->actingAs($employerUser)->get('/interviews')
        ->assertOk()
        ->assertSee('За всё время')
        ->assertSee('Сейчас')
        ->assertSee('проведено сегодня')
        ->assertSee('отменено');
});
