<?php

use App\Models\Complaint;
use App\Models\ModerationNotice;
use App\Models\User;
use App\Models\UserNotification;

function complaintFixture(): array
{
    $ownerUser = User::factory()->employer()->create();
    $employer = makeEmployer($ownerUser);
    $vacancy = makeVacancy($employer);

    $reporter = User::factory()->applicant()->create();
    makeApplicant($reporter);

    return [$ownerUser, $vacancy, $reporter];
}

function fileComplaint(User $reporter, $vacancy): Complaint
{
    return Complaint::create([
        'user_id' => $reporter->id,
        'target_type' => 'vacancy',
        'target_id' => $vacancy->id,
        'reason' => 'fake',
    ]);
}

test('the owner of the target is notified as soon as a complaint is filed', function () {
    [$ownerUser, $vacancy, $reporter] = complaintFixture();

    $this->actingAs($reporter)->post('/complaints', [
        'target_type' => 'vacancy',
        'target_id' => $vacancy->id,
        'reason' => 'fake',
        'message' => 'Зарплата не соответствует',
    ])->assertRedirect();

    $notice = ModerationNotice::where('user_id', $ownerUser->id)->first();

    expect($notice)->not->toBeNull();
    expect($notice->action)->toBe('complaint_received');
    expect($notice->seen_at)->toBeNull();
    expect($notice->comment)->not->toContain($reporter->name);

    // и запись в ящике уведомлений
    $inbox = UserNotification::where('user_id', $ownerUser->id)->where('type', 'complaint')->first();
    expect($inbox)->not->toBeNull();
    expect($inbox->body)->not->toContain($reporter->name);
});

test('the owner picks any status, nobody else can', function () {
    [$ownerUser, $vacancy, $reporter] = complaintFixture();
    $complaint = fileComplaint($reporter, $vacancy);

    expect($complaint->isOpen())->toBeTrue();

    $url = '/complaints/'.$complaint->id.'/status';

    // ни автор жалобы, ни посторонний статус не меняют
    $this->actingAs($reporter)->post($url, ['status' => 'resolved'])->assertForbidden();

    // в работе
    $this->actingAs($ownerUser)->post($url, ['status' => 'in_review'])->assertRedirect();
    expect($complaint->fresh()->status)->toBe('in_review');
    expect($complaint->fresh()->resolved_at)->toBeNull();
    expect($complaint->fresh()->isOpen())->toBeTrue();

    // отклонена с ответом автору
    $this->actingAs($ownerUser)
        ->post($url, ['status' => 'rejected', 'reply' => 'Всё по правилам'])
        ->assertRedirect();
    expect($complaint->fresh()->status)->toBe('rejected');
    expect($complaint->fresh()->owner_reply)->toBe('Всё по правилам');
    expect($complaint->fresh()->isOpen())->toBeFalse();

    // устранена
    $this->actingAs($ownerUser)->post($url, ['status' => 'resolved'])->assertRedirect();
    expect($complaint->fresh()->status)->toBe('resolved');
    expect($complaint->fresh()->resolved_at)->not->toBeNull();

    // автор жалобы получил уведомление в ящик
    expect(UserNotification::where('user_id', $reporter->id)->where('type', 'complaint_status')->count())
        ->toBeGreaterThan(0);
});

test('an admin cannot decide whether a complaint is resolved', function () {
    [$ownerUser, $vacancy, $reporter] = complaintFixture();
    $complaint = fileComplaint($reporter, $vacancy);

    $admin = User::factory()->admin()->create();

    // маршрут ручной смены статуса удалён
    $this->actingAs($admin)
        ->patch('/superadmin/complaints/'.$complaint->id.'/status', ['status' => 'resolved'])
        ->assertNotFound();

    // и содержимое пользователя не правит
    $this->actingAs($admin)
        ->post('/superadmin/complaints/'.$complaint->id.'/act', ['action' => 'close_vacancy'])
        ->assertSessionHasErrors('action');

    expect($complaint->fresh()->status)->toBe('new');
    expect($vacancy->fresh()->status)->toBe('active');

    // блокировка ему доступна — и обе стороны об этом узнают
    $this->actingAs($admin)
        ->post('/superadmin/complaints/'.$complaint->id.'/act', ['action' => 'block_user'])
        ->assertRedirect();

    expect($ownerUser->fresh()->status)->toBe('blocked');
    expect($complaint->fresh()->status)->toBe('new');
    expect(UserNotification::where('user_id', $ownerUser->id)->where('type', 'moderation')->count())
        ->toBeGreaterThan(0);
});

test('the cabinet shows complaints filed against the user', function () {
    [$ownerUser, $vacancy, $reporter] = complaintFixture();
    fileComplaint($reporter, $vacancy);

    $this->actingAs($ownerUser)
        ->get('/employer/complaints')
        ->assertOk()
        ->assertSee('Жалобы на меня')
        ->assertSee('Не устранено');
});
