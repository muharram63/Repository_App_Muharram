<?php

namespace App\Jobs;

use App\Models\AiCandidateDocument;
use App\Services\Ai\AiInvalidAnswerException;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\DocumentAuditor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Проверка одного документа кандидата.
 *
 * Отдельная задача на документ, а не одна на всё: дипломов может быть десяток,
 * и сбой на четвёртом не должен отменять три уже проверенных. Плюс каждый
 * документ кэшируется по отпечатку — повторная проверка того же файла с теми же
 * требованиями бесплатна.
 */
class AnalyzeCandidateDocument implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    /**
     * Растущая пауза: бесплатный ключ отдаёт 429 при двадцати запросах в минуту,
     * а проверка документа — запрос не из дешёвых.
     */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $documentId)
    {
    }

    public function handle(DocumentAuditor $auditor): void
    {
        $document = AiCandidateDocument::with('interview')->find($this->documentId);

        // документ могли удалить, пока задача ждала очереди
        if (! $document || ! $document->interview) {
            return;
        }

        // прочитать не удалось ещё при загрузке — проверять нечего
        if ($document->status === 'unreadable') {
            return;
        }

        $interview = $document->interview;
        $requirements = $this->requirementKeys($interview);

        // тот же файл и те же требования — прежний вердикт ещё годен
        if ($document->isFresh($requirements)) {
            return;
        }

        $parsed = $interview->analysis['resume'] ?? [];

        try {
            $finding = $auditor->auditDocument($document, $parsed);
        } catch (AiInvalidAnswerException $e) {
            /*
             * Модель дважды ответила негодно. Документ помечаем сбойным, но
             * собеседование не останавливаем: непроверенный диплом — это минус
             * одно доказательство, а не причина отказать человеку. Решение
             * увидит, что документ не разобран.
             */
            $document->update([
                'status' => 'failed',
                'failure_reason' => 'Разбор не удался: '.$e->summary(),
                'analysed_at' => now(),
            ]);

            Log::warning('Документ не разобран', ['document' => $document->id]);

            return;
        } catch (AiUnavailableException $e) {
            // сервис недоступен — это повод повторить, а не сдаться
            if ($this->attempts() >= $this->tries) {
                $document->update([
                    'status' => 'failed',
                    'failure_reason' => $e->getMessage(),
                    'analysed_at' => now(),
                ]);

                return;
            }

            throw $e;
        }

        $document->update([
            'analysis' => $finding,
            'status' => 'analysed',
            'source_hash' => AiCandidateDocument::hash($document->path, $requirements),
            'analysed_at' => now(),
            // Прочитать не смогла сама модель — это уже не «сбой разбора».
            // Кандидату честнее сказать, что файл нечитаемый.
            ...($finding['readable'] ? [] : [
                'status' => 'unreadable',
                'failure_reason' => 'Модель не смогла прочитать документ. '
                    .'Попробуйте загрузить более чёткий скан или PDF.',
            ]),
        ]);
    }

    /**
     * Ключи подтверждённых требований — часть отпечатка: изменился набор
     * требований, значит прежний вердикт по документу устарел.
     *
     * @return array<int,string>
     */
    private function requirementKeys($interview): array
    {
        return \App\Models\AiInterviewCriterion::where('vacancy_id', $interview->vacancy_id)
            ->confirmed()->orderBy('key')->pluck('key')->all();
    }

    public function failed(?\Throwable $e): void
    {
        AiCandidateDocument::whereKey($this->documentId)->update([
            'status' => 'failed',
            'failure_reason' => 'Разбор документа не удался.',
        ]);
    }
}
