<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResumeResponse extends Model
{
    protected $table = 'resume_responses';

    protected $fillable = [
        'employer_id',
        'resume_id',
        'message',
        'status',
    ];

    // чтобы у только что созданной модели статус был не null, а как в БД
    protected $attributes = [
        "status" => "new",
    ];

    public function employer()
    {
        return $this->belongsTo(Employer::class);
    }

    public function resume()
    {
        return $this->belongsTo(Resume::class);
    }
}
