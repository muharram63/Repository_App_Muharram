<?php

namespace App\Services\Ai;

use App\Jobs\AnalyzeCandidateDocument;
use App\Jobs\BuildRequirementMatrix;
use App\Jobs\MakeHiringDecision;
use App\Jobs\ScoreInterviewAnswer;
use App\Models\AiInterview;
use App\Models\AiInterviewTurn;
use App\Models\AiTestSubmission;
use App\Models\AiTestTask;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Двигатель собеседования: делает следующий шаг, если очередь его не делает.
 *
 * Весь модуль устроен через очередь: разбор документов, оценка ответов,
 * проверка задания, решение. Без запущенного обработчика ничего из этого не
 * происходило: кандидат отправлял решение и смотрел на «ИИ проверяет — до
 * минуты» бесконечно, а решение не приходило никогда.
 *
 * Теперь страницы кандидата сами просят сделать следующий шаг. Один вызов —
 * один шаг (одно обращение к модели), чтобы запрос не упирался в лимит
 * времени. Если обработчик очереди работает, двигатель ему не мешает: свежие
 * задачи в очереди он оставляет ей, а берётся только за то, что застряло.
 * Все шаги и так безопасны к повтору — проверяют, не сделано ли уже.
 */
class InterviewPump
{
    /** Задача в очереди дольше этого — обработчика, похоже, нет. */
    private const QUEUE_PATIENCE_SECONDS = 20;

    public function __construct(private readonly TestGrader $grader)
    {
    }

    /**
     * @return array{did:?string,stage:string,decided:bool,graded:bool}
     */
    public function step(AiInterview $interview): array
    {
        $did = null;

        $lock = Cache::lock('interview-pump-'.$interview->id, 200);

        // ответ, который ждал работодателя дольше обещанного, — без модели
        // и без очереди, поэтому вне зависимости от её состояния
        app(\App\Services\Scoring\DecisionRelease::class)->remindOverdue($interview->id);

        if ($lock->get()) {
            try {
                if ($this->queueStalled()) {
                    $did = $this->next($interview);
                }
            } catch (AiUnavailableException $e) {
                // модель занята — шаг повторим со следующим вызовом
                $did = 'busy';
            } catch (\Throwable $e) {
                Log::warning('Шаг собеседования не удался', [
                    'interview' => $interview->id,
                    'error' => $e->getMessage(),
                ]);
                $did = 'error';
            } finally {
                $lock->release();
            }
        } else {
            $did = 'busy';
        }

        $interview->refresh();

        return [
            'did' => $did,
            'stage' => (string) $interview->stage,
            // кандидату — только отправленный ответ, а не рекомендация ИИ
            'decided' => $interview->isAnnounced(),
            'graded' => $this->submission($interview)?->isGraded() ?? false,
        ];
    }

    /**
     * Очередь не разбирается: задачи лежат дольше обычного или очередь пуста,
     * а работа не сделана. Свежие задачи оставляем обработчику — если он есть.
     */
    private function queueStalled(): bool
    {
        if (config('queue.default') !== 'database' || ! Schema::hasTable('jobs')) {
            // sync выполнил всё сразу; у прочих драйверов свой обработчик
            return config('queue.default') === 'sync';
        }

        $oldest = DB::table('jobs')->whereNull('reserved_at')->min('available_at');

        return $oldest === null || $oldest < now()->subSeconds(self::QUEUE_PATIENCE_SECONDS)->getTimestamp();
    }

    /**
     * Следующий шаг по порядку: документы, оценки ответов, проверка
     * задания, решение. Порядок важен: решение ждёт всего остального.
     */
    private function next(AiInterview $interview): ?string
    {
        // 1. разбор документов
        if ($interview->analysis_status === 'pending') {
            $document = $interview->documents()->where('status', 'pending')->orderBy('id')->first();

            if ($document) {
                app()->call([new AnalyzeCandidateDocument($document->id), 'handle']);

                return 'document';
            }

            app()->call([new BuildRequirementMatrix($interview->id), 'handle']);

            return 'analysis';
        }

        // 2. оценки ответов (при ручной проверке ждать их незачем)
        if (! $interview->requires_review) {
            $unscored = $interview->turns()
                ->where('role', AiInterviewTurn::ROLE_CANDIDATE)
                ->whereNull('score')
                ->orderBy('position')
                ->first();

            if ($unscored) {
                app()->call([new ScoreInterviewAnswer($unscored->id), 'handle']);

                return 'score';
            }
        }

        // 3. проверка тестовой части
        $submission = $this->submission($interview);

        if ($submission && $submission->submitted_at && ! $submission->isGraded()) {
            try {
                $this->grader->step($submission);
            } catch (AiUnavailableException $e) {
                throw $e;
            }

            return 'grade';
        }

        // 4. решение
        if ($interview->stage === 'decision' && ! $interview->isDecided()) {
            app()->call([new MakeHiringDecision($interview->id), 'handle']);

            return 'decision';
        }

        return null;
    }

    private function submission(AiInterview $interview): ?AiTestSubmission
    {
        $task = $interview->testTasks()->where('kind', AiTestTask::KIND_MAIN)->latest('id')->first();

        return $task?->submission()->first();
    }
}
