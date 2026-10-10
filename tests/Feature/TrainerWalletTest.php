<?php

/*
 * Попытки тренажёра: бесплатная, списание, лимит в сутки, возврат.
 *
 * Деньги и попытки — то, где ошибка стоит дороже всего: человек заплатил и
 * не получил тренировку, или получил бесплатную трижды. Поэтому каждое
 * правило проверяется на краях.
 */

use App\Models\Trainer\TrainerLedgerEntry;
use App\Models\Trainer\TrainerSession;
use App\Models\User;
use App\Services\Trainer\TrainerException;
use App\Services\Trainer\TrainerWallet;

function trainee(?string $phone = null, ?string $email = null): User
{
    $user = User::factory()->applicant()->create($email ? ['email' => $email] : []);
    $applicant = makeApplicant($user);
    $applicant->update(['phone' => $phone]);

    return $user->fresh();
}

function wallet(): TrainerWallet
{
    return app(TrainerWallet::class);
}

function fakeSession(User $user): TrainerSession
{
    return TrainerSession::create([
        'user_id' => $user->id, 'track_title' => 'PHP-разработчик', 'level' => 'junior',
    ]);
}

test('первая попытка бесплатна и выдаётся один раз', function () {
    $user = trainee();

    expect(wallet()->grantFree($user))->toBeTrue()
        ->and(wallet()->grantFree($user))->toBeFalse()
        ->and(wallet()->balance($user))->toBe(1)
        ->and(TrainerLedgerEntry::where('user_id', $user->id)->sole()->reason)->toBe('free');
});

test('удалил аккаунт и зарегистрировался с тем же email — бесплатной попытки нет', function () {
    $first = trainee(email: 'farangis@example.com');
    wallet()->grantFree($first);
    $first->delete();

    // регистр и пробелы email не помогают обойти правило
    $again = trainee(email: 'Farangis@Example.com');

    expect(wallet()->grantFree($again))->toBeFalse()
        ->and(wallet()->balance($again))->toBe(0);
});

test('другой email, но тот же телефон — бесплатной попытки нет', function () {
    wallet()->grantFree(trainee('+992 93 123 45 67'));

    $second = trainee('93-123-45-67');

    expect(wallet()->grantFree($second))->toBeFalse();
});

test('у работодателя бесплатной попытки нет', function () {
    $employer = User::factory()->employer()->create();

    expect(wallet()->grantFree($employer))->toBeFalse();
});

test('тренировка списывает попытку; без попыток — понятный отказ', function () {
    $user = trainee();
    wallet()->grantFree($user);

    $session = wallet()->spendOn($user, fn () => fakeSession($user));

    expect(wallet()->balance($user))->toBe(0)
        ->and(TrainerLedgerEntry::where('session_id', $session->id)->where('reason', 'start')->sole()->delta)->toBe(-1);

    expect(fn () => wallet()->spendOn($user, fn () => fakeSession($user)))
        ->toThrow(TrainerException::class, __('Попытки закончились. Купите пакет попыток, чтобы продолжить.'));

    // отказ не оставил ни тренировки, ни списания
    expect(TrainerSession::where('user_id', $user->id)->count())->toBe(1)
        ->and(wallet()->balance($user))->toBe(0);
});

test('не больше N тренировок в сутки, завтра — снова можно', function () {
    config(['trainer.daily_limit' => 2]);
    $user = trainee();
    wallet()->credit($user, 5, 'manual');

    wallet()->spendOn($user, fn () => fakeSession($user));
    wallet()->spendOn($user, fn () => fakeSession($user));

    expect(fn () => wallet()->spendOn($user, fn () => fakeSession($user)))
        ->toThrow(TrainerException::class, __('На сегодня тренировок достаточно: не больше :limit в сутки. Возвращайтесь завтра.', ['limit' => 2]));
    expect(wallet()->balance($user))->toBe(3);

    $this->travel(1)->days();
    wallet()->spendOn($user, fn () => fakeSession($user));
    expect(wallet()->balance($user))->toBe(2);
});

test('сбой при создании тренировки откатывает списание', function () {
    $user = trainee();
    wallet()->grantFree($user);

    expect(fn () => wallet()->spendOn($user, fn () => throw new RuntimeException('сбой')))
        ->toThrow(RuntimeException::class);

    expect(wallet()->balance($user))->toBe(1);
});

test('возврат попытки — один раз на тренировку', function () {
    $user = trainee();
    wallet()->grantFree($user);
    $session = wallet()->spendOn($user, fn () => fakeSession($user));

    expect(wallet()->refund($session, 'ИИ не смог проверить задание'))->toBeTrue()
        ->and(wallet()->refund($session->fresh(), 'ещё раз'))->toBeFalse()
        ->and(wallet()->balance($user))->toBe(1)
        ->and($session->fresh()->status)->toBe(TrainerSession::STATUS_REFUNDED);
});

test('каждое начисление и списание — в журнале, остаток из журнала', function () {
    $user = trainee();
    wallet()->grantFree($user);
    wallet()->credit($user, 3, 'purchase');
    $session = wallet()->spendOn($user, fn () => fakeSession($user));
    wallet()->refund($session, 'сбой');

    $reasons = TrainerLedgerEntry::where('user_id', $user->id)->orderBy('id')->pluck('reason')->all();

    expect($reasons)->toBe(['free', 'purchase', 'start', 'refund'])
        ->and(wallet()->balance($user))->toBe(4);
});
