<?php

namespace App\Jobs;

use App\Models\AiInterview;
use App\Models\AiInterviewCriterion;
use App\Models\AiRequirementCheck;
use App\Models\UserNotification;
use App\Services\Ai\AiInvalidAnswerException;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\DocumentAuditor;
use App\Services\Scoring\RequirementScore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Разбор резюме и сверка требований — итог работы с документами.
 *
 * Идёт последней в цепочке: сначала проверяются документы, потом их вердикты
 * участвуют в сверке. Здесь же считается балл за документы и рождаются
 * уточняющие вопросы, с которых начнётся собеседование.
 */
class BuildRequirementMatrix implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public readonly int $interviewId)
    {
    }

    public function handle(DocumentAuditor $auditor): void
    {
        $interview = AiInterview::with('documents')->find($this->interviewId);

        if (! $interview) {
            return;
        }

        $criteria = AiInterviewCriterion::where('vacancy_id', $interview->vacancy_id)
            ->confirmed()->ordered()->get();

        if ($criteria->isEmpty()) {
            // работодатель успел снять все критерии — сверять не с чем,
            // и решать за него система не станет
            $this->giveUp($interview, 'У вакансии не осталось подтверждённых критериев.');

            return;
        }

        $resume = $interview->applicant?->resume()->latest('id')->first();

        if (! $resume) {
            $this->giveUp($interview, 'У кандидата нет резюме.');

            return;
        }

        try {
            // Резюме разбираем здесь, а не в отдельной задаче: сверка без него
            // невозможна, и разносить их значило бы плодить состояния, в которых
            // одно есть, а другого нет.
            $parsed = $interview->analysis['resume'] ?? null;

            if (! $parsed) {
                $parsed = $auditor->parseResume($resume);
            }

            $matrix = $auditor->buildMatrix($criteria, $parsed, $this->findings($interview));
        } catch (AiInvalidAnswerException $e) {
            /*
             * Дважды негодный ответ на сверке — это уже не мелочь: без матрицы
             * требований решение принимать нельзя. Собеседование уходит на
             * ручную проверку, и кандидату не отправляется ни отказ, ни
             * приглашение.
             */
            $this->giveUp($interview, 'Модель дважды ответила негодно: '.$e->summary());

            return;
        } catch (AiUnavailableException $e) {
            if ($this->attempts() >= $this->tries) {
                $this->giveUp($interview, $e->getMessage());

                return;
            }

            throw $e;
        }

        $this->saveChecks($interview, $criteria, $matrix['checks']);

        $checks = $interview->requirementChecks()->get();

        $interview->update([
            'analysis' => [
                'resume' => $parsed,
                'inconsistencies' => $matrix['inconsistencies'],
                // вопросы лягут в основу собеседования: с них начнётся разговор
                'questions' => $matrix['questions'],
                'enough_data' => $matrix['enough_data'] && ($parsed['enough_data'] ?? false),
            ],
            'analysis_status' => 'ready',
            'analysed_at' => now(),
            'documents_score' => RequirementScore::compute($checks, $criteria),
            /*
             * Стадию двигает разбор, а не кнопка «продолжить».
             *
             * Кандидат нажимает кнопку, но собеседование не может начаться,
             * пока не готовы вопросы: они рождаются здесь, из несостыковок и
             * частичных совпадений. Если бы стадию переводила кнопка, человек
             * попадал бы в пустой разговор, которому не о чем спрашивать.
             *
             * Назад по стадиям не откатываем: пересчёт документов у кандидата,
             * уже отвечающего на вопросы, не должен возвращать его к началу.
             */
            ...(in_array($interview->stage, ['consent', 'documents'], true)
                ? ['stage' => 'interview']
                : []),
        ]);

        $this->notify($interview);
    }

    /**
     * Статусы требований: переписываем начисто.
     *
     * Прежние проверки удаляем, а не обновляем: набор критериев мог измениться,
     * и осиротевшая строка от снятого требования продолжала бы влиять на отчёт.
     *
     * @param  array<int,array{key:string,status:string,evidence:string,confidence:int}>  $checks
     */
    private function saveChecks(AiInterview $interview, $criteria, array $checks): void
    {
        // Проверки, закрытые на собеседовании, не трогаем: подтверждённое
        // словами кандидата нельзя терять при пересчёте документов.
        $interview->requirementChecks()->where('origin', 'documents')->delete();

        $byKey = collect($criteria)->keyBy('key');

        /*
         * Требование, о котором в ответе не сказано ничего, становится
         * ненайденным.
         *
         * Гарантия живёт здесь, а не только в реализации разборщика: молчание
         * не может означать «есть», иначе пропущенный критерий открывал бы
         * ворота решения сам собой. Задача отвечает за то, чтобы у каждого
         * подтверждённого критерия была строка, какой бы разборщик ни стоял в
         * контейнере.
         */
        $answered = collect($checks)->pluck('key')->all();

        foreach ($byKey->keys()->diff($answered) as $silent) {
            $checks[] = ['key' => $silent, 'status' => 'missing', 'evidence' => '', 'confidence' => 0];
        }

        foreach ($checks as $check) {
            $criterion = $byKey->get($check['key']);

            if (! $criterion) {
                continue;
            }

            // требование уже закрыто устно — документы его не понижают
            $confirmed = $interview->requirementChecks()
                ->where('criterion_id', $criterion->id)
                ->where('status', 'confirmed_in_interview')
                ->exists();

            if ($confirmed) {
                continue;
            }

            AiRequirementCheck::create([
                'ai_interview_id' => $interview->id,
                'criterion_id' => $criterion->id,
                'requirement' => $criterion->label,
                'kind' => $criterion->kind,
                'status' => $check['status'],
                'evidence_quote' => $check['evidence'] ?: null,
                'confidence' => $check['confidence'],
                'origin' => 'documents',
            ]);
        }
    }

    /**
     * Вердикты по документам для сверки.
     *
     * @return array<int,array>
     */
    private function findings(AiInterview $interview): array
    {
        return $interview->documents
            ->filter(fn ($document) => $document->isAnalysed() && filled($document->analysis))
            ->map(fn ($document) => $document->analysis)
            ->values()
            ->all();
    }

    /**
     * Разбор не состоялся — дальше решает человек.
     */
    private function giveUp(AiInterview $interview, string $reason): void
    {
        $interview->update([
            'analysis_status' => 'failed',
            'analysed_at' => now(),
            'requires_review' => true,
            'outcome' => 'manual_review',
        ]);

        Log::warning('Сверка требований не удалась', [
            'interview' => $interview->id,
            'reason' => $reason,
        ]);

        // Работодателю сообщаем: кандидат ждёт, а система не берётся решать.
        UserNotification::deliver(
            $interview->vacancy?->employer?->user_id,
            'interview',
            'Кандидат ждёт ручной проверки',
            'По вакансии «'.($interview->vacancy?->title ?? '—').'» не удалось разобрать документы: '.$reason,
            route('employer.ai.show', $interview->vacancy_id),
        );
    }

    /**
     * Кандидату — что разбор закончен и можно продолжать.
     */
    private function notify(AiInterview $interview): void
    {
        UserNotification::deliver(
            $interview->applicant?->user_id,
            'interview',
            'Документы разобраны',
            'Собеседование по вакансии «'.($interview->vacancy?->title ?? '—').'» готово начаться.',
            route('applicant.ai.interview', $interview),
        );
    }

    public function failed(?\Throwable $e): void
    {
        $interview = AiInterview::find($this->interviewId);

        if ($interview && $interview->analysis_status !== 'ready') {
            $this->giveUp($interview, 'Разбор не удался после нескольких попыток.');
        }
    }
}
