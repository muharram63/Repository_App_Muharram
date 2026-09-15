<?php

use App\Support\PublicNumbers;
use Database\Seeders\TajikDataSeeder;
use Database\Seeders\TajikResponsesSeeder;
use Illuminate\Support\Facades\DB;

/*
 * Отклики между таджикскими соискателями и компаниями.
 *
 * Цифра «вышли на работу» на странице статистики считается по строкам в базе,
 * поэтому поднять её можно только настоящими принятыми откликами.
 */

test('сидер создаёт отклики, приглашения и собеседования', function () {
    $this->seed(TajikDataSeeder::class);
    $this->seed(TajikResponsesSeeder::class);

    expect(DB::table('vacancy_responses')->count())->toBe(110)
        ->and(DB::table('resume_responses')->count())->toBe(55)
        ->and(DB::table('interviews')->count())->toBe(12);
});

test('счётчик «вышли на работу» растёт от реальных записей', function () {
    $this->seed(TajikDataSeeder::class);
    PublicNumbers::forget();

    expect(PublicNumbers::all()['headline']['hired'])->toBe(0);

    $this->seed(TajikResponsesSeeder::class);
    PublicNumbers::forget();

    $hired = PublicNumbers::all()['headline']['hired'];

    // 14 принятых откликов плюс 12 принятых приглашений
    expect($hired)->toBe(26)
        ->and($hired)->toBeGreaterThanOrEqual(20)
        // ровно столько же строк со статусом accepted лежит в базе
        ->and($hired)->toBe(
            DB::table('vacancy_responses')->where('status', 'accepted')->count()
            + DB::table('resume_responses')->where('status', 'accepted')->count()
        );
});

/*
 * Принятых должно быть заметно меньше прочих: на живой площадке большинство
 * откликов остаются без ответа или получают отказ.
 */
test('принятые — меньшая часть откликов', function () {
    $this->seed(TajikDataSeeder::class);
    $this->seed(TajikResponsesSeeder::class);

    $accepted = DB::table('vacancy_responses')->where('status', 'accepted')->count();
    $total = DB::table('vacancy_responses')->count();

    expect($accepted / $total)->toBeLessThan(0.25)
        ->and(DB::table('vacancy_responses')->where('status', 'new')->count())->toBeGreaterThan($accepted);
});

test('сидер не трогает чужие отклики и не плодит дубли', function () {
    $this->seed(TajikDataSeeder::class);
    $this->seed(TajikResponsesSeeder::class);
    $this->seed(TajikResponsesSeeder::class);

    expect(DB::table('vacancy_responses')->count())->toBe(110);

    // уникальная пара (соискатель, вакансия) — повтор уронил бы вставку
    $pairs = DB::table('vacancy_responses')->selectRaw('applicant_id, vacancy_id')->get();
    expect($pairs->unique(fn ($r) => $r->applicant_id.'-'.$r->vacancy_id)->count())->toBe(110);
});

test('собеседования заведены по принятым откликам', function () {
    $this->seed(TajikDataSeeder::class);
    $this->seed(TajikResponsesSeeder::class);

    $interviews = DB::table('interviews')->get();

    expect($interviews)->toHaveCount(12);

    foreach ($interviews as $interview) {
        $response = DB::table('vacancy_responses')
            ->where('applicant_id', $interview->applicant_id)
            ->where('vacancy_id', $interview->vacancy_id)
            ->first();

        expect($response?->status)->toBe('accepted');
    }
});

/*
 * Дата отклика и дата решения — разные события. Раньше updated_at у всех
 * строк ставился как «сейчас», и в списке трудоустройств все даты выглядели
 * одинаковыми — сегодняшними.
 */
test('у трудоустройств разные даты', function () {
    $this->seed(TajikDataSeeder::class);
    $this->seed(TajikResponsesSeeder::class);

    $dates = collect(App\Support\PublicNumbers::hires(100)->items())
        ->map(fn ($row) => substr($row->hired_at, 0, 10))
        ->unique();

    // 26 трудоустройств не могут приходиться на один-два дня
    expect($dates->count())->toBeGreaterThan(10);
});

test('решение приходит позже отклика и не в будущем', function () {
    $this->seed(TajikDataSeeder::class);
    $this->seed(TajikResponsesSeeder::class);

    $rows = DB::table('vacancy_responses')->where('status', '!=', 'new')->get();

    foreach ($rows as $row) {
        expect($row->updated_at)->toBeGreaterThan($row->created_at)
            ->and($row->updated_at)->toBeLessThanOrEqual(now()->toDateTimeString());
    }
});

/* Неотвеченный отклик решения ещё не получал — даты совпадают. */
test('у новых откликов дата решения равна дате отправки', function () {
    $this->seed(TajikDataSeeder::class);
    $this->seed(TajikResponsesSeeder::class);

    $new = DB::table('vacancy_responses')->where('status', 'new')->first();

    expect($new->updated_at)->toBe($new->created_at);
});

/*
 * Просмотры выводятся из активности, а не выдумываются и не остаются нулём.
 * У вакансии с откликами их не может быть ноль: каждый откликнувшийся
 * сначала открыл страницу.
 */
test('у вакансии с откликами просмотров больше нуля', function () {
    $this->seed(TajikDataSeeder::class);
    $this->seed(TajikResponsesSeeder::class);

    $withResponses = DB::table('vacancies')
        ->join('vacancy_responses', 'vacancies.id', '=', 'vacancy_responses.vacancy_id')
        ->select('vacancies.id', 'vacancies.views')
        ->distinct()
        ->get();

    expect($withResponses)->not->toBeEmpty();

    foreach ($withResponses as $vacancy) {
        expect($vacancy->views)->toBeGreaterThan(0);
    }
});

test('просмотров всегда не меньше, чем откликов', function () {
    $this->seed(TajikDataSeeder::class);
    $this->seed(TajikResponsesSeeder::class);

    $rows = DB::table('vacancies')
        ->leftJoin('vacancy_responses', 'vacancies.id', '=', 'vacancy_responses.vacancy_id')
        ->selectRaw('vacancies.id, vacancies.views, count(vacancy_responses.id) as responses')
        ->groupBy('vacancies.id', 'vacancies.views')
        ->get();

    foreach ($rows as $row) {
        expect($row->views)->toBeGreaterThanOrEqual($row->responses);
    }
});

/* Витрина не должна изображать посещаемость, которой нет. */
test('просмотры остаются скромными', function () {
    $this->seed(TajikDataSeeder::class);
    $this->seed(TajikResponsesSeeder::class);

    expect(DB::table('vacancies')->max('views'))->toBeLessThanOrEqual(App\Support\DemoViews::CEILING)
        ->and(DB::table('resumes')->max('views'))->toBeLessThanOrEqual(App\Support\DemoViews::CEILING);
});

