<?php

use App\Models\MasterReview;
use App\Models\PortfolioWork;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/*
 * Каталог мастеров /masters и подсказка о роли мастера при регистрации.
 * Помощники feedMaster(), categoryId() — из tests/Pest.php.
 */

beforeEach(function () {
    Storage::fake('public');
});

function rateMaster(App\Models\Applicant $master, int $rating): void
{
    $owner = User::factory()->employer()->create();
    $order = feedOrder(['owner' => $owner, 'status' => App\Models\UrgentOrder::STATUS_DONE]);
    MasterReview::create(['urgent_order_id' => $order->id, 'applicant_id' => $master->id, 'user_id' => $owner->id, 'rating' => $rating]);
}

test('в каталоге — только соискатели с портфолио', function () {
    feedMaster(name: 'Мастер Фарход');
    $plain = User::factory()->applicant()->create(['name' => 'Просто Соискатель']);
    makeApplicant($plain);

    $this->get(route('public.masters.index'))
        ->assertOk()
        ->assertSee('Мастер Фарход')
        ->assertDontSee('Просто Соискатель')
        ->assertSee('🚰 Сантехника');
});

test('скрытые и заблокированные мастера в каталог не попадают', function () {
    [$hidden] = feedMaster(name: 'Скрытый Мастер');
    $hidden->settings()->create(['hide_profile' => true]);
    [$blocked] = feedMaster(name: 'Блок Мастер');
    $blocked->forceFill(['status' => 'blocked'])->save();
    feedMaster(name: 'Видимый Мастер');

    $this->get(route('public.masters.index'))
        ->assertSee('Видимый Мастер')
        ->assertDontSee('Скрытый Мастер')
        ->assertDontSee('Блок Мастер');
});

test('фильтр по виду работ и поиск по имени, городу и работам', function () {
    feedMaster('plumbing', 'Худжанд', 'Сантехник Али');
    feedMaster('electrical', 'Душанбе', 'Электрик Бахром');

    $this->get(route('public.masters.index', ['category' => 'electrical']))
        ->assertSee('Электрик Бахром')->assertDontSee('Сантехник Али');

    $this->get(route('public.masters.index', ['q' => 'Худжанд']))
        ->assertSee('Сантехник Али')->assertDontSee('Электрик Бахром');

    $this->get(route('public.masters.index', ['q' => 'Бахром']))
        ->assertSee('Электрик Бахром')->assertDontSee('Сантехник Али');

    // «Замена смесителя» — название работы у обоих из помощника
    $this->get(route('public.masters.index', ['q' => 'смесител']))
        ->assertSee('Сантехник Али')->assertSee('Электрик Бахром');
});

test('по умолчанию сверху — с лучшей оценкой, мастера без отзывов в конце', function () {
    [, $noReviews] = feedMaster(name: 'Без Отзывов');
    [, $good] = feedMaster(name: 'Хороший Мастер');
    [, $best] = feedMaster(name: 'Лучший Мастер');
    rateMaster($good, 4);
    rateMaster($best, 5);

    $this->get(route('public.masters.index'))
        ->assertSeeInOrder(['Лучший Мастер', 'Хороший Мастер', 'Без Отзывов'])
        ->assertSee('5,0');
});

test('сортировка по числу работ', function () {
    feedMaster(name: 'Одна Работа');
    [, $many] = feedMaster(name: 'Много Работ');
    foreach (range(1, 3) as $i) {
        PortfolioWork::create(['applicant_id' => $many->id, 'work_category_id' => categoryId('plumbing'), 'title' => "Работа {$i}"]);
    }

    $this->get(route('public.masters.index', ['sort' => 'works']))
        ->assertSeeInOrder(['Много Работ', 'Одна Работа']);
});

test('пустой результат предлагает создать заявку', function () {
    $this->get(route('public.masters.index', ['q' => 'никого-нет']))
        ->assertOk()
        ->assertSee(__('Таких мастеров не нашлось'))
        ->assertSee(route('public.orders.create'), false);
});

test('на карточке — обложки работ и ссылка на профиль', function () {
    [, $master] = feedMaster(name: 'Мастер Фарход');

    $this->get(route('public.masters.index'))
        ->assertSee(route('public.masters.show', $master), false)
        ->assertSee(asset('storage/p_t.jpg'), false);
});

test('«Мастера» — в шапке сайта', function () {
    $this->get(route('public.home'))->assertSee(route('public.masters.index'), false);
});

// ==================== регистрация ====================

test('из регистрации понятно, какую роль выбрать мастеру', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee(__('Соискатель или мастер — ищу работу или заказы'))
        ->assertSee(__('Работодатель — ищу сотрудников'))
        ->assertSee(__('Мастерам — сантехникам, электрикам, малярам — выбирать «Соискатель или мастер»: после регистрации добавьте фото работ в портфолио. Заказать мастера можно с любой ролью.'));
});
