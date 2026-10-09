<?php

use App\Models\Conversation;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserSetting;
use App\Models\VacancyResponse;

test('a response and its status change reach both inboxes', function () {
    $employerUser = User::factory()->employer()->create();
    $employer = makeEmployer($employerUser);
    $vacancy = makeVacancy($employer);

    $applicantUser = User::factory()->applicant()->create();
    $applicant = makeApplicant($applicantUser);

    // соискатель откликается — работодателю падает уведомление
    $this->actingAs($applicantUser)
        ->post('/vacancies/'.$vacancy->id.'/respond', ['message' => 'Готов приступить'])
        ->assertRedirect();

    // «новый кандидат» — вид уведомления работодателю о кандидатах
    expect(UserNotification::where('user_id', $employerUser->id)->where('type', 'candidate')->count())->toBe(1);

    // работодатель меняет статус — уведомление уходит соискателю
    $response = VacancyResponse::first();

    $this->actingAs($employerUser)
        ->patch('/vacancy-responses/'.$response->id.'/status', ['status' => 'accepted'])
        ->assertRedirect();

    expect(UserNotification::where('user_id', $applicantUser->id)->where('type', 'response_status')->count())->toBe(1);
});

test('a chat message reaches the other side inbox', function () {
    $employerUser = User::factory()->employer()->create();
    $employer = makeEmployer($employerUser);
    $applicantUser = User::factory()->applicant()->create();
    $applicant = makeApplicant($applicantUser);

    $conversation = Conversation::create([
        'employer_id' => $employer->id,
        'applicant_id' => $applicant->id,
    ]);

    $this->actingAs($employerUser)
        ->post('/chats/'.$conversation->id.'/messages', ['body' => 'Добрый день'])
        ->assertRedirect();

    $inbox = UserNotification::where('user_id', $applicantUser->id)->where('type', 'message')->first();

    expect($inbox)->not->toBeNull();
    expect($inbox->body)->toContain('Добрый день');

    // отправитель тоже видит запись у себя, но уже прочитанной —
    // счётчик непрочитанного от собственных действий не растёт
    $own = UserNotification::where('user_id', $employerUser->id)->get();
    expect($own)->toHaveCount(1);
    expect($own->first()->isUnread())->toBeFalse();
    expect($employerUser->fresh()->unreadNotificationsCount())->toBe(0);
});

test('a user can switch a notification type off', function () {
    $employerUser = User::factory()->employer()->create();
    $employer = makeEmployer($employerUser);
    $applicantUser = User::factory()->applicant()->create();
    $applicant = makeApplicant($applicantUser);

    UserSetting::updateOrCreate(['user_id' => $applicantUser->id], ['mail_messages' => false]);

    $conversation = Conversation::create([
        'employer_id' => $employer->id,
        'applicant_id' => $applicant->id,
    ]);

    $this->actingAs($employerUser)
        ->post('/chats/'.$conversation->id.'/messages', ['body' => 'Добрый день']);

    expect(UserNotification::where('user_id', $applicantUser->id)->count())->toBe(0);
});

test('a scheduled interview reaches the applicant inbox', function () {
    $employerUser = User::factory()->employer()->create();
    $employer = makeEmployer($employerUser);
    $vacancy = makeVacancy($employer);

    $applicantUser = User::factory()->applicant()->create();
    $applicant = makeApplicant($applicantUser);

    VacancyResponse::create(['applicant_id' => $applicant->id, 'vacancy_id' => $vacancy->id]);

    $this->actingAs($employerUser)->post('/interviews', [
        'applicant_id' => $applicant->id,
        'vacancy_id' => $vacancy->id,
        'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'duration_minutes' => 30,
    ])->assertRedirect();

    expect(UserNotification::where('user_id', $applicantUser->id)->where('type', 'interview')->count())->toBe(1);
});

// ==================== звук и всплывающее сообщение ====================

test('счётчик отдаёт последнее непрочитанное уведомление для всплывающей карточки', function () {
    $user = App\Models\User::factory()->applicant()->create();

    App\Models\UserNotification::deliver($user->id, 'response_status', 'Вы прошли отбор', 'Поздравляем!', '/applicant/ai-interview');

    $note = App\Models\UserNotification::where('user_id', $user->id)->sole();

    $this->actingAs($user)->getJson(route('public.notifications.count'))
        ->assertOk()
        ->assertJsonPath('unread', 1)
        ->assertJsonPath('latest.id', $note->id)
        ->assertJsonPath('latest.title', 'Вы прошли отбор')
        ->assertJsonPath('latest.url', route('public.notifications.open', $note));
});

test('без непрочитанных карточке показывать нечего', function () {
    $user = App\Models\User::factory()->applicant()->create();

    $this->actingAs($user)->getJson(route('public.notifications.count'))
        ->assertOk()
        ->assertJsonPath('latest', null);
});

test('звук уведомлений есть и на страницах ИИ-собеседования, где нет колокольчика', function () {
    Illuminate\Support\Facades\Queue::fake();
    Illuminate\Support\Facades\Storage::fake('local');
    [$vacancy, , $employerUser] = makeAiVacancy();
    [$interview, , $applicantUser] = makeAiInterview($vacancy, ['consent_at' => now(), 'stage' => 'interview', 'analysis_status' => 'ready']);

    $this->actingAs($applicantUser)->get(route('applicant.ai.chat', $interview))
        ->assertOk()
        ->assertSee('id="naToast"', false);

    $this->actingAs($employerUser)->get(route('employer.ai.candidate', [$vacancy, $interview]))
        ->assertOk()
        ->assertSee('id="naToast"', false)
        // обсуждение с Анной включает голос само
        ->assertSee('if (engine) { switchVoice(true); }', false);
});

test('счётчик отдаёт время уведомления и сервера — по ним страница решает, что новое', function () {
    $user = App\Models\User::factory()->applicant()->create();

    // непрочитанных нет: первое настоящее уведомление не должно пропасть молча
    $this->actingAs($user)->getJson(route('public.notifications.count'))
        ->assertOk()
        ->assertJsonPath('latest', null)
        ->assertJsonStructure(['unread', 'now', 'latest']);

    App\Models\UserNotification::deliver($user->id, 'order', 'Новый отклик', 'Откликов: 1', '/orders/1', collapse: true);
    $first = $this->actingAs($user)->getJson(route('public.notifications.count'))->json('latest');

    // склеенное уведомление обновляется на месте: номер тот же, время новее
    $this->travel(5)->seconds();
    App\Models\UserNotification::deliver($user->id, 'order', 'Новый отклик', 'Откликов: 2', '/orders/1', collapse: true);
    $second = $this->actingAs($user)->getJson(route('public.notifications.count'))->json('latest');

    expect($second['id'])->toBe($first['id'])
        ->and($second['ts'])->toBeGreaterThan($first['ts']);
});

test('шапка на телефоне: кнопки входа и кабинета — в меню, а не за краем экрана', function () {
    $this->get(route('public.home'))
        ->assertSee('class="hdr-m-actions"', false)
        ->assertSee(route('register'), false);

    $applicant = App\Models\User::factory()->applicant()->create();
    makeApplicant($applicant);
    $this->actingAs($applicant)->get(route('public.masters.index'))
        ->assertSee('class="hdr-m-actions"', false)
        ->assertSee(route('applicant.dashboard'), false);
});

test('поле ФИО в регистрации без жёсткой ширины', function () {
    $this->get(route('register'))->assertDontSee('style="width: 66vh;"', false);
});

test('ссылка уведомления хранится без адреса сайта — работает на любом адресе', function () {
    $user = App\Models\User::factory()->applicant()->create();

    // как из планировщика: route() дал адрес из APP_URL
    App\Models\UserNotification::deliver($user->id, 'order', 'Срок заявки истёк', null, 'http://localhost/orders/create?repeat=5#top');

    expect(App\Models\UserNotification::where('user_id', $user->id)->value('url'))->toBe('/orders/create?repeat=5#top')
        ->and(App\Models\UserNotification::localUrl('https://example.org/page'))->toBe('https://example.org/page')
        ->and(App\Models\UserNotification::localUrl('/orders/1'))->toBe('/orders/1')
        ->and(App\Models\UserNotification::localUrl(null))->toBeNull();

    $note = App\Models\UserNotification::where('user_id', $user->id)->sole();
    $this->actingAs($user)->get(route('public.notifications.open', $note))->assertRedirect('/orders/create?repeat=5#top');
});
