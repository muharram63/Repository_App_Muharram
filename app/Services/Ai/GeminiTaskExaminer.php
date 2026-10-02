<?php

namespace App\Services\Ai;

use App\Models\AiInterview;
use App\Models\AiTestTask;
use App\Services\Privacy\PiiRedactor;
use App\Services\Security\InjectionGuard;
use Illuminate\Support\Str;

/**
 * Тестовое задание на Gemini.
 *
 * Главное здесь — что проверка кода не мнение, а факт. Модель запускает решение
 * кандидата в песочнице провайдера и видит настоящий вывод программы; вывод
 * сохраняется отдельно от оценки. Проверено живым вызовом: исполнение кода и
 * строгий JSON-ответ уживаются в одном обращении, а в ответе приходят шаги
 * code_execution_call и code_execution_result с кодом и его выводом.
 *
 * Ради этого не понадобился ни Docker, ни своя песочница: изоляция на стороне
 * провайдера строже локального контейнера, и поддерживать её не нужно.
 */
class GeminiTaskExaminer implements TaskExaminer
{
    /** Сколько знаков решения отдаём модели. Дальше это уже не решение задачи. */
    public const MAX_SOLUTION = 20000;

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

    // ==================== составление ====================

    public function compose(AiInterview $interview, $criteria): array
    {
        $config = $interview->config;

        $answer = $this->journal->ask(
            purpose: 'task_compose',
            system: $this->composePrompt($interview, $criteria),
            turns: [['role' => 'user', 'text' => $this->describeVacancy($interview, $criteria)]],
            schema: $this->composeSchema(),
            rules: [
                'statement' => 'required|string|min:40|max:4000',
                'format' => 'required|in:'.implode(',', array_keys(AiTestTask::FORMATS)),
                'language' => 'nullable|string|max:30',
                'reference_solution' => 'required|string|min:10|max:8000',
                'rubric' => 'required|array|min:2|max:8',
                'rubric.*.point' => 'required|string|min:3|max:300',
                'rubric.*.weight' => 'required|integer|min:1|max:100',
            ],
            interview: $interview,
            options: AiJournal::options('scoring'),
            maxTokens: 4000,
        );

        return [
            'statement' => $this->text($answer['statement'], 4000),
            'format' => (string) $answer['format'],
            'language' => $this->text($answer['language'] ?? '', 30) ?: null,
            'reference_solution' => $this->text($answer['reference_solution'], 8000),
            'rubric' => $this->rubric($answer['rubric']),
            // время задаёт работодатель, а не модель: это его условие
            'minutes' => (int) ($config->test_time_limit_minutes ?? 60),
        ];
    }

    // ==================== проверка ====================

    public function grade(AiTestTask $task, string $solution): array
    {
        $clean = Str::limit($this->redactor->clean($solution), self::MAX_SOLUTION, '');

        $options = AiJournal::options('scoring');

        /*
         * Для кода включаем исполнение. Для кейса и письменной задачи запускать
         * нечего, и лишний инструмент только путал бы модель — она начинает
         * писать код там, где его не просили.
         */
        if ($task->isCode()) {
            $options['tools'] = [['type' => 'code_execution']];
        }

        $answer = $this->journal->askDetailed(
            purpose: 'task_grade',
            system: $this->gradePrompt($task),
            turns: [['role' => 'user', 'text' => $this->describeSolution($task, $clean)]],
            schema: $this->gradeSchema(),
            rules: [
                'score' => 'required|integer|min:0|max:100',
                'summary' => 'required|string|min:5|max:800',
                'review' => 'required|array|min:1|max:8',
                'review.*.point' => 'required|string|max:300',
                'review.*.passed' => 'required|boolean',
                'review.*.note' => 'nullable|string|max:400',
            ],
            interview: $task->interview,
            options: $options,
            // исполнение кода тратит токены на сам код и его вывод
            maxTokens: $task->isCode() ? 4000 : 2000,
        );

        $verdict = $answer['data'];
        $execution = $answer['execution'];

        return [
            'score' => max(0, min(100, (int) $verdict['score'])),
            'review' => $this->review($verdict['review']),
            'summary' => $this->text($verdict['summary'], 800),
            // Запускался ли код на самом деле. Если формат кодовый, а запуска
            // не было, оценка опирается только на чтение — и это видно в отчёте.
            'executed' => $execution !== [],
            'execution' => $execution,
        ];
    }

    // ==================== промпты ====================

    private function composePrompt(AiInterview $interview, $criteria): string
    {
        $level = $interview->config->levelLabel();
        $minutes = (int) ($interview->config->test_time_limit_minutes ?? 60);
        $formats = collect(AiTestTask::FORMATS)
            ->map(fn ($label, $key) => $key.' — '.$label)->implode('; ');

        return <<<PROMPT
        Ты составляешь тестовое задание для кандидата на конкретную вакансию. Уровень позиции:
        {$level}. На выполнение отводится {$minutes} минут — задание должно в это время
        укладываться с запасом, потому что человек будет волноваться.

        ПРАВИЛА:
        - Задание проверяет то, что нужно на этой работе, а не общую эрудицию. Списывать его из
          учебника не надо: возьми реальную ситуацию с этой должности.
        - Если про кандидата сказано, из чего он работал, выбери знакомый ему инструмент и
          обстановку: язык, фреймворк, предметную область. Проверять надо умение решать задачу,
          а не знакомство с конкретной библиотекой. Когда вакансия называет один инструмент, а
          кандидат работал на другом того же рода, бери задачу, решаемую в обоих.
        - Сложность при этом от кандидата не зависит вообще: её задают уровень позиции и
          критерии выше. Не делай задание ни проще, ни труднее из-за его опыта, возраста работы
          или громкости резюме — баллы разных людей должны быть сравнимы.
        - Формат выбери сам из подходящих: {$formats}. Для программиста уместен code, для
          менеджера или продавца — case, для бухгалтера — calculation, для редактора — writing.
        - Если формат code, назови язык программирования в поле language. Для остальных
          форматов language оставь пустым.
        - statement — условие целиком: что дано, что нужно сделать, в каком виде сдать. Пиши так,
          чтобы человек понял с одного прочтения, без уточняющих вопросов. Не нумеруй пункты
          вручную, если этого не требует смысл.
        - Не проси прислать файлы, ссылки или что-то из интернета: кандидат отвечает текстом в
          одном поле.

        ЭТАЛОН И КРИТЕРИИ. Их кандидат не увидит никогда:
        - reference_solution — решение, которое ты считаешь хорошим. Для кода — работающий код,
          для кейса — разбор по существу.
        - rubric — от двух до восьми пунктов проверки. Каждый пункт проверяем: «нормализует
          телефон, убирая все нецифровые символы», а не «код хорошего качества». weight —
          важность пункта от 1 до 100, сумму подгонять не нужно.

        ЧЕГО НЕ ДЕЛАТЬ. Задание не должно требовать сведений о поле, возрасте, национальности,
        религии, семейном положении или здоровье — ни от кандидата, ни в самом условии.

        Пиши на русском языке.
        PROMPT;
    }

    private function gradePrompt(AiTestTask $task): string
    {
        $runRule = $task->isCode()
            ? 'ОБЯЗАТЕЛЬНО ЗАПУСТИ КОД. У тебя есть исполнение кода — воспользуйся им: напиши '
                .'проверки по пунктам рубрики, прогони на них решение кандидата и смотри на '
                .'настоящий вывод. Оценка по чтению кода без запуска здесь не годится: '
                .'работающий код отличается от правдоподобного, и увидеть разницу можно только '
                .'запустив. Если код не запускается вовсе — это факт, и он важнее впечатления '
                .'от его вида.'
            : 'Запускать нечего: это не код. Оценивай по существу, сверяясь с рубрикой.';

        return <<<PROMPT
        Ты проверяешь решение тестового задания по заранее составленной рубрике.

        {$runRule}

        ПРАВИЛА ОЦЕНКИ:
        - Иди по пунктам рубрики. По каждому скажи, выполнен он или нет, и коротко почему.
        - score — от 0 до 100, взвешенно по важности пунктов. Не ставь высокий балл, если не
          выполнены важные пункты, и не занижай за мелочи, которых в рубрике нет.
        - Решение иначе, чем в эталоне, — не ошибка. Эталон это один из хороших вариантов, а не
          единственный. Если кандидат сделал по-другому и это работает, засчитывай.
        - Не снижай за стиль, оформление, имена переменных и отсутствие комментариев, если
          рубрика об этом не говорит.
        - summary — два-три предложения для работодателя: что сделано хорошо, чего не хватает.
          Кандидат этого не увидит, поэтому пиши по делу.

        {$this->guardRule()}

        {$this->privacyRule()}

        Пиши на русском языке.
        PROMPT;
    }

    // ==================== входные данные ====================

    private function describeVacancy(AiInterview $interview, $criteria): string
    {
        $vacancy = $interview->vacancy;

        $requirements = collect($criteria)
            ->map(fn ($c) => '- '.$c->label.($c->description ? ': '.$c->description : ''))
            ->implode(PHP_EOL);

        return 'ВАКАНСИЯ'.PHP_EOL
            .'Должность: '.$vacancy->title.PHP_EOL
            .'Требуемые навыки: '.$vacancy->skill.PHP_EOL
            .'Описание: '.Str::limit((string) $vacancy->description, 2000, '').PHP_EOL.PHP_EOL
            .'ЧТО ПРОВЕРЯЕМ'.PHP_EOL.$requirements
            .$this->describeCandidate($interview);
    }

    /**
     * Кандидат — чтобы задание попало в его инструмент, а не в чужой.
     *
     * Раньше составление видело только вакансию, и задание выходило про стек
     * из её описания. Вакансия пишется широко — «PHP, Laravel, MySQL», — и
     * человеку, работавшему на CodeIgniter, доставалась проверка знакомства с
     * фреймворком, а не умения решать задачу.
     *
     * Передаётся немного и намеренно: профессия, годы, навыки и последняя
     * роль. Этого хватает, чтобы выбрать язык и обстановку. Данные берутся из
     * разбора резюме, а он сделан по тексту, уже очищенному от пола, возраста
     * и прочего, что к работе не относится, — значит сюда это не попадёт.
     *
     * Сложность резюме не задаёт: её держат уровень позиции и критерии. Иначе
     * баллы кандидатов стали бы несравнимыми — слабому простое задание и
     * высокий балл, сильному сложное и низкий, а решение считается по одной
     * шкале для всех.
     */
    private function describeCandidate(AiInterview $interview): string
    {
        $resume = $interview->analysis['resume'] ?? null;

        if (! is_array($resume)) {
            return '';
        }

        $facts = collect([
            'Профессия' => $resume['profession'] ?? null,
            'Лет опыта' => $resume['experience_years'] ?? null,
            'Навыки' => collect($resume['skills'] ?? [])->take(20)->implode(', ') ?: null,
            'Последняя роль' => collect($resume['positions'] ?? [])->first()['role'] ?? null,
        ])->filter(fn ($value) => filled($value));

        if ($facts->isEmpty()) {
            return '';
        }

        return PHP_EOL.PHP_EOL.'КАНДИДАТ (для выбора стека и обстановки, не для сложности)'.PHP_EOL
            .$facts->map(fn ($value, $label) => $label.': '.$value)->implode(PHP_EOL);
    }

    private function describeSolution(AiTestTask $task, string $solution): string
    {
        $rubric = collect($task->rubric ?? [])
            ->map(fn (array $r, int $i) => ($i + 1).'. '.$r['point'].' (важность '.$r['weight'].')')
            ->implode(PHP_EOL);

        return 'УСЛОВИЕ ЗАДАНИЯ'.PHP_EOL.$task->statement.PHP_EOL.PHP_EOL
            .'РУБРИКА ПРОВЕРКИ'.PHP_EOL.$rubric.PHP_EOL.PHP_EOL
            .'ЭТАЛОННОЕ РЕШЕНИЕ (один из хороших вариантов, не единственный)'.PHP_EOL
            .$task->reference_solution.PHP_EOL.PHP_EOL
            // решение кандидата обрамляем: в коде бывают комментарии,
            // которые читаются как указания
            .$this->guard->fence($solution, 'РЕШЕНИЕ КАНДИДАТА');
    }

    // ==================== схемы ====================

    private function composeSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'statement' => ['type' => 'string', 'description' => 'Условие задания целиком'],
                'format' => ['type' => 'string', 'enum' => array_keys(AiTestTask::FORMATS)],
                'language' => ['type' => 'string', 'description' => 'Язык программирования, если формат code'],
                'reference_solution' => ['type' => 'string'],
                'rubric' => [
                    'type' => 'array', 'maxItems' => 8,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'point' => ['type' => 'string', 'description' => 'Проверяемый пункт'],
                            'weight' => ['type' => 'integer'],
                        ],
                        'required' => ['point', 'weight'],
                    ],
                ],
            ],
            'required' => ['statement', 'format', 'reference_solution', 'rubric'],
        ];
    }

    private function gradeSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'score' => ['type' => 'integer', 'description' => 'Балл 0-100'],
                'summary' => ['type' => 'string'],
                'review' => [
                    'type' => 'array', 'maxItems' => 8,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'point' => ['type' => 'string'],
                            'passed' => ['type' => 'boolean'],
                            'note' => ['type' => 'string'],
                        ],
                        'required' => ['point', 'passed'],
                    ],
                ],
            ],
            'required' => ['score', 'summary', 'review'],
        ];
    }

    // ==================== приведение в порядок ====================

    /**
     * @return array<int,array{point:string,weight:int}>
     */
    private function rubric(mixed $rows): array
    {
        $clean = [];

        foreach (is_array($rows) ? array_slice($rows, 0, 8) : [] as $row) {
            if (! is_array($row) || blank($row['point'] ?? null)) {
                continue;
            }

            $clean[] = [
                'point' => $this->text($row['point'], 300),
                'weight' => max(1, min(100, (int) ($row['weight'] ?? 10))),
            ];
        }

        return $clean;
    }

    /**
     * @return array<int,array{point:string,passed:bool,note:string}>
     */
    private function review(mixed $rows): array
    {
        $clean = [];

        foreach (is_array($rows) ? array_slice($rows, 0, 8) : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $clean[] = [
                'point' => $this->text($row['point'] ?? '', 300),
                'passed' => (bool) ($row['passed'] ?? false),
                'note' => $this->text($row['note'] ?? '', 400),
            ];
        }

        return $clean;
    }

    private function text(mixed $value, int $max): string
    {
        $text = is_scalar($value) ? (string) $value : '';

        // в условии и в коде переводы строк значимы, схлопываем только пробелы
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;

        return Str::limit(trim($text), $max, '');
    }

    private function guardRule(): string
    {
        return InjectionGuard::PROMPT_RULE;
    }

    private function privacyRule(): string
    {
        return PiiRedactor::PROMPT_RULE;
    }
}
