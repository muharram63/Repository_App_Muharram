<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vacancy extends Model
{
    /**
     * Как в вакансиях записывают сомони. Пустое значение считаем своим:
     * площадка работает в сомони, и старые записи валюту не указывали.
     */
    public const SOMONI = ['TJS', 'tjs', 'somoni', 'сомони', 'смн', ''];

    /**
     * Площадка работает в сомони, коды приводим к читаемому виду.
     */
    public function currencyLabel(): string
    {
        return in_array((string) $this->currency, self::SOMONI, true) ? 'сомони' : (string) $this->currency;
    }

    /**
     * Только вакансии в сомони — чтобы не складывать разные валюты
     * в одно среднее.
     */
    public function scopeInSomoni($query)
    {
        return $query->whereIn('currency', self::SOMONI);
    }

    protected $fillable = [
      'title',
      'description',
      'employer_id',
      'salary_from',
      'salary_to',
      'currency',
      'employment_type',
      'work_schedule',
      'experience_required',
      'skill',
      'status',
      'city_id',
    ];

    public function employer()
    {
        return $this->belongsTo(Employer::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function responses()
    {
        return $this->hasMany(VacancyResponse::class);
    }


}
