<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Тестовое задание, составленное под конкретную вакансию и уровень.
 *
 * Эталонное решение и рубрика создаются заранее и наружу не уходят никогда:
 * иначе задание проверяло бы не умение, а внимательность к исходному коду
 * страницы. Наружу отдаёт только publicPayload().
 */
class AiTestTask extends Model
{
    protected $fillable = [
        'ai_interview_id',
        'kind',
        'statement',
        'format',
        'language',
        'reference_solution',
        'rubric',
        'quiz',
        'parts',
        'time_limit_minutes',
        'issued_at',
        'deadline_at',
    ];

    protected $casts = [
        'reference_solution' => 'encrypted',
        'rubric' => 'encrypted:array',
        // верные ответы теста кандидату не показываются никогда
        'quiz' => 'encrypted:array',
        'parts' => 'encrypted:array',
        'time_limit_minutes' => 'integer',
        'issued_at' => 'datetime',
        'deadline_at' => 'datetime',
    ];

    protected $attributes = [
        'kind' => 'main',
        'format' => 'case',
    ];

    public const KIND_MAIN = 'main';

    /** Сколько вопросов в тесте с вариантами ответа. */
    public const QUIZ_SIZE = 6;

    /** Сколько практических заданий. */
    public const PRACTICAL_COUNT = 2;

    /**
     * Доля теста в балле за тестовую часть. Остальное — практические задания:
     * умение сделать дело весит больше, чем знание, как его делают.
     */
    public const QUIZ_SHARE = 0.4;

    public const KIND_FOLLOW_UP = 'follow_up';

    /**
     * Формат определяет способ проверки: код можно исполнить и получить факт,
     * остальное оценивает модель по рубрике.
     */
    public const FORMATS = [
        'code' => 'Код',
        'case' => 'Разбор кейса',
        'writing' => 'Письменная задача',
        'calculation' => 'Расчёт',
    ];

    /**
     * Запас на отправку: решение, ушедшее за секунду до дедлайна, может дойти
     * уже после. Наказывать за скорость сети нечестно — та же логика, что в
     * проверке навыков (SkillAttempt::GRACE_SECONDS).
     */
    public const GRACE_SECONDS = 60;

    public function interview()
    {
        return $this->belongsTo(AiInterview::class, 'ai_interview_id');
    }

    public function submissions()
    {
        return $this->hasMany(AiTestSubmission::class, 'ai_test_task_id');
    }

    public function submission()
    {
        return $this->hasOne(AiTestSubmission::class, 'ai_test_task_id')->latestOfMany();
    }

    /**
     * То, что можно показать кандидату. Всё остальное — служебное.
     */
    public function publicPayload(): array
    {
        return [
            'statement' => $this->statement,
            'format' => $this->format,
            'language' => $this->language,
            'time_limit_minutes' => $this->time_limit_minutes,
            'deadline_at' => $this->deadline_at,
        ];
    }

    /**
     * Все практические задания по порядку: первое — в основных колонках,
     * остальные — в parts. Одинаковый вид, чтобы проверка, страница и отчёт
     * шли по ним одним циклом.
     *
     * @return array<int,array{statement:string,format:string,language:?string,reference_solution:?string,rubric:array}>
     */
    public function practical(): array
    {
        $first = [
            'statement' => (string) $this->statement,
            'format' => (string) $this->format,
            'language' => $this->language,
            'reference_solution' => $this->reference_solution,
            'rubric' => $this->rubric ?? [],
        ];

        $rest = collect($this->parts ?? [])
            ->filter(fn ($part) => is_array($part) && filled($part['statement'] ?? null))
            ->map(fn (array $part) => [
                'statement' => (string) $part['statement'],
                'format' => (string) ($part['format'] ?? 'case'),
                'language' => $part['language'] ?? null,
                'reference_solution' => $part['reference_solution'] ?? null,
                'rubric' => $part['rubric'] ?? [],
            ])
            ->values()
            ->all();

        return array_merge([$first], $rest);
    }

    /**
     * Вопросы теста без верных ответов — то, что можно показать кандидату.
     *
     * @return array<int,array{question:string,options:array<int,string>}>
     */
    public function quizForCandidate(): array
    {
        return collect($this->quiz ?? [])
            ->map(fn (array $q) => [
                'question' => (string) ($q['question'] ?? ''),
                'options' => array_values($q['options'] ?? []),
            ])
            ->all();
    }

    public function hasQuiz(): bool
    {
        return ! empty($this->quiz);
    }

    /**
     * Срок вышел — с учётом запаса на доставку.
     */
    public function expired(): bool
    {
        return $this->deadline_at !== null
            && now()->greaterThan($this->deadline_at->copy()->addSeconds(self::GRACE_SECONDS));
    }

    /**
     * Закрыть просроченное задание нулём и передать собеседование решению.
     *
     * Раньше это делала только отправка решения после срока. Кандидат, у
     * которого вышло время, видел «Время вышло» — и всё: отправлять было уже
     * нечего, а решение никто не запускал, и собеседование навсегда оставалось
     * на стадии задания. Теперь закрывают и показ страницы, и фоновая
     * уборка (ai:sweep) — для тех, кто не вернулся вовсе.
     *
     * Под замком строки: две перезагрузки страницы одновременно не должны
     * записать две нулевые попытки.
     *
     * @return bool закрыто ли сейчас
     */
    public function closeIfExpired(): bool
    {
        if (! $this->expired()) {
            return false;
        }

        $closed = \Illuminate\Support\Facades\DB::transaction(function () {
            $task = self::whereKey($this->id)->lockForUpdate()->first();

            if (! $task || $task->submissions()->exists()) {
                return false;
            }

            AiTestSubmission::create([
                'ai_test_task_id' => $task->id,
                'content' => null,
                'score' => 0,
                'review' => [],
                'submitted_at' => null,
                'graded_at' => now(),
            ]);

            $interview = $task->interview;

            if ($interview && ! $interview->isDecided()) {
                $interview->update([
                    'test_score' => 0,
                    ...($interview->stage === 'test' ? ['stage' => 'decision'] : []),
                ]);
            }

            return true;
        });

        if ($closed) {
            \App\Jobs\MakeHiringDecision::dispatch($this->ai_interview_id);
        }

        return $closed;
    }

    /**
     * Сколько секунд осталось — по этому числу идёт отсчёт на странице.
     */
    public function secondsLeft(): int
    {
        if ($this->deadline_at === null) {
            return 0;
        }

        // diffInSeconds отдаёт дробное, а метод обещает целое
        return (int) max(0, floor(now()->diffInSeconds($this->deadline_at, false)));
    }

    public function isCode(): bool
    {
        return $this->format === 'code';
    }

    public function formatLabel(): string
    {
        return __(self::FORMATS[$this->format] ?? $this->format);
    }
}
