<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Решение по кандидату — то, за что модуль отвечает перед человеком.
 *
 * Одна запись на каждый расчёт: пограничный балл приводит к дораунду и
 * пересчёту, и обе версии должны остаться. Иначе на вопрос «почему сначала
 * было иначе» ответить нечем.
 *
 * Решение принимает код, а не модель: DecisionEngine складывает взвешенную
 * сумму и применяет ворота. Модель даёт только пооценочные баллы с
 * обоснованием — чтобы исход не зависел от её настроения в этот раз.
 */
class AiDecision extends Model
{
    protected $fillable = [
        'ai_interview_id',
        'round',
        'documents_score',
        'interview_score',
        'test_score',
        'total',
        'outcome',
        'gate_reason',
        'reasons',
        'message_to_candidate',
        'requires_manual_review',
        'overridden_by',
        'overridden_at',
        'override_outcome',
        'override_note',
        'override_message',
        'released_at',
        'release_source',
    ];

    protected $casts = [
        'round' => 'integer',
        'documents_score' => 'integer',
        'interview_score' => 'integer',
        'test_score' => 'integer',
        'total' => 'integer',
        'reasons' => 'encrypted:array',
        'message_to_candidate' => 'encrypted',
        'override_note' => 'encrypted',
        'override_message' => 'encrypted',
        'requires_manual_review' => 'boolean',
        'overridden_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    protected $attributes = [
        'round' => 1,
    ];

    public const OUTCOMES = [
        'passed' => 'Прошёл отбор',
        'rejected' => 'Отказ',
        'manual_review' => 'Нужна ручная проверка',
    ];

    /**
     * Что именно решило исход. Без этого поля отчёт превращается в «так решил
     * ИИ», а объяснить решение кандидату или проверяющему было бы нечем.
     */
    public const GATES = [
        'missing_must_have' => 'Не выполнено обязательное требование',
        'unconfirmed_must_have' => 'Обязательное требование подтверждено лишь частично',
        'below_reject_threshold' => 'Балл ниже порога отказа',
        'above_accept_threshold' => 'Балл выше порога приёма',
        'borderline' => 'Пограничный балл',
        'borderline_after_follow_up' => 'Пограничный балл и после уточнений',
        'invalid_ai_response' => 'Модель дважды ответила неразборчиво',
        'empty_documents' => 'Документов недостаточно для разбора',
    ];

    public function interview()
    {
        return $this->belongsTo(AiInterview::class, 'ai_interview_id');
    }

    /**
     * Работодатель, не согласившийся с решением ИИ.
     */
    public function overriddenBy()
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }

    /**
     * Ответ отправлен кандидату. До этого решение — рекомендация, которую
     * видит только работодатель.
     */
    public function isReleased(): bool
    {
        return $this->released_at !== null;
    }

    public function isOverridden(): bool
    {
        return $this->overridden_at !== null;
    }

    /**
     * Действующий исход: слово человека весомее слова модели.
     */
    public function effectiveOutcome(): string
    {
        return $this->isOverridden() && $this->override_outcome
            ? $this->override_outcome
            : $this->outcome;
    }

    /**
     * Решение объявлено кандидату. При ручной проверке ему не уходит ни
     * отказ, ни приглашение — только обещанный срок ответа.
     */
    public function announced(): bool
    {
        return in_array($this->effectiveOutcome(), ['passed', 'rejected'], true);
    }

    /**
     * Что сейчас сказано кандидату.
     *
     * Изменил решение работодатель — его письмо, иначе письмо ИИ. Без этого
     * кандидат видел новый исход рядом со старым письмом: «Прошёл отбор» и
     * «к сожалению, мы остановились на других кандидатах».
     */
    public function candidateMessage(): ?string
    {
        return $this->isOverridden() && $this->override_message
            ? $this->override_message
            : $this->message_to_candidate;
    }

    public function outcomeLabel(): string
    {
        $outcome = $this->effectiveOutcome();

        return __(self::OUTCOMES[$outcome] ?? $outcome);
    }

    public function gateLabel(): ?string
    {
        if (! $this->gate_reason) {
            return null;
        }

        return __(self::GATES[$this->gate_reason] ?? $this->gate_reason);
    }
}
