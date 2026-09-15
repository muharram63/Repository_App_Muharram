<?php

use App\Support\PublicNumbers;
use Database\Seeders\TajikDataSeeder;
use Illuminate\Support\Facades\DB;

/*
 * Публичная страница статистики: витрина платформы, открытая всем.
 */

beforeEach(fn () => PublicNumbers::forget());

test('страница открыта гостю и показывает ключевые цифры', function () {
    $this->seed(TajikDataSeeder::class);
    PublicNumbers::forget();

    $this->get('/statistics')
        ->assertOk()
        ->assertSee('Workio в цифрах')
        ->assertSee('человек вышли на работу')
        ->assertSee('активных вакансий');
});

test('графики нарисованы и не содержат битых координат', function () {
    $this->seed(TajikDataSeeder::class);
    PublicNumbers::forget();

    $html = $this->get('/statistics')->assertOk()->getContent();

    // линейный график, три бублика и кольцо навыков
    expect(substr_count($html, '<svg'))->toBeGreaterThanOrEqual(5)
        ->and($html)->not->toMatch('/points="[^"]*(NAN|INF)/i')
        ->and($html)->not->toContain(',,');
});

/*
 * На пустой базе страница обязана открываться: деление на ноль в долях
 * и в масштабе графика — самый вероятный способ её уронить.
 */
test('пустая база не роняет страницу', function () {
    $this->get('/statistics')
        ->assertOk()
        ->assertSee('Активных вакансий пока нет');
});

test('«вышли на работу» считает принятые отклики в обе стороны', function () {
    $user = App\Models\User::factory()->employer()->create();
    $employer = makeEmployer($user);
    $vacancy = makeVacancy($employer);

    $applicantUser = App\Models\User::factory()->applicant()->create();
    $applicant = makeApplicant($applicantUser);
    $resume = makeResume($applicant);

    DB::table('vacancy_responses')->insert([
        'applicant_id' => $applicant->id, 'vacancy_id' => $vacancy->id,
        'status' => 'accepted', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('resume_responses')->insert([
        'employer_id' => $employer->id, 'resume_id' => $resume->id,
        'status' => 'accepted', 'created_at' => now(), 'updated_at' => now(),
    ]);

    PublicNumbers::forget();

    expect(PublicNumbers::all()['headline']['hired'])->toBe(2);
});

test('доли по условиям работы в сумме дают все активные вакансии', function () {
    $this->seed(TajikDataSeeder::class);
    PublicNumbers::forget();

    $stats = PublicNumbers::all();
    $active = DB::table('vacancies')->where('status', 'active')->count();

    foreach ($stats['conditions'] as $block) {
        expect($block['total'])->toBe($active)
            ->and(array_sum(array_column($block['rows'], 'count')))->toBe($active);
    }
});

test('наружу не уходит ничего личного', function () {
    $this->seed(TajikDataSeeder::class);
    PublicNumbers::forget();

    $stats = PublicNumbers::all();
    $flat = json_encode($stats, JSON_UNESCAPED_UNICODE);

    // в выдаче только суммы: ни почты, ни телефонов, ни имён компаний
    expect($flat)->not->toContain('@')
        ->and($flat)->not->toContain('+992')
        ->and($flat)->not->toContain('Фаровон');
});

test('ссылка на статистику есть в шапке и подвале', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect(substr_count($html, route('public.stats')))->toBeGreaterThanOrEqual(2);
});

/*
 * Доля сданных заданий. Это отношение сданных попыток к завершённым, где
 * «сдано» — результат не ниже порога. На малом числе попыток такой процент
 * вводит в заблуждение: одна работа двигает его на десятки пунктов, а крупная
 * цифра читается как показатель всей площадки.
 */

/** Завести n завершённых попыток, из них passed — сданных. */
function seedAttempts(int $total, int $passed): void
{
    $skill = App\Models\Skill::firstOrCreate(['name' => 'PHP'], ['area' => 'Разработка']);
    $test = App\Models\SkillTest::create([
        'skill_id' => $skill->id, 'level' => 'confident', 'variant' => 1,
        'questions' => [['text' => 'q', 'options' => ['a', 'b'], 'answer' => 0, 'explain' => '']],
    ]);

    for ($i = 0; $i < $total; $i++) {
        $user = App\Models\User::factory()->applicant()->create();
        $applicant = makeApplicant($user);

        App\Models\SkillAttempt::create([
            'applicant_id' => $applicant->id,
            'skill_test_id' => $test->id,
            'answers' => [],
            'score' => $i < $passed ? 80 : 20,
            'expires_at' => now()->addHour(),
            'finished_at' => now(),
        ]);
    }
}

test('при малом числе попыток показываем сами числа, а не процент', function () {
    seedAttempts(4, 3);
    PublicNumbers::forget();

    $html = $this->get('/statistics')->assertOk()->getContent();

    // 3 из 4 — это 75%, но выборка слишком мала, чтобы показывать долю крупно
    expect($html)->toContain('3 / 4')
        ->and($html)->toContain('Попыток пока мало')
        ->and($html)->not->toContain('>75%<');
});

test('при достаточном числе попыток показываем процент', function () {
    seedAttempts(20, 15);
    PublicNumbers::forget();

    $html = $this->get('/statistics')->assertOk()->getContent();

    expect($html)->toContain('>75%<')
        ->and($html)->not->toContain('Попыток пока мало');
});

test('доля считается от завершённых попыток по порогу', function () {
    seedAttempts(10, 7);
    PublicNumbers::forget();

    $skills = PublicNumbers::all()['skills'];

    expect($skills['attempts'])->toBe(10)
        ->and($skills['passed'])->toBe(7)
        ->and($skills['rate'])->toBe(70)
        ->and($skills['passing'])->toBe(App\Models\SkillAttempt::PASSING);
});

/*
 * Список трудоустройств. Цифра «вышли на работу» должна быть проверяемой:
 * по клику открывается, из чего она сложилась.
 */

test('плитка «вышли на работу» ведёт на список', function () {
    $this->seed(TajikDataSeeder::class);
    PublicNumbers::forget();

    $this->get('/statistics')
        ->assertOk()
        ->assertSee(route('public.stats.hired'), false)
        ->assertSee('Посмотреть список');
});

test('в списке ровно столько строк, сколько показывает счётчик', function () {
    $this->seed(TajikDataSeeder::class);
    $this->seed(Database\Seeders\TajikResponsesSeeder::class);
    PublicNumbers::forget();

    $hired = PublicNumbers::all()['headline']['hired'];
    $html = $this->get('/statistics/hired')->assertOk()->getContent();

    expect(substr_count($html, 'class="hr-row"'))->toBe($hired)
        ->and(PublicNumbers::hires()->total())->toBe($hired);
});

test('видно, откуда взялось трудоустройство — отклик или приглашение', function () {
    $this->seed(TajikDataSeeder::class);
    $this->seed(Database\Seeders\TajikResponsesSeeder::class);
    PublicNumbers::forget();

    $html = $this->get('/statistics/hired')->assertOk()->getContent();

    expect(substr_count($html, 'по отклику'))->toBe(14)
        ->and(substr_count($html, 'по приглашению'))->toBe(12);
});

/*
 * Владелец площадки решил показывать имена и должности. Наружу при этом
 * идёт только это: контактов, почты и зарплаты в списке быть не должно.
 */
test('в списке видно имя и должность', function () {
    $this->seed(TajikDataSeeder::class);
    $this->seed(Database\Seeders\TajikResponsesSeeder::class);
    PublicNumbers::forget();

    $html = $this->get('/statistics/hired')->assertOk()->getContent();
    $first = PublicNumbers::hires()->items()[0];

    expect($html)->toContain($first->person)
        ->toContain($first->role)
        ->toContain($first->company)
        ->and(substr_count($html, 'class="hr-person"'))->toBe(substr_count($html, 'class="hr-row"'));
});

test('список не раскрывает контакты и зарплаты', function () {
    $this->seed(TajikDataSeeder::class);
    $this->seed(Database\Seeders\TajikResponsesSeeder::class);
    PublicNumbers::forget();

    $html = $this->get('/statistics/hired')->assertOk()->getContent();

    expect($html)->not->toContain('@tj.workio.tj')
        ->and($html)->not->toContain('+992')
        ->and($html)->not->toContain('TJS');
});

test('пустая база не роняет список', function () {
    $this->get('/statistics/hired')
        ->assertOk()
        ->assertSee('Пока никого не приняли');
});

/*
 * Счётчик и список должны показывать одно и то же. Раньше ключевые цифры
 * лежали в том же кэше, что и тяжёлые агрегаты: статус отклика меняется
 * в любую минуту, и плитка отставала на четверть часа — на странице стояло
 * 28 при 26 в базе.
 */
test('счётчик не отстаёт от базы', function () {
    $this->seed(TajikDataSeeder::class);
    $this->seed(Database\Seeders\TajikResponsesSeeder::class);

    $before = PublicNumbers::all()['headline']['hired'];

    // меняем статус одного принятого отклика — счётчик обязан это заметить
    DB::table('vacancy_responses')->where('status', 'accepted')->limit(1)->update(['status' => 'rejected']);

    expect(PublicNumbers::all()['headline']['hired'])->toBe($before - 1);
});

test('заголовок списка совпадает с числом строк', function () {
    $this->seed(TajikDataSeeder::class);
    $this->seed(Database\Seeders\TajikResponsesSeeder::class);

    DB::table('resume_responses')->where('status', 'accepted')->limit(2)->update(['status' => 'viewed']);

    $html = $this->get('/statistics/hired')->assertOk()->getContent();
    preg_match('/<span class="count">(\d+)<\/span>/', $html, $m);

    expect((int) $m[1])->toBe(substr_count($html, 'class="hr-row"'))
        ->and((int) $m[1])->toBe(PublicNumbers::all()['headline']['hired']);
});

test('у таблицы есть шапка колонок', function () {
    $this->seed(TajikDataSeeder::class);
    $this->seed(Database\Seeders\TajikResponsesSeeder::class);

    $this->get('/statistics/hired')
        ->assertOk()
        ->assertSee('hr-head-row', false)
        ->assertSee('Кандидат и должность')
        ->assertSee('Компания')
        ->assertSee('Город');
});

/*
 * Плитка активных вакансий ведёт в каталог — отдельный список не нужен,
 * каталог показывает ровно те же вакансии.
 */
test('плитка «активных вакансий» ведёт в каталог', function () {
    $this->seed(TajikDataSeeder::class);
    PublicNumbers::forget();

    $this->get('/statistics')
        ->assertOk()
        ->assertSee(route('public.vacancies.index'), false);
});

/*
 * Счётчик и каталог должны считать по одному правилу. Одного status = active
 * мало: вакансии заблокированных компаний каталог прячет, и если статистика
 * их считает, цифра на плитке разойдётся со списком по клику.
 */
test('счётчик вакансий совпадает с каталогом и после блокировки', function () {
    $this->seed(TajikDataSeeder::class);
    PublicNumbers::forget();

    $catalogue = fn () => App\Models\Vacancy::where('status', 'active')
        ->whereHas('employer.user', fn ($u) => $u->activeAccount())
        ->count();

    expect(PublicNumbers::all()['headline']['vacancies'])->toBe($catalogue());

    // блокируем одну компанию — её вакансии пропадают из каталога
    $employer = App\Models\Employer::has('vacancies')->first();
    App\Models\User::where('id', $employer->user_id)->update(['status' => 'blocked']);

    PublicNumbers::forget();

    expect(PublicNumbers::all()['headline']['vacancies'])->toBe($catalogue());
});

test('распределения считают те же вакансии, что и счётчик', function () {
    $this->seed(TajikDataSeeder::class);
    PublicNumbers::forget();

    $stats = PublicNumbers::all();

    foreach ($stats['conditions'] as $block) {
        expect($block['total'])->toBe($stats['headline']['vacancies']);
    }
});

