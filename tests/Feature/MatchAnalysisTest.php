<?php

use App\Models\MatchAnalysis;
use App\Models\Resume;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\MatchAnalyst;

/**
 * Заглушка разбора: тесты не ходят в сеть и не тратят квоту ключа.
 * Считает вызовы — по ним видно, работает ли кэш.
 */
class FakeAnalyst implements MatchAnalyst
{
    public int $calls = 0;

    public function __construct(
        private readonly bool $available = true,
        private readonly ?string $failure = null,
        private readonly ?array $answer = null,
    ) {
    }

    public function available(): bool
    {
        return $this->available;
    }

    public function analyse(Vacancy $vacancy, Resume $resume): array
    {
        if ($this->failure) {
            throw new AiUnavailableException($this->failure, 17);
        }

        $this->calls++;

        return $this->answer ?? [
            'matched_skills' => ['PHP', 'Laravel'],
            'missing_skills' => ['Kubernetes'],
            'partial_matches' => [['title' => 'SQL', 'note' => 'опыт есть, но в рознице']],
            'verdict' => 'Совпадение по PHP и Laravel, но нет опыта с Kubernetes.',
            'score' => 72,
            'enough_data' => true,
        ];
    }
}

/**
 * Пара «вакансия + резюме» и оба владельца.
 *
 * @return array{0:Vacancy,1:Resume,2:User,3:User}
 */
function matchPair(MatchAnalyst $analyst): array
{
    app()->instance(MatchAnalyst::class, $analyst);

    $employerUser = User::factory()->employer()->create();
    $vacancy = makeVacancy(makeEmployer($employerUser));

    $applicantUser = User::factory()->applicant()->create();
    $resume = makeResume(makeApplicant($applicantUser));

    return [$vacancy, $resume, $applicantUser, $employerUser];
}

test('both sides of the pair get the breakdown', function () {
    [$vacancy, $resume, $applicantUser, $employerUser] = matchPair(new FakeAnalyst);

    foreach ([$applicantUser, $employerUser] as $viewer) {
        $this->actingAs($viewer)->getJson('/match/'.$vacancy->id.'/'.$resume->id)
            ->assertOk()
            ->assertJsonPath('analysis.matched_skills', ['PHP', 'Laravel'])
            ->assertJsonPath('analysis.missing_skills', ['Kubernetes'])
            ->assertJsonPath('analysis.partial_matches.0.title', 'SQL')
            ->assertJsonPath('analysis.score', 72);
    }
});

test('the breakdown is computed once and then served from storage', function () {
    $analyst = new FakeAnalyst;
    [$vacancy, $resume, $applicantUser] = matchPair($analyst);

    $this->actingAs($applicantUser)->getJson('/match/'.$vacancy->id.'/'.$resume->id)->assertOk();
    $this->actingAs($applicantUser)->getJson('/match/'.$vacancy->id.'/'.$resume->id)->assertOk();
    $this->actingAs($applicantUser)->getJson('/match/'.$vacancy->id.'/'.$resume->id)->assertOk();

    // три показа страницы — одно обращение к модели
    expect($analyst->calls)->toBe(1)
        ->and(MatchAnalysis::count())->toBe(1);
});

test('editing the resume makes the stored breakdown stale', function () {
    $analyst = new FakeAnalyst;
    [$vacancy, $resume, $applicantUser] = matchPair($analyst);

    $this->actingAs($applicantUser)->getJson('/match/'.$vacancy->id.'/'.$resume->id)->assertOk();

    $resume->update(['skills' => 'PHP, Laravel, Kubernetes']);

    $this->actingAs($applicantUser)->getJson('/match/'.$vacancy->id.'/'.$resume->id)->assertOk();

    // отпечаток изменился — разбор пересчитан, но запись осталась одна
    expect($analyst->calls)->toBe(2)
        ->and(MatchAnalysis::count())->toBe(1);
});

test('a change that cannot affect the breakdown does not cost a recount', function () {
    $analyst = new FakeAnalyst;
    [$vacancy, $resume, $applicantUser] = matchPair($analyst);

    $this->actingAs($applicantUser)->getJson('/match/'.$vacancy->id.'/'.$resume->id)->assertOk();

    // просмотры на разбор не влияют
    $vacancy->increment('views');

    $this->actingAs($applicantUser)->getJson('/match/'.$vacancy->id.'/'.$resume->id)->assertOk();

    expect($analyst->calls)->toBe(1);
});

test('зарплата больше не критерий — её правка разбор не пересчитывает', function () {
    $analyst = new FakeAnalyst;
    [$vacancy, $resume, $applicantUser] = matchPair($analyst);

    $this->actingAs($applicantUser)->getJson('/match/'.$vacancy->id.'/'.$resume->id)->assertOk();

    // зарплата и город на процент не влияют, поэтому пересчитывать нечего:
    // повторный разбор стоил бы запроса к модели впустую
    $resume->update(['desired_salary' => 99000]);
    $vacancy->update(['salary_to' => 99000]);

    $this->actingAs($applicantUser)->getJson('/match/'.$vacancy->id.'/'.$resume->id)->assertOk();

    expect($analyst->calls)->toBe(1);
});

test('языки — критерий, поэтому их правка разбор обновляет', function () {
    $analyst = new FakeAnalyst;
    [$vacancy, $resume, $applicantUser] = matchPair($analyst);

    $this->actingAs($applicantUser)->getJson('/match/'.$vacancy->id.'/'.$resume->id)->assertOk();

    $resume->update(['languages' => 'русский, английский C1']);

    $this->actingAs($applicantUser)->getJson('/match/'.$vacancy->id.'/'.$resume->id)->assertOk();

    expect($analyst->calls)->toBe(2);
});

test('passing a skill test makes the stored breakdown stale', function () {
    $analyst = new FakeAnalyst;
    [$vacancy, $resume, $applicantUser] = matchPair($analyst);

    $this->actingAs($applicantUser)->getJson('/match/'.$vacancy->id.'/'.$resume->id)->assertOk();

    // кандидат подтвердил навык заданием — прежний разбор его не учитывал
    $skill = App\Models\Skill::firstOrCreate(['name' => 'PHP'], ['area' => 'Разработка']);
    $test = App\Models\SkillTest::create([
        'skill_id' => $skill->id, 'level' => 'confident', 'variant' => 1,
        'questions' => [['text' => 'Вопрос', 'options' => ['a', 'b'], 'answer' => 0, 'explain' => '']],
    ]);
    App\Models\SkillAttempt::create([
        'applicant_id' => $resume->applicant_id, 'skill_test_id' => $test->id,
        'answers' => [], 'review' => [], 'score' => 90, 'finished_at' => now(),
    ]);

    $this->actingAs($applicantUser)->getJson('/match/'.$vacancy->id.'/'.$resume->id)->assertOk();

    expect($analyst->calls)->toBe(2)
        ->and(App\Models\MatchAnalysis::count())->toBe(1);
});

test('an outsider cannot read someone else pair', function () {
    [$vacancy, $resume] = matchPair(new FakeAnalyst);

    $stranger = User::factory()->applicant()->create();
    makeApplicant($stranger);

    $this->actingAs($stranger)->getJson('/match/'.$vacancy->id.'/'.$resume->id)->assertForbidden();

    // и другая компания тоже: по чужой паре сведения о кандидатах не собрать
    $otherEmployer = User::factory()->employer()->create();
    makeEmployer($otherEmployer);

    $this->actingAs($otherEmployer)->getJson('/match/'.$vacancy->id.'/'.$resume->id)->assertForbidden();
});

test('a guest is not served at all', function () {
    [$vacancy, $resume] = matchPair(new FakeAnalyst);

    $this->get('/match/'.$vacancy->id.'/'.$resume->id)
        ->assertRedirect(route('login', absolute: false));
});

test('a refusal carries the pause so the block can retry itself', function () {
    [$vacancy, $resume, $applicantUser] = matchPair(new FakeAnalyst(failure: 'Лимит исчерпан.'));

    $this->actingAs($applicantUser)->getJson('/match/'.$vacancy->id.'/'.$resume->id)
        ->assertStatus(503)
        ->assertJsonPath('error', 'Лимит исчерпан.')
        ->assertJsonPath('retry_after', 17);

    // неудачный разбор не сохраняется, иначе он застрял бы в кэше
    expect(MatchAnalysis::count())->toBe(0);
});

test('without a key the block says so instead of failing silently', function () {
    [$vacancy, $resume, $applicantUser] = matchPair(new FakeAnalyst(available: false));

    $this->actingAs($applicantUser)->getJson('/match/'.$vacancy->id.'/'.$resume->id)
        ->assertStatus(503)
        ->assertJsonPath('error', 'Разбор недоступен: в .env не задан GEMINI_API_KEY.');
});

test('scarce data leaves the score out instead of inventing one', function () {
    [$vacancy, $resume, $applicantUser] = matchPair(new FakeAnalyst(answer: [
        'matched_skills' => [],
        'missing_skills' => [],
        'partial_matches' => [],
        'verdict' => 'В резюме нет описания опыта — для разбора этого мало.',
        'score' => null,
        'enough_data' => false,
    ]));

    $this->actingAs($applicantUser)->getJson('/match/'.$vacancy->id.'/'.$resume->id)
        ->assertOk()
        ->assertJsonPath('analysis.enough_data', false)
        ->assertJsonPath('analysis.score', null);
});

test('the block appears on the vacancy page for a candidate', function () {
    [$vacancy, $resume, $applicantUser] = matchPair(new FakeAnalyst);

    $this->actingAs($applicantUser)->get('/vacancies/'.$vacancy->id)
        ->assertOk()
        ->assertSee('AI Match Analysis', false)
        ->assertSee(route('public.match', [$vacancy, $resume]), false);
});

test('the block appears on the resume page for an employer', function () {
    [$vacancy, $resume, , $employerUser] = matchPair(new FakeAnalyst);

    $this->actingAs($employerUser)->get('/resume/'.$resume->id)
        ->assertOk()
        ->assertSee('AI Match Analysis', false)
        ->assertSee(route('public.match', [$vacancy, $resume]), false);
});

test('a candidate without a resume gets a hint, not a broken block', function () {
    app()->instance(MatchAnalyst::class, new FakeAnalyst);

    $employerUser = User::factory()->employer()->create();
    $vacancy = makeVacancy(makeEmployer($employerUser));

    $bare = User::factory()->applicant()->create();
    makeApplicant($bare);

    $this->actingAs($bare)->get('/vacancies/'.$vacancy->id)
        ->assertOk()
        ->assertSee('Создайте резюме', false);
});
