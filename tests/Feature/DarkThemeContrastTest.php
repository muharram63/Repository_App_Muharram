<?php

/*
 * Тёмная тема: текст не должен сливаться с фоном.
 *
 * Оформление кабинета держится на токенах: --ink для текста, --white для
 * поверхности карточки, и обе переключаются вместе. Ломается это двумя
 * способами, и оба здесь проверяются.
 *
 * Первый — страница со своим оформлением объявляет собственный :root уже после
 * общей темы и перебивает общий токен. Тёмная тема на такой странице
 * выключается наполовину: поверхность остаётся белой, а текст переключается в
 * светлый — или наоборот.
 *
 * Второй — общий компонент не задаёт себе цвет текста и наследует его от body,
 * а страница body переопределила. Выходит тёмный текст на тёмной карточке.
 */

use App\Models\User;

/** Страницы кабинета со своим оформлением — у них и бывают такие срывы. */
function cabinetPages(): array
{
    return [
        'applicant/pages/resumes/create.blade.php',
        'applicant/pages/resumes/edit.blade.php',
        'applicant/pages/resumes/assistant.blade.php',
        'applicant/pages/skills/index.blade.php',
        'applicant/pages/skills/attempt.blade.php',
        'employer/pages/vacancies/create.blade.php',
        'employer/pages/vacancies/edit.blade.php',
    ];
}

/** Токены общей темы: их переключает css.blade.php, страницам трогать нельзя. */
function themeTokens(): array
{
    return [
        '--ink', '--white', '--bg', '--gray-500', '--gray-300', '--gray-200',
        '--gray-100', '--indigo-600', '--indigo-700', '--green', '--danger',
        '--wash-danger', '--wash-good', '--ink-good', '--wash-warn', '--ink-warn',
        '--radius',
    ];
}

test('страницы кабинета не перебивают токены общей темы', function () {
    $broken = [];

    foreach (cabinetPages() as $page) {
        $source = file_get_contents(resource_path('views/'.$page));

        // страница с собственным переключателем тем отвечает за себя сама
        if (str_contains($source, 'prefers-color-scheme')) {
            continue;
        }

        preg_match_all('/:root\s*\{([^}]*)\}/s', $source, $blocks);

        foreach ($blocks[1] as $body) {
            foreach (themeTokens() as $token) {
                if (preg_match('/'.preg_quote($token, '/').'\s*:/', $body)) {
                    $broken[] = $page.' → '.$token;
                }
            }
        }
    }

    /*
     * Объявление страницы идёт после общей темы и побеждает при равной
     * специфичности. Так тёмная тема и выключалась наполовину: --white
     * оставался белым, а текст меню уже был светлым.
     */
    expect($broken)->toBe([], implode(' | ', $broken));
});

test('общие поверхности задают цвет текста сами, а не наследуют его', function () {
    foreach (['applicant', 'employer'] as $cabinet) {
        $css = file_get_contents(resource_path('views/'.$cabinet.'/partials/css.blade.php'));

        // комментарии убираем: внутри встречается «body{color}», и поиск
        // закрывающей скобки обрывался бы на нём
        $css = preg_replace('#/\*.*?\*/#s', '', $css);

        foreach (['.sidebar', '.user-chip', '.card', '.stat-card'] as $selector) {
            preg_match('/^[ \t]*'.preg_quote($selector, '/').'\{(.*?)\}/ms', $css, $found);

            expect($found)->not->toBeEmpty($cabinet.': не нашёлся '.$selector);

            /*
             * Иначе цвет приходит от body, а его страница со своим оформлением
             * переопределяет: карточка переключилась в тёмную, текст остался
             * тёмным, и прочитать нельзя ничего.
             */
            expect($found[1])->toMatch('/color\s*:\s*var\(--ink\)/',
                $cabinet.': '.$selector.' наследует цвет текста от body');
        }
    }
});

test('тёмная тема доезжает до страницы резюме целиком', function () {
    $user = User::factory()->applicant()->create();
    $user->forceFill(['theme' => 'dark'])->save();
    makeApplicant($user);

    $html = $this->actingAs($user)->get(route('applicant.resume.create'))->assertOk()->getContent();

    expect($html)->toContain('data-theme="dark"');

    // поверхность карточки осталась тёмной — страница её больше не перекрашивает
    $dark = mb_strpos($html, '--white:#151A2B');
    $page = mb_strpos($html, '--white: #FFFFFF');

    expect($dark)->not->toBeFalse('в разметке нет тёмного значения --white');
    expect($page)->toBeFalse('страница снова объявляет свой --white после темы');
});

test('итог проверки навыков красится токенами, а не намертво', function () {
    foreach ([
        'applicant/pages/skills/index.blade.php',
        'applicant/pages/skills/attempt.blade.php',
    ] as $page) {
        $source = file_get_contents(resource_path('views/'.$page));

        foreach (explode("\n", $source) as $number => $line) {
            // цвет без собственного светлого фона ложится на карточку,
            // а она в тёмной теме тёмная
            if (! preg_match('/color\s*:\s*#(0|1|2|3)[0-9a-fA-F]{5}/i', $line)) {
                continue;
            }

            if (preg_match('/background(-color)?\s*:\s*#(F|E|D)/i', $line)) {
                continue;
            }

            expect(true)->toBeFalse($page.':'.($number + 1).' — '.trim($line));
        }
    }
});
