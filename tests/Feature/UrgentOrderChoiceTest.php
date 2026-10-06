<?php

use App\Models\UrgentOrder;
use App\Models\UrgentOrderOffer;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Storage;

/*
 * Выбор исполнителя, обмен контактами, «Выполнено», отмена (этап 6).
 * Помощники feedMaster(), feedOrder(), offerPayload() — из UrgentOrderFeedTest.
 */

beforeEach(function () {
    Storage::fake('public');
});

/** Заявка с двумя откликами: [заказчик, заявка, [мастер1, отклик1], [мастер2, отклик2]]. */
function orderWithOffers(): array
{
    $owner = User::factory()->employer()->create(['name' => 'Заказчик Сино']);
    $order = feedOrder(['owner' => $owner, 'contact_phone' => '+992 93 555 66 77']);

    $pairs = [];
    foreach (['Мастер Первый' => '+992 90 111 11 11', 'Мастер Второй' => '+992 90 222 22 22'] as $name => $phone) {
        [$user, $applicant] = feedMaster(name: $name);
        $applicant->update(['phone' => $phone]);
        test()->actingAs($user)->post(route('public.orders.offers.store', $order), offerPayload());
        $pairs[] = [$user, UrgentOrderOffer::where('applicant_id', $applicant->id)->sole()];
    }

    UserNotification::query()->delete();

    return [$owner, $order->fresh(), $pairs[0], $pairs[1]];
}

// ==================== выбор ====================

test('заказчик выбирает исполнителя: статусы и уведомления', function () {
    [$owner, $order, [$first, $firstOffer], [$second, $secondOffer]] = orderWithOffers();

    $this->actingAs($owner)->post(route('public.orders.choose', [$order, $firstOffer]))
        ->assertRedirect(route('public.orders.show', $order));

    $order->refresh();
    expect($order->status)->toBe('assigned')
        ->and($order->chosen_offer_id)->toBe($firstOffer->id)
        ->and($firstOffer->fresh()->status)->toBe('chosen')
        ->and($secondOffer->fresh()->status)->toBe('declined');

    expect(UserNotification::where('user_id', $first->id)->sole()->title)->toBe('Вас выбрали исполнителем')
        ->and(UserNotification::where('user_id', $second->id)->sole()->title)->toBe('Заказчик выбрал другого мастера');
});

test('второго исполнителя выбрать нельзя', function () {
    [$owner, $order, [, $firstOffer], [, $secondOffer]] = orderWithOffers();

    $this->actingAs($owner)->post(route('public.orders.choose', [$order, $firstOffer]));
    $this->actingAs($owner)->post(route('public.orders.choose', [$order, $secondOffer]))->assertSessionHas('error');

    expect($order->fresh()->chosen_offer_id)->toBe($firstOffer->id)
        ->and($secondOffer->fresh()->status)->toBe('declined');
});

test('чужой заказчик и чужой отклик — нельзя', function () {
    [, $order, [, $firstOffer]] = orderWithOffers();

    $this->actingAs(User::factory()->employer()->create())
        ->post(route('public.orders.choose', [$order, $firstOffer]))->assertNotFound();

    [$owner2, $order2] = orderWithOffers();
    // отклик с другой заявки через адрес своей
    $this->actingAs($owner2)->post(route('public.orders.choose', [$order2, $firstOffer]))->assertNotFound();

    expect($order->fresh()->status)->toBe('open');
});

test('отозванный отклик выбрать нельзя', function () {
    [$owner, $order, [$first, $firstOffer]] = orderWithOffers();
    $this->actingAs($first)->delete(route('public.orders.offers.withdraw', $order));

    $this->actingAs($owner)->post(route('public.orders.choose', [$order, $firstOffer]))->assertSessionHas('error');
    expect($order->fresh()->status)->toBe('open');
});

// ==================== контакты ====================

test('выбранный мастер видит адрес и телефон заказчика, остальные — нет', function () {
    [$owner, $order, [$first, $firstOffer], [$second]] = orderWithOffers();

    // до выбора адрес и телефон не видит никто из мастеров
    $this->actingAs($first)->get(route('public.orders.show', $order))
        ->assertDontSee('ул. Ленина, 7')->assertDontSee('+992 93 555 66 77');

    $this->actingAs($owner)->post(route('public.orders.choose', [$order, $firstOffer]));

    $this->actingAs($first)->get(route('public.orders.show', $order))
        ->assertOk()
        ->assertSee(__('Вас выбрали исполнителем'))
        ->assertSee('ул. Ленина, 7, кв. 3')
        ->assertSee('+992 93 555 66 77')
        ->assertSee('tel:+992935556677', false);

    $this->actingAs($second)->get(route('public.orders.show', $order))
        ->assertOk()
        ->assertDontSee('ул. Ленина, 7')
        ->assertDontSee('+992 93 555 66 77')
        ->assertSee(__('Выбран другой мастер'));
});

test('заказчик видит телефон выбранного мастера', function () {
    [$owner, $order, [, $firstOffer]] = orderWithOffers();

    $this->actingAs($owner)->get(route('public.orders.show', $order))
        ->assertSee(__('Выбрать исполнителем'))
        ->assertDontSee('+992 90 111 11 11');

    $this->actingAs($owner)->post(route('public.orders.choose', [$order, $firstOffer]));

    $this->actingAs($owner)->get(route('public.orders.show', $order))
        ->assertSee('🤝 '.__('Исполнитель'), false)
        ->assertSee('+992 90 111 11 11')
        ->assertSee(__('Не выбран'))
        ->assertDontSee(__('Выбрать исполнителем'));
});

test('телефон для связи обязателен и проверяется', function () {
    $owner = User::factory()->employer()->create();
    $payload = [
        'title' => 'Течёт кран', 'work_category_id' => categoryId('plumbing'),
        'description' => 'Капает из-под смесителя.', 'city_id' => feedCity()->id, 'deadline_option' => 'today',
    ];

    $this->actingAs($owner)->post(route('public.orders.store'), $payload)->assertSessionHasErrors('contact_phone');
    $this->actingAs($owner)->post(route('public.orders.store'), $payload + ['contact_phone' => 'позвоните мне'])
        ->assertSessionHasErrors('contact_phone');
    $this->actingAs($owner)->post(route('public.orders.store'), $payload + ['contact_phone' => '+992 (90) 123-45-67'])
        ->assertSessionHasNoErrors();
});

test('в форме телефон подставлен из анкеты', function () {
    $owner = User::factory()->employer()->create();
    makeEmployer($owner)->update(['phone' => '+992 44 600 00 00']);

    $this->actingAs($owner)->get(route('public.orders.create'))->assertSee('+992 44 600 00 00');
});

test('у старой заявки без телефона берётся номер из анкеты заказчика', function () {
    $owner = User::factory()->employer()->create();
    makeEmployer($owner)->update(['phone' => '+992 44 600 00 00']);
    $order = feedOrder(['owner' => $owner]);

    expect($order->contactPhone())->toBe('+992 44 600 00 00');
});

// ==================== выполнено ====================

test('заказчик отмечает «Выполнено», мастеру — спасибо', function () {
    [$owner, $order, [$first, $firstOffer]] = orderWithOffers();
    $this->actingAs($owner)->post(route('public.orders.choose', [$order, $firstOffer]));
    UserNotification::query()->delete();

    $this->actingAs($owner)->patch(route('public.orders.complete', $order))->assertRedirect();

    expect($order->fresh()->status)->toBe('done')
        ->and($order->fresh()->closed_at)->not->toBeNull()
        ->and(UserNotification::where('user_id', $first->id)->sole()->title)->toBe('Заказ отмечен выполненным');
});

test('«Выполнено» — только при выбранном исполнителе и только заказчиком', function () {
    [$owner, $order, [$first, $firstOffer]] = orderWithOffers();

    $this->actingAs($owner)->patch(route('public.orders.complete', $order))->assertSessionHas('error');
    expect($order->fresh()->status)->toBe('open');

    $this->actingAs($owner)->post(route('public.orders.choose', [$order, $firstOffer]));
    $this->actingAs($first)->patch(route('public.orders.complete', $order))->assertNotFound();
    expect($order->fresh()->status)->toBe('assigned');
});

// ==================== отмена ====================

test('отмена с выбранным исполнителем — ему уведомление', function () {
    [$owner, $order, [$first, $firstOffer]] = orderWithOffers();
    $this->actingAs($owner)->post(route('public.orders.choose', [$order, $firstOffer]));
    UserNotification::query()->delete();

    $this->actingAs($owner)->patch(route('public.orders.cancel', $order))->assertRedirect(route('public.orders.mine'));

    expect($order->fresh()->status)->toBe('cancelled')
        ->and($firstOffer->fresh()->status)->toBe('cancelled')
        ->and(UserNotification::where('user_id', $first->id)->sole()->title)->toBe('Заказчик отменил заявку');
});

test('отмена открытой заявки сообщает ждущим мастерам', function () {
    [$owner, $order, [$first], [$second]] = orderWithOffers();

    $this->actingAs($owner)->patch(route('public.orders.cancel', $order));

    expect(UserNotification::whereIn('user_id', [$first->id, $second->id])->where('title', 'Заказчик отменил заявку')->count())->toBe(2);
});

test('выполненную заявку не отменить', function () {
    [$owner, $order, [, $firstOffer]] = orderWithOffers();
    $this->actingAs($owner)->post(route('public.orders.choose', [$order, $firstOffer]));
    $this->actingAs($owner)->patch(route('public.orders.complete', $order));

    $this->actingAs($owner)->patch(route('public.orders.cancel', $order))->assertSessionHas('error');
    expect($order->fresh()->status)->toBe('done');
});

test('мастер, которого выбрали, видит заявку и после выполнения, но без телефона', function () {
    [$owner, $order, [$first, $firstOffer]] = orderWithOffers();
    $this->actingAs($owner)->post(route('public.orders.choose', [$order, $firstOffer]));
    $this->actingAs($owner)->patch(route('public.orders.complete', $order));

    $this->actingAs($first)->get(route('public.orders.show', $order))
        ->assertOk()
        ->assertSee(__('Заказ выполнен — спасибо!'));
});

// ==================== мастер не пришёл — выбрать другого ====================

test('смена исполнителя: заявка снова открыта, прежний теряет адрес, отказанные возвращаются', function () {
    [$owner, $order, [$first, $firstOffer], [$second, $secondOffer]] = orderWithOffers();
    $this->actingAs($owner)->post(route('public.orders.choose', [$order, $firstOffer]));
    UserNotification::query()->delete();

    $this->travel(2)->days();
    $this->actingAs($owner)->patch(route('public.orders.reassign', $order), ['deadline_option' => 'today'])
        ->assertRedirect(route('public.orders.show', $order));

    $order->refresh();
    expect($order->status)->toBe('open')
        ->and($order->chosen_offer_id)->toBeNull()
        ->and($order->deadline_option)->toBe('today')
        ->and($order->deadline_at->isFuture())->toBeTrue()
        ->and($firstOffer->fresh()->status)->toBe('dropped')
        ->and($secondOffer->fresh()->status)->toBe('pending');

    expect(UserNotification::where('user_id', $first->id)->sole()->title)->toBe('Заказчик отменил выбор исполнителя')
        ->and(UserNotification::where('user_id', $second->id)->sole()->title)->toBe('Ваш отклик снова рассматривают');

    // прежний исполнитель адреса и телефона больше не видит
    $this->actingAs($first)->get(route('public.orders.show', $order))
        ->assertOk()
        ->assertDontSee('ул. Ленина, 7')
        ->assertDontSee('+992 93 555 66 77')
        ->assertSee(__('Заказчик отменил выбор'));

    // второго можно выбрать сразу
    $this->actingAs($owner)->post(route('public.orders.choose', [$order, $secondOffer]))->assertSessionHasNoErrors();
    expect($order->fresh()->chosen_offer_id)->toBe($secondOffer->id);
});

test('сменить исполнителя можно только заказчику и только пока работа не выполнена', function () {
    [$owner, $order, [$first, $firstOffer]] = orderWithOffers();

    // ещё никто не выбран
    $this->actingAs($owner)->patch(route('public.orders.reassign', $order), ['deadline_option' => 'today'])->assertSessionHas('error');

    $this->actingAs($owner)->post(route('public.orders.choose', [$order, $firstOffer]));
    $this->actingAs($first)->patch(route('public.orders.reassign', $order), ['deadline_option' => 'today'])->assertNotFound();
    $this->actingAs($owner)->patch(route('public.orders.reassign', $order), ['deadline_option' => 'never'])->assertSessionHasErrors('deadline_option');

    $this->actingAs($owner)->patch(route('public.orders.complete', $order));
    $this->actingAs($owner)->patch(route('public.orders.reassign', $order), ['deadline_option' => 'today'])->assertSessionHas('error');

    expect($order->fresh()->status)->toBe('done');
});

test('у выбранного исполнителя есть кнопка «Выбрать другого мастера»', function () {
    [$owner, $order, [, $firstOffer]] = orderWithOffers();
    $this->actingAs($owner)->post(route('public.orders.choose', [$order, $firstOffer]));

    $this->actingAs($owner)->get(route('public.orders.show', $order))
        ->assertSee(__('Мастер не пришёл или не отвечает?'))
        ->assertSee(route('public.orders.reassign', $order), false);
});
