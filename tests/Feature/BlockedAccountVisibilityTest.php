<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('a blocked employer disappears from the public part', function () {
    $ownerUser = User::factory()->employer()->create();
    $employer = makeEmployer($ownerUser);
    $vacancy = makeVacancy($employer);

    // пока аккаунт открыт — всё на месте
    $this->get('/vacancies')->assertOk()->assertSee('PHP-разработчик');
    $this->get('/vacancies/'.$vacancy->id)->assertOk();
    $this->get('/companies')->assertOk()->assertSee('ООО Тест');
    $this->get('/companies/'.$employer->id)->assertOk();

    $ownerUser->status = 'blocked';
    $ownerUser->save();

    $this->get('/vacancies')->assertOk()->assertDontSee('PHP-разработчик');
    $this->get('/vacancies/'.$vacancy->id)->assertNotFound();
    $this->get('/companies')->assertOk()->assertDontSee('ООО Тест');
    $this->get('/companies/'.$employer->id)->assertNotFound();
    $this->get('/')->assertOk()->assertDontSee('PHP-разработчик');
});

test('a blocked applicant disappears from the public part', function () {
    Storage::fake('local');

    $applicantUser = User::factory()->applicant()->create();
    $applicant = makeApplicant($applicantUser);
    Storage::disk('local')->put('resumes/cv.docx', 'содержимое');
    $resume = makeResume($applicant, 'resumes/cv.docx');

    $viewer = User::factory()->employer()->create();
    makeEmployer($viewer);

    $this->get('/resume')->assertOk()->assertSee('PHP-разработчик');
    $this->get('/resume/'.$resume->id)->assertOk();
    $this->actingAs($viewer)->get('/resume/'.$resume->id.'/document')->assertOk();

    $applicantUser->status = 'blocked';
    $applicantUser->save();

    auth()->logout();
    $this->get('/resume')->assertOk()->assertDontSee('PHP-разработчик');
    $this->get('/resume/'.$resume->id)->assertNotFound();
    $this->actingAs($viewer)->get('/resume/'.$resume->id.'/document')->assertNotFound();
});

test('the owner and the admin still see a blocked account content', function () {
    $ownerUser = User::factory()->employer()->create();
    $employer = makeEmployer($ownerUser);
    $vacancy = makeVacancy($employer);

    $ownerUser->status = 'blocked';
    $ownerUser->save();

    // администратору объект по-прежнему доступен для разбора
    $this->actingAs(User::factory()->admin()->create())
        ->get('/vacancies/'.$vacancy->id)
        ->assertOk();
});

test('an admin can set only an open or a closed account', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->applicant()->create();

    $this->actingAs($admin)
        ->patch('/superadmin/users/'.$user->id, ['status' => 'under review'])
        ->assertSessionHasErrors('status');

    $this->actingAs($admin)
        ->patch('/superadmin/users/'.$user->id, ['status' => 'inactive'])
        ->assertSessionHasErrors('status');

    expect($user->fresh()->status)->toBe('active');

    $this->actingAs($admin)
        ->patch('/superadmin/users/'.$user->id, ['status' => 'blocked'])
        ->assertRedirect();

    expect($user->fresh()->status)->toBe('blocked');
});
