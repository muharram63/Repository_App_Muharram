<?php

namespace App\Services\Trainer;

use App\Models\Trainer\TrainerLedgerEntry;
use App\Models\Trainer\TrainerSession;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Попытки тренажёра: остаток, начисления, списания, возвраты.
 *
 * Остаток — сумма записей журнала, а не число в колонке: каждое начисление
 * и списание видно, и «потерять» попытку нельзя. Списание идёт под замком
 * строки пользователя — две вкладки не потратят одну попытку дважды. Возврат
 * за тренировку бывает один раз: это держит уникальный ключ журнала
 * (тренировка + причина), а не память кода.
 */
class TrainerWallet
{
    public function balance(User $user): int
    {
        return (int) TrainerLedgerEntry::where('user_id', $user->id)->sum('delta');
    }

    /**
     * Первая попытка бесплатно — один раз на человека.
     *
     * «Человек» узнаётся по email и по телефону из анкеты: удалить аккаунт и
     * зарегистрироваться снова с тем же email или телефоном — второй
     * бесплатной попытки не даст. Храним не сами данные, а их хеши.
     *
     * @return bool выдана ли попытка сейчас
     */
    public function grantFree(User $user): bool
    {
        if ($user->role !== 'applicant') {
            return false;
        }

        $email = self::hash(mb_strtolower(trim((string) $user->email)));
        $digits = preg_replace('/\D+/', '', (string) $user->applicant?->phone);
        // короткий номер — не телефон: по нему не отличить людей
        $phone = strlen($digits) >= 7 ? self::hash(substr($digits, -9)) : null;

        $taken = DB::table('trainer_free_claims')
            ->where('email_hash', $email)
            ->when($phone, fn ($q) => $q->orWhere('phone_hash', $phone))
            ->exists();

        if ($taken) {
            return false;
        }

        try {
            DB::transaction(function () use ($user, $email, $phone) {
                // уникальные ключи email_hash / phone_hash не дадут двум
                // одновременным запросам выдать попытку дважды
                DB::table('trainer_free_claims')->insert([
                    'user_id' => $user->id,
                    'email_hash' => $email,
                    'phone_hash' => $phone,
                    'created_at' => now(),
                ]);

                TrainerLedgerEntry::create(['user_id' => $user->id, 'delta' => 1, 'reason' => 'free']);
            });
        } catch (QueryException) {
            return false;
        }

        return true;
    }

    /** Начислить попытки: оплата пакета или вручную администратором. */
    public function credit(User $user, int $attempts, string $reason, ?int $paymentId = null, ?string $note = null, ?int $by = null): TrainerLedgerEntry
    {
        return TrainerLedgerEntry::create([
            'user_id' => $user->id,
            'delta' => max(1, $attempts),
            'reason' => $reason,
            'payment_id' => $paymentId,
            'note' => $note ? mb_substr($note, 0, 255) : null,
            'created_by' => $by,
        ]);
    }

    /** Тренировок за сегодня — для предела в сутки. */
    public function startedToday(User $user): int
    {
        return TrainerSession::where('user_id', $user->id)
            ->where('demo', false)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    /**
     * Проверить, можно ли начать тренировку, и списать попытку — атомарно.
     *
     * $start получает управление внутри транзакции, уже после списания, и
     * создаёт тренировку; если он упадёт, списание откатится вместе с ним.
     *
     * @param  callable(): TrainerSession  $start
     *
     * @throws TrainerException
     */
    public function spendOn(User $user, callable $start): TrainerSession
    {
        return DB::transaction(function () use ($user, $start) {
            // замок строки пользователя: проверка остатка и списание — одно целое
            User::whereKey($user->id)->lockForUpdate()->first();

            $limit = (int) config('trainer.daily_limit', 3);
            if ($limit > 0 && $this->startedToday($user) >= $limit) {
                throw new TrainerException(__('На сегодня тренировок достаточно: не больше :limit в сутки. Возвращайтесь завтра.', ['limit' => $limit]));
            }

            if ($this->balance($user) < 1) {
                throw new TrainerException(__('Попытки закончились. Купите пакет попыток, чтобы продолжить.'));
            }

            $session = $start();

            TrainerLedgerEntry::create([
                'user_id' => $user->id,
                'delta' => -1,
                'reason' => 'start',
                'session_id' => $session->id,
            ]);

            return $session;
        });
    }

    /**
     * Вернуть попытку за тренировку, сорвавшуюся по нашей вине. Один раз.
     *
     * @return bool вернули ли сейчас (false — уже возвращали)
     */
    public function refund(TrainerSession $session, string $note, ?int $by = null): bool
    {
        try {
            DB::transaction(function () use ($session, $note, $by) {
                TrainerLedgerEntry::create([
                    'user_id' => $session->user_id,
                    'delta' => 1,
                    'reason' => 'refund',
                    'session_id' => $session->id,
                    'note' => mb_substr($note, 0, 255),
                    'created_by' => $by,
                ]);

                $session->update(['status' => TrainerSession::STATUS_REFUNDED, 'refunded_at' => now()]);
            });
        } catch (QueryException) {
            // уникальный ключ (тренировка + refund): уже возвращали
            return false;
        }

        return true;
    }

    private static function hash(string $value): string
    {
        return hash('sha256', $value);
    }
}
