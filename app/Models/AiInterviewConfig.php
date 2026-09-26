<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Настройки ИИ-собеседования для одной вакансии.
 *
 * Работодатель заполняет их один раз, дальше каждый кандидат общается только
 * с моделью. Веса и пороги живут здесь, а не в коде, потому что «подходит» для
 * повара и для backend-разработчика — разные числа.
 */
class AiInterviewConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'vacancy_id',
        'enabled',
        'level',
        'language',
        'questions_count',
        'weight_documents',
        'weight_interview',
        'weight_test',
        'threshold_reject',
        'threshold_accept',
        'test_time_limit_minutes',
        'response_sla',
        'decision_mode',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'questions_count' => 'integer',
        'weight_documents' => 'integer',
        'weight_interview' => 'integer',
        'weight_test' => 'integer',
        'threshold_reject' => 'integer',
        'threshold_accept' => 'integer',
        'test_time_limit_minutes' => 'integer',
    ];

    /**
     * Уровень позиции: от него зависит глубина вопросов и сложность задания.
     */
    public const LEVELS = [
        'junior' => 'Начальный',
        'middle' => 'Средний',
        'senior' => 'Старший',
        'lead' => 'Ведущий',
    ];

    /**
     * auto — ИИ сам объявляет решение и ставит статус отклика.
     * advisory — ИИ считает и пишет отчёт, статус ставит работодатель.
     *
     * Второй режим нужен не для полумер, а для доверия: работодатель может
     * посмотреть на десяток решений прежде чем отдать их автоматике.
     */
    public const MODES = [
        'auto' => 'ИИ решает сам',
        'advisory' => 'ИИ советует, решает человек',
    ];

    /**
     * Из чего складывается итоговый балл. Ключи совпадают с именами колонок
     * weight_* и с ключами баллов в решении — по ним же идёт пересчёт.
     */
    public const WEIGHT_PARTS = ['documents', 'interview', 'test'];

    public function vacancy()
    {
        return $this->belongsTo(Vacancy::class);
    }

    /**
     * Критерии оценки этой вакансии. Хранятся у вакансии, а не у настроек:
     * настройки можно пересоздать, а проставленные по критериям баллы — нет.
     */
    public function criteria()
    {
        return $this->hasMany(AiInterviewCriterion::class, 'vacancy_id', 'vacancy_id');
    }

    /**
     * Веса в процентах должны давать сотню — иначе итоговый балл ничего не
     * значит. Проверяет приложение: в базе это условие пришлось бы описывать
     * отдельно для MySQL и для sqlite, на котором идут тесты.
     */
    public function weightsSum(): int
    {
        return $this->weight_documents + $this->weight_interview + $this->weight_test;
    }

    public function weightsAreValid(): bool
    {
        return $this->weightsSum() === 100;
    }

    /**
     * Пороги осмысленны, только если между ними есть зазор: при равных
     * значениях пограничной зоны нет вовсе, и дораунд никогда не случится.
     */
    public function thresholdsAreValid(): bool
    {
        return $this->threshold_reject < $this->threshold_accept
            && $this->threshold_reject >= 0
            && $this->threshold_accept <= 100;
    }

    public function decidesItself(): bool
    {
        return $this->decision_mode === 'auto';
    }

    /**
     * Готова ли вакансия принимать кандидатов через ИИ: мало включить
     * собеседование — настройки должны быть непротиворечивыми.
     */
    public function isUsable(): bool
    {
        return $this->enabled && $this->weightsAreValid() && $this->thresholdsAreValid();
    }

    public function levelLabel(): string
    {
        return __(self::LEVELS[$this->level] ?? $this->level);
    }
}
