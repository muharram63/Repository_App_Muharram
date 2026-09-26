<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Тестовое задание, составленное под конкретную вакансию и уровень.
 *
 * Эталонное решение и рубрика создаются заранее и наружу не уходят никогда:
 * иначе задание проверяло бы не умение, а внимательность к исходному коду
 * страницы. Наружу отдаёт только publicPayload().
 */
class AiTestTask extends Model
{
    protected $fillable = [
        'ai_interview_id',
        'kind',
        'statement',
        'format',
        'language',
        'reference_solution',
        'rubric',
        'time_limit_minutes',
        'issued_at',
        'deadline_at',
    ];

    protected $casts = [
        'reference_solution' => 'encrypted',
        'rubric' => 'encrypted:array',
        'time_limit_minutes' => 'integer',
        'issued_at' => 'datetime',
        'deadline_at' => 'datetime',
    ];

    protected $attributes = [
        'kind' => 'main',
        'format' => 'case',
    ];

    public const KIND_MAIN = 'main';

    public const KIND_FOLLOW_UP = 'follow_up';

    /**
     * Формат определяет способ проверки: код можно исполнить и получить факт,
     * остальное оценивает модель по рубрике.
     */
    public const FORMATS = [
        'code' => 'Код',
        'case' => 'Разбор кейса',
        'writing' => 'Письменная задача',
        'calculation' => 'Расчёт',
    ];

    /**
     * Запас на отправку: решение, ушедшее за секунду до дедлайна, может дойти
     * уже после. Наказывать за скорость сети нечестно — та же логика, что в
     * проверке навыков (SkillAttempt::GRACE_SECONDS).
     */
    public const GRACE_SECONDS = 60;

    public function interview()
    {
        return $this->belongsTo(AiInterview::class, 'ai_interview_id');
    }

    public function submissions()
    {
        return $this->hasMany(AiTestSubmission::class, 'ai_test_task_id');
    }

    public function submission()
    {
        return $this->hasOne(AiTestSubmission::class, 'ai_test_task_id')->latestOfMany();
    }

    /**
     * То, что можно показать кандидату. Всё остальное — служебное.
     */
    public function publicPayload(): array
    {
        return [
            'statement' => $this->statement,
            'format' => $this->format,
            'language' => $this->language,
            'time_limit_minutes' => $this->time_limit_minutes,
            'deadline_at' => $this->deadline_at,
        ];
    }

    /**
     * Срок вышел — с учётом запаса на доставку.
     */
    public function expired(): bool
    {
        return $this->deadline_at !== null
            && now()->greaterThan($this->deadline_at->copy()->addSeconds(self::GRACE_SECONDS));
    }

    /**
     * Сколько секунд осталось — по этому числу идёт отсчёт на странице.
     */
    public function secondsLeft(): int
    {
        if ($this->deadline_at === null) {
            return 0;
        }

        // diffInSeconds отдаёт дробное, а метод обещает целое
        return (int) max(0, floor(now()->diffInSeconds($this->deadline_at, false)));
    }

    public function isCode(): bool
    {
        return $this->format === 'code';
    }

    public function formatLabel(): string
    {
        return __(self::FORMATS[$this->format] ?? $this->format);
    }
}
