<?php

/*
 * Полнота перевода интерфейса.
 *
 * Строка, обёрнутая в __(), но отсутствующая в словаре, молча показывается
 * по-русски — переключение языка выглядит сломанным, хотя ошибок нигде нет.
 * Тест ловит именно это: любая новая строка обязана получить перевод.
 */

/** Все строки, обёрнутые в __() в шаблонах и коде. */
function translatableStrings(): array
{
    $found = [];

    foreach (['resources/views', 'app'] as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir)));

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            foreach (["/__\(\s*'([^']*)'/u", '/__\(\s*"([^"]*)"/u'] as $pattern) {
                if (preg_match_all($pattern, $source, $matches)) {
                    foreach ($matches[1] as $key) {
                        $found[$key] = true;
                    }
                }
            }
        }
    }

    return array_keys($found);
}

test('каждая строка интерфейса переведена', function (string $locale) {
    $dictionary = json_decode(file_get_contents(base_path('lang/'.$locale.'.json')), true);
    $missing = array_values(array_diff(translatableStrings(), array_keys($dictionary)));

    expect($missing)->toBe([], "Нет перевода ({$locale}): ".implode(' | ', array_slice($missing, 0, 15)));
})->with(['tg', 'en']);

test('файлы словарей — валидный JSON', function () {
    foreach (['ru', 'en', 'tg'] as $locale) {
        $raw = file_get_contents(base_path('lang/'.$locale.'.json'));

        expect(json_decode($raw, true))->toBeArray()
            ->and(json_last_error())->toBe(JSON_ERROR_NONE);
    }
});

test('переключение на таджикский действительно меняет интерфейс', function () {
    app()->setLocale('tg');

    expect(__('Статистика'))->toBe('Омор')
        ->and(__('Проверка навыков'))->toBe('Санҷиши маҳоратҳо')
        ->and(__('Вакансии'))->not->toBe('Вакансии');
});

/*
 * Отдельный класс дыры: подписи, которые живут в PHP-массивах и выводятся
 * через __($переменная). Сканер литералов их не видит, поэтому страница
 * статистики показывала «Занятость» и «Требуемый опыт» по-русски даже при
 * полном словаре. Здесь проверяем сами источники подписей.
 */
test('подписи из справочников переведены', function (string $locale) {
    $labels = [];

    foreach (App\Support\AdminNumbers::DISTRIBUTIONS as [$model, $column, $set]) {
        $labels = array_merge($labels, array_values($set));
    }

    foreach ([
        App\Models\Skill::LEVELS,
        App\Models\Resume::STYLES,
        App\Models\SkillTest::LENGTHS,
        App\Models\AdminComment::TOPICS,
        App\Models\AdminComment::STATUSES,
        App\Models\Complaint::REASONS,
        App\Models\Complaint::STATUSES,
        App\Models\Complaint::TARGETS,
        App\Models\ComplaintAction::LABELS,
    ] as $set) {
        $labels = array_merge($labels, array_values($set));
    }

    // заголовки блоков публичной статистики
    $labels = array_merge($labels, ['Занятость', 'График работы', 'Требуемый опыт', 'Прочее']);

    $dictionary = json_decode(file_get_contents(base_path('lang/'.$locale.'.json')), true);
    $missing = array_values(array_diff(array_unique($labels), array_keys($dictionary)));

    expect($missing)->toBe([], "Подписи без перевода ({$locale}): ".implode(' | ', $missing));
})->with(['tg', 'en']);

/*
 * Подписи переводятся внутри моделей, а не на каждом месте вывода: иначе
 * пришлось бы оборачивать десятки вызовов и один всегда бы забылся.
 */
test('модели отдают подписи уже переведёнными', function () {
    app()->setLocale('tg');

    $interview = new App\Models\Interview(['status' => 'confirmed']);
    expect($interview->statusLabel())->toBe('Тасдиқ шуд');

    $test = new App\Models\SkillTest(['level' => 'confident']);
    expect($test->levelLabel())->toBe('Боэътимод');

    $action = new App\Models\ComplaintAction(['action' => 'resolved']);
    expect($action->label())->not->toBe('Отметил решённой');
});

test('страница статистики переключается на таджикский целиком', function () {
    session(['locale' => 'tg']);

    $html = $this->get('/statistics')->assertOk()->getContent();

    foreach (['>Занятость<', '>Требуемый опыт<', '>График работы<', '>Статистика<'] as $russian) {
        expect($html)->not->toContain($russian);
    }

    expect($html)->toContain('Шуғл')
        ->and($html)->toContain('Омор');
});

/*
 * Английский должен переключать интерфейс так же полно, как таджикский.
 */
test('переключение на английский меняет интерфейс', function () {
    app()->setLocale('en');

    expect(__('Статистика'))->toBe('Statistics')
        ->and(__('Проверка навыков'))->toBe('Skill checks')
        ->and(__('человек вышли на работу'))->toBe('people started a job');
});

test('страница статистики переключается на английский целиком', function () {
    session(['locale' => 'en']);

    $html = $this->get('/statistics')->assertOk()->getContent();

    foreach (['>Занятость<', '>Требуемый опыт<', '>График работы<', '>Статистика<'] as $russian) {
        expect($html)->not->toContain($russian);
    }

    expect($html)->toContain('Employment type')
        ->and($html)->toContain('Experience required')
        ->and($html)->toContain('Statistics');
});

test('модели отдают подписи по-английски', function () {
    app()->setLocale('en');

    expect((new App\Models\Interview(['status' => 'confirmed']))->statusLabel())->toBe('Confirmed')
        ->and((new App\Models\SkillTest(['level' => 'confident']))->levelLabel())->toBe('Confident');
});

/*
 * Города — справочные данные с русскими названиями. Переводим их при выводе,
 * а не в модели: поле region редактируется в админке, и подмена значения
 * ломала бы сохранение формы.
 */
test('названия городов и страна переведены', function (string $locale) {
    $dictionary = json_decode(file_get_contents(base_path('lang/'.$locale.'.json')), true);

    $regions = App\Models\City::pluck('region')->all();
    $missing = array_values(array_diff($regions, array_keys($dictionary)));

    expect($missing)->toBe([], "Города без перевода ({$locale}): ".implode(' | ', $missing))
        ->and($dictionary)->toHaveKey('Таджикистан');
})->with(['tg', 'en']);

test('город переводится на страницах каталога', function () {
    $user = App\Models\User::factory()->employer()->create(['locale' => 'tg']);
    $employer = makeEmployer($user);
    makeVacancy($employer);

    session(['locale' => 'tg']);
    $html = $this->get('/vacancies')->assertOk()->getContent();

    $region = $employer->city->region;

    expect($html)->toContain(__($region, [], 'tg'))
        // русское написание в разметку попасть не должно
        ->and($html)->not->toContain('>'.$region.'<');
});

/*
 * Категории и отрасли — такие же справочные данные, как города: в базе
 * они по-русски, поэтому переводятся при выводе.
 */
test('категории и отрасли переведены', function (string $locale) {
    $dictionary = json_decode(file_get_contents(base_path('lang/'.$locale.'.json')), true);

    $names = array_merge(
        App\Models\Category::pluck('name')->all(),
        App\Models\Industry::pluck('name')->all()
    );

    // служебные заглушки вида «industry 3» переводить незачем
    $names = array_filter($names, fn ($n) => ! preg_match('/^(category|industry)\s*\d+$/i', $n));

    $missing = array_values(array_diff($names, array_keys($dictionary)));

    expect($missing)->toBe([], "Справочники без перевода ({$locale}): ".implode(' | ', $missing));
})->with(['tg', 'en']);

/*
 * «Образование» — метка поля анкеты (уровень образования человека), и это
 * по-таджикски «маълумот». Отрасль же называется «маориф». Один ключ на два
 * смысла давал неверный перевод, поэтому категорию переименовали.
 */
test('образование как поле и как отрасль переводятся по-разному', function () {
    app()->setLocale('tg');

    expect(__('Образование'))->toBe('Маълумот')
        ->and(__('Образование и наука'))->toBe('Маориф ва илм')
        ->and(__('Обучение и курсы'))->toBe('Таълим ва курсҳо');
});

