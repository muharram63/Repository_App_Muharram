<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\AiDecision;
use App\Models\AiInteraction;
use App\Models\AiInterview;
use App\Models\AiInterviewCriterion;
use App\Models\Employer;
use App\Models\UserNotification;
use App\Models\Vacancy;
use App\Services\Documents\TextExtractor;
use App\Services\Scoring\DecisionEngine;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
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

        $decision = $interview->latestDecision;

        if (! $decision) {
            return back()->with('error', 'Решения ещё нет — переопределять нечего.');
        }

        /*
         * Переопределение дописывается к решению, а не заменяет его. В отчёте
         * должно быть видно и что посчитал ИИ, и что решил человек: иначе через
         * месяц никто не вспомнит, почему исход не совпадает с баллом.
         */
        $decision->update([
            'overridden_by' => auth()->id(),
            'overridden_at' => now(),
            'override_outcome' => $validated['outcome'],
            'override_note' => $validated['note'] ?? null,
        ]);

        $interview->update([
            'outcome' => $validated['outcome'],
            // решение принято человеком — ждать больше нечего
            'requires_review' => false,
            'decided_at' => $interview->decided_at ?? now(),
            'stage' => 'done',
        ]);

        // статус отклика ставим всегда: это прямое распоряжение работодателя,
        // а не решение автоматики
        $interview->response?->update([
            'status' => $validated['outcome'] === 'passed' ? 'accepted' : 'rejected',
        ]);

        UserNotification::deliver(
            $interview->applicant?->user_id,
            'response_status',
            $validated['outcome'] === 'passed' ? 'Вы прошли отбор' : 'Ответ по вашей кандидатуре',
            'Решение по вакансии «'.$vacancy->title.'» принял работодатель.',
            route('applicant.ai.result', $interview),
        );

        return back()->with('status', 'Решение изменено: '.$decision->fresh()->outcomeLabel().'.');
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

        self::erase($interview);

        return redirect()->route('employer.ai.candidates', $vacancy)
            ->with('status', 'Данные кандидата по этому собеседованию удалены.');
    }

    /**
     * Стереть собеседование вместе с файлами.
     *
     * Публичный и статический, потому что то же самое делает кандидат из
     * своего кабинета: право на удаление принадлежит ему, а работодатель лишь
     * исполняет просьбу.
     */
    public static function erase(AiInterview $interview): void
    {
        foreach ($interview->documents as $document) {
            Storage::disk(TextExtractor::DISK)->delete($document->path);
        }

        // озвучки реплик лежат в общей фонотеке по отпечатку текста и могут
        // быть заняты другим собеседованием — их не трогаем
        $interview->delete();
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
                    ->with('error', 'Сначала заполните анкету работодателя.')
            );
        }

        return $employer;
    }
}
