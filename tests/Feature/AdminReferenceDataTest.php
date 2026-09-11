<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Industry;
use App\Models\User;

// ===== C1 =====
test('a city in use cannot be deleted', function () {
    $admin = User::factory()->admin()->create();
    $employerUser = User::factory()->employer()->create();

    $employer = makeEmployer($employerUser);
    makeVacancy($employer);

    $city = City::find($employer->city_id);

    $this->actingAs($admin)
        ->delete('/superadmin/cities/'.$city->id)
        ->assertRedirect();

    expect(City::find($city->id))->not->toBeNull();
});

test('an unused city is deleted', function () {
    $admin = User::factory()->admin()->create();
    $city = City::create(['country' => 'Таджикистан', 'region' => 'Худжанд']);

    $this->actingAs($admin)->delete('/superadmin/cities/'.$city->id);

    expect(City::find($city->id))->toBeNull();
});

test('an industry can be edited without renaming it', function () {
    $admin = User::factory()->admin()->create();
    [$city, $category, $industry] = refs();

    $other = Category::create(['name' => 'Финансы', 'description' => 'Финансы', 'slug' => 'finance']);

    // меняем описание и категорию, название оставляем прежним
    $this->actingAs($admin)->put('/superadmin/industries/'.$industry->id, [
        'name' => $industry->name,
        'category_id' => $other->id,
        'description' => 'Обновлённое описание',
    ])->assertSessionHasNoErrors()
        ->assertRedirect(route('superadmin.industries.index', absolute: false));

    expect($industry->fresh()->description)->toBe('Обновлённое описание')
        ->and($industry->fresh()->category_id)->toBe($other->id);
});

test('an industry still cannot take the name of another one', function () {
    $admin = User::factory()->admin()->create();
    [$city, $category, $industry] = refs();

    $taken = Industry::create([
        'name' => 'Логистика', 'category_id' => $category->id,
        'parent_id' => 0, 'description' => 'Логистика',
    ]);

    $this->actingAs($admin)->put('/superadmin/industries/'.$industry->id, [
        'name' => $taken->name,
        'category_id' => $category->id,
        'description' => 'Описание',
    ])->assertSessionHasErrors('name');
});

test('the admin panel shows why a form was rejected', function () {
    $admin = User::factory()->admin()->create();
    [$city, $category, $industry] = refs();

    // отказ валидации виден на странице, а не молча возвращает форму
    $this->actingAs($admin)
        ->from(route('superadmin.industries.edit', $industry, absolute: false))
        ->put('/superadmin/industries/'.$industry->id, ['name' => ''])
        ->assertRedirect(route('superadmin.industries.edit', $industry, absolute: false));

    $this->actingAs($admin)->get('/superadmin/industries/'.$industry->id.'/edit')
        ->assertOk()
        ->assertSee('Проверьте форму');
});

test('a blocked deletion explains itself', function () {
    $admin = User::factory()->admin()->create();
    $employer = makeEmployer(User::factory()->employer()->create());

    $this->actingAs($admin)
        ->from(route('superadmin.industries.index', absolute: false))
        ->delete('/superadmin/industries/'.$employer->industry_id);

    // сообщение о причине отказа доходит до страницы
    $this->actingAs($admin)->get('/superadmin/industries')
        ->assertOk()
        ->assertSee('Индустрия используется');
});

test('a category and an industry in use cannot be deleted', function () {
    $admin = User::factory()->admin()->create();
    $employer = makeEmployer(User::factory()->employer()->create());

    $this->actingAs($admin)->delete('/superadmin/categories/'.$employer->category_id);
    expect(Category::find($employer->category_id))->not->toBeNull();

    $this->actingAs($admin)->delete('/superadmin/industries/'.$employer->industry_id);
    expect(Industry::find($employer->industry_id))->not->toBeNull();
});
