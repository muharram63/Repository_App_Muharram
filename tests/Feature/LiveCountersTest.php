<?php

use App\Models\Complaint;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\VacancyResponse;

test('the cabinet endpoint answers with live numbers', function () {
    $employerUser = User::factory()->employer()->create();
    $employer = makeEmployer($employerUser);
    $vacancy = makeVacancy($employer);

    $applicantUser = User::factory()->applicant()->create();
    $applicant = makeApplicant($applicantUser);

    VacancyResponse::create(['applicant_id' => $applicant->id, 'vacancy_id' => $vacancy->id]);
    UserNotification::deliver($employerUser->id, 'response', 'Новый отклик');

    $this->actingAs($employerUser)->get('/live-counters')
        ->assertOk()
        ->assertJson([
            'nav-notifications' => 1,
            'vacanciesCount' => 1,
            'responsesCount' => 1,
            'responsesPending' => 1,
        ]);
});

test('cabinet numbers follow the data', function () {
    $applicantUser = User::factory()->applicant()->create();
    makeApplicant($applicantUser);

    $before = $this->actingAs($applicantUser)->get('/live-counters')->json();
    expect($before['nav-notifications'])->toBe(0);

    UserNotification::deliver($applicantUser->id, 'message', 'Новое сообщение');

    // цифры меню считаются один раз за запрос и держатся на объекте пользователя;
    // в реальном запросе он каждый раз загружается заново, поэтому берём свежий
    $after = $this->actingAs($applicantUser->fresh())->get('/live-counters')->json();
    expect($after['nav-notifications'])->toBe(1);
    expect($after['notifications-total'])->toBe(1);
});

test('the admin endpoint answers with live numbers', function () {
    $employerUser = User::factory()->employer()->create();
    $employer = makeEmployer($employerUser);
    $vacancy = makeVacancy($employer);

    $reporter = User::factory()->applicant()->create();
    makeApplicant($reporter);

    Complaint::create([
        'user_id' => $reporter->id,
        'target_type' => 'vacancy',
        'target_id' => $vacancy->id,
        'reason' => 'fake',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/superadmin/live-counters')
        ->assertOk()
        ->assertJson([
            'nav-complaints' => 1,
            'complaints-total' => 1,
            'complaints-today' => 1,
            'complaints-trashed' => 0,
            'vacanciesTotal' => 1,
            'activeVacancies' => 1,
        ]);
});

test('a page asks only for the numbers it shows', function () {
    $employerUser = User::factory()->employer()->create();
    $employer = makeEmployer($employerUser);
    makeVacancy($employer);

    // журнал звонков и аналитика считаются только когда их спросили
    $narrow = $this->actingAs($employerUser)
        ->get('/live-counters?keys=nav-notifications,vacanciesCount')
        ->json();

    expect(array_keys($narrow))->toBe(['nav-notifications', 'vacanciesCount']);

    $withCalls = $this->actingAs($employerUser->fresh())
        ->get('/live-counters?keys=calls-all,calls-missed')
        ->json();

    expect($withCalls)->toHaveKeys(['calls-all', 'calls-missed']);
    expect($withCalls['calls-all'])->toBe(0);
});

test('analytics numbers are served on request', function () {
    $employerUser = User::factory()->employer()->create();
    $employer = makeEmployer($employerUser);
    makeVacancy($employer);

    $applicantUser = User::factory()->applicant()->create();
    makeResume(makeApplicant($applicantUser));

    $numbers = $this->actingAs(User::factory()->admin()->create())
        ->get('/superadmin/live-counters?keys=an-conversion,an-dist-schedule-fullday,an-complaints-total,activeVacancies')
        ->assertOk()
        ->json();

    // порядок ключей задаёт карта на сервере, а не запрос
    expect($numbers)->toHaveKeys(['an-conversion', 'an-dist-schedule-fullday', 'an-complaints-total', 'activeVacancies']);
    expect($numbers)->toHaveCount(4);
    expect($numbers['an-complaints-total'])->toBe(0);
    // распределения отдают число, а не долю: доля живёт под ключом с -pct
    expect($numbers['an-dist-schedule-fullday'])->toBe(1);
});

test('both cabinets separate what came in from what was sent', function () {
    $employerUser = User::factory()->employer()->create();
    $employer = makeEmployer($employerUser);
    $vacancy = makeVacancy($employer);

    $applicantUser = User::factory()->applicant()->create();
    $applicant = makeApplicant($applicantUser);
    $resume = makeResume($applicant);

    // кандидат откликнулся на вакансию, компания пригласила по резюме
    App\Models\VacancyResponse::create([
        'applicant_id' => $applicant->id, 'vacancy_id' => $vacancy->id, 'status' => 'new',
    ]);
    App\Models\ResumeResponse::create([
        'employer_id' => $employer->id, 'resume_id' => $resume->id, 'status' => 'new',
    ]);

    // у соискателя: приглашение пришло, отклик отправлен
    $mine = $this->actingAs($applicantUser->fresh())
        ->get('/live-counters?keys=invitesCount,invitesPending,responsesCount,responsesPending')
        ->assertOk()->json();

    expect($mine['invitesCount'])->toBe(1)
        ->and($mine['invitesPending'])->toBe(1)
        ->and($mine['responsesCount'])->toBe(1);

    // у компании — ровно наоборот
    $theirs = $this->actingAs($employerUser->fresh())
        ->get('/live-counters?keys=invitesCount,invitesPending,responsesCount,responsesPending')
        ->assertOk()->json();

    expect($theirs['responsesCount'])->toBe(1)
        ->and($theirs['responsesPending'])->toBe(1)
        ->and($theirs['invitesCount'])->toBe(1);

    // обе карточки видны в кабинетах и ведут к своим спискам
    $this->actingAs($applicantUser->fresh())->get('/applicant/profile')
        ->assertOk()
        ->assertSee('Получено приглашений')
        ->assertSee('Отправлено откликов')
        ->assertSee(route('public.responses.index').'#received', false)
        ->assertSee(route('public.responses.index').'#sent', false);

    $this->actingAs($employerUser->fresh())->get('/employer/profile')
        ->assertOk()
        ->assertSee('Получено откликов')
        ->assertSee('Отправлено приглашений')
        ->assertSee(route('public.responses.index').'#received', false)
        ->assertSee(route('public.responses.index').'#sent', false);

    // якоря существуют на странице, куда ведут карточки
    $this->actingAs($applicantUser->fresh())->get('/responses')
        ->assertOk()
        ->assertSee('id="sent"', false)
        ->assertSee('id="received"', false);
});

test('both cabinets can collapse the sidebar', function () {
    $applicantUser = User::factory()->applicant()->create();
    makeApplicant($applicantUser);

    $employerUser = User::factory()->employer()->create();
    makeEmployer($employerUser);

    foreach ([[$applicantUser, '/applicant/profile'], [$employerUser, '/employer/profile']] as [$user, $uri]) {
        $html = $this->actingAs($user->fresh())->get($uri)->assertOk()->getContent();

        // кнопка, стили свёрнутого состояния и сохранение выбора — всё вместе,
        // иначе меню сворачивалось бы и разворачивалось само на каждой странице
        expect($html)->toContain('id="sideToggle"')
            ->and($html)->toContain('aria-controls="cabinetSidebar"')
            ->and($html)->toContain('.app.rail')
            ->and($html)->toContain('workio.sidebar');
    }
});

test('the call log and analytics pages carry live markers', function () {
    $employerUser = User::factory()->employer()->create();
    makeEmployer($employerUser);

    $this->actingAs($employerUser)->get('/employer/calls')
        ->assertOk()
        ->assertSee('data-live="calls-all"', false)
        ->assertSee('data-live="calls-missed"', false);

    $this->actingAs(User::factory()->admin()->create())->get('/superadmin/analytics')
        ->assertOk()
        ->assertSee('data-live="an-conversion"', false)
        ->assertSee('data-live="an-dist-experience-year"', false)
        ->assertSee('data-live-width="an-dist-schedule-fullday-pct"', false);
});

test('the overview and analytics pages are fully live', function () {
    $employerUser = User::factory()->employer()->create();
    $employer = makeEmployer($employerUser);
    makeVacancy($employer);
    makeResume(makeApplicant(User::factory()->applicant()->create()));

    $admin = User::factory()->admin()->create();

    foreach (['/superadmin' => 12, '/superadmin/analytics' => 40] as $uri => $least) {
        $html = $this->actingAs($admin)->get($uri)->assertOk()->getContent();

        preg_match_all('/data-live(?:-width)?="([a-zA-Z0-9_-]+)"/', $html, $found);
        $keys = array_values(array_unique($found[1]));

        expect(count($keys))->toBeGreaterThanOrEqual($least);

        // каждый маркер на странице обслуживается сервером
        $numbers = $this->actingAs($admin)
            ->get('/superadmin/live-counters?keys='.implode(',', $keys))
            ->assertOk()
            ->json();

        expect(array_diff($keys, array_keys($numbers)))->toBe([]);
    }
});

test('live endpoints are closed to outsiders', function () {
    $this->get('/live-counters')->assertRedirect(route('login', absolute: false));
    $this->get('/superadmin/live-counters')->assertRedirect(route('login', absolute: false));

    $applicantUser = User::factory()->applicant()->create();
    makeApplicant($applicantUser);

    $this->actingAs($applicantUser)->get('/superadmin/live-counters')->assertForbidden();

    // у администратора нет кабинета — кабинетные цифры ему не отдаются
    $this->actingAs(User::factory()->admin()->create())->get('/live-counters')->assertForbidden();
});

test('pages carry the markers the script fills in', function () {
    $employerUser = User::factory()->employer()->create();
    makeEmployer($employerUser);

    $this->actingAs($employerUser)->get('/employer/profile')
        ->assertOk()
        ->assertSee('data-live="nav-notifications"', false)
        ->assertSee('data-live="profileViews"', false)
        ->assertSee('live-counters', false);

    $this->actingAs(User::factory()->admin()->create())->get('/superadmin/complaints')
        ->assertOk()
        ->assertSee('data-live="complaints-total"', false)
        ->assertSee('data-live="nav-complaints"', false);
});
