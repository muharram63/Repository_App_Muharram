<?php

use App\Models\User;
use App\Models\UserNotification;

test('the cabinet pages render with the notification bell', function () {
    $employerUser = User::factory()->employer()->create();
    makeEmployer($employerUser);

    $applicantUser = User::factory()->applicant()->create();
    makeApplicant($applicantUser);

    $this->actingAs($employerUser)->get('/employer/profile')->assertOk()->assertSee('nbToggle');
    $this->actingAs($applicantUser)->get('/applicant/profile')->assertOk()->assertSee('nbToggle');
});

test('the inbox lists notifications and marks them read', function () {
    $user = User::factory()->applicant()->create();
    makeApplicant($user);

    UserNotification::deliver($user->id, 'response', 'Новый отклик', 'Иван откликнулся', '/responses');
    UserNotification::deliver($user->id, 'message', 'Новое сообщение', 'Привет', '/chats');

    $this->actingAs($user)->get('/notifications')
        ->assertOk()
        ->assertSee('Новый отклик')
        ->assertSee('Новое сообщение');

    expect($user->unreadNotificationsCount())->toBe(2);

    $this->actingAs($user)->get('/notifications-count')
        ->assertOk()
        ->assertJson(['unread' => 2]);

    $this->actingAs($user)->post('/notifications/read')->assertRedirect();

    expect($user->fresh()->unreadNotificationsCount())->toBe(0);
});

test('opening a notification marks it read and redirects to the object', function () {
    $user = User::factory()->applicant()->create();
    makeApplicant($user);

    $notification = UserNotification::deliver($user->id, 'response', 'Новый отклик', null, '/responses');

    $this->actingAs($user)
        ->get('/notifications/'.$notification->id)
        ->assertRedirect('/responses');

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('a notification belongs to one person only', function () {
    $owner = User::factory()->applicant()->create();
    makeApplicant($owner);
    $stranger = User::factory()->employer()->create();
    makeEmployer($stranger);

    $notification = UserNotification::deliver($owner->id, 'response', 'Новый отклик');

    $this->actingAs($stranger)
        ->get('/notifications/'.$notification->id)
        ->assertForbidden();
});
