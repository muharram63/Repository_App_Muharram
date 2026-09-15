<?php

use App\Models\User;
use App\Models\Vacancy;

/*
 * Требование к языкам у вакансии.
 *
 * Раньше его негде было указать: разбор совпадения вытаскивал языки из текста
 * описания. Теперь это отдельное поле — такое же перечисление через запятую,
 * как навыки в вакансии и языки в резюме.
 */

function employerWithLanguagesVacancy(): array
{
    $user = User::factory()->employer()->create();
    $employer = makeEmployer($user);

    return [$user, $employer, makeVacancy($employer)];
}

function languagesPayload(Vacancy $vacancy, array $overrides = []): array
{
    return $overrides + $vacancy->only([
        'title', 'description', 'salary_from', 'salary_to', 'currency',
        'employment_type', 'work_schedule', 'experience_required', 'skill', 'city_id', 'status',
    ]);
}

test('поле языков есть в формах создания и редактирования', function () {
    [$user, , $vacancy] = employerWithLanguagesVacancy();

    $this->actingAs($user)->get('/employer/vacancies/create')
        ->assertOk()
        ->assertSee('name="languages"', false);

    $this->actingAs($user)->get('/employer/vacancies/'.$vacancy->id.'/edit')
        ->assertOk()
        ->assertSee('name="languages"', false);
});

test('языки сохраняются через форму создания', function () {
    $user = User::factory()->employer()->create();
    $employer = makeEmployer($user);

    $this->actingAs($user)->post('/employer/vacancies', [
        'title' => 'Барноманависи Backend',
        'description' => 'Мутахассис лозим аст.',
        'salary_from' => 3000,
        'salary_to' => 6000,
        'currency' => 'TJS',
        'employment_type' => 'full-time',
        'work_schedule' => 'full_day',
        'experience_required' => 'year',
        'skill' => 'PHP, Laravel',
        'languages' => 'Тоҷикӣ, Англисӣ B2',
        'status' => 'active',
        'city_id' => $employer->city_id,
    ])->assertSessionHasNoErrors();

    expect(Vacancy::latest('id')->first()->languages)->toBe('Тоҷикӣ, Англисӣ B2');
});

test('языки меняются через форму редактирования', function () {
    [$user, , $vacancy] = employerWithLanguagesVacancy();

    $this->actingAs($user)
        ->patch('/employer/vacancies/'.$vacancy->id, languagesPayload($vacancy, ['languages' => 'Русӣ']))
        ->assertSessionHasNoErrors();

    expect($vacancy->fresh()->languages)->toBe('Русӣ');
});

/* Поле необязательное: многим вакансиям язык не важен. */
test('вакансию можно сохранить без языков', function () {
    [$user, , $vacancy] = employerWithLanguagesVacancy();

    $this->actingAs($user)
        ->patch('/employer/vacancies/'.$vacancy->id, languagesPayload($vacancy, ['languages' => '']))
        ->assertSessionHasNoErrors();

    expect($vacancy->fresh()->languages)->toBeEmpty();
});

test('требуемые языки показаны на странице вакансии', function () {
    [, , $vacancy] = employerWithLanguagesVacancy();
    $vacancy->update(['languages' => 'Тоҷикӣ, Русӣ']);

    $html = $this->get('/vacancies/'.$vacancy->id)->assertOk()->getContent();

    expect($html)->toContain('Требуемые языки')
        ->and($html)->toContain('Тоҷикӣ')
        ->and($html)->toContain('Русӣ');
});

test('без языков блок на странице не показывается', function () {
    [, , $vacancy] = employerWithLanguagesVacancy();
    $vacancy->update(['languages' => null]);

    expect($this->get('/vacancies/'.$vacancy->id)->assertOk()->getContent())
        ->not->toContain('Требуемые языки');
});

/*
 * Просмотры. Сидеры проставляли случайные числа, и в каталоге у вакансии
 * стояло «372 просмотра», хотя её никто не открывал. Счётчик и так растёт
 * сам при заходе на страницу — выдумывать значения незачем.
 */
test('сидеры не выдумывают просмотры', function () {
    $this->seed(Database\Seeders\TajikDataSeeder::class);

    expect(Vacancy::max('views'))->toBe(0)
        ->and(App\Models\Resume::max('views'))->toBe(0);
});

test('просмотр растёт от реального захода на страницу', function () {
    [, , $vacancy] = employerWithLanguagesVacancy();
    $vacancy->update(['views' => 0]);

    $this->get('/vacancies/'.$vacancy->id)->assertOk();

    expect($vacancy->fresh()->views)->toBe(1);
});

/*
 * Обновление страницы просмотр не добавляет: иначе счётчик накручивался бы
 * одним человеком. Один просмотр на вакансию за сессию.
 */
test('повторный заход в той же сессии счётчик не двигает', function () {
    [, , $vacancy] = employerWithLanguagesVacancy();
    $vacancy->update(['views' => 0]);

    $this->get('/vacancies/'.$vacancy->id)->assertOk();
    $this->get('/vacancies/'.$vacancy->id)->assertOk();
    $this->get('/vacancies/'.$vacancy->id)->assertOk();

    expect($vacancy->fresh()->views)->toBe(1);

    // новая сессия — новый посетитель
    $this->flushSession();
    $this->get('/vacancies/'.$vacancy->id)->assertOk();

    expect($vacancy->fresh()->views)->toBe(2);
});

test('публичная страница вакансии показывает требуемые языки', function () {
    $this->seed(Database\Seeders\TajikDataSeeder::class);

    $vacancy = Vacancy::whereNotNull('languages')->where('languages', '!=', '')->first();
    $html = $this->get('/vacancies/'.$vacancy->id)->assertOk()->getContent();

    $first = trim(explode(',', $vacancy->languages)[0]);

    expect($html)->toContain('Требуемые языки')
        ->and($html)->toContain($first);
});

