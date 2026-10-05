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
     * Допустимые статусы. Форма предлагает ровно их, а проверка раньше
     * пропускала любую строку до 255 символов — запросом в обход формы можно
     * было записать статус, которого не понимает ни каталог, ни аналитика.
     */
    public const STATUSES = ['active', 'inactive', 'closed', 'in_archived'];

    /**
     * Предел описания. Было 600 символов — меньше одной страницы, а из
     * описания ИИ-собеседование достаёт требования вакансии: в 600 символов
     * обязанности, условия и требования вместе не помещались. Колонка —
     * longText, так что ограничение только ради разумного объёма.
     */
    public const DESCRIPTION_MAX = 5000;

    /**
     * ИИ-собеседования вакансии уходят каскадом базы, а файлы документов
     * кандидатов — нет. Стираем их до удаления строки.
     */
    protected static function booted(): void
    {
        static::deleting(function (Vacancy $vacancy) {
            AiInterview::eraseWhere(fn ($query) => $query->where('vacancy_id', $vacancy->id));
        });
    }

    /**
     * Площадка работает в сомони, коды приводим к читаемому виду.
     */
    public function currencyLabel(): string
    {
        return in_array((string) $this->currency, self::SOMONI, true) ? __('сомони') : (string) $this->currency;
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
      'languages',
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
