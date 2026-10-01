<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Собеседование кандидата с ИИ — одно на пару «вакансия + соискатель».
 *
 * Запись ведёт кандидата по стадиям и хранит текущие баллы. Снимок на момент
 * решения лежит отдельно, в AiDecision: правка настроек вакансии не должна
 * задним числом менять уже объявленный человеку итог.
 */
class AiInterview extends Model
{
    use HasFactory;

    protected $fillable = [
        'vacancy_id',
        'applicant_id',
        'vacancy_response_id',
        'stage',
        'consent_at',
        'consent_ip',
        'analysis',
        'analysis_status',
        'analysed_at',
        'documents_score',
        'interview_score',
        'test_score',
        'total_score',
        'outcome',
        'requires_review',
        'follow_up_round',
        'decided_at',
    ];

    protected $casts = [
        'consent_at' => 'datetime',
        'decided_at' => 'datetime',
        // адрес — персональные данные, наружу не отдаётся и в базе шифруется
        'consent_ip' => 'encrypted',
        // разбор содержит цитаты из резюме и документов — шифруется
        'analysis' => 'encrypted:array',
        'analysed_at' => 'datetime',
        'documents_score' => 'integer',
        'interview_score' => 'integer',
        'test_score' => 'integer',
        'total_score' => 'integer',
        'requires_review' => 'boolean',
        'follow_up_round' => 'integer',
    ];

    protected $attributes = [
        'stage' => 'consent',
    ];

    /**
     * Стадии по порядку — по нему же считается прогресс для полосы в интерфейсе.
     */
    public const STAGES = [
        'consent' => 'Согласие',
        'documents' => 'Документы',
        'interview' => 'Собеседование',
        'test' => 'Тестовое задание',
        'decision' => 'Решение',
        'done' => 'Завершено',
    ];

    /**
     * manual_review — не «третий исход», а честное «система не берётся
     * решать»: кандидату в этом случае не уходит ни отказ, ни приглашение,
     * только обещанный срок ответа.
     */
    public const OUTCOMES = [
        'passed' => 'Прошёл отбор',
        'rejected' => 'Отказ',
        'manual_review' => 'Нужна ручная проверка',
    ];

    /**
     * Сколько пограничных дораундов допускаем. Один: если и после
     * дополнительных вопросов решение не очевидно, дальше уточнять бессмысленно —
     * это работа для человека.
     */
    public const MAX_FOLLOW_UP_ROUNDS = 1;

    public function vacancy()
    {
        return $this->belongsTo(Vacancy::class);
    }

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    /**
     * Отклик, из которого выросло собеседование. Может быть отозван
     * кандидатом — тогда null, а собеседование и его аудит остаются.
     */
    public function response()
    {
        return $this->belongsTo(VacancyResponse::class, 'vacancy_response_id');
    }

    public function documents()
    {
        return $this->hasMany(AiCandidateDocument::class);
    }

    public function turns()
    {
        return $this->hasMany(AiInterviewTurn::class)->orderBy('position');
    }

    public function requirementChecks()
    {
        return $this->hasMany(AiRequirementCheck::class);
    }

    public function testTasks()
    {
        return $this->hasMany(AiTestTask::class);
    }

    public function decisions()
    {
        return $this->hasMany(AiDecision::class)->orderBy('round');
    }

    public function interactions()
    {
        return $this->hasMany(AiInteraction::class);
    }

    public function injectionAttempts()
    {
        return $this->hasMany(AiInjectionAttempt::class);
    }

    /**
     * Настройки вакансии. Через вакансию, а не напрямую: собеседование
     * принадлежит паре «вакансия + кандидат», а настройки — только вакансии.
     */
    public function config()
    {
        return $this->hasOne(AiInterviewConfig::class, 'vacancy_id', 'vacancy_id');
    }

    /**
     * Последнее по счёту решение — оно и есть действующее.
     */
    public function latestDecision()
    {
        return $this->hasOne(AiDecision::class)->latestOfMany('round');
    }

    public function consented(): bool
    {
        return $this->consent_at !== null;
    }

    public function isDecided(): bool
    {
        return $this->outcome !== null;
    }

    /**
     * Насколько кандидат продвинулся — для полосы прогресса.
     * Считается так же, как в помощнике по резюме (ResumeDraft::progress).
     */
    public function progress(): int
    {
        $stages = array_keys(self::STAGES);
        $position = array_search($this->stage, $stages, true);

        return $position === false ? 0 : (int) round($position / (count($stages) - 1) * 100);
    }

    public function stageLabel(): string
    {
        return __(self::STAGES[$this->stage] ?? $this->stage);
    }

    public function outcomeLabel(): ?string
    {
        return $this->outcome ? __(self::OUTCOMES[$this->outcome] ?? $this->outcome) : null;
    }

    /**
     * Обязательные требования, которые так и остались незакрытыми.
     * Их наличие — ворота отказа, поэтому список нужен и решению, и отчёту.
     */
    public function unmetMustHaves()
    {
        return $this->requirementChecks
            ->where('kind', 'must')
            ->filter(fn (AiRequirementCheck $check) => ! $check->isSatisfied())
            ->values();
    }

    /**
     * Ещё можно уточнять или дораунды исчерпаны.
     */
    public function canAskFollowUp(): bool
    {
        return $this->follow_up_round < self::MAX_FOLLOW_UP_ROUNDS;
    }

    /**
     * Сколько вопросов должно прозвучать всего.
     *
     * Пограничный дораунд добавляет несколько сверх заказанного работодателем:
     * иначе разговор заканчивался бы ровно там же, где и в прошлый раз, и
     * уточнять было бы негде.
     */
    public function questionTarget(): int
    {
        $base = (int) ($this->config->questions_count ?? 8);

        return $base + $this->follow_up_round * \App\Jobs\MakeHiringDecision::FOLLOW_UP_QUESTIONS;
    }

    /**
     * Сколько ждём разбор, прежде чем признать, что он не идёт.
     *
     * Разбор занимает около минуты, и три минуты — это уже не «медленно», а
     * «не выполняется»: задачи стоят в очереди, а обработчика нет. Самая частая
     * причина — не запущен `php artisan queue:work`.
     */
    public const ANALYSIS_PATIENCE_MINUTES = 3;

    /**
     * Разбор помечен идущим, но слишком давно.
     *
     * Без этого страница кандидата перезагружалась каждые пять секунд до конца
     * времён и повторяла «занимает до минуты». Человеку надо сказать правду:
     * что-то не двигается, и вот что можно сделать.
     */
    public function analysisStalled(): bool
    {
        return $this->analysis_status === 'pending'
            && $this->updated_at !== null
            && $this->updated_at->lt(now()->subMinutes(self::ANALYSIS_PATIENCE_MINUTES));
    }

    /**
     * Стереть собеседование вместе с файлами документов.
     *
     * Живёт на модели, а не в контроллере, потому что удаляют втроём: кандидат
     * из своего кабинета, работодатель из отчёта и кандидат же, когда
     * отказывается от собеседования. Пока это лежало в контроллере отчёта,
     * отказ удалял строки, но не файлы, и дипломы оставались на диске без
     * единой ссылки на них — хуже, чем не удалять вовсе: человек считает, что
     * его документов у нас нет.
     *
     * Каскад базы уносит стенограмму, оценки, задания, решения и аудит
     * обращений к модели: в промптах лежат ответы человека и содержимое его
     * документов, и «сохраним аудит, удалим остальное» было бы обещанием,
     * которого мы не выполняем. Озвучки не трогаем — они лежат в общей
     * фонотеке по отпечатку текста и могут быть заняты другим собеседованием.
     */
    public function eraseCompletely(): void
    {
        foreach ($this->documents as $document) {
            \Illuminate\Support\Facades\Storage::disk(
                \App\Services\Documents\TextExtractor::DISK
            )->delete($document->path);
        }

        $this->delete();
    }
}