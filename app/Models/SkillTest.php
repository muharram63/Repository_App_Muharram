<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Готовое задание по навыку.
 *
 * Вопросы хранятся вместе с правильными ответами, поэтому наружу они уходят
 * только через publicQuestions(): иначе ответы приехали бы в браузер вместе
 * со страницей и проверять было бы нечего.
 */
class SkillTest extends Model
{
    /** Сколько вариантов одного уровня держим, чтобы задания не разошлись по рукам. */
    public const VARIANTS = 3;

    /**
     * Из скольких вопросов состоит задание — выбирает сам кандидат.
     * Короткое даёт быстро подтвердить навык, полное — показать глубину,
     * и заодно задания перестают быть одинаковыми у всех.
     *
     * Банк вариантов ведётся отдельно для каждой длины: задание на 5 вопросов
     * не годится тому, кто попросил 15.
     */
    public const LENGTHS = [
        5 => 'Короткое',
        10 => 'Обычное',
        15 => 'Полное',
    ];

    public const DEFAULT_LENGTH = 5;

    /** Сколько вопросов в этом задании. */
    public function length(): int
    {
        return count($this->questions ?? []);
    }

    protected $fillable = ['skill_id', 'level', 'variant', 'questions'];

    protected $casts = ['questions' => 'array'];

    public function skill()
    {
        return $this->belongsTo(Skill::class);
    }

    public function attempts()
    {
        return $this->hasMany(SkillAttempt::class);
    }

    /**
     * Вопросы для показа кандидату — без правильных ответов и пояснений.
     */
    public function publicQuestions(): array
    {
        return collect($this->questions ?? [])
            ->map(fn (array $q) => [
                'text' => $q['text'] ?? '',
                'options' => array_values($q['options'] ?? []),
            ])
            ->all();
    }

    public function levelLabel(): string
    {
        return __(Skill::LEVELS[$this->level] ?? $this->level);
    }
}
