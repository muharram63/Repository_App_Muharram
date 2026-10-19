<?php

/*
 * Наполнитель вакансий по профессиям: у каждой профессии 2–3 вакансии
 * в разных компаниях, дизайнеры есть, повторный запуск ничего не дублирует.
 */

use App\Models\Vacancy;
use Database\Seeders\ProfessionVacanciesSeeder;

test('у каждой профессии 2–3 вакансии в разных компаниях, дизайнеры на месте', function () {
    $this->seed(ProfessionVacanciesSeeder::class);

    $byTitle = Vacancy::where('status', 'active')->get()->groupBy('title');

    expect($byTitle->count())->toBeGreaterThanOrEqual(40);

    foreach ($byTitle as $title => $list) {
        expect($list->count())->toBeGreaterThanOrEqual(2, $title)->toBeLessThanOrEqual(3, $title)
            ->and($list->pluck('employer_id')->unique()->count())->toBe($list->count(), $title);
    }

    expect($byTitle->keys()->filter(fn ($t) => str_contains($t, 'дизайнер'))->count())->toBeGreaterThanOrEqual(3);

    // вакансии видны в каталоге
    $this->get(route('public.vacancies.index', ['q' => 'дизайнер']))->assertOk();
});

test('повторный запуск не создаёт дублей', function () {
    $this->seed(ProfessionVacanciesSeeder::class);
    $count = Vacancy::count();

    $this->seed(ProfessionVacanciesSeeder::class);

    expect(Vacancy::count())->toBe($count);
});
