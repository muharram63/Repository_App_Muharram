<?php

use App\Support\MatchCriteria;

/**
 * Совпадение считается по трём критериям: профессия, навыки и языки.
 * Оценивает их модель, а сложение весов — обычная арифметика, поэтому
 * проверяется без обращения к ИИ.
 */

test('ответ модели превращается в критерий, а молчание — в unknown', function () {
    $ok = MatchCriteria::fromModel('role', 'Профессия', ['state' => 'ok', 'note' => 'то же направление']);
    expect($ok['state'])->toBe('ok')
        ->and($ok['label'])->toBe('Профессия')
        ->and($ok['note'])->toBe('то же направление');

    // вакансия молчит о языках — модель поле не заполняет, критерий не учитывается
    expect(MatchCriteria::fromModel('languages', 'Языки', null)['state'])->toBe('unknown');

    // и мусор в состоянии тоже не должен превращаться в оценку
    expect(MatchCriteria::fromModel('languages', 'Языки', ['state' => 'наверное'])['state'])->toBe('unknown');
});

test('оценка взвешивается по критериям, о которых есть данные', function () {
    expect(MatchCriteria::score([
        ['key' => 'role', 'state' => 'ok'],
        ['key' => 'skills', 'state' => 'ok'],
        ['key' => 'languages', 'state' => 'ok'],
    ]))->toBe(100);

    // навыки весомее языка: провал по навыкам роняет результат сильнее
    $noSkills = MatchCriteria::score([
        ['key' => 'skills', 'state' => 'no'],
        ['key' => 'languages', 'state' => 'ok'],
    ]);
    $noLanguages = MatchCriteria::score([
        ['key' => 'skills', 'state' => 'ok'],
        ['key' => 'languages', 'state' => 'no'],
    ]);

    expect($noSkills)->toBeLessThan($noLanguages);

    // критерий без данных не занижает оценку, а выпадает из расчёта
    expect(MatchCriteria::score([
        ['key' => 'skills', 'state' => 'ok'],
        ['key' => 'languages', 'state' => 'unknown'],
    ]))->toBe(100);

    // считать не из чего — числа нет вовсе
    expect(MatchCriteria::score([['key' => 'languages', 'state' => 'unknown']]))->toBeNull();
});

test('частичное совпадение считается за половину', function () {
    expect(MatchCriteria::score([['key' => 'skills', 'state' => 'partial']]))->toBe(50);
});

/*
 * Город, зарплата и стаж из разбора убраны намеренно: это условия и «корочки»,
 * а не признак того, что человек справится с работой. Даже если такая строка
 * каким-то образом окажется в списке, на процент она влиять не должна.
 */
test('город, зарплата и стаж не имеют веса', function () {
    expect(MatchCriteria::WEIGHTS)->toHaveKeys(['role', 'skills', 'languages'])
        ->and(MatchCriteria::WEIGHTS)->not->toHaveKey('city')
        ->and(MatchCriteria::WEIGHTS)->not->toHaveKey('salary')
        ->and(MatchCriteria::WEIGHTS)->not->toHaveKey('experience');

    // провал по снятому критерию не должен уронить стопроцентное совпадение
    expect(MatchCriteria::score([
        ['key' => 'skills', 'state' => 'ok'],
        ['key' => 'city', 'state' => 'no'],
        ['key' => 'salary', 'state' => 'no'],
        ['key' => 'experience', 'state' => 'no'],
    ]))->toBe(100);
});

/*
 * Профессия работает как ворота, а не как слагаемое.
 *
 * Повару не поможет знание английского, если ищут электрика: направление
 * другое — совпадения нет. Навыки и языки при этом остаются в разборе,
 * чтобы было видно, что у человека есть, просто в процент это не идёт.
 */
test('другая профессия обнуляет совпадение', function () {
    $score = MatchCriteria::score([
        ['key' => 'role', 'state' => 'no'],
        ['key' => 'skills', 'state' => 'ok'],
        ['key' => 'languages', 'state' => 'ok'],
    ]);

    // навыки и язык совпали полностью, но направление другое
    expect($score)->toBe(0);
});

test('совпавшая профессия открывает счёт навыкам и языкам', function () {
    $all = MatchCriteria::score([
        ['key' => 'role', 'state' => 'ok'],
        ['key' => 'skills', 'state' => 'ok'],
        ['key' => 'languages', 'state' => 'ok'],
    ]);

    $noSkills = MatchCriteria::score([
        ['key' => 'role', 'state' => 'ok'],
        ['key' => 'skills', 'state' => 'no'],
        ['key' => 'languages', 'state' => 'ok'],
    ]);

    $noLanguages = MatchCriteria::score([
        ['key' => 'role', 'state' => 'ok'],
        ['key' => 'skills', 'state' => 'ok'],
        ['key' => 'languages', 'state' => 'no'],
    ]);

    expect($all)->toBe(100)
        // 30 за профессию и 15 за язык из 100
        ->and($noSkills)->toBe(45)
        // 30 за профессию и 55 за навыки
        ->and($noLanguages)->toBe(85);
});

/*
 * Частичное совпадение воротами не считается: «тестировщик» на
 * «backend-разработчика» — переход реальный, и такой случай считается
 * обычным взвешенным средним.
 */
test('смежная профессия совпадение не обнуляет', function () {
    $score = MatchCriteria::score([
        ['key' => 'role', 'state' => 'partial'],
        ['key' => 'skills', 'state' => 'ok'],
        ['key' => 'languages', 'state' => 'ok'],
    ]);

    expect($score)->toBe(85)
        ->and($score)->toBeGreaterThan(0);
});

test('без данных о профессии ворота не срабатывают', function () {
    $score = MatchCriteria::score([
        ['key' => 'role', 'state' => 'unknown'],
        ['key' => 'skills', 'state' => 'ok'],
        ['key' => 'languages', 'state' => 'ok'],
    ]);

    // профессию сравнить не удалось — считаем по тому, что известно
    expect($score)->toBe(100);
});

