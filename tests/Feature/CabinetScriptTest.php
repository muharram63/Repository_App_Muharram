<?php

/*
 * Общий скрипт кабинета не должен падать на страницах без своих элементов.
 *
 * Он остался от статического макета и искал #profileForm, которого нет ни на
 * одной из страниц, где он подключён. Обращение к null роняло весь блок, и
 * вместе с ним не доживал до регистрации обработчик выхода — а тот уводил бы
 * на несуществующий index.html вместо настоящего выхода из аккаунта.
 */

test('скрипт кабинета не обращается к элементам вслепую', function () {
    foreach (['applicant', 'employer'] as $cabinet) {
        $js = file_get_contents(resource_path('views/'.$cabinet.'/partials/js.blade.php'));

        // getElementById сразу с точкой — это и есть обращение без проверки
        expect($js)->not->toMatch('/getElementById\([^)]*\)\s*\./',
            $cabinet.': есть обращение к элементу без проверки');

        // выход — обычная форма, перехватывать его скриптом нельзя
        expect($js)->not->toContain('logoutBtn', $cabinet.': скрипт снова трогает кнопку выхода')
            ->and($js)->not->toContain('index.html', $cabinet.': остался уход на index.html');
    }
});

test('#profileForm не существует ни на одной странице кабинета', function () {
    $pages = glob(resource_path('views/{applicant,employer}/pages/**/*.blade.php'), GLOB_BRACE);

    foreach ($pages as $page) {
        expect(file_get_contents($page))->not->toContain('id="profileForm"', basename($page));
    }
});
