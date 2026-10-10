<?php

namespace App\Services\Trainer;

use App\Jobs\BuildTrainerFeedback;
use App\Models\AiInterview;
use App\Models\AiInterviewConfig;
use App\Models\AiInterviewCriterion;
use App\Models\Trainer\TrainerSession;
use App\Models\Trainer\TrainerTrack;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vacancy;

/**
 * Запуск тренировки на готовом движке ИИ-собеседования.
 *
 * Тренировка — обычное ИИ-собеседование, только по скрытой учебной вакансии
 * служебного аккаунта. Поэтому всё, что движок умеет, работает и здесь без
 * единой правки: голос Анны, вопросы по резюме, тестовое задание, запуск
 * кода, оценка, запасная модель. Учебная вакансия получает настройки под
 * направление и уровень:
 * — документы не нужны (вес 0): тренировка начинается сразу с разговора;
 * — разговор и задание — пополам;
 * — работодателю ничего не напоминается (decision_mode advisory), а
 *   служебному аккаунту уведомления выключены вовсе.
 */
class TrainerSessions
{
    public function __construct(private readonly TrainerWallet $wallet)
    {
    }

    /**
     * Начать тренировку: списать попытку и завести учебное собеседование.
     *
     * @throws TrainerException
     */
    public function start(User $user, TrainerTrack $track, string $level, ?string $ip = null): TrainerSession
    {
        $applicant = $user->applicant;

        if (! $applicant) {
            throw new TrainerException(__('Сначала заполните анкету соискателя — по ней Анна задаёт вопросы.'));
        }

        if (! $track->active || $track->criteria() === []) {
            throw new TrainerException(__('Это направление сейчас недоступно. Выберите другое.'));
        }

        $levels = config('trainer.levels');
        if (! isset($levels[$level])) {
            throw new TrainerException(__('Выберите уровень: Junior, Middle или Senior.'));
        }

        return $this->wallet->spendOn($user, function () use ($user, $applicant, $track, $level, $levels, $ip) {
            $vacancy = $this->vacancy($track, $level, $levels[$level]);

            $interview = AiInterview::create([
                'vacancy_id' => $vacancy->id,
                'applicant_id' => $applicant->id,
                // согласие дано на странице тренажёра — галочкой перед стартом
                'consent_at' => now(),
                'consent_ip' => $ip,
                // документов нет: сразу разговор, разбор считается готовым
                'stage' => 'interview',
                'analysis_status' => 'ready',
                'analysed_at' => now(),
                'documents_score' => 0,
            ]);

            return TrainerSession::create([
                'user_id' => $user->id,
                'track_id' => $track->id,
                'track_title' => $track->title,
                'level' => $level,
                'vacancy_id' => $vacancy->id,
                'ai_interview_id' => $interview->id,
                'status' => TrainerSession::STATUS_ACTIVE,
            ]);
        });
    }

    /**
     * Движок объявил решение — тренировка окончена.
     *
     * Если решить не удалось по нашей вине (модель не оценила ответы или не
     * проверила задание), попытка возвращается сама: кандидат не платит за
     * наш сбой. Иначе — балл движка и разбор в очередь.
     */
    public function finish(TrainerSession $session, AiInterview $interview): void
    {
        if ($session->isRefunded() || $session->isFinished()) {
            return;
        }

        $gate = $interview->latestDecision()->value('gate_reason');

        if ($gate === 'invalid_ai_response') {
            $this->wallet->refund($session, 'Сбой проверки ИИ: тренировка не засчитана');

            UserNotification::deliver(
                $session->user_id,
                'trainer',
                UserNotification::text('Попытка возвращена'),
                UserNotification::text('Тренировку «:track» не удалось проверить из-за сбоя на нашей стороне. Попытка вернулась на счёт — можно пройти заново.', ['track' => $session->track_title]),
                route('applicant.trainer.index'),
            );

            return;
        }

        $session->update([
            'status' => TrainerSession::STATUS_FINISHED,
            'score' => $interview->total_score,
            'finished_at' => now(),
            'feedback_status' => 'pending',
        ]);

        BuildTrainerFeedback::dispatch($session->id);
    }

    /** Учебная вакансия под тренировку: своя на каждую, скрытая. */
    private function vacancy(TrainerTrack $track, string $level, array $config): Vacancy
    {
        $criteria = $track->criteria();

        $vacancy = Vacancy::create([
            'title' => $track->title.' · '.$config['label'],
            'description' => $track->description ?: $track->title,
            'employer_id' => TrainerSystem::employer()->id,
            'salary_from' => 0,
            'salary_to' => 0,
            'currency' => 'TJS',
            'employment_type' => 'full-time',
            'work_schedule' => 'remote_work',
            'experience_required' => $config['experience'],
            'skill' => mb_substr(collect($criteria)->pluck('label')->implode(', '), 0, 255),
            // в архиве: в каталоге и поиске её нет, откликнуться на неё нельзя
            'status' => 'in_archived',
            'city_id' => TrainerSystem::city()->id,
        ]);

        AiInterviewConfig::create([
            'vacancy_id' => $vacancy->id,
            'enabled' => true,
            'level' => $level,
            'language' => $track->language ?: 'ru',
            'questions_count' => $config['questions'],
            'weight_documents' => 0,
            'weight_interview' => 50,
            'weight_test' => 50,
            'threshold_reject' => 40,
            'threshold_accept' => 70,
            'test_time_limit_minutes' => $config['minutes'],
            // так Анна закончит разговор: «ответ вы получите …»
            'response_sla' => 'сразу после проверки — разбор появится в «Тренажёре»',
            'decision_mode' => 'advisory',
        ]);

        foreach ($criteria as $i => $criterion) {
            AiInterviewCriterion::create($criterion + [
                'vacancy_id' => $vacancy->id,
                // критерии направления задаёт администратор — как работодатель свои
                'source' => 'employer',
                // первый навык — сама профессия направления, остальные — навыки
                'basis' => $i === 0 ? 'title' : 'skills',
                'confirmed_at' => now(),
                'position' => $i,
            ]);
        }

        return $vacancy;
    }
}
