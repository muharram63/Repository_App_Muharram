<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Проверка одного требования вакансии у одного кандидата.
 *
 * Главная мысль: статус не окончателен после разбора документов. Требование,
 * которого нет в резюме, кандидат может закрыть словами на собеседовании —
 * тогда статус поднимается до confirmed_in_interview. Ворота решения смотрят
 * на финальный статус, иначе система отказывала бы всем, у кого навык есть,
 * но в резюме о нём не написано.
 */
class AiRequirementCheck extends Model
{
    protected $fillable = [
        'ai_interview_id',
        'criterion_id',
        'requirement',
        'kind',
        'status',
        'evidence_quote',
        'confidence',
        'origin',
    ];

    protected $casts = [
        // цитата из резюме или ответа кандидата — персональные данные
        'evidence_quote' => 'encrypted',
        'confidence' => 'integer',
    ];

    protected $attributes = [
        'status' => 'missing',
        'kind' => 'must',
        'origin' => 'documents',
    ];

    public const STATUSES = [
        'found' => 'Найдено',
        'partial' => 'Частично',
        'missing' => 'Не найдено',
        'confirmed_in_interview' => 'Подтверждено на собеседовании',
    ];

    public const ORIGINS = [
        'documents' => 'Документы',
        'interview' => 'Собеседование',
    ];

    /**
     * Статусы, при которых требование считается закрытым. partial сюда не
     * входит: «частично» — это половина балла в оценке, но не пропуск через
     * ворота обязательного требования.
     */
    public const SATISFIED = ['found', 'confirmed_in_interview'];

    public function interview()
    {
        return $this->belongsTo(AiInterview::class, 'ai_interview_id');
    }

    public function criterion()
    {
        return $this->belongsTo(AiInterviewCriterion::class, 'criterion_id');
    }

    public function isSatisfied(): bool
    {
        return in_array($this->status, self::SATISFIED, true);
    }

    public function isMust(): bool
    {
        return $this->kind === 'must';
    }

    /**
     * Вклад в балл за документы: закрытое требование — единица,
     * частичное — половина, ненайденное — ноль.
     */
    public function weightShare(): float
    {
        return match ($this->status) {
            'found', 'confirmed_in_interview' => 1.0,
            'partial' => 0.5,
            default => 0.0,
        };
    }

    /**
     * Незакрытые обязательные требования — то, из-за чего кандидату откажут.
     */
    public function scopeUnmetMust($query)
    {
        return $query->where('kind', 'must')->whereNotIn('status', self::SATISFIED);
    }

    public function statusLabel(): string
    {
        return __(self::STATUSES[$this->status] ?? $this->status);
    }
}
