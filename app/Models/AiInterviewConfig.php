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

    /**
     * Значения по умолчанию для новой вакансии.
     *
     * Живут здесь, а не только в колонках базы, и это не дублирование.
     * Настройки заводятся через firstOrCreate(): строка в базе получает
     * значения колонок, но объект в памяти остаётся пустым, и на первом заходе
     * работодатель видел нули во всех полях, сумму весов 0 и три красных
     * пункта — а после перезагрузки страницы всё «само чинилось». Атрибуты
     * модели подставляются сразу, поэтому форма открывается заполненной.
     *
     * Сами числа — рабочая настройка, с которой можно начинать: веса дают
     * сотню, пороги разведены, между ними есть пограничная зона. Остаётся
     * подтвердить обязательный критерий — единственное, что за работодателя
     * решить нельзя.
     */
    protected $attributes = [
        'enabled' => false,
        'level' => 'middle',
        'language' => 'ru',
        'questions_count' => 5,
        'weight_documents' => 40,
        'weight_interview' => 40,
        'weight_test' => 20,
        'threshold_reject' => 40,
        'threshold_accept' => 70,
        'test_time_limit_minutes' => 30,
        'response_sla' => 'в течение 3 дней',
        'decision_mode' => 'auto',
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
        return $this->enabled && $this->problems() === [];
    }

    /**
     * Что мешает запустить собеседование — человеческим языком.
     *
     * Список в одном месте, потому что его спрашивают трое: страница настроек
     * рисует предупреждения, сохранение не даёт включить собеседование вслепую,
     * а отклик кандидата решает, вести его к ИИ или к обычному ожиданию. Если бы
     * условия жили в трёх местах, они разошлись бы на первой же правке.
     *
     * @return array<int,string>
     */
    public function problems(): array
    {
        return collect($this->checklist())
            ->reject(fn (array $item) => $item['met'])
            ->map(fn (array $item) => $item['problem'])
            ->values()
            ->all();
    }

    /**
     * Те же условия, но все три и со статусом.
     *
     * Список один на два применения: страница рисует по нему чеклист, а
     * problems() отбирает из него невыполненное. Держать два списка значило бы
     * однажды получить страницу, которая показывает зелёную галочку там, где
     * сохранение отказывает.
     *
     * text — само условие, его видно всегда. problem — что не так, он идёт в
     * сообщение при попытке включить собеседование. Разные формулировки нужны
     * потому, что «веса дают 100» и «веса должны давать 100, сейчас 90» — это
     * не одна фраза с разным знаком.
     *
     * @return array<int,array{key:string,met:bool,text:string,problem:string}>
     */
    public function checklist(): array
    {
        $sum = $this->weightsSum();

        return [
            [
                'key' => 'weights',
                'met' => $this->weightsAreValid(),
                'text' => 'Сумма весов документов, собеседования и задания равна 100',
                'problem' => 'Веса документов, собеседования и задания должны в сумме давать 100 '
                    .'(сейчас '.$sum.').',
            ],
            [
                'key' => 'thresholds',
                'met' => $this->thresholdsAreValid(),
                'text' => 'Порог приёма выше порога отказа',
                'problem' => 'Порог приёма должен быть выше порога отказа, и оба — от 0 до 100.',
            ],
            [
                /*
                 * Хотя бы одно подтверждённое обязательное требование.
                 *
                 * Без обязательных критериев ворота решения не работают вовсе:
                 * отказать можно только по баллу, и вакансия «нужен электрик»
                 * пропускала бы повара с красивыми ответами.
                 */
                'key' => 'criteria',
                'met' => $this->confirmedMustCount() > 0,
                'text' => 'Подтверждён хотя бы один обязательный критерий',
                'problem' => 'Нужен хотя бы один подтверждённый обязательный критерий: '
                    .'иначе отказывать придётся только по баллу.',
            ],
        ];
    }

    /**
     * Сколько обязательных критериев работодатель подтвердил.
     */
    public function confirmedMustCount(): int
    {
        if (! $this->vacancy_id) {
            return 0;
        }

        return AiInterviewCriterion::where('vacancy_id', $this->vacancy_id)
            ->confirmed()->must()->count();
    }

    public function levelLabel(): string
    {
        return __(self::LEVELS[$this->level] ?? $this->level);
    }
}
