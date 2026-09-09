<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Навык из справочника — то, что кандидат может подтвердить заданием.
 */
class Skill extends Model
{
    protected $fillable = ['name', 'area'];

    /**
     * Уровни задания. Ключ хранится в базе, подпись видит человек.
     */
    public const LEVELS = [
        'basic' => 'Начальный',
        'confident' => 'Уверенный',
        'expert' => 'Продвинутый',
    ];

    public function tests()
    {
        return $this->hasMany(SkillTest::class);
    }
}
