<?php

use App\Models\City;
use App\Models\UrgentOrder;
use App\Models\User;
use App\Models\WorkCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/*
 * Срочные заявки: создание, «Мои заявки», отмена (этап 4).
 */

beforeEach(function () {
    Storage::fake('public');
});

function orderCity(): City
{
    return City::first() ?? City::create(['country' => 'Таджикистан', 'region' => 'город Душанбе']);
}

function orderPayload(array $extra = []): array
{
    return $extra + [
        'title' => 'Не сливает воду стиральная машина',
        'work_category_id' => WorkCategory::where('slug', 'appliances')->value('id'),
        'description' => 'Samsung, 6 кг. Вода стоит в барабане, насос гудит.',
        'city_id' => orderCity()->id,
        'district' => '102 мкр',
        'address' => 'ул. Айни, 45, кв. 12',
        'contact_phone' => '+992 90 123 45 67',
        'deadline_option' => 'today',
        'budget' => 300,
    ];
}

function makeOrder(User $user, array $attributes = []): UrgentOrder
{
    return UrgentOrder::create($attributes + [
        'user_id' => $user->id,
        'work_category_id' => WorkCategory::where('slug', 'plumbing')->value('id'),
        'title' => 'Протекает кран',
        'description' => 'Капает из-под смесителя на кухне.',
        'city_id' => orderCity()->id,
        'address' => 'ул. Рудаки, 1',
        'deadline_option' => '1d',
        'deadline_at' => now()->addDay(),
        'status' => UrgentOrder::STATUS_OPEN,
    ]);
}

// ==================== кто может ====================

test('заявку создаёт и работодатель, и соискатель', function (string $role) {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)->get(route('public.orders.create'))->assertOk()->assertSee(__('Нужен мастер'));

    $this->actingAs($user)->post(route('public.orders.store'), orderPayload())->assertRedirect();

    expect(UrgentOrder::where('user_id', $user->id)->count())->toBe(1);
})->with(['employer', 'applicant']);

test('гостя отправляют войти', function () {
    $this->get(route('public.orders.create'))->assertRedirect(route('login'));
    $this->post(route('public.orders.store'), orderPayload())->assertRedirect(route('login'));
    expect(UrgentOrder::count())->toBe(0);
});

// ==================== создание ====================

test('заявка сохраняется со сроком, статусом и фото без EXIF', function () {
    Carbon::setTestNow('2026-10-07 10:00:00');
    $user = User::factory()->applicant()->create();

    $this->actingAs($user)->post(route('public.orders.store'), orderPayload([
        'photos' => [UploadedFile::fake()->image('leak.jpg', 3000, 2000), UploadedFile::fake()->image('b.png', 400, 300)],
    ]))->assertRedirect();

    $order = UrgentOrder::with('photos')->sole();

    expect($order->status)->toBe('open')
        ->and($order->isUrgent())->toBeTrue()
        ->and($order->deadline_at->toDateTimeString())->toBe('2026-10-07 23:59:59')
        ->and($order->budget)->toBe(300)
        ->and($order->address)->toBe('ул. Айни, 45, кв. 12')
        ->and($order->photos)->toHaveCount(2)
        ->and($order->photos[0]->width)->toBe(2000);

    Storage::disk('public')->assertExists($order->photos[0]->path);
    Storage::disk('public')->assertExists($order->photos[0]->thumb_path);
    expect(str_starts_with($order->photos[0]->path, 'orders/'.$order->id.'/'))->toBeTrue();

    Carbon::setTestNow();
});

test('срок считается от выбора заказчика', function () {
    $from = Carbon::parse('2026-10-07 10:00:00');

    expect(UrgentOrder::deadlineFor('today', $from)->toDateTimeString())->toBe('2026-10-07 23:59:59')
        ->and(UrgentOrder::deadlineFor('1d', $from)->toDateTimeString())->toBe('2026-10-08 10:00:00')
        ->and(UrgentOrder::deadlineFor('3d', $from)->toDateTimeString())->toBe('2026-10-10 10:00:00')
        // поздно вечером «сегодня» — не полчаса, а хотя бы три часа
        ->and(UrgentOrder::deadlineFor('today', Carbon::parse('2026-10-07 23:30:00'))->toDateTimeString())->toBe('2026-10-08 02:30:00');
});

test('срочными считаются «сегодня» и «сутки», а не «2–3 дня»', function () {
    $user = User::factory()->employer()->create();

    expect(makeOrder($user, ['deadline_option' => 'today'])->isUrgent())->toBeTrue()
        ->and(makeOrder($user, ['deadline_option' => '1d'])->isUrgent())->toBeTrue()
        ->and(makeOrder($user, ['deadline_option' => '3d'])->isUrgent())->toBeFalse();
});

test('обязательные поля и справочники проверяются', function () {
    $user = User::factory()->employer()->create();

    $this->actingAs($user)->post(route('public.orders.store'), orderPayload([
        'title' => '',
        'work_category_id' => 999,
        'description' => 'коротко',
        'city_id' => 999,
        'deadline_option' => 'week',
        'budget' => -5,
    ]))->assertSessionHasErrors(['title', 'work_category_id', 'description', 'city_id', 'deadline_option', 'budget']);

    expect(UrgentOrder::count())->toBe(0);
});

test('бюджет и адрес необязательны', function () {
    $user = User::factory()->employer()->create();

    $this->actingAs($user)->post(route('public.orders.store'), orderPayload(['budget' => null, 'address' => null, 'district' => null]))
        ->assertSessionHasNoErrors();

    expect(UrgentOrder::sole()->budget)->toBeNull();
});

test('не фото под видом фото не проходит, и заявка не создаётся', function () {
    $user = User::factory()->employer()->create();

    $this->actingAs($user)->post(route('public.orders.store'), orderPayload([
        'photos' => [UploadedFile::fake()->createWithContent('leak.jpg', '<?php echo 1; ?>')],
    ]))->assertSessionHasErrors('photos.0');

    expect(UrgentOrder::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('больше пяти фото и фото больше 10 МБ не проходят', function () {
    $user = User::factory()->employer()->create();

    $six = array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, 6));
    $this->actingAs($user)->post(route('public.orders.store'), orderPayload(['photos' => $six]))
        ->assertSessionHasErrors('photos');

    $big = UploadedFile::fake()->image('big.jpg')->size(11 * 1024);
    $this->actingAs($user)->post(route('public.orders.store'), orderPayload(['photos' => [$big]]))
        ->assertSessionHasErrors('photos.0');

    expect(UrgentOrder::count())->toBe(0);
});

test('открытых заявок у одного заказчика не больше пяти', function () {
    $user = User::factory()->employer()->create();
    foreach (range(1, UrgentOrder::MAX_OPEN_PER_USER) as $i) {
        makeOrder($user);
    }

    $this->actingAs($user)->get(route('public.orders.create'))->assertRedirect(route('public.orders.mine'));
    $this->actingAs($user)->post(route('public.orders.store'), orderPayload())->assertSessionHasErrors('title');

    // закрытые не считаются
    UrgentOrder::first()->update(['status' => UrgentOrder::STATUS_CANCELLED]);
    $this->actingAs($user)->post(route('public.orders.store'), orderPayload())->assertSessionHasNoErrors();
});

// ==================== просмотр ====================

test('заказчик видит свою заявку с адресом', function () {
    $user = User::factory()->employer()->create();
    $order = makeOrder($user);

    $this->actingAs($user)->get(route('public.orders.show', $order))
        ->assertOk()
        ->assertSee('Протекает кран')
        ->assertSee('ул. Рудаки, 1')
        ->assertSee(__('Отменить заявку'));
});

test('открытую чужую заявку видно без адреса, закрытую — нет', function () {
    $order = makeOrder(User::factory()->employer()->create());

    $this->actingAs(User::factory()->applicant()->create())
        ->get(route('public.orders.show', $order))
        ->assertOk()
        ->assertSee('Протекает кран')
        ->assertDontSee('ул. Рудаки, 1');

    $order->update(['status' => UrgentOrder::STATUS_CANCELLED]);

    $this->actingAs(User::factory()->applicant()->create())
        ->get(route('public.orders.show', $order))
        ->assertNotFound();
});

test('«Мои заявки»: свои — открытые сверху, чужих нет, адреса в списке нет', function () {
    $user = User::factory()->employer()->create();
    makeOrder($user, ['title' => 'Старая отменённая', 'status' => UrgentOrder::STATUS_CANCELLED]);
    makeOrder($user, ['title' => 'Открытая срочная', 'deadline_option' => 'today']);
    makeOrder(User::factory()->employer()->create(), ['title' => 'Чужая заявка']);

    $this->actingAs($user)->get(route('public.orders.mine'))
        ->assertOk()
        ->assertSeeInOrder(['Открытая срочная', 'Старая отменённая'])
        ->assertSee('🔥 '.__('Срочно'))
        ->assertDontSee('Чужая заявка')
        ->assertDontSee('ул. Рудаки, 1');
});

// ==================== отмена ====================

test('заказчик отменяет открытую заявку', function () {
    $user = User::factory()->employer()->create();
    $order = makeOrder($user);

    $this->actingAs($user)->patch(route('public.orders.cancel', $order))->assertRedirect(route('public.orders.mine'));

    expect($order->fresh()->status)->toBe('cancelled')
        ->and($order->fresh()->closed_at)->not->toBeNull();
});

test('чужую заявку не отменить, закрытую — тоже', function () {
    $owner = User::factory()->employer()->create();
    $order = makeOrder($owner);

    $this->actingAs(User::factory()->employer()->create())->patch(route('public.orders.cancel', $order))->assertNotFound();
    expect($order->fresh()->status)->toBe('open');

    $order->update(['status' => UrgentOrder::STATUS_EXPIRED]);
    $this->actingAs($owner)->patch(route('public.orders.cancel', $order))->assertSessionHas('error');
    expect($order->fresh()->status)->toBe('expired');
});

test('удаление аккаунта удаляет заявки и их фото', function () {
    $user = User::factory()->employer()->create(['password' => bcrypt('secret-pass')]);
    // удалить аккаунт можно из кабинета — значит, профиль компании заполнен
    makeEmployer($user);

    $this->actingAs($user)->post(route('public.orders.store'), orderPayload([
        'photos' => [UploadedFile::fake()->image('a.jpg')],
    ]));
    $order = UrgentOrder::sole();
    expect(Storage::disk('public')->allFiles($order->folder()))->not->toBe([]);

    $this->actingAs($user)->delete(route('employer.settings.destroy'), ['password' => 'secret-pass']);

    expect(User::find($user->id))->toBeNull()
        ->and(UrgentOrder::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles($order->folder()))->toBe([]);
});

// ==================== вход в раздел ====================

test('кнопка «Нужен мастер» есть в шапке, «Мои заявки» — в кабинетах', function () {
    $this->get(route('public.home'))->assertSee(route('public.orders.create'), false);

    $applicant = User::factory()->applicant()->create();
    makeApplicant($applicant);
    $this->actingAs($applicant)->get(route('applicant.portfolio.index'))->assertSee(route('public.orders.mine'), false);
});
