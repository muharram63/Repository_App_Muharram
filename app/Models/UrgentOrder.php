<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Срочная заявка заказчика: что сломалось, где, к какому сроку.
 */
class UrgentOrder extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_DONE = 'done';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_OPEN => 'Ищем мастера',
        self::STATUS_ASSIGNED => 'Исполнитель выбран',
        self::STATUS_DONE => 'Выполнено',
        self::STATUS_EXPIRED => 'Срок истёк',
        self::STATUS_CANCELLED => 'Отменена',
    ];

    /** Что заказчик выбирает в форме — подписи кнопок. */
    public const DEADLINES = [
        'today' => 'Сегодня',
        '1d' => 'В течение суток',
        '3d' => 'За 2–3 дня',
    ];

    /** Сегодня и сутки — «Срочно»: такие заявки идут первыми. */
    public const URGENT_DEADLINES = ['today', '1d'];

    /**
     * «Сегодня» в 23:30 — это полчаса, мастер не успеет даже ответить.
     * Поэтому срок «сегодня» не короче этого числа часов.
     */
    public const MIN_TODAY_HOURS = 3;

    /** Открытых заявок у одного заказчика одновременно — не больше. */
    public const MAX_OPEN_PER_USER = 5;

    /** Фото проблемы — не больше. */
    public const MAX_PHOTOS = 5;

    public const DISK = 'public';

    protected $fillable = [
        'user_id',
        'work_category_id',
        'title',
        'description',
        'city_id',
        'district',
        'address',
        'contact_phone',
        'deadline_option',
        'deadline_at',
        'budget',
        'status',
        'chosen_offer_id',
        'closed_at',
    ];

    protected $casts = [
        'deadline_at' => 'datetime',
        'closed_at' => 'datetime',
        'budget' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleted(function (UrgentOrder $order) {
            Storage::disk(self::DISK)->deleteDirectory($order->folder());
        });
    }

    /**
     * Когда истекает срок, выбранный заказчиком.
     */
    public static function deadlineFor(string $option, ?CarbonInterface $from = null): CarbonInterface
    {
        $from = $from ? $from->copy() : now();

        return match ($option) {
            'today' => $from->copy()->endOfDay()->max($from->copy()->addHours(self::MIN_TODAY_HOURS)),
            '1d' => $from->addDay(),
            default => $from->addDays(3),
        };
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(WorkCategory::class, 'work_category_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function offers()
    {
        return $this->hasMany(UrgentOrderOffer::class)->latest('id');
    }

    /** Выбранный отклик — исполнитель заявки. */
    public function chosenOffer()
    {
        return $this->belongsTo(UrgentOrderOffer::class, 'chosen_offer_id');
    }

    public function isAssigned(): bool
    {
        return $this->status === self::STATUS_ASSIGNED;
    }

    /** Пользователь — выбранный исполнитель этой заявки. */
    public function isChosenMaster(?User $user): bool
    {
        if (! $user || ! $user->applicant || ! $this->chosen_offer_id) {
            return false;
        }

        $offer = $this->relationLoaded('chosenOffer') ? $this->chosenOffer : $this->chosenOffer()->first();

        return $offer !== null
            && $offer->applicant_id === $user->applicant->id
            && in_array($this->status, [self::STATUS_ASSIGNED, self::STATUS_DONE], true);
    }

    /**
     * Телефон заказчика: указанный в заявке, иначе из его анкеты.
     */
    public function contactPhone(): ?string
    {
        if (filled($this->contact_phone)) {
            return $this->contact_phone;
        }

        $owner = $this->user;

        return $owner?->employer?->phone ?: $owner?->applicant?->phone ?: null;
    }

    public function photos()
    {
        return $this->hasMany(UrgentOrderPhoto::class)->orderBy('position')->orderBy('id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function folder(): string
    {
        return 'orders/'.$this->id;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /** Бейдж «Срочно»: сегодня или в течение суток. */
    public function isUrgent(): bool
    {
        return in_array($this->deadline_option, self::URGENT_DEADLINES, true);
    }

    public function statusLabel(): string
    {
        return __(self::STATUSES[$this->status] ?? $this->status);
    }

    public function deadlineLabel(): string
    {
        return __(self::DEADLINES[$this->deadline_option] ?? $this->deadline_option);
    }

    /** Город и район — то, что видно всем мастерам. */
    public function place(): string
    {
        return collect([$this->city ? __($this->city->region) : null, $this->district])
            ->filter()->implode(', ');
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->user_id === $user->id;
    }

    /**
     * Точный адрес и телефон заказчика — заказчику, администратору и
     * выбранному мастеру. Остальные мастера видят только город и район.
     */
    public function addressVisibleTo(?User $user): bool
    {
        return $user !== null && ($this->isOwnedBy($user) || $user->role === 'admin' || $this->isChosenMaster($user));
    }

    /** Сколько осталось до конца срока — «3 ч», «1 д 4 ч». */
    public function timeLeftLabel(): ?string
    {
        if (! $this->isOpen() || $this->deadline_at->isPast()) {
            return null;
        }

        $minutes = (int) now()->diffInMinutes($this->deadline_at);
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);

        if ($days > 0) {
            return __(':d д :h ч', ['d' => $days, 'h' => $hours]);
        }

        return $hours > 0 ? __(':h ч', ['h' => $hours]) : __(':m мин', ['m' => max(1, $minutes % 60)]);
    }
}
