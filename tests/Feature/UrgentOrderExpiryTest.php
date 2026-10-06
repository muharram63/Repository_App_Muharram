<?php

use App\Models\UrgentOrder;
use App\Models\UrgentOrderOffer;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Orders\OrderExpiry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/*
 * Автоматическое закрытие просроченных заявок и «Повторить» (этап 7).
 * Помощники feedOrder(), feedMaster(), offerPayload() — из tests/Pest.php.
 */

beforeEach(function () {
    Storage::fake('public');
    Cache::flush();
});

test('просроченная открытая заявка закрывается, заказчику — уведомление с «Повторить»', function () {
    $order = feedOrder(['deadline_at' => now()->subMinute()]);

    expect(app(OrderExpiry::class)->expireOverdue())->toBe(1);

    $order->refresh();
    expect($order->status)->toBe('expired')
        ->and($order->closed_at)->not->toBeNull();

    $note = UserNotification::where('user_id', $order->user_id)->sole();
    expect($note->title)->toBe('Срок заявки истёк')
        ->and($note->url)->toBe(route('public.orders.create', ['repeat' => $order->id], false));
});

test('заявки в срок, с исполнителем и уже закрытые не трогаются', function () {
    $fresh = feedOrder(['deadline_at' => now()->addHour()]);
    $assigned = feedOrder(['deadline_at' => now()->subHour(), 'status' => UrgentOrder::STATUS_ASSIGNED]);
    $cancelled = feedOrder(['deadline_at' => now()->subHour(), 'status' => UrgentOrder::STATUS_CANCELLED]);

    expect(app(OrderExpiry::class)->expireOverdue())->toBe(0)
        ->and($fresh->fresh()->status)->toBe('open')
        ->and($assigned->fresh()->status)->toBe('assigned')
        ->and($cancelled->fresh()->status)->toBe('cancelled')
        ->and(UserNotification::count())->toBe(0);
});

test('ждавшие отклики получают статус «срок истёк», а в уведомлении — число откликов', function () {
    $order = feedOrder();
    [$master] = feedMaster();
    $this->actingAs($master)->post(route('public.orders.offers.store', $order), offerPayload());
    UserNotification::query()->delete();

    $this->travel(2)->days();
    app(OrderExpiry::class)->expireOverdue();

    expect(UrgentOrderOffer::sole()->status)->toBe('expired')
        ->and(UrgentOrderOffer::sole()->statusLabel())->toBe('Срок заявки истёк')
        ->and(UserNotification::where('user_id', $order->user_id)->sole()->body)->toContain('1');
});

test('повторный запуск не шлёт второе уведомление', function () {
    $order = feedOrder(['deadline_at' => now()->subMinute()]);
    $expiry = app(OrderExpiry::class);

    $expiry->expireOverdue();
    expect($expiry->expire($order))->toBeFalse()
        ->and($expiry->expireOverdue())->toBe(0)
        ->and(UserNotification::where('user_id', $order->user_id)->count())->toBe(1);
});

test('команда планировщика закрывает просроченные и стоит в расписании', function () {
    feedOrder(['deadline_at' => now()->subMinute()]);

    $this->artisan('orders:expire')->expectsOutputToContain('1')->assertSuccessful();
    expect(UrgentOrder::sole()->status)->toBe('expired');

    $events = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
        ->map(fn ($event) => $event->command);
    expect($events->contains(fn ($command) => str_contains((string) $command, 'orders:expire')))->toBeTrue();
});

test('без планировщика заявка закрывается при открытии ленты', function () {
    $order = feedOrder(['title' => 'Просроченный кран', 'deadline_at' => now()->subMinute()]);

    $this->get(route('public.orders.index'))->assertOk()->assertDontSee('Просроченный кран');

    expect($order->fresh()->status)->toBe('expired');
});

test('без планировщика заявка закрывается при открытии «Моих заявок» и самой заявки', function () {
    $owner = User::factory()->employer()->create();
    $first = feedOrder(['owner' => $owner, 'deadline_at' => now()->subMinute()]);

    $this->actingAs($owner)->get(route('public.orders.mine'))->assertOk()->assertSee(__('Срок истёк'));
    expect($first->fresh()->status)->toBe('expired');

    // страница заявки закрывает её сама, даже если общий проход был минуту назад
    $second = feedOrder(['owner' => $owner, 'deadline_at' => now()->subMinute()]);
    $this->actingAs($owner)->get(route('public.orders.show', $second))
        ->assertOk()
        ->assertSee(__('Повторить заявку'));
    expect($second->fresh()->status)->toBe('expired');
});

test('на просроченную заявку откликнуться нельзя', function () {
    $order = feedOrder(['deadline_at' => now()->subMinute()]);
    [$master] = feedMaster();

    $this->actingAs($master)->post(route('public.orders.offers.store', $order), offerPayload())->assertSessionHas('error');
    expect(UrgentOrderOffer::count())->toBe(0);
});

// ==================== «Повторить» ====================

test('«Повторить» подставляет данные прошлой заявки', function () {
    $owner = User::factory()->employer()->create();
    $order = feedOrder(['owner' => $owner, 'title' => 'Течёт кран на кухне', 'budget' => 400,
        'contact_phone' => '+992 93 000 11 22', 'status' => UrgentOrder::STATUS_EXPIRED]);

    $this->actingAs($owner)->get(route('public.orders.create', ['repeat' => $order->id]))
        ->assertOk()
        ->assertSee(__('Данные прошлой заявки подставлены — проверьте их и выберите новый срок. Фото добавьте заново.'))
        ->assertSee('value="Течёт кран на кухне"', false)
        ->assertSee('value="400"', false)
        ->assertSee('+992 93 000 11 22')
        ->assertSee('ул. Ленина, 7, кв. 3')
        ->assertSee('Капает из-под смесителя.');
});

test('чужую заявку «повторить» нельзя — форма пустая', function () {
    $order = feedOrder(['title' => 'Чужой кран', 'status' => UrgentOrder::STATUS_EXPIRED]);

    $this->actingAs(User::factory()->employer()->create())
        ->get(route('public.orders.create', ['repeat' => $order->id]))
        ->assertOk()
        ->assertDontSee('Чужой кран')
        ->assertDontSee('ул. Ленина, 7');
});

test('повторённая заявка создаётся новой, со свежим сроком', function () {
    $owner = User::factory()->employer()->create();
    $old = feedOrder(['owner' => $owner, 'status' => UrgentOrder::STATUS_EXPIRED, 'deadline_at' => now()->subDay()]);

    $this->actingAs($owner)->post(route('public.orders.store'), [
        'title' => $old->title, 'work_category_id' => $old->work_category_id, 'description' => $old->description,
        'city_id' => $old->city_id, 'contact_phone' => '+992 90 000 00 00', 'deadline_option' => '1d',
    ])->assertSessionHasNoErrors();

    $new = UrgentOrder::latest('id')->first();
    expect($new->id)->not->toBe($old->id)
        ->and($new->status)->toBe('open')
        ->and($new->deadline_at->isFuture())->toBeTrue()
        ->and($old->fresh()->status)->toBe('expired');
});

test('кнопка «Повторить» есть у отменённой своей заявки', function () {
    $owner = User::factory()->employer()->create();
    $order = feedOrder(['owner' => $owner, 'status' => UrgentOrder::STATUS_CANCELLED]);

    $this->actingAs($owner)->get(route('public.orders.show', $order))
        ->assertSee(route('public.orders.create', ['repeat' => $order->id]), false);
});
