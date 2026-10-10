<?php

/*
 * Конец тренировки: разбор для кандидата и возврат попытки.
 *
 * Движок объявляет решение как обычно; тренажёр замечает это сам и
 * закрывает тренировку. Сбой проверки по нашей вине — попытка возвращается
 * без участия человека. Разбор есть всегда: без модели — из оценок движка.
 */

use App\Jobs\BuildTrainerFeedback;
use App\Models\AiDecision;
use App\Models\AiInterview;
use App\Models\AiInterviewTurn;
use App\Models\AiTestSubmission;
use App\Models\AiTestTask;
use App\Models\Trainer\TrainerSession;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Ai\AiJournal;
use App\Services\Ai\GeminiClient;
use App\Services\Trainer\TrainerCoach;
use App\Services\Trainer\TrainerWallet;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    // ключ из .env не должен уводить тесты к настоящей модели: квота не бесконечна
    config(['services.gemini.key' => null]);
    Illuminate\Support\Facades\Http::preventStrayRequests();
});

/** Объявить решение так, как это делает движок. */
function decideTraining(TrainerSession $session, ?string $gate = null, int $total = 42): void
{
    $interview = $session->interview;

    AiDecision::create(['ai_interview_id' => $interview->id, 'round' => 1, 'total' => $total,
        'outcome' => $gate ? 'manual_review' : 'rejected', 'gate_reason' => $gate ?? 'below_reject_threshold',
        'reasons' => [], 'message_to_candidate' => '—', 'requires_manual_review' => (bool) $gate]);

    $interview->update(['outcome' => $gate ? 'manual_review' : 'rejected', 'total_score' => $total,
        'interview_score' => 40, 'test_score' => 30, 'decided_at' => now(), 'stage' => 'done']);
}

// ==================== конец тренировки ====================

test('решение движка закрывает тренировку и ставит разбор в очередь', function () {
    $session = playedSession($this);
    decideTraining($session);

    $session->refresh();
    expect($session->status)->toBe(TrainerSession::STATUS_FINISHED)
        ->and($session->score)->toBe(42)
        ->and($session->feedback_status)->toBe('pending');

    Queue::assertPushed(BuildTrainerFeedback::class, fn ($job) => $job->sessionId === $session->id);
});

test('без модели разбор собирается из оценок: ответы, ошибки теста, эталон', function () {
    $session = playedSession($this);
    decideTraining($session);

    (new BuildTrainerFeedback($session->id))->handle(app(TrainerCoach::class));

    $session->refresh();
    $feedback = $session->feedback;

    expect($session->feedback_status)->toBe('ready')
        ->and($feedback['source'])->toBe('basic')
        ->and($feedback['scores']['total'])->toBe(42)
        ->and($feedback['questions'][0]['question'])->toBe('Чем отличается INNER JOIN от LEFT JOIN?')
        ->and($feedback['questions'][0]['score'])->toBe(4)
        ->and($feedback['quiz']['mistakes'][0])->toBe(['question' => 'Что вернёт count([])?', 'yours' => 'null', 'right' => '0'])
        ->and($feedback['tasks'][0]['reference'])->toContain('evenSum')
        ->and($feedback['improvements'])->not->toBeEmpty();

    // кандидат получил уведомление со ссылкой на разбор
    expect(UserNotification::where('user_id', $session->user_id)->where('type', 'trainer')
        ->where('url', route('applicant.trainer.show', $session, false))->exists())->toBeTrue();
});

test('с моделью — дружелюбный текст и «как правильно», а баллы остаются от движка', function () {
    $session = playedSession($this);
    decideTraining($session);

    Http::fake(['*' => Http::response(['steps' => [['type' => 'model_output', 'content' => [
        ['type' => 'text', 'text' => json_encode([
            'summary' => 'Хорошее начало: основы вы знаете, а с JOIN и циклами стоит поработать.',
            'strengths' => ['Чётко объяснили LEFT JOIN'],
            'improvements' => ['Расскажите и про INNER JOIN'],
            'questions' => [['n' => 1, 'comment' => 'Половина ответа есть.', 'better_answer' => 'INNER JOIN — только совпадающие строки, LEFT JOIN — все слева.']],
            'tasks' => [['n' => 1, 'how_to' => 'Отфильтруйте чётные и сложите.']],
            'next_steps' => ['Повторите виды JOIN'],
        ], JSON_UNESCAPED_UNICODE)],
    ]]]])]);

    $coach = new TrainerCoach(new AiJournal(new GeminiClient([
        'key' => 'k', 'model' => 'gemini-3.5-flash-lite',
        'model_scoring' => 'gemini-3.6-flash', 'model_vision' => 'gemini-3.6-flash',
        'revision' => 'r', 'timeout' => 5, 'connect_timeout' => 3,
    ])));

    $feedback = $coach->build($session->fresh());

    expect($feedback['source'])->toBe('ai')
        ->and($feedback['questions'][0]['better'])->toContain('INNER JOIN — только совпадающие')
        ->and($feedback['tasks'][0]['better'])->toBe('Отфильтруйте чётные и сложите.')
        ->and($feedback['next_steps'])->toBe(['Повторите виды JOIN'])
        // цифры модель не трогает
        ->and($feedback['scores']['total'])->toBe(42)
        ->and($feedback['questions'][0]['score'])->toBe(4);
});

test('страница разбора: всё видно владельцу, чужому — 403', function () {
    $session = playedSession($this);
    decideTraining($session);
    (new BuildTrainerFeedback($session->id))->handle(app(TrainerCoach::class));

    $this->actingAs($session->user)->get(route('applicant.trainer.show', $session))
        ->assertOk()
        ->assertSee('Чем отличается INNER JOIN от LEFT JOIN?')
        ->assertSee('Что вернёт count([])?')
        ->assertSee('evenSum');

    $this->actingAs(trainingUser())->get(route('applicant.trainer.show', $session))->assertForbidden();
});

test('застрявший разбор страница собирает сама', function () {
    $session = playedSession($this);
    decideTraining($session);
    $session->update(['finished_at' => now()->subMinutes(5)]);

    $this->actingAs($session->user)->get(route('applicant.trainer.show', $session))
        ->assertOk()
        ->assertSee('Чем отличается INNER JOIN от LEFT JOIN?');

    expect($session->fresh()->feedback_status)->toBe('ready');
});

test('пока тренировка идёт — страница зовёт вернуться к Анне', function () {
    $user = trainingUser();
    startTraining($this, $user, phpTrack());
    $session = TrainerSession::sole();

    $this->actingAs($user)->get(route('applicant.trainer.show', $session))
        ->assertOk()
        ->assertSee(route('applicant.ai.interview', $session->ai_interview_id));
});

// ==================== возврат ====================

test('сбой проверки ИИ — попытка возвращается сама, с уведомлением', function () {
    $session = playedSession($this);
    $wallet = app(TrainerWallet::class);
    expect($wallet->balance($session->user))->toBe(0);

    decideTraining($session, 'invalid_ai_response');

    $session->refresh();
    expect($session->status)->toBe(TrainerSession::STATUS_REFUNDED)
        ->and($wallet->balance($session->user))->toBe(1);

    Queue::assertNotPushed(BuildTrainerFeedback::class);
    expect(UserNotification::where('user_id', $session->user_id)->where('type', 'trainer')->exists())->toBeTrue();
});

test('обычный отказ по баллам — не наш сбой, попытка не возвращается', function () {
    $session = playedSession($this);
    decideTraining($session);

    expect(app(TrainerWallet::class)->balance($session->user))->toBe(0);
});

test('админ возвращает попытку вручную — один раз', function () {
    $user = trainingUser();
    startTraining($this, $user, phpTrack());
    $session = TrainerSession::sole();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('superadmin.trainer'))->assertOk()->assertSee('Вернуть попытку');

    $this->actingAs($admin)->post(route('superadmin.trainer.sessions.refund', $session), ['note' => 'Анна замолчала'])
        ->assertSessionHas('status');
    $this->actingAs($admin)->post(route('superadmin.trainer.sessions.refund', $session), ['note' => 'ещё раз'])
        ->assertSessionHas('error');

    expect(app(TrainerWallet::class)->balance($user))->toBe(1)
        ->and($session->fresh()->status)->toBe(TrainerSession::STATUS_REFUNDED);
});

test('на возвращённую тренировку движок уже не влияет', function () {
    $session = playedSession($this);
    app(TrainerWallet::class)->refund($session, 'вручную');

    decideTraining($session->fresh());

    expect($session->fresh()->status)->toBe(TrainerSession::STATUS_REFUNDED)
        ->and(app(TrainerWallet::class)->balance($session->user))->toBe(1);
    Queue::assertNotPushed(BuildTrainerFeedback::class);
});
