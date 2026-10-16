<?php

/*
 * Второй аудит тренажёра: ручной возврат закрывает идущую тренировку,
 * кандидат узнаёт о возврате и начислении, неоплаченные заявки истекают,
 * а письмо кандидату по тренировке модель не пишет.
 */

use App\Jobs\MakeHiringDecision;
use App\Models\AiInterview;
use App\Models\Trainer\TrainerPackage;
use App\Models\Trainer\TrainerPayment;
use App\Models\Trainer\TrainerSession;
use App\Models\User;
use App\Models\UserNotification;
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

test('ручной возврат идущей тренировки закрывает её: продолжить разговор нельзя', function () {
    $user = trainingUser();
    startTraining($this, $user, phpTrack());
    $session = TrainerSession::sole();
    $interviewId = $session->ai_interview_id;

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('superadmin.trainer.sessions.refund', $session), ['note' => 'Анна замолчала'])
        ->assertSessionHas('status');

    expect(AiInterview::find($interviewId))->toBeNull()
        ->and($session->fresh()->status)->toBe(TrainerSession::STATUS_REFUNDED)
        ->and(app(TrainerWallet::class)->balance($user))->toBe(1);

    // страница разбора честно говорит, что тренировка не засчитана
    $this->actingAs($user->fresh())->get(route('applicant.trainer.show', $session))
        ->assertOk()
        ->assertSee('Тренировка не засчитана');

    // и кандидат об этом узнал
    expect(UserNotification::where('user_id', $user->id)->where('type', 'trainer')->count())->toBe(1);
});

test('возврат законченной тренировки не трогает её собеседование', function () {
    $session = playedSession($this);
    $session->update(['status' => TrainerSession::STATUS_FINISHED, 'finished_at' => now()]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('superadmin.trainer.sessions.refund', $session), ['note' => 'жалоба'])
        ->assertSessionHas('status');

    expect(AiInterview::find($session->ai_interview_id))->not->toBeNull();
});

test('ручное начисление попыток — кандидату приходит уведомление', function () {
    $user = trainingUser();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('superadmin.trainer.grant'), ['email' => $user->email, 'attempts' => 2])
        ->assertSessionHas('status');

    $note = UserNotification::where('user_id', $user->id)->sole();

    expect(app(TrainerWallet::class)->balance($user))->toBe(2)
        ->and((string) json_encode($note->getAttributes(), JSON_UNESCAPED_UNICODE))->toContain('Начислены попытки');
});

test('неоплаченная заявка истекает через неделю; «Я оплатил» можно нажать и после', function () {
    $user = trainingUser();
    $package = TrainerPackage::create(['title' => 'Пакет', 'attempts' => 3, 'price' => 30, 'active' => true]);
    $payments = app(TrainerPayments::class);

    $fresh = $payments->create($user, $package);
    $old = $payments->create($user, $package);
    $old->forceFill(['created_at' => now()->subDays(8)])->saveQuietly();

    $this->artisan('trainer:expire-payments')->assertSuccessful();

    expect($old->fresh()->status)->toBe(TrainerPayment::EXPIRED)
        ->and($fresh->fresh()->status)->toBe(TrainerPayment::PENDING);

    // у администратора в «Открытых» её больше нет
    $this->actingAs(User::factory()->admin()->create())->get(route('superadmin.trainer'))
        ->assertOk()
        ->assertDontSee($old->reference())
        ->assertSee($fresh->reference());

    // кандидат видит, что срок истёк, но может сообщить о переводе
    $this->actingAs($user)->get(route('applicant.trainer.payment', $old))
        ->assertOk()
        ->assertSee('Срок заявки истёк')
        ->assertSee('Я оплатил');

    $this->actingAs($user)->post(route('applicant.trainer.claim', $old), ['payer_note' => 'перевёл'])
        ->assertRedirect();

    expect($old->fresh()->status)->toBe(TrainerPayment::CLAIMED);
});

test('письмо кандидату по тренировке модель не пишет — лишнего запроса нет', function () {
    $session = playedSession($this);

    $writer = new class implements DecisionWriter
    {
        public int $calls = 0;

        public function available(): bool
        {
            return true;
        }

        public function write(AiInterview $interview, string $outcome, array $reasons, string $fallback): string
        {
            $this->calls++;

            return $fallback;
        }
    };

    (new MakeHiringDecision($session->ai_interview_id))->handle($writer);

    expect($writer->calls)->toBe(0)
        ->and($session->fresh()->status)->toBe(TrainerSession::STATUS_FINISHED);
});
