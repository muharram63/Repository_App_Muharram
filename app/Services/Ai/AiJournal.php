<?php

namespace App\Services\Ai;

use App\Models\AiInteraction;
use App\Models\AiInterview;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Единственный вход к модели для всего модуля собеседования.
 *
 * Здесь собрано то, что иначе пришлось бы повторять в каждом сервисе: проверка
 * ответа, повтор при негодном ответе, запись в аудит и выбор модели под задачу.
 * Сервисы получаются короткими — промпт, схема, правила проверки.
 *
 * Зачем проверять ответ, если схема уже передана модели. Схема — просьба, а не
 * гарантия: модель может вернуть балл 7 при шкале до 5, пустую строку там, где
 * ждали название навыка, или список из одного элемента вместо трёх. На таких
 * ответах строится решение о человеке, поэтому проверка обязательна, а не
 * желательна.
 *
 * Почему при негодном ответе ровно один повтор. Первая неудача обычно
 * случайность и лечится повтором. Вторая — признак, что промпт или схема не
 * сходятся с задачей, и третья попытка даст то же самое, только дороже. Дальше
 * честнее признать, что система не берётся решать: наверх уходит
 * AiInvalidAnswerException, собеседование переходит на ручную проверку, и
 * кандидату не отправляется ни отказ, ни приглашение.
 */
class AiJournal
{
    /**
     * Сколько раз пробуем получить годный ответ. Двойка — это одна попытка и
     * один повтор.
     */
    public const ATTEMPTS = 2;

    public function __construct(private readonly GeminiClient $client)
    {
    }

    public function available(): bool
    {
        return $this->client->configured();
    }

    /**
     * Спросить модель и получить проверенный ответ.
     *
     * @param  string  $purpose  ключ из AiInteraction::PURPOSES
     * @param  array<int,array{role:string,text:string}>  $turns
     * @param  array  $schema  схема для модели
     * @param  array<string,mixed>  $rules  правила проверки ответа (как у Laravel)
     * @param  array{model?:string,temperature?:float,thinking_level?:string}  $options
     * @return array проверенный ответ
     *
     * @throws AiUnavailableException  сервис недоступен — можно повторить позже
     * @throws AiInvalidAnswerException  ответу нельзя верить — ручная проверка
     */
    public function ask(
        string $purpose,
        string $system,
        array $turns,
        array $schema,
        array $rules = [],
        ?AiInterview $interview = null,
        array $options = [],
        int $maxTokens = 2000,
    ): array {
        return $this->askDetailed(...func_get_args())['data'];
    }

    /**
     * То же, но вместе с фактом исполнения кода.
     *
     * Нужно проверке тестового задания: там важно не только что ответила
     * модель, но и что на самом деле вывела запущенная программа. При
     * расхождении верить надо выводу.
     *
     * @return array{data:array,execution:array<int,array>}
     *
     * @throws AiUnavailableException
     * @throws AiInvalidAnswerException
     */
    public function askDetailed(
        string $purpose,
        string $system,
        array $turns,
        array $schema,
        array $rules = [],
        ?AiInterview $interview = null,
        array $options = [],
        int $maxTokens = 2000,
    ): array {
        $attempt = 0;
        $lastErrors = [];

        while ($attempt < self::ATTEMPTS) {
            $attempt++;
            $startedAt = microtime(true);

            try {
                $answer = $this->client->structuredDetailed(
                    $system,
                    // на повторе просим ровно то же: подсказка о прошлой
                    // ошибке подталкивала бы модель угадывать, а не отвечать
                    $turns,
                    $schema,
                    $this->budget($maxTokens, $options),
                    $options,
                );
            } catch (AiUnreadableAnswerException $e) {
                /*
                 * Ответ пришёл, но это не JSON — чаще всего оборванный на
                 * середине. Считаем негодным ответом, а не сбоем связи: такой
                 * повторяется, и второй попытки обычно достаточно.
                 */
                $this->record($purpose, $interview, $system, $turns, null, $startedAt, 'invalid', $e->getMessage(), $options);

                $lastErrors = ['ответ' => ['модель вернула не JSON']];

                Log::warning('Ответ модели не прочитать', ['purpose' => $purpose, 'attempt' => $attempt]);

                continue;
            } catch (AiUnavailableException $e) {
                // сбой связи или лимита — это не негодный ответ, и решать за
                // него ручной проверкой неправильно: пусть выше решают, ждать
                // ли и сколько
                $this->record($purpose, $interview, $system, $turns, null, $startedAt, 'failed', $e->getMessage(), $options);

                throw $e;
            }

            $validated = $this->check($answer['data'], $rules);

            if ($validated['ok']) {
                $this->record(
                    $purpose, $interview, $system, $turns, $answer, $startedAt, 'ok', null, $options,
                );

                return [
                    'data' => $validated['data'],
                    'execution' => $answer['execution'] ?? [],
                ];
            }

            $lastErrors = $validated['errors'];

            $this->record(
                $purpose, $interview, $system, $turns, $answer, $startedAt, 'invalid',
                $this->firstError($lastErrors), $options,
            );

            Log::warning('Ответ модели не прошёл проверку', [
                'purpose' => $purpose,
                'attempt' => $attempt,
                'errors' => array_keys($lastErrors),
            ]);
        }

        throw new AiInvalidAnswerException(
            'Модель дважды ответила негодно, решение нужно проверить вручную.',
            $lastErrors,
            $purpose,
        );
    }

    /**
     * Модель под задачу. Ключи совпадают с назначением вызова, чтобы в сервисах
     * не встречалось имён моделей — иначе смена модели превращается в обход
     * десятка файлов.
     *
     * Оценкам достаётся низкая температура: один и тот же ответ кандидата не
     * должен получать разный балл при повторном разборе.
     */
    public static function options(string $kind): array
    {
        return match ($kind) {
            'chat' => [
                'model' => config('services.gemini.model'),
                'temperature' => 0.4,
                'thinking_level' => 'low',
            ],
            'scoring' => [
                'model' => config('services.gemini.model_scoring'),
                'temperature' => 0.1,
                // оценка стоит размышления: поверхностный разбор ответа
                // кандидата — это и есть та самая ошибка, которой мы боимся
                'thinking_level' => 'medium',
            ],
            'vision' => [
                'model' => config('services.gemini.model_vision'),
                'temperature' => 0.1,
                'thinking_level' => 'medium',
            ],
            default => [],
        };
    }

    /**
     * Запас токенов на размышления модели.
     *
     * Потолок max_output_tokens считает не только ответ, но и размышления.
     * Это выяснилось живым вызовом: при thinking_level = medium и потолке 400
     * модель израсходовала бюджет на размышление, а JSON оборвался на первой
     * же скобке — в логе остался ответ из одного символа «{».
     *
     * Поэтому вызывающий код задаёт длину ответа, а запас на размышления
     * добавляется здесь: правило должно жить в одном месте, иначе каждый новый
     * сервис будет натыкаться на обрыв заново.
     */
    private function budget(int $maxTokens, array $options): int
    {
        return $maxTokens + match ($options['thinking_level'] ?? 'low') {
            'medium' => 1024,
            'high' => 2048,
            default => 0,
        };
    }

    /**
     * Проверка ответа правилами Laravel.
     *
     * @return array{ok:bool,data:array,errors:array}
     */
    private function check(array $data, array $rules): array
    {
        if ($rules === []) {
            return ['ok' => true, 'data' => $data, 'errors' => []];
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return ['ok' => false, 'data' => $data, 'errors' => $validator->errors()->toArray()];
        }

        /*
         * Возвращаем исходный массив, а не validated().
         *
         * validated() оставляет только поля, упомянутые в правилах, и поля без
         * правил молча исчезали бы. Для разбора это опасно: в ответе есть
         * вложенные структуры, описывать каждый ключ правилами незачем, а
         * терять их — нельзя.
         */
        return ['ok' => true, 'data' => $data, 'errors' => []];
    }

    private function firstError(array $errors): ?string
    {
        foreach ($errors as $field => $messages) {
            return mb_substr($field.': '.(is_array($messages) ? reset($messages) : $messages), 0, 250);
        }

        return null;
    }

    /**
     * Запись в аудит.
     *
     * Промпт и ответ храним целиком: смысл таблицы в том, чтобы через месяцы
     * можно было показать, на каком основании принято решение. Без этого
     * «так решил ИИ» остаётся утверждением, которое нечем проверить.
     *
     * Сбой самой записи проглатывается, и это не небрежность. Аудит —
     * побочная запись, а вокруг него стоят пути, которые существуют ровно для
     * того, чтобы не падать: «модель недоступна, попробуйте позже», «оставим
     * шаблонный текст». Если бы запись в аудит могла бросить исключение, она
     * ломала бы именно эти пути — кандидат получал бы пятисотую вместо
     * понятного сообщения. Причина попадёт в лог, и это правильное место.
     *
     * @param  array{data:array,usage:array,model:string}|null  $answer
     */
    private function record(
        string $purpose,
        ?AiInterview $interview,
        string $system,
        array $turns,
        ?array $answer,
        float $startedAt,
        string $status,
        ?string $error,
        array $options,
    ): void {
        try {
            $this->write($purpose, $interview, $system, $turns, $answer, $startedAt, $status, $error, $options);
        } catch (\Throwable $e) {
            Log::error('Не удалось записать обращение к модели в аудит', [
                'purpose' => $purpose,
                'interview' => $interview?->id,
                'reason' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Собственно вставка. Отдельным методом, чтобы поймать её целиком.
     */
    private function write(
        string $purpose,
        ?AiInterview $interview,
        string $system,
        array $turns,
        ?array $answer,
        float $startedAt,
        string $status,
        ?string $error,
        array $options,
    ): void {
        AiInteraction::create([
            'ai_interview_id' => $interview?->id,
            'vacancy_id' => $interview?->vacancy_id,
            'purpose' => $purpose,
            'model' => $answer['model'] ?? ($options['model'] ?? config('services.gemini.model', '')),
            'request' => $this->transcript($system, $turns),
            'response' => $answer
                ? json_encode($answer['data'], JSON_UNESCAPED_UNICODE)
                : null,
            'tokens_in' => $answer['usage']['input'] ?? null,
            // размышления входят в выход по деньгам, поэтому складываем:
            // иначе отчёт о расходе занижал бы стоимость оценок
            'tokens_out' => $answer
                ? (int) ($answer['usage']['output'] ?? 0) + (int) ($answer['usage']['thought'] ?? 0)
                : null,
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'status' => $status,
            // колонка на 255 символов, а сюда приходит и текст исключения
            'error' => $error === null ? null : mb_substr($error, 0, 250),
        ]);
    }

    /**
     * Промпт и реплики в читаемом виде — так запись в аудите можно просто
     * прочитать глазами, не разбирая JSON.
     */
    private function transcript(string $system, array $turns): string
    {
        $lines = ['[инструкция] '.$system];

        foreach ($turns as $turn) {
            $role = ($turn['role'] ?? 'user') === 'model' ? 'модель' : 'вход';

            // Вложение отмечаем, но само содержимое в лог не пишем: база
            // распухла бы от base64 документов, а для объяснения решения
            // достаточно знать, что файл был и какого он типа.
            foreach ($turn['files'] ?? [] as $file) {
                $lines[] = '[файл] '.($file['mime'] ?? '?').', '
                    .round(strlen((string) ($file['data'] ?? '')) * 3 / 4 / 1024).' КБ';
            }

            $lines[] = '['.$role.'] '.($turn['text'] ?? '');
        }

        return implode(PHP_EOL.PHP_EOL, $lines);
    }
}
