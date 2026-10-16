<?php

namespace App\Services\Trainer;

use App\Models\Trainer\TrainerPackage;
use App\Models\Trainer\TrainerPayment;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;

/**
 * Заявки на оплату пакетов попыток.
 *
 * Путь заявки: создана (кандидат видит реквизиты) → «Я оплатил» →
 * администратор подтверждает (попытки начисляются, запись в журнале) или
 * отклоняет с причиной. Подтверждение — под замком строки заявки: двойное
 * нажатие не начислит попытки дважды.
 */
class TrainerPayments
{
    /** Не больше стольких открытых заявок у одного человека — от спама в админку. */
    public const MAX_OPEN = 3;

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly TrainerWallet $wallet,
    ) {
    }

    public function gateway(): PaymentGateway
    {
        return $this->gateway;
    }

    /** @throws TrainerException */
    public function create(User $user, TrainerPackage $package): TrainerPayment
    {
        if (! $package->active) {
            throw new TrainerException(__('Этот пакет больше не продаётся.'));
        }

        $open = TrainerPayment::where('user_id', $user->id)
            ->whereIn('status', [TrainerPayment::PENDING, TrainerPayment::CLAIMED])
            ->count();

        if ($open >= self::MAX_OPEN) {
            throw new TrainerException(__('У вас уже есть неоплаченные заявки. Оплатите или дождитесь проверки, прежде чем создавать новую.'));
        }

        return TrainerPayment::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'package_title' => $package->title,
            'attempts' => $package->attempts,
            'amount' => $package->price,
            'gateway' => $this->gateway->key(),
            'status' => TrainerPayment::PENDING,
        ]);
    }

    /**
     * Кандидат нажал «Я оплатил».
     *
     * @return bool принята ли отметка (false — заявка уже не ждёт оплаты)
     */
    public function claim(TrainerPayment $payment, ?string $note): bool
    {
        // и после истечения срока: человек мог перевести деньги, просто не
        // нажал вовремя — деньги не должны пропасть
        if (! in_array($payment->status, [TrainerPayment::PENDING, TrainerPayment::EXPIRED], true)) {
            return false;
        }

        $payment->update([
            'status' => TrainerPayment::CLAIMED,
            'payer_note' => $note ? mb_substr(trim($note), 0, 255) : null,
            'claimed_at' => now(),
        ]);

        return true;
    }

    /**
     * Подтвердить оплату и начислить попытки — один раз.
     *
     * @return bool начислены ли сейчас (false — заявка уже решена)
     */
    public function confirm(TrainerPayment $payment, ?User $admin): bool
    {
        $done = DB::transaction(function () use ($payment, $admin) {
            $fresh = TrainerPayment::whereKey($payment->id)->lockForUpdate()->first();

            if (! $fresh || ! $fresh->isOpen()) {
                return false;
            }

            $fresh->update([
                'status' => TrainerPayment::CONFIRMED,
                'decided_by' => $admin?->id,
                'decided_at' => now(),
            ]);

            $this->wallet->credit($fresh->user, $fresh->attempts, 'purchase', $fresh->id, $fresh->reference(), $admin?->id);

            return true;
        });

        if ($done) {
            UserNotification::deliver(
                $payment->user_id,
                'trainer',
                UserNotification::text('Оплата подтверждена'),
                UserNotification::text('Начислено попыток: :count. Можно тренироваться.', ['count' => $payment->attempts]),
                route('applicant.trainer.index'),
            );
        }

        return $done;
    }

    /**
     * Закрыть заявки, по которым так и не нажали «Я оплатил».
     *
     * Без срока они висели в «Открытых» у администратора вечно. Каждый час
     * из планировщика и при открытии страниц тренажёра — на случай, если
     * планировщик не запущен.
     *
     * @return int сколько закрыто
     */
    public function expireStale(): int
    {
        return TrainerPayment::where('status', TrainerPayment::PENDING)
            ->where('created_at', '<', now()->subDays(max(1, (int) config('trainer.payment_ttl_days', 7))))
            ->update(['status' => TrainerPayment::EXPIRED, 'updated_at' => now()]);
    }

    /** Отклонить заявку с причиной. */
    public function reject(TrainerPayment $payment, ?User $admin, string $reason): bool
    {
        $done = DB::transaction(function () use ($payment, $admin, $reason) {
            $fresh = TrainerPayment::whereKey($payment->id)->lockForUpdate()->first();

            if (! $fresh || ! $fresh->isOpen()) {
                return false;
            }

            $fresh->update([
                'status' => TrainerPayment::REJECTED,
                'reject_reason' => mb_substr(trim($reason), 0, 255),
                'decided_by' => $admin?->id,
                'decided_at' => now(),
            ]);

            return true;
        });

        if ($done) {
            UserNotification::deliver(
                $payment->user_id,
                'trainer',
                UserNotification::text('Оплата не подтверждена'),
                UserNotification::text('Заявка :ref: :reason', ['ref' => $payment->reference(), 'reason' => mb_substr(trim($reason), 0, 200)]),
                route('applicant.trainer.index'),
            );
        }

        return $done;
    }
}
