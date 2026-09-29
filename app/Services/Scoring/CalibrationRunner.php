<?php

namespace App\Services\Scoring;

use App\Jobs\BuildRequirementMatrix;
use App\Jobs\ComposeTestTask;
use App\Jobs\GradeTestSubmission;
use App\Jobs\MakeHiringDecision;
use App\Jobs\ScoreInterviewAnswer;
use App\Models\AiInterview;
use App\Models\AiInterviewConfig;
use App\Models\AiInterviewCriterion;
use App\Models\AiInterviewTurn;
use App\Models\AiTestSubmission;
use App\Models\AiTestTask;
use App\Models\Applicant;
use App\Models\Category;
use App\Models\City;
use App\Models\Employer;
use App\Models\Industry;
use App\Models\Resume;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyResponse;
use App\Services\Ai\Interviewer;
use App\Services\Security\InjectionGuard;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Прогон калибровочных кандидатов через отбор.
 *
 * Гоняет профили из CalibrationProfiles по тем же задачам и сервисам, которыми
 * пользуется живой кандидат: BuildRequirementMatrix, ScoreInterviewAnswer,
 * ComposeTestTask, GradeTestSubmission, MakeHiringDecision. Своей арифметики
 * здесь нет вовсе — иначе калибровалась бы копия правил, а не сами правила.
 *
 * Из контроллера повторён только цикл «вопрос — ответ»: всё остальное в нём —
 * защита от двойной отправки, озвучка, JSON для страницы — к калибровке
 * отношения не имеет.
 *
 * Данные создаются настоящие, в рабочей базе, и помечаются служебной почтой
 * (MARK). Убирает их за собой cleanup(): калибровка не должна оставлять
 * вымышленных людей в списке кандидатов работодателя.
 */
class CalibrationRunner
{
    /**
     * Метка служебных записей. По ней же они и удаляются, поэтому домен
     * нарочно невозможный: настоящий человек с такой почтой не заведётся.
     */
    public const MARK = '@calibration.invalid';

    /** Предохранитель от бесконечного разговора, если цель всё не достигается. */
    private const MAX_QUESTIONS = 20;

    public function __construct(
        private readonly Interviewer $interviewer,
        private readonly InjectionGuard $guard,
    ) {
    }

    /**
     * Вакансия для калибровки: создаётся один раз на прогон.
     */
    public function vacancy(): Vacancy
    {
        $employer = $this->employer();

        $vacancy = Vacancy::create([
            'title' => CalibrationProfiles::VACANCY['title'],
            'description' => 'Служебная вакансия для калибровки ИИ-отбора.',
            'employer_id' => $employer->id,
            'salary_from' => 4000,
            'salary_to' => 9000,
            'currency' => 'TJS',
            'employment_type' => 'full-time',
            'work_schedule' => 'full_day',
            'experience_required' => '3_years',
            'skill' => 'PHP, Laravel, MySQL',
            // неактивная: служебная вакансия не должна попасть в общий поиск
            'status' => 'inactive',
            'city_id' => $employer->city_id,
        ]);

        AiInterviewConfig::create(
            ['vacancy_id' => $vacancy->id, 'enabled' => true]
            + array_diff_key(CalibrationProfiles::VACANCY, ['title' => null])
        );

        foreach (CalibrationProfiles::CRITERIA as $position => $criterion) {
            AiInterviewCriterion::create($criterion + [
                'vacancy_id' => $vacancy->id,
                'source' => 'employer',
                'confirmed_at' => now(),
                'position' => $position,
            ]);
        }

        return $vacancy->fresh();
    }

    /**
     * Один кандидат: от согласия до объявленного решения.
     *
     * @param  array  $profile  строка из CalibrationProfiles
     * @return array{
     *     slug:string, name:string, expect:string, expect_gate:?string,
     *     outcome:?string, gate:?string, total:?int,
     *     documents:?int, interview:?int, test:?int,
     *     questions:int, follow_up:int, injections:int,
     *     matched:bool, interview_id:int, note:?string
     * }
     */
    public function run(array $profile, Vacancy $vacancy): array
    {
        $interview = $this->enrol($profile, $vacancy);

        // 1. разбор резюме и сверка требований
        (new BuildRequirementMatrix($interview->id))->handle(app(\App\Services\Ai\DocumentAuditor::class));

        $interview->refresh();

        if ($interview->analysis_status !== 'ready') {
            return $this->row($profile, $interview, 'Разбор документов не состоялся.');
        }

        // 2. разговор, задание, решение — и, если балл пограничный, ещё круг
        $this->converse($profile, $interview, $vacancy);
        $this->test($profile, $interview);
        $this->decide($interview);

        $interview->refresh();

        // пограничный дораунд вернул кандидата в разговор: цель выросла
        if (! $interview->isDecided() && $interview->stage === 'interview') {
            $this->converse($profile, $interview, $vacancy);
            $this->decide($interview);
            $interview->refresh();
        }

        return $this->row($profile, $interview);
    }

    /**
     * Убрать за собой всё созданное калибровкой.
     *
     * Порядок не случаен: вакансия каскадом уносит настройки, критерии,
     * собеседования и отклики; резюме от анкеты каскадом не уходят, их надо
     * удалить раньше самой анкеты; пользователь уносит анкету и работодателя.
     *
     * @return array{users:int,vacancies:int}
     */
    public function cleanup(): array
    {
        $users = User::where('email', 'like', '%'.self::MARK)->get();

        if ($users->isEmpty()) {
            return ['users' => 0, 'vacancies' => 0];
        }

        $employers = Employer::whereIn('user_id', $users->pluck('id'))->pluck('id');
        $vacancies = Vacancy::whereIn('employer_id', $employers)->get();

        foreach ($vacancies as $vacancy) {
            $vacancy->delete();
        }

        $applicants = Applicant::whereIn('user_id', $users->pluck('id'))->pluck('id');

        Resume::whereIn('applicant_id', $applicants)->delete();

        foreach ($users as $user) {
            $user->delete();
        }

        return ['users' => $users->count(), 'vacancies' => $vacancies->count()];
    }

    // ==================== шаги ====================

    /**
     * Кандидат: пользователь, анкета, резюме, отклик, согласие.
     */
    private function enrol(array $profile, Vacancy $vacancy): AiInterview
    {
        $user = $this->user($profile['name'], 'calib-'.$profile['slug'], 'applicant');

        $applicant = Applicant::create([
            'user_id' => $user->id,
            // образование обязательно на уровне базы: у кого его нет, так и пишем
            'education' => $profile['education'] ?? 'Не указано',
            'about_me' => $profile['about'],
        ]);

        // зарплата в резюме обязательна на уровне базы, а к отбору отношения не
        // имеет: в критериях её нет, и на оценку она не влияет
        Resume::create($profile['resume'] + [
            'applicant_id' => $applicant->id,
            'desired_salary' => 6000,
        ]);

        $response = VacancyResponse::create([
            'applicant_id' => $applicant->id,
            'vacancy_id' => $vacancy->id,
        ]);

        return AiInterview::create([
            'vacancy_id' => $vacancy->id,
            'applicant_id' => $applicant->id,
            'vacancy_response_id' => $response->id,
            'stage' => 'documents',
            'consent_at' => now(),
            'consent_ip' => '127.0.0.1',
        ]);
    }

    /**
     * Разговор: вопрос от модели, заготовленный ответ, оценка.
     *
     * Ответ берётся по критерию заданного вопроса — так заготовка попадает
     * в тему. Для вопроса без критерия есть общий ответ.
     */
    private function converse(array $profile, AiInterview $interview, Vacancy $vacancy): void
    {
        $criteria = AiInterviewCriterion::where('vacancy_id', $vacancy->id)
            ->confirmed()->ordered()->get();

        $guard = 0;

        while ($interview->stage === 'interview' && $guard++ < self::MAX_QUESTIONS) {
            $asked = $interview->turns()->where('role', AiInterviewTurn::ROLE_AI)->count();

            if ($asked >= $interview->questionTarget()) {
                break;
            }

            $next = $this->interviewer->nextQuestion($interview, $criteria);

            $question = AiInterviewTurn::create([
                'ai_interview_id' => $interview->id,
                'position' => $this->nextPosition($interview),
                'role' => AiInterviewTurn::ROLE_AI,
                'text' => $next['text'],
                'question_kind' => $next['kind'],
                'criterion_key' => $next['criterion_key'],
                'speech_status' => 'none',
            ]);

            $text = $profile['answers'][$next['criterion_key'] ?? '*']
                ?? $profile['answers']['*'];

            $found = $this->guard->inspect($text);

            $answer = AiInterviewTurn::create([
                'ai_interview_id' => $interview->id,
                'position' => $this->nextPosition($interview),
                'role' => AiInterviewTurn::ROLE_CANDIDATE,
                'text' => $text,
                'criterion_key' => $question->criterion_key,
                'injection_flagged' => $found['flagged'],
            ]);

            if ($found['flagged']) {
                $this->guard->record($found, $interview, $answer);
            }

            (new ScoreInterviewAnswer($answer->id))->handle($this->interviewer);

            $interview->refresh();
        }

        // разговор окончен — дальше задание
        if ($interview->stage === 'interview') {
            $interview->update(['stage' => 'test']);
        }
    }

    /**
     * Тестовое задание: составление, сдача, проверка.
     *
     * Профиль без решения — это кандидат, который не сдал. Он и должен пройти
     * тем же путём, что живой молчун: нулевой балл, а не пропуск этапа.
     */
    private function test(array $profile, AiInterview $interview): void
    {
        $examiner = app(\App\Services\Ai\TaskExaminer::class);

        (new ComposeTestTask($interview->id))->handle($examiner);

        $interview->refresh();

        $task = $interview->testTasks()->where('kind', AiTestTask::KIND_MAIN)->latest('id')->first();

        if (! $task) {
            return;
        }

        if ($profile['solution'] === null) {
            AiTestSubmission::create([
                'ai_test_task_id' => $task->id,
                'content' => null,
                'score' => 0,
                'review' => [],
                'submitted_at' => null,
                'graded_at' => now(),
            ]);

            $interview->update(['test_score' => 0, 'stage' => 'decision']);

            return;
        }

        $found = $this->guard->inspect($profile['solution']);

        if ($found['flagged']) {
            $this->guard->record($found, $interview);
        }

        $submission = AiTestSubmission::create([
            'ai_test_task_id' => $task->id,
            'content' => $profile['solution'],
            'submitted_at' => now(),
        ]);

        $interview->update(['stage' => 'decision']);

        (new GradeTestSubmission($submission->id))->handle($examiner);
    }

    /**
     * Решение. Ожидание догоняющих оценок здесь не нужно: всё посчитано
     * подряд, очереди не участвуют.
     */
    private function decide(AiInterview $interview): void
    {
        (new MakeHiringDecision($interview->id, MakeHiringDecision::MAX_WAITS))
            ->handle(app(\App\Services\Ai\DecisionWriter::class));
    }

    // ==================== мелочи ====================

    private function nextPosition(AiInterview $interview): int
    {
        return (int) $interview->turns()->max('position') + 1;
    }

    /**
     * Строка итога по кандидату.
     */
    private function row(array $profile, AiInterview $interview, ?string $note = null): array
    {
        $interview->refresh();

        $decision = $interview->latestDecision;

        return [
            'slug' => $profile['slug'],
            'name' => $profile['name'],
            'expect' => $profile['expect'],
            'expect_gate' => $profile['expect_gate'],
            'outcome' => $interview->outcome,
            'gate' => $decision?->gate_reason,
            'total' => $interview->total_score,
            'documents' => $interview->documents_score,
            'interview' => $interview->interview_score,
            'test' => $interview->test_score,
            'questions' => $interview->turns()->where('role', AiInterviewTurn::ROLE_AI)->count(),
            'follow_up' => (int) $interview->follow_up_round,
            'injections' => $interview->injectionAttempts()->count(),
            'matched' => $interview->outcome === $profile['expect']
                && ($profile['expect_gate'] === null || $decision?->gate_reason === $profile['expect_gate']),
            'interview_id' => $interview->id,
            'note' => $note,
        ];
    }

    /**
     * Служебный пользователь.
     *
     * role и status — колонки уровня базы, в fillable их нет намеренно: роль
     * не должна назначаться массовым присваиванием из формы. Здесь она ставится
     * явно, и это единственное место в калибровке, где нужен forceFill.
     */
    private function user(string $name, string $prefix, string $role): User
    {
        $user = new User();

        $user->forceFill([
            'name' => $name,
            'email' => $prefix.'-'.Str::lower(Str::random(8)).self::MARK,
            'password' => Hash::make(Str::random(32)),
            'role' => $role,
            'status' => 'active',
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    /**
     * Служебный работодатель. Справочники берём существующие: заводить свои
     * города и отрасли ради калибровки — значит мусорить в общих данных.
     */
    private function employer(): Employer
    {
        $user = $this->user('Калибровка ИИ-отбора', 'calib-employer', 'employer');

        $city = City::query()->first();
        $category = Category::query()->first();
        $industry = Industry::query()->first();

        if (! $city || ! $category || ! $industry) {
            throw new RuntimeException(
                'В базе нет справочников (город, категория, отрасль) — калибровке не на чем построить вакансию.'
            );
        }

        return Employer::create([
            'user_id' => $user->id,
            'company_name' => 'Калибровка',
            'job' => 'HR',
            'email_company' => 'calib'.self::MARK,
            'phone' => '+992000000000',
            'category_id' => $category->id,
            'description' => 'Служебная запись для калибровки ИИ-отбора.',
            'city_id' => $city->id,
            'industry_id' => $industry->id,
            'website_url' => 'https://workio.tj',
        ]);
    }
}
