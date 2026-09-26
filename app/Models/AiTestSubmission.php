<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Решение тестового задания, присланное кандидатом.
 *
 * Результат реального исполнения кода хранится отдельно от мнения модели
 * намеренно: код возврата и упавшие тесты — это факт, и в спорном случае он
 * весомее любой оценки. Если факт и мнение расходятся, в отчёте видно оба.
 */
class AiTestSubmission extends Model
{
    protected $fillable = [
        'ai_test_task_id',
        'content',
        'files',
        'execution',
        'score',
        'review',
        'submitted_at',
        'graded_at',
    ];

    protected $casts = [
        // решение кандидата — его интеллектуальный труд и персональные данные
        'content' => 'encrypted',
        'review' => 'encrypted:array',
        // технический вывод запуска, персональных данных не содержит
        'files' => 'array',
        'execution' => 'array',
        'score' => 'integer',
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(AiTestTask::class, 'ai_test_task_id');
    }

    public function isGraded(): bool
    {
        return $this->graded_at !== null;
    }

    /**
     * Код исполнялся и завершился без ошибки.
     */
    public function executionPassed(): bool
    {
        return ($this->execution['return_code'] ?? null) === 0;
    }

    /**
     * Исполнение вообще проводилось: для кейса или письменной задачи запускать
     * нечего, и отсутствие результата не является провалом.
     */
    public function wasExecuted(): bool
    {
        return filled($this->execution);
    }
}
