<?php

use App\Models\Conversation;
use App\Models\User;
use App\Models\UserNotification;

function pair(): array
{
    $employerUser = User::factory()->employer()->create(['name' => 'Компания Тест']);
    $employer = makeEmployer($employerUser);
    $vacancy = makeVacancy($employer);

    $applicantUser = User::factory()->applicant()->create(['name' => 'Пётр Соискатель']);
    $applicant = makeApplicant($applicantUser);
    $resume = makeResume($applicant);

    return [$employerUser, $employer, $vacancy, $applicantUser, $applicant, $resume];
}

test('a response lands in both inboxes', function () {
    [$employerUser, , $vacancy, $applicantUser] = pair();

    $this->actingAs($applicantUser)
        ->post('/vacancies/'.$vacancy->id.'/respond', ['message' => 'Готов приступить'])
        ->assertRedirect();

    // получателю — непрочитанное
    $forEmployer = UserNotification::where('user_id', $employerUser->id)->first();
    expect($forEmployer)->not->toBeNull();
    expect($forEmployer->title)->toBe('Новый отклик на вакансию');
    expect($forEmployer->isUnread())->toBeTrue();

    // отправителю — запись о своём действии, уже прочитанная
    $forApplicant = UserNotification::where('user_id', $applicantUser->id)->first();
    expect($forApplicant)->not->toBeNull();
    expect($forApplicant->title)->toBe('Вы откликнулись на вакансию');
    expect($forApplicant->isUnread())->toBeFalse();

    // счётчик непрочитанного растёт только у получателя
    expect($employerUser->fresh()->unreadNotificationsCount())->toBe(1);
    expect($applicantUser->fresh()->unreadNotificationsCount())->toBe(0);
});

test('an invitation lands in both inboxes', function () {
    [$employerUser, , , $applicantUser, , $resume] = pair();

    $this->actingAs($employerUser)
        ->post('/resume/'.$resume->id.'/respond', ['message' => 'Приходите на собеседование'])
        ->assertRedirect();

    $forApplicant = UserNotification::where('user_id', $applicantUser->id)->first();
    expect($forApplicant->title)->toBe('Приглашение от работодателя');
    expect($forApplicant->isUnread())->toBeTrue();

    $forEmployer = UserNotification::where('user_id', $employerUser->id)->first();
    expect($forEmployer->title)->toBe('Вы отправили приглашение');
    expect($forEmployer->isUnread())->toBeFalse();
});

test('a chat message lands in both inboxes and stays one row per dialog', function () {
    [$employerUser, $employer, , $applicantUser, $applicant] = pair();

    $conversation = Conversation::create([
        'employer_id' => $employer->id,
        'applicant_id' => $applicant->id,
    ]);

    foreach (['Первое', 'Второе', 'Третье'] as $text) {
        $this->actingAs($employerUser)
            ->post('/chats/'.$conversation->id.'/messages', ['body' => $text]);
    }

    // у получателя одна склеенная запись с последним текстом
    $forApplicant = UserNotification::where('user_id', $applicantUser->id)->get();
    expect($forApplicant)->toHaveCount(1);
    expect($forApplicant->first()->body)->toContain('Третье');
    expect($forApplicant->first()->isUnread())->toBeTrue();

    // у отправителя тоже одна, но прочитанная
    $forEmployer = UserNotification::where('user_id', $employerUser->id)->get();
    expect($forEmployer)->toHaveCount(1);
    expect($forEmployer->first()->title)->toBe('Вы написали в чате');
    expect($forEmployer->first()->body)->toContain('Третье');
    expect($forEmployer->first()->isUnread())->toBeFalse();

    expect($employerUser->fresh()->unreadNotificationsCount())->toBe(0);
});

test('a user who switched the notification off gets nothing at all', function () {
    [$employerUser, , $vacancy, $applicantUser] = pair();

    App\Models\UserSetting::updateOrCreate(['user_id' => $applicantUser->id], ['mail_responses' => false]);

    $this->actingAs($applicantUser)
        ->post('/vacancies/'.$vacancy->id.'/respond', ['message' => 'Готов приступить']);

    // работодателю пришло, отправителю — нет: он сам отключил этот вид
    expect(UserNotification::where('user_id', $employerUser->id)->count())->toBe(1);
    expect(UserNotification::where('user_id', $applicantUser->id)->count())->toBe(0);
});
