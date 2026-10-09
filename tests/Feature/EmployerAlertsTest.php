<?php

/*
 * Уведомления работодателю о кандидатах: новый отклик, готовый отчёт
 * ИИ-собеседования и напоминание о тех, кто ждёт решения.
 *
 * Главное здесь — работодатель не пропускает кандидата и не тонет в дублях:
 * одно событие — одно уведомление, получает только владелец вакансии, а вид
 * и канал он выбирает сам.
 */

use App\Models\AiDecision;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserSetting;
use App\Models\VacancyResponse;
use App\Notifications\AwaitingDecisionAlert;
use App\Notifications\InterviewReportAlert;
use App\Notifications\NewCandidateAlert;
use App\Support\EmployerAlerts;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

/** Решение ИИ по собеседованию, ждущее работодателя. */
function awaitingDecision(string $outcome = 'passed', ?App\Models\Vacancy $vacancy = null): array
{
    [$interview, , $applicantUser] = makeAiInterview($vacancy, [
        'consent_at' => now(), 'stage' => 'decision', 'analysis_status' => 'ready',
        'documents_score' => 70, 'interview_score' => 80, 'test_score' => 75, 'total_score' => 76,
        'outcome' => $outcome, 'decided_at' => now(),
    ]);

    $decision = AiDecision::create([
        'ai_interview_id' => $interview->id, 'round' => 1,
        'documents_score' => 70, 'interview_score' => 80, 'test_score' => 75, 'total' => 76,
        'outcome' => $outcome, 'gate_reason' => 'above_accept_threshold',
        'reasons' => [], 'message_to_candidate' => 'Поздравляем!',
    ]);

    return [$interview->fresh(), $decision, $applicantUser];
}

function employerOf(App\Models\AiInterview $interview): User
{
    return $interview->vacancy->employer->user;
}

// ==================== новый отклик ====================

test('при отклике работодатель получает «Новый кандидат … откликнулся на вакансию …»', function () {
    [$vacancy, , $employerUser] = makeAiVacancy(config: ['enabled' => false]);
    $applicantUser = User::factory()->applicant()->create(['name' => 'Фарангис Каримова']);
    makeResume(makeApplicant($applicantUser));

    $this->actingAs($applicantUser)->post(route('public.vacancies.respond', $vacancy))->assertRedirect();
    // повторный отклик — «вы уже откликались», второго уведомления нет
    $this->actingAs($applicantUser)->post(route('public.vacancies.respond', $vacancy))->assertRedirect();

    $notes = UserNotification::where('user_id', $employerUser->id)->where('type', 'candidate')->get();

    expect($notes)->toHaveCount(1)
        ->and($notes->first()->title)->toBe('Новый кандидат')
        ->and($notes->first()->body)->toBe('Новый кандидат Фарангис Каримова откликнулся на вакансию «'.$vacancy->title.'»')
        ->and($notes->first()->isUnread())->toBeTrue()
        ->and($notes->first()->url)->toBe(UserNotification::localUrl(route('employer.responses.index')));
});

test('одно событие — одно уведомление, даже если его прислали дважды', function () {
    [$vacancy] = makeAiVacancy();
    $applicant = makeApplicant(User::factory()->applicant()->create());
    $response = VacancyResponse::create(['applicant_id' => $applicant->id, 'vacancy_id' => $vacancy->id]);

    expect(EmployerAlerts::newCandidate($response))->toBeTrue()
        ->and(EmployerAlerts::newCandidate($response))->toBeFalse()
        ->and(UserNotification::where('type', 'candidate')->count())->toBe(1);
});

// ==================== отчёт готов ====================

test('отчёт готов: имя, процент совпадения и рекомендация — и ведёт на карточку кандидата', function () {
    [$interview, $decision, $applicantUser] = awaitingDecision('rejected');

    EmployerAlerts::reportReady($interview, $decision);
    EmployerAlerts::reportReady($interview, $decision);

    $note = UserNotification::where('user_id', employerOf($interview)->id)->where('type', 'candidate')->sole();

    expect($note->title)->toBe('Отчёт ИИ-собеседования готов')
        ->and($note->body)->toContain('ИИ-собеседование с '.$applicantUser->name.' завершено.')
        ->and($note->body)->toMatch('/Совпадение: \d+%\./u')
        ->and($note->body)->toContain('Рекомендация: Отказать')
        ->and($note->url)->toBe(UserNotification::localUrl(route('employer.ai.candidate', [$interview->vacancy_id, $interview->id])));
});

test('решение ИИ само отправляет работодателю уведомление об отчёте', function () {
    [$interview] = makeAiInterview(null, [
        'consent_at' => now(), 'stage' => 'decision', 'analysis_status' => 'ready', 'requires_review' => true,
    ]);

    (new App\Jobs\MakeHiringDecision($interview->id))->handle(new class implements App\Services\Ai\DecisionWriter
    {
        public function available(): bool { return false; }

        public function write(App\Models\AiInterview $interview, string $outcome, array $reasons, string $fallback): string
        {
            return $fallback;
        }
    });

    expect(UserNotification::where('user_id', employerOf($interview)->id)
        ->where('title', 'Отчёт ИИ-собеседования готов')
        ->where('body', 'like', '%Рекомендация: Доп. собеседование%')->count())->toBe(1);
});

// ==================== напоминание ====================

test('кандидаты ждут больше суток — одно напоминание в сутки с их числом', function () {
    [$first] = awaitingDecision();
    awaitingDecision(vacancy: $first->vacancy);
    $employer = employerOf($first);

    // сутки ещё не прошли
    $this->artisan('employers:remind-awaiting')->assertSuccessful();
    expect(UserNotification::where('user_id', $employer->id)->where('title', 'Кандидаты ждут решения')->count())->toBe(0);

    $this->travel(25)->hours();
    $this->artisan('employers:remind-awaiting')->assertSuccessful();
    // в те же сутки — без повтора
    $this->artisan('employers:remind-awaiting')->assertSuccessful();

    $reminders = UserNotification::where('user_id', $employer->id)->where('title', 'Кандидаты ждут решения')->get();
    expect($reminders)->toHaveCount(1)
        ->and($reminders->first()->body)->toBe('У вас 2 кандидата ждут решения');

    // на следующий день — снова
    $this->travel(1)->days();
    $this->artisan('employers:remind-awaiting')->assertSuccessful();
    expect(UserNotification::where('user_id', $employer->id)->where('title', 'Кандидаты ждут решения')->count())->toBe(2);
});

test('отправленный ответ больше не ждёт — о нём не напоминают', function () {
    [$interview, $decision] = awaitingDecision();
    $decision->update(['released_at' => now(), 'release_source' => 'employer']);

    $this->travel(2)->days();

    expect(EmployerAlerts::remindAwaiting())->toBe(0);
});

test('напоминание в планировщике — каждый час', function () {
    $this->artisan('schedule:list')->assertSuccessful()->expectsOutputToContain('employers:remind-awaiting');
});

test('числа в напоминании склоняются: 1 кандидат, 3 кандидата, 5 кандидатов', function () {
    $body = fn (int $n) => (new AwaitingDecisionAlert($n))->toSite(new User)['body'];

    expect($body(1)[0])->toBe('У вас :count кандидат ждёт решения')
        ->and($body(21)[0])->toBe('У вас :count кандидат ждёт решения')
        ->and($body(3)[0])->toBe('У вас :count кандидата ждут решения')
        ->and($body(5)[0])->toBe('У вас :count кандидатов ждут решения')
        ->and($body(12)[0])->toBe('У вас :count кандидатов ждут решения');
});

// ==================== только своему работодателю ====================

test('чужой работодатель не видит чужих уведомлений', function () {
    [$interview, $decision] = awaitingDecision();
    EmployerAlerts::reportReady($interview, $decision);

    $owner = employerOf($interview);
    $stranger = User::factory()->employer()->create();
    makeEmployer($stranger);

    $note = UserNotification::where('user_id', $owner->id)->sole();

    expect(UserNotification::where('user_id', $stranger->id)->count())->toBe(0);

    $this->actingAs($stranger)->get(route('public.notifications.index'))
        ->assertOk()
        ->assertDontSee('Отчёт ИИ-собеседования готов');

    // по чужой ссылке не открыть
    $this->actingAs($stranger)->get(route('public.notifications.open', $note))->assertForbidden();
    $this->actingAs($stranger)->getJson(route('public.notifications.count'))->assertJsonPath('unread', 0);
});

// ==================== настройки: виды и каналы ====================

test('выключенный вид уведомления не приходит', function () {
    [$interview, $decision] = awaitingDecision();
    UserSetting::create(['user_id' => employerOf($interview)->id, 'notify_reports' => false]);

    expect(EmployerAlerts::reportReady($interview, $decision))->toBeFalse()
        ->and(UserNotification::count())->toBe(0);
});

test('каналы: на сайте и на почту — как выбрал работодатель', function () {
    Notification::fake();
    [$interview, $decision] = awaitingDecision();
    $employer = employerOf($interview);

    EmployerAlerts::reportReady($interview, $decision);
    Notification::assertSentTo($employer, InterviewReportAlert::class,
        fn ($note, $channels) => in_array(App\Notifications\Channels\SiteChannel::class, $channels, true) && in_array('mail', $channels, true));

    // только сайт: почту выключили
    UserSetting::create(['user_id' => $employer->id, 'channel_email' => false]);
    [$other, $otherDecision] = awaitingDecision(vacancy: $interview->vacancy);
    EmployerAlerts::reportReady($other, $otherDecision);

    Notification::assertSentTo($employer->fresh(), InterviewReportAlert::class,
        fn ($note, $channels) => $note->interview->is($other) && $channels === [App\Notifications\Channels\SiteChannel::class]);
});

test('письмо работодателю — стандартное уведомление Laravel с кнопкой в кабинет', function () {
    [$interview, $decision] = awaitingDecision();
    $employer = employerOf($interview);

    $mail = (new InterviewReportAlert($interview, $decision, 64))->toMail($employer);

    expect($mail->subject)->toBe('Отчёт ИИ-собеседования готов')
        ->and(implode(' ', $mail->introLines))->toContain('Совпадение: 64%. Рекомендация: Принять')
        ->and($mail->actionUrl)->toBe(route('employer.ai.candidate', [$interview->vacancy_id, $interview->id]));
});

test('в настройках работодателя — виды, каналы и разрешение для браузера', function () {
    $employer = User::factory()->employer()->create();
    makeEmployer($employer);

    $this->actingAs($employer)->get(route('employer.settings.index'))
        ->assertOk()
        ->assertSee('name="notify_new_candidates"', false)
        ->assertSee('name="notify_reports"', false)
        ->assertSee('name="notify_reminders"', false)
        ->assertSee('name="channel_browser"', false)
        ->assertSee(__('Разрешить уведомления в браузере'));

    $this->actingAs($employer)->patch(route('employer.settings.notifications'), [
        'notify_new_candidates' => '1', 'channel_site' => '1',
    ])->assertRedirect();

    $settings = $employer->fresh()->settingsOrDefault();
    expect($settings->notify_new_candidates)->toBeTrue()
        ->and($settings->notify_reports)->toBeFalse()
        ->and($settings->channel_email)->toBeFalse();
});

test('страница показывает уведомление браузера, когда вкладка свёрнута', function () {
    $employer = User::factory()->employer()->create();
    makeEmployer($employer);

    $this->actingAs($employer)->get(route('employer.settings.index'))
        ->assertOk()
        ->assertSee('function notifyBrowser(note)', false)
        ->assertSee('const browserOn = true;', false);
});
