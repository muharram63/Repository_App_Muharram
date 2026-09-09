<?php

namespace App\Services\Ai;

use App\Models\ResumeDraft;
use Illuminate\Support\Str;

/**
 * Реализация помощника на Gemini.
 *
 * Здесь живут два промпта и две схемы ответа. Схема — главный инструмент
 * надёжности: модель обязана вернуть готовую структуру, поэтому мы никогда
 * не разбираем свободный текст и не гадаем, что она имела в виду.
 */
class GeminiResumeAssistant implements ResumeAssistant
{
    public function __construct(private readonly GeminiClient $client)
    {
    }

    public function available(): bool
    {
        return $this->client->configured();
    }

    public function next(ResumeDraft $draft, string $message): array
    {
        $turns = $draft->turns();
        $turns[] = ['role' => 'user', 'text' => $message];

        // Состояние резюме уходит системной инструкцией, а не подмешивается
        // в реплику человека: в чужой роли модель принимала служебный текст
        // за сказанное вслух и пересказывала его обратно в резюме.
        $answer = $this->client->structured(
            $this->dialoguePrompt().PHP_EOL.PHP_EOL
                .'Уже собранное резюме, которое нужно дополнить (JSON): '
                .json_encode($draft->data ?? [], JSON_UNESCAPED_UNICODE),
            $turns,
            $this->dialogueSchema(),
            // ход отдаёт только новые факты, поэтому ответ короткий и потолок
            // служит страховкой от срыва в цикл, а не рабочим ограничением
            maxTokens: 1500,
        );

        // Модель присылает только новые факты, накапливает их сервер. Раньше она
        // переписывала резюме целиком на каждом ходу: ответ рос вместе с
        // разговором, упирался в потолок длины и приходил обрезанным на середине
        // JSON — ход терялся с ошибкой «ответил неразборчиво».
        $resume = $this->tidyResume($this->merge(
            $draft->data ?? [],
            is_array($answer['updates'] ?? null) ? $answer['updates'] : [],
        ));

        return [
            'reply' => $this->tidyText((string) ($answer['reply'] ?? ''), 600),
            'stage' => (string) ($answer['stage'] ?? $draft->stage),
            'done' => (bool) ($answer['done'] ?? false),
            'resume' => $resume,
        ];
    }

    /**
     * Складывает новые факты с уже собранными.
     *
     * Скалярные поля перезаписываются, когда пришло непустое значение (так
     * работает исправление раннего ответа), списки объединяются, а записи об
     * опыте и учёбе добавляются, если такой ещё не было.
     */
    private function merge(array $current, array $updates): array
    {
        foreach (['name', 'headline', 'desired_position', 'city', 'summary', 'experience_years'] as $field) {
            if (filled($updates[$field] ?? null)) {
                $current[$field] = $updates[$field];
            }
        }

        foreach (['achievements', 'skills', 'languages'] as $field) {
            if (is_array($updates[$field] ?? null)) {
                $current[$field] = array_merge($current[$field] ?? [], $updates[$field]);
            }
        }

        // ключ записи — то, что делает её уникальной для человека
        $keys = [
            'positions' => ['company', 'role'],
            'education' => ['place', 'program'],
            'extras' => ['title'],
        ];

        foreach ($keys as $field => $parts) {
            if (! is_array($updates[$field] ?? null)) {
                continue;
            }

            $rows = is_array($current[$field] ?? null) ? $current[$field] : [];

            foreach ($updates[$field] as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $key = fn (array $item) => mb_strtolower(implode('|', array_map(
                    fn (string $part) => trim((string) ($item[$part] ?? '')), $parts,
                )));

                // та же запись, дополненная новыми подробностями, заменяет прежнюю
                foreach ($rows as $i => $existing) {
                    if (is_array($existing) && $key($existing) === $key($row)) {
                        $rows[$i] = array_merge($existing, array_filter($row, fn ($v) => filled($v)));

                        continue 2;
                    }
                }

                $rows[] = $row;
            }

            $current[$field] = $rows;
        }

        return $current;
    }

    public function finalize(ResumeDraft $draft): array
    {
        $answer = $this->client->structured(
            $this->finalPrompt(),
            [['role' => 'user', 'text' => 'Собранное в разговоре резюме (JSON):'.PHP_EOL
                .json_encode($draft->data ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)]],
            $this->finalSchema(),
            maxTokens: 2500,
        );

        return [
            // 255 — предел колонок в таблице резюме, обрезаем здесь, а не в БД
            'profession' => $this->tidyText((string) ($answer['profession'] ?? ''), 255),
            'desired_position' => $this->tidyText((string) ($answer['desired_position'] ?? ''), 255),
            'experience_years' => min(70, max(0, (int) ($answer['experience_years'] ?? 0))),
            'desired_salary' => max(0, (int) ($answer['desired_salary'] ?? 0)),
            'skills' => $this->tidyText((string) ($answer['skills'] ?? ''), 2000),
            'languages' => $this->tidyText((string) ($answer['languages'] ?? ''), 255),
            'place_work' => $this->tidyText((string) ($answer['place_work'] ?? ''), 255),
            'description' => $this->tidyText((string) ($answer['description'] ?? ''), 4000),
        ];
    }

    /**
     * Приводит резюме в порядок перед сохранением.
     *
     * Модель может сорваться в повтор и вернуть поле, забитое одной фразой
     * сотни раз, — такой черновик уже нельзя ни читать, ни показывать. Здесь
     * стоит последний рубеж: длины, количества и повторы режутся независимо
     * от того, что прислал провайдер.
     */
    private function tidyResume(array $resume): array
    {
        $text = fn ($value, int $max) => $this->tidyText((string) ($value ?? ''), $max);

        $strings = function ($items, int $count, int $max) {
            if (! is_array($items)) {
                return [];
            }

            $clean = [];

            foreach (array_slice($items, 0, $count) as $item) {
                $value = $this->tidyText((string) (is_scalar($item) ? $item : ''), $max);
                // повторы в списках так же бессмысленны, как и в тексте
                if ($value !== '' && ! in_array($value, $clean, true)) {
                    $clean[] = $value;
                }
            }

            return $clean;
        };

        // модель может прислать строку там, где схема просила массив,
        // — на этом падал разбор, поэтому тип проверяем до срезки
        $rows = fn ($value, int $count) => is_array($value) ? array_slice($value, 0, $count) : [];

        $positions = [];

        foreach ($rows($resume['positions'] ?? null, 8) as $job) {
            if (! is_array($job)) {
                continue;
            }

            $positions[] = array_filter([
                'company' => $text($job['company'] ?? null, 160),
                'role' => $text($job['role'] ?? null, 160),
                'period' => $text($job['period'] ?? null, 60),
                'bullets' => $strings($job['bullets'] ?? [], 6, 300),
            ]);
        }

        $education = [];

        foreach ($rows($resume['education'] ?? null, 5) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $education[] = array_filter([
                'place' => $text($item['place'] ?? null, 160),
                'program' => $text($item['program'] ?? null, 160),
                'year' => $text($item['year'] ?? null, 40),
            ]);
        }

        $extras = [];

        foreach ($rows($resume['extras'] ?? null, 5) as $block) {
            if (! is_array($block)) {
                continue;
            }

            $items = $strings($block['items'] ?? [], 8, 300);

            if ($items !== []) {
                $extras[] = ['title' => $text($block['title'] ?? null, 80), 'items' => $items];
            }
        }

        return array_filter([
            'name' => $text($resume['name'] ?? null, 120),
            'headline' => $text($resume['headline'] ?? null, 160),
            'desired_position' => $text($resume['desired_position'] ?? null, 160),
            'city' => $text($resume['city'] ?? null, 120),
            // краткое резюме о себе: три предложения, не эссе
            'summary' => $text($resume['summary'] ?? null, 600),
            'experience_years' => min(70, max(0, (int) ($resume['experience_years'] ?? 0))) ?: null,
            'positions' => $positions,
            'achievements' => $strings($resume['achievements'] ?? [], 10, 300),
            'skills' => $strings($resume['skills'] ?? [], 30, 60),
            'languages' => $strings($resume['languages'] ?? [], 10, 60),
            'education' => $education,
            'extras' => $extras,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * Убирает зацикленный текст и обрезает по длине.
     *
     * Повторяющиеся предложения схлопываются в одно: именно так выглядит срыв
     * модели в цикл, и простое обрезание оставило бы начало этой каши.
     */
    private function tidyText(string $text, int $max): string
    {
        $text = trim(preg_replace('/[ \t]+/u', ' ', $text) ?? '');

        if ($text === '') {
            return '';
        }

        $unique = [];

        foreach (preg_split('/(?<=[.!?])\s+|\R+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            $part = trim($part);
            $key = mb_strtolower($part);

            if ($part !== '' && ! isset($unique[$key])) {
                $unique[$key] = $part;
            }
        }

        return Str::limit(implode(' ', $unique), $max, '');
    }

    /**
     * Язык интерфейса — язык разговора: площадка трёхъязычная.
     */
    private function language(): string
    {
        return match (app()->getLocale()) {
            'en' => 'английском',
            'tg' => 'таджикском',
            default => 'русском',
        };
    }

    private function dialoguePrompt(): string
    {
        $language = $this->language();

        return <<<PROMPT
        Ты — карьерный консультант сервиса Workio. Ты помогаешь соискателю собрать резюме в живом разговоре.

        ГЛАВНОЕ ПРАВИЛО — ты вытягиваешь конкретику.
        Ответы вроде «работал в компании», «занимался разработкой», «ответственный и коммуникабельный»
        не годятся. Каждый раз, когда человек отвечает расплывчато, задавай ОДИН уточняющий вопрос и
        показывай, какого ответа ждёшь: что именно он делал, на каких инструментах, что получилось,
        есть ли результат в цифрах. Пример: «Что именно вы делали в этом проекте и что изменилось после
        вашей работы — например, сколько времени или денег это сэкономило?»
        Никогда не выдумывай за человека факты, цифры, компании и даты. В резюме попадает только то,
        что он сказал сам.

        ПОРЯДОК РАЗГОВОРА, поле stage:
        1. identity — как зовут, кем себя видит: роль, желаемая должность, город.
        2. experience — последнее место работы: компания, должность, период, обязанности.
        3. achievements — конкретные результаты, по возможности в цифрах.
        4. skills — инструменты, технологии, языки.
        5. education — образование и курсы.
        6. extras — по желанию: проекты, сертификаты, портфолио.
        7. done — данных достаточно для резюме.

        КАК ВЕСТИ РАЗГОВОР:
        - Один вопрос за раз. Никогда не задавай два вопроса в одном сообщении.
        - Реплика короткая, 1–3 предложения: сначала живая человеческая реакция, потом вопрос.
        - Просит пропустить («не знаю», «пропусти», «дальше») — не спорь, переходи к следующему этапу.
        - Исправляет сказанное раньше — обнови соответствующее поле, а не добавляй рядом второе такое же.
        - Не более двух уточнений подряд по одному этапу: дальше двигайся вперёд с тем, что есть.
        - Когда собраны опыт, навыки и хотя бы одно достижение — ставь stage = done, done = true
          и предложи собрать финальную версию.

        ПОЛЕ updates — ТОЛЬКО то новое, что ты узнал из последнего ответа человека. Уже собранное
        повторять не нужно: сервер сам складывает новое с прежним. Ничего не узнал — оставь updates пустым.
        Если человек исправляет сказанное раньше, положи в updates исправленное значение — оно заменит старое.

        КУДА КЛАСТЬ УСЛЫШАННОЕ. Как только человек назвал факт, он идёт в своё поле, а не в summary:
        - имя → name; профессия («менеджер проектов») → headline; желаемая должность → desired_position;
        - город → city; годы опыта числом → experience_years;
        - место работы → новая запись в positions: company, role, period, а обязанности и результаты
          отдельными строками в bullets;
        - измеримые результаты → achievements; инструменты и технологии → skills; языки → languages;
        - учёба и курсы → education; проекты, сертификаты, портфолио → extras.
        summary — только короткая выжимка о специалисте, и она не заменяет остальные поля.

        СТРОГО О ДЛИНЕ И СОДЕРЖИМОМ resume:
        - summary — не длиннее трёх предложений. Это карточка специалиста, а не сочинение.
        - В полях резюме нет обращений к человеку, вопросов, приветствий, подписей и пометок
          вроде «(конец)» или «жду ответа». Всё, что адресовано человеку, живёт только в поле reply.
        - Никогда не повторяй одну и ту же фразу дважды. Если сказать нечего — оставь поле пустым.
        - Пока человек не назвал факт, поля нет. Заполнитель хуже пустоты.

        Разговаривай на {$language} языке.
        PROMPT;
    }

    private function finalPrompt(): string
    {
        $language = $this->language();

        return <<<PROMPT
        Ты — карьерный консультант сервиса Workio. Из собранных в разговоре данных собери финальную
        версию резюме для публикации на площадке поиска работы.

        Правила:
        - Профессиональный, спокойный тон. Без канцелярита и без рекламных восклицаний.
        - Не добавляй ничего, чего нет в данных. Пустое поле лучше выдуманного.
        - description — готовый текст резюме: короткий абзац о специалисте, затем опыт по местам работы
          с результатами, затем ключевые навыки. Обычным текстом, разделяй абзацы пустой строкой,
          без markdown и без заголовков в звёздочках.
        - skills и languages — перечисление через запятую.
        - experience_years — целое число лет опыта; если не звучало, поставь 0.
        - desired_salary — число, если человек называл сумму; иначе 0.
        - place_work — последнее место работы, если оно известно.
        - Никаких обращений к человеку, вопросов, подписей и служебных пометок: это готовый документ.
        - Ни одна фраза не повторяется дважды. Пустое поле лучше воды.

        Пиши на {$language} языке.
        PROMPT;
    }

    /**
     * Схема хода диалога. Обязательны только служебные поля: незаполненные
     * разделы резюме должны отсутствовать, а не заполняться выдумкой.
     */
    private function dialogueSchema(): array
    {
        // потолки в самой схеме: они дешевле любых уговоров в промпте
        $strings = ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 20];

        return [
            'type' => 'object',
            'properties' => [
                'reply' => [
                    'type' => 'string',
                    'description' => 'Реплика ассистента: короткая реакция и один вопрос, не более трёх предложений',
                ],
                'stage' => [
                    'type' => 'string',
                    'enum' => ['identity', 'experience', 'achievements', 'skills', 'education', 'extras', 'done'],
                ],
                'done' => ['type' => 'boolean'],
                'updates' => [
                    'type' => 'object',
                    'description' => 'Только новые факты из последнего ответа; прежнее не повторять',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'headline' => ['type' => 'string', 'description' => 'Профессия, например «Backend-разработчик»'],
                        'desired_position' => ['type' => 'string'],
                        'city' => ['type' => 'string'],
                        'summary' => [
                            'type' => 'string',
                            'description' => 'Карточка специалиста: не более трёх предложений, без обращений и вопросов',
                        ],
                        'experience_years' => ['type' => 'integer'],
                        'positions' => [
                            'type' => 'array',
                            'maxItems' => 8,
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'company' => ['type' => 'string'],
                                    'role' => ['type' => 'string'],
                                    'period' => ['type' => 'string'],
                                    'bullets' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 6],
                                ],
                            ],
                        ],
                        'achievements' => $strings,
                        'skills' => $strings,
                        'languages' => $strings,
                        'education' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'place' => ['type' => 'string'],
                                    'program' => ['type' => 'string'],
                                    'year' => ['type' => 'string'],
                                ],
                            ],
                        ],
                        'extras' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'title' => ['type' => 'string'],
                                    'items' => ['type' => 'array', 'items' => ['type' => 'string']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            // updates не обязателен: ход, из которого нечего извлечь, — это норма
            'required' => ['reply', 'stage', 'done'],
        ];
    }

    /**
     * Схема финальной версии повторяет колонки таблицы резюме: то, что вернёт
     * модель, пользователь правит руками и сразу сохраняет.
     */
    private function finalSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'profession' => ['type' => 'string'],
                'desired_position' => ['type' => 'string'],
                'experience_years' => ['type' => 'integer'],
                'desired_salary' => ['type' => 'integer'],
                'skills' => ['type' => 'string', 'description' => 'Перечисление через запятую'],
                'languages' => ['type' => 'string', 'description' => 'Перечисление через запятую'],
                'place_work' => ['type' => 'string'],
                'description' => ['type' => 'string', 'description' => 'Готовый текст резюме'],
            ],
            'required' => ['profession', 'desired_position', 'description'],
        ];
    }
}
