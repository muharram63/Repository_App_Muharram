<?php

/*
 * Разбор документов: резюме в структурированный вид, проверка дипломов,
 * сверка требований.
 *
 * Что здесь защищается в первую очередь:
 *  — вывод «требование закрыто» невозможен без цитаты-доказательства;
 *  — требование, о котором модель промолчала, не считается выполненным;
 *  — сбой разбора не превращается в отказ кандидату, а зовёт человека;
 *  — модель не обвиняет кандидата в подделке документа;
 *  — балл за документы считает код, и он воспроизводим.
 */

use App\Jobs\AnalyzeCandidateDocument;
use App\Jobs\BuildRequirementMatrix;
use App\Models\AiCandidateDocument;
use App\Models\AiInteraction;
use App\Models\AiInterview;
use App\Models\AiInterviewCriterion;
use App\Models\AiRequirementCheck;
use App\Models\Resume;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Ai\AiInvalidAnswerException;
use App\Services\Ai\AiJournal;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\DocumentAuditor;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\GeminiDocumentAuditor;
use App\Services\Privacy\PiiRedactor;
use App\Services\Scoring\RequirementScore;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Подменный разборщик. Считает вызовы, умеет падать негодным ответом и
 * недоступностью — обе беды ведут к разным последствиям.
 */
class FakeAuditor implements DocumentAuditor
{
    public int $resumeCalls = 0;

    public int $documentCalls = 0;

    public int $matrixCalls = 0;

    public function __construct(
        private readonly bool $available = true,
        private readonly ?string $unavailable = null,
        private readonly bool $invalid = false,
        private readonly ?array $matrix = null,
        private readonly ?array $document = null,
    ) {
    }

    public function available(): bool
    {
        return $this->available;
    }

    public function parseResume(Resume $resume): array
    {
        $this->boom();
        $this->resumeCalls++;

        return [
            'enough_data' => true,
            'profession' => 'PHP-разработчик',
            'experience_years' => 4,
            'skills' => ['PHP', 'Laravel', 'SQL'],
            'languages' => ['русский'],
            'positions' => [['company' => 'Алиф Банк', 'role' => 'Backend', 'period' => '2021–2025',
                'summary' => 'платёжный шлюз']],
            'education' => [['place' => 'ТТУ', 'program' => 'Информационные системы', 'year' => '2014']],
            'achievements' => ['сократил отклик на 40%'],
        ];
    }

    public function auditDocument(AiCandidateDocument $document, array $parsedResume): array
    {
        $this->boom();
        $this->documentCalls++;

        return $this->document ?? [
            'readable' => true,
            'kind' => 'Диплом',
            'person' => 'Хамидова Мухаррам',
            'institution' => 'ТТУ',
            'program' => 'Информационные системы',
            'year' => '2014',
            'matches_resume' => 'yes',
            'mismatches' => [],
            'relevance' => 'direct',
            'note' => 'Диплом соответствует заявленному образованию.',
            'authenticity' => AiCandidateDocument::AUTHENTICITY_NOTE,
        ];
    }

    public function buildMatrix($criteria, array $parsedResume, array $documentFindings): array
    {
        $this->boom();
        $this->matrixCalls++;

        if ($this->matrix) {
            return $this->matrix;
        }

        return [
            'enough_data' => true,
            'checks' => collect($criteria)->map(fn ($c) => [
                'key' => $c->key,
                'status' => 'found',
                'evidence' => 'Цитата из резюме про '.$c->label,
                'confidence' => 80,
            ])->all(),
            'inconsistencies' => ['периоды работы в двух компаниях пересекаются'],
            'questions' => ['Вижу, что периоды в двух компаниях пересекаются — расскажите, как это было?'],
        ];
    }

    private function boom(): void
    {
        if ($this->invalid) {
            throw new AiInvalidAnswerException('дважды негодно', ['checks' => ['пусто']]);
        }

        if ($this->unavailable) {
            throw new AiUnavailableException($this->unavailable, 30);
        }
    }
}

/**
 * Собеседование, доведённое до стадии документов, с подменённым разборщиком.
 *
 * @return array{0:AiInterview,1:User,2:FakeAuditor,3:\Illuminate\Support\Collection}
 */
function analysisReady(?FakeAuditor $auditor = null, array $criteria = null): array
{
    Storage::fake('local');

    $auditor ??= new FakeAuditor;
    app()->instance(DocumentAuditor::class, $auditor);

    [$vacancy] = makeAiVacancy($criteria ?? [
        ['key' => 'php', 'label' => 'PHP и Laravel', 'kind' => 'must', 'weight' => 60],
        ['key' => 'sql', 'label' => 'SQL', 'kind' => 'nice', 'weight' => 40],
    ]);

    [$interview, , $applicantUser] = makeAiInterview($vacancy, [
        'consent_at' => now(), 'consent_ip' => '127.0.0.1', 'stage' => 'documents',
    ]);

    return [
        $interview,
        $applicantUser,
        $auditor,
        AiInterviewCriterion::where('vacancy_id', $vacancy->id)->confirmed()->get(),
    ];
}

/** Загруженный документ. */
function uploadedDocument(AiInterview $interview, string $mime = 'application/pdf', int $size = 1024): AiCandidateDocument
{
    $path = 'ai/documents/'.$interview->id.'/d'.uniqid().'.pdf';
    Storage::disk('local')->put($path, 'содержимое');

    return AiCandidateDocument::create([
        'ai_interview_id' => $interview->id,
        'kind' => 'diploma',
        'path' => $path,
        'original_name' => 'diplom.pdf',
        'mime' => $mime,
        'size' => $size,
    ]);
}

// ==================== запуск разбора ====================

test('переход к собеседованию ставит разбор в очередь цепочкой', function () {
    Bus::fake();
    [$interview, $user] = analysisReady();
    uploadedDocument($interview);
    uploadedDocument($interview);

    $this->actingAs($user)->post(route('applicant.ai.proceed', $interview))
        ->assertRedirect()->assertSessionHas('status');

    expect($interview->fresh()->analysis_status)->toBe('pending');

    // Сверка требований опирается на вердикты по документам, поэтому порядок
    // важен: цепочка, а не пачка независимых задач.
    Bus::assertChained([
        AnalyzeCandidateDocument::class,
        AnalyzeCandidateDocument::class,
        BuildRequirementMatrix::class,
    ]);
});

test('повторное нажатие не ставит разбор дважды', function () {
    Bus::fake();
    [$interview, $user] = analysisReady();

    $this->actingAs($user)->post(route('applicant.ai.proceed', $interview));
    $this->actingAs($user)->post(route('applicant.ai.proceed', $interview))
        ->assertSessionHas('status');

    Bus::assertDispatchedTimes(AnalyzeCandidateDocument::class, 0);
    Bus::assertDispatchedTimes(BuildRequirementMatrix::class, 1);
});

test('без документов разбор всё равно идёт: дипломы необязательны', function () {
    Bus::fake();
    [$interview, $user] = analysisReady();

    $this->actingAs($user)->post(route('applicant.ai.proceed', $interview));

    Bus::assertChained([BuildRequirementMatrix::class]);
});

// ==================== проверка документа ====================

test('документ получает вердикт и отпечаток', function () {
    [$interview, , $auditor] = analysisReady();
    $document = uploadedDocument($interview);

    (new AnalyzeCandidateDocument($document->id))->handle($auditor);

    $document->refresh();

    expect($document->status)->toBe('analysed')
        ->and($document->analysis['institution'])->toBe('ТТУ')
        ->and($document->analysis['matches_resume'])->toBe('yes')
        ->and($document->source_hash)->not->toBeNull()
        ->and($document->analysed_at)->not->toBeNull()
        // оговорка о непроверенной подлинности приписывается всегда
        ->and($document->analysis['authenticity'])->toContain('не проверена');
});

test('тот же файл с теми же требованиями второй раз не разбирается', function () {
    [$interview, , $auditor] = analysisReady();
    $document = uploadedDocument($interview);

    (new AnalyzeCandidateDocument($document->id))->handle($auditor);
    (new AnalyzeCandidateDocument($document->id))->handle($auditor);

    expect($auditor->documentCalls)->toBe(1);
});

test('изменённый набор требований обесценивает прежний вердикт', function () {
    [$interview, , $auditor] = analysisReady();
    $document = uploadedDocument($interview);

    (new AnalyzeCandidateDocument($document->id))->handle($auditor);

    // работодатель добавил требование — документ надо перепроверить
    AiInterviewCriterion::factory()->create([
        'vacancy_id' => $interview->vacancy_id, 'key' => 'docker', 'kind' => 'nice',
    ]);

    (new AnalyzeCandidateDocument($document->id))->handle($auditor);

    expect($auditor->documentCalls)->toBe(2);
});

test('нечитаемый при загрузке документ модели не показывают', function () {
    [$interview, , $auditor] = analysisReady();
    $document = uploadedDocument($interview);
    $document->update(['status' => 'unreadable', 'failure_reason' => 'нет текста']);

    (new AnalyzeCandidateDocument($document->id))->handle($auditor);

    expect($auditor->documentCalls)->toBe(0);
});

test('модель не прочитала документ — это говорят кандидату, а не прячут', function () {
    [$interview] = analysisReady();
    $document = uploadedDocument($interview);

    $auditor = new FakeAuditor(document: [
        'readable' => false, 'kind' => '', 'person' => null, 'institution' => null,
        'program' => null, 'year' => null, 'matches_resume' => 'unknown',
        'mismatches' => [], 'relevance' => 'unknown', 'note' => '',
        'authenticity' => AiCandidateDocument::AUTHENTICITY_NOTE,
    ]);

    (new AnalyzeCandidateDocument($document->id))->handle($auditor);

    $document->refresh();

    expect($document->status)->toBe('unreadable')
        ->and($document->failure_reason)->toContain('более чёткий скан');
});

test('негодный ответ по документу не останавливает собеседование', function () {
    [$interview] = analysisReady();
    $document = uploadedDocument($interview);

    (new AnalyzeCandidateDocument($document->id))->handle(new FakeAuditor(invalid: true));

    $document->refresh();

    // непроверенный диплом — минус одно доказательство, а не причина отказать
    expect($document->status)->toBe('failed')
        ->and($document->failure_reason)->toContain('Разбор не удался')
        ->and($interview->fresh()->outcome)->toBeNull()
        ->and($interview->fresh()->requires_review)->toBeFalse();
});

// ==================== сверка требований ====================

test('сверка расставляет статусы, считает балл и рождает вопросы', function () {
    [$interview, , $auditor, $criteria] = analysisReady();
    $document = uploadedDocument($interview);
    (new AnalyzeCandidateDocument($document->id))->handle($auditor);

    (new BuildRequirementMatrix($interview->id))->handle($auditor);

    $interview->refresh();

    expect($interview->analysis_status)->toBe('ready')
        ->and($interview->analysed_at)->not->toBeNull()
        // все требования закрыты — сотня
        ->and($interview->documents_score)->toBe(100)
        ->and($interview->analysis['resume']['profession'])->toBe('PHP-разработчик')
        ->and($interview->analysis['questions'])->toHaveCount(1)
        ->and($interview->analysis['inconsistencies'][0])->toContain('пересекаются');

    $checks = $interview->requirementChecks;

    expect($checks)->toHaveCount(2)
        ->and($checks->pluck('status')->unique()->all())->toBe(['found'])
        ->and($checks->first()->evidence_quote)->toContain('Цитата из резюме')
        ->and($checks->first()->origin)->toBe('documents');
});

test('кандидат получает уведомление о готовности разбора', function () {
    [$interview, , $auditor] = analysisReady();

    (new BuildRequirementMatrix($interview->id))->handle($auditor);

    expect(UserNotification::where('user_id', $interview->applicant->user_id)
        ->where('type', 'interview')->where('title', 'Документы разобраны')->exists())->toBeTrue();
});

test('требование, о котором модель промолчала, считается ненайденным', function () {
    [$interview, , , $criteria] = analysisReady();

    // модель ответила только про один критерий из двух
    $auditor = new FakeAuditor(matrix: [
        'enough_data' => true,
        'checks' => [['key' => 'php', 'status' => 'found', 'evidence' => 'Laravel 4 года', 'confidence' => 90]],
        'inconsistencies' => [],
        'questions' => [],
    ]);

    (new BuildRequirementMatrix($interview->id))->handle($auditor);

    $checks = $interview->fresh()->requirementChecks->keyBy('requirement');

    // Молчание не может означать «есть»: иначе пропущенный критерий открывал бы
    // ворота решения сам собой.
    expect($checks['SQL']->status)->toBe('missing')
        ->and($checks['SQL']->confidence)->toBe(0)
        ->and($checks['PHP и Laravel']->status)->toBe('found');
});

test('сбой сверки зовёт человека, а не отказывает кандидату', function () {
    [$interview] = analysisReady();

    (new BuildRequirementMatrix($interview->id))->handle(new FakeAuditor(invalid: true));

    $interview->refresh();

    expect($interview->analysis_status)->toBe('failed')
        ->and($interview->outcome)->toBe('manual_review')
        ->and($interview->requires_review)->toBeTrue()
        // кандидату не отправлено ни отказа, ни приглашения
        ->and($interview->latestDecision)->toBeNull();

    // а работодателю сказано, что кандидат ждёт
    expect(UserNotification::where('title', 'Кандидат ждёт ручной проверки')->exists())->toBeTrue();
});

test('снятые работодателем критерии переводят дело на человека', function () {
    [$interview, , $auditor] = analysisReady();

    AiInterviewCriterion::where('vacancy_id', $interview->vacancy_id)->delete();

    (new BuildRequirementMatrix($interview->id))->handle($auditor);

    expect($interview->fresh()->analysis_status)->toBe('failed')
        ->and($interview->fresh()->outcome)->toBe('manual_review')
        ->and($auditor->matrixCalls)->toBe(0);
});

test('пересчёт не затирает то, что подтверждено на собеседовании', function () {
    [$interview, , $auditor, $criteria] = analysisReady();

    $php = $criteria->firstWhere('key', 'php');

    // требование закрыто словами кандидата в разговоре
    AiRequirementCheck::create([
        'ai_interview_id' => $interview->id,
        'criterion_id' => $php->id,
        'requirement' => $php->label,
        'kind' => 'must',
        'status' => 'confirmed_in_interview',
        'evidence_quote' => 'рассказал про очереди Laravel',
        'origin' => 'interview',
    ]);

    (new BuildRequirementMatrix($interview->id))->handle($auditor);

    $checks = $interview->fresh()->requirementChecks->keyBy('requirement');

    // Подтверждённое устно нельзя потерять при пересчёте документов: иначе
    // человек второй раз доказывал бы то же самое.
    expect($checks[$php->label]->status)->toBe('confirmed_in_interview')
        ->and($checks[$php->label]->origin)->toBe('interview');
});

test('уже разобранное резюме второй раз не разбирается', function () {
    [$interview, , $auditor] = analysisReady();

    (new BuildRequirementMatrix($interview->id))->handle($auditor);
    (new BuildRequirementMatrix($interview->id))->handle($auditor);

    expect($auditor->resumeCalls)->toBe(1)
        ->and($auditor->matrixCalls)->toBe(2);
});

// ==================== балл за документы ====================

test('балл считает код по весам и статусам', function () {
    [$interview, , , $criteria] = analysisReady();

    $php = $criteria->firstWhere('key', 'php');   // вес 60, обязательное
    $sql = $criteria->firstWhere('key', 'sql');   // вес 40, желательное

    $make = function ($criterion, string $status) use ($interview) {
        return AiRequirementCheck::create([
            'ai_interview_id' => $interview->id,
            'criterion_id' => $criterion->id,
            'requirement' => $criterion->label,
            'kind' => $criterion->kind,
            'status' => $status,
        ]);
    };

    // закрыто 60, частично 40 (половина) => (60 + 20) / 100 = 80
    $checks = collect([$make($php, 'found'), $make($sql, 'partial')]);

    expect(RequirementScore::compute($checks, $criteria))->toBe(80);

    // всё закрыто — сотня; ничего — ноль
    $interview->requirementChecks()->delete();
    $all = collect([$make($php, 'found'), $make($sql, 'found')]);
    expect(RequirementScore::compute($all, $criteria))->toBe(100);

    $interview->requirementChecks()->delete();
    $none = collect([$make($php, 'missing'), $make($sql, 'missing')]);
    expect(RequirementScore::compute($none, $criteria))->toBe(0);
});

test('подтверждённое на собеседовании считается закрытым', function () {
    [$interview, , , $criteria] = analysisReady();
    $php = $criteria->firstWhere('key', 'php');
    $sql = $criteria->firstWhere('key', 'sql');

    $checks = collect([
        AiRequirementCheck::create([
            'ai_interview_id' => $interview->id, 'criterion_id' => $php->id,
            'requirement' => 'PHP', 'kind' => 'must', 'status' => 'confirmed_in_interview',
        ]),
        AiRequirementCheck::create([
            'ai_interview_id' => $interview->id, 'criterion_id' => $sql->id,
            'requirement' => 'SQL', 'kind' => 'nice', 'status' => 'found',
        ]),
    ]);

    expect(RequirementScore::compute($checks, $criteria))->toBe(100)
        ->and(RequirementScore::mustHavesSatisfied($checks))->toBeTrue();
});

test('незакрытое обязательное требование видно отдельно от балла', function () {
    [$interview, , , $criteria] = analysisReady();
    $php = $criteria->firstWhere('key', 'php');
    $sql = $criteria->firstWhere('key', 'sql');

    $checks = collect([
        AiRequirementCheck::create([
            'ai_interview_id' => $interview->id, 'criterion_id' => $php->id,
            'requirement' => 'PHP', 'kind' => 'must', 'status' => 'partial',
        ]),
        AiRequirementCheck::create([
            'ai_interview_id' => $interview->id, 'criterion_id' => $sql->id,
            'requirement' => 'SQL', 'kind' => 'nice', 'status' => 'found',
        ]),
    ]);

    // балл приличный, а ворота закрыты: «частично» обязательное не закрывает
    expect(RequirementScore::compute($checks, $criteria))->toBe(70)
        ->and(RequirementScore::mustHavesSatisfied($checks))->toBeFalse();
});

test('без подтверждённых критериев балла нет вовсе', function () {
    [$interview] = analysisReady();

    // ноль означал бы «кандидат ничего не закрыл», а правда в том,
    // что сверять было не с чем
    expect(RequirementScore::compute(collect(), collect()))->toBeNull();
});

test('проверка по удалённому критерию в балл не идёт', function () {
    [$interview, , , $criteria] = analysisReady();
    $php = $criteria->firstWhere('key', 'php');

    $orphan = AiRequirementCheck::create([
        'ai_interview_id' => $interview->id, 'criterion_id' => null,
        'requirement' => 'Снятое требование', 'kind' => 'must', 'status' => 'missing',
    ]);
    $kept = AiRequirementCheck::create([
        'ai_interview_id' => $interview->id, 'criterion_id' => $php->id,
        'requirement' => 'PHP', 'kind' => 'must', 'status' => 'found',
    ]);

    // осиротевшая проверка осталась в отчёте, но веса у неё больше нет
    expect(RequirementScore::compute(collect([$orphan, $kept]), $criteria))->toBe(100);
});

// ==================== сам разборщик на Http::fake ====================

/** Ответ Interactions API с заданной начинкой. */
function auditAnswer(array $payload): array
{
    return [
        'model' => 'gemini-3.6-flash',
        'usage' => ['total_input_tokens' => 500, 'total_output_tokens' => 300],
        'steps' => [['type' => 'model_output', 'content' => [
            ['type' => 'text', 'text' => json_encode($payload, JSON_UNESCAPED_UNICODE)],
        ]]],
    ];
}

function realAuditor(): GeminiDocumentAuditor
{
    return new GeminiDocumentAuditor(
        new AiJournal(new GeminiClient([
            'key' => 'k', 'model' => 'gemini-3.5-flash-lite',
            'model_scoring' => 'gemini-3.6-flash', 'model_vision' => 'gemini-3.6-flash',
            'revision' => 'r', 'timeout' => 5, 'connect_timeout' => 3,
        ])),
        new PiiRedactor,
    );
}

test('файл уходит в модель блоком document, а не текстом', function () {
    Http::fake(['*' => Http::response(auditAnswer([
        'readable' => true, 'matches_resume' => 'yes', 'relevance' => 'direct',
        'institution' => 'ТТУ', 'year' => '2014',
    ]))]);

    [$interview] = analysisReady();
    $document = uploadedDocument($interview, 'image/png', 2048);

    realAuditor()->auditDocument($document, ['profession' => 'Инженер']);

    Http::assertSent(function ($request) {
        $content = $request['input'][0]['content'];

        // Блок document — единственная форма, которую принимает Interactions
        // API; выяснено перебором. Файл идёт перед текстом.
        return $content[0]['type'] === 'document'
            && $content[0]['mime_type'] === 'image/png'
            && filled($content[0]['data'])
            && $content[1]['type'] === 'text';
    });
});

test('слишком большой файл в модель не отправляется', function () {
    Http::fake(['*' => Http::response(auditAnswer([
        'readable' => false, 'matches_resume' => 'unknown', 'relevance' => 'unknown',
    ]))]);

    [$interview] = analysisReady();
    $document = uploadedDocument($interview, 'application/pdf',
        GeminiDocumentAuditor::MAX_FILE_BYTES + 1);
    $document->update(['extracted_text' => null]);

    realAuditor()->auditDocument($document, []);

    // base64 раздувает файл на треть: семимегабайтное тело упёрлось бы в таймаут
    Http::assertSent(fn ($request) => $request['input'][0]['content'][0]['type'] === 'text');
});

test('когда файл не уходит, вместо него идёт извлечённый текст', function () {
    Http::fake(['*' => Http::response(auditAnswer([
        'readable' => true, 'matches_resume' => 'partial', 'relevance' => 'related',
    ]))]);

    [$interview] = analysisReady();
    $document = uploadedDocument($interview,
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    $document->update(['extracted_text' => 'Диплом инженера, ТТУ, 2014']);

    realAuditor()->auditDocument($document, []);

    Http::assertSent(function ($request) {
        $text = $request['input'][0]['content'][0]['text'];

        return str_contains($text, 'ТЕКСТ ДОКУМЕНТА') && str_contains($text, 'ТТУ');
    });
});

test('в аудите отмечен факт вложения, но не его содержимое', function () {
    Http::fake(['*' => Http::response(auditAnswer([
        'readable' => true, 'matches_resume' => 'yes', 'relevance' => 'direct',
    ]))]);

    [$interview] = analysisReady();
    $document = uploadedDocument($interview, 'image/png', 2048);

    realAuditor()->auditDocument($document, []);

    $log = AiInteraction::where('purpose', 'document_audit')->sole();

    // base64 документа в базе не нужен: для объяснения решения довольно знать,
    // что файл был и какого он типа
    $data = base64_encode('содержимое');

    expect($log->request)->toContain('[файл] image/png')
        // сам base64 документа в записи не появляется: аудиту довольно знать,
        // что файл был и какого он типа
        ->and($log->request)->not->toContain($data);
});

test('требование без цитаты закрытым не считается', function () {
    Http::fake(['*' => Http::response(auditAnswer([
        'enough_data' => true,
        'checks' => [
            ['key' => 'php', 'status' => 'found', 'evidence' => '', 'confidence' => 90],
            ['key' => 'sql', 'status' => 'partial', 'evidence' => '', 'confidence' => 50],
        ],
    ]))]);

    [, , , $criteria] = analysisReady();

    $matrix = realAuditor()->buildMatrix($criteria, ['profession' => 'PHP'], []);
    $byKey = collect($matrix['checks'])->keyBy('key');

    /*
     * Вывод без доказательства не принимается: found без цитаты понижается до
     * partial, partial без цитаты — до missing. Цитата это то, что работодатель
     * прочитает в отчёте, а кандидат сможет оспорить.
     */
    expect($byKey['php']['status'])->toBe('partial')
        ->and($byKey['sql']['status'])->toBe('missing');
});

test('чужой ключ требования отбрасывается, а не валит всю сверку', function () {
    Http::fake(['*' => Http::response(auditAnswer([
        'enough_data' => true,
        'checks' => [
            ['key' => 'php', 'status' => 'found', 'evidence' => 'Laravel 4 года'],
            ['key' => 'выдуманный', 'status' => 'found', 'evidence' => 'что-то'],
        ],
    ]))]);

    [, , , $criteria] = analysisReady();

    $matrix = realAuditor()->buildMatrix($criteria, [], []);
    $keys = collect($matrix['checks'])->pluck('key')->all();

    expect($keys)->toContain('php')
        ->and($keys)->not->toContain('выдуманный')
        // и пропущенный sql добавлен как missing
        ->and($keys)->toContain('sql');
});

test('персональные данные не доходят до модели при разборе резюме', function () {
    Http::fake(['*' => Http::response(auditAnswer(['enough_data' => true, 'profession' => 'Инженер']))]);

    [$interview] = analysisReady();
    $resume = $interview->applicant->resume()->first();
    $resume->update(['description' => 'Возраст: 34. Пол: мужской. Четыре года на Laravel.']);

    realAuditor()->parseResume($resume->fresh());

    Http::assertSent(function ($request) {
        $text = $request['input'][0]['content'][0]['text'];

        return ! str_contains($text, '34')
            && ! str_contains($text, 'мужской')
            && str_contains($text, 'Laravel');
    });
});

test('промпт запрещает обвинять кандидата в подделке документа', function () {
    $auditor = realAuditor();

    $method = (new ReflectionClass($auditor))->getMethod('documentPrompt');
    $method->setAccessible(true);
    $prompt = $method->invoke($auditor);

    /*
     * Модель не может установить подлинность и не должна этого утверждать. Это
     * не вежливость, а граница возможностей: расхождение в датах объясняется
     * вторым дипломом, сменой фамилии или опечаткой в резюме.
     */
    expect($prompt)->toContain('не можешь установить')
        ->toContain('подделка')
        // фраза переносится на другую строку, поэтому ищем слово
        ->toContain('обвинить');
});

test('промпт сверки требует цитату, а не пересказ', function () {
    $auditor = realAuditor();

    $method = (new ReflectionClass($auditor))->getMethod('matrixPrompt');
    $method->setAccessible(true);
    $prompt = $method->invoke($auditor);

    expect($prompt)->toContain('ДОКАЗАТЕЛЬСТВО ОБЯЗАТЕЛЬНО')
        ->toContain('Не выдумывай цитат')
        ->toContain('ничего не пропуская');
});

// ==================== страница ====================

test('страница показывает, что разбор идёт', function () {
    [$interview, $user] = analysisReady();
    $interview->update(['analysis_status' => 'pending']);

    $this->actingAs($user)->get(route('applicant.ai.documents', $interview))
        ->assertOk()
        ->assertSee('ИИ разбирает документы', false)
        ->assertSee('window.location.reload', false);
});

test('страница честно объясняет неудачный разбор и обещает человека', function () {
    [$interview, $user] = analysisReady();
    $interview->update(['analysis_status' => 'failed']);

    $this->actingAs($user)->get(route('applicant.ai.documents', $interview))
        ->assertOk()
        ->assertSee('Это не отказ', false)
        ->assertSee('рассмотрит человек', false);
});

test('после разбора страница ведёт к собеседованию', function () {
    [$interview, $user] = analysisReady();
    $interview->update(['analysis_status' => 'ready']);

    $this->actingAs($user)->get(route('applicant.ai.documents', $interview))
        ->assertOk()
        ->assertSee('Документы разобраны', false)
        ->assertSee('К собеседованию', false);
});

test('стадию до собеседования двигает разбор, а не кнопка', function () {
    [$interview, , $auditor] = analysisReady();

    expect($interview->stage)->toBe('documents');

    (new BuildRequirementMatrix($interview->id))->handle($auditor);

    expect($interview->fresh()->stage)->toBe('interview')
        ->and($interview->fresh()->progress())->toBe(40);
});

test('пересчёт документов не откатывает кандидата назад по стадиям', function () {
    [$interview, , $auditor] = analysisReady();

    // кандидат уже дошёл до тестового задания
    $interview->update(['stage' => 'test']);

    (new BuildRequirementMatrix($interview->id))->handle($auditor);

    expect($interview->fresh()->stage)->toBe('test');
});
