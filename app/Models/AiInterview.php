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
        'analysis_started_at',
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
        'analysis_started_at' => 'datetime',
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
     * Ответ отправлен кандидату — только тогда он видит итог.
     */
    public function isAnnounced(): bool
    {
        return (bool) $this->latestDecision?->isReleased();
    }

    /**
     * Кандидат прошёл всё, что от него зависело.
     *
     * Дальше работа не его: ИИ проверяет решения, готовит рекомендацию,
     * работодатель решает. Кандидату на этом этапе делать нечего — только
     * ждать ответа.
     */
    public function candidateFinished(): bool
    {
        if ($this->isAnnounced() || $this->latestDecision || in_array($this->stage, ['decision', 'done'], true)) {
            return true;
        }

        if ($this->stage !== 'test') {
            return false;
        }

        // решения отправлены — или время вышло, и попытка закрыта
        $task = $this->testTasks()->where('kind', AiTestTask::KIND_MAIN)->latest('id')->first();

        return (bool) $task?->submission()->exists();
    }

    /**
     * Сколько шагов кандидат прошёл — в процентах.
     *
     * Считаются только его шаги: согласие, документы, разговор (растёт с
     * каждым ответом), тестовая часть. Проверка и решение — работа ИИ и
     * работодателя, и в полосе кандидата их нет: раньше человек, прошедший
     * всё, видел 85% и думал, что что-то не доделал.
     *
     *   согласие 10 · документы 10 · разговор 50 · тестовая часть 30.
     *
     * Ровно половина ответов в разговоре — половина его доли.
     */
    public function progress(): int
    {
        if ($this->candidateFinished()) {
            return 100;
        }

        $percent = 0;

        if ($this->consented()) {
            $percent += 10;
        }

        $afterDocuments = ! in_array($this->stage, ['consent', 'documents'], true);

        if ($afterDocuments || in_array($this->analysis_status, ['pending', 'ready', 'failed'], true)) {
            $percent += 10;
        }

        $noTest = $this->config && ! $this->config->needsTest();
        $conversationShare = $noTest ? 80 : 50;

        if ($this->stage === 'test') {
            $percent += $conversationShare;
        } elseif ($this->stage === 'interview') {
            $answers = $this->turns()->where('role', AiInterviewTurn::ROLE_CANDIDATE)->count();
            $percent += (int) floor(min(1, $answers / max(1, $this->questionTarget())) * $conversationShare);
        }

        return min(99, $percent);
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
     * Что Анна говорит, заканчивая разговор.
     *
     * Как на живом собеседовании: поблагодарить, сказать, что дальше, и когда
     * ждать ответа. Текст собирает код, а не модель: обещание о сроке ответа
     * и о следующем шаге должно быть точным, а не таким, как модели
     * вздумается сформулировать. Язык — язык собеседования.
     *
     * $toTest — впереди тестовое задание. Иначе (задания нет или оно уже
     * сдано, а это был дораунд) — сразу ожидание решения.
     */
    public function closingRemark(bool $toTest): string
    {
        $locale = $this->config?->candidateLocale() ?? 'ru';
        // стандартный срок есть в словаре и прозвучит на языке собеседования;
        // свой срок работодателя остаётся таким, как он его написал
        $sla = __($this->config?->response_sla ?: 'в течение 24 часов', [], $locale);

        if ($toTest) {
            $minutes = (int) ($this->config?->test_time_limit_minutes ?? 30);

            return __('Спасибо, это был содержательный разговор — у меня больше нет вопросов. Переходим к практической части: сейчас я дам вам тестовое задание по вашей специальности. На него будет :minutes минут, а время пойдёт с момента, когда задание откроется у вас на экране. Решайте спокойно и так, как сделали бы на работе. По результатам задания и нашей беседы я подготовлю ответ — вы получите его :sla. Удачи!', [
                'minutes' => $minutes,
                'sla' => $sla,
            ], $locale);
        }

        if ($this->follow_up_round > 0) {
            return __('Спасибо за уточнения, теперь картина полная — вопросов у меня больше нет. Я учту ваши ответы вместе с тестовым заданием и всей нашей беседой и подготовлю решение. Ответ вы получите :sla.', [
                'sla' => $sla,
            ], $locale);
        }

        return __('Спасибо, это был содержательный разговор — у меня больше нет вопросов. По итогам нашей беседы и ваших документов я подготовлю ответ — вы получите его :sla. Хорошего дня!', [
            'sla' => $sla,
        ], $locale);
    }

    /**
     * Заключительные слова после тестовой части — собеседование закончено.
     *
     * Звучат, когда кандидат отправил решения: беседа и задания позади,
     * остаётся ждать ответа. Как и прочие обещания кандидату, текст собирает
     * код: срок ответа — тот, что задал работодатель.
     */
    public function farewellRemark(): string
    {
        $locale = $this->config?->candidateLocale() ?? 'ru';
        $sla = __($this->config?->response_sla ?: 'в течение 24 часов', [], $locale);

        return __('Спасибо, ваши решения приняты — на этом наше собеседование закончено. Я проверю тест и задания и вместе с нашей беседой передам на рассмотрение. Ответ мы пришлём вам :sla: он придёт в уведомления и появится в разделе «Мои ИИ-собеседования». Спасибо за ваше время и удачи!', [
            'sla' => $sla,
        ], $locale);
    }

    /**
     * Набор документов закрыт для правки.
     *
     * Разбор и сверка требований делаются по тому набору, что был на момент
     * «Продолжить». Раньше страница документов оставалась доступной по
     * адресу и после разбора, и после решения: добавленный файл в разбор уже
     * не попадал и висел у работодателя «неразобранным», а удалённый выбивал
     * опору из готового отчёта — вывод ИИ ссылался на диплом, которого нет.
     * Пока разбор идёт, правка тоже бессмысленна; зависший разбор — другое
     * дело: его перезапускают, и новый файл попадёт в цепочку.
     */
    public function documentsLocked(): bool
    {
        if ($this->analysis_status === 'ready' || $this->isDecided()) {
            return true;
        }

        return $this->analysis_status === 'pending' && ! $this->analysisStalled();
    }

    /**
     * Работодатель может вынести решение.
     *
     * Когда решение уже есть (его можно изменить) или когда система сама
     * передала кандидата человеку. Пока собеседование идёт своим ходом,
     * вмешиваться нельзя: кандидат посреди разговора получил бы итог.
     */
    public function awaitsEmployer(): bool
    {
        return $this->latestDecision !== null
            || $this->requires_review
            || $this->outcome !== null;
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
        $base = (int) ($this->config->questions_count ?? AiInterviewConfig::DEFAULT_QUESTIONS);

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
        // от момента запуска, а не от последней правки строки: пока идёт
        // разговор, оценки ответов постоянно обновляют updated_at
        $since = $this->analysis_started_at ?? $this->updated_at;

        return $this->analysis_status === 'pending'
            && $since !== null
            && $since->lt(now()->subMinutes(self::ANALYSIS_PATIENCE_MINUTES));
    }

    /**
     * Разбор документов ещё идёт своим ходом.
     */
    public function analysisRunning(): bool
    {
        return $this->analysis_status === 'pending' && ! $this->analysisStalled();
    }

    /**
     * Кто перед нами — для вопросов и задания по специальности кандидата.
     *
     * Разговор начинается сразу после документов, не дожидаясь разбора. Пока
     * разбор не готов, берём поля резюме как есть: профессию, желаемую
     * должность, опыт, навыки, место работы. Когда готов — его структурированный
     * вид, он точнее. Персональные данные вычищаются в обоих случаях.
     *
     * @return array<string,string>
     */
    public function candidateProfile(): array
    {
        $parsed = $this->analysis['resume'] ?? null;

        if (is_array($parsed) && filled($parsed['profession'] ?? null)) {
            $facts = [
                'Профессия' => $parsed['profession'] ?? null,
                'Лет опыта' => $parsed['experience_years'] ?? null,
                'Навыки' => collect($parsed['skills'] ?? [])->take(20)->implode(', '),
                'Последняя роль' => collect($parsed['positions'] ?? [])->first()['role'] ?? null,
            ];
        } else {
            $resume = $this->applicant?->resume()->latest('id')->first();

            if (! $resume) {
                return [];
            }

            $facts = [
                'Профессия' => $resume->profession,
                'Желаемая должность' => $resume->desired_position,
                'Лет опыта' => $resume->experience_years,
                'Навыки' => \Illuminate\Support\Str::limit((string) $resume->skills, 300, ''),
                'Последнее место работы' => $resume->place_work,
            ];
        }

        $redactor = app(\App\Services\Privacy\PiiRedactor::class);

        return collect($facts)
            ->map(fn ($value) => is_string($value) ? trim($redactor->clean($value)) : $value)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => (string) $value)
            ->all();
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
        $disk = \Illuminate\Support\Facades\Storage::disk(\App\Services\Documents\TextExtractor::DISK);

        foreach ($this->documents as $document) {
            $disk->delete($document->path);
        }

        // и сама папка собеседования: пустые каталоги по номерам удалённых
        // собеседований — тоже след того, чего у нас быть не должно
        $disk->deleteDirectory('ai/documents/'.$this->id);

        $this->delete();
    }

    /**
     * Стереть все собеседования, попадающие под условие, вместе с файлами.
     *
     * Нужна там, где собеседования уходят не по одному, а каскадом базы:
     * удаление аккаунта или вакансии. Каскад уносит строки, но файлы дипломов
     * оставались на диске без единой ссылки — ровно то, от чего защищает
     * eraseCompletely(). Поэтому перед таким удалением проходим по ним сами.
     */
    public static function eraseWhere(callable $scope): void
    {
        $query = static::query();
        $scope($query);

        $query->with('documents')->each(fn (AiInterview $interview) => $interview->eraseCompletely());
    }
}