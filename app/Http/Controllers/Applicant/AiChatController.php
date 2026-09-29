<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Jobs\ScoreInterviewAnswer;
use App\Jobs\SynthesizeTurnSpeech;
use App\Models\AiInterview;
use App\Models\AiInterviewCriterion;
use App\Models\AiInterviewTurn;
use App\Services\Ai\AiInvalidAnswerException;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\GeminiInterviewer;
use App\Services\Ai\Interviewer;
use App\Services\Ai\SpeechLibrary;
use App\Services\Security\InjectionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Разговор кандидата с ИИ.
 *
 * Страница устроена как чат помощника по резюме: обычный Blade и fetch, без
 * второго фронтенд-подхода в проекте. Реплика приходит синхронно — её человек
 * ждёт, глядя в экран. Оценка ответа уходит в очередь: балл кандидату всё равно
 * не показывают, и заставлять его ждать ещё десять секунд незачем.
 */
class AiChatController extends Controller
{
    /** Предел длины ответа. Больше — это уже не ответ, а сочинение. */
    public const MAX_ANSWER = 4000;

    public function __construct(
        private readonly Interviewer $interviewer,
        private readonly InjectionGuard $guard,
        private readonly SpeechLibrary $speech,
    ) {
    }

    /**
     * Страница разговора.
     */
    public function show(AiInterview $interview)
    {
        $this->mine($interview);

        if ($redirect = $this->guardStage($interview)) {
            return $redirect;
        }

        $turns = $interview->turns()->get();

        return view('applicant.pages.ai-interview.chat', [
            'user' => auth()->user(),
            'applicant' => $interview->applicant,
            'interview' => $interview,
            'vacancy' => $interview->vacancy,
            'turns' => $turns,
            'config' => $interview->config,
            'asked' => $turns->where('role', AiInterviewTurn::ROLE_AI)->count(),
            'target' => (int) ($interview->config->questions_count ?? 8),
            'assistantName' => GeminiInterviewer::NAME,
            'assistantRole' => GeminiInterviewer::ROLE,
            'voiceEnabled' => $this->speech->available(),
            'maxAnswer' => self::MAX_ANSWER,
        ]);
    }

    /**
     * Первая реплика: ассистент здоровается и задаёт вопрос.
     *
     * Отдельный вызов, а не часть страницы: страница должна открыться сразу, а
     * первый вопрос модель формулирует несколько секунд. Пока он готовится,
     * аватар стоит в состоянии «думает».
     */
    public function begin(AiInterview $interview): JsonResponse
    {
        $this->mine($interview);

        if ($interview->turns()->exists()) {
            return response()->json(['already' => true]);
        }

        return $this->askNext($interview);
    }

    /**
     * Ответ кандидата и следующий вопрос.
     */
    public function answer(Request $request, AiInterview $interview): JsonResponse
    {
        $this->mine($interview);

        /*
         * Проверяем сами и отвечаем JSON.
         *
         * $request->validate() здесь не годится: в bootstrap/app.php стоит
         * shouldRenderJsonWhen(fn ($r) => $r->is('api/*')), поэтому на обычном
         * маршруте ошибка валидации уходит редиректом с ошибками в сессии, а
         * страница общается с сервером через fetch и редирект не разберёт.
         */
        $validator = Validator::make($request->all(), [
            'text' => 'required|string|min:1|max:'.self::MAX_ANSWER,
            'question_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $validated = $validator->validated();

        if ($interview->stage !== 'interview') {
            return response()->json(['error' => 'Собеседование уже завершено.'], 422);
        }

        /*
         * reorder, а не orderByDesc.
         *
         * Связь turns() уже отсортирована по возрастанию, и добавленный
         * orderByDesc не отменяет её, а становится вторым правилом — порядок
         * остаётся возрастающим. «Последним вопросом» оказывался первый, и
         * любой второй ответ отвергался как уже отправленный.
         */
        $question = $interview->turns()->where('role', AiInterviewTurn::ROLE_AI)
            ->reorder('position', 'desc')->first();

        if (! $question) {
            return response()->json(['error' => 'Вопрос ещё не задан.'], 422);
        }

        /*
         * Двойное нажатие «отправить».
         *
         * Страница присылает номер вопроса, на который отвечает. Если он не
         * совпал с текущим, значит первый запрос уже прошёл и успел задать
         * следующий вопрос — второй ответ относился бы не к тому вопросу и
         * получил бы чужую оценку. Проверка по «есть ли ответ после вопроса»
         * такой случай не ловит: к моменту второго запроса новый вопрос ещё без
         * ответа, и он выглядит законным.
         */
        $answering = (int) ($validated['question_id'] ?? 0);

        if ($answering > 0 && $answering !== $question->id) {
            return response()->json(['error' => 'Этот ответ уже отправлен.'], 422);
        }

        $answered = $interview->turns()
            ->where('role', AiInterviewTurn::ROLE_CANDIDATE)
            ->where('position', '>', $question->position)
            ->exists();

        if ($answered) {
            return response()->json(['error' => 'Этот ответ уже отправлен.'], 422);
        }

        /*
         * Попытку управлять оценкой отмечаем, но ответ не подменяем и не
         * чистим: слова кандидата должны остаться его словами, иначе оценка
         * пойдёт не по тому, что он сказал. Защита — в устройстве оценки, а не
         * в правке текста.
         */
        $found = $this->guard->inspect($validated['text']);

        $answer = AiInterviewTurn::create([
            'ai_interview_id' => $interview->id,
            'position' => $this->nextPosition($interview),
            'role' => AiInterviewTurn::ROLE_CANDIDATE,
            'text' => $validated['text'],
            // критерий берём у вопроса: оценка должна знать, что проверялось
            'criterion_key' => $question->criterion_key,
            'injection_flagged' => $found['flagged'],
        ]);

        if ($found['flagged']) {
            $this->guard->record($found, $interview, $answer);
        }

        // балл считается в очереди: кандидату он не показывается
        ScoreInterviewAnswer::dispatch($answer->id);

        return $this->askNext($interview);
    }

    /**
     * Состояние: сколько задано, сколько осталось, готова ли озвучка.
     *
     * Страница спрашивает его, пока ждёт реплику или звук.
     */
    public function state(AiInterview $interview): JsonResponse
    {
        $this->mine($interview);

        $turns = $interview->turns()->get();
        $last = $turns->where('role', AiInterviewTurn::ROLE_AI)->last();

        return response()->json([
            'stage' => $interview->stage,
            'asked' => $turns->where('role', AiInterviewTurn::ROLE_AI)->count(),
            'target' => (int) ($interview->config->questions_count ?? 8),
            'progress' => $interview->progress(),
            'speech' => $last ? [
                'turn' => $last->id,
                'status' => $last->speech_status,
                'url' => $last->speechReady()
                    ? route('applicant.ai.speech.turn', $last)
                    : null,
            ] : null,
        ]);
    }

    /**
     * Спросить модель о следующей реплике и сохранить её.
     */
    private function askNext(AiInterview $interview): JsonResponse
    {
        $criteria = AiInterviewCriterion::where('vacancy_id', $interview->vacancy_id)
            ->confirmed()->ordered()->get();

        try {
            $next = $this->interviewer->nextQuestion($interview, $criteria);
        } catch (AiInvalidAnswerException $e) {
            /*
             * Модель дважды не смогла задать вопрос. Разговор не бросаем на
             * полуслове: передаём человеку и честно говорим об этом кандидату.
             */
            $interview->update(['requires_review' => true]);

            return response()->json([
                'error' => 'Не удалось продолжить разговор. Ваши ответы сохранены, '
                    .'дальше кандидатуру рассмотрит человек.',
                'handover' => true,
            ], 503);
        } catch (AiUnavailableException $e) {
            // сохранённый прогресс позволяет вернуться и продолжить
            return response()->json(array_filter([
                'error' => $e->getMessage(),
                'retry_after' => $e->retryAfter,
            ]), 503);
        }

        $asked = $interview->turns()->where('role', AiInterviewTurn::ROLE_AI)->count();

        $turn = AiInterviewTurn::create([
            'ai_interview_id' => $interview->id,
            'position' => $this->nextPosition($interview),
            'role' => AiInterviewTurn::ROLE_AI,
            'text' => $next['text'],
            'question_kind' => $next['kind'],
            'criterion_key' => $next['criterion_key'],
            'speech_status' => $this->speech->available() ? 'pending' : 'none',
        ]);

        // озвучка догоняет: вопрос уже виден текстом
        if ($this->speech->available()) {
            SynthesizeTurnSpeech::dispatch($turn->id);
        }

        $done = $this->conversationOver($interview, $asked + 1, (bool) $next['done']);

        if ($done) {
            $interview->update(['stage' => 'test']);
        }

        return response()->json([
            'question' => [
                'id' => $turn->id,
                'text' => $turn->text,
                'kind' => $turn->questionKindLabel(),
                'speech_status' => $turn->speech_status,
                'speech_url' => route('applicant.ai.speech.turn', $turn),
                'speech_state_url' => route('applicant.ai.speech.status', $turn),
            ],
            'asked' => $asked + 1,
            'target' => (int) ($interview->config->questions_count ?? 8),
            'done' => $done,
        ]);
    }

    /**
     * Разговор закончен?
     *
     * Решает контроллер, а не модель. Модель может счесть, что ей всё ясно, уже
     * на втором вопросе, а работодатель заказал восемь — собеседование в одну
     * реплику не собеседование. Поэтому её «хватит» принимается только после
     * половины заказанных вопросов, а безусловно разговор заканчивается, когда
     * задано нужное число.
     *
     * Правило живёт здесь намеренно: в реализации собеседующего оно зависело бы
     * от того, какая реализация стоит в контейнере, а это условие договора с
     * работодателем, а не свойство провайдера.
     */
    private function conversationOver(AiInterview $interview, int $asked, bool $modelSaysDone): bool
    {
        $target = (int) ($interview->config->questions_count ?? 8);

        if ($asked >= $target) {
            return true;
        }

        return $modelSaysDone && $asked >= (int) ceil($target / 2);
    }

    /**
     * Номер следующей реплики. Считаем от максимума, а не от количества:
     * удалённая реплика не должна приводить к столкновению на unique.
     */
    private function nextPosition(AiInterview $interview): int
    {
        return (int) $interview->turns()->max('position') + 1;
    }

    /**
     * Кандидат пришёл не на ту стадию — отправляем туда, где он нужен.
     */
    private function guardStage(AiInterview $interview)
    {
        if (! $interview->consented()) {
            return redirect()->route('applicant.ai.interview', $interview);
        }

        if ($interview->analysis_status !== 'ready') {
            return redirect()->route('applicant.ai.documents', $interview);
        }

        // разговор закончен: тестовое задание и решение — следующие этапы
        if (! in_array($interview->stage, ['interview'], true)) {
            return redirect()->route('applicant.ai.documents', $interview)
                ->with('status', 'Собеседование пройдено. Следующий этап ещё готовится.');
        }

        return null;
    }

    private function mine(AiInterview $interview): void
    {
        $applicant = auth()->user()->applicant;

        abort_if(! $applicant || $interview->applicant_id !== $applicant->id, 403);
    }
}
