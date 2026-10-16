<?php

/*
 * Каркас админки: меню одним списком, текущий пункт выделен, в шапке —
 * название открытой страницы, на телефоне меню открывается кнопкой.
 */

use App\Models\Trainer\TrainerPayment;
use App\Models\User;

test('в шапке название открытой страницы, а не всегда «Пользователи»', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('superadmin.ai'))
        ->assertOk()
        ->assertSee('<b>ИИ-собеседования</b>', false)
        ->assertDontSee('<b>Пользователи</b>', false);

    $this->actingAs($admin)->get(route('superadmin.cities.index'))
        ->assertOk()
        ->assertSee('<b>Города</b>', false);
});

test('текущий пункт меню выделен, и только он', function () {
    $admin = User::factory()->admin()->create();

    $html = $this->actingAs($admin)->get(route('superadmin.complaints'))->assertOk()->getContent();

    expect(substr_count($html, 'class="nav-item active"'))->toBe(1)
        ->and($html)->toMatch('#class="nav-item active" href="[^"]*/superadmin/complaints"#');
});

test('на телефоне меню открывается кнопкой, шапка без жёсткой ширины', function () {
    $this->actingAs(User::factory()->admin()->create())->get(route('superadmin.dashboard'))
        ->assertOk()
        ->assertSee('data-nav-toggle', false)
        ->assertDontSee('177vh', false);
});

test('у пункта «Тренажёр» счётчик заявок «Я оплатил»', function () {
    $admin = User::factory()->admin()->create();
    $user = trainingUser();
    $package = App\Models\Trainer\TrainerPackage::create(['title' => '3 тренировки', 'attempts' => 3, 'price' => 30, 'active' => true]);
    $payment = app(App\Services\Trainer\TrainerPayments::class)->create($user, $package);
    app(App\Services\Trainer\TrainerPayments::class)->claim($payment, null);

    expect($payment->fresh()->status)->toBe(TrainerPayment::CLAIMED);

    $this->actingAs($admin)->get(route('superadmin.dashboard'))
        ->assertOk()
        ->assertSee('data-live="nav-trainer"', false);

    $this->actingAs($admin)->getJson(route('superadmin.live.counters', ['keys' => 'nav-trainer']))
        ->assertOk()
        ->assertJson(['nav-trainer' => 1]);
});
