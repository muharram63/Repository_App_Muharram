<?php

namespace App\Services\Ai;

use App\Models\AiInterview;
use App\Services\Scoring\DecisionEngine;
use Illuminate\Support\Str;

/**
 * Формулировка решения на Gemini.
 *
 * Модель получает готовый текст и правила, по которым его можно менять. Ей не
 * сообщают ни балла, ни порогов — не потому, что это тайна, а потому, что
 * лишние данные в промпте рано или поздно просачиваются в ответ, а кандидату
 * внутренние цифры не показывают.
 */
class GeminiDecisionWriter implements DecisionWriter
{
    /** Предел длины письма. Длиннее человек не дочитает, а отказ — тем более. */
    public const MAX_CHARS = 900;

    public function __construct(private readonly AiJournal $journal)
    {
    }

    public function available(): bool
    {
        return $this->journal->available();
    }

    public function write(
        AiInterview $interview,
        string $outcome,
        array $reasons,
        string $fallback,
    ): string {
        $answer = $this->journal->ask(
            purpose: 'decision_summary',
            system: $this->prompt($outcome, $interview),
            turns: [['role' => 'user', 'text' => $this->describe($interview, $reasons, $fallback)]],
            schema: [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string', 'description' => 'Готовое письмо кандидату'],
                ],
                'required' => ['message'],
            ],
            rules: [
                'message' => 'required|string|min:40|max:'.self::MAX_CHARS,
            ],
            interview: $interview,
            options: AiJournal::options('chat'),
            maxTokens: 900,
        );

        $message = $this->tidy($answer['message']);

        /*
         * Последняя проверка на цифры.
         *
         * Промпт запрещает называть баллы, но запрет в промпте — просьба, а не
         * гарантия. Если процент всё-таки просочился, берём шаблон: лучше
         * суховатый текст, чем внутренняя цифра в письме кандидату.
         */
        if ($this->mentionsScores($message)) {
            return $fallback;
        }

        return $message;
    }

    /**
     * В тексте есть балл или процент.
     */
    private function mentionsScores(string $message): bool
    {
        return (bool) preg_match('/\b\d{1,3}\s*(%|балл|из\s+100|процент)/ui', $message);
    }

    private function prompt(string $outcome, AiInterview $interview): string
    {
        $sla = $interview->config?->response_sla ?? 'в ближайшее время';

        $task = match ($outcome) {
            DecisionEngine::PASSED => <<<TXT
            Кандидат ПРОШЁЛ отбор. Сообщи об этом радостно, но без восклицаний через слово.
            Обязательно назови позицию и скажи, что с ним свяжутся {$sla} с деталями
            предложения и следующими шагами. Ничего сверх этого не обещай: ни зарплаты, ни
            даты выхода, ни имени того, кто позвонит.
            TXT,
            DecisionEngine::REJECTED => <<<TXT
            Кандидату ОТКАЗ. Это самое трудное письмо, и написать его надо так, чтобы человек
            дочитал без обиды.
            - Поблагодари за потраченное время, коротко и искренне.
            - Назови причину — ровно ту, что дана в данных, своими словами. Причина
              обязательна: отказ без причины читается как отписка.
            - Скажи прямо, что дело в совпадении с требованиями этой вакансии, а не в оценке
              человека как специалиста.
            - Предложи посмотреть другие вакансии на площадке и пожелай удачи.
            - Не жалей кандидата и не извиняйся трижды. Сочувствие в трёх абзацах унижает
              сильнее сухого отказа.
            TXT,
            default => <<<TXT
            Решение по кандидату принимает человек: система не берётся решать сама. Скажи, что
            ответы получены и переданы работодателю, и что о результате сообщат {$sla}.
            Не намекай ни на успех, ни на отказ — ни словом, ни интонацией. Человек должен
            понять, что это ожидание, а не мягкая форма отказа.
            TXT,
        };

        return <<<PROMPT
        Ты пишешь письмо кандидату по итогам собеседования от лица компании.

        {$task}

        ОБЩИЕ ПРАВИЛА:
        - Два-три коротких абзаца, не больше. Живым человеческим языком, на «вы».
        - НИКАКИХ ЦИФР: ни баллов, ни процентов, ни порогов, ни «вы набрали столько-то».
          Кандидату внутренние оценки не показывают. Говори словами, чего не хватило.
        - Не выдумывай подробностей, которых нет в данных: ни сроков, ни имён, ни условий.
        - Не упоминай, что письмо написано ИИ, и не пиши «как ИИ-ассистент». Кандидат уже
          знает, кто вёл собеседование; подпись тут не нужна.
        - Не обращайся по имени: имени у тебя нет, и придумывать его нельзя.
        - Верни готовое письмо целиком, без темы, приветственных шаблонов вроде «Уважаемый
          кандидат» и без подписи.

        Ниже дан образец — правильный по смыслу, но суховатый. Сохрани его смысл и причину
        полностью, а формулировку сделай живее. Если сомневаешься, лучше держись ближе к
        образцу: точность здесь важнее красоты.

        Пиши на русском языке.
        PROMPT;
    }

    /**
     * @param  array<int,string>  $reasons
     */
    private function describe(AiInterview $interview, array $reasons, string $fallback): string
    {
        $text = 'ВАКАНСИЯ: '.($interview->vacancy?->title ?? '—').PHP_EOL
            .'КОМПАНИЯ: '.($interview->vacancy?->employer?->company_name ?? '—').PHP_EOL
            .'СРОК ОТВЕТА: '.($interview->config?->response_sla ?? '—').PHP_EOL.PHP_EOL;

        if ($reasons !== []) {
            $text .= 'ЧЕГО НЕ ХВАТИЛО (называй только это, своими словами):'.PHP_EOL
                .collect($reasons)->map(fn ($r) => '- '.$r)->implode(PHP_EOL).PHP_EOL.PHP_EOL;
        }

        return $text.'ОБРАЗЕЦ ПИСЬМА'.PHP_EOL.$fallback;
    }

    private function tidy(mixed $message): string
    {
        $text = preg_replace('/[ \t]+/u', ' ', (string) $message) ?? (string) $message;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return Str::limit(trim($text), self::MAX_CHARS, '');
    }
}
