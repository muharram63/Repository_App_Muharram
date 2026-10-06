<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Отзыв заказчика о мастере по выполненной заявке.
 */
class MasterReview extends Model
{
    /** Сколько дней после выполнения отзыв можно оставить или исправить. */
    public const EDIT_DAYS = 30;

    public const RATINGS = [
        5 => 'Отлично',
        4 => 'Хорошо',
        3 => 'Нормально',
        2 => 'Плохо',
        1 => 'Очень плохо',
    ];

    protected $fillable = [
        'urgent_order_id',
        'applicant_id',
        'user_id',
        'rating',
        'comment',
        'master_reply',
        'replied_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'replied_at' => 'datetime',
    ];

    /** Отвечает на отзыв только тот мастер, о котором он. */
    public function canBeRepliedBy(?User $user): bool
    {
        return $user !== null && $user->applicant !== null && $user->applicant->id === $this->applicant_id;
    }

    public function order()
    {
        return $this->belongsTo(UrgentOrder::class, 'urgent_order_id');
    }

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** «Сино Каримова» → «Сино К.»: отзыв публичный, фамилию целиком не показываем. */
    public function authorName(): string
    {
        $parts = preg_split('/\s+/u', trim((string) $this->author?->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return __('Заказчик');
        }

        return $parts[0].(isset($parts[1]) ? ' '.Str::upper(Str::substr($parts[1], 0, 1)).'.' : '');
    }

    public function ratingLabel(): string
    {
        return __(self::RATINGS[$this->rating] ?? '');
    }

    /** «★★★★☆» — для вывода рядом с текстом. */
    public static function stars(float|int $rating): string
    {
        $full = (int) round($rating);

        return str_repeat('★', $full).str_repeat('☆', 5 - $full);
    }

    /**
     * Средняя оценка и число отзывов мастера.
     *
     * Отдельным запросом без сортировки: связь Applicant::reviews() сортирует
     * по новизне, а AVG/COUNT вместе с ORDER BY MySQL 8 в строгом режиме
     * не принимает (так уже падала лента заказов на DISTINCT).
     *
     * @return array{avg: ?float, count: int}
     */
    public static function summaryFor(?int $applicantId): array
    {
        if (! $applicantId) {
            return ['avg' => null, 'count' => 0];
        }

        $row = self::query()->where('applicant_id', $applicantId)
            ->selectRaw('avg(rating) as avg_rating, count(*) as total')
            ->toBase()->first();

        return [
            'avg' => $row?->avg_rating !== null ? (float) $row->avg_rating : null,
            'count' => (int) ($row?->total ?? 0),
        ];
    }

    /** «4,8» — средняя оценка с одним знаком. */
    public static function formatAverage(?float $average): string
    {
        return $average === null ? '—' : number_format($average, 1, ',', '');
    }
}
