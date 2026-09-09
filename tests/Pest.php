<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Фабрики-помощники
|--------------------------------------------------------------------------
|
| Модельных фабрик в проекте нет, кроме UserFactory, а анкеты и вакансии
| требуют цепочку справочников. Собираем их здесь, чтобы не дублировать.
|
*/

/**
 * Минимальный набор справочников: [город, категория, индустрия].
 */
function refs(): array
{
    $city = App\Models\City::create(['country' => 'Таджикистан', 'region' => 'Душанбе']);
    $category = App\Models\Category::create(['name' => 'IT', 'description' => 'IT', 'slug' => 'it']);
    $industry = App\Models\Industry::create([
        'name' => 'Разработка',
        'category_id' => $category->id,
        'parent_id' => 0,
        'description' => 'Разработка',
    ]);

    return [$city, $category, $industry];
}

function makeEmployer(App\Models\User $user): App\Models\Employer
{
    [$city, $category, $industry] = refs();

    return App\Models\Employer::create([
        'user_id' => $user->id,
        'company_name' => 'ООО Тест',
        'job' => 'HR',
        'email_company' => 'hr@example.com',
        'phone' => '+992000000000',
        'category_id' => $category->id,
        'description' => 'Компания',
        'city_id' => $city->id,
        'industry_id' => $industry->id,
        'website_url' => 'https://example.com',
    ]);
}

function makeApplicant(App\Models\User $user): App\Models\Applicant
{
    return App\Models\Applicant::create([
        'user_id' => $user->id,
        'education' => 'Высшее',
    ]);
}

function makeVacancy(App\Models\Employer $employer, string $status = 'active'): App\Models\Vacancy
{
    return App\Models\Vacancy::create([
        'title' => 'PHP-разработчик',
        'description' => 'Описание вакансии',
        'employer_id' => $employer->id,
        'salary_from' => 1000,
        'salary_to' => 2000,
        'currency' => 'TJS',
        'employment_type' => 'full-time',
        'work_schedule' => 'full_day',
        'experience_required' => 'year',
        'skill' => 'PHP',
        'status' => $status,
        'city_id' => $employer->city_id,
    ]);
}

function makeResume(App\Models\Applicant $applicant, ?string $documents = null): App\Models\Resume
{
    return App\Models\Resume::create([
        'applicant_id' => $applicant->id,
        'profession' => 'PHP-разработчик',
        'experience_years' => 3,
        'desired_position' => 'Backend',
        'desired_salary' => 5000,
        'languages' => 'русский',
        'documents' => $documents,
    ]);
}
