<?php

namespace App\Services\Trainer;

use App\Models\AiInterview;
use App\Models\AiInterviewTurn;
use App\Models\AiTestTask;
use App\Models\Trainer\TrainerSession;
use App\Services\Ai\AiJournal;
use Illuminate\Support\Str;

/**
 * Разбор тренировки для самого кандидата.
 *
 * Основа разбора собирается кодом из того, что движок уже оценил: балл и
 * комментарий проверки по каждому ответу, тест с ключом, задания с рубрикой,
 * качеством кода и эталонным решением. Такой разбор есть всегда — даже без
 * модели. Модель поверх него пишет дружелюбный текст: что получилось, что
 * подтянуть, как было бы правильно и что делать дальше. Баллы модель не
 * меняет: цифры берутся только из оценок движка.
 *
 * В отличие от настоящего собеседования, здесь кандидату показывается всё:
 * баллы, верные ответы теста и эталон задания. Это его тренировка, и учиться
 * без «как правильно» нельзя.
 */
class TrainerCoach
{
    public const MAX_QUESTIONS = 10;

    public function __construct(private readonly AiJournal $journal)
    {
    }

    /**
     * Разбор целиком: основа от кода и, если модель доступна, её текст.
     */
    public function build(TrainerSession $session): array
    {
        $feedback = $this->basic($session);

        if (! $this->journal->available()) {
            return $feedback;
        }

        try {
            return $this->merge($feedback, $this->ask($session, $feedback));
        } catch (\Throwable $e) {
            // без модели разбор беднее, но он есть: кандидат не остаётся ни с чем
            report($e);

            return $feedback;
        }
    }

    /**
     * Разбор из оценок движка — без обращения к модели.
     */
    public function basic(TrainerSession $session): array
    {
        $interview = $session->interview()->with('config')->first();
        $locale = $interview?->config?->candidateLocale() ?? 'ru';

        $feedback = [
            'source' => 'basic',
            'model' => null,
            'locale' => $locale,
            'scores' => [
                'total' => $interview?->total_score,
                'interview' => $interview?->interview_score,
                'test' => $interview?->test_score,
            ],
            'summary' => '',
            'strengths' => [],
            'improvements' => [],
            'questions' => $interview ? $this->questions($interview) : [],
            'quiz' => null,
            'tasks' => [],
            'next_steps' => [],
        ];

        if ($interview) {
            [$feedback['quiz'], $feedback['tasks']] = $this->test($interview);
        }

        $feedback['summary'] = $this->summaryFromScores($feedback, $locale);
        [$feedback['strengths'], $feedback['improvements']] = $this->splitByScore($feedback, $locale);

        return $feedback;
    }

    // ==================== данные ====================

    /** Вопрос Анны, ответ кандидата, балл и комментарий проверки. */
    private function questions(AiInterview $interview): array
    {
        $turns = $interview->turns()->orderBy('position')->get();
        $rows = [];
        $question = null;

        foreach ($turns as $turn) {
            if ($turn->role === AiInterviewTurn::ROLE_AI) {
                $question = $turn->isClosing() ? null : $turn;

                continue;
            }

            if (! $turn->isFromCandidate() || ! $question || count($rows) >= self::MAX_QUESTIONS) {
                continue;
            }

            $rows[] = [
                'question' => Str::limit((string) $question->text, 600),
                'answer' => Str::limit((string) $turn->text, 600),
                'score' => $turn->score,
                'max' => $turn->max_score ?: 10,
                'comment' => Str::limit((string) $turn->rationale, 500),
                'better' => null,
            ];

            // на один вопрос — один ответ; уточнения считаются отдельно
            $question = null;
        }

        return $rows;
    }

    /** Тест: ошибки с верным ответом. Задания: балл, рубрика, качество, эталон. */
    private function test(AiInterview $interview): array
    {
        $task = $interview->testTasks()->where('kind', AiTestTask::KIND_MAIN)->latest('id')->first();
        $submission = $task?->submission()->first();

        if (! $task || ! $submission) {
            return [null, []];
        }

        $details = $submission->details ?? [];
        $answers = $submission->answers ?? [];
        $quiz = null;

        if (isset($details['quiz'])) {
            $mistakes = [];

            foreach ($details['quiz']['items'] ?? [] as $i => $item) {
                if (! empty($item['correct']) || ! isset($task->quiz[$i])) {
                    continue;
                }

                $options = array_values($task->quiz[$i]['options'] ?? []);
                $given = $answers['quiz'][$i] ?? null;

                $mistakes[] = [
                    'question' => (string) ($task->quiz[$i]['question'] ?? ''),
                    'yours' => is_numeric($given) ? ($options[(int) $given] ?? null) : (is_string($given) ? $given : null),
                    'right' => $this->option($options, $task->quiz[$i]['answer'] ?? null),
                ];
            }

            $quiz = [
                'correct' => (int) ($details['quiz']['correct'] ?? 0),
                'total' => (int) ($details['quiz']['total'] ?? 0),
                'mistakes' => array_slice($mistakes, 0, 10),
            ];
        }

        $tasks = [];

        foreach ($task->practical() as $i => $item) {
            $part = $details['parts'][$i] ?? null;

            $tasks[] = [
                'statement' => Str::limit($item['statement'], 1500),
                'format' => $item['format'],
                'score' => $part['score'] ?? null,
                'summary' => (string) ($part['summary'] ?? ''),
                'review' => array_values($part['review'] ?? []),
                'quality' => is_array($part['quality'] ?? null) ? $part['quality'] : null,
                'executed' => (bool) ($part['executed'] ?? false),
                'reference' => Str::limit((string) ($item['reference_solution'] ?? ''), 4000),
                'better' => null,
            ];
        }

        return [$quiz, $tasks];
    }

    /** Верный вариант: ключ — номер варианта или сам текст. */
    private function option(array $options, mixed $answer): ?string
    {
        if (is_numeric($answer)) {
            return $options[(int) $answer] ?? null;
        }

        return is_string($answer) && $answer !== '' ? $answer : null;
    }

    private function summaryFromScores(array $feedback, string $locale): string
    {
        $total = $feedback['scores']['total'];

        if ($total === null) {
            return __('Тренировка завершена. Ниже — ответы и проверка по каждой части.', [], $locale);
        }

        $key = match (true) {
            $total >= 80 => 'Отличный результат: :total из 100. Вы уверенно отвечаете по теме — ниже детали и что ещё можно отточить.',
            $total >= 60 => 'Хороший результат: :total из 100. Основа есть — ниже видно, где теряются баллы и как их добрать.',
            $total >= 40 => 'Результат :total из 100. Часть тем даётся хорошо, а над некоторыми стоит поработать — ниже разобрано по шагам.',
            default => 'Результат :total из 100. Это тренировка — как раз для того, чтобы найти пробелы. Ниже разобрано, с чего начать.',
        };

        return __($key, ['total' => $total], $locale);
    }

    /** Сильное и слабое по баллам — пока нет текста модели. */
    private function splitByScore(array $feedback, string $locale): array
    {
        $strong = [];
        $weak = [];

        foreach ($feedback['questions'] as $i => $q) {
            if ($q['score'] === null) {
                continue;
            }

            $label = __('Вопрос :n', ['n' => $i + 1], $locale).': '.Str::limit($q['question'], 90);
            $share = $q['score'] / max(1, $q['max']);

            if ($share >= 0.7) {
                $strong[] = $label;
            } elseif ($share < 0.5) {
                $weak[] = $label;
            }
        }

        foreach ($feedback['tasks'] as $i => $task) {
            if ($task['score'] === null) {
                continue;
            }

            $label = __('Задание :n', ['n' => $i + 1], $locale);
            $task['score'] >= 70 ? $strong[] = $label : ($task['score'] < 50 ? $weak[] = $label : null);
        }

        return [array_slice($strong, 0, 5), array_slice($weak, 0, 5)];
    }

    // ==================== модель ====================

    private function ask(TrainerSession $session, array $feedback): array
    {
        $interview = $session->interview()->with('config')->first();

        $answer = $this->journal->askDetailed(
            purpose: 'trainer_feedback',
            system: $this->prompt($session, $interview),
            turns: [['role' => 'user', 'text' => $this->describe($feedback)]],
            schema: [
                'type' => 'object',
                'properties' => [
                    'summary' => ['type' => 'string'],
                    'strengths' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'improvements' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'questions' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'n' => ['type' => 'integer'],
                                'comment' => ['type' => 'string'],
                                'better_answer' => ['type' => 'string'],
                            ],
                            'required' => ['n', 'comment', 'better_answer'],
                        ],
                    ],
                    'tasks' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'n' => ['type' => 'integer'],
                                'how_to' => ['type' => 'string'],
                            ],
                            'required' => ['n', 'how_to'],
                        ],
                    ],
                    'next_steps' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['summary', 'strengths', 'improvements', 'questions', 'next_steps'],
            ],
            rules: [
                'summary' => 'required|string|min:20|max:1500',
                'strengths' => 'present|array|max:8',
                'improvements' => 'present|array|max:8',
                'questions' => 'present|array|max:'.self::MAX_QUESTIONS,
                'next_steps' => 'present|array|max:8',
            ],
            interview: $interview,
            options: AiJournal::options('chat'),
            maxTokens: 4000,
        );

        return $answer['data'] + ['_model' => $answer['model'] ?? null];
    }

    private function prompt(TrainerSession $session, ?AiInterview $interview): string
    {
        $language = $interview?->config?->languageName() ?? 'русском';
        $level = $session->levelLabel();

        return <<<PROMPT
        Ты — доброжелательный наставник. Кандидат прошёл ТРЕНИРОВОЧНОЕ собеседование
        «{$session->track_title}», уровень {$level}. Это не отбор: никто его не нанимает и не
        отказывает. Твоя задача — помочь ему подготовиться к настоящему собеседованию.

        Тебе даны вопросы, его ответы, баллы и комментарии проверки, тест и задания с эталоном.
        Напиши разбор:
        - summary: 2–4 предложения об общем впечатлении. Честно, но поддерживающе, на «вы».
        - strengths: что получилось — конкретно, со ссылкой на ответ или задание.
        - improvements: что подтянуть — конкретно, без общих слов вроде «учите больше».
        - questions: для КАЖДОГО вопроса (n — его номер из данных) — comment: что в ответе
          хорошо и чего не хватило; better_answer: как ответить сильнее, коротко, по сути,
          так, чтобы можно было сказать вслух на собеседовании.
        - tasks: для каждого задания (n — номер) — how_to: как решить правильно, шагами;
          опирайся на эталон, но объясняй, а не копируй его целиком.
        - next_steps: 3–5 практичных шагов, что сделать до следующей тренировки.

        ПРАВИЛА:
        - Баллы не пересчитывай и новых не придумывай: цифры уже есть в данных.
        - Не выдумывай фактов о кандидате, которых нет в ответах.
        - Не говори о найме, отказе, работодателе и вакансии — это тренировка.
        - Пиши на {$language} языке: на нём шла тренировка.
        PROMPT;
    }

    private function describe(array $feedback): string
    {
        $lines = ['ИТОГ: '.($feedback['scores']['total'] ?? '—').' из 100; разговор '
            .($feedback['scores']['interview'] ?? '—').', задание '.($feedback['scores']['test'] ?? '—').'.', ''];

        $lines[] = 'ВОПРОСЫ И ОТВЕТЫ';
        foreach ($feedback['questions'] as $i => $q) {
            $lines[] = ($i + 1).'. Вопрос: '.$q['question'];
            $lines[] = '   Ответ кандидата: '.($q['answer'] !== '' ? $q['answer'] : '(пусто)');
            $lines[] = '   Балл: '.($q['score'] ?? '—').' из '.$q['max'].'. Проверка: '.($q['comment'] ?: '—');
        }

        if ($feedback['quiz']) {
            $lines[] = '';
            $lines[] = 'ТЕСТ: верно '.$feedback['quiz']['correct'].' из '.$feedback['quiz']['total'].'.';
            foreach ($feedback['quiz']['mistakes'] as $m) {
                $lines[] = '- ошибка: «'.Str::limit($m['question'], 200).'» — выбран «'.($m['yours'] ?? '—')
                    .'», верно «'.($m['right'] ?? '—').'»';
            }
        }

        foreach ($feedback['tasks'] as $i => $t) {
            $lines[] = '';
            $lines[] = 'ЗАДАНИЕ '.($i + 1).': '.Str::limit($t['statement'], 800);
            $lines[] = 'Балл: '.($t['score'] ?? 'не проверено').'. Проверка: '.Str::limit($t['summary'], 400);
            foreach ($t['review'] as $r) {
                $lines[] = '- '.($r['passed'] ? '✓' : '✗').' '.$r['point'].($r['note'] ? ' — '.$r['note'] : '');
            }
            if ($t['quality']) {
                $lines[] = 'Качество кода: '.($t['quality']['score'] ?? '—').' из 100. '.($t['quality']['notes'] ?? '');
            }
            if ($t['reference'] !== '') {
                $lines[] = 'Эталон: '.Str::limit($t['reference'], 1500);
            }
        }

        return implode(PHP_EOL, $lines);
    }

    /** Текст модели — поверх основы. Цифры остаются от движка. */
    private function merge(array $feedback, array $answer): array
    {
        $clean = fn ($items) => collect(is_array($items) ? $items : [])
            ->filter(fn ($s) => is_string($s) && trim($s) !== '')
            ->map(fn ($s) => Str::limit(trim($s), 500))
            ->take(8)->values()->all();

        $feedback['source'] = 'ai';
        $feedback['model'] = $answer['_model'] ?? null;
        $feedback['summary'] = Str::limit(trim((string) $answer['summary']), 1500);
        $feedback['strengths'] = $clean($answer['strengths']) ?: $feedback['strengths'];
        $feedback['improvements'] = $clean($answer['improvements']) ?: $feedback['improvements'];
        $feedback['next_steps'] = $clean($answer['next_steps']);

        foreach ($answer['questions'] ?? [] as $row) {
            $i = (int) ($row['n'] ?? 0) - 1;

            if (isset($feedback['questions'][$i])) {
                $feedback['questions'][$i]['comment'] = Str::limit(trim((string) ($row['comment'] ?? '')), 800)
                    ?: $feedback['questions'][$i]['comment'];
                $feedback['questions'][$i]['better'] = Str::limit(trim((string) ($row['better_answer'] ?? '')), 1200) ?: null;
            }
        }

        foreach ($answer['tasks'] ?? [] as $row) {
            $i = (int) ($row['n'] ?? 0) - 1;

            if (isset($feedback['tasks'][$i])) {
                $feedback['tasks'][$i]['better'] = Str::limit(trim((string) ($row['how_to'] ?? '')), 2500) ?: null;
            }
        }

        return $feedback;
    }
}
