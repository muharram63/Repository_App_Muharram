<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VacancyResponse extends Model
{
    protected $table = 'vacancy_responses';

    protected $fillable = [
        'applicant_id',
        'vacancy_id',
        'message',
        'status',
    ];

    // чтобы у только что созданной модели статус был не null, а как в БД
    protected $attributes = [
        "status" => "new",
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function vacancy()
    {
        return $this->belongsTo(Vacancy::class);
    }
}
