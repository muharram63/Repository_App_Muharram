<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Каждая страница админ-панели должна открываться.
 *
 * Проверка идёт по таблице маршрутов, а не по списку адресов: новый раздел
 * попадёт под неё сам, и забытая страница с ошибкой не доживёт до комиссии.
 */
test('every admin page opens', function () {
    $admin = User::factory()->admin()->create();

    $employerUser = User::factory()->employer()->create();
    $employer = makeEmployer($employerUser);
    $vacancy = makeVacancy($employer);

    $applicant = makeApplicant(User::factory()->applicant()->create());
    $resume = makeResume($applicant);

    // чем подставлять параметры маршрутов
    $values = [
        'user' => $employerUser->id,
        'vacancy' => $vacancy->id,
        'company' => $employer->id,
        'applicant' => $applicant->id,
        'resume' => $resume->id,
        'category' => $employer->category_id,
        'industry' => $employer->industry_id,
        'city' => $employer->city_id,
    ];

    $checked = [];
    $broken = [];
    $skipped = [];

    foreach (Route::getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }

        if (! str_starts_with((string) $route->getName(), 'superadmin.')) {
            continue;
        }

        $uri = $route->uri();

        // подставляем идентификаторы; чего не знаем — честно помечаем пропуском
        if (preg_match_all('/\{(\w+)\??\}/', $uri, $found)) {
            foreach ($found[1] as $param) {
                if (! isset($values[$param])) {
                    $skipped[] = $uri;

                    continue 2;
                }

                $uri = str_replace(['{'.$param.'}', '{'.$param.'?}'], (string) $values[$param], $uri);
            }
        }

        $status = $this->actingAs($admin)->get('/'.ltrim($uri, '/'))->getStatusCode();
        $checked[] = $uri;

        if ($status !== 200) {
            $broken[] = $uri.' → '.$status;
        }
    }

    expect($broken)->toBe([])
        // страховка от того, что фильтр однажды перестанет находить маршруты
        ->and(count($checked))->toBeGreaterThan(20)
        ->and($skipped)->toBe([]);
});

test('a guest is sent to the login page, not to a 403', function () {
    // проверку гостя держим отдельным тестом: actingAs действует до конца теста,
    // и после входа «гостя» в нём уже не получится
    foreach (['/superadmin', '/superadmin/industries', '/superadmin/analytics'] as $uri) {
        $this->get($uri)->assertRedirect(route('login', absolute: false));
    }
});

test('the admin panel is closed to other roles', function () {
    $applicantUser = User::factory()->applicant()->create();
    makeApplicant($applicantUser);

    $employerUser = User::factory()->employer()->create();
    makeEmployer($employerUser);

    foreach (['/superadmin', '/superadmin/industries', '/superadmin/analytics'] as $uri) {
        $this->actingAs($applicantUser)->get($uri)->assertForbidden();
        $this->actingAs($employerUser)->get($uri)->assertForbidden();
    }
});
