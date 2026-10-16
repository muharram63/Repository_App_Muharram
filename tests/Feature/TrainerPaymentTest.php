<?php

/*
 * Оплата пакетов тренажёра: заявка → «Я оплатил» → подтверждение админом.
 *
 * Деньги приходят вручную, поэтому главное — попытки начисляются ровно один
 * раз, и только после решения администратора; чужую заявку не увидеть.
 */

use App\Models\Trainer\TrainerLedgerEntry;
use App\Models\Trainer\TrainerPackage;
use App\Models\Trainer\TrainerPayment;
use App\Models\Trainer\TrainerSetting;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Payments\ManualTransferGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Trainer\TrainerWallet;

function buyer(): User
{
    static $n = 0;

    $user = User::factory()->applicant()->create();
    // телефон — без него бесплатной попытки нет
    makeApplicant($user)->update(['phone' => '+992 92 '.str_pad((string) ++$n, 7, '0', STR_PAD_LEFT)]);

    return $user->fresh();
}

function package(int $attempts = 5, int $price = 50): TrainerPackage
{
    return TrainerPackage::create(['title' => 'Пакет '.$attempts, 'attempts' => $attempts, 'price' => $price, 'active' => true]);
}

test('кандидат видит тренажёр: бесплатная попытка, пакеты; в меню есть «Тренажёр»', function () {
    $user = buyer();
    package();

    $this->actingAs($user)->get(route('applicant.trainer.index'))
        ->assertOk()
        ->assertSee(__('Первая тренировка — бесплатно. Попытка уже начислена.'))
        ->assertSee('<b id="trBalance">1</b>', false)
        ->assertSee('Пакет 5')
        ->assertSee(route('applicant.trainer.index'), false);
});

test('путь оплаты: заявка → реквизиты → «Я оплатил» → админ подтверждает → попытки начислены', function () {
    TrainerSetting::put('wallet', '+992 900 00 00 00');
    TrainerSetting::put('recipient', 'ООО «Workio»');
    $user = buyer();
    $pack = package(5, 50);
    $admin = User::factory()->admin()->create();

    $this->actingAs($user)->post(route('applicant.trainer.buy', $pack))->assertRedirect();
    $payment = TrainerPayment::sole();

    expect($payment->status)->toBe(TrainerPayment::PENDING)
        ->and($payment->amount)->toBe(50)
        ->and($payment->gateway)->toBe('manual');

    $this->actingAs($user)->get(route('applicant.trainer.payment', $payment))
        ->assertOk()
        ->assertSee('+992 900 00 00 00')
        ->assertSee($payment->reference())
        ->assertSee(__('Я оплатил'));

    $this->actingAs($user)->post(route('applicant.trainer.claim', $payment), ['payer_note' => '+992 93 *** 45 67'])->assertRedirect();
    expect($payment->fresh()->status)->toBe(TrainerPayment::CLAIMED);

    // заявка видна в админке, «Я оплатил» — наверху
    $this->actingAs($admin)->get(route('superadmin.trainer'))
        ->assertOk()
        ->assertSee($payment->reference())
        ->assertSee('+992 93 *** 45 67');

    $this->actingAs($admin)->post(route('superadmin.trainer.confirm', $payment))->assertRedirect();
    // повторное подтверждение ничего не начисляет
    $this->actingAs($admin)->post(route('superadmin.trainer.confirm', $payment))->assertRedirect();

    expect($payment->fresh()->status)->toBe(TrainerPayment::CONFIRMED)
        ->and(TrainerLedgerEntry::where('payment_id', $payment->id)->count())->toBe(1)
        ->and(app(TrainerWallet::class)->balance($user))->toBe(5)
        ->and(UserNotification::where('user_id', $user->id)->where('title', 'Оплата подтверждена')->exists())->toBeTrue();
});

test('отклонённая заявка — без попыток, с причиной для кандидата', function () {
    $user = buyer();
    $admin = User::factory()->admin()->create();
    $this->actingAs($user)->post(route('applicant.trainer.buy', package()));
    $payment = TrainerPayment::sole();

    $this->actingAs($admin)->post(route('superadmin.trainer.reject', $payment), ['reason' => 'Перевод не найден'])->assertRedirect();

    expect($payment->fresh()->status)->toBe(TrainerPayment::REJECTED)
        ->and(app(TrainerWallet::class)->balance($user))->toBe(0)
        ->and(UserNotification::where('user_id', $user->id)->where('title', 'Оплата не подтверждена')->first()->body)->toContain('Перевод не найден');

    // решённую заявку уже не подтвердить
    $this->actingAs($admin)->post(route('superadmin.trainer.confirm', $payment));
    expect(app(TrainerWallet::class)->balance($user))->toBe(0);
});

test('не больше трёх открытых заявок у одного кандидата', function () {
    $user = buyer();
    $pack = package();

    foreach (range(1, 3) as $i) {
        $this->actingAs($user)->post(route('applicant.trainer.buy', $pack));
    }

    $this->actingAs($user)->post(route('applicant.trainer.buy', $pack))->assertSessionHas('error');
    expect(TrainerPayment::count())->toBe(3);
});

test('чужую заявку не открыть и не отметить оплаченной', function () {
    $owner = buyer();
    $stranger = buyer();
    $this->actingAs($owner)->post(route('applicant.trainer.buy', package()));
    $payment = TrainerPayment::sole();

    $this->actingAs($stranger)->get(route('applicant.trainer.payment', $payment))->assertForbidden();
    $this->actingAs($stranger)->post(route('applicant.trainer.claim', $payment))->assertForbidden();
});

test('админка тренажёра закрыта для не-админов', function () {
    $this->actingAs(buyer())->get(route('superadmin.trainer'))->assertForbidden();
});

test('админ начисляет попытки вручную — с пометкой в журнале', function () {
    $user = buyer();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('superadmin.trainer.grant'), [
        'email' => $user->email, 'attempts' => 2, 'note' => 'оплата наличными',
    ])->assertRedirect();

    $entry = TrainerLedgerEntry::where('user_id', $user->id)->sole();
    expect($entry->delta)->toBe(2)
        ->and($entry->reason)->toBe('manual')
        ->and($entry->note)->toBe('оплата наличными')
        ->and($entry->created_by)->toBe($admin->id);
});

test('админ меняет пакеты и реквизиты', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('superadmin.trainer.packages.store'), [
        'title' => 'Стандарт', 'attempts' => 5, 'price' => 45, 'active' => '1',
    ])->assertRedirect();
    $pack = TrainerPackage::sole();

    $this->actingAs($admin)->post(route('superadmin.trainer.packages.update', $pack), [
        'title' => 'Стандарт', 'attempts' => 5, 'price' => 40,
    ])->assertRedirect();
    expect($pack->fresh()->price)->toBe(40)->and($pack->fresh()->active)->toBeFalse();

    $this->actingAs($admin)->post(route('superadmin.trainer.settings'), [
        'wallet' => '4444 5555', 'recipient' => 'Получатель', 'instructions' => 'Укажите код',
    ])->assertRedirect();
    expect(TrainerSetting::get('wallet'))->toBe('4444 5555');
});

test('оплата — отдельный модуль за интерфейсом: сейчас ручной перевод', function () {
    expect(app(PaymentGateway::class))->toBeInstanceOf(ManualTransferGateway::class)
        ->and(app(PaymentGateway::class)->confirmsAutomatically())->toBeFalse();
});
