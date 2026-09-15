<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Interview extends Model
{
    protected $table = 'interviews';

    protected $fillable = [
        'employer_id',
        'applicant_id',
        'vacancy_id',
        'vacancy_response_id',
        'resume_response_id',
        'scheduled_at',
        'duration_minutes',
        'room',
        'note',
        'status',
        'answered_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'answered_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'scheduled',
        'duration_minutes' => 30,
    ];

    public function employer()
    {
        return $this->belongsTo(Employer::class);
    }

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function vacancy()
    {
        return $this->belongsTo(Vacancy::class);
    }

    /**
     * Комната доступна за 15 минут до начала и до конца встречи + 30 минут запаса.
     */
    public function isRoomOpen(): bool
    {
        if (in_array($this->status, ['declined', 'canceled'], true)) {
            return false;
        }

        $opensAt = $this->scheduled_at->copy()->subMinutes(15);
        $closesAt = $this->scheduled_at->copy()->addMinutes($this->duration_minutes + 30);

        return now()->betweenIncluded($opensAt, $closesAt);
    }

    public function isPast(): bool
    {
        return $this->scheduled_at->copy()->addMinutes($this->duration_minutes)->isPast();
    }

    public function statusLabel(): string
    {
        $map = [
            'scheduled' => 'Ожидает подтверждения',
            'confirmed' => 'Подтверждено',
            'declined' => 'Отклонено',
            'canceled' => 'Отменено',
            'finished' => 'Завершено',
        ];

        return __($map[$this->status] ?? $this->status);
    }
}
