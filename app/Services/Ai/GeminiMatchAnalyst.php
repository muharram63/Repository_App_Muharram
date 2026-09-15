<?php

namespace App\Services\Ai;

use App\Models\Resume;
use App\Models\SkillAttempt;
use App\Models\Vacancy;
use App\Support\MatchCriteria;
use Illuminate\Support\Str;

/**
 * Разбор совпадения на Gemini.
 *
 * Промпт намеренно требует называть конкретные навыки и запрещает общие
 * оценки: «хорошее совпадение» ничего не говорит ни кандидату, ни компании,
 * а «совпадение по Laravel и MySQL, но нет опыта с Kubernetes» — говорит.
 */
class GeminiMatchAnalyst implements MatchAnalyst
{
    /** Сколько знаков текста берём с каждой стороны: дальше растёт только цена. */
    private const LIMIT = 2500;

    public function __construct(private readonly GeminiClient $client)
    {
    }

    public function available(): bool
    {
        return $this->client->configured();
    }

    public function analyse(Vacancy $vacancy, Resume $resume): array
    {
        $badges = SkillAttempt::badgesFor($resume->applicant);

        $answer = $this->client->structured(
            $this->prompt(),
            [['role' => 'user', 'text' => $this->describe($vacancy, $resume, $badges)]],
            $this->schema(),
            maxTokens: 1200,
        );

        $enough = (bool) ($answer['enough_data'] ?? true);
        $matched = $this->items($answer['matched_skills'] ?? [], 12);
        $missing = $this->items($answer['missing_skills'] ?? [], 12);
        $partial = $this->partials($answer['partial_matches'] ?? []);

        // Разбор по трём критериям: профессия, навыки и языки. Город,
        // зарплату и стаж не сравниваем — это условия и «корочки», а не
        // признак того, что человек справится с работой.
        $criteria = [
            MatchCriteria::fromModel('role', 'Профессия', $answer['role_match'] ?? null),
            $this->skillsCriterion($matched, $missing, $partial),
            MatchCriteria::fromModel('languages', 'Языки', $answer['language_match'] ?? null),
        ];

        return [
            'matched_skills' => $matched,
            'missing_skills' => $missing,
            'partial_matches' => $partial,
            'criteria' => $criteria,
            // какие из совпадений подтверждены заданием — сверяем сами, а не
            // спрашиваем модель: это факт из базы, гадать тут не о чем
            'proven_skills' => $this->proven($matched, $badges),
            'verdict' => Str::limit(trim((string) ($answer['verdict'] ?? '')), 400, ''),
            // Процент считаем сами по критериям, а не берём у модели: так он
            // объясним — видно, из чего сложился. При нехватке данных числа нет
            // вовсе: оно вводило бы в заблуждение сильнее, чем его отсутствие.
            'score' => $enough ? MatchCriteria::score($criteria) : null,
            'enough_data' => $enough,
        ];
    }

    /**
     * Навыки как критерий: доля закрытых требований, частичные — половиной.
     */
    private function skillsCriterion(array $matched, array $missing, array $partial): array
    {
        $total = count($matched) + count($missing) + count($partial);

        if ($total === 0) {
            return MatchCriteria::row('skills', 'Навыки', 'unknown', 'Требования не разобраны');
        }

        $share = (count($matched) + count($partial) / 2) / $total;
        $note = count($matched).' из '.$total.' требований'
            .(count($partial) ? ', '.count($partial).' частично' : '');

        return MatchCriteria::row('skills', 'Навыки', match (true) {
            $share >= 0.8 => 'ok',
            $share >= 0.4 => 'partial',
            default => 'no',
        }, $note);
    }

    /**
     * Совпадения, за которыми стоит пройденное задание — вместе с уровнем
     * и баллом: работодателю важно не только «проверено», но и насколько
     * глубоко проверено.
     *
     * @param  array<int,string>  $matched
     * @return array<int,array{skill:string,level:string,score:int}>
     */
    private function proven(array $matched, $badges): array
    {
        $confirmed = $badges->keyBy(fn (array $b) => mb_strtolower(trim($b['skill'])));

        return collect($matched)
            ->map(fn (string $skill) => $confirmed->get(mb_strtolower(trim($skill))))
            ->filter()
            ->map(fn (array $b) => [
                'skill' => $b['skill'],
                'level' => $b['level'],
                'score' => (int) $b['score'],
            ])
            ->values()
            ->all();
    }

    /**
     * Тексты сторон. Режем длину: разбору хватает сути, а счёт идёт за токены.
     */
    private function describe(Vacancy $vacancy, Resume $resume, $badges): string
    {
        $vacancyText = collect([
            'Должность' => $vacancy->title,
            'Требуемые навыки' => $vacancy->skill,
            'Требуемые языки' => $vacancy->languages,
            'Занятость' => $vacancy->employment_type,
            'График' => $vacancy->work_schedule,
            'Описание' => Str::limit((string) $vacancy->description, self::LIMIT, ''),
        ]);

        $resumeText = collect([
            'Профессия' => $resume->profession,
            'Желаемая должность' => $resume->desired_position,
            'Навыки' => $resume->skills,
            'Языки' => $resume->languages,
            'Последнее место работы' => $resume->place_work,
            'О себе' => Str::limit((string) $resume->description, self::LIMIT, ''),
        ]);

        $lines = fn ($fields) => $fields
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value, $label) => $label.': '.$value)
            ->implode(PHP_EOL);

        $text = 'ВАКАНСИЯ'.PHP_EOL.$lines($vacancyText).PHP_EOL.PHP_EOL
            .'РЕЗЮМЕ КАНДИДАТА'.PHP_EOL.$lines($resumeText);

        // подтверждённые навыки идут отдельным блоком: это проверенный факт,
        // а не то, что кандидат написал о себе сам
        if ($badges->isNotEmpty()) {
            $text .= PHP_EOL.PHP_EOL.'ПОДТВЕРЖДЕНО ЗАДАНИЕМ НА ПЛАТФОРМЕ'.PHP_EOL
                .$badges->map(fn (array $b) => $b['skill'].' — '.$b['score'].'% ('.$b['level'].')')
                    ->implode(PHP_EOL);
        }

        return $text;
    }

    private function prompt(): string
    {
        $language = match (app()->getLocale()) {
            'en' => 'английском',
            'tg' => 'таджикском',
            default => 'русском',
        };

        return <<<PROMPT
        Ты — рекрутер, который сопоставляет кандидата и вакансию. Твоя работа — назвать конкретику,
        по которой человек поймёт решение за пару секунд.

        ЖЁСТКИЕ ПРАВИЛА:
        - Каждый пункт — конкретный навык, инструмент или требование из текстов. «Хорошее совпадение»,
          «подходит по опыту», «развитые soft skills» — это не пункты, такое не пиши.
        - matched_skills: то, что требует вакансия и что подтверждено резюме. Пиши название навыка,
          а не предложение: «Laravel», «SQL», «переговоры с клиентами».
        - missing_skills: требования вакансии, которых в резюме нет вовсе.
        - partial_matches: близко, но не то же самое. В title — навык или требование, в note —
          одно предложение, чем именно отличается: «опыт есть, но в рознице, а не в финтехе».
        - Не выдумывай навыки, которых нет ни в вакансии, ни в резюме. Не повторяй один навык в двух списках.
        - verdict: 1–2 предложения по существу, почему кандидат подходит или нет. Без вежливых общих слов.

        ПРО ПРОФЕССИЮ (role_match). Сравни название вакансии с профессией и желаемой должностью
        кандидата ПО СМЫСЛУ, а не по совпадению слов:
        - ok — то же направление, пусть и названо иначе («Backend-разработчик» и «PHP-программист»);
        - partial — смежное направление, переход реален («Тестировщик» на «Backend-разработчика»);
        - no — другая профессия («Оператор станка» и «Оператор call-центра» похожи только на вид).
        В note — одна строка, чем именно близко или далеко.

        ПРО ЯЗЫКИ (language_match). Требование указано в поле «Требуемые языки». Сравни его
        с языками из резюме. Если поле пустое, посмотри название и описание вакансии:
        - ok — все нужные языки у кандидата есть;
        - partial — язык есть, но уровнем ниже требуемого, либо из нескольких нужных владеет частью;
        - no — требуемым языком кандидат не владеет.
        Если в вакансии о языках не сказано ни слова, поле language_match НЕ ЗАПОЛНЯЙ вовсе —
        система тогда просто не учтёт этот критерий. Не додумывай требование, которого нет.

        Процент совпадения не считай: его выводит система по трём критериям — профессии,
        навыкам и языкам. Город, зарплату и стаж не обсуждай вовсе, их в разборе нет.
        Твоё дело — назвать конкретику.

        ПРО ПОДТВЕРЖДЁННЫЕ НАВЫКИ. Если в данных есть блок «ПОДТВЕРЖДЕНО ЗАДАНИЕМ НА ПЛАТФОРМЕ» —
        это результат проверки, а не слова кандидата о себе. Такой навык считается доказанным:
        ставь его в matched_skills, даже если в тексте резюме о нём сказано вскользь, и не отправляй
        в partial_matches из-за нехватки описания. При равных прочих такой кандидат оценивается выше,
        и в verdict это стоит назвать прямо: навык проверен заданием.

        КОГДА ДАННЫХ МАЛО (резюме почти пустое, в вакансии нет требований) — поставь enough_data = false,
        оставь списки пустыми и в verdict честно скажи, чего не хватает для разбора.
        Домысливать вместо разбора нельзя.

        Пиши на {$language} языке.
        PROMPT;
    }

    private function schema(): array
    {
        $skills = [
            'type' => 'array',
            'items' => ['type' => 'string'],
            'maxItems' => 12,
        ];

        return [
            'type' => 'object',
            'properties' => [
                'enough_data' => [
                    'type' => 'boolean',
                    'description' => 'Хватает ли данных для содержательного разбора',
                ],
                'matched_skills' => $skills,
                'missing_skills' => $skills,
                'partial_matches' => [
                    'type' => 'array',
                    'maxItems' => 6,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'note' => ['type' => 'string', 'description' => 'Одно предложение о различии'],
                        ],
                        'required' => ['title'],
                    ],
                ],
                'role_match' => [
                    'type' => 'object',
                    'description' => 'Насколько профессия кандидата отвечает названию вакансии',
                    'properties' => [
                        'state' => ['type' => 'string', 'enum' => ['ok', 'partial', 'no']],
                        'note' => ['type' => 'string', 'description' => 'Короткое пояснение, одна строка'],
                    ],
                    'required' => ['state'],
                ],
                'language_match' => [
                    'type' => 'object',
                    'description' => 'Владеет ли кандидат языками, которых требует вакансия. '
                        .'Не заполнять, если о языках в вакансии не сказано',
                    'properties' => [
                        'state' => ['type' => 'string', 'enum' => ['ok', 'partial', 'no']],
                        'note' => ['type' => 'string', 'description' => 'Короткое пояснение, одна строка'],
                    ],
                    'required' => ['state'],
                ],
                'verdict' => ['type' => 'string', 'description' => 'Один-два предложения по существу'],
            ],
            // language_match намеренно не обязателен: вакансия может молчать о языках
            'required' => ['enough_data', 'verdict', 'role_match'],
        ];
    }

    /**
     * @return array<int,string>
     */
    private function items(mixed $values, int $limit): array
    {
        if (! is_array($values)) {
            return [];
        }

        $clean = [];

        foreach (array_slice($values, 0, $limit) as $value) {
            $item = Str::limit(trim((string) (is_scalar($value) ? $value : '')), 80, '');

            if ($item !== '' && ! in_array($item, $clean, true)) {
                $clean[] = $item;
            }
        }

        return $clean;
    }

    /**
     * @return array<int,array{title:string,note:string}>
     */
    private function partials(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $clean = [];

        foreach (array_slice($values, 0, 6) as $row) {
            if (! is_array($row) || blank($row['title'] ?? null)) {
                continue;
            }

            $clean[] = [
                'title' => Str::limit(trim((string) $row['title']), 80, ''),
                'note' => Str::limit(trim((string) ($row['note'] ?? '')), 200, ''),
            ];
        }

        return $clean;
    }
}
