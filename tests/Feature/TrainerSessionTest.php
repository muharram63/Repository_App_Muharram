<?php

/*
 * Тренировка на движке ИИ-собеседования и её полная изоляция.
 *
 * Тренировка — обычное собеседование по скрытой учебной вакансии служебного
 * аккаунта. Самое важное здесь — она нигде не всплывает: ни у работодателей,
 * ни в отчётах по вакансиям, ни в каталоге, ни в админке и статистике, ни в
 * «Моих ИИ-собеседованиях» кандидата.
 */

use App\Models\AiInterview;
use App\Models\AiInterviewConfig;
use App\Models\Trainer\TrainerLedgerEntry;
use App\Models\Trainer\TrainerSession;
use App\Models\Trainer\TrainerTrack;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vacancy;
use App\Services\Trainer\TrainerWallet;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    // ключ из .env не должен уводить тесты к настоящей модели: квота не бесконечна
    config(['services.gemini.key' => null]);
    Illuminate\Support\Facades\Http::preventStrayRequests();
});

// ==================== запуск ====================

test('старт: попытка списана, учебная вакансия скрыта, собеседование сразу с разговора', function () {
    $user = trainingUser();
    $track = phpTrack();

    $response = startTraining($this, $user, $track, 'middle');

    $session = TrainerSession::sole();
    $interview = $session->interview;
    $response->assertRedirect(route('applicant.ai.chat', $interview));

    expect($session->level)->toBe('middle')
        ->and($session->track_title)->toBe('PHP-разработчик')
        ->and(app(TrainerWallet::class)->balance($user))->toBe(0)
        ->and(TrainerLedgerEntry::where('session_id', $session->id)->sole()->reason)->toBe('start');

    // учебная вакансия: в архиве, у служебного аккаунта
    $vacancy = $session->vacancy;
    expect($vacancy->status)->toBe('in_archived')
        ->and($vacancy->employer->company_name)->toBe('Тренажёр Workio');

    // настройки под уровень, документы не нужны, работодателю не напоминать
    $config = AiInterviewConfig::where('vacancy_id', $vacancy->id)->sole();
    expect($config->weight_documents)->toBe(0)
        ->and($config->questions_count)->toBe(config('trainer.levels.middle.questions'))
        ->and($config->decision_mode)->toBe('advisory');

    // критерии — навыки направления, подтверждены
    expect($vacancy->id)->not->toBeNull()
        ->and(App\Models\AiInterviewCriterion::where('vacancy_id', $vacancy->id)->confirmed()->count())->toBe(3);

    // собеседование: согласие есть, разговор начинается сразу
    expect($interview->consented())->toBeTrue()
        ->and($interview->stage)->toBe('interview')
        ->and($interview->analysis_status)->toBe('ready');

    // страница разговора открывается и говорит, что это тренировка
    $this->actingAs($user)->get(route('applicant.ai.chat', $interview))
        ->assertOk()
        ->assertSee(__('Это тренировка: работодатели её не видят. Баллы и подробный разбор появятся в «Тренажёре», когда закончите.'))
        ->assertDontSee(__('Оценки ответов вам не показываются — их увидит работодатель в отчёте.'));
});

test('без согласия тренировка не начинается и попытка не тратится', function () {
    $user = trainingUser();
    $track = phpTrack();
    $this->actingAs($user)->get(route('applicant.trainer.index'));

    $this->actingAs($user)->post(route('applicant.trainer.start'), ['track_id' => $track->id, 'level' => 'junior'])
        ->assertSessionHasErrors('consent');

    expect(TrainerSession::count())->toBe(0)
        ->and(app(TrainerWallet::class)->balance($user))->toBe(1);
});

test('без попыток — понятный отказ, без тренировки', function () {
    $user = trainingUser();
    $track = phpTrack();
    startTraining($this, $user, $track);

    $this->actingAs($user)->post(route('applicant.trainer.start'), ['track_id' => $track->id, 'level' => 'junior', 'consent' => '1'])
        ->assertSessionHas('error', __('Попытки закончились. Купите пакет попыток, чтобы продолжить.'));

    expect(TrainerSession::count())->toBe(1);
});

test('выключенное направление не запускается', function () {
    $user = trainingUser();
    $track = phpTrack(['active' => false]);

    startTraining($this, $user, $track)->assertSessionHas('error');
    expect(TrainerSession::count())->toBe(0);
});

test('прощание Анны после задания — о разборе в тренажёре, а не о приёме на работу', function () {
    $user = trainingUser();
    startTraining($this, $user, phpTrack());
    $interview = TrainerSession::sole()->interview;

    expect($interview->farewellRemark())->toContain('тренировка закончена')
        ->not->toContain('Мои ИИ-собеседования');
});

// ==================== изоляция ====================

test('работодатель не откроет отчёт тренировки — даже по прямой ссылке', function () {
    $user = trainingUser();
    startTraining($this, $user, phpTrack());
    $interview = TrainerSession::sole()->interview;

    $employer = User::factory()->employer()->create();
    makeEmployer($employer);

    $this->actingAs($employer)->get(route('employer.ai.candidate', [$interview->vacancy_id, $interview->id]))->assertForbidden();
    $this->actingAs($employer)->get(route('employer.ai.candidates', $interview->vacancy_id))->assertForbidden();
});

test('тренировок нет в «Моих ИИ-собеседованиях» кандидата', function () {
    $user = trainingUser();
    startTraining($this, $user, phpTrack());

    $this->actingAs($user)->get(route('applicant.ai.index'))
        ->assertOk()
        ->assertDontSee('PHP-разработчик · Junior');
});

test('учебной вакансии нет в каталоге, а её страница закрыта', function () {
    $user = trainingUser();
    startTraining($this, $user, phpTrack());
    $vacancy = TrainerSession::sole()->vacancy;

    $this->get(route('public.vacancies.index'))->assertOk()->assertDontSee('PHP-разработчик · Junior');
    $this->get(route('public.vacancies.show', $vacancy))->assertNotFound();
});

test('в админке и статистике тренировок нет', function () {
    $user = trainingUser();
    startTraining($this, $user, phpTrack());
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('superadmin.ai'))->assertOk()->assertDontSee('PHP-разработчик · Junior');
    $this->actingAs($admin)->get(route('superadmin.vacancy'))->assertOk()->assertDontSee('PHP-разработчик · Junior');
    $this->actingAs($admin)->get(route('superadmin.companies'))->assertOk()->assertDontSee('Тренажёр Workio');

    $totals = app(App\Support\AdminNumbers::class)->totals();
    expect($totals['vacanciesTotal'])->toBe(Vacancy::withoutTraining()->count())
        ->and($totals['vacanciesTotal'])->toBe(0)
        ->and(AiInterview::withoutTraining()->count())->toBe(0);
});

test('решение по тренировке не шлёт уведомлений ни одному работодателю', function () {
    $user = trainingUser();
    $employer = User::factory()->employer()->create();
    makeEmployer($employer);
    startTraining($this, $user, phpTrack());
    $interview = TrainerSession::sole()->interview;
    $interview->update(['stage' => 'decision', 'requires_review' => true]);

    (new App\Jobs\MakeHiringDecision($interview->id))->handle(new class implements App\Services\Ai\DecisionWriter
    {
        public function available(): bool { return false; }

        public function write(AiInterview $interview, string $outcome, array $reasons, string $fallback): string
        {
            return $fallback;
        }
    });

    $employerIds = User::where('role', 'employer')->pluck('id');
    expect(UserNotification::whereIn('user_id', $employerIds)->count())->toBe(0);
});

// ==================== направления в админке ====================

test('админ заводит направление: навыки по строкам', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('superadmin.trainer.tracks.store'), [
        'title' => 'Бухгалтер',
        'language' => 'ru',
        'skills' => "Проводки и 1С; обязательный; 40\nНалоговый учёт; обязательный\nExcel",
        'active' => '1',
    ])->assertRedirect();

    $track = TrainerTrack::sole();
    expect($track->skills)->toBe([
        ['label' => 'Проводки и 1С', 'kind' => 'must', 'weight' => 40],
        ['label' => 'Налоговый учёт', 'kind' => 'must', 'weight' => 20],
        ['label' => 'Excel', 'kind' => 'nice', 'weight' => 20],
    ]);

    $this->actingAs($admin)->get(route('superadmin.trainer'))
        ->assertOk()
        ->assertSee('Бухгалтер')
        ->assertSee('Проводки и 1С; обязательный; 40');
});
