<?php

namespace App\Services\Ai;

use App\Jobs\MakeHiringDecision;
use App\Models\AiInterview;
use App\Models\AiInterviewCriterion;
use App\Models\AiTestTask;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Подготовка тестового задания — заранее, пока кандидат ещё разговаривает.
 *
 * Раньше задание начинали составлять, только когда кандидат открывал его
 * страницу, и только через очередь. Составление — самый долгий вызов модуля,
 * а без обработчика очереди оно не начиналось вовсе: человек смотрел на
 * «задание готовится» минутами. Теперь страница разговора просит составить
 * задание в фоне после первого ответа, и к концу беседы оно уже лежит готовым.
 *
 * Зовут трое — очередь, фоновый запрос из чата и страница задания, — и
 * замок не даёт им составить два задания или заплатить за одно дважды.
 *
 * Отсчёт времени здесь не начинается: он идёт с первого показа задания.
 */
class TaskPreparer
{
    public function __construct(private readonly TaskExaminer $examiner)
    {
    }

    /**
     * @return string ready — задание есть; busy — его уже составляют;
     *                skipped — задание не нужно; failed — составить не вышло
     *
     * @throws AiUnavailableException модель занята — можно повторить позже
     */
    public function prepare(AiInterview $interview): string
    {
        if ($this->mainTask($interview)) {
            return 'ready';
        }

        if ($interview->config && ! $interview->config->needsTest()) {
            return 'skipped';
        }

        if ($interview->isDecided()) {
            return 'skipped';
        }

        $lock = Cache::lock('compose-task-'.$interview->id, 300);

        if (! $lock->get()) {
            return 'busy';
        }

        try {
            // пока ждали замок, задание мог составить другой
            if ($this->mainTask($interview)) {
                return 'ready';
            }

            $criteria = AiInterviewCriterion::where('vacancy_id', $interview->vacancy_id)
                ->confirmed()->ordered()->get();

            try {
                $task = $this->examiner->compose($interview, $criteria);
            } catch (AiInvalidAnswerException $e) {
                $this->giveUp($interview, 'Не удалось составить задание: '.$e->summary());

                return 'failed';
            }

            AiTestTask::create([
                'ai_interview_id' => $interview->id,
                'kind' => AiTestTask::KIND_MAIN,
                'statement' => $task['statement'],
                'format' => $task['format'],
                'language' => $task['language'],
                'reference_solution' => $task['reference_solution'],
                'rubric' => $task['rubric'],
                // тест с вариантами и остальные практические задания
                'quiz' => $task['quiz'] ?? null,
                'parts' => $task['parts'] ?? null,
                'time_limit_minutes' => $task['minutes'],
                // issued_at и deadline_at ставит первый показ задания кандидату
            ]);

            return 'ready';
        } finally {
            $lock->release();
        }
    }

    /**
     * Задание составить не удалось.
     *
     * Выдумывать его нельзя: решение без задания — уже не то, о чём
     * договаривались с работодателем, поэтому зовём человека. Если кандидат
     * ещё разговаривает, решение не запускаем — оно случится, когда беседа
     * закончится (AiChatController::afterConversation). Иначе итог пришёл бы
     * посреди разговора.
     */
    public function giveUp(AiInterview $interview, string $reason): void
    {
        $waiting = in_array($interview->stage, ['test', 'decision'], true);

        $interview->update([
            'requires_review' => true,
            ...($interview->stage === 'test' ? ['stage' => 'decision'] : []),
        ]);

        Log::warning('Тестовое задание не составлено', [
            'interview' => $interview->id,
            'reason' => $reason,
        ]);

        if ($waiting) {
            MakeHiringDecision::dispatch($interview->id);
        }
    }

    private function mainTask(AiInterview $interview): ?AiTestTask
    {
        return $interview->testTasks()->where('kind', AiTestTask::KIND_MAIN)->latest('id')->first();
    }
}
