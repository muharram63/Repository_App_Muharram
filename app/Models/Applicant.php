<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Applicant extends Model
{
    protected $fillable = [
      'user_id',
        'birth',
        'gender',
        'phone',
        'city' ,
         'address' ,
        'education' ,
        'about_me' ,
    ];
    protected $casts = [
        'birth' => 'date',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function resume()
    {
        return $this->hasMany(Resume::class);
    }

    public function vacancyResponses()
    {
        return $this->hasMany(VacancyResponse::class);
    }

    /**
     * Попытки пройти задания по навыкам: из них складываются подтверждения.
     */
    public function skillAttempts()
    {
        return $this->hasMany(SkillAttempt::class);
    }

    public function interviews()
    {
        return $this->hasMany(Interview::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Заполненность анкеты в процентах и список незаполненных полей.
     */
    public function completeness(): array
    {
        $fields = [
            'Дата рождения' => $this->birth,
            'Пол' => $this->gender,
            'Телефон' => $this->phone,
            'Город' => $this->city,
            'Адрес' => $this->address,
            'Образование' => $this->education,
            'О себе' => $this->about_me,
            'Фото' => $this->user?->avatar,
            'Резюме' => $this->resume()->count() ? 'да' : null,
        ];

        $filled = array_filter($fields, fn ($value) => ! empty($value));

        return [
            'percent' => (int) round(count($filled) / count($fields) * 100),
            'missing' => array_keys(array_diff_key($fields, $filled)),
        ];
    }
}
