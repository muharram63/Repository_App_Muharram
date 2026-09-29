<?php

namespace App\Services\Ai;

use App\Models\AiInterview;
use App\Models\AiInterviewCriterion;
use App\Models\AiInterviewTurn;
use App\Services\Privacy\PiiRedactor;
use App\Services\Security\InjectionGuard;
use Illuminate\Support\Str;

/**
 * Собеседующий на Gemini.
 *
 * Разговор ведёт быстрая модель: реплику кандидат ждёт, глядя в экран, и четыре
 * секунды против двадцати здесь важнее глубины. Оценку ставит модель посильнее,
 * и ждать её никто не должен — она считается в очереди, а кандидату балл всё
 * равно не показывают.
 */
class GeminiInterviewer implements Interviewer
{
    /**
     * Имя, которым ассистент представляется.
     *
     * Человеческое имя рядом с честным «я ИИ» — не обман, а вежливость: с «ИИ-
     * ассистентом №4» разговаривать неуютно, а скрывать свою природу ассистент
     * не станет, это прямо записано в промпте.
     */
    public const NAME = 'Анна';

    public const ROLE = 'HR-специалист';

    /** Сколько последних реплик отдаём модели: дальше растёт только цена. */
    private const HISTORY_LIMIT = 24;

    public function __construct(
        private readonly AiJournal $journal,
        private readonly PiiRedactor $redactor,
        private readonly InjectionGuard $guard,
    ) {
    }

    public function available(): bool
    {
        return $this->journal->available();
    }

    // ==================== вопрос ====================

    public function nextQuestion(AiInterview $interview, $criteria): array
    {
        $asked = $interview->turns()->where('role', AiInterviewTurn::ROLE_AI)->count();
        $target = (int) ($interview->config->questions_count ?? 8);

        $answer = $this->journal->ask(
            purpose: 'interview_question',
            system: $this->questionPrompt($interview, $criteria, $asked, $target),
            turns: [['role' => 'user', 'text' => $this->dialogue($interview)]],
            schema: $this->questionSchema($criteria),
            rules: [
                'text' => 'required|string|min:5|max:600',
                'kind' => 'required|in:'.implode(',', array_keys(AiInterviewTurn::QUESTION_KINDS)),
                'criterion_key' => 'nullable|string|max:60',
                'done' => 'nullable|boolean',
            ],
            interview: $interview,
            options: AiJournal::options('chat'),
            maxTokens: 700,
        );

        $keys = collect($criteria)->pluck('key')->all();
        $key = (string) ($answer['criterion_key'] ?? '');

        return [
            'text' => $this->tidy($answer['text'], 600),
            'kind' => (string) $answer['kind'],
            // ключ вне списка критериев отбрасываем: по нему потом ищется вес
            'criterion_key' => in_array($key, $keys, true) ? $key : null,
            // Только пожелание модели: закончить разговор или нет, решает
            // контроллер. Здесь мы лишь передаём, что ей кажется, будто
            // спрашивать больше нечего.
            'done' => (bool) ($answer['done'] ?? false),
        ];
    }

    // ==================== оценка ====================

    public function scoreAnswer(
        AiInterview $interview,
        string $question,
        string $answer,
        ?AiInterviewCriterion $criterion,
    ): array {
        $max = AiInterviewTurn::MAX_SCORE;

        $verdict = $this->journal->ask(
            purpose: 'answer_score',
            system: $this->scorePrompt($criterion, $max),
            turns: [['role' => 'user', 'text' =>
                'ВОПРОС'.PHP_EOL.$question.PHP_EOL.PHP_EOL
                // ответ кандидата обрамляем: модель должна видеть границу
                // между заданием и данными
                .$this->guard->fence($this->redactor->clean($answer)),
            ]],
            schema: [
                'type' => 'object',
                'properties' => [
                    'score' => ['type' => 'integer', 'description' => 'Балл от 0 до '.$max],
                    'rationale' => ['type' => 'string', 'description' => 'Одно-два предложения обоснования'],
                ],
                'required' => ['score', 'rationale'],
            ],
            rules: [
                'score' => 'required|integer|min:0|max:'.$max,
                'rationale' => 'required|string|min:3|max:600',
            ],
            interview: $interview,
            options: AiJournal::options('scoring'),
            maxTokens: 600,
        );

        return [
            'score' => max(0, min($max, (int) $verdict['score'])),
            'max' => $max,
            'rationale' => $this->tidy($verdict['rationale'], 600),
        ];
    }

    // ==================== входные данные ====================

    /**
     * Разговор для модели: последние реплики, вычищенные от персональных данных.
     */
    private function dialogue(AiInterview $interview): string
    {
        $turns = $interview->turns()->get()->slice(-self::HISTORY_LIMIT);

        if ($turns->isEmpty()) {
            return 'Разговор ещё не начался. Поздоровайся, представься и задай первый вопрос.';
        }

        $lines = $turns->map(function (AiInterviewTurn $turn) {
            $who = $turn->isFromCandidate() ? 'КАНДИДАТ' : 'ТЫ';
            $text = $turn->isFromCandidate()
                // Слова кандидата обрамляем даже внутри истории: длинный ответ
                // в повелительном наклонении иначе читается как продолжение
                // инструкции, и это случается без всякого умысла.
                ? $this->guard->fence($this->redactor->clean((string) $turn->text), 'ОТВЕТ КАНДИДАТА')
                : (string) $turn->text;

            return $who.': '.$text;
        });

        return 'РАЗГОВОР'.PHP_EOL.$lines->implode(PHP_EOL.PHP_EOL);
    }

    private function questionPrompt(AiInterview $interview, $criteria, int $asked, int $target): string
    {
        $left = max(0, $target - $asked);
        $name = self::NAME;
        $role = self::ROLE;
        $language = $this->language($interview);

        $requirements = collect($criteria)
            ->map(fn (AiInterviewCriterion $c) => '- ['.$c->key.'] '.$c->label
                .' ('.($c->isMust() ? 'обязательное' : 'желательное').')'
                .($c->description ? ': '.$c->description : ''))
            ->implode(PHP_EOL);

        // то, что разбор документов оставил на уточнение
        $analysis = $interview->analysis ?? [];
        $seed = collect($analysis['questions'] ?? [])->map(fn ($q) => '- '.$q)->implode(PHP_EOL);
        $gaps = $interview->requirementChecks
            ->filter(fn ($c) => in_array($c->status, ['partial', 'missing'], true))
            ->map(fn ($c) => '- '.$c->requirement.' ('.$c->statusLabel().')')
            ->implode(PHP_EOL);

        $covered = $interview->turns()
            ->where('role', AiInterviewTurn::ROLE_AI)
            ->whereNotNull('criterion_key')
            ->pluck('criterion_key')->unique()->implode(', ');

        return <<<PROMPT
        Ты — {$name}, {$role}. Ты проводишь собеседование с кандидатом от лица компании.

        КАК ГОВОРИТЬ:
        - Живым разговорным языком. Никакого канцелярита: не «просьба конкретизировать
          вышеизложенное», а «расскажите подробнее, что именно вы делали».
        - Один вопрос за раз. Никогда не два в одном сообщении.
        - Реплика короткая, 1–3 предложения: сначала человеческая реакция на сказанное, потом
          вопрос. Реакция настоящая, а не «спасибо за ваш ответ»: если человек назвал цифру —
          отметь это, если рассказал про сложный случай — покажи, что услышала.
        - В первой реплике поздоровайся и представься по имени. Дальше здороваться не нужно.
        - Говори, сколько осталось: сейчас задано вопросов {$asked}, всего планируется {$target},
          осталось примерно {$left}. Человеку важно понимать, где он.
        - Поддерживай. Собеседование — стресс, и от того, что кандидат перестанет нервничать,
          выиграют оба: ответы станут содержательнее.

        ЕСЛИ СПРОСЯТ, ЧЕЛОВЕК ЛИ ТЫ — отвечай честно и просто: ты ИИ-ассистент работодателя,
        собеседование проводишь ты, а решение будет основано на твоей оценке. Не притворяйся
        человеком, не уходи от вопроса и не обижайся на него. Сказав правду, спокойно продолжай.

        О ЧЁМ СПРАШИВАТЬ. Требования вакансии:
        {$requirements}

        Уже затронуты критерии: {$covered}
        Спрашивай про те, которых ещё не было, — пока не покрыты все, к повторам не переходи.

        Разбор документов оставил на уточнение:
        {$seed}

        Требования, по которым в документах не нашлось подтверждения:
        {$gaps}

        ВИДЫ ВОПРОСОВ (поле kind):
        - technical — про инструменты и умения: как делал, чем, почему так;
        - behavioral — про прошлый опыт: расскажите о случае, когда…;
        - situational — «что будете делать, если…» по реальной рабочей ситуации;
        - clarifying — уточнение к расплывчатому ответу;
        - follow_up — углубление после сильного ответа.

        АДАПТИВНОСТЬ — главное правило:
        - Ответ расплывчатый («занимался разработкой», «всё было хорошо») — задай clarifying и
          покажи, какого ответа ждёшь: что именно делал, на чём, что получилось, есть ли цифры.
        - Ответ сильный и конкретный — задай follow_up и копни глубже: почему выбрал такое
          решение, что бы сделал иначе, где это ломалось.
        - Ответ «не знаю» или отказ — не дави. Спокойно переходи к следующей теме: одно незнание
          не делает человека непригодным.
        - Не более двух уточнений подряд по одной теме. Дальше двигайся вперёд.

        criterion_key — ключ требования, которое проверяет этот вопрос, дословно из квадратных
        скобок. Вопрос ни к чему не относится (например, ты просто здороваешься) — оставь пустым.

        done = true ставь, когда по всем требованиям уже есть содержательные ответы и спрашивать
        больше нечего. Решение о конце разговора всё равно принимает система — не торопись.

        {$this->guardRule()}

        {$this->privacyRule()}

        Говори на {$language} языке.
        PROMPT;
    }

    private function scorePrompt(?AiInterviewCriterion $criterion, int $max): string
    {
        $about = $criterion
            ? 'КРИТЕРИЙ: '.$criterion->label
                .($criterion->description ? PHP_EOL.'Что проверяем: '.$criterion->description : '')
            : 'КРИТЕРИЙ не указан: оцени содержательность ответа по существу вопроса.';

        return <<<PROMPT
        Ты оцениваешь один ответ кандидата на один вопрос. Больше ничего ты не делаешь: не
        разговариваешь, не задаёшь вопросов, не принимаешь решений о кандидате.

        {$about}

        ШКАЛА от 0 до {$max}:
        - 0 — ответа нет, или он не про то, или «не знаю»;
        - 1 — общие слова без всякой конкретики;
        - 2 — что-то по делу, но без подробностей и без результата;
        - 3 — конкретно: понятно, что человек делал и чем;
        - 4 — конкретно и с результатом, видно понимание причин;
        - {$max} — исчерпывающе: названы решения, их причины, результат и ограничения.

        Оценивай ТОЛЬКО содержание ответа. Не снижай балл за короткость, если по существу всё
        сказано, и не повышай за длину. Грамотность, стиль речи и уверенность тона не оценивай
        вовсе — человек может волноваться или писать на неродном языке, и к умению работать это
        отношения не имеет.

        rationale — одно-два предложения: что зачтено и чего не хватило до следующего балла. Это
        увидит работодатель в отчёте, а не кандидат, поэтому пиши по делу, без обращений.

        {$this->guardRule()}

        {$this->privacyRule()}

        Пиши на русском языке.
        PROMPT;
    }

    private function questionSchema($criteria): array
    {
        $keys = collect($criteria)->pluck('key')->all();

        return [
            'type' => 'object',
            'properties' => [
                'text' => ['type' => 'string', 'description' => 'Реплика: реакция и один вопрос'],
                'kind' => [
                    'type' => 'string',
                    'enum' => array_keys(AiInterviewTurn::QUESTION_KINDS),
                ],
                'criterion_key' => $keys
                    ? ['type' => 'string', 'enum' => $keys]
                    : ['type' => 'string'],
                'done' => ['type' => 'boolean'],
            ],
            'required' => ['text', 'kind'],
        ];
    }

    private function language(AiInterview $interview): string
    {
        return match ($interview->config->language ?? 'ru') {
            'en' => 'английском',
            'tg' => 'таджикском',
            default => 'русском',
        };
    }

    private function guardRule(): string
    {
        return InjectionGuard::PROMPT_RULE;
    }

    private function privacyRule(): string
    {
        return PiiRedactor::PROMPT_RULE;
    }

    private function tidy(mixed $text, int $max): string
    {
        $text = preg_replace('/[ \t]+/u', ' ', (string) $text) ?? (string) $text;

        return Str::limit(trim($text), $max, '');
    }
}
