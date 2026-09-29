<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Jobs\ComposeTestTask;
use App\Jobs\GradeTestSubmission;
use App\Models\AiInterview;
use App\Models\AiTestSubmission;
use App\Models\AiTestTask;
use App\Services\Ai\GeminiTaskExaminer;
use App\Services\Security\InjectionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Тестовое задание со стороны кандидата.
 *
 * Отсчёт времени начинается не когда задание составлено, а когда кандидат его
 * впервые открыл. Иначе минуты утекали бы, пока он отходит от компьютера после
 * собеседования, и человек получал бы задание с уже истёкшим сроком.
 */
class AiTaskController extends Controller
{
    public function __construct(private readonly InjectionGuard $guard)
    {
    }

    /**
     * Страница задания.
     */
    public function show(AiInterview $interview)
    {
        $this->mine($interview);

        if ($redirect = $this->guardStage($interview)) {
            return $redirect;
        }

        $task = $interview->testTasks()->where('kind', AiTestTask::KIND_MAIN)->latest('id')->first();

        // задания ещё нет — ставим составление в очередь и показываем ожидание
        if (! $task) {
            $this->requestTask($interview);
        }

        // отсчёт пошёл с первого показа
        if ($task && ! $task->issued_at) {
            $task->update([
                'issued_at' => now(),
                'deadline_at' => now()->addMinutes($task->time_limit_minutes),
            ]);
            $task->refresh();
        }

        return view('applicant.pages.ai-interview.task', [
            'user' => auth()->user(),
            'applicant' => $interview->applicant,
            'interview' => $interview,
            'vacancy' => $interview->vacancy,
            'task' => $task,
            'submission' => $task?->submission()->first(),
            'maxSolution' => GeminiTaskExaminer::MAX_SOLUTION,
        ]);
    }

    /**
     * Отправка решения.
     */
    public function submit(Request $request, AiInterview $interview)
    {
        $this->mine($interview);

        $task = $interview->testTasks()->where('kind', AiTestTask::KIND_MAIN)->latest('id')->first();

        if (! $task) {
            return back()->with('error', 'Задание ещё не готово.');
        }

        if ($task->submission()->first()?->submitted_at) {
            return back()->with('error', 'Решение уже отправлено.');
        }

        /*
         * Срок с запасом на доставку. Решение, ушедшее за секунду до конца,
         * может дойти уже после — наказывать за скорость сети нечестно. Тот же
         * приём, что в проверке навыков.
         */
        if ($task->expired()) {
            $this->closeExpired($interview, $task);

            return redirect()->route('applicant.ai.task', $interview)
                ->with('error', 'Время на задание вышло.');
        }

        $validator = Validator::make($request->all(), [
            'solution' => 'required|string|min:1|max:'.GeminiTaskExaminer::MAX_SOLUTION,
        ]);

        if ($validator->fails()) {
            return back()->with('error', $validator->errors()->first())->withInput();
        }

        $solution = $validator->validated()['solution'];

        $submission = AiTestSubmission::create([
            'ai_test_task_id' => $task->id,
            'content' => $solution,
            'submitted_at' => now(),
        ]);

        // в коде бывают комментарии, читающиеся как указания модели
        $found = $this->guard->inspect($solution);

        if ($found['flagged']) {
            $this->guard->record($found, $interview);
        }

        GradeTestSubmission::dispatch($submission->id);

        return redirect()->route('applicant.ai.task', $interview)
            ->with('status', 'Решение отправлено. ИИ проверяет его — это занимает до минуты.');
    }

    /**
     * Состояние: готово ли задание, проверено ли решение, сколько осталось.
     */
    public function state(AiInterview $interview): JsonResponse
    {
        $this->mine($interview);

        $task = $interview->testTasks()->where('kind', AiTestTask::KIND_MAIN)->latest('id')->first();
        $submission = $task?->submission()->first();

        return response()->json([
            'ready' => $task !== null,
            'seconds_left' => $task?->secondsLeft() ?? 0,
            'expired' => (bool) $task?->expired(),
            'submitted' => $submission?->submitted_at !== null,
            'graded' => (bool) $submission?->isGraded(),
            'stage' => $interview->stage,
        ]);
    }

    /**
     * Поставить составление в очередь, не плодя задач.
     */
    private function requestTask(AiInterview $interview): void
    {
        // задача уже в очереди с прошлого захода
        if ($interview->testTasks()->exists()) {
            return;
        }

        ComposeTestTask::dispatch($interview->id);
    }

    /**
     * Срок вышел, решения нет.
     *
     * Ноль за задание, а не отказ: невыполненное задание — это часть итога с
     * весом, который задал работодатель, и решение принимается по сумме.
     */
    private function closeExpired(AiInterview $interview, AiTestTask $task): void
    {
        if ($task->submission()->first()) {
            return;
        }

        AiTestSubmission::create([
            'ai_test_task_id' => $task->id,
            'content' => null,
            'score' => 0,
            'review' => [],
            'submitted_at' => null,
            'graded_at' => now(),
        ]);

        $interview->update([
            'test_score' => 0,
            ...($interview->stage === 'test' ? ['stage' => 'decision'] : []),
        ]);
    }

    private function guardStage(AiInterview $interview)
    {
        if (! $interview->consented()) {
            return redirect()->route('applicant.ai.interview', $interview);
        }

        if ($interview->stage === 'interview') {
            return redirect()->route('applicant.ai.chat', $interview);
        }

        if (! in_array($interview->stage, ['test', 'decision', 'done'], true)) {
            return redirect()->route('applicant.ai.documents', $interview);
        }

        return null;
    }

    private function mine(AiInterview $interview): void
    {
        $applicant = auth()->user()->applicant;

        abort_if(! $applicant || $interview->applicant_id !== $applicant->id, 403);
    }
}
