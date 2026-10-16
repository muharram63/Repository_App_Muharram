<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiInjectionAttempt;
use App\Models\AiInteraction;
use App\Models\AiInterview;
use App\Models\AiTestTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Состояние модуля ИИ-собеседования для администратора.
 *
 * Раздел служебный: работает ли модуль, не застряли ли кандидаты, сколько
 * уходит квоты модели и где срабатывала защита. Ответы, документы и оценки
 * кандидата здесь не показываются — согласие обещает, что они доступны только
 * работодателю вакансии. Отсюда же видно, что не запущен обработчик очереди:
 * самая частая причина «ИИ не отвечает».
 */
class AdminAiController extends Controller
{
    /** За сколько дней считаем расход модели. */
    public const USAGE_DAYS = 30;

    /** Дольше этого ни один запрос к модели не идёт (таймауты меньше), мс. */
    public const LATENCY_CAP = 300000;

    /** Сколько часов без движения считаем собеседование брошенным. */
    public const IDLE_HOURS = 24;

    public function index()
    {
        // учебные собеседования тренажёра — не отбор, здесь их нет
        $interviews = AiInterview::withoutTraining()->with('vacancy.employer', 'applicant.user', 'testTasks.submissions')
            ->latest('updated_at')
            ->limit(100)
            ->get();

        $since = now()->subDays(self::USAGE_DAYS);
        $usage = AiInteraction::where('created_at', '>=', $since);

        return view('admin.pages.ai.index', [
            'counts' => [
                'all' => AiInterview::withoutTraining()->count(),
                'running' => AiInterview::withoutTraining()->whereNull('outcome')->count(),
                'passed' => AiInterview::withoutTraining()->where('outcome', 'passed')->count(),
                'rejected' => AiInterview::withoutTraining()->where('outcome', 'rejected')->count(),
                'manual' => AiInterview::withoutTraining()->where('requires_review', true)->count(),
            ],
            'stuck' => $interviews->filter(fn (AiInterview $i) => $this->problem($i) !== null)->count(),
            'interviews' => $interviews,
            'problem' => fn (AiInterview $i) => $this->problem($i),
            'idle' => fn (AiInterview $i) => $this->idle($i),
            'queue' => $this->queue(),
            'usage' => [
                'calls' => (clone $usage)->count(),
                'tokens' => (int) (clone $usage)->sum(DB::raw('coalesce(tokens_in, 0) + coalesce(tokens_out, 0)')),
                'failed' => (clone $usage)->where('status', '!=', 'ok')->count(),
                // без записей дольше LATENCY_CAP: это компьютер уснул посреди
                // запроса, а не модель думала часами — одна такая запись
                // превращала среднее в «~123 с»
                'latency' => (int) round((float) (clone $usage)->where('latency_ms', '<=', self::LATENCY_CAP)->avg('latency_ms')),
            ],
            'byPurpose' => (clone $usage)
                ->selectRaw('purpose, count(*) as calls, sum(case when status = ? then 0 else 1 end) as failed', ['ok'])
                ->groupBy('purpose')->orderByDesc('calls')->get(),
            'byModel' => (clone $usage)
                ->selectRaw('model, count(*) as calls, avg(case when latency_ms <= ? then latency_ms end) as latency', [self::LATENCY_CAP])
                ->groupBy('model')->orderByDesc('calls')->get(),
            'purposes' => AiInteraction::PURPOSES,
            'injections' => AiInjectionAttempt::with('interview.vacancy')
                ->latest()->limit(20)->get(),
            'days' => self::USAGE_DAYS,
        ]);
    }

    /**
     * Что не так с собеседованием, если что-то не так.
     */
    private function problem(AiInterview $interview): ?string
    {
        if ($interview->isDecided()) {
            return null;
        }

        if ($interview->analysisStalled()) {
            return 'Разбор документов не движется — проверьте обработчик очереди';
        }

        $task = $interview->testTasks->firstWhere('kind', AiTestTask::KIND_MAIN);

        if ($task && $task->expired() && $task->submissions->isEmpty()) {
            return 'Срок задания вышел, решения нет — его закроет ai:sweep';
        }

        if ($interview->stage === 'decision' && $interview->updated_at?->lt(now()->subMinutes(10))) {
            return 'Решение не принято больше 10 минут';
        }

        return null;
    }

    /**
     * Кандидат ушёл и не вернулся. Это не сбой — исправлять тут нечего,
     * поэтому в «Требуют внимания» не считается, но и «В порядке» не пишем.
     */
    private function idle(AiInterview $interview): ?string
    {
        if ($interview->isDecided() || ! $interview->updated_at?->lt(now()->subHours(self::IDLE_HOURS))) {
            return null;
        }

        return 'Нет движения '.$interview->updated_at->diffForHumans(now(), true);
    }

    /**
     * Очередь: сколько задач ждёт и сколько упало.
     */
    private function queue(): array
    {
        $pending = Schema::hasTable('jobs') ? DB::table('jobs')->count() : null;
        $oldest = Schema::hasTable('jobs') ? DB::table('jobs')->min('created_at') : null;

        return [
            'driver' => config('queue.default'),
            'pending' => $pending,
            // задача ждёт дольше пяти минут — обработчик, скорее всего, не запущен
            'stale' => $oldest !== null && $oldest < now()->subMinutes(5)->getTimestamp(),
            'failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null,
        ];
    }
}
