<?php

namespace App\Services\Ai;

use App\Models\AiInterviewCriterion;
use App\Models\Vacancy;
use Illuminate\Support\Str;

/**
 * Критерии оценки на Gemini.
 *
 * Промпт требует от модели того же, чего требует от неё разбор совпадения:
 * конкретики. «Хорошие коммуникативные навыки» — не критерий, потому что по
 * нему нельзя ни задать вопрос, ни проверить ответ. «Умение объяснить
 * технический выбор нетехническому заказчику» — критерий.
 */
class GeminiCriteriaAdvisor implements CriteriaAdvisor
{
    /** Сколько критериев просим. Больше десятка человек уже не проверяет глазами. */
    public const MAX_CRITERIA = 8;

    /** Сколько знаков описания вакансии отдаём: дальше растёт только цена. */
    private const LIMIT = 3000;

    public function __construct(private readonly AiJournal $journal)
    {
    }

    public function available(): bool
    {
        return $this->journal->available();
    }

    public function suggest(Vacancy $vacancy): array
    {
        $answer = $this->journal->ask(
            purpose: 'criteria_advice',
            system: $this->prompt(),
            turns: [['role' => 'user', 'text' => $this->describe($vacancy)]],
            schema: $this->schema(),
            rules: $this->rules(),
            options: AiJournal::options('scoring'),
            /*
             * Бюджет с запасом, и это измерено. При 1800 первая попытка
             * оборвалась на полуслове: пять критериев с описаниями заняли 1986
             * токенов выхода, а восемь займут больше. Повтор потом проходил, но
             * стоил лишних 25 секунд ожидания и вдвое больше токенов — дешевле
             * сразу дать место.
             */
            maxTokens: 3200,
        );

        return $this->tidy($answer['criteria'] ?? []);
    }

    /**
     * Правила проверки ответа.
     *
     * Строже, чем схема: схема не умеет требовать непустую строку и не
     * проверит, что вид критерия — одно из двух известных слов. А на этих
     * значениях потом стоят ворота решения, поэтому проверяем всерьёз.
     */
    private function rules(): array
    {
        return [
            'criteria' => 'required|array|min:1|max:'.self::MAX_CRITERIA,
            'criteria.*.label' => 'required|string|min:2|max:120',
            'criteria.*.description' => 'nullable|string|max:400',
            'criteria.*.kind' => 'required|in:must,nice',
            'criteria.*.weight' => 'required|integer|min:1|max:100',
        ];
    }

    /**
     * Приводит предложение к пригодному виду.
     *
     * Ключ собираем сами, а не просим у модели: он должен быть латинским
     * слагом, уникальным в пределах вакансии, — на него ссылаются оценки
     * ответов. Модель же выдаёт ключи то по-русски, то с пробелами.
     *
     * @return array<int,array{key:string,label:string,description:string,kind:string,weight:int}>
     */
    private function tidy(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $clean = [];
        $keys = [];

        foreach (array_slice($rows, 0, self::MAX_CRITERIA) as $position => $row) {
            if (! is_array($row) || blank($row['label'] ?? null)) {
                continue;
            }

            $label = Str::limit(trim((string) $row['label']), 120, '');
            $key = $this->key($label, $keys);
            $keys[] = $key;

            $clean[] = [
                'key' => $key,
                'label' => $label,
                'description' => Str::limit(trim((string) ($row['description'] ?? '')), 400, ''),
                'kind' => ($row['kind'] ?? 'must') === 'nice' ? 'nice' : 'must',
                'weight' => max(1, min(100, (int) ($row['weight'] ?? 10))),
                'position' => $position,
            ];
        }

        return $clean;
    }

    /**
     * Латинский слаг из названия критерия.
     *
     * Русское название слагом не становится — Str::slug вырезал бы кириллицу
     * целиком и оставил пустую строку. Поэтому при пустом результате берём
     * отпечаток: ключ должен быть устойчивым, а не красивым.
     *
     * @param  array<int,string>  $taken
     */
    private function key(string $label, array $taken): string
    {
        $slug = Str::slug($label, '_');

        if ($slug === '') {
            $slug = 'k_'.substr(md5($label), 0, 8);
        }

        // обрезка по длине попадает в середину слова и оставляет хвостовое
        // подчёркивание — ключ от него не ломается, но выглядит недоделанным
        $slug = rtrim(Str::limit($slug, 34, ''), '_');
        $key = $slug;
        $suffix = 2;

        // одинаковые названия у двух критериев — не повод падать на unique
        while (in_array($key, $taken, true)) {
            $key = Str::limit($slug, 32, '').'_'.$suffix++;
        }

        return $key;
    }

    /**
     * Что знает о вакансии площадка. Пустые поля не отправляем: модель
     * принимает «Требуемые языки:» без значения за требование не указывать язык.
     */
    private function describe(Vacancy $vacancy): string
    {
        $fields = collect([
            'Должность' => $vacancy->title,
            'Требуемые навыки' => $vacancy->skill,
            'Требуемые языки' => $vacancy->languages,
            'Требуемый опыт' => $vacancy->experience_required,
            'Занятость' => $vacancy->employment_type,
            'График' => $vacancy->work_schedule,
            'Описание' => Str::limit((string) $vacancy->description, self::LIMIT, ''),
        ]);

        return 'ВАКАНСИЯ'.PHP_EOL.$fields
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value, $label) => $label.': '.$value)
            ->implode(PHP_EOL);
    }

    private function prompt(): string
    {
        $max = self::MAX_CRITERIA;
        $must = AiInterviewCriterion::KINDS['must'];
        $nice = AiInterviewCriterion::KINDS['nice'];

        // Критерии читает работодатель, поэтому язык — его интерфейса, а не
        // вакансии: таджикская вакансия при русском интерфейсе должна дать
        // критерии по-русски. Так же выбирает язык разбор совпадения.
        $language = match (app()->getLocale()) {
            'en' => 'английском',
            'tg' => 'таджикском',
            default => 'русском',
        };

        return <<<PROMPT
        Ты — опытный HR-специалист. По описанию вакансии предложи, по каким критериям оценивать
        кандидата на собеседовании. Их подтвердит работодатель, поэтому каждый критерий должен быть
        понятен человеку без пояснений.

        ЖЁСТКИЕ ПРАВИЛА:
        - От 3 до {$max} критериев. Меньше трёх — оценка выйдет грубой, больше восьми человек уже не
          проверяет глазами.
        - Каждый критерий проверяем вопросом или заданием. «Хорошие коммуникативные навыки»,
          «стрессоустойчивость», «ответственность» — не критерии: по ним нельзя задать вопрос и
          нельзя проверить ответ. Годится «умение объяснить технический выбор нетехническому
          заказчику», «работа с чужим кодом без документации», «расчёт сметы по неполным данным».
        - Критерии берутся из текста вакансии, а не из общих представлений о профессии. Если
          требование в вакансии не названо, не выдумывай его.
        - kind = must — без этого работать нельзя, требование обязательное. Подпись: «{$must}».
          kind = nice — полезно, но не решает. Подпись: «{$nice}».
          Обязательных не больше четырёх: когда обязательно всё, отбор превращается в лотерею.
        - weight — важность от 1 до 100. Сумму подгонять не нужно, систему интересует соотношение.
        - label — короткое название, 2-6 слов, без «умение» в начале каждого пункта.
        - description — одна-две фразы: что именно проверяем и что считаем хорошим ответом.
          Это увидит работодатель, а не кандидат.

        ЧЕГО НЕ ДЕЛАТЬ. Не предлагай критериев про пол, возраст, национальность, гражданство,
        религию, семейное положение, внешность и здоровье. Это не критерии пригодности к работе, и
        оценивать по ним запрещено. Про наличие диплома как таковое тоже не надо: проверяется
        умение, а корочка — не умение.

        Пиши на {$language} языке, даже если вакансия описана на другом.
        PROMPT;
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'criteria' => [
                    'type' => 'array',
                    'maxItems' => self::MAX_CRITERIA,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'label' => ['type' => 'string', 'description' => 'Короткое название, 2-6 слов'],
                            'description' => ['type' => 'string', 'description' => 'Что проверяем, одна-две фразы'],
                            'kind' => ['type' => 'string', 'enum' => ['must', 'nice']],
                            'weight' => ['type' => 'integer', 'description' => 'Важность от 1 до 100'],
                        ],
                        'required' => ['label', 'kind', 'weight'],
                    ],
                ],
            ],
            'required' => ['criteria'],
        ];
    }
}
