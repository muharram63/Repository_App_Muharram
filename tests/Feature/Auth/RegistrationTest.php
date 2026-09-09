<?php

use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'applicant',
    ]);

    $this->assertAuthenticated();
    // сразу после регистрации соискателя ведут заполнять анкету
    $response->assertRedirect(route('public.applicants.create', absolute: false));

    // роль вне массового заполнения, но выставиться она обязана
    expect(User::where('email', 'test@example.com')->value('role'))->toBe('applicant');
});

test('employers are sent to the company form after registration', function () {
    $response = $this->post('/register', [
        'name' => 'Test Company',
        'email' => 'company@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'employer',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('public.employers.create', absolute: false));

    expect(User::where('email', 'company@example.com')->value('role'))->toBe('employer');
});

test('registration requires a known role', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'admin@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'admin',
    ]);

    $response->assertSessionHasErrors('role');
    $this->assertGuest();
});
