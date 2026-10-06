<?php

use App\Models\City;
use App\Models\User;
use App\Services\Cities\CityDirectory;

/*
 * Дубли в справочнике городов: поиск, объединение, защита в админке.
 */

function city(string $region): City
{
    return City::create(['country' => 'Таджикистан', 'region' => $region]);
}

test('ключ города сводит разные написания к одному', function (string $a, string $b) {
    expect(CityDirectory::key($a))->toBe(CityDirectory::key($b));
})->with([
    ['Исфара', 'Согдийская область, город Исфара'],
    ['Кулоб', 'Хатлонская область, город Куляб'],
    ['Согдийская область, город Конибодом', 'Согдийская область, город Канибадам'],
    ['Согдийская область, город Душанбе', 'город Душанбе'],
    ['г. Худжанд', 'Хуҷанд'],
]);

test('разные города — разные ключи', function () {
    expect(CityDirectory::key('город Душанбе'))->not->toBe(CityDirectory::key('Согдийская область, город Худжанд'))
        ->and(CityDirectory::key('Хатлонская область, город Бохтар'))->toBe('бохтар');
});

test('дубли находятся группами', function () {
    $kulob = city('Кулоб');
    $kulyab = city('Хатлонская область, город Куляб');
    $khorog = city('ГБАО, город Хорог');

    $duplicates = app(CityDirectory::class)->duplicates();

    expect($duplicates->get($kulob->id)->pluck('id')->all())->toBe([$kulyab->id])
        ->and($duplicates->get($kulyab->id)->pluck('id')->all())->toBe([$kulob->id])
        ->and($duplicates->has($khorog->id))->toBeFalse();
});

test('объединение переносит компании, вакансии и заявки и удаляет дубль', function () {
    $duplicate = city('Кулоб');
    $canonical = city('Хатлонская область, город Куляб');

    $employerUser = User::factory()->employer()->create();
    $employer = makeEmployer($employerUser);
    $employer->update(['city_id' => $duplicate->id]);
    $vacancy = makeVacancy($employer);
    $vacancy->update(['city_id' => $duplicate->id]);
    $order = feedOrder(['city_id' => $duplicate->id]);

    $moved = app(CityDirectory::class)->merge($duplicate, $canonical);

    expect($moved)->toBe(['employers' => 1, 'vacancies' => 1, 'urgent_orders' => 1])
        ->and(City::find($duplicate->id))->toBeNull()
        ->and($employer->fresh()->city_id)->toBe($canonical->id)
        ->and($vacancy->fresh()->city_id)->toBe($canonical->id)
        ->and($order->fresh()->city_id)->toBe($canonical->id);
});

test('миграция объединяет известные дубли по названию', function () {
    $wrongDushanbe = city('Согдийская область, город Душанбе');
    $dushanbe = city('город Душанбе');
    $isfara = city('Исфара');
    $isfaraCanonical = city('Согдийская область, город Исфара');
    // у Конибодома правильной пары нет — запись просто переименовывается
    $konibodom = city('Согдийская область, город Конибодом');

    $employer = makeEmployer(User::factory()->employer()->create());
    $employer->update(['city_id' => $wrongDushanbe->id]);

    (require database_path('migrations/2026_10_10_000001_merge_duplicate_cities.php'))->up();

    expect(City::find($wrongDushanbe->id))->toBeNull()
        ->and(City::find($isfara->id))->toBeNull()
        ->and(City::find($dushanbe->id))->not->toBeNull()
        ->and(City::find($isfaraCanonical->id))->not->toBeNull()
        ->and($employer->fresh()->city_id)->toBe($dushanbe->id)
        ->and($konibodom->fresh()->region)->toBe('Согдийская область, город Канибадам');

    // повторный запуск ничего не ломает
    (require database_path('migrations/2026_10_10_000001_merge_duplicate_cities.php'))->up();
    expect(City::where('region', 'город Душанбе')->count())->toBe(1);
});

// ==================== админка ====================

test('админ не может добавить уже существующий город в другом написании', function () {
    $admin = User::factory()->admin()->create();
    city('Хатлонская область, город Куляб');

    $this->actingAs($admin)->post(route('superadmin.cities.store'), ['country' => 'Таджикистан', 'region' => 'Кулоб'])
        ->assertSessionHasErrors('region');

    expect(City::where('region', 'Кулоб')->exists())->toBeFalse();

    // новый город — можно
    $this->actingAs($admin)->post(route('superadmin.cities.store'), ['country' => 'Таджикистан', 'region' => 'Согдийская область, город Бустон'])
        ->assertSessionHasNoErrors();
});

test('при правке нельзя превратить город в дубль другого, но можно сохранить его же', function () {
    $admin = User::factory()->admin()->create();
    city('город Душанбе');
    $khorog = city('ГБАО, город Хорог');

    $this->actingAs($admin)->put(route('superadmin.cities.update', $khorog), ['country' => 'Таджикистан', 'region' => 'Душанбе'])
        ->assertSessionHasErrors('region');

    $this->actingAs($admin)->put(route('superadmin.cities.update', $khorog), ['country' => 'Таджикистан', 'region' => 'ГБАО, г. Хорог'])
        ->assertSessionHasNoErrors();
    expect($khorog->fresh()->region)->toBe('ГБАО, г. Хорог');
});

test('в списке городов дубли помечены, у каждого — сколько к нему привязано', function () {
    $admin = User::factory()->admin()->create();
    city('Исфара');
    city('Согдийская область, город Исфара');

    $this->actingAs($admin)->get(route('superadmin.cities.index'))
        ->assertOk()
        ->assertSee('Возможный дубль')
        ->assertSee('Объединить с…')
        ->assertSee('компаний — 0, вакансий — 0, заявок — 0');
});

test('админ объединяет дубль через список', function () {
    $admin = User::factory()->admin()->create();
    $duplicate = city('Исфара');
    $canonical = city('Согдийская область, город Исфара');
    $order = feedOrder(['city_id' => $duplicate->id]);

    $this->actingAs($admin)
        ->post(route('superadmin.cities.merge', $duplicate), ['into' => $canonical->id, 'city_id' => $duplicate->id])
        ->assertRedirect(route('superadmin.cities.index'))
        ->assertSessionHas('status');

    expect(City::find($duplicate->id))->toBeNull()
        ->and($order->fresh()->city_id)->toBe($canonical->id);
});

test('объединять города может только админ', function () {
    $duplicate = city('Исфара');
    $canonical = city('Согдийская область, город Исфара');

    $this->actingAs(User::factory()->employer()->create())
        ->post(route('superadmin.cities.merge', $duplicate), ['into' => $canonical->id, 'city_id' => $duplicate->id]);

    expect(City::find($duplicate->id))->not->toBeNull();
});
