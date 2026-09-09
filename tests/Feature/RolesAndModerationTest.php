<?php

use App\Models\Applicant;
use App\Models\Category;
use App\Models\City;
use App\Models\Employer;
use App\Models\Industry;
use App\Models\User;
use App\Models\Vacancy;

// ===== A1 =====
test('applicant cannot create an employer profile', function () {
    $user = User::factory()->applicant()->create();
    [$city, $category, $industry] = refs();

    $this->actingAs($user)->post('/employers', [
        'company_name' => 'ООО Чужое',
        'job' => 'HR',
        'email_company' => 'hr@example.com',
        'phone' => '+992000000000',
        'category_id' => $category->id,
        'description' => 'Компания',
        'city_id' => $city->id,
        'industry_id' => $industry->id,
        'website_url' => 'https://example.com',
    ]);

    expect(Employer::where('user_id', $user->id)->exists())->toBeFalse();
});

// ===== A2 =====
test('employer without a company profile is sent to the profile form', function () {
    $user = User::factory()->employer()->create();

    $this->actingAs($user)
        ->get('/employer/vacancies/create')
        ->assertRedirect(route('public.employers.create', absolute: false));

    $this->actingAs($user)
        ->post('/employer/vacancies', ['title' => 'PHP-разработчик'])
        ->assertRedirect(route('public.employers.create', absolute: false));
});

// ===== A3 =====
test('a vacancy closed by moderation leaves the public catalog', function () {
    $owner = User::factory()->employer()->create();
    $employer = makeEmployer($owner);
    $vacancy = makeVacancy($employer, 'closed');

    $this->get('/vacancies')->assertOk()->assertDontSee('PHP-разработчик');
    $this->get('/vacancies/'.$vacancy->id)->assertNotFound();

    // владелец свою снятую вакансию всё же видит
    $this->actingAs($owner)->get('/vacancies/'.$vacancy->id)->assertOk();
});

test('an active vacancy stays visible', function () {
    $owner = User::factory()->employer()->create();
    $vacancy = makeVacancy(makeEmployer($owner), 'active');

    $this->get('/vacancies')->assertOk()->assertSee('PHP-разработчик');
    $this->get('/vacancies/'.$vacancy->id)->assertOk();
});

// ===== A4 =====
test('blocking a user ends their open session', function () {
    $user = User::factory()->applicant()->create();
    Applicant::create(['user_id' => $user->id, 'education' => 'Высшее']);

    $this->actingAs($user)->get('/applicant/profile')->assertOk();

    // status вне массового заполнения — назначаем явно
    $user->status = 'blocked';
    $user->save();

    $this->actingAs($user)
        ->get('/applicant/profile')
        ->assertRedirect(route('login', absolute: false));

    $this->assertGuest();
});
