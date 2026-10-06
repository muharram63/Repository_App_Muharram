<?php

use App\Models\Category;
use App\Models\WorkCategory;

/*
 * Справочник категорий работ мастеров (этап 1).
 */

test('после миграции в справочнике восемь категорий работ', function () {
    expect(WorkCategory::ordered()->pluck('slug')->all())->toBe([
        'plumbing', 'electrical', 'construction', 'painting',
        'appliances', 'auto', 'art', 'other',
    ]);
});

test('ключи категорий уникальны', function () {
    WorkCategory::create(['slug' => 'plumbing', 'name' => 'Дубль']);
})->throws(Illuminate\Database\QueryException::class);

test('категории работ не смешиваются с отраслями вакансий', function () {
    expect(Category::where('name', 'Сантехника')->exists())->toBeFalse()
        ->and(WorkCategory::where('name', 'Сантехника')->exists())->toBeTrue();
});

test('у каждой категории есть перевод', function (string $locale) {
    $dictionary = json_decode(file_get_contents(base_path('lang/'.$locale.'.json')), true);
    $missing = array_values(array_diff(WorkCategory::pluck('name')->all(), array_keys($dictionary)));

    expect($missing)->toBe([], "Категории без перевода ({$locale}): ".implode(' | ', $missing));
})->with(['tg', 'en']);

test('модель отдаёт название на языке интерфейса', function () {
    $plumbing = WorkCategory::where('slug', 'plumbing')->sole();

    expect($plumbing->label())->toBe('Сантехника');

    app()->setLocale('en');
    expect($plumbing->label())->toBe('Plumbing')
        ->and($plumbing->title())->toBe('🚰 Plumbing');

    app()->setLocale('tg');
    expect($plumbing->label())->toBe('Сантехникӣ');
});

test('подписи статусов и сроков заявок переведены', function (string $locale) {
    $dictionary = json_decode(file_get_contents(base_path('lang/'.$locale.'.json')), true);
    $labels = array_merge(
        array_values(App\Models\UrgentOrder::STATUSES),
        array_values(App\Models\UrgentOrder::DEADLINES),
        array_values(App\Models\PortfolioMedia::STAGES),
        array_values(App\Models\UrgentOrderOffer::STATUSES),
        array_values(App\Models\UrgentOrderOffer::CAN_START),
        array_values(App\Models\UrgentOrderOffer::CUSTOMER_STATUSES),
        ['Вас выбрали исполнителем', 'Заказчик выбрал другого мастера', 'Заказ отмечен выполненным', 'Заказчик отменил заявку', 'Заявка «:title». Адрес и телефон заказчика — на странице заявки.', 'По заявке «:title». Спасибо за отклик — новые заказы появляются каждый день.', 'Заказчик подтвердил выполнение заявки «:title». Спасибо за работу!', 'Заявка «:title» больше не актуальна.', 'Срок заявки истёк', '«:title»: исполнитель не выбран, откликов было :count. Повторите заявку — данные подставятся сами.', '«:title»: к сроку мастер не нашёлся. Повторите заявку — данные подставятся сами.'],
        ['Срочная заявка: :category', 'Новая заявка: :category', 'Новый отклик на заявку «:title»', ':name · :price · откликов: :count'],
    );

    expect(array_values(array_diff($labels, array_keys($dictionary))))->toBe([]);
})->with(['tg', 'en']);
