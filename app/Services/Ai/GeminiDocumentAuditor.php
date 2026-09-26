<?php

namespace App\Services\Ai;

use App\Models\AiCandidateDocument;
use App\Models\AiRequirementCheck;
use App\Models\Resume;
use App\Services\Documents\TextExtractor;
use App\Services\Privacy\PiiRedactor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Разбор документов на Gemini.
 *
 * Сквозное правило всех трёх промптов: вывод без доказательства не принимается.
 * Требование считается закрытым только с цитатой из резюме или документа, а не
 * потому что модель так решила. Цитата — это то, что работодатель сможет
 * прочитать в отчёте, а кандидат — оспорить.
 */
class GeminiDocumentAuditor implements DocumentAuditor
{
    /**
     * Предел размера файла для отправки в модель.
     *
     * Base64 раздувает файл на треть, и пятимегабайтный скан превращается в
     * семимегабайтное тело запроса. Такой запрос уходит минуты и легко
     * упирается в таймаут, поэтому крупные файлы до модели не доводим — лучше
     * честно сказать, что документ не разобран.
     */
    public const MAX_FILE_BYTES = 4 * 1024 * 1024;

    public function __construct(
        private readonly AiJournal $journal,
        private readonly PiiRedactor $redactor,
    ) {
    }

    public function available(): bool
    {
        return $this->journal->available();
    }

    // ==================== резюме ====================

    public function parseResume(Resume $resume): array
    {
        $answer = $this->journal->ask(
            purpose: 'resume_parse',
            system: $this->resumePrompt(),
            turns: [['role' => 'user', 'text' => $this->describeResume($resume)]],
            schema: $this->resumeSchema(),
            rules: [
                'enough_data' => 'required|boolean',
                'profession' => 'nullable|string|max:200',
                'experience_years' => 'nullable|integer|min:0|max:70',
                'skills' => 'nullable|array|max:40',
                'skills.*' => 'string|max:80',
                'languages' => 'nullable|array|max:12',
                'positions' => 'nullable|array|max:12',
                'education' => 'nullable|array|max:8',
                'achievements' => 'nullable|array|max:12',
            ],
            options: AiJournal::options('scoring'),
            maxTokens: 2600,
        );

        return [
            'enough_data' => (bool) ($answer['enough_data'] ?? false),
            'profession' => $this->line($answer['profession'] ?? '', 200),
            'experience_years' => max(0, min(70, (int) ($answer['experience_years'] ?? 0))),
            'skills' => $this->strings($answer['skills'] ?? [], 40, 80),
            'languages' => $this->strings($answer['languages'] ?? [], 12, 60),
            'positions' => $this->rows($answer['positions'] ?? [], 12,
                ['company' => 160, 'role' => 160, 'period' => 60, 'summary' => 400]),
            'education' => $this->rows($answer['education'] ?? [], 8,
                ['place' => 200, 'program' => 200, 'year' => 40]),
            'achievements' => $this->strings($answer['achievements'] ?? [], 12, 300),
        ];
    }

    // ==================== документ ====================

    public function auditDocument(AiCandidateDocument $document, array $parsedResume): array
    {
        $turn = ['role' => 'user', 'text' => $this->describeForAudit($document, $parsedResume)];

        /*
         * Файл отдаём модели целиком, когда она умеет его читать. Это важнее,
         * чем кажется: в скане диплома есть печать, подпись и расположение
         * полей, а в вытащенном тексте — нет. Отдав только текст, мы лишили бы
         * проверку половины данных.
         */
        if ($file = $this->fileFor($document)) {
            $turn['files'] = [$file];
        }

        $answer = $this->journal->ask(
            purpose: 'document_audit',
            system: $this->documentPrompt(),
            turns: [$turn],
            schema: $this->documentSchema(),
            rules: [
                'readable' => 'required|boolean',
                'matches_resume' => 'required|in:yes,partial,no,unknown',
                'relevance' => 'required|in:direct,related,none,unknown',
                'mismatches' => 'nullable|array|max:8',
                'mismatches.*' => 'string|max:300',
                'note' => 'nullable|string|max:400',
            ],
            interview: $document->interview,
            options: AiJournal::options('vision'),
            maxTokens: 1400,
        );

        return [
            'readable' => (bool) ($answer['readable'] ?? false),
            'kind' => $this->line($answer['kind'] ?? '', 60),
            'person' => $this->line($answer['person'] ?? '', 160) ?: null,
            'institution' => $this->line($answer['institution'] ?? '', 200) ?: null,
            'program' => $this->line($answer['program'] ?? '', 200) ?: null,
            'year' => $this->line($answer['year'] ?? '', 40) ?: null,
            'matches_resume' => (string) $answer['matches_resume'],
            'mismatches' => $this->strings($answer['mismatches'] ?? [], 8, 300),
            'relevance' => (string) $answer['relevance'],
            'note' => $this->line($answer['note'] ?? '', 400),
            // Строка приписывается всегда и в коде, а не моделью: она не должна
            // зависеть от того, вспомнит ли модель эту оговорку.
            'authenticity' => AiCandidateDocument::AUTHENTICITY_NOTE,
        ];
    }

    // ==================== требования ====================

    public function buildMatrix($criteria, array $parsedResume, array $documentFindings): array
    {
        $keys = collect($criteria)->pluck('key')->all();

        $answer = $this->journal->ask(
            purpose: 'requirement_matrix',
            system: $this->matrixPrompt(),
            turns: [['role' => 'user', 'text' => $this->describeForMatrix($criteria, $parsedResume, $documentFindings)]],
            schema: $this->matrixSchema($keys),
            rules: [
                'enough_data' => 'required|boolean',
                'checks' => 'required|array|min:1|max:'.max(1, count($keys)),
                // Перечень допустимых ключей в правилах не указываем, хотя он
                // есть в схеме. Стоило бы — но тогда один выдуманный моделью
                // ключ проваливал бы всю сверку, включая правильные строки.
                // Лишние ключи отбрасывает checks(), а пропущенные добавляет
                // как ненайденные: это дешевле и надёжнее, чем повтор.
                'checks.*.key' => 'required|string|max:60',
                'checks.*.status' => 'required|in:found,partial,missing',
                'checks.*.evidence' => 'nullable|string|max:400',
                'checks.*.confidence' => 'nullable|integer|min:0|max:100',
                'inconsistencies' => 'nullable|array|max:8',
                'inconsistencies.*' => 'string|max:300',
                'questions' => 'nullable|array|max:8',
                'questions.*' => 'string|max:300',
            ],
            options: AiJournal::options('scoring'),
            maxTokens: 2600,
        );

        return [
            'enough_data' => (bool) ($answer['enough_data'] ?? false),
            'checks' => $this->checks($answer['checks'] ?? [], $keys),
            'inconsistencies' => $this->strings($answer['inconsistencies'] ?? [], 8, 300),
            'questions' => $this->strings($answer['questions'] ?? [], 8, 300),
        ];
    }

    /**
     * Статусы требований, приведённые в порядок.
     *
     * Требование без цитаты закрытым не считается: понижаем до partial, а
     * partial без цитаты — до missing. Так вывод «есть» всегда сопровождается
     * доказательством, и отчёт нечем оспорить, кроме самой цитаты.
     *
     * @param  array<int,string>  $keys
     * @return array<int,array{key:string,status:string,evidence:string,confidence:int}>
     */
    private function checks(mixed $rows, array $keys): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $clean = [];
        $seen = [];

        foreach ($rows as $row) {
            $key = is_array($row) ? (string) ($row['key'] ?? '') : '';

            if (! in_array($key, $keys, true) || in_array($key, $seen, true)) {
                continue;
            }

            $seen[] = $key;
            $status = (string) ($row['status'] ?? 'missing');
            $evidence = $this->line($row['evidence'] ?? '', 400);

            if ($evidence === '') {
                $status = $status === 'found' ? 'partial' : 'missing';
            }

            $clean[] = [
                'key' => $key,
                'status' => in_array($status, ['found', 'partial', 'missing'], true) ? $status : 'missing',
                'evidence' => $evidence,
                'confidence' => max(0, min(100, (int) ($row['confidence'] ?? 50))),
            ];
        }

        // Требование, о котором модель промолчала, считается ненайденным.
        // Молчание не может означать «есть»: иначе пропущенный критерий
        // открывал бы ворота решения сам собой.
        foreach (array_diff($keys, $seen) as $missed) {
            $clean[] = [
                'key' => $missed,
                'status' => 'missing',
                'evidence' => '',
                'confidence' => 0,
            ];
        }

        return $clean;
    }

    // ==================== входные данные ====================

    /**
     * Резюме в текст для модели. Персональные данные вырезаны.
     */
    private function describeResume(Resume $resume): string
    {
        $fields = collect([
            'Профессия' => $resume->profession,
            'Желаемая должность' => $resume->desired_position,
            'Лет опыта' => $resume->experience_years,
            'Навыки' => $resume->skills,
            'Языки' => $resume->languages,
            'Последнее место работы' => $resume->place_work,
            'О себе' => Str::limit((string) $resume->description, 6000, ''),
        ]);

        $text = 'РЕЗЮМЕ КАНДИДАТА'.PHP_EOL.$fields
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value, $label) => $label.': '.$value)
            ->implode(PHP_EOL);

        // Анкета соискателя тоже участвует: образование там, а не в резюме.
        if ($about = $resume->applicant?->about_me) {
            $text .= PHP_EOL.PHP_EOL.'ИЗ АНКЕТЫ'.PHP_EOL.Str::limit($about, 2000, '');
        }

        if ($education = $resume->applicant?->education) {
            $text .= PHP_EOL.'Образование: '.$education;
        }

        return $this->redactor->clean($text);
    }

    private function describeForAudit(AiCandidateDocument $document, array $parsedResume): string
    {
        $text = 'ЧТО КАНДИДАТ УКАЗАЛ О СЕБЕ'.PHP_EOL
            .'Профессия: '.($parsedResume['profession'] ?? '—').PHP_EOL
            .'Образование: '.collect($parsedResume['education'] ?? [])
                ->map(fn ($row) => trim(($row['place'] ?? '').' — '.($row['program'] ?? '').' '.($row['year'] ?? '')))
                ->filter()->implode('; ').PHP_EOL
            .'Навыки: '.implode(', ', array_slice($parsedResume['skills'] ?? [], 0, 25)).PHP_EOL.PHP_EOL
            .'ДОКУМЕНТ'.PHP_EOL
            .'Кандидат назвал его: '.$document->kindLabel().PHP_EOL
            .'Имя файла: '.$document->original_name;

        // текст прилагаем, только когда сам файл модели не уходит
        if (! $this->fileFor($document) && filled($document->extracted_text)) {
            $text .= PHP_EOL.PHP_EOL.'ТЕКСТ ДОКУМЕНТА'.PHP_EOL
                .Str::limit((string) $document->extracted_text, 6000, '');
        }

        return $this->redactor->clean($text);
    }

    private function describeForMatrix($criteria, array $parsedResume, array $documentFindings): string
    {
        $requirements = collect($criteria)
            ->map(fn ($c) => '- ['.$c->key.'] '.$c->label
                .' ('.($c->isMust() ? 'обязательное' : 'желательное').')'
                .($c->description ? ': '.$c->description : ''))
            ->implode(PHP_EOL);

        $resume = 'РЕЗЮМЕ (разобранное)'.PHP_EOL
            .'Профессия: '.($parsedResume['profession'] ?? '—').PHP_EOL
            .'Лет опыта: '.($parsedResume['experience_years'] ?? 0).PHP_EOL
            .'Навыки: '.implode(', ', $parsedResume['skills'] ?? []).PHP_EOL
            .'Языки: '.implode(', ', $parsedResume['languages'] ?? []).PHP_EOL
            .'Места работы:'.PHP_EOL.collect($parsedResume['positions'] ?? [])
                ->map(fn ($p) => '  · '.($p['role'] ?? '').' в '.($p['company'] ?? '')
                    .' ('.($p['period'] ?? '—').'): '.($p['summary'] ?? ''))
                ->implode(PHP_EOL).PHP_EOL
            .'Образование:'.PHP_EOL.collect($parsedResume['education'] ?? [])
                ->map(fn ($e) => '  · '.($e['place'] ?? '').' — '.($e['program'] ?? '').' '.($e['year'] ?? ''))
                ->implode(PHP_EOL).PHP_EOL
            .'Достижения:'.PHP_EOL.collect($parsedResume['achievements'] ?? [])
                ->map(fn ($a) => '  · '.$a)->implode(PHP_EOL);

        $documents = collect($documentFindings)
            ->map(fn (array $f) => '  · '.($f['kind'] ?: 'документ')
                .': '.($f['institution'] ?: '—').' / '.($f['program'] ?: '—').' / '.($f['year'] ?: '—')
                .'; сверка с резюме: '.($f['matches_resume'] ?? 'unknown')
                .'; отношение к вакансии: '.($f['relevance'] ?? 'unknown')
                .($f['mismatches'] ? '; расхождения: '.implode(', ', $f['mismatches']) : ''))
            ->implode(PHP_EOL);

        return 'ТРЕБОВАНИЯ ВАКАНСИИ'.PHP_EOL.$requirements.PHP_EOL.PHP_EOL
            .$resume.PHP_EOL.PHP_EOL
            .'ДОКУМЕНТЫ'.PHP_EOL.($documents ?: '  документов нет');
    }

    /**
     * Файл для отправки в модель, если она его прочитает и он не слишком велик.
     *
     * @return array{mime:string,data:string}|null
     */
    private function fileFor(AiCandidateDocument $document): ?array
    {
        $extractor = new TextExtractor;

        if (! $extractor->readableByModel($document->mime)) {
            return null;
        }

        if (! Storage::disk(TextExtractor::DISK)->exists($document->path)) {
            return null;
        }

        if (($document->size ?? 0) > self::MAX_FILE_BYTES) {
            return null;
        }

        return [
            'mime' => (string) $document->mime,
            'data' => base64_encode((string) Storage::disk(TextExtractor::DISK)->get($document->path)),
        ];
    }

    // ==================== промпты ====================

    private function resumePrompt(): string
    {
        return <<<PROMPT
        Ты разбираешь резюме кандидата и переводишь его в структурированный вид.

        ПРАВИЛА:
        - Не додумывай. Если чего-то в тексте нет, оставь поле пустым или список пустым.
          Пустое поле честнее выдуманного: по этим данным потом принимается решение о человеке.
        - skills — конкретные инструменты, технологии и умения, каждое отдельной строкой.
          Не «хорошие коммуникативные навыки», а то, что можно проверить вопросом.
        - positions — места работы. В summary одной-двумя фразами: что человек делал и что
          получилось. Пересказывать всё резюме не нужно.
        - achievements — только измеримые или конкретные результаты. Общие слова не достижения.
        - experience_years — целое число лет. Не сказано — ставь 0, не прикидывай по датам,
          если даты неполные.
        - enough_data = false, когда резюме почти пустое и разбирать нечего. Это нормальный
          ответ, а не ошибка: лучше честно сказать, чем выдумать опыт.

        {$this->privacyRule()}

        Пиши на русском языке.
        PROMPT;
    }

    private function documentPrompt(): string
    {
        return <<<PROMPT
        Ты сверяешь документ кандидата — диплом, сертификат или иное свидетельство — с тем, что он
        указал о себе. Документ приложен файлом либо приведён текстом.

        ЧТО НУЖНО:
        - Прочитай документ и назови: кем выдан (institution), по какой программе или специальности
          (program), за какой год (year), на чьё имя (person), что это за документ (kind).
          Не прочитал — readable = false и остальное оставь пустым.
        - matches_resume — сходятся ли данные документа с указанным в резюме:
          yes — сходятся; partial — сходятся частично; no — противоречат;
          unknown — сравнить не с чем.
        - mismatches — конкретные расхождения, каждое отдельной строкой: «в резюме указан
          2016 год выпуска, в документе 2014», «специальность в документе — экономика,
          в резюме заявлена информатика». Без расхождений — пустой список.
        - relevance — отношение документа к работе: direct — прямо по профессии;
          related — смежное; none — не относится; unknown — не понять.
        - note — одна-две фразы для работодателя: что важно знать об этом документе.

        ЧЕГО НЕЛЬЗЯ КАТЕГОРИЧЕСКИ. Ты не можешь установить, подлинный документ или поддельный, и
        не должен этого утверждать, предполагать или намекать. Слов «подделка», «фальшивый»,
        «сомнительный документ» в твоём ответе быть не должно. Расхождение в данных — это
        расхождение в данных, и ничего больше: человек мог получить второй диплом, сменить
        фамилию или ошибиться при заполнении резюме. Твоя работа — назвать расхождение, а не
        обвинить.

        {$this->privacyRule()}

        Пиши на русском языке.
        PROMPT;
    }

    private function matrixPrompt(): string
    {
        $found = AiRequirementCheck::STATUSES['found'];
        $partial = AiRequirementCheck::STATUSES['partial'];
        $missing = AiRequirementCheck::STATUSES['missing'];

        return <<<PROMPT
        Ты сверяешь требования вакансии с резюме кандидата и его документами. По каждому
        требованию нужен статус и доказательство.

        СТАТУСЫ:
        - found («{$found}») — требование закрыто, и в резюме или документах есть прямое тому
          подтверждение;
        - partial («{$partial}») — есть близкое, но не то же самое: смежный опыт, меньший масштаб,
          другая отрасль;
        - missing («{$missing}») — подтверждения нет.

        ДОКАЗАТЕЛЬСТВО ОБЯЗАТЕЛЬНО. В поле evidence — цитата из резюме или документа, по которой
        виден твой вывод. Своими словами пересказывать нельзя: цитату работодатель прочитает в
        отчёте, а кандидат сможет оспорить. Нет цитаты — значит статус не found.
        Не выдумывай цитат: если в тексте этого нет, ставь missing.

        confidence — насколько ты уверен, от 0 до 100. Низкая уверенность при статусе found —
        признак, что нужнее partial.

        ОТВЕТЬ ПО КАЖДОМУ требованию из списка, ничего не пропуская. Ключ бери из квадратных
        скобок дословно.

        НЕСОСТЫКОВКИ (inconsistencies). Назови противоречия внутри данных: пересекающиеся периоды
        работы, навык в резюме без единого подтверждения в опыте, документ, которого нет в резюме,
        разные даты одного и того же события. Это не обвинение — это то, что стоит уточнить у
        человека вслух.

        ВОПРОСЫ (questions). Из несостыковок и из статусов partial сделай уточняющие вопросы для
        собеседования: короткие, по одному предмету, в вежливом разговорном тоне. Не «объясните
        противоречие в датах», а «вижу, что периоды в двух компаниях пересекаются — расскажите,
        как это было?». Вопросов не больше восьми.

        enough_data = false, если данных так мало, что сверять нечего.

        {$this->privacyRule()}

        Пиши на русском языке.
        PROMPT;
    }

    /**
     * Правило о персональных данных — во всех промптах разбора.
     */
    private function privacyRule(): string
    {
        return PiiRedactor::PROMPT_RULE;
    }

    // ==================== схемы ====================

    private function resumeSchema(): array
    {
        $strings = fn (int $max) => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => $max];

        return [
            'type' => 'object',
            'properties' => [
                'enough_data' => ['type' => 'boolean'],
                'profession' => ['type' => 'string'],
                'experience_years' => ['type' => 'integer'],
                'skills' => $strings(40),
                'languages' => $strings(12),
                'achievements' => $strings(12),
                'positions' => [
                    'type' => 'array', 'maxItems' => 12,
                    'items' => ['type' => 'object', 'properties' => [
                        'company' => ['type' => 'string'],
                        'role' => ['type' => 'string'],
                        'period' => ['type' => 'string'],
                        'summary' => ['type' => 'string'],
                    ]],
                ],
                'education' => [
                    'type' => 'array', 'maxItems' => 8,
                    'items' => ['type' => 'object', 'properties' => [
                        'place' => ['type' => 'string'],
                        'program' => ['type' => 'string'],
                        'year' => ['type' => 'string'],
                    ]],
                ],
            ],
            'required' => ['enough_data'],
        ];
    }

    private function documentSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'readable' => ['type' => 'boolean'],
                'kind' => ['type' => 'string', 'description' => 'Что за документ'],
                'person' => ['type' => 'string'],
                'institution' => ['type' => 'string'],
                'program' => ['type' => 'string'],
                'year' => ['type' => 'string'],
                'matches_resume' => ['type' => 'string', 'enum' => ['yes', 'partial', 'no', 'unknown']],
                'mismatches' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 8],
                'relevance' => ['type' => 'string', 'enum' => ['direct', 'related', 'none', 'unknown']],
                'note' => ['type' => 'string'],
            ],
            'required' => ['readable', 'matches_resume', 'relevance'],
        ];
    }

    /**
     * @param  array<int,string>  $keys
     */
    private function matrixSchema(array $keys): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'enough_data' => ['type' => 'boolean'],
                'checks' => [
                    'type' => 'array',
                    'maxItems' => max(1, count($keys)),
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'key' => ['type' => 'string', 'enum' => $keys ?: ['none']],
                            'status' => ['type' => 'string', 'enum' => ['found', 'partial', 'missing']],
                            'evidence' => ['type' => 'string', 'description' => 'Цитата из резюме или документа'],
                            'confidence' => ['type' => 'integer'],
                        ],
                        'required' => ['key', 'status'],
                    ],
                ],
                'inconsistencies' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 8],
                'questions' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 8],
            ],
            'required' => ['enough_data', 'checks'],
        ];
    }

    // ==================== приведение в порядок ====================

    private function line(mixed $value, int $max): string
    {
        $text = is_scalar($value) ? (string) $value : '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return Str::limit(trim($text), $max, '');
    }

    /**
     * @return array<int,string>
     */
    private function strings(mixed $values, int $count, int $max): array
    {
        if (! is_array($values)) {
            return [];
        }

        $clean = [];

        foreach (array_slice($values, 0, $count) as $value) {
            $item = $this->line($value, $max);

            if ($item !== '' && ! in_array($item, $clean, true)) {
                $clean[] = $item;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string,int>  $fields  поле => предел длины
     * @return array<int,array<string,string>>
     */
    private function rows(mixed $values, int $count, array $fields): array
    {
        if (! is_array($values)) {
            return [];
        }

        $clean = [];

        foreach (array_slice($values, 0, $count) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $item = [];

            foreach ($fields as $field => $max) {
                $item[$field] = $this->line($row[$field] ?? '', $max);
            }

            // строка, в которой всё пусто, ничего не добавляет
            if (implode('', $item) !== '') {
                $clean[] = $item;
            }
        }

        return $clean;
    }
}
