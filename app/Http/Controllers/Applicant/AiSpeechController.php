<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\AiInterviewTurn;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\GeminiSpeech;
use App\Services\Ai\SpeechLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Голос ИИ-собеседования.
 *
 * Два маршрута с разным назначением. turn() отдаёт готовую озвучку реплики и
 * проверяет, что собеседование принадлежит этому соискателю. preview() нужен
 * странице проверки аватара: она озвучивает произвольный текст, поэтому он
 * ограничен по частоте и длине — иначе превратился бы в бесплатный синтезатор
 * речи за чужой счёт.
 */
class AiSpeechController extends Controller
{
    public function __construct(private readonly SpeechLibrary $library)
    {
    }

    /**
     * Страница проверки аватара.
     *
     * Существует потому, что страницы собеседования ещё нет: компонент нужно
     * видеть и слышать до того, как появится диалог. Заодно остаётся полезной
     * и после — на ней видно, что умеет конкретный браузер, и почему в нём
     * может не быть голоса.
     */
    public function page()
    {
        return view('applicant.pages.ai-interview.avatar-preview', [
            'user' => auth()->user(),
            'applicant' => auth()->user()->applicant,
            'ttsAvailable' => $this->library->available(),
            'sample' => 'Расскажите, пожалуйста, о задаче, которой вы сами довольны: '
                .'что нужно было сделать, что вы сделали и что изменилось после этого?',
        ]);
    }

    /**
     * Озвучка реплики собеседования.
     */
    public function turn(AiInterviewTurn $turn)
    {
        $this->authorizeTurn($turn);

        abort_unless($turn->speechReady(), 404);

        $path = $turn->speech_path;

        // файл мог не дожить до запроса: чистка диска, перенос, ручное удаление.
        // Для страницы это не ошибка — она прочитает вопрос голосом браузера.
        abort_unless(Storage::disk(SpeechLibrary::DISK)->exists($path), 404);

        return Storage::disk(SpeechLibrary::DISK)->response($path, 'question.wav', [
            'Content-Type' => 'audio/wav',
            // озвучка одного и того же вопроса не меняется — пусть браузер
            // держит её у себя и не просит повторно при каждом повторе
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Состояние озвучки: страница спрашивает, дождалась ли она звука.
     *
     * Отдельный маршрут, а не заголовок к аудио: пока идёт синтез, отдавать
     * нечего, а аватар должен знать, ждать ему или читать самому.
     */
    public function status(AiInterviewTurn $turn): JsonResponse
    {
        $this->authorizeTurn($turn);

        return response()->json([
            'status' => $turn->speech_status,
            'ms' => $turn->speech_ms,
            'url' => $turn->speechReady()
                ? route('applicant.ai.speech.turn', $turn)
                : null,
        ]);
    }

    /**
     * Озвучка произвольного текста для страницы проверки аватара.
     *
     * Возвращает сам WAV, а не ссылку: файл кэшируется на диске, но отдавать
     * его отдельным адресом означало бы открыть маршрут, у которого нет
     * владельца и, значит, некому проверять права.
     */
    public function preview(Request $request)
    {
        /*
         * Проверяем вручную и отвечаем JSON сами.
         *
         * $request->validate() здесь не годится: в bootstrap/app.php стоит
         * shouldRenderJsonWhen(fn ($r) => $r->is('api/*')), поэтому на обычном
         * маршруте ошибка валидации уходит редиректом с ошибками в сессии.
         * Страница вызывает этот адрес через fetch — редирект она разобрать
         * не сможет и покажет «синтез недоступен» вместо «текст пуст».
         */
        $validator = Validator::make($request->all(), [
            'text' => 'required|string|max:'.GeminiSpeech::MAX_CHARS,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first('text'),
            ], 422);
        }

        $validated = $validator->validated();

        if (! $this->library->available()) {
            return response()->json([
                'error' => 'Голосовой синтез выключен: проверьте GEMINI_API_KEY и GEMINI_TTS_ENABLED.',
                'fallback' => 'browser',
            ], 503);
        }

        try {
            $made = $this->library->make($validated['text']);
        } catch (AiUnavailableException $e) {
            // fallback говорит странице: это не тупик, читай голосом браузера
            return response()->json(array_filter([
                'error' => $e->getMessage(),
                'retry_after' => $e->retryAfter,
                'fallback' => 'browser',
            ]), 503);
        }

        return Storage::disk(SpeechLibrary::DISK)->response($made['path'], 'preview.wav', [
            'Content-Type' => 'audio/wav',
            // по этим заголовкам страница показывает, попала ли она в кэш:
            // на проверке аватара это единственный способ увидеть разницу
            'X-Speech-Cached' => $made['cached'] ? '1' : '0',
            'X-Speech-Ms' => (string) $made['ms'],
        ]);
    }

    /**
     * Реплика принадлежит собеседованию текущего соискателя.
     *
     * Проверка та же, что у документов резюме: чужую озвучку не получить, даже
     * зная её адрес — иначе по ней можно было бы собирать вопросы вакансий.
     */
    private function authorizeTurn(AiInterviewTurn $turn): void
    {
        $applicant = auth()->user()->applicant;

        abort_if(! $applicant, 403);
        abort_if($turn->interview?->applicant_id !== $applicant->id, 403);
    }
}
