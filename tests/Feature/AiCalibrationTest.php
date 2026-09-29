<?php

/*
 * Калибровка отбора на десяти заготовленных кандидатах.
 *
 * Здесь проверяется не модель, а правила: веса, пороги и ворота обязательных
 * требований. Модель подменена оценщиком, который судит по содержанию текста —
 * развёрнут ли ответ, есть ли в нём числа и предметные слова. Он груб, но не
 * знает, кто перед ним: ярлыка «сильный» в профиле для него нет, он читает те
 * же самые слова, что прочитала бы настоящая модель.
 *
 * Живой прогон — команда ai:calibrate. Здесь же доказывается, что упряжь
 * работает и что при правдоподобных оценках отбор действительно разделяет
 * людей, а не пропускает всех подряд.
 */

use App\Models\AiCandidateDocument;
use App\Models\AiInterview;
use App\Models\AiInterviewCriterion;
use App\Models\AiTestTask;
use App\Models\Resume;
use App\Models\User;
use App\Services\Ai\DecisionWriter;
use App\Services\Ai\DocumentAuditor;
use App\Services\Ai\Interviewer;
use App\Services\Ai\TaskExaminer;
use App\Services\Scoring\CalibrationProfiles;
use App\Services\Scoring\CalibrationRunner;
use App\Services\Scoring\DecisionEngine;
use Illuminate\Support\Facades\Queue;

/**
 * Насколько ответ похож на ответ: 0–5.
 *
 * Ровно те признаки, по которым человек отличает рассказ о работе от общих
 * слов: развёрнутость, конкретные числа, предметные термины. Текст без единого
 * предметного слова штрафуется — длина сама по себе не достоинство.
 */
function calibQuality(string $text): int
{
    $terms = [
        'laravel', 'php', 'sql', 'индекс', 'очеред', 'api', 'rest', 'redis', 'explain',
        'партицион', 'идемпотент', 'вебхук', 'ревью', 'миграци', 'кеш', 'токен',
        'событи', 'запрос', 'таблиц', 'чанк', 'нагруз', 'пагинац', 'курсор',
    ];

    $low = mb_strtolower($text);
    $hits = 0;

    foreach ($terms as $term) {
        if (str_contains($low, $term)) {
            $hits++;
        }
    }

    $length = mb_strlen($text);
    $score = 0;

    $length >= 90 and $score++;
    $length >= 200 and $score++;
    preg_match('/\d/', $text) and $score++;
    $hits >= 1 and $score++;
    $hits >= 3 and $score++;

    // вода без единого предметного слова — это не ответ, какой бы длины она ни была
    if ($hits === 0) {
        $score -= 2;
    }

    return max(0, min(5, $score));
}

/**
 * Разбор документов: ищет в резюме следы требования.
 *
 * Два разных следа и больше — «найдено», один — «частично», ни одного — «нет».
 * Настоящая модель делает то же самое, только умнее: ищет доказательство и
 * приводит цитату.
 */
class CalibAuditor implements DocumentAuditor
{
    public const MARKERS = [
        'php' => ['laravel', 'php', 'symfony'],
        'sql' => ['sql', 'mysql', 'postgres', 'индекс', 'clickhouse'],
        'api' => ['api', 'rest', 'graphql', 'вебхук'],
        'team' => ['команд', 'ревью', 'ревю', 'лид', 'обучал'],
    ];

    public function available(): bool
    {
        return true;
    }

    public function parseResume(Resume $resume): array
    {
        return [
            'enough_data' => filled($resume->description),
            'profession' => (string) $resume->profession,
            'experience_years' => (int) $resume->experience_years,
            'skills' => array_map('trim', explode(',', (string) $resume->skills)),
            'languages' => [(string) $resume->languages],
            'positions' => [],
            'education' => [],
            'achievements' => array_filter([(string) $resume->description, (string) $resume->place_work]),
        ];
    }

    public function auditDocument(AiCandidateDocument $document, array $parsedResume): array
    {
        return [
            'readable' => true, 'kind' => $document->kind, 'person' => null,
            'institution' => null, 'program' => null, 'year' => null,
            'matches_resume' => 'yes', 'mismatches' => [], 'relevance' => 'related', 'note' => '',
        ];
    }

    public function buildMatrix($criteria, array $parsedResume, array $documentFindings): array
    {
        $text = mb_strtolower(implode(' ', [
            $parsedResume['profession'],
            implode(' ', $parsedResume['skills']),
            implode(' ', $parsedResume['achievements']),
        ]));

        $checks = [];

        foreach ($criteria as $criterion) {
            $hits = 0;

            foreach (self::MARKERS[$criterion->key] ?? [] as $marker) {
                if (str_contains($text, $marker)) {
                    $hits++;
                }
            }

            $checks[] = [
                'key' => $criterion->key,
                'status' => match (true) {
                    $hits >= 2 => 'found',
                    $hits === 1 => 'partial',
                    default => 'missing',
                },
                'evidence' => $hits > 0 ? mb_substr($text, 0, 120) : '',
                'confidence' => $hits >= 2 ? 80 : ($hits === 1 ? 50 : 0),
            ];
        }

        return [
            'checks' => $checks,
            'inconsistencies' => [],
            'questions' => [],
            'enough_data' => true,
        ];
    }
}

/**
 * Собеседующий: спрашивает по критериям по кругу, оценивает по содержанию.
 */
class CalibInterviewer implements Interviewer
{
    public function available(): bool
    {
        return true;
    }

    public function nextQuestion(AiInterview $interview, $criteria): array
    {
        $asked = $interview->turns()->where('role', 'ai')->count();
        $list = collect($criteria)->values();
        $criterion = $list->isEmpty() ? null : $list[$asked % $list->count()];

        return [
            'text' => 'Расскажите про «'.($criterion?->label ?? 'ваш опыт').'» на конкретном примере.',
            'kind' => 'technical',
            'criterion_key' => $criterion?->key,
            'done' => false,
        ];
    }

    public function scoreAnswer(AiInterview $interview, string $question, string $answer, ?AiInterviewCriterion $criterion): array
    {
        $score = calibQuality($answer);

        return [
            'score' => $score,
            'max' => 5,
            'rationale' => $score >= 4
                ? 'Конкретно, с примером и измеримым результатом.'
                : ($score >= 2 ? 'По теме, но без подробностей.' : 'Сказано общими словами.'),
        ];
    }
}

/**
 * Проверяющий задание.
 *
 * Разбор кода честный, насколько это возможно без запуска: синтаксис
 * проверяется разбором на токены, и непарсящееся решение получает ноль — как и
 * у настоящего проверяющего, который его запускает.
 */
class CalibExaminer implements TaskExaminer
{
    public const RUBRIC = [
        ['point' => 'Убирает нецифровые символы', 'weight' => 30, 'needle' => '/preg_replace|preg_match|str_replace/i'],
        ['point' => 'Приводит номер к коду страны', 'weight' => 25, 'needle' => '/992/'],
        ['point' => 'Проверяет итоговую длину', 'weight' => 20, 'needle' => '/strlen|mb_strlen/i'],
        ['point' => 'Возвращает null на непригодный номер', 'weight' => 25, 'needle' => '/null/i'],
    ];

    public function available(): bool
    {
        return true;
    }

    public function compose(AiInterview $interview, $criteria): array
    {
        return [
            'statement' => 'Напишите функцию normalizePhone(string $raw): ?string, приводящую телефон к формату +992XXXXXXXXX.',
            'format' => 'code',
            'language' => 'PHP',
            'reference_solution' => 'preg_replace + проверка длины + null',
            'rubric' => array_map(
                fn (array $row) => ['point' => $row['point'], 'weight' => $row['weight']],
                self::RUBRIC,
            ),
            'minutes' => 40,
        ];
    }

    public function grade(AiTestTask $task, string $solution): array
    {
        if (trim($solution) === '') {
            return ['score' => 0, 'review' => [], 'summary' => 'Решение пустое.', 'executed' => false, 'execution' => []];
        }

        // синтаксис: непарсящийся код не запустится, чем бы его ни проверяли
        try {
            token_get_all($solution, TOKEN_PARSE);
        } catch (\ParseError $e) {
            return [
                'score' => 0,
                'review' => array_map(
                    fn (array $row) => ['point' => $row['point'], 'passed' => false, 'note' => 'код не запускается'],
                    self::RUBRIC,
                ),
                'summary' => 'Код не компилируется.',
                'executed' => true,
                'execution' => [[
                    'language' => 'php', 'code' => $solution,
                    'result' => 'PHP Parse error: '.$e->getMessage(), 'is_error' => true,
                ]],
            ];
        }

        $score = 0;
        $review = [];

        foreach (self::RUBRIC as $row) {
            $passed = (bool) preg_match($row['needle'], $solution);
            $score += $passed ? $row['weight'] : 0;

            $review[] = [
                'point' => $row['point'],
                'passed' => $passed,
                'note' => $passed ? 'есть' : 'нет',
            ];
        }

        return [
            'score' => $score,
            'review' => $review,
            'summary' => 'Проверено по рубрике.',
            'executed' => true,
            'execution' => [[
                'language' => 'php', 'code' => $solution,
                'result' => 'Пройдено пунктов: '.count(array_filter($review, fn ($r) => $r['passed'])).'/'.count($review),
                'is_error' => false,
            ]],
        ];
    }
}

/** Формулировщик решения: шаблон и есть ответ, живее делать нечем. */
class CalibWriter implements DecisionWriter
{
    public function available(): bool
    {
        return true;
    }

    public function write(AiInterview $interview, string $outcome, array $reasons, string $fallback): string
    {
        return $fallback;
    }
}

/**
 * Прогнать всех и вернуть строки итогов, ключ — slug.
 *
 * @return array<string,array>
 */
function calibrationRun(): array
{
    Queue::fake();
    refs();

    app()->bind(DocumentAuditor::class, CalibAuditor::class);
    app()->bind(Interviewer::class, CalibInterviewer::class);
    app()->bind(TaskExaminer::class, CalibExaminer::class);
    app()->bind(DecisionWriter::class, CalibWriter::class);

    $runner = app(CalibrationRunner::class);
    $vacancy = $runner->vacancy();

    $rows = [];

    foreach (CalibrationProfiles::all() as $profile) {
        $rows[$profile['slug']] = $runner->run($profile, $vacancy);
    }

    return $rows;
}

// ==================== профили как данные ====================

test('профилей десять и все они разные', function () {
    $profiles = CalibrationProfiles::all();

    expect($profiles)->toHaveCount(10)
        ->and(array_unique(array_column($profiles, 'slug')))->toHaveCount(10);

    foreach ($profiles as $profile) {
        expect($profile['expect'])->toBeIn(array_keys(CalibrationProfiles::BUCKETS))
            ->and($profile['why'])->not->toBeEmpty()
            // общий ответ обязателен: вопрос может прийти без критерия
            ->and($profile['answers'])->toHaveKey('*');

        foreach (CalibrationProfiles::CRITERIA as $criterion) {
            expect($profile['answers'])->toHaveKey($criterion['key']);
        }
    }
});

test('в наборе есть кого принять, кому отказать и кого отдать человеку', function () {
    $expected = array_count_values(array_column(CalibrationProfiles::all(), 'expect'));

    expect($expected[DecisionEngine::PASSED] ?? 0)->toBeGreaterThanOrEqual(3)
        ->and($expected[DecisionEngine::REJECTED] ?? 0)->toBeGreaterThanOrEqual(3)
        ->and($expected[DecisionEngine::MANUAL] ?? 0)->toBeGreaterThanOrEqual(1);
});

test('веса критериев дают сотню, а веса частей — тоже', function () {
    expect(array_sum(array_column(CalibrationProfiles::CRITERIA, 'weight')))->toBe(100)
        ->and(CalibrationProfiles::VACANCY['weight_documents']
            + CalibrationProfiles::VACANCY['weight_interview']
            + CalibrationProfiles::VACANCY['weight_test'])->toBe(100);
});

// ==================== прогон ====================

test('система не выносит противоположного решения', function () {
    $rows = calibrationRun();

    /*
     * Главное требование к отбору, и оно слабее, чем «угадал исход».
     *
     * Оценщик здесь грубый: он читает слова, а не понимает их, и между
     * «крепким средним» и «пограничным» разницы почти не видит. Требовать от
     * него точного попадания в исход — значит подгонять либо оценщика, либо
     * профили. Чего требовать можно и нужно: система не должна выносить
     * противоположное решение. Засомневаться и позвать человека — можно,
     * отказать тому, кого ждали в приём, или принять того, кого ждали в
     * отказ, — нельзя.
     *
     * Совпадение исхода в точности проверяется на живой модели командой
     * ai:calibrate: там оценки ставит тот, кто будет ставить их кандидатам.
     */
    $inverted = collect($rows)->filter(fn (array $r) => match ($r['expect']) {
        DecisionEngine::PASSED => $r['outcome'] === DecisionEngine::REJECTED,
        DecisionEngine::REJECTED => $r['outcome'] === DecisionEngine::PASSED,
        default => false,
    });

    $describe = fn (array $r) => $r['slug'].': ждали '.$r['expect'].', вышло '.$r['outcome']
        .' (итог '.($r['total'] ?? '—').')';

    expect($inverted->all())->toBe([], $inverted->map($describe)->implode(' | '));
})->group('calibration');

test('решение не принимается, пока не посчитаны все три части', function () {
    $rows = calibrationRun();

    foreach ($rows as $slug => $row) {
        if ($row['outcome'] === null || $row['gate'] === 'missing_must_have') {
            continue;
        }

        expect($row['documents'])->not->toBeNull($slug)
            ->and($row['interview'])->not->toBeNull($slug)
            ->and($row['test'])->not->toBeNull($slug);
    }
})->group('calibration');

test('сильные набирают больше слабых с запасом', function () {
    $rows = calibrationRun();

    $totals = fn (string $expect) => collect($rows)->where('expect', $expect)
        ->pluck('total')->filter(fn ($t) => $t !== null);

    $floor = $totals(DecisionEngine::PASSED)->min();
    $ceiling = $totals(DecisionEngine::REJECTED)->max();

    /*
     * Главное число калибровки. Совпавшие исходы ещё ничего не доказывают:
     * они могли сойтись впритык, и тогда любой следующий кандидат ляжет не
     * туда. Значение имеет расстояние между группами.
     */
    expect($floor)->toBeGreaterThan($ceiling,
        'Группы перекрываются: слабейший из сильных '.$floor.', сильнейший из слабых '.$ceiling);

    expect($floor - $ceiling)->toBeGreaterThanOrEqual(10);
})->group('calibration');

test('порог приёма не пропускает никого из слабых', function () {
    $rows = calibrationRun();

    $ceiling = collect($rows)->where('expect', DecisionEngine::REJECTED)
        ->pluck('total')->filter(fn ($t) => $t !== null)->max();

    /*
     * Про порог отказа такого же утверждения здесь нет, и это не упущение.
     * Приукрасивший резюме набирает выше сорока пяти за счёт одних документов
     * (тридцать процентов итога), и по баллу ему отказа не будет — он уйдёт на
     * ручную проверку. Так и задумано: между «бумага хорошая, разговор пустой»
     * решает человек. Числа, при которых это перестало бы быть так, видны
     * только на живом прогоне, поэтому менять их по грубому оценщику нельзя.
     */
    expect($ceiling)->toBeLessThan(CalibrationProfiles::VACANCY['threshold_accept']);
})->group('calibration');

// ==================== отдельные свойства ====================

test('не та профессия отсекается воротами, а не баллом', function () {
    $rows = calibrationRun();

    expect($rows['wrong-profession']['gate'])->toBe('missing_must_have');
})->group('calibration');

test('отсутствие диплома не мешает пройти', function () {
    $rows = calibrationRun();

    expect($rows['strong-no-documents']['outcome'])->toBe(DecisionEngine::PASSED);
})->group('calibration');

test('скромное резюме не мешает: решают ответы', function () {
    $rows = calibrationRun();

    // в резюме три строчки, а на вопросах виден мастер
    expect($rows['modest-strong']['documents'])->toBeLessThan($rows['embellished']['documents'])
        ->and($rows['modest-strong']['total'])->toBeGreaterThan($rows['embellished']['total']);
})->group('calibration');

test('приукрашенное резюме не спасает от разговора', function () {
    $rows = calibrationRun();

    // бумага у него лучше, чем у крепкого среднего, а итог — хуже
    expect($rows['embellished']['documents'])->toBeGreaterThan($rows['solid-middle']['documents'])
        ->and($rows['embellished']['total'])->toBeLessThan($rows['solid-middle']['total']);
})->group('calibration');

test('попытки управлять оценкой не повышают балл и попадают в отчёт', function () {
    $rows = calibrationRun();

    expect($rows['pushy']['injections'])->toBeGreaterThan(0)
        ->and($rows['pushy']['outcome'])->toBe(DecisionEngine::REJECTED)
        ->and($rows['pushy']['interview'])->toBeLessThan(30);
})->group('calibration');

test('пограничного сперва доспрашивают, а потом отдают человеку', function () {
    $rows = calibrationRun();

    expect($rows['borderline']['follow_up'])->toBe(1)
        ->and($rows['borderline']['questions'])
        ->toBeGreaterThan(CalibrationProfiles::VACANCY['questions_count'])
        ->and($rows['borderline']['outcome'])->toBe(DecisionEngine::MANUAL);
})->group('calibration');

test('провальное задание у сильного не превращается в отказ', function () {
    $rows = calibrationRun();

    expect($rows['strong-failed-task']['test'])->toBe(0)
        ->and($rows['strong-failed-task']['outcome'])->not->toBe(DecisionEngine::REJECTED);
})->group('calibration');

// ==================== за собой убирает ====================

test('калибровка не оставляет следов в базе', function () {
    calibrationRun();

    expect(User::where('email', 'like', '%'.CalibrationRunner::MARK)->count())->toBeGreaterThan(0);

    $removed = app(CalibrationRunner::class)->cleanup();

    expect($removed['users'])->toBe(11)
        ->and($removed['vacancies'])->toBe(1)
        ->and(User::where('email', 'like', '%'.CalibrationRunner::MARK)->count())->toBe(0)
        ->and(AiInterview::count())->toBe(0)
        ->and(AiInterviewCriterion::count())->toBe(0);
})->group('calibration');
