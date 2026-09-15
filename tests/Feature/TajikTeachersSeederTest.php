<?php

use Database\Seeders\TajikTeachersSeeder;
use Illuminate\Support\Facades\DB;

/*
 * Учителя: 50 вакансий и 50 резюме на таджикском, по всем школьным предметам.
 */

/** Список предметов из самого сидера — чтобы тест не разошёлся с ним. */
function teacherSubjects(): array
{
    $property = (new ReflectionClass(TajikTeachersSeeder::class))->getProperty('subjects');
    $property->setAccessible(true);

    return $property->getValue(new TajikTeachersSeeder);
}

test('сидер добавляет 50 вакансий и 50 резюме учителей', function () {
    $this->seed(TajikTeachersSeeder::class);

    expect(DB::table('vacancies')->count())->toBe(50)
        ->and(DB::table('resumes')->count())->toBe(50)
        // 25 заведений: школа нанимает нескольких учителей сразу
        ->and(DB::table('employers')->count())->toBe(25);
});

/*
 * Главное требование: ни один предмет не должен остаться без вакансии и без
 * резюме. При случайной раздаче половина предметов просто не попала бы
 * в выдачу, поэтому сидер раздаёт их по кругу.
 */
test('каждый школьный предмет представлен и вакансией, и резюме', function () {
    $this->seed(TajikTeachersSeeder::class);

    $vacancies = DB::table('vacancies')->pluck('description')->implode(' ');
    $resumes = DB::table('resumes')->pluck('description')->implode(' ');

    foreach (teacherSubjects() as $subject) {
        expect($vacancies)->toContain($subject)
            ->and($resumes)->toContain($subject);
    }
});

test('тексты учительских записей написаны по-таджикски', function () {
    $this->seed(TajikTeachersSeeder::class);

    expect(DB::table('vacancies')->value('description'))->toContain('омӯзгори фанни')
        ->and(DB::table('vacancies')->value('description'))->toContain('Талабот:')
        ->and(DB::table('resumes')->value('description'))->toContain('бо таҷрибаи')
        ->and(DB::table('applicants')->value('about_me'))->toContain('Омӯзгори фанни');
});

test('все учебные заведения находятся в Таджикистане', function () {
    $this->seed(TajikTeachersSeeder::class);

    $outside = DB::table('employers')
        ->join('cities', 'employers.city_id', '=', 'cities.id')
        ->where('cities.country', '!=', 'Таджикистан')
        ->count();

    expect($outside)->toBe(0);
});

test('повторный запуск не создаёт дублей', function () {
    $this->seed(TajikTeachersSeeder::class);
    $this->seed(TajikTeachersSeeder::class);

    expect(DB::table('vacancies')->count())->toBe(50)
        ->and(DB::table('employers')->count())->toBe(25);
});
