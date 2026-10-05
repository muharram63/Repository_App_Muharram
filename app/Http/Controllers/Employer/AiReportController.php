<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\AiCandidateDocument;
use App\Models\AiDecision;
use App\Models\AiInteraction;
use App\Models\AiInterview;
use App\Models\AiInterviewCriterion;
use App\Models\Employer;
use App\Models\UserNotification;
use App\Models\Vacancy;
use App\Services\Ai\AiInvalidAnswerException;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\HiringAdvisor;
use App\Services\Ai\SpeechLibrary;
use App\Services\Ai\SpeechRecognizer;
use App\Services\Documents\TextExtractor;
use App\Services\Scoring\DecisionEngine;
use App\Services\Scoring\DecisionRelease;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Отчёт работодателя по кандидатам.
 *
 * Смысл раздела в том, чтобы решение ИИ можно было проверить, а не принять на
 * веру. Поэтому здесь показывается не только исход, но и всё, из чего он
 * сложился: какие требования закрыты и какой цитатой, как оценён каждый ответ и
 * почему, что вывела запущенная программа, какие обращения к модели были и
 * сколько стоили.
 *
 * Последнее слово за человеком: любое решение можно переопределить, и прежнее
 * при этом никуда не девается — видно и что решил ИИ, и что решил работодатель.
 */
class AiReportController extends Controller
{
    public function __construct(
        private readonly DecisionRelease $release,
        private readonly HiringAdvisor $advisor,
        private readonly SpeechLibrary $speech,
        private readonly SpeechRecognizer $recognizer,
    ) {
    }

    /**
     * Реплика разговора с Анной о кандидате.
     *
     * Работодатель спрашивает — Анна отвечает по отчёту. Если он говорит, что
     * принимает или отказывает, Анна только переспрашивает: ответ кандидату
     * уходит обычными кнопками после явного «да» на странице.
     */
    public function advise(Request $request, Vacancy $vacancy, AiInterview $interview): JsonResponse
    {
        $this->authorizeVacancy($vacancy);
        $this->authorizeInterview($vacancy, $interview);

        $validator = Validator::make($request->all(), [
            'message' => 'nullable|string|max:1500',
            'history' => 'nullable|array|max:30',
            'history.*.role' => 'required|in:ai,employer',
            'history.*.text' => 'required|string|max:1500',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        if (! $this->advisor->available()) {
            return response()->json(['error' => __('ИИ недоступен: проверьте GEMINI_API_KEY в .env.')], 503);
        }

        $validated = $validator->validated();

        try {
            $answer = $this->advisor->advise(
                $interview,
                $validated['history'] ?? [],
                (string) ($validated['message'] ?? ''),
                app()->getLocale(),
            );
        } catch (AiInvalidAnswerException $e) {
            return response()->json(['error' => __('Не получилось ответить — спросите иначе.')], 503);
        } catch (AiUnavailableException $e) {
            return response()->json(array_filter(['error' => $e->getMessage(), 'retry_after' => $e->retryAfter]), 503);
        }

        $decision = $interview->latestDecision;

        return response()->json($answer + [
            // как исполнить решение: подтвердить ИИ или выбрать свой итог
            'can_approve' => $decision && ! $decision->isReleased()
                && in_array($decision->outcome, ['passed', 'rejected'], true),
            'recommended' => $decision?->outcome,
            'released' => (bool) $decision?->isReleased(),
            'awaits' => $interview->awaitsEmployer(),
        ]);
    }

    /**
     * Довести проверку до конца, пока работодатель смотрит отчёт.
     *
     * Тест и задания проверяет ИИ, но шаг за шагом и только когда его кто-то
     * двигает: очередь или открытая страница. Кандидат после отправки решений на
     * сайт может не вернуться — тогда работодатель видел «тест ещё не
     * проверен», а Анна объясняла это так, будто проверки нет вовсе.
     */
    public function pump(Vacancy $vacancy, AiInterview $interview, \App\Services\Ai\InterviewPump $pump): JsonResponse
    {
        $this->authorizeVacancy($vacancy);
        $this->authorizeInterview($vacancy, $interview);

        @set_time_limit(180);
        ignore_user_abort(true);

        $state = $pump->step($interview);
        $interview->refresh();

        return response()->json($state + [
            // рекомендация готова — странице пора обновиться
            'ready' => $interview->latestDecision !== null,
        ]);
    }

    /**
     * Озвучить реплику Анны. Только её собственные ответы — длина ограничена,
     * а частота — как у разговора: синтезатором речи на чужие тексты это не станет.
     */
    public function speak(Request $request, Vacancy $vacancy, AiInterview $interview)
    {
        $this->authorizeVacancy($vacancy);
        $this->authorizeInterview($vacancy, $interview);

        $validated = $request->validate(['text' => 'required|string|max:1500']);

        if (! $this->speech->available()) {
            return response()->json(['error' => __('Голосовой синтез выключен: проверьте GEMINI_API_KEY и GEMINI_TTS_ENABLED.'), 'fallback' => 'browser'], 503);
        }

        @set_time_limit(120);

        try {
            $made = $this->speech->make($validated['text'], $interview);
        } catch (AiUnavailableException $e) {
            return response()->json(['error' => $e->getMessage(), 'fallback' => 'browser'], 503);
        }

        return Storage::disk(SpeechLibrary::DISK)->response($made['path'], 'advice.wav', [
            'Content-Type' => 'audio/wav',
        ]);
    }

    /**
     * Голос работодателя в текст — там, где браузер не распознаёт речь сам.
     */
    public function listen(Request $request, Vacancy $vacancy, AiInterview $interview): JsonResponse
    {
        $this->authorizeVacancy($vacancy);
        $this->authorizeInterview($vacancy, $interview);

        $validator = Validator::make($request->all(), [
            'audio' => 'required|file|max:6144|mimetypes:audio/wav,audio/x-wav,audio/wave,audio/vnd.wave',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        try {
            $text = $this->recognizer->transcribe(
                $interview,
                (string) file_get_contents($request->file('audio')->getRealPath()),
                'audio/wav',
                app()->getLocale(),
            );
        } catch (AiInvalidAnswerException|AiUnavailableException $e) {
            return response()->json(['error' => __('Не удалось разобрать запись — повторите или напишите текстом.')], 503);
        }

        return response()->json(['text' => $text]);
    }

    /**
     * Кандидаты по вакансии.
     */
    public function candidates(Vacancy $vacancy)
    {
        $this->authorizeVacancy($vacancy);

        $interviews = AiInterview::where('vacancy_id', $vacancy->id)
            ->with('applicant.user', 'latestDecision')
            ->orderByRaw('decided_at is null desc')
            ->latest('updated_at')
            ->get();

        return view('employer.pages.ai-screening.candidates', [
            'user' => auth()->user(),
            'employer' => $this->currentEmployer(),
            'vacancy' => $vacancy,
            'interviews' => $interviews,
            'counts' => [
                'all' => $interviews->count(),
                'passed' => $interviews->where('outcome', 'passed')->count(),
                'rejected' => $interviews->where('outcome', 'rejected')->count(),
                'manual' => $interviews->where('requires_review', true)->count(),
                'running' => $interviews->whereNull('outcome')->count(),
            ],
        ]);
    }

    /**
     * Подробный отчёт по одному кандидату.
     */
    public function candidate(Vacancy $vacancy, AiInterview $interview)
    {
        $this->authorizeVacancy($vacancy);
        $this->authorizeInterview($vacancy, $interview);

        return view('employer.pages.ai-screening.candidate',
            $this->report($vacancy, $interview) + [
                'user' => auth()->user(),
                'employer' => $this->currentEmployer(),
            ]);
    }

    /**
     * Работодатель не согласен с решением ИИ.
     */
    public function override(Request $request, Vacancy $vacancy, AiInterview $interview)
    {
        $this->authorizeVacancy($vacancy);
        $this->authorizeInterview($vacancy, $interview);

        $validated = $request->validate([
            'outcome' => 'required|in:passed,rejected',
            'note' => 'nullable|string|max:1000',
        ]);

        if (! $interview->awaitsEmployer()) {
            return back()->with('error', __('Собеседование ещё идёт — решение можно будет принять, когда оно закончится или потребует вашей проверки.'));
        }

        /*
         * Записи решения может не быть вовсе.
         *
         * Если разбор документов дважды провалился или модель не смогла вести
         * разговор, собеседование уходит «на ручную проверку» без записи
         * решения. Раньше здесь был отказ «решения ещё нет», и кандидат,
         * которого система передала человеку, не мог получить ответа ни от
         * кого: работодателю переопределять было нечего. Теперь запись
         * заводится сейчас — как ручная проверка, которую решил человек.
         */
        $decision = $interview->latestDecision ?? $this->manualDecision($interview);

        $outcome = $validated['outcome'];

        /*
         * Письмо кандидату — под новый исход и на языке собеседования.
         *
         * Раньше менялся только исход, а письмо оставалось от ИИ: кандидат
         * видел «Прошёл отбор» и под ним отказ. Причины отказа берём из
         * решения ИИ — других у системы нет; если их нет, письмо без причины.
         */
        $message = \App\Services\Scoring\DecisionMessage::template(
            $outcome,
            $interview,
            // причины годятся только из отказа ИИ: у ручной проверки там
            // служебное «не удалось разобрать», и в письмо оно не годится
            $outcome === 'rejected' && $decision->outcome === 'rejected' ? ($decision->reasons ?? []) : [],
        );

        /*
         * Переопределение дописывается к решению, а не заменяет его. В отчёте
         * должно быть видно и что посчитал ИИ, и что решил человек: иначе через
         * месяц никто не вспомнит, почему исход не совпадает с баллом.
         */
        $decision->update([
            'overridden_by' => auth()->id(),
            'overridden_at' => now(),
            'override_outcome' => $outcome,
            'override_note' => $validated['note'] ?? null,
            'override_message' => $message,
        ]);

        $interview->update([
            'outcome' => $outcome,
            // решение принято человеком — ждать больше нечего
            'requires_review' => false,
            'decided_at' => $interview->decided_at ?? now(),
            'stage' => 'done',
        ]);

        // Ответ кандидату уходит сейчас: решение принял работодатель. Если оно
        // было отправлено раньше, кандидату сообщаем, что решение пересмотрено.
        if (! $this->release->release($decision->fresh(), 'employer')) {
            $interview->response?->update([
                'status' => $outcome === 'passed' ? 'accepted' : 'rejected',
            ]);

            UserNotification::deliver(
                $interview->applicant?->user_id,
                'response_status',
                UserNotification::text($outcome === 'passed' ? 'Вы прошли отбор' : 'Ответ по вашей кандидатуре'),
                UserNotification::text('Решение по вакансии «:title» принял работодатель.', ['title' => $vacancy->title]),
                route('applicant.ai.result', $interview),
            );
        }

        return back()->with('status', __('Решение изменено: :outcome.', ['outcome' => $decision->fresh()->outcomeLabel()]));
    }

    /**
     * Работодатель согласен с ИИ — ответ уходит кандидату.
     */
    public function approve(Vacancy $vacancy, AiInterview $interview)
    {
        $this->authorizeVacancy($vacancy);
        $this->authorizeInterview($vacancy, $interview);

        $decision = $interview->latestDecision;

        if (! $decision || ! in_array($decision->effectiveOutcome(), ['passed', 'rejected'], true)) {
            return back()->with('error', __('ИИ не взялся решать сам — выберите итог ниже.'));
        }

        if (! $this->release->release($decision, 'employer')) {
            return back()->with('status', __('Ответ кандидату уже отправлен.'));
        }

        $interview->update(['requires_review' => false, 'stage' => 'done']);

        return back()->with('status', __('Решение подтверждено и отправлено кандидату.'));
    }

    /**
     * Файл документа кандидата.
     *
     * Отчёт показывал только разбор диплома моделью, а сам файл открыть было
     * нельзя — проверить вывод ИИ своими глазами работодатель не мог. Отдаём
     * с приватного диска и только владельцу вакансии.
     */
    public function document(Vacancy $vacancy, AiInterview $interview, AiCandidateDocument $document)
    {
        $this->authorizeVacancy($vacancy);
        $this->authorizeInterview($vacancy, $interview);

        abort_if($document->ai_interview_id !== $interview->id, 403);

        $disk = Storage::disk(TextExtractor::DISK);

        abort_unless($disk->exists($document->path), 404);

        return $disk->response($document->path, $document->original_name);
    }

    /**
     * Выгрузка отчёта отдельным файлом.
     *
     * Самодостаточная страница: открывается без сети и печатается в PDF
     * средствами браузера. Так отчёт можно приложить к переписке или показать
     * тому, у кого нет доступа к площадке.
     */
    public function export(Vacancy $vacancy, AiInterview $interview): StreamedResponse
    {
        $this->authorizeVacancy($vacancy);
        $this->authorizeInterview($vacancy, $interview);

        $html = view('employer.pages.ai-screening.export', $this->report($vacancy, $interview))->render();

        $name = 'otchet-'.$interview->id.'-'.now()->format('Y-m-d').'.html';

        return response()->streamDownload(
            fn () => print($html),
            $name,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    /**
     * Удаление данных кандидата по его запросу.
     *
     * Удаляется всё, включая аудит обращений к модели: в промптах лежат ответы
     * человека и содержимое его документов, и «сохраним аудит, удалим остальное»
     * было бы обещанием, которого мы не выполняем. Отклик при этом остаётся —
     * он принадлежит воронке найма, а не собеседованию.
     */
    public function purge(Vacancy $vacancy, AiInterview $interview)
    {
        $this->authorizeVacancy($vacancy);
        $this->authorizeInterview($vacancy, $interview);

        $interview->eraseCompletely();

        return redirect()->route('employer.ai.candidates', $vacancy)
            ->with('status', __('Данные кандидата по этому собеседованию удалены.'));
    }

    /**
     * Данные отчёта. Собираются в одном месте: их показывают и страница,
     * и выгрузка, и расходиться они не должны.
     */
    private function report(Vacancy $vacancy, AiInterview $interview): array
    {
        $interview->load([
            'applicant.user', 'documents', 'requirementChecks.criterion',
            'turns', 'testTasks.submissions', 'decisions.overriddenBy', 'injectionAttempts',
        ]);

        $criteria = AiInterviewCriterion::where('vacancy_id', $vacancy->id)
            ->confirmed()->ordered()->get();

        $usage = AiInteraction::where('ai_interview_id', $interview->id)
            ->selectRaw('count(*) as calls, sum(tokens_in) as tokens_in,
                sum(tokens_out) as tokens_out, sum(latency_ms) as latency')
            ->first();

        $task = $interview->testTasks->firstWhere('kind', 'main');

        return [
            'vacancy' => $vacancy,
            'interview' => $interview,
            'candidate' => $interview->applicant?->user,
            'resume' => $interview->applicant?->resume()->latest('id')->first(),
            'criteria' => $criteria,
            'checks' => $interview->requirementChecks,
            'documents' => $interview->documents,
            'turns' => $interview->turns,
            'task' => $task,
            'submission' => $task?->submissions->sortByDesc('id')->first(),
            'decision' => $interview->latestDecision,
            'decisions' => $interview->decisions,
            'analysis' => $interview->analysis ?? [],
            'injections' => $interview->injectionAttempts,
            'usage' => $usage,
            'gates' => AiDecision::GATES,
            'outcomes' => DecisionEngine::PASSED,
        ];
    }

    /**
     * Запись решения для собеседования, которое система передала человеку,
     * так и не решив сама. Баллы — какие успели посчитаться.
     */
    private function manualDecision(AiInterview $interview): AiDecision
    {
        return AiDecision::create([
            'ai_interview_id' => $interview->id,
            'round' => $interview->follow_up_round + 1,
            'documents_score' => $interview->documents_score,
            'interview_score' => $interview->interview_score,
            'test_score' => $interview->test_score,
            'total' => $interview->total_score,
            'outcome' => 'manual_review',
            'gate_reason' => 'invalid_ai_response',
            'reasons' => [],
            'message_to_candidate' => \App\Services\Scoring\DecisionMessage::template('manual_review', $interview),
            'requires_manual_review' => true,
        ]);
    }

    private function authorizeInterview(Vacancy $vacancy, AiInterview $interview): void
    {
        abort_if($interview->vacancy_id !== $vacancy->id, 403);
    }

    private function authorizeVacancy(Vacancy $vacancy): void
    {
        abort_if($vacancy->employer_id !== $this->currentEmployer()->id, 403);
    }

    private function currentEmployer(): Employer
    {
        $employer = auth()->user()?->employer;

        if (! $employer) {
            throw new HttpResponseException(
                redirect()->route('public.employers.create')
                    ->with('error', __('Сначала заполните анкету работодателя.'))
            );
        }

        return $employer;
    }
}
