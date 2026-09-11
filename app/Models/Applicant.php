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
}
