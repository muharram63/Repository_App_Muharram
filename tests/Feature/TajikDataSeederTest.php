<?php

use Database\Seeders\TajikDataSeeder;
use Illuminate\Support\Facades\DB;

/*
 * Демо-данные на таджикском. Сидер наполняет витрину платформы: каталог
 * должен выглядеть как таджикский рынок труда, а не как абстрактная выборка.
 */

test('в каталог попадают только существующие организации', function () {
    $this->seed(TajikDataSeeder::class);

    // компаний столько, сколько реальных названий в списке: выдуманные
    // «для объёма» убраны, поэтому число меньше исходных пятидесяти
    $companies = DB::table('employers')->count();

    expect($companies)->toBe(41)
        // вакансий и резюме по-прежнему по 50: часть компаний публикует по две
        ->and(DB::table('vacancies')->count())->toBe(50)
        ->and(DB::table('resumes')->count())->toBe(50);
});

/* Названия без реального прототипа не должны вернуться при повторном запуске. */
test('выдуманные компании в сидер не возвращаются', function () {
    $this->seed(TajikDataSeeder::class);

    $names = DB::table('employers')->pluck('company_name');

    foreach (['Баракат Маркет', 'Сомон Маркет', 'Барака Логистик', 'Академияи Рушд'] as $invented) {
        expect($names)->not->toContain($invented);
    }
});

test('в каталоге есть названные компании', function () {
    $this->seed(TajikDataSeeder::class);

    $names = DB::table('employers')->pluck('company_name');

    foreach (['Фаровон', 'Арча', 'Алиф Банк', 'Банки Эсхата'] as $expected) {
        expect($names)->toContain($expected);
    }
});

/* Главное требование: ни одной компании за пределами Таджикистана. */
test('все компании находятся в Таджикистане', function () {
    $this->seed(TajikDataSeeder::class);

    $outside = DB::table('employers')
        ->join('cities', 'employers.city_id', '=', 'cities.id')
        ->where('cities.country', '!=', 'Таджикистан')
        ->count();

    expect($outside)->toBe(0);
});

test('вакансии и резюме написаны по-таджикски', function () {
    $this->seed(TajikDataSeeder::class);

    // «лозим аст» и «Таҷрибаи корӣ» — таджикские обороты, в русском тексте их нет
    expect(DB::table('vacancies')->value('description'))->toContain('лозим аст')
        ->and(DB::table('resumes')->value('description'))->toContain('Таҷрибаи корӣ')
        ->and(DB::table('applicants')->value('about_me'))->toContain('Мутахассис аз шаҳри');
});

/* Сидер запускают руками и нередко дважды — дубли он плодить не должен. */
test('повторный запуск не создаёт дублей', function () {
    $this->seed(TajikDataSeeder::class);
    $this->seed(TajikDataSeeder::class);

    expect(DB::table('employers')->count())->toBe(41)
        ->and(DB::table('users')->where('email', 'like', '%@tj.workio.tj')->count())->toBe(91);
});
