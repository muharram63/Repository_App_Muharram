<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Одна реплика собеседования: вопрос ИИ или ответ кандидата.
 *
 * Балл и обоснование хранятся здесь же, но кандидату не показываются никогда —
 * они нужны решению и отчёту работодателю. Оценку ставит отдельный вызов
 * модели, который видит только пару «вопрос — ответ» и рубрику: в таком вызове
 * уговаривать модель нечем, потому что разговора он не видит.
 */
class AiInterviewTurn extends Model
{
    protected $fillable = [
        'ai_interview_id',
        'position',
        'role',
        'text',
        'question_kind',
        'criterion_key',
        'score',
        'max_score',
        'rationale',
        'injection_flagged',
    ];

    protected $casts = [
        // слова кандидата и вопросы о его опыте — персональные данные
        'text' => 'encrypted',
        'rationale' => 'encrypted',
        'position' => 'integer',
        'score' => 'integer',
        'max_score' => 'integer',
        'injection_flagged' => 'boolean',
    ];

    public const ROLE_AI = 'ai';

    public const ROLE_CANDIDATE = 'candidate';

    /**
     * Виды вопросов. clarifying — уточнение по расплывчатому ответу,
     * follow_up — дополнительный вопрос в пограничном дораунде.
     */
    public const QUESTION_KINDS = [
        'technical' => 'Технический',
        'behavioral' => 'Поведенческий',
        'situational' => 'Ситуационный',
        'clarifying' => 'Уточняющий',
        'follow_up' => 'Дополнительный',
    ];

    /**
     * Шкала оценки одного ответа. Десять градаций — это больше, чем модель
     * способна различать осмысленно; пять хватает, и обоснование к каждому
     * баллу остаётся внятным.
     */
    public const MAX_SCORE = 5;

    public function interview()
    {
        return $this->belongsTo(AiInterview::class, 'ai_interview_id');
    }

    public function criterion()
    {
        return $this->belongsTo(AiInterviewCriterion::class, 'criterion_key', 'key');
    }

    public function isFromCandidate(): bool
    {
        return $this->role === self::ROLE_CANDIDATE;
    }

    public function isScored(): bool
    {
        return $this->score !== null;
    }

    /**
     * Ответы кандидата — то, что подлежит оценке.
     */
    public function scopeAnswers($query)
    {
        return $query->where('role', self::ROLE_CANDIDATE);
    }

    /**
     * Доля балла от максимума — из них складывается оценка за интервью.
     */
    public function ratio(): ?float
    {
        if ($this->score === null || ! $this->max_score) {
            return null;
        }

        return $this->score / $this->max_score;
    }

    public function questionKindLabel(): ?string
    {
        if (! $this->question_kind) {
            return null;
        }

        return __(self::QUESTION_KINDS[$this->question_kind] ?? $this->question_kind);
    }
}
