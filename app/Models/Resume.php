<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Resume extends Model
{
    protected $table = 'resumes';
    protected $fillable = [
        'applicant_id',
        'profession',
        'experience_years',
        'desired_position',
        'desired_salary',
        'url_website',
        'skills',
        'languages',
        'place_work',
        'description',
        'documents',
        'style',
    ];

    /**
     * Оформления, из которых выбирает соискатель. Ключ хранится в базе,
     * подпись показывается в интерфейсе.
     */
    public const STYLES = [
        'classic' => 'Классическое',
        'modern' => 'Современное',
        'compact' => 'Компактное',
        'strict' => 'Строгое',
        'elegant' => 'Элегантное',
        'bold' => 'Контрастное',
        'soft' => 'Мягкое',
        'editorial' => 'Журнальное',
        'tech' => 'Техническое',
        'warm' => 'Тёплое',
        'night' => 'Тёмное',
        'blueprint' => 'Чертёж',
        'brutal' => 'Брутальное',
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function responses()
    {
        return $this->hasMany(ResumeResponse::class);
    }
}
