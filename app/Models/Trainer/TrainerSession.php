<?php

namespace App\Models\Trainer;

use App\Models\AiInterview;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Database\Eloquent\Model;

/**
 * Тренировка кандидата.
 *
 * Сам разговор, задание и оценка живут в собеседовании движка (ai_interview);
 * здесь — то, что принадлежит тренажёру: направление, уровень, итоговый балл
 * и разбор для кандидата.
 */
class TrainerSession extends Model
{
    protected $table = 'trainer_sessions';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'user_id', 'track_id', 'track_title', 'level', 'vacancy_id', 'ai_interview_id',
        'status', 'score', 'feedback', 'feedback_status', 'demo', 'finished_at', 'refunded_at',
    ];

    protected $casts = [
        'feedback' => 'array',
        'score' => 'integer',
        'demo' => 'boolean',
        'finished_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function track()
    {
        return $this->belongsTo(TrainerTrack::class, 'track_id');
    }

    public function interview()
    {
        return $this->belongsTo(AiInterview::class, 'ai_interview_id');
    }

    public function vacancy()
    {
        return $this->belongsTo(Vacancy::class);
    }

    public function levelLabel(): string
    {
        return config('trainer.levels.'.$this->level.'.label', $this->level);
    }

    public function isFinished(): bool
    {
        return $this->status === self::STATUS_FINISHED;
    }

    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    /**
     * Шла, но собеседования больше нет: кандидат удалил его (отказ, удаление
     * данных). Продолжить нечего — без этой проверки ссылка «Продолжить»
     * строилась на пустой номер и страница падала.
     */
    public function isAborted(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->ai_interview_id === null;
    }

    public function statusLabel(): string
    {
        if ($this->isAborted()) {
            return __('Прервана');
        }

        return match ($this->status) {
            self::STATUS_FINISHED => __('Завершена'),
            self::STATUS_REFUNDED => __('Попытка возвращена'),
            default => __('Идёт'),
        };
    }
}
