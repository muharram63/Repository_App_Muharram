<?php

use App\Models\User;

/*
 * Описание компании и вакансии валидируется до 600 символов.
 *
 * Колонка employers.description была varchar(255): при строгом режиме MySQL
 * сохранение более длинного текста падало с 22001 «Data too long», и
 * работодатель вместо анкеты получал пятисотую. Миграция расширила её до text.
 *
 * У вакансии предел при создании (600) и при правке (255) разошёлся, поэтому
 * вакансию с длинным описанием нельзя было пересохранить после любой правки.
 */

test('анкета работодателя сохраняет описание длиннее 255 символов', function () {
    $user = User::factory()->employer()->create();
    $employer = makeEmployer($user);
    $long = str_repeat('я', 500);

    $this->actingAs($user)->post(route('employer.profile.update'), [
        'name' => $user->name,
        'company_name' => $employer->company_name,
        'job' => $employer->job,
        'email' => $user->email,
        'phone' => '900000000',
        'category_id' => $employer->category_id,
        'industry_id' => $employer->industry_id,
        'city_id' => $employer->city_id,
        'description' => $long,
    ])->assertSessionHasNoErrors();

    expect($employer->fresh()->description)->toBe($long);
});

test('вакансию с длинным описанием можно пересохранить', function () {
    $user = User::factory()->employer()->create();
    $employer = makeEmployer($user);
    $vacancy = makeVacancy($employer);
    $long = str_repeat('и', 500);

    $payload = $vacancy->only([
        'title', 'salary_from', 'salary_to', 'currency',
        'employment_type', 'work_schedule', 'experience_required', 'skill', 'city_id', 'status',
    ]) + ['description' => $long];

    $this->actingAs($user)
        ->patch(route('employer.vacancies.update', $vacancy), $payload)
        ->assertSessionHasNoErrors();

    expect($vacancy->fresh()->description)->toBe($long);
});

test('описание всё же ограничено — 700 символов не проходят', function () {
    $user = User::factory()->employer()->create();
    $employer = makeEmployer($user);
    $vacancy = makeVacancy($employer);

    $payload = $vacancy->only([
        'title', 'salary_from', 'salary_to', 'currency',
        'employment_type', 'work_schedule', 'experience_required', 'skill', 'city_id', 'status',
    ]) + ['description' => str_repeat('и', 700)];

    $this->actingAs($user)
        ->patch(route('employer.vacancies.update', $vacancy), $payload)
        ->assertSessionHasErrors('description');
});
