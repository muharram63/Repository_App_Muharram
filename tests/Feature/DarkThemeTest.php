<?php

use App\Models\User;

/*
 * Тёмная тема кабинета.
 *
 * Раньше страницы кабинета начинались с <html lang="ru"> без атрибута темы,
 * поэтому выбор в настройках на них не влиял вовсе. Теперь атрибут ставит
 * общий партиал, а палитра переключается на уровне токенов.
 */

function cabinetHtml(string $theme): string
{
    $user = User::factory()->applicant()->create(['theme' => $theme]);
    makeApplicant($user);

    return test()->actingAs($user)->get('/applicant/profile')->assertOk()->getContent();
}

test('выбранная тёмная тема доезжает до кабинета', function () {
    expect(cabinetHtml('dark'))->toContain('data-theme="dark"');
});

test('выбранная светлая тема ставит атрибут явно', function () {
    expect(cabinetHtml('light'))->toContain('data-theme="light"');
});

/*
 * «Системная» — это не синоним светлой: атрибута быть не должно, иначе
 * media-запрос никогда не сработает и настройка устройства будет проигнорирована.
 */
test('системная тема не ставит атрибут и отдаётся на откуп устройству', function () {
    $html = cabinetHtml('system');

    // смотрим именно тег <html>: в самих стилях «data-theme» встречается
    // в селекторах, и поиск по всей странице ничего бы не доказал
    preg_match('/<html[^>]*>/', $html, $tag);

    expect($tag[0])->not->toContain('data-theme')
        ->and($html)->toContain('prefers-color-scheme: dark');
});

test('тёмная палитра и подсветка есть в стилях кабинета', function () {
    $html = cabinetHtml('dark');

    // поверхность карточки перестаёт быть белой
    expect($html)->toContain('--white:#151A2B')
        // и появляется то самое освещение
        ->and($html)->toContain('--card-glow')
        ->and($html)->toContain('box-shadow:var(--card-glow)')
        ->and($html)->toContain('box-shadow:var(--rail-glow)');
});

test('у работодателя тема работает так же', function () {
    $user = User::factory()->employer()->create(['theme' => 'dark']);
    makeEmployer($user);

    expect(test()->actingAs($user)->get('/employer/profile')->assertOk()->getContent())
        ->toContain('data-theme="dark"')
        ->toContain('--white:#151A2B');
});

/*
 * Цвета, которые раньше стояли числом прямо в правилах: на тёмной теме
 * светлая заливка плашки слепила, а красный на тёмном фоне становился грязным.
 */
test('плашки статусов и опасные действия ходят через токены', function () {
    $html = cabinetHtml('dark');

    expect($html)->toContain('background:var(--wash-good)')
        ->and($html)->toContain('background:var(--wash-warn)')
        ->and($html)->toContain('color:var(--danger)')
        // и у каждого есть тёмное значение, иначе плашка слепила бы
        ->and($html)->toContain('--wash-good:#0F2E20')
        ->and($html)->toContain('--wash-warn:#2E2415');
});

/*
 * Публичная часть. Раньше тему считали копипастой в двух файлах, и «системная»
 * молча означала светлую: атрибут ставился всегда.
 */

test('на публичных страницах тема тоже работает во всех трёх состояниях', function () {
    foreach (['dark' => 'data-theme="dark"', 'light' => 'data-theme="light"'] as $theme => $expected) {
        $user = User::factory()->applicant()->create(['theme' => $theme]);
        makeApplicant($user);

        expect($this->actingAs($user)->get('/')->assertOk()->getContent())->toContain($expected);
    }
});

test('системная тема на главной не ставит атрибут', function () {
    $user = User::factory()->applicant()->create(['theme' => 'system']);
    makeApplicant($user);

    $html = $this->actingAs($user)->get('/')->assertOk()->getContent();
    preg_match('/<html[^>]*>/', $html, $tag);

    expect($tag[0])->not->toContain('data-theme')
        ->and($html)->toContain('prefers-color-scheme: dark');
});

/* Гостю по-прежнему показываем светлую: так было и до правки. */
test('гость видит светлую тему', function () {
    $html = $this->get('/')->assertOk()->getContent();
    preg_match('/<html[^>]*>/', $html, $tag);

    expect($tag[0])->toContain('data-theme="light"');
});

test('каталоги наследуют тему от общего макета', function () {
    foreach (['/vacancies', '/resume', '/companies'] as $url) {
        expect($this->get($url)->assertOk()->getContent())
            ->toContain('prefers-color-scheme: dark');
    }
});

/*
 * Картинка в герое была голым <img> без единого правила — прямоугольник
 * с резкими краями поверх фона. Теперь у неё оболочка со скруглением,
 * маской краёв, подсветкой и затемнением на тёмной теме.
 */
test('картинка героя вписана в фон, а не наложена на него', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('class="hero-art"')
        ->and($html)->toContain('--art-radius')
        ->and($html)->toContain('mask-image:radial-gradient')
        // затемнение выключено на светлой теме и включено на тёмной
        ->and($html)->toContain('--art-dim:0')
        ->and($html)->toContain('--art-dim:.42');
});

/*
 * Два десятка правил вида «:root[data-theme="dark"] .x{color:var(--accent)}»
 * при системной теме не сработали бы вовсе — селектор требует атрибута.
 * Их заменил один токен.
 */
test('цвет акцентного текста живёт в токене, а не в правилах под тему', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('--accent-text')
        ->and($html)->not->toContain(':root[data-theme="dark"] .');
});

/*
 * Шапка и подвал. Их CSS-правила и так шли через токены, но логотип и подпись
 * были заданы инлайново в style="" — а инлайн перебивает любую тему, поэтому
 * на тёмном фоне текст оставался почти чёрным.
 */
test('шапка и подвал не держат цвета инлайном', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('color:#111827')
        ->and($html)->not->toContain('color:#6B7280')
        // логотип и подпись теперь берут цвет темы
        ->and($html)->toContain('color:var(--text);')
        ->and($html)->toContain('color:var(--text-muted);')
        ->and($html)->toContain('<footer>');
});

/*
 * На публичных страницах встречался индиго #4F46E5 из палитры кабинета —
 * чужой акцент, который не менялся вместе с темой.
 */
test('публичная часть пользуется своим акцентом', function () {
    foreach (['/', '/companies', '/resume'] as $url) {
        expect($this->get($url)->assertOk()->getContent())->not->toContain('#4F46E5');
    }
});

/*
 * Каталог компаний подключал bootstrap.css внутри секции контента — то есть
 * ПОСЛЕ стилей макета. Bootstrap переопределяет body (цвет и фон), a и
 * .container, поэтому на этой странице тема не работала вовсе, а подвал
 * получал почти чёрный текст и синие подчёркнутые ссылки.
 *
 * Нужен он был ради одного класса form-control на поле поиска.
 */
test('каталог компаний не тянет чужой CSS поверх темы', function () {
    $user = User::factory()->applicant()->create(['theme' => 'dark']);
    makeApplicant($user);

    $html = $this->actingAs($user)->get('/companies')->assertOk()->getContent();

    expect($html)->not->toContain('bootstrap.css')
        ->and($html)->not->toContain('class="form-control"')
        // поле поиска теперь своё и темизованное
        ->and($html)->toContain('class="company-search"')
        ->and($html)->toContain('data-theme="dark"')
        ->and($html)->toContain('<footer>');
});

/*
 * Ширины формы задавались в vh — это единицы ВЫСОТЫ окна, поэтому поле
 * и кнопки меняли ширину при изменении высоты браузера.
 */
test('ширины в каталоге компаний не зависят от высоты окна', function () {
    $html = $this->get('/companies')->assertOk()->getContent();

    expect($html)->not->toMatch('/width:\s*[\d.]+vh/');
});

