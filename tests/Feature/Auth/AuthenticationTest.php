<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    // после входа пользователя ведёт в кабинет его роли, а не на общий /dashboard
    $response->assertRedirect(route('applicant.dashboard', absolute: false));
});

test('a page asked for before login opens right after it', function () {
    $user = User::factory()->applicant()->create();
    makeApplicant($user);

    // гость постучался в закрытую страницу — адрес осел в сессии
    $this->get('/responses')->assertRedirect(route('login', absolute: false));

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/responses');
});

test('an address left in the session does not send a user to a foreign cabinet', function () {
    $user = User::factory()->applicant()->create();
    makeApplicant($user);

    // гость наткнулся на кабинет работодателя и остался на входе
    $this->get('/employer/vacancies')->assertRedirect(route('login', absolute: false));

    // вход соискателем: сохранённый адрес чужой, ведём в его собственный кабинет
    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('applicant.dashboard', absolute: false));

    // и адрес не всплывает при следующем переходе
    $this->get(route('applicant.dashboard', absolute: false))->assertOk();
});

test('an employer without a profile fills it in instead of the cabinet', function () {
    $user = User::factory()->employer()->create();

    $this->get('/employer/vacancies')->assertRedirect(route('login', absolute: false));

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('public.employers.create', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
