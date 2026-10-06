<?php

use App\Models\MasterReview;
use App\Models\UrgentOrder;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Storage;

/*
 * Отзывы и оценки мастерам.
 * Помощники feedMaster(), feedOrder(), offerPayload() — из tests/Pest.php.
 */

beforeEach(function () {
    Storage::fake('public');
});

/** Выполненная заявка: [заказчик, заявка, мастер-пользователь, мастер-анкета]. */
function doneOrder(): array
{
    $owner = User::factory()->employer()->create(['name' => 'Сино Каримова']);
    $order = feedOrder(['owner' => $owner, 'title' => 'Замена смесителя']);
    [$masterUser, $master] = feedMaster(name: 'Мастер Фарход');

    test()->actingAs($masterUser)->post(route('public.orders.offers.store', $order), offerPayload());
    test()->actingAs($owner)->post(route('public.orders.choose', [$order, $order->offers()->sole()]));
    test()->actingAs($owner)->patch(route('public.orders.complete', $order));
    UserNotification::query()->delete();

    return [$owner, $order->fresh(), $masterUser, $master];
}

test('заказчик оставляет отзыв после выполнения, мастеру — уведомление', function () {
    [$owner, $order, $masterUser, $master] = doneOrder();

    $this->actingAs($owner)->post(route('public.orders.review', $order), ['rating' => 5, 'comment' => 'Пришёл вовремя, всё чисто.'])
        ->assertRedirect(route('public.orders.show', $order).'#review');

    $review = MasterReview::sole();
    expect($review->applicant_id)->toBe($master->id)
        ->and($review->user_id)->toBe($owner->id)
        ->and($review->rating)->toBe(5);

    $note = UserNotification::where('user_id', $masterUser->id)->sole();
    expect($note->title)->toBe('Новый отзыв: ★★★★★')
        ->and($note->body)->toBe('Пришёл вовремя, всё чисто.');
});

test('отзыв можно исправить — он остаётся одним', function () {
    [$owner, $order] = doneOrder();

    $this->actingAs($owner)->post(route('public.orders.review', $order), ['rating' => 2, 'comment' => 'Опоздал']);
    $this->actingAs($owner)->post(route('public.orders.review', $order), ['rating' => 4, 'comment' => 'Опоздал, но сделал хорошо']);

    expect(MasterReview::count())->toBe(1)
        ->and(MasterReview::sole()->rating)->toBe(4);
});

test('отзыв без выполненной работы оставить нельзя', function () {
    $owner = User::factory()->employer()->create();
    $order = feedOrder(['owner' => $owner]);
    [$masterUser] = feedMaster();
    $this->actingAs($masterUser)->post(route('public.orders.offers.store', $order), offerPayload());

    // открытая
    $this->actingAs($owner)->post(route('public.orders.review', $order), ['rating' => 5])->assertSessionHas('error');

    // исполнитель выбран, но работа не отмечена выполненной
    $this->actingAs($owner)->post(route('public.orders.choose', [$order, $order->offers()->sole()]));
    $this->actingAs($owner)->post(route('public.orders.review', $order), ['rating' => 5])->assertSessionHas('error');

    expect(MasterReview::count())->toBe(0);
});

test('чужой человек и сам мастер отзыв не оставят', function () {
    [, $order, $masterUser] = doneOrder();

    $this->actingAs(User::factory()->employer()->create())
        ->post(route('public.orders.review', $order), ['rating' => 1])->assertNotFound();
    $this->actingAs($masterUser)->post(route('public.orders.review', $order), ['rating' => 5])->assertNotFound();

    expect(MasterReview::count())->toBe(0);
});

test('после 30 дней отзыв уже не оставить и не исправить', function () {
    [$owner, $order] = doneOrder();

    $this->travel(MasterReview::EDIT_DAYS + 1)->days();

    $this->actingAs($owner)->post(route('public.orders.review', $order), ['rating' => 5])->assertSessionHas('error');
    expect(MasterReview::count())->toBe(0);
});

test('оценка — от одной до пяти звёзд', function () {
    [$owner, $order] = doneOrder();

    $this->actingAs($owner)->post(route('public.orders.review', $order), ['rating' => 0])->assertSessionHasErrors('rating');
    $this->actingAs($owner)->post(route('public.orders.review', $order), ['rating' => 6])->assertSessionHasErrors('rating');
    $this->actingAs($owner)->post(route('public.orders.review', $order), [])->assertSessionHasErrors('rating');
});

// ==================== где видно ====================

test('на странице выполненной заявки заказчик видит звёзды, потом — свой отзыв', function () {
    [$owner, $order] = doneOrder();

    $this->actingAs($owner)->get(route('public.orders.show', $order))
        ->assertSee(__('Оцените работу мастера'))
        ->assertSee('name="rating"', false);

    $this->actingAs($owner)->post(route('public.orders.review', $order), ['rating' => 4, 'comment' => 'Хорошая работа']);

    $this->actingAs($owner)->get(route('public.orders.show', $order))
        ->assertSee(__('Ваш отзыв о мастере'))
        ->assertSee('Хорошая работа')
        ->assertSee(__('Изменить отзыв'));
});

test('мастер видит отзыв о себе на странице заявки', function () {
    [$owner, $order, $masterUser] = doneOrder();
    $this->actingAs($owner)->post(route('public.orders.review', $order), ['rating' => 5, 'comment' => 'Супер']);

    $this->actingAs($masterUser)->get(route('public.orders.show', $order))
        ->assertSee(__('Отзыв заказчика'))
        ->assertSee('Супер')
        ->assertDontSee('name="rating"', false);
});

test('профиль мастера: средняя оценка и отзывы с сокращённым именем', function () {
    [$owner, $order, , $master] = doneOrder();
    $this->actingAs($owner)->post(route('public.orders.review', $order), ['rating' => 5, 'comment' => 'Пришёл через час после звонка']);

    // вторая выполненная заявка того же мастера — оценка 4
    $owner2 = User::factory()->employer()->create(['name' => 'Далер']);
    $order2 = feedOrder(['owner' => $owner2]);
    $this->actingAs($master->user)->post(route('public.orders.offers.store', $order2), offerPayload());
    $this->actingAs($owner2)->post(route('public.orders.choose', [$order2, $order2->offers()->sole()]));
    $this->actingAs($owner2)->patch(route('public.orders.complete', $order2));
    $this->actingAs($owner2)->post(route('public.orders.review', $order2), ['rating' => 4]);

    auth()->logout();
    $this->get(route('public.masters.show', $master))
        ->assertOk()
        ->assertSee(__('Отзывы заказчиков'))
        ->assertSee('4,5')
        ->assertSee(__('Отзывов: :count', ['count' => 2]))
        ->assertSee('Сино К.')
        ->assertDontSee('Каримова')
        ->assertSee('Пришёл через час после звонка')
        ->assertSeeInOrder(['Далер', 'Сино К.']);

    expect(MasterReview::summaryFor($master->id))->toBe(['avg' => 4.5, 'count' => 2]);
});

test('без отзывов в профиле — подсказка, а не «0 отзывов»', function () {
    [, $master] = feedMaster();

    $this->get(route('public.masters.show', $master))
        ->assertSee(__('Отзывов пока нет. Их оставляют заказчики после выполненной заявки.'))
        ->assertDontSee(__('Отзывов: :count', ['count' => 0]));
});

test('оценка видна рядом с откликом, под резюме и в каталоге', function () {
    [$owner, $order, $masterUser, $master] = doneOrder();
    $this->actingAs($owner)->post(route('public.orders.review', $order), ['rating' => 5]);
    $resume = makeResume($master);

    // новый заказчик выбирает мастера — видит его оценку
    $newOrder = feedOrder();
    $this->actingAs($masterUser)->post(route('public.orders.offers.store', $newOrder), offerPayload());
    $this->actingAs($newOrder->user)->get(route('public.orders.show', $newOrder))
        ->assertSee('5,0')
        ->assertSee(route('public.masters.show', $master).'#reviews', false);

    auth()->logout();
    $this->get(route('public.resumes.show', $resume))->assertSee('5,0');
    $this->get(route('public.resumes.index'))->assertSee(__('Отзывов: :count', ['count' => 1]));
});

test('имя автора сокращается до имени и инициала', function () {
    $review = new MasterReview();
    $review->setRelation('author', new User(['name' => 'Сино Каримова']));
    expect($review->authorName())->toBe('Сино К.');

    $review->setRelation('author', new User(['name' => 'Далер']));
    expect($review->authorName())->toBe('Далер');

    expect(MasterReview::stars(4))->toBe('★★★★☆')
        ->and(MasterReview::formatAverage(4.666))->toBe('4,7');
});
