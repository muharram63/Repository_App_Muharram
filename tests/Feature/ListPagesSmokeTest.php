<?php

use App\Models\User;

/**
 * Дымовой тест на все списки, которые переехали на постраничность
 * и серверный поиск: страницы открываются, фильтры не падают.
 */
function seedCatalog(): void
{
    $employerUser = User::factory()->employer()->create();
    $employer = makeEmployer($employerUser);
    makeVacancy($employer);

    $applicantUser = User::factory()->applicant()->create();
    makeResume(makeApplicant($applicantUser));
}

test('public catalogs render with pagination and server-side search', function () {
    seedCatalog();

    foreach (['/vacancies', '/resume', '/companies'] as $uri) {
        $this->get($uri)->assertOk();
        $this->get($uri.'?q=PHP')->assertOk();
    }

    $this->get('/resume?exp=junior')->assertOk();
    $this->get('/vacancies?q=PHP&city=Душанбе')->assertOk();
    $this->get('/companies?category=1&industry=1')->assertOk();
});

test('admin lists render with pagination and server-side filters', function () {
    seedCatalog();

    $admin = User::factory()->admin()->create();

    $pages = [
        '/superadmin/users' => ['role=employer', 'status=blocked', 'status=active', 'q=test'],
        '/superadmin/vacancy' => ['status=active', 'q=PHP'],
        '/superadmin/companies' => ['status=active', 'q=Тест'],
        '/superadmin/resumes' => ['exp=middle', 'q=PHP'],
        '/superadmin/applicants' => ['kind=withresume', 'q=Душанбе'],
    ];

    foreach ($pages as $uri => $filters) {
        $this->actingAs($admin)->get($uri)->assertOk();

        foreach ($filters as $filter) {
            $this->actingAs($admin)->get($uri.'?'.$filter)->assertOk();
        }
    }
});

test('the second page of a list still keeps the filter', function () {
    seedCatalog();

    // список пользователей отдаётся целиком, лишний page его не ломает
    $this->actingAs(User::factory()->admin()->create())
        ->get('/superadmin/users?role=employer&page=2')
        ->assertOk();

    $this->get('/vacancies?q=PHP&page=2')->assertOk();
});
