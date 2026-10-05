<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Jobs\ComposeTestTask;
use App\Jobs\GradeTestSubmission;
use App\Jobs\MakeHiringDecision;
use App\Jobs\SynthesizeTurnSpeech;
use App\Models\AiInterview;
use App\Models\AiInterviewTurn;
use App\Models\AiTestSubmission;
use App\Models\AiTestTask;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\GeminiTaskExaminer;
use App\Services\Ai\SpeechLibrary;
use App\Services\Ai\TaskPreparer;
use App\Services\Security\InjectionGuard;
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
    public function __construct(
        private readonly InjectionGuard $guard,
        private readonly SpeechLibrary $speech,
    ) {
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

        // задание с нулевым весом не составляем: оно ни на что не влияет
        $testSkipped = ! $task && $interview->config && ! $interview->config->needsTest();

        // кандидат попал на стадию задания раньше, чем вес обнулили, —
        // ждать нечего, сразу к решению
        if ($testSkipped && $interview->stage === 'test') {
            $interview->update(['stage' => 'decision']);
            MakeHiringDecision::dispatch($interview->id);
        }

        // задания ещё нет — ставим составление в очередь и показываем ожидание
        if (! $task && ! $testSkipped) {
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

        // Время вышло, а решения нет: страница перезагружается по таймеру
        // именно затем, чтобы сервер закрыл попытку и запустил решение.
        // Без этого собеседование оставалось на стадии задания навсегда.
        if ($task && $task->closeIfExpired()) {
            $interview->refresh();
        }

        return view('applicant.pages.ai-interview.task', [
            'user' => auth()->user(),
            'applicant' => $interview->applicant,
            'interview' => $interview,
            'vacancy' => $interview->vacancy,
            'task' => $task,
            'testSkipped' => $testSkipped,
            // тест без верных ответов и все практические задания
            'quiz' => $task?->quizForCandidate() ?? [],
            'practical' => $task?->practical() ?? [],
            // заключительные слова Анны — последняя реплика, если это прощание
            'farewell' => $this->farewell($interview),
            'assistantName' => \App\Services\Ai\GeminiInterviewer::NAME,
            'assistantRole' => \App\Services\Ai\GeminiInterviewer::ROLE,
            'submission' => $task?->submission()->first(),
            'maxSolution' => GeminiTaskExaminer::MAX_SOLUTION,
        ]);
    }

    /**
     * Составить задание сейчас, не дожидаясь очереди.
     *
     * Зовёт страница разговора в фоне — после первого ответа, чтобы к концу
     * беседы задание уже было готово, — и страница задания, если кандидат
     * пришёл раньше. Составление занимает до полуминуты, столько этот
     * запрос и длится; кандидат его не ждёт.
     */
    public function prepare(AiInterview $interview, TaskPreparer $preparer)
    {
        $this->mine($interview);

        if (! $interview->consented() || ! in_array($interview->stage, ['interview', 'test'], true)) {
            return response()->json(['status' => 'skipped']);
        }

        @set_time_limit(180);
        // страница могла уйти дальше, а задание всё равно нужно доделать
        ignore_user_abort(true);

        try {
            $status = $preparer->prepare($interview);
        } catch (AiUnavailableException $e) {
            // модель занята — попробуем позже, страница спросит снова
            $status = 'busy';
        }

        return response()->json(['status' => $status]);
    }

    /**
     * Отправка решения.
     */
    public function submit(Request $request, AiInterview $interview)
    {
        $this->mine($interview);

        /*
         * Решение принимается только на своей стадии.
         *
         * Страницу вне стадии guardStage() не отдаёт, а отправка проверки не
         * имела: задание составляется в конце разговора, и по сохранившемуся
         * адресу решение уходило ещё до того, как прозвучал последний вопрос.
         * Дописывать его к уже объявленному решению тоже нельзя — итог от этого
         * не изменится, а в отчёте появится ответ, которого при решении не было.
         */
        if (! in_array($interview->stage, ['test', 'decision'], true)) {
            return redirect()->route('applicant.ai.interview', $interview)
                ->with('error', $interview->isDecided()
                    ? __('Собеседование завершено — задание больше не принимается.')
                    : __('Задание откроется после собеседования.'));
        }

        $task = $interview->testTasks()->where('kind', AiTestTask::KIND_MAIN)->latest('id')->first();

        if (! $task) {
            return back()->with('error', __('Задание ещё не готово.'));
        }

        if ($task->submission()->first()?->submitted_at) {
            return back()->with('error', __('Решение уже отправлено.'));
        }

        /*
         * Срок с запасом на доставку. Решение, ушедшее за секунду до конца,
         * может дойти уже после — наказывать за скорость сети нечестно. Тот же
         * приём, что в проверке навыков.
         */
        if ($task->expired()) {
            $task->closeIfExpired();

            return redirect()->route('applicant.ai.task', $interview)
                ->with('error', __('Время на задание вышло.'));
        }

        $validator = Validator::make($request->all(), [
            // решение первого задания, остальных — в parts, ответы теста — в quiz
            'solution' => 'nullable|string|max:'.GeminiTaskExaminer::MAX_SOLUTION,
            'parts' => 'nullable|array|max:5',
            'parts.*' => 'nullable|string|max:'.GeminiTaskExaminer::MAX_SOLUTION,
            'quiz' => 'nullable|array|max:10',
            'quiz.*' => 'nullable|integer|min:0|max:3',
        ]);

        if ($validator->fails()) {
            return back()->with('error', $validator->errors()->first())->withInput();
        }

        $validated = $validator->validated();
        $solution = trim((string) ($validated['solution'] ?? ''));
        $parts = array_map(fn ($p) => trim((string) $p), array_values($validated['parts'] ?? []));
        $quiz = $validated['quiz'] ?? [];

        // совсем пустую отправку не принимаем: скорее всего, нажали случайно
        if ($solution === '' && collect($parts)->filter()->isEmpty() && collect($quiz)->filter(fn ($v) => $v !== null)->isEmpty()) {
            return back()->with('error', __('Ответьте хотя бы на часть теста или заданий, прежде чем отправлять.'))->withInput();
        }

        $quizAnswers = [];
        foreach (array_keys($task->quiz ?? []) as $i) {
            $quizAnswers[$i] = isset($quiz[$i]) ? (int) $quiz[$i] : null;
        }

        $submission = AiTestSubmission::create([
            'ai_test_task_id' => $task->id,
            'content' => $solution,
            'answers' => ['quiz' => $quizAnswers, 'parts' => $parts],
            'submitted_at' => now(),
        ]);

        // попытку повлиять на оценку ищем во всех решениях сразу
        $solution = implode(PHP_EOL, array_merge([$solution], $parts));

        $found = $this->guard->inspect($solution);

        if ($found['flagged']) {
            $this->guard->record($found, $interview);
        }

        GradeTestSubmission::dispatch($submission->id);

        /*
         * Анна прощается: собеседование закончено, ответ придёт в срок.
         *
         * Реплика ложится в разговор, как и завершение беседы: её можно
         * озвучить, и в отчёте видно, что кандидату сказали напоследок.
         */
        $farewell = AiInterviewTurn::create([
            'ai_interview_id' => $interview->id,
            'position' => (int) $interview->turns()->max('position') + 1,
            'role' => AiInterviewTurn::ROLE_AI,
            'text' => $interview->farewellRemark(),
            'question_kind' => AiInterviewTurn::KIND_CLOSING,
            'speech_status' => $this->speech->available() ? 'pending' : 'none',
        ]);

        if ($this->speech->available()) {
            SynthesizeTurnSpeech::dispatch($farewell->id);
        }

        return redirect()->route('applicant.ai.task', $interview)
            ->with('status', __('Решение отправлено. ИИ проверяет его — это занимает до минуты.'));
    }


    /**
     * Поставить составление в очередь, не плодя задач.
     */
    private function requestTask(AiInterview $interview): void
    {
        // задача уже в очереди с прошлого захода
        if ($interview->testTasks()->where('kind', AiTestTask::KIND_MAIN)->exists()) {
            return;
        }

        ComposeTestTask::dispatch($interview->id);
    }

    private function farewell(AiInterview $interview): ?AiInterviewTurn
    {
        $last = $interview->turns()->reorder('position', 'desc')->first();

        return $last && $last->isClosing() ? $last : null;
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
