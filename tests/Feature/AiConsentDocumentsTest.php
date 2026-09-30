<?php

/*
 * Согласие кандидата и загрузка документов.
 *
 * Самое важное здесь — что без согласия не начинается ничего, а отказ ничего не
 * ломает: отклик остаётся, и его рассмотрит человек. Второе — что документ
 * попадает на приватный диск, а вырезание персональных данных происходит при
 * загрузке, а не «когда-нибудь перед оценкой».
 */

use App\Http\Controllers\Applicant\AiInterviewController;
use App\Models\AiCandidateDocument;
use App\Models\AiInterview;
use App\Models\AiInterviewConfig;
use App\Models\AiInterviewCriterion;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\VacancyResponse;
use App\Services\Documents\TextExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

/**
 * Вакансия с рабочим ИИ-собеседованием и откликнувшийся кандидат с резюме.
 *
 * Отклик создаётся здесь же: собеседование растёт из него, и без отклика вход
 * закрыт. Кому нужно пройти этот путь через сам запрос — передаёт
 * responded: false.
 *
 * @return array{0:App\Models\Vacancy,1:User,2:App\Models\Applicant}
 */
function readyToConsent(array $config = [], bool $responded = true): array
{
    [$vacancy] = makeAiVacancy(null, $config);

    // рабочая настройка требует подтверждённого обязательного критерия —
    // makeAiVacancy его и создаёт
    $applicantUser = User::factory()->applicant()->create();
    $applicant = makeApplicant($applicantUser);
    makeResume($applicant);

    if ($responded) {
        VacancyResponse::create([
            'applicant_id' => $applicant->id,
            'vacancy_id' => $vacancy->id,
        ]);
    }

    return [$vacancy, $applicantUser, $applicant];
}

/** Валидный DOCX: zip с одним word/document.xml. */
function docxFile(string $text): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'docx').'.docx';

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml',
        '<?xml version="1.0"?><w:document xmlns:w="x"><w:body>'
        .'<w:p><w:r><w:t>'.$text.'</w:t></w:r></w:p>'
        .'<w:p><w:r><w:t>Вторая строка</w:t></w:r></w:p>'
        .'</w:body></w:document>');
    $zip->close();

    return new UploadedFile($path, 'diplom.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
}

// ==================== вход и согласие ====================

test('отклик на вакансию с ИИ ведёт кандидата к согласию', function () {
    // отклик этот тест отправляет сам — он и проверяется
    [$vacancy, $applicantUser] = readyToConsent(responded: false);

    $this->actingAs($applicantUser)
        ->post(route('public.vacancies.respond', $vacancy))
        ->assertRedirect(route('applicant.ai.start', $vacancy));

    // отклик создан обычным порядком
    expect(VacancyResponse::where('vacancy_id', $vacancy->id)->count())->toBe(1);

    // и уведомление есть: вкладку закроют, а вернуться нужно
    expect(UserNotification::where('user_id', $applicantUser->id)
        ->where('type', 'interview')->exists())->toBeTrue();
});

test('отклик на обычную вакансию ведёт себя как прежде', function () {
    $employerUser = User::factory()->employer()->create();
    $vacancy = makeVacancy(makeEmployer($employerUser));

    $applicantUser = User::factory()->applicant()->create();
    makeApplicant($applicantUser);

    // собеседование у вакансии не настроено — поток не изменился
    $this->actingAs($applicantUser)
        ->post(route('public.vacancies.respond', $vacancy))
        ->assertRedirect();

    expect(AiInterview::count())->toBe(0);
});

test('включённое, но противоречиво настроенное собеседование в тупик не ведёт', function () {
    [$vacancy] = makeAiVacancy();

    // веса сломаны — собеседовать нельзя, и кандидата туда не отправляем
    AiInterviewConfig::where('vacancy_id', $vacancy->id)
        ->update(['weight_documents' => 50, 'weight_interview' => 50, 'weight_test' => 50]);

    $applicantUser = User::factory()->applicant()->create();
    makeApplicant($applicantUser);

    $this->actingAs($applicantUser)
        ->post(route('public.vacancies.respond', $vacancy))
        ->assertRedirect();

    expect(AiInterview::count())->toBe(0);
});

test('вход создаёт собеседование и показывает согласие', function () {
    [$vacancy, $applicantUser, $applicant] = readyToConsent();

    $this->actingAs($applicantUser)->get(route('applicant.ai.start', $vacancy))
        ->assertRedirect();

    $interview = AiInterview::sole();

    expect($interview->applicant_id)->toBe($applicant->id)
        ->and($interview->stage)->toBe('consent')
        ->and($interview->consented())->toBeFalse();

    $this->actingAs($applicantUser)->get(route('applicant.ai.interview', $interview))
        ->assertOk()
        ->assertSee('Собеседование проводит ИИ-ассистент', false)
        // предупреждение об обработке данных обязательно
        ->assertSee('обработку моих данных', false)
        ->assertSee('не учитываются', false);
});

test('повторный вход не плодит второе собеседование', function () {
    [$vacancy, $applicantUser] = readyToConsent();

    $this->actingAs($applicantUser)->get(route('applicant.ai.start', $vacancy));
    $this->actingAs($applicantUser)->get(route('applicant.ai.start', $vacancy));

    expect(AiInterview::count())->toBe(1);
});

test('без резюме собеседование не начать', function () {
    [$vacancy] = makeAiVacancy();

    $applicantUser = User::factory()->applicant()->create();
    makeApplicant($applicantUser);

    // резюме нет — разбирать нечего
    $this->actingAs($applicantUser)->get(route('applicant.ai.start', $vacancy))
        ->assertRedirect(route('applicant.resume.create'));

    expect(AiInterview::count())->toBe(0);
});

test('выключенное после отклика собеседование объясняет себя', function () {
    [$vacancy, $applicantUser] = readyToConsent();

    AiInterviewConfig::where('vacancy_id', $vacancy->id)->update(['enabled' => false]);

    $this->actingAs($applicantUser)->get(route('applicant.ai.start', $vacancy))
        ->assertRedirect(route('public.responses.index'))
        ->assertSessionHas('status');
});

test('согласие фиксируется вместе со временем и адресом', function () {
    [$vacancy, $applicantUser] = readyToConsent();
    $this->actingAs($applicantUser)->get(route('applicant.ai.start', $vacancy));
    $interview = AiInterview::sole();

    $this->actingAs($applicantUser)
        ->post(route('applicant.ai.consent', $interview), ['consent' => '1'])
        ->assertRedirect(route('applicant.ai.documents', $interview));

    $interview->refresh();

    expect($interview->consented())->toBeTrue()
        ->and($interview->stage)->toBe('documents')
        ->and($interview->consent_ip)->not->toBeNull()
        // адрес — персональные данные, в базе шифруется
        ->and(rawColumn('ai_interviews', $interview->id, 'consent_ip'))
        ->not->toBe($interview->consent_ip);
});

test('без поставленной галочки согласие не принимается', function () {
    [$vacancy, $applicantUser] = readyToConsent();
    $this->actingAs($applicantUser)->get(route('applicant.ai.start', $vacancy));
    $interview = AiInterview::sole();

    $this->actingAs($applicantUser)
        ->post(route('applicant.ai.consent', $interview), [])
        ->assertSessionHasErrors('consent');

    $this->actingAs($applicantUser)
        ->post(route('applicant.ai.consent', $interview), ['consent' => '0'])
        ->assertSessionHasErrors('consent');

    expect($interview->fresh()->consented())->toBeFalse();
});

test('отказ убирает собеседование, но не отклик', function () {
    [$vacancy, $applicantUser] = readyToConsent();

    $this->actingAs($applicantUser)->post(route('public.vacancies.respond', $vacancy));
    $this->actingAs($applicantUser)->get(route('applicant.ai.start', $vacancy));

    $interview = AiInterview::sole();

    $this->actingAs($applicantUser)
        ->delete(route('applicant.ai.decline', $interview))
        ->assertRedirect(route('public.responses.index'))
        ->assertSessionHas('status');

    expect(AiInterview::count())->toBe(0)
        // отклик на месте: его рассмотрит человек
        ->and(VacancyResponse::where('vacancy_id', $vacancy->id)->count())->toBe(1);
});

test('после отказа можно вернуться и согласиться', function () {
    [$vacancy, $applicantUser] = readyToConsent();

    $this->actingAs($applicantUser)->get(route('applicant.ai.start', $vacancy));
    $this->actingAs($applicantUser)->delete(route('applicant.ai.decline', AiInterview::sole()));

    $this->actingAs($applicantUser)->get(route('applicant.ai.start', $vacancy))->assertRedirect();

    expect(AiInterview::count())->toBe(1);
});

// ==================== доступ ====================

test('чужое собеседование не открыть и не пополнить', function () {
    [$vacancy, $applicantUser] = readyToConsent();
    $this->actingAs($applicantUser)->get(route('applicant.ai.start', $vacancy));
    $interview = AiInterview::sole();

    $stranger = User::factory()->applicant()->create();
    makeApplicant($stranger);

    $this->actingAs($stranger)->get(route('applicant.ai.interview', $interview))->assertForbidden();
    $this->actingAs($stranger)->post(route('applicant.ai.consent', $interview), ['consent' => '1'])
        ->assertForbidden();
    $this->actingAs($stranger)->get(route('applicant.ai.documents', $interview))->assertForbidden();
    $this->actingAs($stranger)->delete(route('applicant.ai.decline', $interview))->assertForbidden();
});

test('документы без согласия недоступны', function () {
    [$vacancy, $applicantUser] = readyToConsent();
    $this->actingAs($applicantUser)->get(route('applicant.ai.start', $vacancy));
    $interview = AiInterview::sole();

    $this->actingAs($applicantUser)->get(route('applicant.ai.documents', $interview))
        ->assertForbidden();

    $this->actingAs($applicantUser)->post(route('applicant.ai.documents.upload', $interview), [
        'kind' => 'diploma',
        'file' => UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'),
    ])->assertForbidden();

    expect(AiCandidateDocument::count())->toBe(0);
});

test('работодателю раздел кандидата недоступен', function () {
    $employer = User::factory()->employer()->create();

    $this->actingAs($employer)->get(route('applicant.ai.index'))->assertForbidden();
});

// ==================== загрузка документов ====================

/** Согласившийся кандидат и его собеседование. */
function consented(): array
{
    [$vacancy, $applicantUser] = readyToConsent();

    test()->actingAs($applicantUser)->get(route('applicant.ai.start', $vacancy));
    $interview = AiInterview::sole();
    test()->actingAs($applicantUser)->post(route('applicant.ai.consent', $interview), ['consent' => '1']);

    return [$interview->fresh(), $applicantUser];
}

test('документ ложится на приватный диск', function () {
    [$interview, $user] = consented();

    $this->actingAs($user)->post(route('applicant.ai.documents.upload', $interview), [
        'kind' => 'diploma',
        'file' => UploadedFile::fake()->create('diplom.pdf', 120, 'application/pdf'),
    ])->assertRedirect()->assertSessionHas('status');

    $document = AiCandidateDocument::sole();

    expect($document->kind)->toBe('diploma')
        ->and($document->original_name)->toBe('diplom.pdf')
        // PDF читает модель — текст заранее не извлекаем
        ->and($document->extracted_text)->toBeNull()
        ->and($document->status)->toBe('pending')
        ->and($document->path)->toStartWith('ai/documents/'.$interview->id);

    Storage::disk('local')->assertExists($document->path);
});

test('из DOCX текст извлекается сразу и без персональных данных', function () {
    [$interview, $user] = consented();

    $this->actingAs($user)->post(route('applicant.ai.documents.upload', $interview), [
        'kind' => 'certificate',
        'file' => docxFile('Возраст: 34. Диплом инженера, Таджикский технический университет'),
    ])->assertRedirect();

    $document = AiCandidateDocument::sole();

    expect($document->status)->toBe('pending')
        ->and($document->extracted_text)->toContain('Таджикский технический университет')
        // абзацы не склеились в одно слово
        ->and($document->extracted_text)->toContain('Вторая строка')
        // возраст вырезан при загрузке, а не «когда-нибудь перед оценкой»
        ->and($document->extracted_text)->not->toContain('34');

    // и в базе текст зашифрован
    expect(rawColumn('ai_candidate_documents', $document->id, 'extracted_text'))
        ->not->toContain('Таджикский');
});

test('нечитаемый файл сохраняется, но помечается и объясняется', function () {
    [$interview, $user] = consented();

    // .txt с пустым содержимым: читать нечего
    $this->actingAs($user)->post(route('applicant.ai.documents.upload', $interview), [
        'kind' => 'other',
        'file' => UploadedFile::fake()->createWithContent('empty.txt', '   '),
    ])->assertRedirect()->assertSessionHas('error');

    $document = AiCandidateDocument::sole();

    expect($document->status)->toBe('unreadable')
        ->and($document->failure_reason)->not->toBeNull();
});

test('чужие форматы и великаны не принимаются', function () {
    [$interview, $user] = consented();

    $this->actingAs($user)->post(route('applicant.ai.documents.upload', $interview), [
        'kind' => 'diploma',
        'file' => UploadedFile::fake()->create('virus.exe', 10),
    ])->assertSessionHasErrors('file');

    $this->actingAs($user)->post(route('applicant.ai.documents.upload', $interview), [
        'kind' => 'diploma',
        'file' => UploadedFile::fake()->create('huge.pdf', AiInterviewController::MAX_KB + 100, 'application/pdf'),
    ])->assertSessionHasErrors('file');

    expect(AiCandidateDocument::count())->toBe(0);
});

test('предел числа документов соблюдается', function () {
    [$interview, $user] = consented();

    for ($i = 0; $i < AiInterviewController::MAX_DOCUMENTS; $i++) {
        AiCandidateDocument::create([
            'ai_interview_id' => $interview->id,
            'kind' => 'diploma', 'path' => 'ai/documents/x'.$i, 'original_name' => 'd'.$i.'.pdf',
        ]);
    }

    $this->actingAs($user)->post(route('applicant.ai.documents.upload', $interview), [
        'kind' => 'diploma',
        'file' => UploadedFile::fake()->create('one-more.pdf', 10, 'application/pdf'),
    ])->assertSessionHas('error');

    expect(AiCandidateDocument::count())->toBe(AiInterviewController::MAX_DOCUMENTS);
});

test('документ удаляется вместе с файлом', function () {
    [$interview, $user] = consented();

    $this->actingAs($user)->post(route('applicant.ai.documents.upload', $interview), [
        'kind' => 'diploma',
        'file' => UploadedFile::fake()->create('d.pdf', 20, 'application/pdf'),
    ]);

    $document = AiCandidateDocument::sole();
    $path = $document->path;

    $this->actingAs($user)
        ->delete(route('applicant.ai.documents.destroy', [$interview, $document]))
        ->assertRedirect();

    expect(AiCandidateDocument::count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
});

test('свой документ скачивается, чужой — нет', function () {
    [$interview, $user] = consented();

    $this->actingAs($user)->post(route('applicant.ai.documents.upload', $interview), [
        'kind' => 'diploma',
        'file' => UploadedFile::fake()->create('d.pdf', 20, 'application/pdf'),
    ]);

    $document = AiCandidateDocument::sole();

    $this->actingAs($user)
        ->get(route('applicant.ai.documents.file', [$interview, $document]))
        ->assertOk();

    $stranger = User::factory()->applicant()->create();
    makeApplicant($stranger);

    $this->actingAs($stranger)
        ->get(route('applicant.ai.documents.file', [$interview, $document]))
        ->assertForbidden();
});

test('страница документов говорит, что резюме загружать не нужно', function () {
    [$interview, $user] = consented();

    $this->actingAs($user)->get(route('applicant.ai.documents', $interview))
        ->assertOk()
        ->assertSee('Резюме уже на площадке', false)
        ->assertSee('Подлинность документов система не проверяет', false);
});

test('дипломов может не быть вовсе, и дальше пройти можно', function () {
    Illuminate\Support\Facades\Bus::fake();
    [$interview, $user] = consented();

    $this->actingAs($user)->post(route('applicant.ai.proceed', $interview))
        ->assertRedirect()->assertSessionHas('status');

    /*
     * Стадия остаётся на документах, пока разбор не закончен: вопросы для
     * разговора рождаются из разбора, и до его готовности собеседованию не о
     * чем спрашивать. Стадию двигает задача BuildRequirementMatrix.
     */
    expect($interview->fresh()->stage)->toBe('documents')
        ->and($interview->fresh()->analysis_status)->toBe('pending')
        ->and(AiCandidateDocument::count())->toBe(0);
});

// ==================== возврат к незаконченному ====================

test('список собеседований показывает стадию и прогресс', function () {
    [$interview, $user] = consented();

    $this->actingAs($user)->get(route('applicant.ai.index'))
        ->assertOk()
        ->assertSee($interview->vacancy->title)
        ->assertSee('Продолжить', false);
});

test('список пуст, когда собеседований нет', function () {
    $user = User::factory()->applicant()->create();
    makeApplicant($user);

    $this->actingAs($user)->get(route('applicant.ai.index'))
        ->assertOk()
        ->assertSee('Собеседований пока нет', false);
});

test('согласившийся кандидат со страницы согласия уходит к документам', function () {
    [$interview, $user] = consented();

    // согласие уже дано — показывать его второй раз незачем
    $this->actingAs($user)->get(route('applicant.ai.interview', $interview))
        ->assertRedirect(route('applicant.ai.documents', $interview));
});

// ==================== извлечение текста ====================

test('извлекатель знает, что читает сам, а что оставляет модели', function () {
    $extractor = new TextExtractor;

    foreach (TextExtractor::MODEL_MIMES as $mime) {
        expect($extractor->readableByModel($mime))->toBeTrue($mime);

        $result = $extractor->extract('ai/documents/whatever', $mime);

        // Текст заранее не вытаскиваем: модель увидит сам файл, а значит и
        // печати, и подписи, и расположение полей.
        expect($result['method'])->toBe('model')
            ->and($result['text'])->toBeNull()
            ->and($result['readable'])->toBeTrue();
    }

    expect($extractor->readableByModel('application/msword'))->toBeFalse();
});

test('старый doc отвергается с понятной причиной', function () {
    $result = (new TextExtractor)->extract('ai/documents/old.doc', 'application/msword');

    expect($result['readable'])->toBeFalse()
        ->and($result['method'])->toBe('none')
        ->and($result['reason'])->toContain('PDF или DOCX');
});

test('битый DOCX не роняет загрузку', function () {
    Storage::disk('local')->put('ai/documents/broken.docx', 'это не архив');

    $result = (new TextExtractor)->extract(
        'ai/documents/broken.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    );

    expect($result['readable'])->toBeFalse()
        ->and($result['reason'])->toContain('повреждён');
});

test('текст в cp1251 не превращается в крокозябры', function () {
    // Блокнот на русской Windows до сих пор пишет в cp1251
    Storage::disk('local')->put(
        'ai/documents/win.txt',
        mb_convert_encoding('Инженер-программист, Душанбе', 'Windows-1251', 'UTF-8'),
    );

    $result = (new TextExtractor)->extract('ai/documents/win.txt', 'text/plain');

    expect($result['readable'])->toBeTrue()
        ->and($result['text'])->toContain('Инженер-программист')
        ->and(mb_check_encoding($result['text'], 'UTF-8'))->toBeTrue();
});

test('длинный текст обрезается по пределу', function () {
    Storage::disk('local')->put(
        'ai/documents/long.txt',
        str_repeat('очень длинный текст резюме ', 3000),
    );

    $result = (new TextExtractor)->extract('ai/documents/long.txt', 'text/plain');

    expect(mb_strlen($result['text']))->toBeLessThanOrEqual(TextExtractor::LIMIT);
});
