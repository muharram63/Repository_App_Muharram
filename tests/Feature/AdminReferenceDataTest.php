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

test('a category and an industry in use cannot be deleted', function () {
    $admin = User::factory()->admin()->create();
    $employer = makeEmployer(User::factory()->employer()->create());

    $this->actingAs($admin)->delete('/superadmin/categories/'.$employer->category_id);
    expect(Category::find($employer->category_id))->not->toBeNull();

    $this->actingAs($admin)->delete('/superadmin/industries/'.$employer->industry_id);
    expect(Industry::find($employer->industry_id))->not->toBeNull();
});
