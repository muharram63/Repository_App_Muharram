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
