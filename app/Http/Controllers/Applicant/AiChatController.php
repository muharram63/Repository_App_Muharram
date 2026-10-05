<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Jobs\MakeHiringDecision;
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
use App\Services\Ai\SpeechRecognizer;
use App\Services\Security\InjectionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        private readonly SpeechRecognizer $recognizer,
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
            'asked' => $turns->filter(fn (AiInterviewTurn $t) => $t->role === AiInterviewTurn::ROLE_AI && ! $t->isClosing())->count(),
            'target' => $interview->questionTarget(),
            'assistantName' => GeminiInterviewer::NAME,
            'assistantRole' => GeminiInterviewer::ROLE,
            'voiceEnabled' => $this->speech->available(),
            // распознавание на сервере — для браузеров без своего (Firefox, Safari)
            'serverRecognition' => $this->recognizer->available(),
            'maxAnswer' => self::MAX_ANSWER,
            // разбор документов ещё идёт — скажем, что вопросы уточнятся
            'analysing' => $interview->analysisRunning(),
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

        /*
         * Стадию проверяем и здесь, а не только на странице.
         *
         * Страницу без согласия и без разбора документов guardStage() не
         * отдаёт, но этот адрес вызывается через fetch и достижим сам по себе.
         * Без проверки первый вопрос задавался бы человеку, который согласия не
         * давал: модель спрошена, реплика в базе, стадия всё ещё «согласие».
         * Обещание «без согласия ничего не происходит» нарушалось бы ровно
         * одним POST-запросом.
         */
        if ($error = $this->notReadyToTalk($interview)) {
            return response()->json([
                'error' => $error,
                'reload' => true,
            ], 422);
        }

        /*
         * Последняя реплика — вопрос: он уже задан, ждём ответа.
         * Последняя реплика — ответ кандидата: следующий вопрос не пришёл
         * (модель была недоступна) — задаём его сейчас. Без этого разговор
         * после любого сбоя модели обрывался навсегда.
         */
        $last = $interview->turns()->reorder('position', 'desc')->first();

        // завершение прошлого круга — не вопрос: после дораунда разговор
        // продолжается новым вопросом
        if ($last && $last->role === AiInterviewTurn::ROLE_AI && ! $last->isClosing()) {
            return response()->json(['already' => true]);
        }

        return $this->proceed($interview);
    }

    /** Предел записи устного ответа: около трёх минут WAV 16 кГц моно. */
    public const MAX_AUDIO_KB = 6144;

    /**
     * Устный ответ в текст — для голосового режима.
     *
     * Только расшифровка: ответ не сохраняется и не отправляется. Текст
     * возвращается странице, кандидат видит его в поле ответа, и отправка
     * идёт обычным путём. Так устный и письменный ответ — одно и то же, а
     * сама запись голоса у нас не остаётся вовсе.
     */
    public function transcribe(Request $request, AiInterview $interview): JsonResponse
    {
        $this->mine($interview);

        if ($error = $this->notReadyToTalk($interview)) {
            return response()->json(['error' => $error, 'reload' => true], 422);
        }

        $validator = Validator::make($request->all(), [
            // WAV собирает сама страница: его понимают и браузер, и модель
            'audio' => 'required|file|max:'.self::MAX_AUDIO_KB.'|mimetypes:audio/wav,audio/x-wav,audio/wave,audio/vnd.wave',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        if (! $this->recognizer->available()) {
            return response()->json(['error' => __('Распознавание речи недоступно — ответьте текстом.')], 503);
        }

        try {
            $text = $this->recognizer->transcribe(
                $interview,
                (string) file_get_contents($request->file('audio')->getRealPath()),
                'audio/wav',
            );
        } catch (AiInvalidAnswerException $e) {
            return response()->json(['error' => __('Не удалось разобрать запись — повторите или ответьте текстом.')], 503);
        } catch (AiUnavailableException $e) {
            return response()->json(array_filter([
                'error' => $e->getMessage(),
                'retry_after' => $e->retryAfter,
            ]), 503);
        }

        return response()->json(['text' => $text]);
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

        if ($error = $this->notReadyToTalk($interview)) {
            return response()->json(['error' => $error, 'reload' => true], 422);
        }

        /*
         * reorder, а не orderByDesc.
         *
         * Связь turns() уже отсортирована по возрастанию, и добавленный
         * orderByDesc не отменяет её, а становится вторым правилом — порядок
         * остаётся возрастающим. «Последним вопросом» оказывался первый, и
         * любой второй ответ отвергался как уже отправленный.
         */
        $question = $interview->turns()->questions()
            ->reorder('position', 'desc')->first();

        if (! $question) {
            return response()->json(['error' => __('Вопрос ещё не задан.')], 422);
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
            return response()->json(['error' => __('Этот ответ уже отправлен.')], 422);
        }

        /*
         * Попытку управлять оценкой отмечаем, но ответ не подменяем и не
         * чистим: слова кандидата должны остаться его словами, иначе оценка
         * пойдёт не по тому, что он сказал. Защита — в устройстве оценки, а не
         * в правке текста.
         */
        $found = $this->guard->inspect($validated['text']);

        /*
         * Проверка «ответа ещё нет» и запись ответа — под замком строки
         * собеседования. Без него два запроса, пришедшие одновременно (двойное
         * нажатие, две вкладки), оба проходили проверку и записывали два
         * ответа на один вопрос — и оба получали оценку.
         */
        $answer = DB::transaction(function () use ($interview, $question, $validated, $found) {
            AiInterview::whereKey($interview->id)->lockForUpdate()->first();

            $answered = $interview->turns()
                ->where('role', AiInterviewTurn::ROLE_CANDIDATE)
                ->where('position', '>', $question->position)
                ->exists();

            if ($answered) {
                return null;
            }

            return AiInterviewTurn::create([
                'ai_interview_id' => $interview->id,
                'position' => $this->nextPosition($interview),
                'role' => AiInterviewTurn::ROLE_CANDIDATE,
                'text' => $validated['text'],
                // критерий берём у вопроса: оценка должна знать, что проверялось
                'criterion_key' => $question->criterion_key,
                'injection_flagged' => $found['flagged'],
            ]);
        });

        if (! $answer) {
            // ответ записан раньше, а следующего вопроса нет — страница не
            // повторяет ответ, а просит продолжить разговор
            return response()->json(['error' => __('Этот ответ уже отправлен.'), 'resume' => true], 422);
        }

        if ($found['flagged']) {
            $this->guard->record($found, $interview, $answer);
        }

        // балл считается в очереди: кандидату он не показывается
        ScoreInterviewAnswer::dispatch($answer->id);

        return $this->proceed($interview, saved: true);
    }

    /**
     * Следующий шаг разговора после ответа (или при возвращении к нему).
     *
     * Разговор заканчивается, когда кандидат ОТВЕТИЛ на нужное число вопросов,
     * а не когда это число вопросов задано. Раньше счёт шёл по заданным
     * вопросам: N-й вопрос появлялся на экране и тут же закрывал разговор —
     * ответить на него было нельзя, и человек видел вопрос рядом с надписью
     * «Собеседование пройдено».
     *
     * $saved — ответ кандидата уже записан. Если модель сейчас недоступна,
     * странице надо сказать именно это: ответ не повторять, а продолжить
     * позже. Раньше страница возвращала текст в поле, повторная отправка
     * получала «Этот ответ уже отправлен», а следующий вопрос не задавался
     * уже никогда — даже после перезагрузки.
     */
    private function proceed(AiInterview $interview, bool $saved = false): JsonResponse
    {
        $answers = $this->answersCount($interview);

        if ($answers >= $interview->questionTarget()) {
            return $this->finish($interview);
        }

        $criteria = AiInterviewCriterion::where('vacancy_id', $interview->vacancy_id)
            ->confirmed()->ordered()->get();

        try {
            $next = $this->interviewer->nextQuestion($interview, $criteria);
        } catch (AiInvalidAnswerException $e) {
            /*
             * Модель дважды не смогла задать вопрос. Разговор не бросаем на
             * полуслове: передаём человеку и честно говорим об этом кандидату.
             *
             * И сразу запускаем решение. Одной пометки было мало: стадия
             * оставалась «собеседование», решение не принимал никто, и
             * работодатель не мог вынести своё — переопределять было нечего.
             */
            $interview->update(['requires_review' => true, 'stage' => 'decision']);
            MakeHiringDecision::dispatch($interview->id);

            return response()->json([
                'error' => __('Не удалось продолжить разговор. Ваши ответы сохранены, дальше кандидатуру рассмотрит человек.'),
                'handover' => true,
            ], 503);
        } catch (AiUnavailableException $e) {
            // сохранённый прогресс позволяет вернуться и продолжить
            return response()->json(array_filter([
                'error' => $e->getMessage(),
                'retry_after' => $e->retryAfter,
                'saved' => $saved ?: null,
            ]), 503);
        }

        /*
         * «Хватит» модели разговор не заканчивает.
         *
         * Раньше её done принимался после половины вопросов, и при пяти
         * заказанных разговор обрывался на третьем ответе. Число вопросов —
         * договорённость с работодателем: ИИ задаёт их все, а когда основные
         * требования уже покрыты, углубляется в опыт кандидата.
         */

        /*
         * Вопрос записываем под замком и только если он всё ещё нужен.
         *
         * Модель думает несколько секунд, и за это время второй запрос
         * (вторая вкладка, повтор после сбоя) мог уже задать свой вопрос —
         * тогда этот лишний, и кандидат увидел бы два вопроса подряд.
         */
        $turn = DB::transaction(function () use ($interview, $next) {
            AiInterview::whereKey($interview->id)->lockForUpdate()->first();

            $last = $interview->turns()->reorder('position', 'desc')->first();

            if ($last && $last->role === AiInterviewTurn::ROLE_AI && ! $last->isClosing()) {
                return null;
            }

            return AiInterviewTurn::create([
                'ai_interview_id' => $interview->id,
                'position' => $this->nextPosition($interview),
                'role' => AiInterviewTurn::ROLE_AI,
                'text' => $next['text'],
                'question_kind' => $next['kind'],
                'criterion_key' => $next['criterion_key'],
                'speech_status' => $this->speech->available() ? 'pending' : 'none',
            ]);
        });

        if (! $turn) {
            return response()->json(['already' => true, 'reload' => true]);
        }

        // озвучка догоняет: вопрос уже виден текстом
        if ($this->speech->available()) {
            SynthesizeTurnSpeech::dispatch($turn->id);
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
            'asked' => $interview->turns()->questions()->count(),
            'target' => $interview->questionTarget(),
            'done' => false,
        ]);
    }

    /**
     * Разговор окончен: дальше задание или решение.
     */
    private function finish(AiInterview $interview): JsonResponse
    {
        $this->afterConversation($interview);

        /*
         * Анна сама подводит черту.
         *
         * Раньше разговор просто обрывался панелью «Собеседование пройдено», и
         * кандидат сам искал, куда идти. Теперь она говорит, что дальше и когда
         * ждать ответа, а страница после её слов сама ведёт к заданию.
         */
        $closing = AiInterviewTurn::create([
            'ai_interview_id' => $interview->id,
            'position' => $this->nextPosition($interview),
            'role' => AiInterviewTurn::ROLE_AI,
            'text' => $interview->closingRemark($interview->fresh()->stage === 'test'),
            'question_kind' => AiInterviewTurn::KIND_CLOSING,
            'speech_status' => $this->speech->available() ? 'pending' : 'none',
        ]);

        if ($this->speech->available()) {
            SynthesizeTurnSpeech::dispatch($closing->id);
        }

        return response()->json([
            'asked' => $interview->turns()->questions()->count(),
            'target' => $interview->questionTarget(),
            'done' => true,
            'closing' => [
                'id' => $closing->id,
                'text' => $closing->text,
                'speech_status' => $closing->speech_status,
                'speech_state_url' => route('applicant.ai.speech.status', $closing),
            ],
            // куда идти дальше, решает show(): задание или ожидание решения
            'next_url' => route('applicant.ai.interview', $interview),
        ]);
    }

    private function answersCount(AiInterview $interview): int
    {
        return $interview->turns()->where('role', AiInterviewTurn::ROLE_CANDIDATE)->count();
    }

    /**
     * Разговор окончен — куда дальше.
     *
     * После первого прохода впереди тестовое задание. После пограничного
     * дораунда задание уже сдано и проверено, и ждать нечего: пересчитываем
     * решение с новыми ответами.
     */
    private function afterConversation(AiInterview $interview): void
    {
        $graded = $interview->testTasks()
            ->whereHas('submissions', fn ($q) => $q->whereNotNull('graded_at'))
            ->exists();

        // задание с нулевым весом в итог не входит — не выдаём его вовсе
        $noTest = $interview->config && ! $interview->config->needsTest();

        // задание составлялось заранее и не вышло — ждать нечего, решает человек
        $failed = $interview->fresh()->requires_review
            && ! $interview->testTasks()->where('kind', \App\Models\AiTestTask::KIND_MAIN)->exists();

        $noTest = $noTest || $failed;

        if ($graded || $noTest) {
            $interview->update(['stage' => 'decision']);
            MakeHiringDecision::dispatch($interview->id);

            return;
        }

        $interview->update(['stage' => 'test']);
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
     * Почему разговор сейчас невозможен — или null, если возможен.
     *
     * Одно место для двух адресов: и «начать», и «ответить» приходят через
     * fetch, и оба должны отвечать одинаково, иначе одна из дверей однажды
     * окажется открытой.
     */
    private function notReadyToTalk(AiInterview $interview): ?string
    {
        if (! $interview->consented()) {
            return __('Сначала нужно дать согласие на ИИ-собеседование.');
        }

        if ($interview->stage !== 'interview') {
            return __('Собеседование уже завершено.');
        }

        return null;
    }

    /**
     * Кандидат пришёл не на ту стадию — отправляем туда, где он нужен.
     */
    private function guardStage(AiInterview $interview)
    {
        if (! $interview->consented()) {
            return redirect()->route('applicant.ai.interview', $interview);
        }

        // разговор пройден — дальше тестовое задание
        if (in_array($interview->stage, ['test', 'decision', 'done'], true)) {
            return redirect()->route('applicant.ai.task', $interview);
        }

        if ($interview->stage !== 'interview') {
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
