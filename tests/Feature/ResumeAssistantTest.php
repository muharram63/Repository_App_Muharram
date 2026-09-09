<?php

use App\Models\Resume;
use App\Models\ResumeDraft;
use App\Models\User;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\ResumeAssistant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Заглушка помощника: тесты не должны ходить в сеть и тратить квоту ключа.
 */
class FakeAssistant implements ResumeAssistant
{
    public array $seen = [];

    public function __construct(
        private readonly bool $available = true,
        private readonly ?string $failure = null,
    ) {
    }

    public function available(): bool
    {
        return $this->available;
    }

    public function next(ResumeDraft $draft, string $message): array
    {
        if ($this->failure) {
            throw new AiUnavailableException($this->failure);
        }

        $this->seen[] = $message;

        return [
            'reply' => 'А что именно вы делали в этом проекте?',
            'stage' => 'achievements',
            'done' => false,
            'resume' => ['headline' => 'PHP-разработчик', 'skills' => ['PHP', 'Laravel']],
        ];
    }

    public function finalize(ResumeDraft $draft): array
    {
        if ($this->failure) {
            throw new AiUnavailableException($this->failure);
        }

        return [
            'profession' => 'PHP-разработчик',
            'desired_position' => 'Backend-разработчик',
            'experience_years' => 3,
            'desired_salary' => 6000,
            'skills' => 'PHP, Laravel, MySQL',
            'languages' => 'русский, английский',
            'place_work' => 'ООО Тест',
            'description' => 'Опытный backend-разработчик.',
        ];
    }
}

/**
 * Соискатель с анкетой и подменённым помощником.
 */
function assistantUser(ResumeAssistant $assistant): User
{
    app()->instance(ResumeAssistant::class, $assistant);

    $user = User::factory()->applicant()->create();
    makeApplicant($user);

    return $user;
}

/**
 * Открыть помощника и получить разговор, который он показал.
 */
function openAssistant(User $user): ResumeDraft
{
    test()->actingAs($user)->get('/applicant/resume/assistant')->assertOk();

    return ResumeDraft::where('applicant_id', $user->applicant->id)->latest('id')->first();
}

/**
 * Правила, заданные самому листу в этом оформлении. Вложенные селекторы
 * (`… .cv-head{`) не в счёт: проверяем именно рамку, а не содержимое.
 */
function frameRules(string $html, string $style): string
{
    preg_match_all(
        '/\.cv-sheet\[data-skin="'.preg_quote($style, '/').'"\]\s*\{([^}]*)\}/',
        $html,
        $found,
    );

    return implode(' ', $found[1]);
}

/** Минимальный набор полей резюме для сохранения. */
function resumeFields(array $extra = []): array
{
    return $extra + [
        'profession' => 'PHP-разработчик',
        'desired_position' => 'Backend-разработчик',
        'experience_years' => 3,
        'desired_salary' => 6000,
        'languages' => 'русский',
    ];
}

test('the assistant page opens and starts the conversation with a greeting', function () {
    $user = assistantUser(new FakeAssistant);

    $this->actingAs($user)->get('/applicant/resume/assistant')
        ->assertOk()
        ->assertSee('Ваше резюме')
        // приветствие показывается без обращения к модели
        ->assertSee('как вас зовут', false);

    expect(ResumeDraft::where('applicant_id', $user->applicant->id)->count())->toBe(1);
});

test('a turn of the dialogue saves both replies and the resume state', function () {
    $assistant = new FakeAssistant;
    $user = assistantUser($assistant);
    $draft = openAssistant($user);

    $this->actingAs($user)
        ->postJson('/applicant/resume/assistant/'.$draft->id.'/message', ['message' => 'Работал в компании'])
        ->assertOk()
        ->assertJsonPath('reply', 'А что именно вы делали в этом проекте?')
        ->assertJsonPath('resume.headline', 'PHP-разработчик')
        ->assertJsonPath('stage', 'achievements');

    // приветствие + реплика пользователя + ответ помощника
    expect($draft->fresh()->messages)->toHaveCount(3)
        ->and($draft->fresh()->messages[1]['text'])->toBe('Работал в компании')
        ->and($draft->fresh()->data['skills'])->toBe(['PHP', 'Laravel'])
        ->and($assistant->seen)->toBe(['Работал в компании']);
});

test('a failed call keeps the history clean so a retry does not duplicate it', function () {
    $user = assistantUser(new FakeAssistant(failure: 'Лимит запросов исчерпан.'));
    $draft = openAssistant($user);

    $this->actingAs($user)
        ->postJson('/applicant/resume/assistant/'.$draft->id.'/message', ['message' => 'Мой опыт — три года'])
        ->assertStatus(503)
        ->assertJsonPath('error', 'Лимит запросов исчерпан.');

    // в истории осталось только приветствие: неотправленная реплика не записана
    expect($draft->fresh()->messages)->toHaveCount(1);
});

test('the final version is built and then saved as a real resume', function () {
    $user = assistantUser(new FakeAssistant);
    $draft = openAssistant($user);

    $this->actingAs($user)
        ->postJson('/applicant/resume/assistant/'.$draft->id.'/message', ['message' => 'Я backend-разработчик']);

    $this->actingAs($user)->postJson('/applicant/resume/assistant/'.$draft->id.'/finalize')
        ->assertOk()
        ->assertJsonPath('final.profession', 'PHP-разработчик');

    $this->actingAs($user)->post('/applicant/resume/assistant/'.$draft->id.'/save', resumeFields([
        'skills' => 'PHP, Laravel, MySQL',
        'description' => 'Опытный backend-разработчик.',
    ]))->assertRedirect(route('applicant.resume.index', absolute: false));

    $resume = Resume::first();

    expect($resume->applicant_id)->toBe($user->applicant->id)
        ->and($resume->profession)->toBe('PHP-разработчик');

    // разговор не стёрт, а помечен завершённым: к нему можно вернуться
    expect($draft->fresh()->resume_id)->toBe($resume->id);
});

test('a second visit after publishing opens a new conversation and keeps the old one', function () {
    $user = assistantUser(new FakeAssistant);
    $first = openAssistant($user);

    $this->actingAs($user)
        ->postJson('/applicant/resume/assistant/'.$first->id.'/message', ['message' => 'Я backend-разработчик']);

    expect($first->fresh()->messages)->toHaveCount(3);

    $this->actingAs($user)->post('/applicant/resume/assistant/'.$first->id.'/save', resumeFields())
        ->assertRedirect(route('applicant.resume.index', absolute: false));

    $second = openAssistant($user);

    // новый разговор: только приветствие, и это не прежний
    expect($second->id)->not->toBe($first->id)
        ->and($second->messages)->toHaveCount(1)
        ->and($second->data)->toBe([])
        // прежний разговор никуда не делся
        ->and(ResumeDraft::count())->toBe(2)
        ->and($first->fresh())->not->toBeNull();
});

test('an old conversation can be opened again from the list', function () {
    $user = assistantUser(new FakeAssistant);
    $first = openAssistant($user);

    $this->actingAs($user)
        ->postJson('/applicant/resume/assistant/'.$first->id.'/message', ['message' => 'Я backend-разработчик']);

    // начинаем второй разговор — первый остаётся
    $this->actingAs($user)->post('/applicant/resume/assistant/new')->assertRedirect();

    expect(ResumeDraft::count())->toBe(2);

    // и к первому можно вернуться со всей перепиской
    $this->actingAs($user)->get('/applicant/resume/assistant/'.$first->id)
        ->assertOk()
        ->assertSee('Я backend-разработчик', false)
        ->assertSee('А что именно вы делали в этом проекте?', false);
});

test('the list of conversations shows the finished ones apart', function () {
    $user = assistantUser(new FakeAssistant);
    $first = openAssistant($user);

    $this->actingAs($user)
        ->postJson('/applicant/resume/assistant/'.$first->id.'/message', ['message' => 'Я backend-разработчик']);
    $this->actingAs($user)->post('/applicant/resume/assistant/'.$first->id.'/save', resumeFields());

    $html = $this->actingAs($user)->get('/applicant/resume/assistant')->assertOk()->getContent();

    // подпись берётся из собранных данных, а не «Черновик»
    expect($html)->toContain('PHP-разработчик')
        ->and($html)->toContain('опубликовано')
        ->and($html)->toContain('ai-thread-dot done');
});

test('a conversation can be deleted, and the resume it produced stays', function () {
    $user = assistantUser(new FakeAssistant);
    $draft = openAssistant($user);

    $this->actingAs($user)->post('/applicant/resume/assistant/'.$draft->id.'/save', resumeFields());

    expect(Resume::count())->toBe(1);

    $this->actingAs($user)->delete('/applicant/resume/assistant/'.$draft->id)
        ->assertRedirect(route('applicant.resume.assistant', absolute: false));

    // переписка удалена, опубликованное резюме — нет
    expect(ResumeDraft::whereKey($draft->id)->exists())->toBeFalse()
        ->and(Resume::count())->toBe(1);
});

test('someone else conversation cannot be deleted', function () {
    $owner = assistantUser(new FakeAssistant);
    $draft = openAssistant($owner);

    $stranger = User::factory()->applicant()->create();
    makeApplicant($stranger);

    $this->actingAs($stranger)->delete('/applicant/resume/assistant/'.$draft->id)->assertForbidden();

    expect(ResumeDraft::whereKey($draft->id)->exists())->toBeTrue();
});

test('every layout changes the frame of the sheet, not only the type', function () {
    $applicant = makeApplicant(User::factory()->applicant()->create());
    $resume = makeResume($applicant);

    $html = $this->get('/resume/'.$resume->id)->assertOk()->getContent();

    // у каждого оформления, кроме классического, своя рамка листа
    foreach (array_keys(Resume::STYLES) as $style) {
        if ($style === 'classic') {
            continue;
        }

        expect(frameRules($html, $style))->toMatch('/border(-radius|-color|:)|box-shadow/');
    }
});

test('someone else conversation cannot be opened or continued', function () {
    $owner = assistantUser(new FakeAssistant);
    $draft = openAssistant($owner);

    $stranger = User::factory()->applicant()->create();
    makeApplicant($stranger);

    $this->actingAs($stranger)->get('/applicant/resume/assistant/'.$draft->id)->assertForbidden();
    $this->actingAs($stranger)
        ->postJson('/applicant/resume/assistant/'.$draft->id.'/message', ['message' => 'привет'])
        ->assertForbidden();
    $this->actingAs($stranger)
        ->post('/applicant/resume/assistant/'.$draft->id.'/save', resumeFields())
        ->assertForbidden();
});

test('the chosen layout is saved together with the resume', function () {
    $user = assistantUser(new FakeAssistant);
    $draft = openAssistant($user);

    $this->actingAs($user)
        ->post('/applicant/resume/assistant/'.$draft->id.'/save', resumeFields(['style' => 'modern']))
        ->assertRedirect(route('applicant.resume.index', absolute: false));

    expect(Resume::first()->style)->toBe('modern');
});

test('the public resume page is rendered in the chosen layout', function () {
    $applicant = makeApplicant(User::factory()->applicant()->create());
    $resume = makeResume($applicant);

    // каждое оформление доходит до публичной страницы и имеет свои правила
    foreach (array_keys(Resume::STYLES) as $style) {
        $resume->update(['style' => $style]);

        $html = $this->get('/resume/'.$resume->id)->assertOk()->getContent();

        expect($html)->toContain('data-skin="'.$style.'"');

        if ($style !== 'classic') {
            // classic намеренно ничего не переопределяет — это прежний вид
            expect($html)->toContain('.cv-sheet[data-skin="'.$style.'"]');
        }
    }
});

test('the assistant offers every layout and a way to start a new resume', function () {
    $user = assistantUser(new FakeAssistant);

    $html = $this->actingAs($user)->get('/applicant/resume/assistant')->assertOk()->getContent();

    foreach (array_keys(Resume::STYLES) as $style) {
        expect($html)->toContain('data-skin="'.$style.'"')
            ->and($html)->toContain('.ai-skin-'.$style);
    }

    // «Собрать новое резюме» стоит на виду, в шапке разговора
    expect($html)->toContain('Собрать новое резюме')
        ->and($html)->toContain(route('applicant.resume.assistant.start'));
});

test('a resume created before the picker keeps the old look', function () {
    $applicant = makeApplicant(User::factory()->applicant()->create());
    $resume = makeResume($applicant);

    // classic не переопределяет ни одного правила — страница выглядит как раньше
    $this->get('/resume/'.$resume->id)
        ->assertOk()
        ->assertSee('data-skin="classic"', false);
});

test('the layout can be changed later from the edit form', function () {
    $user = assistantUser(new FakeAssistant);
    $resume = makeResume($user->applicant);

    // значение по умолчанию проставляет база, поэтому читаем запись заново
    expect($resume->fresh()->style)->toBe('classic');

    $this->actingAs($user)->put('/applicant/resume/'.$resume->id, [
        'profession' => $resume->profession,
        'desired_position' => $resume->desired_position,
        'experience_years' => $resume->experience_years,
        'desired_salary' => $resume->desired_salary,
        'languages' => $resume->languages,
        'style' => 'compact',
    ])->assertRedirect(route('applicant.resume.index', absolute: false));

    expect($resume->fresh()->style)->toBe('compact');
});

test('the edit form offers every layout', function () {
    $user = assistantUser(new FakeAssistant);
    $resume = makeResume($user->applicant);
    $resume->update(['style' => 'strict']);

    $html = $this->actingAs($user)->get('/applicant/resume/'.$resume->id.'/edit')
        ->assertOk()
        ->getContent();

    foreach (array_keys(Resume::STYLES) as $style) {
        expect($html)->toContain('value="'.$style.'"');
    }

    // отмечено ровно одно оформление — текущее
    expect($html)->toMatch('/value="strict"\s+checked/')
        ->and(preg_match_all('/name="style"[^>]*checked/', $html))->toBe(1);
});

test('an unknown layout is not accepted', function () {
    $user = assistantUser(new FakeAssistant);
    $draft = openAssistant($user);

    $this->actingAs($user)
        ->post('/applicant/resume/assistant/'.$draft->id.'/save', resumeFields(['style' => '"><script>']))
        ->assertSessionHasErrors('style');

    expect(Resume::count())->toBe(0);
});

test('a resume saved without a choice gets the default layout', function () {
    $user = assistantUser(new FakeAssistant);
    $draft = openAssistant($user);

    $this->actingAs($user)->post('/applicant/resume/assistant/'.$draft->id.'/save', resumeFields());

    expect(Resume::first()->style)->toBe('classic');
});

test('a document attached at the last step lands on the private disk', function () {
    Storage::fake('local');

    $user = assistantUser(new FakeAssistant);
    $draft = openAssistant($user);

    $this->actingAs($user)->post('/applicant/resume/assistant/'.$draft->id.'/save', resumeFields([
        'documents' => UploadedFile::fake()->create('diplom.pdf', 120, 'application/pdf'),
    ]))->assertRedirect(route('applicant.resume.index', absolute: false));

    $resume = Resume::first();

    expect($resume->documents)->toStartWith('resumes/');
    // приватный диск: файл отдаётся только через SecureFileController
    Storage::disk('local')->assertExists($resume->documents);
});

test('a document of the wrong kind is rejected', function () {
    Storage::fake('local');

    $user = assistantUser(new FakeAssistant);
    $draft = openAssistant($user);

    $this->actingAs($user)->post('/applicant/resume/assistant/'.$draft->id.'/save', resumeFields([
        'documents' => UploadedFile::fake()->create('virus.exe', 10),
    ]))->assertSessionHasErrors('documents');

    expect(Resume::count())->toBe(0);
});

test('a resume without a document saves as before', function () {
    $user = assistantUser(new FakeAssistant);
    $draft = openAssistant($user);

    $this->actingAs($user)->post('/applicant/resume/assistant/'.$draft->id.'/save', resumeFields())
        ->assertRedirect(route('applicant.resume.index', absolute: false));

    expect(Resume::first()->documents)->toBeNull();
});

test('saving obeys the same rules as the manual form', function () {
    $user = assistantUser(new FakeAssistant);
    $draft = openAssistant($user);

    $this->actingAs($user)->post('/applicant/resume/assistant/'.$draft->id.'/save', [
        'profession' => 'PHP-разработчик',
        // без желаемой должности и языков форма тоже не проходит
        'experience_years' => 3,
        'desired_salary' => 6000,
    ])->assertSessionHasErrors(['desired_position', 'languages']);

    expect(Resume::count())->toBe(0);
});

test('nothing to finalize before the conversation has data', function () {
    $user = assistantUser(new FakeAssistant);
    $draft = openAssistant($user);

    $this->actingAs($user)->postJson('/applicant/resume/assistant/'.$draft->id.'/finalize')
        ->assertStatus(422);
});

test('the page still works when the key is missing', function () {
    $user = assistantUser(new FakeAssistant(available: false));

    $this->actingAs($user)->get('/applicant/resume/assistant')
        ->assertOk()
        ->assertSee('GEMINI_API_KEY');
});

test('the assistant is closed to employers', function () {
    $employerUser = User::factory()->employer()->create();
    makeEmployer($employerUser);

    $this->actingAs($employerUser)->get('/applicant/resume/assistant')->assertForbidden();
});
