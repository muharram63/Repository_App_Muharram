<?php

/*
 * Видеовстреча: попасть в комнату, когда время пришло.
 *
 * Сама комната открывалась правильно — это проверено здесь по минутам. А вот
 * дойти до неё из кабинета было нельзя: раздел «Собеседования» жил только в
 * шапке публичного сайта, и человек, сидящий в личном кабинете, его не видел.
 * Напомнить тоже было нечем: уведомление приходит один раз, когда встречу
 * назначают, а планировщика в проекте нет, и фоновая задача к часу встречи
 * просто не запустилась бы.
 */

use App\Models\Interview;
use App\Models\User;

/**
 * Работодатель, соискатель и встреча между ними.
 *
 * @return array{0:Interview,1:User,2:User}
 */
function meeting(array $state = []): array
{
    $employerUser = User::factory()->employer()->create();
    $employer = makeEmployer($employerUser);

    $applicantUser = User::factory()->applicant()->create();
    $applicant = makeApplicant($applicantUser);

    $interview = Interview::create($state + [
        'employer_id' => $employer->id,
        'applicant_id' => $applicant->id,
        'scheduled_at' => now(),
        'duration_minutes' => 30,
        'room' => 'workio-'.str()->lower(str()->random(20)),
        'status' => 'scheduled',
    ]);

    return [$interview, $applicantUser, $employerUser];
}

// ==================== когда комната открыта ====================

test('комната открывается за пятнадцать минут и живёт до конца встречи с запасом', function () {
    $cases = [
        // [когда назначено, открыта ли]
        ['+20 minutes', false],  // ещё рано
        ['+15 minutes', true],   // ровно на границе
        ['+10 minutes', true],
        ['now', true],
        ['-10 minutes', true],   // идёт
        ['-55 minutes', true],   // 30 минут встречи и запас в полчаса
        ['-70 minutes', false],  // запас вышел
    ];

    foreach ($cases as [$when, $expected]) {
        [$interview] = meeting(['scheduled_at' => new DateTime($when === 'now' ? 'now' : $when)]);

        expect($interview->isRoomOpen())->toBe($expected, 'назначено '.$when);
    }
});

test('отменённая, отклонённая и завершённая встреча комнату не открывает', function () {
    foreach (['canceled', 'declined', 'finished'] as $status) {
        [$interview] = meeting(['status' => $status]);

        /*
         * Статуса finished в списке не было, и после того как работодатель
         * отмечал встречу завершённой, комната оставалась открытой ещё
         * полчаса: кнопка звала войти туда, откуда все уже вышли.
         */
        expect($interview->isRoomOpen())->toBeFalse($status);
    }
});

test('соискатель входит в комнату в назначенное время', function () {
    [$interview, $applicantUser] = meeting();

    $this->actingAs($applicantUser)
        ->get(route('public.interviews.show', $interview))
        ->assertOk()
        ->assertSee('id="jitsi"', false)
        ->assertSee('Комната открыта', false)
        ->assertDontSee('откроется за 15 минут', false);

    // открыл не тот, кто звал, — встреча засчитана состоявшейся
    expect($interview->fresh()->answered_at)->not->toBeNull();
});

test('до срока вместо комнаты честное ожидание', function () {
    [$interview, $applicantUser] = meeting(['scheduled_at' => now()->addHour()]);

    $this->actingAs($applicantUser)
        ->get(route('public.interviews.show', $interview))
        ->assertOk()
        ->assertSee('откроется за 15 минут', false)
        ->assertDontSee('id="jitsi"', false);
});

test('посторонний в комнату не попадёт', function () {
    [$interview] = meeting();

    $stranger = User::factory()->applicant()->create();
    makeApplicant($stranger);

    $this->actingAs($stranger)
        ->get(route('public.interviews.show', $interview))
        ->assertForbidden();
});

// ==================== как человек узнаёт, что пора ====================

test('в кабинете есть путь к собеседованиям', function () {
    [, $applicantUser, $employerUser] = meeting();

    /*
     * Раздел был только в шапке публичного сайта. Сидящий в кабинете до него
     * не доходил — а именно там человек и находится, когда ждёт встречу.
     */
    $this->actingAs($applicantUser)->get(route('applicant.dashboard'))
        ->assertOk()
        ->assertSee(route('public.interviews.index'), false)
        ->assertSee('Собеседования', false);

    $this->actingAs($employerUser)->get(route('employer.dashboard'))
        ->assertOk()
        ->assertSee(route('public.interviews.index'), false);
});

test('пока комната открыта, в меню стоит отметка «сейчас»', function () {
    [, $applicantUser] = meeting();

    $html = $this->actingAs($applicantUser)->get(route('applicant.dashboard'))
        ->assertOk()->getContent();

    // отметка не спрятана — это и есть напоминание, что время пришло
    expect($html)->toMatch('/data-section="interviews".*?сейчас<\/span>/su')
        ->and($html)->not->toMatch('/data-section="interviews".*?hidden\s*>\s*сейчас/su');
});

test('когда встреча ещё не скоро, отметка скрыта', function () {
    [, $applicantUser] = meeting(['scheduled_at' => now()->addDays(2)]);

    $html = $this->actingAs($applicantUser)->get(route('applicant.dashboard'))
        ->assertOk()->getContent();

    expect($html)->toMatch('/data-section="interviews".*?hidden\s*>\s*сейчас/su');
});

test('счёт открытых комнат считает только свои и только живые', function () {
    [$interview, $applicantUser] = meeting();

    expect($applicantUser->fresh()->openInterviewRooms())->toBe(1);

    // чужая встреча в счёт не идёт
    meeting();

    expect($applicantUser->fresh()->openInterviewRooms())->toBe(1);

    $interview->update(['status' => 'canceled']);

    expect($applicantUser->fresh()->openInterviewRooms())->toBe(0);
});

test('давняя встреча отметку не зажигает', function () {
    [, $applicantUser] = meeting(['scheduled_at' => now()->subDays(3)]);

    expect($applicantUser->openInterviewRooms())->toBe(0);
});

// ==================== приглашение ====================

test('соискатель получает непрочитанное приглашение со ссылкой на встречу', function () {
    $employerUser = User::factory()->employer()->create();
    $employer = makeEmployer($employerUser);

    $applicantUser = User::factory()->applicant()->create();
    $applicant = makeApplicant($applicantUser);

    // компания и соискатель уже связаны: иначе назначать встречу нельзя
    $vacancy = makeVacancy($employer);
    App\Models\VacancyResponse::create([
        'applicant_id' => $applicant->id,
        'vacancy_id' => $vacancy->id,
    ]);

    $this->actingAs($employerUser)->post(route('public.interviews.store'), [
        'applicant_id' => $applicant->id,
        'vacancy_id' => $vacancy->id,
        'scheduled_at' => now()->addHour()->format('Y-m-d\TH:i'),
        'duration_minutes' => 30,
    ])->assertRedirect();

    $interview = Interview::sole();

    $note = App\Models\UserNotification::where('user_id', $applicantUser->id)
        ->where('title', 'Вас пригласили на собеседование')->sole();

    expect($note->read_at)->toBeNull()
        ->and($note->url)->toBe(route('public.interviews.show', $interview));
});

// ==================== видеосвязь: встраивание или новая вкладка ====================

/*
 * meet.ffmuc.net запрещает показ себя внутри чужих сайтов (X-Frame-Options
 * SAMEORIGIN и frame-ancestors со своими доменами). Встроенная встреча была
 * пустой рамкой «отказано в подключении», а запасная ссылка не появлялась.
 */
test('сервер, запрещающий встраивание, — встреча открывается в новой вкладке', function () {
    config(['services.jitsi.embed' => 'auto', 'services.jitsi.domain' => 'meet.ffmuc.net']);
    Illuminate\Support\Facades\Cache::flush();

    Illuminate\Support\Facades\Http::fake(['*' => Illuminate\Support\Facades\Http::response('', 200, [
        'X-Frame-Options' => 'SAMEORIGIN',
        'Content-Security-Policy' => "frame-ancestors 'self' *.ffmuc.net;",
    ])]);

    [$interview, $applicantUser] = meeting();

    $this->actingAs($applicantUser)
        ->get(route('public.interviews.show', $interview))
        ->assertOk()
        ->assertSee('data-embed="0"', false)
        ->assertSee('https://meet.ffmuc.net/'.$interview->room, false)
        ->assertSee('target="_blank"', false)
        ->assertDontSee('external_api.js', false);
});

test('сервер, разрешающий встраивание, — встреча прямо на странице', function () {
    config(['services.jitsi.embed' => 'auto', 'services.jitsi.domain' => 'meet.element.io']);
    Illuminate\Support\Facades\Cache::flush();

    Illuminate\Support\Facades\Http::fake(['*' => Illuminate\Support\Facades\Http::response('', 200)]);

    [$interview, $applicantUser] = meeting();

    $this->actingAs($applicantUser)
        ->get(route('public.interviews.show', $interview))
        ->assertOk()
        ->assertSee('data-embed="1"', false)
        ->assertSee('https://meet.element.io/external_api.js', false)
        // на незащищённой странице скрипт сам уходит в новую вкладку
        ->assertSee('window.isSecureContext', false);
});

test('ответ сервера Jitsi запоминается, а не спрашивается на каждом открытии', function () {
    config(['services.jitsi.embed' => 'auto', 'services.jitsi.domain' => 'meet.element.io']);
    Illuminate\Support\Facades\Cache::flush();
    Illuminate\Support\Facades\Http::fake(['*' => Illuminate\Support\Facades\Http::response('', 200)]);

    App\Support\VideoRoom::embeddable('workio.tj');
    App\Support\VideoRoom::embeddable('workio.tj');

    Illuminate\Support\Facades\Http::assertSentCount(1);
});

test('недоступный сервер Jitsi — новая вкладка, а не пустая рамка', function () {
    config(['services.jitsi.embed' => 'auto']);
    Illuminate\Support\Facades\Cache::flush();
    Illuminate\Support\Facades\Http::fake(fn () => throw new Illuminate\Http\Client\ConnectionException('нет сети'));

    expect(App\Support\VideoRoom::embeddable('workio.tj'))->toBeFalse();
});

test('разбор заголовков встраивания', function () {
    $allow = fn (string $xfo, string $csp, string $host = 'workio.tj') => App\Support\VideoRoom::headersAllow($host, $xfo, $csp);

    expect($allow('', ''))->toBeTrue()
        ->and($allow('SAMEORIGIN', ''))->toBeFalse()
        ->and($allow('DENY', ''))->toBeFalse()
        ->and($allow('', "frame-ancestors 'self' *.ffmuc.net"))->toBeFalse()
        ->and($allow('', 'frame-ancestors *'))->toBeTrue()
        ->and($allow('', 'frame-ancestors https://workio.tj'))->toBeTrue()
        ->and($allow('', 'frame-ancestors *.workio.tj', 'app.workio.tj'))->toBeTrue();
});

test('почта участника на сервер видеосвязи не уходит', function () {
    config(['services.jitsi.embed' => 'always']);
    [$interview, $applicantUser] = meeting();

    $html = $this->actingAs($applicantUser)
        ->get(route('public.interviews.show', $interview))
        ->assertOk()
        ->getContent();

    // настройки встречи, которые уходят на сервер Jitsi
    preg_match('/new JitsiMeetExternalAPI\((.*?)\}\);/s', $html, $setup);

    expect($setup)->not->toBeEmpty()
        ->and($setup[1])->toContain('displayName')
        ->and($setup[1])->not->toContain('email')
        ->and($setup[1])->not->toContain($applicantUser->email);
});

// ==================== вторая сторона узнаёт о решении ====================

test('отказ соискателя от встречи приходит работодателю, отмена — соискателю', function () {
    [$interview, $applicantUser, $employerUser] = meeting(['scheduled_at' => now()->addDay()]);

    $this->actingAs($applicantUser)
        ->patch(route('public.interviews.status', $interview), ['status' => 'declined']);

    expect(App\Models\UserNotification::where('user_id', $employerUser->id)
        ->where('title', 'Приглашение на собеседование отклонено')->exists())->toBeTrue();

    [$other, $otherApplicant, $otherEmployer] = meeting(['scheduled_at' => now()->addDay()]);

    $this->actingAs($otherEmployer)
        ->patch(route('public.interviews.status', $other), ['status' => 'canceled']);

    expect(App\Models\UserNotification::where('user_id', $otherApplicant->id)
        ->where('title', 'Собеседование отменено')->exists())->toBeTrue();
});
