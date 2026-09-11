<?php

use App\Models\City;
use App\Models\User;
use App\Support\MatchCriteria;

/**
 * Точные критерии совпадения. Опыт, зарплата и город считаются без модели,
 * поэтому и проверяются без неё — обычной арифметикой.
 */
function criteriaFor(array $vacancyFields, array $resumeFields, ?string $city = null)
{
    $employer = makeEmployer(User::factory()->employer()->create());
    $vacancy = makeVacancy($employer);
    $vacancy->update($vacancyFields);

    $applicant = makeApplicant(User::factory()->applicant()->create());

    if ($city !== null) {
        $applicant->update(['city' => $city]);
    }

    $resume = makeResume($applicant);
    $resume->update($resumeFields);

    return collect(MatchCriteria::for($vacancy->fresh(), $resume->fresh()))->keyBy('key');
}

test('experience is compared with the requirement, not guessed', function () {
    $enough = criteriaFor(['experience_required' => '3_years'], ['experience_years' => 5]);
    expect($enough['experience']['state'])->toBe('ok');

    // недобор в один год — повод поговорить, а не отказать
    $almost = criteriaFor(['experience_required' => '3_years'], ['experience_years' => 2]);
    expect($almost['experience']['state'])->toBe('partial');

    $far = criteriaFor(['experience_required' => 'more_6years'], ['experience_years' => 1]);
    expect($far['experience']['state'])->toBe('no')
        ->and($far['experience']['note'])->toContain('при требуемых от 6');

    $none = criteriaFor(['experience_required' => 'not'], ['experience_years' => 0]);
    expect($none['experience']['state'])->toBe('ok');
});

test('salary is compared with the upper bound of the range', function () {
    $fits = criteriaFor(['salary_to' => 6000], ['desired_salary' => 5000]);
    expect($fits['salary']['state'])->toBe('ok');

    // небольшой разрыв закрывается на переговорах
    $close = criteriaFor(['salary_to' => 6000], ['desired_salary' => 6500]);
    expect($close['salary']['state'])->toBe('partial');

    $far = criteriaFor(['salary_to' => 6000], ['desired_salary' => 20000]);
    expect($far['salary']['state'])->toBe('no');

    // не указана — критерий не учитывается, а не занижает результат
    $silent = criteriaFor(['salary_to' => 6000], ['desired_salary' => 0]);
    expect($silent['salary']['state'])->toBe('unknown');
});

test('remote work makes the city irrelevant', function () {
    $remote = criteriaFor(['work_schedule' => 'remote_work'], [], 'Худжанд');

    expect($remote['city']['state'])->toBe('ok')
        ->and($remote['city']['note'])->toContain('Удалённая работа');
});

test('a different city is counted against the match', function () {
    $same = criteriaFor(['work_schedule' => 'full_day'], [], 'Душанбе');
    expect($same['city']['state'])->toBe('ok');

    $other = criteriaFor(['work_schedule' => 'full_day'], [], 'Худжанд');
    expect($other['city']['state'])->toBe('no')
        ->and($other['city']['note'])->toContain('вакансия в городе Душанбе');
});

test('the score is weighted across the criteria that have data', function () {
    // всё совпало — сто процентов
    expect(MatchCriteria::score([
        ['key' => 'role', 'state' => 'ok'],
        ['key' => 'skills', 'state' => 'ok'],
        ['key' => 'experience', 'state' => 'ok'],
    ]))->toBe(100);

    // навыки весомее города: провал по навыкам роняет результат сильнее
    $noSkills = MatchCriteria::score([
        ['key' => 'skills', 'state' => 'no'],
        ['key' => 'city', 'state' => 'ok'],
    ]);
    $noCity = MatchCriteria::score([
        ['key' => 'skills', 'state' => 'ok'],
        ['key' => 'city', 'state' => 'no'],
    ]);

    expect($noSkills)->toBeLessThan($noCity);

    // критерии без данных не занижают оценку
    expect(MatchCriteria::score([
        ['key' => 'skills', 'state' => 'ok'],
        ['key' => 'salary', 'state' => 'unknown'],
        ['key' => 'city', 'state' => 'unknown'],
    ]))->toBe(100);

    // считать не из чего — числа нет вовсе
    expect(MatchCriteria::score([['key' => 'salary', 'state' => 'unknown']]))->toBeNull();
});

test('a partial state counts as half', function () {
    expect(MatchCriteria::score([['key' => 'skills', 'state' => 'partial']]))->toBe(50);
});
