<?php

namespace App\Models\Trainer;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Заявка на оплату пакета попыток.
 *
 * pending  — заявка создана, кандидат видит реквизиты;
 * claimed  — кандидат нажал «Я оплатил», ждёт проверки;
 * confirmed — админ подтвердил, попытки начислены;
 * rejected — админ отклонил (с причиной);
 * expired  — «Я оплатил» так и не нажали за TTL дней: заявка уходит из
 *            открытых. Нажать «Я оплатил» можно и после — вдруг перевод был.
 */
class TrainerPayment extends Model
{
    protected $table = 'trainer_payments';

    public const PENDING = 'pending';

    public const CLAIMED = 'claimed';

    public const CONFIRMED = 'confirmed';

    public const REJECTED = 'rejected';

    public const EXPIRED = 'expired';

    protected $fillable = [
        'user_id', 'package_id', 'package_title', 'attempts', 'amount', 'gateway', 'status',
        'payer_note', 'reject_reason', 'decided_by', 'claimed_at', 'decided_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'amount' => 'integer',
        'claimed_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function package()
    {
        return $this->belongsTo(TrainerPackage::class, 'package_id');
    }

    /** Код для назначения платежа: по нему админ найдёт перевод. */
    public function reference(): string
    {
        return 'WK-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::PENDING, self::CLAIMED], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::PENDING => __('Ждёт оплаты'),
            self::CLAIMED => __('Проверяем оплату'),
            self::CONFIRMED => __('Оплачено — попытки начислены'),
            self::REJECTED => __('Отклонено'),
            self::EXPIRED => __('Срок заявки истёк'),
            default => $this->status,
        };
    }
}
