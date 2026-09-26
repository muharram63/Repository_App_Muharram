<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Аудит обращений к модели.
 *
 * Смысл таблицы — чтобы любое решение можно было объяснить и перепроверить
 * через месяцы: видно, что спросили, что ответила модель, какой моделью и
 * сколько это стоило. Без этой записи «ИИ отказал» остаётся утверждением,
 * которое нечем подтвердить.
 *
 * Собеседование указывается необязательно: критерии вакансии модель предлагает
 * до того, как появится первый кандидат.
 */
class AiInteraction extends Model
{
    protected $fillable = [
        'ai_interview_id',
        'vacancy_id',
        'purpose',
        'model',
        'request',
        'response',
        'tokens_in',
        'tokens_out',
        'latency_ms',
        'status',
        'error',
    ];

    protected $casts = [
        // в промпт и ответ попадают данные кандидата
        'request' => 'encrypted',
        'response' => 'encrypted',
        'tokens_in' => 'integer',
        'tokens_out' => 'integer',
        'latency_ms' => 'integer',
    ];

    protected $attributes = [
        'status' => 'ok',
    ];

    public const PURPOSES = [
        'criteria_advice' => 'Предложение критериев',
        'resume_parse' => 'Разбор резюме',
        'document_audit' => 'Проверка документа',
        'interview_question' => 'Вопрос собеседования',
        'answer_score' => 'Оценка ответа',
        'task_compose' => 'Составление задания',
        'task_grade' => 'Проверка задания',
        'decision_summary' => 'Текст решения кандидату',
    ];

    /**
     * invalid — ответ пришёл, но не прошёл проверку схемой. Именно он, повторившись,
     * ведёт к статусу «ручная проверка»: случайное решение хуже отсутствия решения.
     */
    public const STATUSES = [
        'ok' => 'Успешно',
        'invalid' => 'Ответ не прошёл проверку',
        'failed' => 'Сбой обращения',
    ];

    public function interview()
    {
        return $this->belongsTo(AiInterview::class, 'ai_interview_id');
    }

    public function vacancy()
    {
        return $this->belongsTo(Vacancy::class);
    }

    public function succeeded(): bool
    {
        return $this->status === 'ok';
    }

    /**
     * Неудачные обращения по одной цели подряд — сигнал переводить
     * собеседование на ручную проверку.
     */
    public function scopeFailures($query)
    {
        return $query->whereIn('status', ['invalid', 'failed']);
    }

    public function purposeLabel(): string
    {
        return __(self::PURPOSES[$this->purpose] ?? $this->purpose);
    }
}
