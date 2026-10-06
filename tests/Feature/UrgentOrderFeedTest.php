<?php

use App\Models\City;
use App\Models\PortfolioWork;
use App\Models\UrgentOrder;
use App\Models\UrgentOrderOffer;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\WorkCategory;
use App\Services\Orders\MasterMatcher;
use Illuminate\Support\Facades\Storage;

/*
 * Лента срочных заказов, отклики мастеров, уведомления (этап 5).
 */

beforeEach(function () {
    Storage::fake('public');
});

// ==================== лента ====================

test('лента открыта гостям, срочные сверху, адреса нет', function () {
    feedOrder(['title' => 'Покраска за три дня', 'deadline_option' => '3d', 'deadline_at' => now()->addHours(5)]);
    feedOrder(['title' => 'Срочно сегодня', 'deadline_option' => 'today', 'deadline_at' => now()->addHours(10)]);
    feedOrder(['title' => 'В течение суток', 'deadline_option' => '1d', 'deadline_at' => now()->addHours(20)]);

    $this->get(route('public.orders.index'))
        ->assertOk()
        // срочные (сегодня, сутки) — первыми, хотя у «трёх дней» срок ближе
        ->assertSeeInOrder(['Срочно сегодня', 'В течение суток', 'Покраска за три дня'])
        ->assertDontSee('ул. Ленина, 7')
        ->assertSee('18 мкр');
});

test('в ленте нет закрытых и просроченных заявок', function () {
    feedOrder(['title' => 'Открытая']);
    feedOrder(['title' => 'Отменённая', 'status' => UrgentOrder::STATUS_CANCELLED]);
    feedOrder(['title' => 'Срок вышел', 'deadline_at' => now()->subHour()]);

    $this->get(route('public.orders.index'))
        ->assertSee('Открытая')
        ->assertDontSee('Отменённая')
        ->assertDontSee('Срок вышел');
});

test('фильтры по виду работ и городу', function () {
    feedOrder(['title' => 'Кран в Худжанде']);
    feedOrder(['title' => 'Розетка в Худжанде', 'work_category_id' => categoryId('electrical')]);
    feedOrder(['title' => 'Кран в Кулобе', 'city_id' => feedCity('Кулоб')->id]);

    $this->get(route('public.orders.index', ['category' => 'electrical']))
        ->assertSee('Розетка в Худжанде')->assertDontSee('Кран в Худжанде');

    $this->get(route('public.orders.index', ['city' => feedCity('Кулоб')->id]))
        ->assertSee('Кран в Кулобе')->assertDontSee('Кран в Худжанде');
});

test('«Подходящие мне» — по видам работ из портфолио мастера', function () {
    [$user] = feedMaster('electrical');
    feedOrder(['title' => 'Кран течёт']);
    feedOrder(['title' => 'Розетка искрит', 'work_category_id' => categoryId('electrical')]);

    $this->actingAs($user)->get(route('public.orders.index', ['mine' => 1]))
        ->assertSee('Розетка искрит')
        ->assertDontSee('Кран течёт');
});

test('соискателю без портфолио лента подсказывает его добавить', function () {
    $user = User::factory()->applicant()->create();
    makeApplicant($user);

    $this->actingAs($user)->get(route('public.orders.index'))
        ->assertSee(__('Чтобы откликаться на заказы, добавьте в портфолио хотя бы одну работу — заказчики выбирают по фото.'));
});

test('над вакансиями — полоса «Срочно нужен мастер» только со срочными', function () {
    feedOrder(['title' => 'Срочный кран', 'deadline_option' => 'today']);
    feedOrder(['title' => 'Неспешная покраска', 'deadline_option' => '3d', 'deadline_at' => now()->addDays(3)]);

    $this->get(route('public.vacancies.index'))
        ->assertOk()
        ->assertSee(__('Срочно нужен мастер'))
        ->assertSee('Срочный кран')
        ->assertDontSee('Неспешная покраска');
});

test('без срочных заявок полосы над вакансиями нет', function () {
    $this->get(route('public.vacancies.index'))->assertOk()->assertDontSee(__('Срочно нужен мастер'));
});

// ==================== отклик ====================

test('мастер откликается, заказчик получает уведомление', function () {
    [$user, $applicant] = feedMaster();
    $order = feedOrder();

    $this->actingAs($user)->post(route('public.orders.offers.store', $order), offerPayload())
        ->assertRedirect(route('public.orders.show', $order));

    $offer = UrgentOrderOffer::sole();
    expect($offer->applicant_id)->toBe($applicant->id)
        ->and($offer->status)->toBe('pending')
        ->and($offer->price)->toBe(250);

    $note = UserNotification::where('user_id', $order->user_id)->sole();
    expect($note->type)->toBe('order')
        ->and($note->url)->toBe(route('public.orders.show', $order));
});

test('уведомления о нескольких откликах склеиваются в одно', function () {
    $order = feedOrder();
    foreach (['Первый', 'Второй'] as $name) {
        [$user] = feedMaster(name: $name);
        $this->actingAs($user)->post(route('public.orders.offers.store', $order), offerPayload());
    }

    expect(UserNotification::where('user_id', $order->user_id)->count())->toBe(1)
        ->and(UserNotification::where('user_id', $order->user_id)->sole()->body)->toContain('2');
});

test('без портфолио, гостем, на свою и закрытую заявку откликнуться нельзя', function () {
    $order = feedOrder();

    $noPortfolio = User::factory()->applicant()->create();
    makeApplicant($noPortfolio);
    $this->actingAs($noPortfolio)->post(route('public.orders.offers.store', $order), offerPayload())->assertSessionHas('error');

    $employer = User::factory()->employer()->create();
    $this->actingAs($employer)->post(route('public.orders.offers.store', $order), offerPayload())->assertSessionHas('error');

    [$master] = feedMaster();
    $own = feedOrder(['owner' => $master]);
    $this->actingAs($master)->post(route('public.orders.offers.store', $own), offerPayload())->assertSessionHas('error');

    $closed = feedOrder(['status' => UrgentOrder::STATUS_CANCELLED]);
    $this->actingAs($master)->post(route('public.orders.offers.store', $closed), offerPayload())->assertSessionHas('error');

    $late = feedOrder(['deadline_at' => now()->subMinute()]);
    $this->actingAs($master)->post(route('public.orders.offers.store', $late), offerPayload())->assertSessionHas('error');

    auth()->logout();
    $this->post(route('public.orders.offers.store', $order), offerPayload())->assertRedirect(route('login'));

    expect(UrgentOrderOffer::count())->toBe(0);
});

test('второй отклик на ту же заявку не создаётся', function () {
    [$user] = feedMaster();
    $order = feedOrder();

    $this->actingAs($user)->post(route('public.orders.offers.store', $order), offerPayload());
    $this->actingAs($user)->post(route('public.orders.offers.store', $order), offerPayload(['price' => 100]))
        ->assertSessionHas('error');

    expect(UrgentOrderOffer::count())->toBe(1)->and(UrgentOrderOffer::sole()->price)->toBe(250);
});

test('отклик проверяется: сообщение, цена, срок', function () {
    [$user] = feedMaster();
    $order = feedOrder();

    $this->actingAs($user)->post(route('public.orders.offers.store', $order), ['message' => '', 'price' => -1, 'can_start' => 'never'])
        ->assertSessionHasErrors(['message', 'price', 'can_start']);
});

test('отклик можно отозвать и вернуть', function () {
    [$user] = feedMaster();
    $order = feedOrder();
    $this->actingAs($user)->post(route('public.orders.offers.store', $order), offerPayload());

    $this->actingAs($user)->delete(route('public.orders.offers.withdraw', $order))->assertRedirect();
    expect(UrgentOrderOffer::sole()->status)->toBe('withdrawn');

    $this->actingAs($user)->post(route('public.orders.offers.store', $order), offerPayload(['price' => 200]))
        ->assertSessionHasNoErrors();
    expect(UrgentOrderOffer::count())->toBe(1)
        ->and(UrgentOrderOffer::sole()->status)->toBe('pending')
        ->and(UrgentOrderOffer::sole()->price)->toBe(200);
});

test('чужой отклик не отозвать', function () {
    [$user] = feedMaster();
    $order = feedOrder();
    $this->actingAs($user)->post(route('public.orders.offers.store', $order), offerPayload());

    [$other] = feedMaster(name: 'Другой');
    $this->actingAs($other)->delete(route('public.orders.offers.withdraw', $order))->assertNotFound();

    expect(UrgentOrderOffer::sole()->status)->toBe('pending');
});

// ==================== что кто видит ====================

test('заказчик видит отклики с ценой и работами мастера', function () {
    [$user] = feedMaster(name: 'Мастер Фарход');
    $order = feedOrder();
    $this->actingAs($user)->post(route('public.orders.offers.store', $order), offerPayload());

    $this->actingAs($order->user)->get(route('public.orders.show', $order))
        ->assertOk()
        ->assertSee('Мастер Фарход')
        ->assertSee('250')
        ->assertSee('Приеду с запчастями, опыт 7 лет.')
        ->assertSee('Замена смесителя')
        ->assertSee(route('public.masters.show', $user->applicant), false);
});

test('мастер видит свой отклик, но не чужие', function () {
    [$first] = feedMaster(name: 'Первый');
    [$second] = feedMaster(name: 'Второй');
    $order = feedOrder();
    $this->actingAs($first)->post(route('public.orders.offers.store', $order), offerPayload(['message' => 'Мой текст отклика']));
    $this->actingAs($second)->post(route('public.orders.offers.store', $order), offerPayload(['message' => 'Чужой текст отклика']));

    $this->actingAs($first)->get(route('public.orders.show', $order))
        ->assertSee(__('Ваш отклик'))
        ->assertSee('Мой текст отклика')
        ->assertDontSee('Чужой текст отклика')
        ->assertSee(__('Отозвать отклик'));
});

test('мастер видит форму отклика, соискатель без портфолио — подсказку', function () {
    $order = feedOrder();
    [$master] = feedMaster();

    $this->actingAs($master)->get(route('public.orders.show', $order))->assertSee(__('Отправить отклик'));

    $plain = User::factory()->applicant()->create();
    makeApplicant($plain);
    $this->actingAs($plain)->get(route('public.orders.show', $order))
        ->assertDontSee(__('Отправить отклик'))
        ->assertSee(route('applicant.portfolio.create'), false);
});

test('закрытую заявку мастер, который откликался, всё ещё видит', function () {
    [$master] = feedMaster();
    $order = feedOrder();
    $this->actingAs($master)->post(route('public.orders.offers.store', $order), offerPayload());
    $order->update(['status' => UrgentOrder::STATUS_EXPIRED]);

    $this->actingAs($master)->get(route('public.orders.show', $order))->assertOk();
});

test('«Мои отклики» в ленте', function () {
    [$master] = feedMaster();
    $mine = feedOrder(['title' => 'Откликался сюда']);
    feedOrder(['title' => 'Сюда не откликался']);
    $this->actingAs($master)->post(route('public.orders.offers.store', $mine), offerPayload());

    $this->actingAs($master)->get(route('public.orders.index', ['offered' => 1]))
        ->assertSee('Откликался сюда')
        ->assertDontSee('Сюда не откликался');
});

// ==================== уведомления мастерам ====================

test('название города из справочника', function () {
    expect(MasterMatcher::cityName(feedCity('Согдийская область, город Худжанд')))->toBe('Худжанд')
        ->and(MasterMatcher::cityName(feedCity('Кулоб')))->toBe('Кулоб')
        ->and(MasterMatcher::cityName(null))->toBeNull();
});

test('о новой заявке узнают мастера нужного вида и города', function () {
    [$plumberHere] = feedMaster('plumbing', 'г. Худжанд', 'Сантехник здесь');
    [$plumberNoCity] = feedMaster('plumbing', null, 'Сантехник без города');
    [$plumberThere] = feedMaster('plumbing', 'Душанбе', 'Сантехник далеко');
    [$electricianHere] = feedMaster('electrical', 'Худжанд', 'Электрик здесь');
    $owner = User::factory()->employer()->create();

    $this->actingAs($owner)->post(route('public.orders.store'), [
        'title' => 'Течёт кран',
        'work_category_id' => categoryId('plumbing'),
        'description' => 'Капает из-под смесителя на кухне.',
        'city_id' => feedCity()->id,
        'contact_phone' => '+992 90 000 00 00',
        'deadline_option' => 'today',
    ])->assertSessionHasNoErrors();

    $notified = UserNotification::where('type', 'order')->pluck('user_id');

    expect($notified)->toContain($plumberHere->id)
        ->and($notified)->toContain($plumberNoCity->id)
        ->and($notified)->not->toContain($plumberThere->id)
        ->and($notified)->not->toContain($electricianHere->id)
        ->and($notified)->not->toContain($owner->id);

    expect(UserNotification::where('user_id', $plumberHere->id)->sole()->url)
        ->toBe(route('public.orders.show', UrgentOrder::sole()));
});

test('заблокированному мастеру уведомление не идёт', function () {
    [$blocked] = feedMaster();
    $blocked->forceFill(['status' => 'blocked'])->save();

    expect(app(MasterMatcher::class)->mastersFor(feedOrder()))->toHaveCount(0);
});

test('рассылка ограничена', function () {
    expect(MasterMatcher::NOTIFY_LIMIT)->toBeLessThanOrEqual(50);
});

test('«Срочные заказы» в шапке, «Заказы для мастеров» — в кабинете соискателя', function () {
    $this->get(route('public.home'))->assertSee(route('public.orders.index'), false);

    [$master] = feedMaster();
    $this->actingAs($master)->get(route('applicant.portfolio.index'))
        ->assertSee(route('public.orders.index', ['mine' => 1]), false);
});
