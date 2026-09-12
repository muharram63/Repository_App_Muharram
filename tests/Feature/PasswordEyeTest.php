<?php

use App\Models\User;

/*
 * Глазок у полей пароля. Партиал подключается один раз на страницу и сам
 * навешивает кнопку на каждое input[type="password"], поэтому проверяем
 * не разметку кнопок, а то, что партиал попал на страницу и поля на месте.
 */

test('на сбросе пароля есть глазок у обоих полей', function () {
    $html = $this->get('/reset-password/token?email=user@example.com')->assertOk()->getContent();

    expect($html)->toContain('pass-eye')
        ->and(substr_count($html, 'name="password"'))->toBe(1)
        ->and(substr_count($html, 'name="password_confirmation"'))->toBe(1);
});

test('на подтверждении пароля есть глазок', function () {
    $user = User::factory()->applicant()->create();

    $html = $this->actingAs($user)->get('/confirm-password')->assertOk()->getContent();

    expect($html)->toContain('pass-eye');
});

test('в настройках соискателя глазок у всех полей пароля', function () {
    $user = User::factory()->applicant()->create();
    makeApplicant($user);

    $html = $this->actingAs($user)->get('/applicant/settings')->assertOk()->getContent();

    expect($html)->toContain('pass-eye');
});

test('в настройках работодателя глазок у всех полей пароля', function () {
    $user = User::factory()->employer()->create();
    makeEmployer($user);

    $html = $this->actingAs($user)->get('/employer/settings')->assertOk()->getContent();

    expect($html)->toContain('pass-eye');
});
