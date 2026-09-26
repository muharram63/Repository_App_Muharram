<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Критерий оценки кандидата по вакансии.
 *
 * Модель предлагает набор критериев с весами по описанию вакансии, а
 * работодатель подтверждает. Неподтверждённый критерий в оценке не участвует:
 * иначе ИИ сам себе назначал бы правила, по которым потом отказывает людям.
 */
class AiInterviewCriterion extends Model
{
    use HasFactory;

    protected $table = 'ai_interview_criteria';

    protected $fillable = [
        'vacancy_id',
        'key',
        'label',
        'description',
        'kind',
        'weight',
        'source',
        'confirmed_at',
        'position',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'weight' => 'integer',
        'position' => 'integer',
    ];

    /**
     * must — обязательное требование. Работает как ворота: не закрыто —
     * отказ, сколько бы баллов ни набралось в остальном.
     * nice — желательное, влияет только на балл.
     */
    public const KINDS = [
        'must' => 'Обязательное',
        'nice' => 'Желательное',
    ];

    public const SOURCES = [
        'ai' => 'Предложено ИИ',
        'employer' => 'Добавлено работодателем',
    ];

    public function vacancy()
    {
        return $this->belongsTo(Vacancy::class);
    }

    /**
     * Проверки этого критерия у всех кандидатов.
     */
    public function checks()
    {
        return $this->hasMany(AiRequirementCheck::class, 'criterion_id');
    }

    /**
     * Только подтверждённые работодателем — по ним и идёт оценка.
     */
    public function scopeConfirmed($query)
    {
        return $query->whereNotNull('confirmed_at');
    }

    public function scopeMust($query)
    {
        return $query->where('kind', 'must');
    }

    /**
     * Порядок для интерфейса и для плана интервью: сначала обязательные,
     * дальше по заданной работодателем позиции.
     */
    public function scopeOrdered($query)
    {
        return $query->orderByRaw("CASE WHEN kind = 'must' THEN 0 ELSE 1 END")
            ->orderBy('position')
            ->orderBy('id');
    }

    public function isMust(): bool
    {
        return $this->kind === 'must';
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function kindLabel(): string
    {
        return __(self::KINDS[$this->kind] ?? $this->kind);
    }
}
