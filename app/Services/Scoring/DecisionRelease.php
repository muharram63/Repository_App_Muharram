<?php

namespace App\Services\Scoring;

use App\Models\AiDecision;
use App\Models\AiInterview;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Отправка решения кандидату.
 *
 * Решение ИИ — рекомендация. Кандидат узнаёт итог, только когда работодатель
 * разрешил: подтвердил или изменил решение в отчёте (кнопкой или голосом в
 * разговоре с ИИ). Сам ИИ ответ кандидату не отправляет никогда — ни сразу,
 * ни по истечении срока; он только напоминает работодателю.
 */
class DecisionRelease
{
    /** Через сколько часов ожидания ИИ напоминает работодателю о кандидате. */
    public const REMIND_AFTER_HOURS = 24;

    /**
     * Отправить ответ кандидату.
     *
     * @param  string  $source  employer — решение работодателя (другого источника нет)
     * @return bool отправлено ли сейчас (false — уже было отправлено)
     */
    public function release(AiDecision $decision, string $source): bool
    {
        if ($decision->isReleased()) {
            return false;
        }

        $decision->update([
            'released_at' => now(),
            'release_source' => $source,
        ]);

        $interview = $decision->interview;
        $outcome = $decision->effectiveOutcome();

        $this->applyToResponse($interview, $outcome, $source);

        UserNotification::deliver(
            $interview?->applicant?->user_id,
            'response_status',
            UserNotification::text(DecisionMessage::headline($outcome)),
            Str::limit((string) $decision->candidateMessage(), 160),
            route('applicant.ai.result', $interview),
        );

        // и на почту — если в .env настроен SMTP; иначе только уведомление
        \App\Support\CandidateMail::decision($decision->fresh());

        return true;
    }

    /**
     * Напомнить работодателю о кандидатах, которые ждут ответа дольше срока.
     *
     * Раньше в режиме «ИИ решает сам» ответ через 24 часа уходил кандидату
     * без работодателя. Теперь так не бывает никогда: ИИ только советует,
     * а отправляет человек. Вместо отправки — напоминание работодателю, не
     * чаще раза в сутки на каждое решение, пока он не ответит.
     *
     * @param  int|null  $interviewId  только это собеседование
     * @return int сколько напоминаний отправлено
     */
    public function remindOverdue(?int $interviewId = null): int
    {
        /*
         * Напоминание теперь одно на работодателя — «У вас N кандидатов ждут
         * решения», не чаще раза в сутки (EmployerAlerts). Раньше уходило по
         * напоминанию на каждого кандидата, и вместе с общим получались дубли.
         * Здесь лишь выбираем, кому: владельцу этого собеседования или всем.
         */
        $employerUserId = $interviewId
            ? AiInterview::with('vacancy.employer')->find($interviewId)?->vacancy?->employer?->user_id
            : null;

        if ($interviewId && ! $employerUserId) {
            return 0;
        }

        return \App\Support\EmployerAlerts::remindAwaiting($employerUserId);
    }

    /**
     * Статус отклика по отправленному решению.
     *
     * Работодатель решил сам — ставим его решение, это прямое распоряжение.
     * Отправил ИИ — не трогаем статус, который работодатель уже поставил в
     * обычном списке откликов.
     */
    private function applyToResponse(?AiInterview $interview, string $outcome, string $source): void
    {
        $response = $interview?->response;

        if (! $response || ! in_array($outcome, [DecisionEngine::PASSED, DecisionEngine::REJECTED], true)) {
            return;
        }

        if ($source !== 'employer' && in_array($response->status, ['accepted', 'rejected'], true)) {
            return;
        }

        $response->update(['status' => $outcome === DecisionEngine::PASSED ? 'accepted' : 'rejected']);
    }
}
