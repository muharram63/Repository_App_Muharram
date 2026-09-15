<?php

use App\Models\User;

/*
 * salary_from, salary_to и desired_salary лежат в колонках int (предел
 * ~2,1 млрд). Валидация была без верхней границы, поэтому слишком большое
 * число уходило в базу и падало с 22003 «Numeric value out of range» —
 * пользователь видел пятисотую вместо сообщения об ошибке.
 */

function vacancyPayload(App\Models\Vacancy $vacancy, array $overrides): array
{
    return $overrides + $vacancy->only([
        'title', 'description', 'salary_from', 'salary_to', 'currency',
        'employment_type', 'work_schedule', 'experience_required', 'skill', 'city_id', 'status',
    ]);
}

test('зарплата больше предела колонки отсекается валидацией, а не падает', function () {
    $user = User::factory()->employer()->create();
    $vacancy = makeVacancy(makeEmployer($user));

    $this->actingAs($user)
        ->patch(route('employer.vacancies.update', $vacancy),
            vacancyPayload($vacancy, ['salary_from' => 1, 'salary_to' => 99999999999]))
        ->assertSessionHasErrors('salary_to');
});

test('отрицательная зарплата не принимается', function () {
    $user = User::factory()->employer()->create();
    $vacancy = makeVacancy(makeEmployer($user));

    $this->actingAs($user)
        ->patch(route('employer.vacancies.update', $vacancy),
            vacancyPayload($vacancy, ['salary_from' => -5000, 'salary_to' => 1000]))
        ->assertSessionHasErrors('salary_from');
});

test('верхняя граница вилки не может быть ниже нижней', function () {
    $user = User::factory()->employer()->create();
    $vacancy = makeVacancy(makeEmployer($user));

    $this->actingAs($user)
        ->patch(route('employer.vacancies.update', $vacancy),
            vacancyPayload($vacancy, ['salary_from' => 5000, 'salary_to' => 1000]))
        ->assertSessionHasErrors('salary_to');
});

test('обычная вилка по-прежнему сохраняется', function () {
    $user = User::factory()->employer()->create();
    $vacancy = makeVacancy(makeEmployer($user));

    $this->actingAs($user)
        ->patch(route('employer.vacancies.update', $vacancy),
            vacancyPayload($vacancy, ['salary_from' => 1500, 'salary_to' => 3000]))
        ->assertSessionHasNoErrors();

    expect($vacancy->fresh()->salary_to)->toBe(3000);
});
