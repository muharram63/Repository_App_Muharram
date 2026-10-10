<?php

namespace App\Support;

use App\Models\AiDecision;
use App\Models\AiInterview;
use App\Models\User;
use App\Models\VacancyResponse;
use App\Notifications\AwaitingDecisionAlert;
use App\Notifications\EmployerAlert;
use App\Notifications\InterviewReportAlert;
use App\Notifications\NewCandidateAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Уведомления работодателю о кандидатах — одна точка отправки.
 *
 * Здесь три правила, которые не должны расходиться между местами вызова:
 * — получатель только один: владелец вакансии;
 * — работодатель сам выключает вид уведомления в настройках;
 * — одно событие — одно уведомление: ключ события пишется в notification_log
 *   уникально ДО отправки, и повтор (второй запрос, повтор задачи очереди)
 *   ничего не отправит.
 */
class EmployerAlerts
{
    /** Новый отклик на вакансию. */
    public static function newCandidate(VacancyResponse $response): bool
    {
        return self::send(
            $response->vacancy?->employer?->user,
            new NewCandidateAlert($response),
            'response:'.$response->id,
        );
    }

    /** Кандидат прошёл ИИ-собеседование, отчёт готов. */
    public static function reportReady(AiInterview $interview, AiDecision $decision): bool
    {
        $criteria = \App\Models\AiInterviewCriterion::where('vacancy_id', $interview->vacancy_id)->confirmed()->get();
        $match = MatchPercent::compute($criteria, $interview->requirementChecks()->get())['overall']
            ?? $decision->total;

        return self::send(
            $interview->vacancy?->employer?->user,
            new InterviewReportAlert($interview, $decision, $match),
            'report:'.$decision->id,
        );
    }

    /** Разбор документов не удался — кандидата решает работодатель. */
    public static function manualReview(AiInterview $interview, string $reason): bool
    {
        // ключ — с моментом запуска разбора: перезапущенный и снова
        // сорвавшийся разбор — новое событие, о нём тоже надо сказать
        $attempt = $interview->analysis_started_at?->timestamp ?? 0;

        return self::send(
            $interview->vacancy?->employer?->user,
            new \App\Notifications\ManualReviewAlert($interview, $reason),
            'manual:'.$interview->id.':'.$attempt,
        );
    }

    /**
     * Напомнить о кандидатах, ждущих решения дольше суток, — не чаще раза в
     * сутки на работодателя. $employerUserId — только ему (из страницы),
     * null — всем (из планировщика). Возвращает, скольким напомнили.
     */
    public static function remindAwaiting(?int $employerUserId = null): int
    {
        $waiting = AiDecision::query()
            ->whereNull('released_at')
            ->where('created_at', '<=', now()->subHours(\App\Services\Scoring\DecisionRelease::REMIND_AFTER_HOURS))
            ->with('interview.config', 'interview.vacancy.employer.user')
            ->get()
            // действует только последнее решение собеседования
            ->filter(fn (AiDecision $d) => $d->interview?->latestDecision?->is($d))
            // работодатель может выключить напоминания у вакансии
            ->filter(fn (AiDecision $d) => $d->interview?->config?->remindsEmployer())
            ->groupBy(fn (AiDecision $d) => $d->interview?->vacancy?->employer?->user_id)
            ->filter(fn ($group, $userId) => $userId && (! $employerUserId || (int) $userId === $employerUserId));

        $reminded = 0;

        foreach ($waiting as $userId => $decisions) {
            $sent = self::send(
                User::find($userId),
                new AwaitingDecisionAlert($decisions->count()),
                'awaiting:'.now()->toDateString(),
            );

            $reminded += $sent ? 1 : 0;
        }

        return $reminded;
    }

    private static function send(?User $user, EmployerAlert $alert, string $eventKey): bool
    {
        if (! $user || ! $user->setting($alert->setting())) {
            return false;
        }

        // одно событие — одно уведомление: второй раз ключ не вставится
        $fresh = DB::table('notification_log')->insertOrIgnore([
            'user_id' => $user->id,
            'event_key' => $eventKey,
            'created_at' => now(),
        ]);

        if (! $fresh) {
            return false;
        }

        try {
            $user->notify($alert);
        } catch (\Throwable $e) {
            // сбой почты не должен ронять отклик или решение: на сайте
            // уведомление уже записано, ошибка — в журнал
            Log::warning('Уведомление работодателю не ушло целиком', [
                'user' => $user->id,
                'event' => $eventKey,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }
}
