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

    expect(UserNotification::where('user_id', $employerUser->id)->where('type', 'response')->count())->toBe(1);

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
