<?php

namespace App\Models\Trainer;

use Illuminate\Database\Eloquent\Model;

/**
 * Настройки тренажёра из админки: реквизиты для оплаты и подсказка, как платить.
 */
class TrainerSetting extends Model
{
    protected $table = 'trainer_settings';

    protected $fillable = ['key', 'value'];

    public const KEYS = [
        'wallet' => 'Номер кошелька или карты',
        'recipient' => 'Получатель',
        'instructions' => 'Как оплатить',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
