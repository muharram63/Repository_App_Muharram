<?php

/*
 * php artisan trainer:demo — данные для показа жюри.
 */

use App\Console\Commands\TrainerDemo;
use App\Models\Trainer\TrainerPayment;
use App\Models\Trainer\TrainerSession;
use App\Models\User;
use App\Services\Trainer\TrainerWallet;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    config(['services.gemini.key' => null]);
    Illuminate\Support\Facades\Http::preventStrayRequests();
});

test('демо: кандидат, две законченные тренировки с разбором и заявка «Я оплатил»', function () {
    $this->artisan('trainer:demo')->assertSuccessful();

    $user = User::where('email', TrainerDemo::EMAIL)->sole();
    $sessions = TrainerSession::where('user_id', $user->id)->get();

    expect($sessions)->toHaveCount(2)
        ->and($sessions->every(fn ($s) => $s->demo && $s->isFinished() && $s->feedback_status === 'ready'))->toBeTrue()
        ->and($sessions->pluck('score')->sort()->values()->all())->toBe([68, 81])
        ->and(TrainerPayment::where('user_id', $user->id)->sole()->status)->toBe(TrainerPayment::CLAIMED)
        // попытки на демо потрачены, баланс не раздут
        ->and(app(TrainerWallet::class)->balance($user))->toBe(0)
        // демо не съедает дневной лимит
        ->and(app(TrainerWallet::class)->startedToday($user))->toBe(0);

    // разбор открывается и в нём «как правильно»
    $this->actingAs($user)->get(route('applicant.trainer.show', $sessions->firstWhere('score', 68)))
        ->assertOk()
        ->assertSee('CSRF')
        ->assertSee('evenSum')
        ->assertDontSee('модель была недоступна');

    // админ видит заявку на проверку; у демо-кандидата свой пароль, и его
    // хеш в сессии иначе разлогинил бы админа (AuthenticateSession)
    $this->flushSession();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(route('superadmin.trainer'))->assertOk()->assertSee(TrainerDemo::EMAIL);
});

test('повторный запуск пересоздаёт демо, а не копит его', function () {
    $this->artisan('trainer:demo')->assertSuccessful();
    $this->artisan('trainer:demo')->assertSuccessful();

    $user = User::where('email', TrainerDemo::EMAIL)->sole();

    expect(TrainerSession::where('user_id', $user->id)->count())->toBe(2)
        ->and(TrainerPayment::where('user_id', $user->id)->count())->toBe(1);
});
