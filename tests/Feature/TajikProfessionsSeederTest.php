<?php

use Database\Seeders\TajikProfessionsSeeder;
use Illuminate\Support\Facades\DB;

/*
 * Рабочие профессии и сфера услуг: 37 вакансий и 37 резюме на таджикском.
 */

/** Список профессий из самого сидера — чтобы тест не разошёлся с ним. */
function workerProfessions(): array
{
    $property = (new ReflectionClass(TajikProfessionsSeeder::class))->getProperty('professions');
    $property->setAccessible(true);

    return array_column($property->getValue(new TajikProfessionsSeeder), 0);
}

test('сидер добавляет 37 вакансий и 37 резюме', function () {
    $this->seed(TajikProfessionsSeeder::class);

    expect(DB::table('vacancies')->count())->toBe(37)
        ->and(DB::table('resumes')->count())->toBe(37);
});

/*
 * По одной вакансии и одному резюме на профессию: при случайной раздаче
 * часть профессий не попала бы в выдачу вовсе.
 */
test('каждая профессия представлена и вакансией, и резюме', function () {
    $this->seed(TajikProfessionsSeeder::class);

    $titles = DB::table('vacancies')->pluck('title')->all();
    $professions = DB::table('resumes')->pluck('profession')->all();

    foreach (workerProfessions() as $profession) {
        expect($titles)->toContain($profession)
            ->and($professions)->toContain($profession);
    }

    // ни одна профессия не продублирована
    expect(count(array_unique($titles)))->toBe(count(workerProfessions()));
});

/* Обязанности у каждой профессии свои — иначе тексты были бы под копирку. */
test('описания опираются на обязанности конкретной профессии', function () {
    $this->seed(TajikProfessionsSeeder::class);

    $welder = DB::table('vacancies')->where('title', 'Ҷӯшгар')->value('description');
    $gardener = DB::table('resumes')->where('profession', 'Боғбон')->value('description');

    expect($welder)->toContain('ҷӯшонидани конструксияҳои металлӣ')
        ->and($welder)->toContain('лозим аст')
        ->and($gardener)->toContain('нигоҳубини боғ')
        ->and(DB::table('applicants')->value('about_me'))->toContain('Мутахассиси касби');
});

test('все организации находятся в Таджикистане', function () {
    $this->seed(TajikProfessionsSeeder::class);

    $outside = DB::table('employers')
        ->join('cities', 'employers.city_id', '=', 'cities.id')
        ->where('cities.country', '!=', 'Таджикистан')
        ->count();

    expect($outside)->toBe(0);
});

test('повторный запуск не создаёт дублей', function () {
    $this->seed(TajikProfessionsSeeder::class);
    $this->seed(TajikProfessionsSeeder::class);

    expect(DB::table('vacancies')->count())->toBe(37)
        ->and(DB::table('resumes')->count())->toBe(37);
});
