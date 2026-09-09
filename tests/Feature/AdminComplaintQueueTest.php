<?php

use App\Models\Complaint;
use App\Models\User;

function queueFixture(): array
{
    $ownerUser = User::factory()->employer()->create();
    $employer = makeEmployer($ownerUser);
    $vacancy = makeVacancy($employer);

    $reporter = User::factory()->applicant()->create();
    makeApplicant($reporter);

    $complaint = Complaint::create([
        'user_id' => $reporter->id,
        'target_type' => 'vacancy',
        'target_id' => $vacancy->id,
        'reason' => 'fake',
    ]);

    return [$ownerUser, $vacancy, $reporter, $complaint];
}

// ===== разбор жалоб больше не забота администратора =====
test('there is no review queue for the admin any more', function () {
    [, , , $complaint] = queueFixture();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->patch('/superadmin/complaints/'.$complaint->id.'/review', ['review_state' => 'handled'])
        ->assertNotFound();

    $this->actingAs($admin)
        ->get('/superadmin/complaints')
        ->assertOk()
        ->assertDontSee('Сохранить разбор')
        ->assertDontSee('Ждут разбора');
});

// ===== блокировка — единственное действие администратора по жалобе =====
test('an admin blocks and unblocks the owner of the complained object', function () {
    $reported = User::factory()->applicant()->create();
    makeApplicant($reported);

    $reporter = User::factory()->employer()->create();
    makeEmployer($reporter);

    $complaint = Complaint::create([
        'user_id' => $reporter->id,
        'target_type' => 'user',
        'target_id' => $reported->id,
        'reason' => 'offensive',
    ]);

    expect($complaint->allowedActions())->toBe(['block_user']);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/superadmin/complaints/'.$complaint->id.'/act', ['action' => 'block_user'])
        ->assertRedirect();

    expect($reported->fresh()->status)->toBe('blocked');
    // судьбу жалобы администратор не решает
    expect($complaint->fresh()->status)->toBe('new');
    expect($complaint->fresh()->allowedActions())->toBe(['unblock_user']);

    $this->actingAs($admin)
        ->post('/superadmin/complaints/'.$complaint->id.'/act', ['action' => 'unblock_user'])
        ->assertRedirect();

    expect($reported->fresh()->status)->toBe('active');
});

// ===== содержимое пользователей администратор не трогает =====
test('the admin is never offered a content measure', function () {
    [, $vacancy, , $complaint] = queueFixture();

    expect($complaint->allowedActions())->toBe(['block_user']);

    $vacancy->update(['status' => 'closed']);
    $complaint->refresh();

    expect($complaint->allowedActions())->toBe(['block_user']);
});

test('a content measure sent directly is rejected', function () {
    [, $vacancy, , $complaint] = queueFixture();

    $admin = User::factory()->admin()->create();

    foreach (['close_vacancy', 'reopen_vacancy', 'hide_resume', 'show_resume'] as $action) {
        $this->actingAs($admin)
            ->post('/superadmin/complaints/'.$complaint->id.'/act', ['action' => $action])
            ->assertSessionHasErrors('action');
    }

    expect($vacancy->fresh()->status)->toBe('active');
});

test('measures are not offered on an object owned by an admin', function () {
    $admin = User::factory()->admin()->create();

    $reporter = User::factory()->employer()->create();
    makeEmployer($reporter);

    $complaint = Complaint::create([
        'user_id' => $reporter->id,
        'target_type' => 'user',
        'target_id' => $admin->id,
        'reason' => 'offensive',
    ]);

    expect($complaint->allowedActions())->toBe([]);

    $this->actingAs($admin)
        ->post('/superadmin/complaints/'.$complaint->id.'/act', ['action' => 'block_user'])
        ->assertSessionHas('error');

    expect($admin->fresh()->status)->toBe('active');
});

// ===== поиск по названию объекта =====
test('search finds a complaint by the title of its target', function () {
    [, , , $complaint] = queueFixture();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/superadmin/complaints?q='.urlencode('PHP-разработчик'))
        ->assertOk()
        ->assertSee('Жалоба №'.$complaint->id);

    $this->actingAs($admin)
        ->get('/superadmin/complaints?q='.urlencode('такого-объекта-нет'))
        ->assertOk()
        ->assertDontSee('Жалоба №'.$complaint->id);
});

// ===== корзина и возврат =====
test('a complaint goes to the bin and can be restored', function () {
    [, , , $complaint] = queueFixture();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->delete('/superadmin/complaints/'.$complaint->id)->assertRedirect();

    expect(Complaint::find($complaint->id))->toBeNull();
    expect(Complaint::onlyTrashed()->find($complaint->id))->not->toBeNull();

    $this->actingAs($admin)->get('/superadmin/complaints?trashed=1')
        ->assertOk()
        ->assertSee('Жалоба №'.$complaint->id);

    $this->actingAs($admin)->post('/superadmin/complaints/'.$complaint->id.'/restore')->assertRedirect();

    expect(Complaint::find($complaint->id))->not->toBeNull();
});

// ===== повторные жалобы на один объект =====
test('repeat complaints about the same object are counted', function () {
    [, $vacancy, , ] = queueFixture();

    $second = User::factory()->applicant()->create();
    makeApplicant($second);

    Complaint::create([
        'user_id' => $second->id,
        'target_type' => 'vacancy',
        'target_id' => $vacancy->id,
        'reason' => 'spam',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/superadmin/complaints')
        ->assertOk()
        ->assertSee('на этот объект');
});
