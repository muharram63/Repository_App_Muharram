<?php

/*
 * Вход и регистрация через Google. Сам Google подменяется: проверяем, что
 * делает сайт с тем, что Google вернул.
 */

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    config([
        'services.google.client_id' => 'test-client',
        'services.google.client_secret' => 'test-secret',
        'services.google.redirect' => '/auth/google/callback',
    ]);
});

/** Подменить ответ Google на возврате. */
function fakeGoogle(string $id, string $email, bool $verified = true, string $name = 'Фарангис Каримова'): void
{
    $google = (new SocialiteUser)->map(['id' => $id, 'name' => $name, 'email' => $email]);
    $google->setRaw(['email_verified' => $verified]);

    Socialite::shouldReceive('driver->user')->andReturn($google);
}

test('ссылка «Зарегистрируйтесь бесплатно» ведёт на страницу регистрации, а не на файл шаблона', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('href="'.route('register').'"', false)
        ->assertDontSee('register.blade.php');
});

test('кнопка «Google» на входе и регистрации ведёт на вход через Google', function () {
    $this->get(route('login'))->assertOk()->assertSee('href="'.route('auth.google').'"', false);
    $this->get(route('register'))->assertOk()->assertSee('href="'.route('auth.google').'"', false);
});

test('кнопка уводит в Google', function () {
    $this->get(route('auth.google'))
        ->assertRedirect()
        ->assertRedirectContains('accounts.google.com');
});

test('без ключей Google — понятная ошибка, а не падение', function () {
    config(['services.google.client_id' => null]);

    $this->get(route('auth.google'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');
});

test('уже связанный аккаунт входит по номеру Google', function () {
    $user = User::factory()->applicant()->create(['email' => 'old@example.com']);
    $user->forceFill(['google_id' => 'g-1'])->save();
    fakeGoogle('g-1', 'new-address@gmail.com');

    $this->get(route('auth.google.callback'))->assertRedirect(route('applicant.dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('аккаунт с той же подтверждённой почтой связывается с Google и входит', function () {
    $user = User::factory()->applicant()->create(['email' => 'farangis@gmail.com']);
    fakeGoogle('g-2', 'Farangis@Gmail.com');

    $this->get(route('auth.google.callback'))->assertRedirect(route('applicant.dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->google_id)->toBe('g-2');
});

test('неподтверждённая Google почта не открывает чужой аккаунт', function () {
    $user = User::factory()->applicant()->create(['email' => 'victim@gmail.com']);
    fakeGoogle('g-3', 'victim@gmail.com', verified: false);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    expect($user->fresh()->google_id)->toBeNull();
});

test('заблокированного не пускает и через Google', function () {
    $user = User::factory()->applicant()->create(['email' => 'blocked@gmail.com']);
    $user->forceFill(['status' => 'blocked'])->save();
    fakeGoogle('g-4', 'blocked@gmail.com');

    $this->get(route('auth.google.callback'))->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('новый пользователь со страницы регистрации создаётся сразу с выбранной ролью', function () {
    $this->get(route('auth.google', ['role' => 'employer']))->assertRedirect();
    fakeGoogle('g-5', 'company@gmail.com', name: 'ООО Сомон');

    $this->get(route('auth.google.callback'))->assertRedirect(route('public.employers.create'));

    $user = User::where('email', 'company@gmail.com')->sole();
    expect($user->role)->toBe('employer')
        ->and($user->google_id)->toBe('g-5')
        ->and($user->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

test('новый пользователь со страницы входа сначала выбирает роль', function () {
    $this->get(route('auth.google'))->assertRedirect();
    fakeGoogle('g-6', 'seeker@gmail.com');

    $this->get(route('auth.google.callback'))->assertRedirect(route('auth.google.role'));
    $this->assertGuest();

    $this->get(route('auth.google.role'))
        ->assertOk()
        ->assertSee('seeker@gmail.com');

    // админа так получить нельзя
    $this->post(route('auth.google.role.store'), ['role' => 'admin'])->assertSessionHasErrors('role');

    $this->post(route('auth.google.role.store'), ['role' => 'applicant'])
        ->assertRedirect(route('public.applicants.create'));

    $user = User::where('email', 'seeker@gmail.com')->sole();
    expect($user->role)->toBe('applicant')->and($user->google_id)->toBe('g-6');
    $this->assertAuthenticatedAs($user);
});

test('отмена у Google — возврат ко входу с сообщением', function () {
    Socialite::shouldReceive('driver->user')->andThrow(new Laravel\Socialite\Two\InvalidStateException);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');
});

test('страница входа показывает настоящую причину, а не всегда «неверный пароль»', function () {
    config(['services.google.client_id' => null]);

    $this->followingRedirects()->get(route('auth.google'))
        ->assertSee('Вход через Google пока не настроен')
        ->assertDontSee('Неверный e-mail или пароль');

    // неверный пароль — по-прежнему своя понятная фраза
    $user = User::factory()->applicant()->create();
    $this->followingRedirects()->from(route('login'))
        ->post(route('login'), ['email' => $user->email, 'password' => 'не-тот-пароль'])
        ->assertSee('Неверный e-mail или пароль');
});
