<?php

namespace App\Models\Trainer;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Запись журнала попыток: каждое начисление (+) и списание (−).
 *
 * Остаток попыток — сумма записей пользователя, а не число в отдельной
 * колонке: так его нельзя «потерять» и всегда видно, откуда он взялся.
 */
class TrainerLedgerEntry extends Model
{
    protected $table = 'trainer_ledger';

    public const UPDATED_AT = null;

    public const REASONS = [
        'free' => 'Бесплатная попытка',
        'purchase' => 'Оплата пакета',
        'manual' => 'Начислено администратором',
        'start' => 'Тренировка',
        'refund' => 'Возврат: тренировка сорвалась по нашей вине',
    ];

    protected $fillable = ['user_id', 'delta', 'reason', 'payment_id', 'session_id', 'note', 'created_by'];

    protected $casts = ['delta' => 'integer'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reasonLabel(): string
    {
        return __(self::REASONS[$this->reason] ?? $this->reason);
    }
}
