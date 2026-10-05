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
        'questions_count' => self::DEFAULT_QUESTIONS,
        'weight_documents' => 40,
        'weight_interview' => 40,
        'weight_test' => 20,
        'threshold_reject' => 40,
        'threshold_accept' => 70,
        'test_time_limit_minutes' => 30,
        // Анна произносит это в конце разговора: «ответ вы получите в течение
        // 24 часов». Решение считается сразу, срок — с запасом на проверку
        'response_sla' => 'в течение 24 часов',
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
     * Сколько вопросов задаёт ИИ.
     *
     * Меньше шести не бывает: первый вопрос — знакомство, ещё один-два уходят на
     * уточнения расплывчатых ответов, и при пяти на требования вакансии
     * оставалось два-три вопроса — оценке не на чем было стоять.
     */
    public const MIN_QUESTIONS = 8;

    public const DEFAULT_QUESTIONS = 12;

    public const MAX_QUESTIONS = 20;

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
        // ответ кандидату в обоих режимах уходит только с разрешения работодателя
        'auto' => 'ИИ советуется с вами и напоминает, если кандидат ждёт ответа больше суток',
        'advisory' => 'ИИ советуется с вами, без напоминаний',
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
     * Наименьший зазор между порогами.
     *
     * Зазор — это пограничная зона, ради которой существует дораунд: балл в ней
     * означает «неясно», и ИИ задаёт ещё вопросов, прежде чем решать. Зазора в
     * один-два балла не бывает: в него никто не попадёт, уточнять система не
     * станет никогда, и решение сведётся к одной черте.
     *
     * Десять — не вкусовая величина. Итог считается как взвешенное среднее трёх
     * частей по шкале 0–100, и одна часть с весом 20 % меняет его шагами по два
     * балла; зона уже десяти не вмещает и одного такого шага.
     */
    public const MIN_THRESHOLD_GAP = 10;

    /**
     * Ниже какого порога приёма «прошёл» перестаёт что-либо значить.
     *
     * Не блокирует сохранение — это дело работодателя, — но на странице об этом
     * говорится вслух. Поводом стал живой случай: пороги 3 и 5 на шкале 0–100.
     * Формально это зазор, по смыслу — «пропускай всех»: кандидат, у которого
     * одна строка требований закрыта наполовину, уже набирает больше пяти.
     */
    public const WEAK_ACCEPT = 30;

    /**
     * Пороги осмысленны, только если между ними есть зазор: при равных
     * значениях пограничной зоны нет вовсе, и дораунд никогда не случится.
     */
    public function thresholdsAreValid(): bool
    {
        return $this->threshold_reject >= 0
            && $this->threshold_accept <= 100
            && $this->thresholdGap() >= self::MIN_THRESHOLD_GAP;
    }

    public function thresholdGap(): int
    {
        return (int) $this->threshold_accept - (int) $this->threshold_reject;
    }

    /**
     * Порог приёма так низок, что отбор почти никого не отсеет.
     */
    public function acceptIsWeak(): bool
    {
        return (int) $this->threshold_accept < self::WEAK_ACCEPT;
    }

    /**
     * Напоминать ли работодателю о кандидатах, ждущих ответа.
     *
     * Раньше режим «auto» означал, что ИИ через сутки отвечает кандидату сам.
     * Теперь ответ без разрешения работодателя не уходит ни в каком режиме.
     */
    public function remindsEmployer(): bool
    {
        return $this->decision_mode === 'auto';
    }

    /**
     * Нужно ли тестовое задание вообще.
     *
     * Нулевой вес значит, что задание в итог не входит. Раньше его всё равно
     * составляли и выдавали: кандидат полчаса решал то, что ни на что не
     * влияет, а составление и проверка тратили квоту модели.
     */
    public function needsTest(): bool
    {
        return (int) $this->weight_test > 0;
    }

    /**
     * Языки, на которых модель говорит с кандидатом, — в виде, пригодном для
     * промпта («Пиши на … языке»). Одно место на всех: собеседующего,
     * составителя задания и автора письма с решением.
     */
    public const LANGUAGE_NAMES = [
        'ru' => 'русском',
        'en' => 'английском',
        'tg' => 'таджикском',
    ];

    public function languageName(): string
    {
        return self::LANGUAGE_NAMES[$this->language] ?? self::LANGUAGE_NAMES['ru'];
    }

    /**
     * Язык кандидата как локаль приложения — для шаблонных текстов.
     */
    public function candidateLocale(): string
    {
        return array_key_exists((string) $this->language, self::LANGUAGE_NAMES)
            ? (string) $this->language
            : 'ru';
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
                'text' => __('Сумма весов документов, собеседования и задания равна 100'),
                'problem' => __('Веса документов, собеседования и задания должны в сумме давать 100 (сейчас :sum).', ['sum' => $sum]),
            ],
            [
                'key' => 'thresholds',
                'met' => $this->thresholdsAreValid(),
                'text' => __('Между порогами есть пограничная зона — не меньше :gap баллов', ['gap' => self::MIN_THRESHOLD_GAP]),
                'problem' => __('Порог приёма должен быть выше порога отказа не меньше чем на :gap баллов (сейчас разница :now), и оба — от 0 до 100.', [
                    'gap' => self::MIN_THRESHOLD_GAP,
                    'now' => $this->thresholdGap(),
                ]),
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
                'text' => __('Подтверждён хотя бы один обязательный критерий'),
                'problem' => __('Нужен хотя бы один подтверждённый обязательный критерий: иначе отказывать придётся только по баллу.'),
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
