<?php

use App\Models\User;

test('an admin cannot change a users name, email or role', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->applicant()->create(['name' => 'Исходное имя', 'email' => 'user@example.com']);

    $this->actingAs($admin)->patch('/superadmin/users/'.$user->id, [
        'name' => 'Подменённое имя',
        'email' => 'hacked@example.com',
        'role' => 'admin',
        'status' => 'active',
    ])->assertRedirect();

    $user->refresh();

    expect($user->name)->toBe('Исходное имя');
    expect($user->email)->toBe('user@example.com');
    expect($user->role)->toBe('applicant');
});

test('an admin can still block and delete', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->applicant()->create();

    $this->actingAs($admin)->patch('/superadmin/users/'.$user->id, ['status' => 'blocked']);
    expect($user->fresh()->status)->toBe('blocked');

    $this->actingAs($admin)->delete('/superadmin/users/'.$user->id);
    expect(User::find($user->id))->toBeNull();
});

test('there is no endpoint for editing someone elses profile at all', function () {
    $admin = User::factory()->admin()->create();

    $ownerUser = User::factory()->employer()->create();
    $employer = makeEmployer($ownerUser);

    // роуты public.employers.update / public.applicants.update удалены:
    // анкету правит только владелец через свой кабинет
    $this->actingAs($admin)->put('/employers/'.$employer->id, [
        'company_name' => 'Переименовано админом',
    ])->assertNotFound();

    expect($employer->fresh()->company_name)->toBe('ООО Тест');

    $applicantUser = User::factory()->applicant()->create();
    $applicant = makeApplicant($applicantUser);

    $this->actingAs($admin)->put('/applicants/'.$applicant->id, [
        'education' => 'Изменено админом',
    ])->assertNotFound();

    expect($applicant->fresh()->education)->toBe('Высшее');
});

test('the last admin cannot resign, a second admin can', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/superadmin/users/resign', ['role' => 'applicant']);
    expect($admin->fresh()->role)->toBe('admin');

    User::factory()->admin()->create();

    $this->actingAs($admin)->post('/superadmin/users/resign', ['role' => 'employer'])->assertRedirect();
    expect($admin->fresh()->role)->toBe('employer');
});
