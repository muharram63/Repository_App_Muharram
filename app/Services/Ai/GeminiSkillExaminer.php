<?php

namespace App\Services\Ai;

use App\Models\Skill;
use Illuminate\Support\Str;

/**
 * Экзаменатор на Gemini.
 *
 * Два разных обращения к модели с разными промптами: одно составляет задание
 * (редко, результат сохраняется в банк), второе проверяет свободные ответы
 * (только если кандидат ими воспользовался).
 */
class GeminiSkillExaminer implements SkillExaminer
{
    /** Сколько вопросов в задании: 5 укладывается в 10–15 минут. */
    public const QUESTIONS = 5;

    public function __construct(private readonly GeminiClient $client)
    {
    }

    public function available(): bool
    {
        return $this->client->configured();
    }

    public function compose(Skill $skill, string $level, int $variant): array
    {
        $answer = $this->client->structured(
            $this->composePrompt($skill, $level, $variant),
            [['role' => 'user', 'text' => 'Составь задание по навыку «'.$skill->name.'».']],
            $this->composeSchema(),
            maxTokens: 3000,
        );

        return $this->clean($answer['questions'] ?? []);
    }

    public function grade(Skill $skill, array $answers): array
    {
        $payload = collect($answers)->map(fn (array $a, int $i) => sprintf(
            "ВОПРОС %d: %s\nЧТО СЧИТАЕТСЯ ВЕРНЫМ: %s\nОТВЕТ КАНДИДАТА: %s",
            $i + 1,
            $a['question'],
            $a['expected'],
            $a['answer'],
        ))->implode(PHP_EOL.PHP_EOL);

        $answer = $this->client->structured(
            $this->gradePrompt($skill),
            [['role' => 'user', 'text' => $payload]],
            $this->gradeSchema(),
            maxTokens: 1500,
        );

        $verdicts = [];

        foreach (array_values($answer['verdicts'] ?? []) as $i => $verdict) {
            $verdicts[$i] = [
                'correct' => (bool) ($verdict['correct'] ?? false),
                'comment' => Str::limit(trim((string) ($verdict['comment'] ?? '')), 300, ''),
            ];
        }

        // модель могла вернуть меньше вердиктов, чем ответов: недостающее
        // не засчитываем, но и не роняем проверку целиком
        foreach (array_keys($answers) as $i) {
            $verdicts[$i] ??= ['correct' => false, 'comment' => 'Ответ не удалось проверить.'];
        }

        ksort($verdicts);

        return $verdicts;
    }

    private function composePrompt(Skill $skill, string $level, int $variant): string
    {
        $level = Skill::LEVELS[$level] ?? $level;
        $count = self::QUESTIONS;

        return <<<PROMPT
        Ты составляешь короткую практическую проверку навыка для площадки поиска работы.
        Навык: «{$skill->name}». Уровень: {$level}. Это вариант №{$variant} — он должен отличаться
        от других вариантов по этому навыку темами и формулировками.

        ПРАВИЛА:
        - Ровно {$count} вопросов. Каждый — про практику, а не про определения из учебника.
          Спрашивай «что сделать в такой ситуации», а не «как называется».
        - У каждого вопроса 4 варианта ответа, из них верен ровно один.
          Неверные варианты должны быть правдоподобными, а не нелепыми.
        - Вопрос понятен человеку без диплома: без жаргона ради жаргона и без отсылок к конкретной компании.
        - explain — одно предложение, почему верный ответ верен. Его увидит кандидат после проверки.
        - Не нумеруй вопросы и не пиши «Вопрос 1»: нумерация появится сама.
        - Ничего, кроме задания: никаких приветствий и пояснений вокруг.

        Пиши на русском языке.
        PROMPT;
    }

    private function gradePrompt(Skill $skill): string
    {
        return <<<PROMPT
        Ты проверяешь ответы кандидата по навыку «{$skill->name}». Кандидат отвечал своими словами,
        а не выбирал из вариантов — оценивай смысл, а не совпадение формулировок.

        ПРАВИЛА:
        - Ответ верен, если по сути совпадает с тем, что считается верным, пусть и сказан иначе.
          Формулировка своими словами, синонимы, другой порядок слов — это верно.
        - Ответ неверен, если он про другое, содержит ошибку по существу или ничего не утверждает
          («не знаю», «затрудняюсь», пустая фраза).
        - Кандидат может дать ответ лучше ожидаемого — это тоже верно.
        - comment — одно короткое предложение: что именно зачтено или в чём ошибка.
          Пиши кандидату и по делу, без назиданий.
        - Верни ровно столько вердиктов, сколько было вопросов, в том же порядке.

        Пиши на русском языке.
        PROMPT;
    }

    private function composeSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'questions' => [
                    'type' => 'array',
                    'maxItems' => self::QUESTIONS,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => ['type' => 'string', 'description' => 'Практическая ситуация или задача'],
                            'options' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                                'maxItems' => 4,
                            ],
                            'answer' => ['type' => 'integer', 'description' => 'Номер верного варианта, с нуля'],
                            'explain' => ['type' => 'string', 'description' => 'Одно предложение, почему так'],
                        ],
                        'required' => ['text', 'options', 'answer'],
                    ],
                ],
            ],
            'required' => ['questions'],
        ];
    }

    private function gradeSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'verdicts' => [
                    'type' => 'array',
                    'maxItems' => self::QUESTIONS,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'correct' => ['type' => 'boolean'],
                            'comment' => ['type' => 'string', 'description' => 'Одно предложение кандидату'],
                        ],
                        'required' => ['correct'],
                    ],
                ],
            ],
            'required' => ['verdicts'],
        ];
    }

    /**
     * Приводит задание в годный вид: вопрос без четырёх вариантов и без
     * указанного верного ответа проверить нельзя, такой лучше выбросить.
     */
    private function clean(mixed $questions): array
    {
        if (! is_array($questions)) {
            return [];
        }

        $clean = [];

        foreach (array_slice($questions, 0, self::QUESTIONS) as $question) {
            if (! is_array($question)) {
                continue;
            }

            $options = collect(is_array($question['options'] ?? null) ? $question['options'] : [])
                ->map(fn ($o) => Str::limit(trim((string) (is_scalar($o) ? $o : '')), 300, ''))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $answer = (int) ($question['answer'] ?? -1);
            $text = Str::limit(trim((string) ($question['text'] ?? '')), 600, '');

            if ($text === '' || count($options) < 2 || $answer < 0 || $answer >= count($options)) {
                continue;
            }

            $clean[] = [
                'text' => $text,
                'options' => $options,
                'answer' => $answer,
                'explain' => Str::limit(trim((string) ($question['explain'] ?? '')), 300, ''),
            ];
        }

        return $clean;
    }
}
