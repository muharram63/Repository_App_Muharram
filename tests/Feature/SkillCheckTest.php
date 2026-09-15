<?php

use App\Models\Skill;
use App\Models\SkillAttempt;
use App\Models\SkillTest;
use App\Models\User;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\SkillExaminer;

/**
 * Заглушка экзаменатора: тесты не ходят в сеть.
 * Считает обращения — по ним видно, что банк заданий работает.
 */
class FakeExaminer implements SkillExaminer
{
    public int $composed = 0;

    public int $graded = 0;

    public function __construct(
        private readonly bool $available = true,
        private readonly ?string $failure = null,
        private readonly bool $acceptFree = true,
    ) {
    }

    public function available(): bool
    {
        return $this->available;
    }

    public function compose(Skill $skill, string $level, int $variant, int $count): array
    {
        if ($this->failure) {
            throw new AiUnavailableException($this->failure);
        }

        $this->composed++;

        return collect(range(1, $count))->map(fn (int $n) => [
            'text' => 'Вопрос '.$n.' про '.$skill->name.' (вариант '.$variant.')',
            'options' => ['Верно', 'Неверно A', 'Неверно B', 'Неверно C'],
            'answer' => 0,
            'explain' => 'Потому что так принято.',
        ])->all();
    }

    public function grade(Skill $skill, array $answers): array
    {
        if ($this->failure) {
            throw new AiUnavailableException($this->failure);
        }

        $this->graded++;

        return collect($answers)->map(fn () => [
            'correct' => $this->acceptFree,
            'comment' => $this->acceptFree ? 'По смыслу верно.' : 'Ответ не про то.',
        ])->all();
    }
}

/**
 * Соискатель с анкетой и подменённым экзаменатором.
 */
function skillUser(SkillExaminer $examiner): User
{
    app()->instance(SkillExaminer::class, $examiner);

    $user = User::factory()->applicant()->create();
    makeApplicant($user);

    return $user;
}

function someSkill(): Skill
{
    return Skill::firstOrCreate(['name' => 'PHP'], ['area' => 'Разработка']);
}

/** Начать проверку и вернуть заведённую попытку. */
function startCheck(User $user, string $level = 'confident'): SkillAttempt
{
    test()->actingAs($user)->post('/applicant/skills/start', [
        'skill_id' => someSkill()->id,
        'level' => $level,
    ])->assertRedirect();

    return SkillAttempt::latest('id')->first();
}

test('the skills page lists the catalogue', function () {
    $user = skillUser(new FakeExaminer);
    someSkill();

    $this->actingAs($user)->get('/applicant/skills')
        ->assertOk()
        ->assertSee('PHP')
        ->assertSee('Начать проверку');
});

test('starting a check composes a test once and reuses it afterwards', function () {
    $examiner = new FakeExaminer;
    $first = skillUser($examiner);

    startCheck($first);

    expect($examiner->composed)->toBe(1)
        ->and(SkillTest::count())->toBe(1);

    // другому кандидату то же задание достаётся из банка, без новой генерации
    $second = skillUser($examiner);
    startCheck($second);

    expect($examiner->composed)->toBe(1)
        ->and(SkillTest::count())->toBe(1);
});

test('a repeat attempt gets a variant the candidate has not seen', function () {
    $examiner = new FakeExaminer;
    $user = skillUser($examiner);

    $one = startCheck($user);
    $this->actingAs($user)->post('/applicant/skills/attempt/'.$one->id, ['option' => [0 => 0]]);

    // повтор возможен только после паузы
    $this->travelTo(now()->addMinutes(SkillAttempt::COOLDOWN_MINUTES + 1));

    $two = startCheck($user);

    // повторять то же задание бессмысленно — составлен новый вариант
    expect($two->skill_test_id)->not->toBe($one->skill_test_id)
        ->and($examiner->composed)->toBe(2);
});

test('the correct answers never reach the page while the test is unfinished', function () {
    $user = skillUser(new FakeExaminer);
    $attempt = startCheck($user);

    $html = $this->actingAs($user)->get('/applicant/skills/attempt/'.$attempt->id)
        ->assertOk()
        ->assertSee('Вопрос 1')
        ->assertSee('Другое — ответьте своими словами')
        ->getContent();

    // подсказки и ключ остаются на сервере
    expect($html)->not->toContain('Потому что так принято');
});

test('picked options are graded by the key, without asking the model', function () {
    $examiner = new FakeExaminer;
    $user = skillUser($examiner);
    $attempt = startCheck($user);

    $this->actingAs($user)->post('/applicant/skills/attempt/'.$attempt->id, [
        // три верных из пяти
        'option' => [0 => 0, 1 => 0, 2 => 0, 3 => 1, 4 => 2],
    ])->assertRedirect(route('applicant.skills.attempt', $attempt, absolute: false));

    $attempt->refresh();

    expect($attempt->score)->toBe(60)
        ->and($attempt->finished_at)->not->toBeNull()
        // свободных ответов не было — модель не понадобилась
        ->and($examiner->graded)->toBe(0);
});

test('a free answer is graded by the model and outranks the picked option', function () {
    $examiner = new FakeExaminer;
    $user = skillUser($examiner);
    $attempt = startCheck($user);

    $this->actingAs($user)->post('/applicant/skills/attempt/'.$attempt->id, [
        // на первом вопросе выбран заведомо неверный вариант, но написан свой ответ
        'option' => [0 => 3, 1 => 0, 2 => 0, 3 => 0, 4 => 0],
        'own' => [0 => 'Я бы сделал по-своему, и вот почему'],
    ])->assertRedirect();

    $attempt->refresh();

    // свой ответ зачтён — значит пятёрка из пяти, а не четыре
    expect($attempt->score)->toBe(100)
        ->and($attempt->review[0]['correct'])->toBeTrue()
        ->and($attempt->review[0]['comment'])->toBe('По смыслу верно.')
        // все свободные ответы уходят одним запросом
        ->and($examiner->graded)->toBe(1);
});

test('an unanswered question is simply not counted', function () {
    $user = skillUser(new FakeExaminer);
    $attempt = startCheck($user);

    $this->actingAs($user)->post('/applicant/skills/attempt/'.$attempt->id, [
        'option' => [0 => 0],
    ])->assertRedirect();

    $attempt->refresh();

    expect($attempt->score)->toBe(20)
        ->and($attempt->review[1]['comment'])->toBe('Ответ не дан.');
});

test('a passing result confirms the skill and shows up on the public resume', function () {
    $user = skillUser(new FakeExaminer);
    $resume = makeResume($user->applicant);
    $attempt = startCheck($user);

    $this->actingAs($user)->post('/applicant/skills/attempt/'.$attempt->id, [
        'option' => [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0],
    ]);

    expect($attempt->refresh()->passed())->toBeTrue();

    // работодатель видит проверенный результат, а не строчку в резюме
    $this->get('/resume/'.$resume->id)
        ->assertOk()
        ->assertSee('Подтверждено заданием')
        ->assertSee('100%');
});

test('a failed attempt confirms nothing but does not block a retry', function () {
    $user = skillUser(new FakeExaminer(acceptFree: false));
    $resume = makeResume($user->applicant);
    $attempt = startCheck($user);

    $this->actingAs($user)->post('/applicant/skills/attempt/'.$attempt->id, [
        'option' => [0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1],
    ]);

    expect($attempt->refresh()->score)->toBe(0)
        ->and(SkillAttempt::badgesFor($user->applicant))->toBeEmpty();

    $this->get('/resume/'.$resume->id)->assertOk()->assertDontSee('Подтверждено заданием');

    // и можно пройти снова, когда пройдёт пауза
    $this->travelTo(now()->addMinutes(SkillAttempt::COOLDOWN_MINUTES + 1));

    startCheck($user);
    expect(SkillAttempt::count())->toBe(2);
});

test('a confirmed skill is marked as proven in the match breakdown', function () {
    $user = skillUser(new FakeExaminer);
    $resume = makeResume($user->applicant);
    $attempt = startCheck($user);

    $this->actingAs($user)->post('/applicant/skills/attempt/'.$attempt->id, [
        'option' => [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0],
    ]);

    // разбор совпадения делает настоящий аналитик поверх поддельного HTTP:
    // проверяем не модель, а то, что подтверждение доехало до разбора
    Illuminate\Support\Facades\Http::fake(['*' => Illuminate\Support\Facades\Http::response([
        'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => json_encode([
            'enough_data' => true,
            'matched_skills' => ['PHP', 'Вёрстка'],
            'missing_skills' => [],
            'partial_matches' => [],
            'verdict' => 'Навык PHP проверен заданием.',
            'score' => 80,
        ], JSON_UNESCAPED_UNICODE)]]]],
    ])]);

    $analyst = new App\Services\Ai\GeminiMatchAnalyst(new App\Services\Ai\GeminiClient([
        'key' => 'test-key', 'model' => 'gemini-3.5-flash-lite',
        'revision' => '2026-05-20', 'timeout' => 5, 'connect_timeout' => 3,
    ]));

    $vacancy = makeVacancy(makeEmployer(User::factory()->employer()->create()));
    $analysis = $analyst->analyse($vacancy, $resume);

    // PHP подтверждён заданием, «Вёрстка» — только со слов кандидата;
    // вместе с навыком приезжают уровень и балл, иначе «проверено» не отвечает,
    // насколько глубоко проверено
    expect($analysis['proven_skills'])->toBe([
        ['skill' => 'PHP', 'level' => 'Уверенный', 'score' => 100],
    ]);

    // и подтверждение ушло в модель отдельным блоком, а не растворилось в резюме
    Illuminate\Support\Facades\Http::assertSent(function ($request) {
        return str_contains($request->data()['input'][0]['content'][0]['text'],
            'ПОДТВЕРЖДЕНО ЗАДАНИЕМ НА ПЛАТФОРМЕ');
    });
});

test('the catalogue can be filtered by a skill proven with a test', function () {
    $examiner = new FakeExaminer;

    // один кандидат подтвердил навык, второй просто указал его в резюме
    $proved = skillUser($examiner);
    makeResume($proved->applicant)->update(['profession' => 'Кандидат с подтверждением']);
    $attempt = startCheck($proved);
    $this->actingAs($proved)->post('/applicant/skills/attempt/'.$attempt->id, [
        'option' => [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0],
    ]);

    $declared = skillUser($examiner);
    makeResume($declared->applicant)->update([
        'profession' => 'Кандидат без подтверждения',
        'skills' => 'PHP, Laravel',
    ]);

    // без фильтра видно обоих
    $this->get('/resume')
        ->assertOk()
        ->assertSee('Кандидат с подтверждением')
        ->assertSee('Кандидат без подтверждения');

    // с фильтром остаётся только тот, кто прошёл задание
    $this->get('/resume?proven='.someSkill()->id)
        ->assertOk()
        ->assertSee('Кандидат с подтверждением')
        ->assertDontSee('Кандидат без подтверждения');
});

test('the filter offers only skills someone has actually proven', function () {
    $examiner = new FakeExaminer;
    $user = skillUser($examiner);
    makeResume($user->applicant);

    Skill::firstOrCreate(['name' => 'Копирайтинг'], ['area' => 'Контент']);

    // пока никто ничего не подтвердил — фильтра нет вовсе
    $this->get('/resume')->assertOk()->assertDontSee('Навык подтверждён заданием');

    $attempt = startCheck($user);
    $this->actingAs($user)->post('/applicant/skills/attempt/'.$attempt->id, [
        'option' => [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0],
    ]);

    $html = $this->get('/resume')->assertOk()->getContent();

    // появился PHP, по которому есть подтверждение, но не «Копирайтинг»
    expect($html)->toContain('Навык подтверждён заданием')
        ->and($html)->toContain('proven='.someSkill()->id)
        ->and($html)->not->toContain('Копирайтинг');
});

test('the catalogue shows proven skills on the card without extra queries', function () {
    $examiner = new FakeExaminer;
    $user = skillUser($examiner);
    makeResume($user->applicant);
    $attempt = startCheck($user);

    $this->actingAs($user)->post('/applicant/skills/attempt/'.$attempt->id, [
        'option' => [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0],
    ]);

    Illuminate\Support\Facades\DB::enableQueryLog();

    $this->get('/resume')->assertOk()->assertSee('rc-chip proof', false)->assertSee('100%');

    $queries = count(Illuminate\Support\Facades\DB::getQueryLog());
    Illuminate\Support\Facades\DB::disableQueryLog();

    // подтверждения загружены заранее: страница не ходит в базу на каждую карточку
    expect($queries)->toBeLessThan(20);
});

test('an attempt carries a deadline and a running clock', function () {
    $user = skillUser(new FakeExaminer);
    $attempt = startCheck($user);

    $minutes = SkillAttempt::minutesFor($attempt->test->length());

    expect($attempt->expires_at)->not->toBeNull()
        // ровно столько, сколько обещано, с поправкой на время самого запроса
        ->and(now()->diffInMinutes($attempt->expires_at))->toBeGreaterThan($minutes - 1)
        ->and(now()->diffInMinutes($attempt->expires_at))->toBeLessThanOrEqual($minutes);

    $this->actingAs($user)->get('/applicant/skills/attempt/'.$attempt->id)
        ->assertOk()
        ->assertSee('qzClock', false)
        ->assertSee('осталось');
});

test('answers sent after the deadline are not counted', function () {
    $user = skillUser(new FakeExaminer);
    $attempt = startCheck($user);

    $this->travelTo(now()->addMinutes(SkillAttempt::minutesFor($attempt->test->length()) + 2));

    $this->actingAs($user)->post('/applicant/skills/attempt/'.$attempt->id, [
        'option' => [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0],
    ])->assertRedirect(route('applicant.skills.attempt', $attempt, absolute: false));

    // все ответы верные, но время вышло — попытка закрыта нулём
    expect($attempt->refresh()->score)->toBe(0)
        ->and($attempt->finished_at)->not->toBeNull();
});

test('a slightly late answer still counts', function () {
    $user = skillUser(new FakeExaminer);
    $attempt = startCheck($user);

    // ответ ушёл вовремя, но дошёл на несколько секунд позже срока
    $this->travelTo($attempt->expires_at->copy()->addSeconds(SkillAttempt::GRACE_SECONDS - 5));

    $this->actingAs($user)->post('/applicant/skills/attempt/'.$attempt->id, [
        'option' => [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0],
    ]);

    expect($attempt->refresh()->score)->toBe(100);
});

test('an abandoned attempt is closed when it is opened again', function () {
    $user = skillUser(new FakeExaminer);
    $attempt = startCheck($user);

    $this->travelTo(now()->addHours(3));

    $this->actingAs($user)->get('/applicant/skills/attempt/'.$attempt->id)
        ->assertOk()
        ->assertSee('Пока не зачтено');

    expect($attempt->refresh()->finished_at)->not->toBeNull()
        ->and($attempt->score)->toBe(0);
});

test('a repeat on the same skill waits for the cooldown', function () {
    $examiner = new FakeExaminer;
    $user = skillUser($examiner);
    $attempt = startCheck($user);

    $this->actingAs($user)->post('/applicant/skills/attempt/'.$attempt->id, [
        'option' => [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0],
    ]);

    // сразу после сдачи новую попытку начать нельзя
    $this->actingAs($user)->post('/applicant/skills/start', [
        'skill_id' => someSkill()->id,
        'level' => 'confident',
    ])->assertSessionHas('error');

    expect(SkillAttempt::count())->toBe(1);

    // а после паузы — можно
    $this->travelTo(now()->addMinutes(SkillAttempt::COOLDOWN_MINUTES + 1));

    startCheck($user);

    expect(SkillAttempt::count())->toBe(2);
});

test('the cooldown holds only the same skill', function () {
    $examiner = new FakeExaminer;
    $user = skillUser($examiner);
    $attempt = startCheck($user);

    $this->actingAs($user)->post('/applicant/skills/attempt/'.$attempt->id, ['option' => [0 => 0]]);

    // другой навык проверять никто не мешает
    $other = Skill::firstOrCreate(['name' => 'Excel'], ['area' => 'Данные и аналитика']);

    $this->actingAs($user)->post('/applicant/skills/start', [
        'skill_id' => $other->id,
        'level' => 'confident',
    ])->assertRedirect();

    expect(SkillAttempt::count())->toBe(2);
});

test('pressing start again returns to the running attempt instead of resetting the clock', function () {
    $examiner = new FakeExaminer;
    $user = skillUser($examiner);
    $attempt = startCheck($user);

    $this->travelTo(now()->addMinutes(5));

    $again = startCheck($user);

    // та же попытка с тем же сроком — время не начинается заново
    expect($again->id)->toBe($attempt->id)
        ->and(SkillAttempt::count())->toBe(1)
        ->and($examiner->composed)->toBe(1);
});

test('the level is shown to the employer next to the score', function () {
    $user = skillUser(new FakeExaminer);
    $resume = makeResume($user->applicant);
    $attempt = startCheck($user, 'expert');

    $this->actingAs($user)->post('/applicant/skills/attempt/'.$attempt->id, [
        'option' => [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0],
    ]);

    // на странице резюме
    $this->get('/resume/'.$resume->id)
        ->assertOk()
        ->assertSee('Подтверждено заданием')
        ->assertSee('Продвинутый');

    // и на карточке в каталоге
    $this->get('/resume')->assertOk()->assertSee('rc-level', false)->assertSee('Продвинутый');
});

test('the strongest attempt wins: level first, score second', function () {
    $examiner = new FakeExaminer;
    $user = skillUser($examiner);

    // начальный уровень сдан идеально
    $basic = startCheck($user, 'basic');
    $this->actingAs($user)->post('/applicant/skills/attempt/'.$basic->id, [
        'option' => [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0],
    ]);

    $this->travelTo(now()->addMinutes(SkillAttempt::COOLDOWN_MINUTES + 1));

    // продвинутый — слабее по баллу, но выше по уровню
    $expert = startCheck($user, 'expert');
    $this->actingAs($user)->post('/applicant/skills/attempt/'.$expert->id, [
        'option' => [0 => 0, 1 => 0, 2 => 0, 3 => 1, 4 => 1],
    ]);

    $badge = SkillAttempt::badgesFor($user->applicant->fresh())->first();

    // «продвинутый на 60%» говорит о человеке больше, чем «начальный на 100%»
    expect($badge['level'])->toBe('Продвинутый')
        ->and($badge['score'])->toBe(60);
});

test('a finished attempt cannot be submitted twice', function () {
    $user = skillUser(new FakeExaminer);
    $attempt = startCheck($user);

    $this->actingAs($user)->post('/applicant/skills/attempt/'.$attempt->id, ['option' => [0 => 0]]);

    $this->actingAs($user)->post('/applicant/skills/attempt/'.$attempt->id, ['option' => [0 => 0, 1 => 0]])
        ->assertForbidden();

    expect($attempt->refresh()->score)->toBe(20);
});

test('someone else attempt is closed', function () {
    $owner = skillUser(new FakeExaminer);
    $attempt = startCheck($owner);

    $stranger = User::factory()->applicant()->create();
    makeApplicant($stranger);

    $this->actingAs($stranger)->get('/applicant/skills/attempt/'.$attempt->id)->assertForbidden();
    $this->actingAs($stranger)->post('/applicant/skills/attempt/'.$attempt->id, ['option' => [0 => 0]])
        ->assertForbidden();
});

test('the section is closed to employers', function () {
    $employerUser = User::factory()->employer()->create();
    makeEmployer($employerUser);

    $this->actingAs($employerUser)->get('/applicant/skills')->assertForbidden();
});

test('a refusal from the model does not burn the attempt', function () {
    $user = skillUser(new FakeExaminer(failure: 'Лимит исчерпан.'));

    $this->actingAs($user)->post('/applicant/skills/start', [
        'skill_id' => someSkill()->id,
        'level' => 'confident',
    ])->assertSessionHas('error', 'Лимит исчерпан.');

    expect(SkillAttempt::count())->toBe(0);
});

test('the page works without a key, but the check cannot be started', function () {
    $user = skillUser(new FakeExaminer(available: false));
    someSkill();

    $this->actingAs($user)->get('/applicant/skills')
        ->assertOk()
        ->assertSee('GEMINI_API_KEY');
});

/*
 * Длину задания выбирает кандидат: раньше во всех проверках было ровно пять
 * вопросов, и задания получались одинаковыми у всех.
 */

test('кандидат выбирает длину задания, и время считается от неё', function () {
    $user = skillUser(new FakeExaminer);

    $this->actingAs($user)->post('/applicant/skills/start', [
        'skill_id' => someSkill()->id,
        'level' => 'confident',
        'questions' => 15,
    ])->assertRedirect();

    $attempt = SkillAttempt::latest('id')->first();

    expect($attempt->test->length())->toBe(15)
        // по три минуты на вопрос, а не прежние фиксированные 15 минут
        ->and(now()->diffInMinutes($attempt->expires_at))->toBeGreaterThan(44)
        ->and(now()->diffInMinutes($attempt->expires_at))->toBeLessThanOrEqual(45);
});

test('без выбора длины задание короткое', function () {
    $user = skillUser(new FakeExaminer);
    $attempt = startCheck($user);

    expect($attempt->test->length())->toBe(SkillTest::DEFAULT_LENGTH);
});

test('банк заданий ведётся отдельно по длине', function () {
    $examiner = new FakeExaminer;
    $user = skillUser($examiner);
    $skill = someSkill();

    // короткое задание уже составлено и лежит в банке
    $this->actingAs($user)->post('/applicant/skills/start', [
        'skill_id' => $skill->id, 'level' => 'confident', 'questions' => 5,
    ])->assertRedirect();

    SkillAttempt::latest('id')->first()->update(['finished_at' => now(), 'score' => 100]);
    $this->travelTo(now()->addMinutes(SkillAttempt::COOLDOWN_MINUTES + 1));

    // просьба о полном задании не должна отдать короткое из банка
    $this->actingAs($user)->post('/applicant/skills/start', [
        'skill_id' => $skill->id, 'level' => 'confident', 'questions' => 15,
    ])->assertRedirect();

    expect(SkillAttempt::latest('id')->first()->test->length())->toBe(15)
        ->and($examiner->composed)->toBe(2);

    // варианты нумеруются сквозным счётчиком: на (навык, уровень, вариант)
    // стоит уникальный индекс, и отдельная нумерация по длинам дала бы дубль
    expect(SkillTest::where('skill_id', $skill->id)->pluck('variant')->sort()->values()->all())
        ->toBe([1, 2]);
});

test('чужая длина задания не принимается', function () {
    $user = skillUser(new FakeExaminer);

    $this->actingAs($user)->post('/applicant/skills/start', [
        'skill_id' => someSkill()->id,
        'level' => 'confident',
        'questions' => 7,
    ])->assertSessionHasErrors('questions');
});

test('каталог предлагает выбрать длину', function () {
    $user = skillUser(new FakeExaminer);
    someSkill();

    $html = $this->actingAs($user)->get('/applicant/skills')->assertOk()->getContent();

    foreach (array_keys(SkillTest::LENGTHS) as $count) {
        expect($html)->toContain('name="questions" value="'.$count.'"');
    }
});
