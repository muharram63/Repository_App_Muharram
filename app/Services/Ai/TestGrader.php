<?php

namespace App\Services\Ai;

use App\Models\AiInterview;
use App\Models\AiTestSubmission;
use App\Models\AiTestTask;
use Illuminate\Support\Facades\Log;

/**
 * Проверка тестовой части: тест с вариантами ответа и несколько практических
 * заданий.
 *
 * Тест проверяет код — верный ответ известен, модель тут не нужна. Каждое
 * практическое задание проверяет модель, отдельным вызовом. Работа идёт
 * шагами: один вызов модели — один шаг, и прогресс сохраняется после каждого.
 * Так проверку может вести и очередь, и сама страница кандидата, и ни один
 * запрос не упирается в лимит времени PHP.
 */
class TestGrader
{
    public function __construct(private readonly TaskExaminer $examiner)
    {
    }

    /**
     * Сделать следующий шаг проверки.
     *
     * @return string graded — всё проверено; progress — шаг сделан, есть ещё;
     *                failed — проверить не вышло, решает человек
     *
     * @throws AiUnavailableException модель занята — шаг можно повторить
     */
    public function step(AiTestSubmission $submission): string
    {
        if ($submission->isGraded()) {
            return 'graded';
        }

        $task = $submission->task;
        $details = $submission->details ?? [];
        $answers = $submission->answers ?? [];

        // проверку уже передали человеку — не повторяем её по кругу: каждый
        // повтор тратил лимит модели и не пускал собеседование к решению
        if (filled($details['handed_over'] ?? null)) {
            return 'failed';
        }

        // тест — сразу и без модели
        if ($task->hasQuiz() && ! isset($details['quiz'])) {
            $details['quiz'] = $this->scoreQuiz($task, $answers['quiz'] ?? []);
            $submission->update(['details' => $details]);
        }

        $practical = $task->practical();
        $done = $details['parts'] ?? [];

        foreach ($practical as $index => $item) {
            if (isset($done[$index])) {
                continue;
            }

            $solution = $index === 0
                ? (string) $submission->content
                : (string) ($answers['parts'][$index - 1] ?? '');

            // пустое решение не на что проверять — ноль без вызова модели
            if (trim($solution) === '') {
                $done[$index] = ['score' => 0, 'summary' => 'Решение не дано.', 'review' => [], 'execution' => [], 'executed' => false];
            } else {
                try {
                    $verdict = $this->examiner->grade($task, $solution, $index);
                } catch (AiInvalidAnswerException $e) {
                    $this->handOver($submission, 'Модель дважды ответила негодно: '.$e->summary());

                    return 'failed';
                }

                $done[$index] = [
                    'score' => (int) $verdict['score'],
                    'summary' => (string) ($verdict['summary'] ?? ''),
                    'review' => $verdict['review'] ?? [],
                    'execution' => $verdict['execution'] ?? [],
                    'executed' => (bool) ($verdict['executed'] ?? false),
                    'model' => $verdict['model'] ?? null,
                ];
            }

            $details['parts'] = $done;
            $submission->update(['details' => $details]);

            // один вызов модели за шаг; если заданий больше — следующий шаг
            if (count($done) < count($practical)) {
                return 'progress';
            }
        }

        $this->finish($submission, $details);

        return 'graded';
    }

    /**
     * Проверить всё сразу — для очереди, где лимита запроса нет.
     *
     * @throws AiUnavailableException
     */
    public function gradeAll(AiTestSubmission $submission): string
    {
        do {
            $state = $this->step($submission->refresh());
        } while ($state === 'progress');

        return $state;
    }

    /**
     * @return array{correct:int,total:int,score:int,items:array}
     */
    private function scoreQuiz(AiTestTask $task, array $chosen): array
    {
        $items = [];
        $correct = 0;

        foreach ($task->quiz ?? [] as $i => $q) {
            $pick = isset($chosen[$i]) && $chosen[$i] !== '' && $chosen[$i] !== null ? (int) $chosen[$i] : null;
            $ok = $pick !== null && $pick === (int) $q['answer'];
            $correct += $ok ? 1 : 0;
            $items[] = ['chosen' => $pick, 'answer' => (int) $q['answer'], 'correct' => $ok];
        }

        $total = count($items);

        return [
            'correct' => $correct,
            'total' => $total,
            'score' => $total ? (int) round($correct / $total * 100) : 0,
            'items' => $items,
        ];
    }

    /**
     * Итог: тест весит AiTestTask::QUIZ_SHARE, остальное — практика.
     */
    private function finish(AiTestSubmission $submission, array $details): void
    {
        $parts = collect($details['parts'] ?? []);
        $practical = $parts->isEmpty() ? 0 : (int) round($parts->avg('score'));

        $score = isset($details['quiz'])
            ? (int) round($details['quiz']['score'] * AiTestTask::QUIZ_SHARE + $practical * (1 - AiTestTask::QUIZ_SHARE))
            : $practical;

        $first = $parts->first() ?? [];

        $submission->update([
            'score' => $score,
            // первое задание — в прежних колонках: по ним работает старый отчёт
            'review' => $first['review'] ?? [],
            'execution' => $first['execution'] ?? [],
            'details' => $details + ['practical_score' => $practical],
            'graded_at' => now(),
        ]);

        $interview = $submission->task->interview;

        $interview?->update([
            'test_score' => $score,
            ...($interview->stage === 'test' ? ['stage' => 'decision'] : []),
        ]);
    }

    /**
     * Проверить не вышло — решает человек, и решение идёт дальше.
     */
    public function handOver(AiTestSubmission $submission, string $reason): void
    {
        $interview = $submission->task?->interview;

        if (! $interview) {
            return;
        }

        $interview->update([
            'requires_review' => true,
            ...($interview->stage === 'test' ? ['stage' => 'decision'] : []),
        ]);

        // пометка на самой попытке: иначе отчёт и Анна продолжали бы
        // говорить «проверяется», хотя проверять её уже никто не будет
        $submission->update(['details' => ($submission->details ?? []) + ['handed_over' => $reason]]);

        Log::warning('Задание не проверено', [
            'interview' => $interview->id,
            'reason' => $reason,
        ]);
    }
}
