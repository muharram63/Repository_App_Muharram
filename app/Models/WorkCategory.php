<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Вид работ мастера: сантехника, электрика, ремонт техники…
 *
 * Не путать с Category — та описывает отрасль компании и вакансии. По этой
 * категории мастер раскладывает портфолио, а заказчик выбирает, кого звать.
 */
class WorkCategory extends Model
{
    protected $fillable = ['slug', 'name', 'icon', 'position'];

    protected $casts = [
        'position' => 'integer',
    ];

    /**
     * Название на языке интерфейса. В базе — русское, оно же ключ перевода.
     */
    public function label(): string
    {
        return __($this->name);
    }

    /** Название со значком — для списков и бейджей. */
    public function title(): string
    {
        return trim(($this->icon ? $this->icon.' ' : '').$this->label());
    }

    /** В порядке, заданном справочником, а не по алфавиту одного языка. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }
}
