<?php

/*
 * Аудит тренажёра: сквозной путь через настоящий движок решения и места,
 * где тренировка раньше ломалась или просачивалась наружу.
 */

use App\Jobs\BuildTrainerFeedback;
use App\Jobs\MakeHiringDecision;
use App\Models\AiInterview;
use App\Models\AiTestTask;
use App\Models\Trainer\TrainerPayment;
use App\Models\Trainer\TrainerSession;
use App\Models\User;
use App\Services\Ai\DecisionWriter;
use App\Services\Trainer\TrainerPayments;
use App\Services\Trainer\TrainerWallet;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    config(['services.gemini.key' => null]);
    Http::preventStrayRequests();
});

/** Автор письма без модели — как при недоступном ключе. */
function trainerSilentWriter(): DecisionWriter
{
    return new class implements DecisionWriter
    {
        public function available(): bool
        {
            return false;
        }

        public function write(AiInterview $interview, string $outcome, array $reasons, string $fallback): string
        {
            return $fallback;
        }
    };
}

// ==================== сквозной путь через движок ====================

test('настоящий движок решения доводит тренировку до конца: балл, разбор в очереди', function () {
    $session = playedSession($this);

    (new MakeHiringDecision($session->ai_interview_id))->handle(trainerSilentWriter());

    $session->refresh();
    $interview = $session->interview;

    expect($interview->isDecided())->toBeTrue()
        ->and($session->status)->toBe(TrainerSession::STATUS_FINISHED)
        ->and($session->score)->toBe($interview->total_score)
        ->and($session->score)->not->toBeNull()
        ->and(app(TrainerWallet::class)->balance($session->user))->toBe(0);

    Queue::assertPushed(BuildTrainerFeedback::class);
});

test('настоящий движок: сбой ИИ во время собеседования — попытка возвращается', function () {
    $session = playedSession($this);
    // так движок отмечает, что модель не справилась (ответ не разобран и т. п.)
    $session->interview->update(['requires_review' => true]);

    (new MakeHiringDecision($session->ai_interview_id))->handle(trainerSilentWriter());

    expect($session->fresh()->status)->toBe(TrainerSession::STATUS_REFUNDED)
        ->and(app(TrainerWallet::class)->balance($session->user))->toBe(1)
        ->and($session->interview->latestDecision->gate_reason)->toBe('invalid_ai_response');
});

// ==================== прерванная тренировка ====================

test('кандидат удалил собеседование тренировки — страница не падает, тренировка «прервана»', function () {
    $user = trainingUser();
    startTraining($this, $user, phpTrack());
    $session = TrainerSession::sole();

    $this->actingAs($user)->delete(route('applicant.ai.decline', $session->ai_interview_id));

    $session->refresh();
    expect($session->ai_interview_id)->toBeNull()
        ->and($session->isAborted())->toBeTrue();

    $this->actingAs($user)->get(route('applicant.trainer.show', $session))
        ->assertOk()
        ->assertSee(__('Тренировка прервана: собеседование по ней удалено. Продолжить её нельзя — начните новую.'));
    $this->actingAs($user)->get(route('applicant.trainer.index'))->assertOk()->assertSee(__('Прервана'));
});

// ==================== тупики страницы задания ====================

test('время на задание вышло — тренировка ведёт к своему разбору, а не в «Мои ИИ-собеседования»', function () {
    $user = trainingUser();
    startTraining($this, $user, phpTrack());
    $session = TrainerSession::sole();
    $interview = $session->interview;
    $interview->update(['stage' => 'test']);

    AiTestTask::create([
        'ai_interview_id' => $interview->id, 'kind' => AiTestTask::KIND_MAIN,
        'statement' => 'Задача', 'format' => 'case', 'rubric' => [['point' => 'x', 'weight' => 100]],
        'time_limit_minutes' => 30, 'issued_at' => now()->subHours(2), 'deadline_at' => now()->subHour(),
    ]);

    $this->actingAs($user)->get(route('applicant.ai.task', $interview))
        ->assertOk()
        ->assertSee(route('applicant.trainer.show', $session))
        ->assertDontSee(__('решение принимается по совокупности с документами и собеседованием'));
});

test('задание не составилось — у тренировки свой текст и ссылка к ней, без «рассматривает человек»', function () {
    $user = trainingUser();
    startTraining($this, $user, phpTrack());
    $session = TrainerSession::sole();
    $interview = $session->interview;
    $interview->update(['stage' => 'test', 'requires_review' => true]);

    $this->actingAs($user)->get(route('applicant.ai.task', $interview))
        ->assertOk()
        ->assertSee(route('applicant.trainer.show', $session))
        ->assertDontSee(__('Ответ придёт в срок, обещанный работодателем. Ваши ответы на собеседовании сохранены.'));
});

// ==================== язык ====================

test('срок в словах Анны переведён: тренировка на таджикском не переходит на русский', function () {
    $user = trainingUser();
    startTraining($this, $user, phpTrack(['language' => 'tg']));
    $remark = TrainerSession::sole()->interview->closingRemark(true);

    expect($remark)->not->toContain('сразу после проверки');
});

// ==================== лимит ====================

test('возвращённая тренировка не занимает место в дневном пределе', function () {
    config(['trainer.daily_limit' => 1]);
    $user = trainingUser();
    $wallet = app(TrainerWallet::class);
    startTraining($this, $user, phpTrack());
    $wallet->refund(TrainerSession::sole(), 'сбой');

    expect($wallet->startedToday($user))->toBe(0);

    $this->actingAs($user)->post(route('applicant.trainer.start'), [
        'track_id' => TrainerSession::sole()->track_id, 'level' => 'junior', 'consent' => '1',
    ])->assertRedirect();

    expect(TrainerSession::count())->toBe(2);
});

// ==================== служебная компания ====================

test('«Тренажёр Workio» не виден в каталоге компаний и в топе на главной', function () {
    $user = trainingUser();
    startTraining($this, $user, phpTrack());

    $this->get(route('public.companies.index'))->assertOk()->assertDontSee('Тренажёр Workio');
    $this->get('/')->assertOk()->assertDontSee('Тренажёр Workio');
});

// ==================== оплата ====================

test('«Я оплатил» по уже подтверждённой заявке — честный ответ, а не «спасибо, проверим»', function () {
    $user = trainingUser();
    $package = App\Models\Trainer\TrainerPackage::create(['title' => 'П', 'attempts' => 3, 'price' => 30, 'active' => true]);
    $payments = app(TrainerPayments::class);
    $payment = $payments->create($user, $package);
    $payments->confirm($payment, null);

    $this->actingAs($user)->post(route('applicant.trainer.claim', $payment), ['payer_note' => 'x'])
        ->assertSessionHas('error');

    expect($payment->fresh()->status)->toBe(TrainerPayment::CONFIRMED);
});
