<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Resume;
use App\Models\ResumeDraft;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\ResumeAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Диалоговый помощник по составлению резюме.
 *
 * Черновик всегда достаётся по текущему пользователю: идентификатор из
 * запроса не принимается, поэтому чужой разговор открыть нельзя в принципе.
 */
class ResumeAssistantController extends Controller
{
    /** Приветствие показываем без обращения к модели: страница открывается мгновенно. */
    private const GREETING = 'Здравствуйте! Я помогу собрать резюме за несколько минут — просто отвечайте своими словами, а я задам уточняющие вопросы и соберу всё в готовый документ справа. Начнём с главного: как вас зовут и кем вы хотите работать?';

    public function __construct(private readonly ResumeAssistant $assistant)
    {
    }

    /**
     * Страница разговора.
     */
    public function index(?ResumeDraft $draft = null)
    {
        $user = auth()->user();
        $applicant = $user->applicant;

        if (! $applicant) {
            return redirect()->route('public.applicants.create')
                ->with('error', 'Сначала заполните анкету соискателя.');
        }

        // без явного адреса продолжаем последний незаконченный разговор,
        // а если такого нет — начинаем новый
        $draft = $draft ? $this->mine($draft) : $this->latest($applicant->id);

        return view('applicant.pages.resumes.assistant', [
            'user' => $user,
            'applicant' => $applicant,
            'draft' => $draft,
            'messages' => $draft->messages ?? [],
            'resume' => $draft->data ?? [],
            'final' => $draft->final,
            'progress' => $draft->progress(),
            // все разговоры соискателя: список слева от ленты, как в чате
            'conversations' => ResumeDraft::where('applicant_id', $applicant->id)
                ->latest('updated_at')->get(),
            // без ключа страница честно говорит, что помощник выключен,
            // и уводит на обычную форму вместо неработающего поля ввода
            'enabled' => $this->assistant->available(),
        ]);
    }

    /**
     * Один ход диалога.
     */
    public function message(Request $request, ResumeDraft $draft): JsonResponse
    {
        $validated = $request->validate(['message' => 'required|string|max:2000']);

        $draft = $this->mine($draft);

        try {
            $answer = $this->assistant->next($draft, $validated['message']);
        } catch (AiUnavailableException $e) {
            // реплику пользователя намеренно не сохраняем: после ошибки текст
            // остаётся в поле ввода, и повтор не задваивает историю
            return $this->stumble($e);
        }

        $draft->remember('user', $validated['message']);
        $draft->remember('model', $answer['reply']);
        $draft->data = $answer['resume'];
        $draft->stage = $answer['stage'];
        $draft->save();

        return response()->json([
            'reply' => $answer['reply'],
            'resume' => $draft->data,
            'stage' => $draft->stage,
            'done' => $answer['done'],
            'progress' => $draft->progress(),
        ]);
    }

    /**
     * Финальная версия: причёсанный текст и поля будущего резюме.
     */
    public function finalize(ResumeDraft $draft): JsonResponse
    {
        $draft = $this->mine($draft);

        if (blank($draft->data)) {
            return response()->json([
                'error' => 'Сначала расскажите о себе — собирать пока нечего.',
            ], 422);
        }

        try {
            $final = $this->assistant->finalize($draft);
        } catch (AiUnavailableException $e) {
            return $this->stumble($e);
        }

        $draft->final = $final;
        $draft->save();

        return response()->json(['final' => $final]);
    }

    /**
     * Сохранение готового резюме. Правила те же, что у обычной формы:
     * помощник не должен создавать записи, которые руками создать нельзя.
     */
    public function save(Request $request, ResumeDraft $draft)
    {
        $draft = $this->mine($draft);

        $validated = $request->validate(ResumeController::rules() + [
            'documents' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
        ]);

        if ($request->hasFile('documents')) {
            // приватный диск, как и у обычной формы: документ отдаётся
            // только через SecureFileController, а не прямой ссылкой
            $validated['documents'] = $request->file('documents')->store('resumes', 'local');
        } else {
            unset($validated['documents']);
        }

        $resume = Resume::create($validated + ['applicant_id' => $draft->applicant_id]);

        // Разговор не стирается, а помечается завершённым: он остаётся в списке,
        // к нему можно вернуться и посмотреть, из чего выросло резюме. При этом
        // следующий заход откроет новый разговор, а не уже опубликованный.
        $draft->resume_id = $resume->id;
        $draft->save();

        return redirect()->route('applicant.resume.index')
            ->with('status', 'Резюме опубликовано.');
    }

    /**
     * Начать новый разговор. Прежний остаётся в списке.
     */
    public function start()
    {
        $applicant = auth()->user()->applicant;

        abort_unless($applicant, 403);

        return redirect()->route('applicant.resume.assistant.open', $this->create($applicant->id));
    }

    /**
     * Сбой помощника для браузера. Когда провайдер сказал, через сколько
     * повторить, отдаём это число: интерфейс покажет обратный отсчёт и
     * повторит сам, вместо того чтобы упереться в ошибку.
     */
    private function stumble(AiUnavailableException $e): JsonResponse
    {
        return response()->json(array_filter([
            'error' => $e->getMessage(),
            'retry_after' => $e->retryAfter,
        ]), 503);
    }

    /**
     * Удалить разговор. Опубликованное из него резюме не трогаем: оно живёт
     * своей жизнью, а переписка — лишь черновик, приведший к нему.
     */
    public function destroy(ResumeDraft $draft)
    {
        $this->mine($draft)->delete();

        return redirect()->route('applicant.resume.assistant')
            ->with('status', 'Разговор удалён.');
    }

    /**
     * Разговор принадлежит текущему соискателю — иначе чужой не открыть.
     */
    private function mine(ResumeDraft $draft): ResumeDraft
    {
        $applicant = auth()->user()->applicant;

        abort_if(! $applicant || $draft->applicant_id !== $applicant->id, 403);

        return $draft;
    }

    /**
     * Последний незаконченный разговор соискателя, иначе новый.
     */
    private function latest(int $applicantId): ResumeDraft
    {
        return ResumeDraft::where('applicant_id', $applicantId)
            ->unfinished()
            ->latest('updated_at')
            ->first() ?? $this->create($applicantId);
    }

    /**
     * Новый разговор с приветствием.
     */
    private function create(int $applicantId): ResumeDraft
    {
        return ResumeDraft::create([
            'applicant_id' => $applicantId,
            'messages' => [['role' => 'model', 'text' => self::GREETING]],
            'data' => [],
            'stage' => 'identity',
        ]);
    }
}
