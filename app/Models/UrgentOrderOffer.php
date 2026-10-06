<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Отклик мастера на срочную заявку: цена, когда сможет, пара слов.
 */
class UrgentOrderOffer extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CHOSEN = 'chosen';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    /** Выбранный исполнитель не пришёл — заказчик отменил выбор. */
    public const STATUS_DROPPED = 'dropped';

    public const STATUSES = [
        self::STATUS_PENDING => 'Ждёт решения заказчика',
        self::STATUS_CHOSEN => 'Вас выбрали',
        self::STATUS_DECLINED => 'Выбран другой мастер',
        self::STATUS_WITHDRAWN => 'Отклик отозван',
        self::STATUS_CANCELLED => 'Заказчик отменил заявку',
        self::STATUS_EXPIRED => 'Срок заявки истёк',
        self::STATUS_DROPPED => 'Заказчик отменил выбор',
    ];

    /** Те же статусы глазами заказчика: «Вас выбрали» ему не подходит. */
    public const CUSTOMER_STATUSES = [
        self::STATUS_PENDING => 'Ждёт вашего решения',
        self::STATUS_CHOSEN => 'Исполнитель',
        self::STATUS_DECLINED => 'Не выбран',
        self::STATUS_WITHDRAWN => 'Отклик отозван',
        self::STATUS_CANCELLED => 'Заявка отменена',
        self::STATUS_EXPIRED => 'Срок истёк',
        self::STATUS_DROPPED => 'Выбор отменён',
    ];

    public const CAN_START = [
        'today' => 'Сегодня',
        'tomorrow' => 'Завтра',
        'later' => 'Через 2–3 дня',
    ];

    protected $fillable = [
        'urgent_order_id',
        'applicant_id',
        'message',
        'price',
        'can_start',
        'status',
    ];

    protected $casts = [
        'price' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(UrgentOrder::class, 'urgent_order_id');
    }

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_PENDING || $this->status === self::STATUS_CHOSEN;
    }

    public function statusLabel(): string
    {
        return __(self::STATUSES[$this->status] ?? $this->status);
    }

    public function customerStatusLabel(): string
    {
        return __(self::CUSTOMER_STATUSES[$this->status] ?? $this->status);
    }

    public function canStartLabel(): string
    {
        return __(self::CAN_START[$this->can_start] ?? $this->can_start);
    }
}
