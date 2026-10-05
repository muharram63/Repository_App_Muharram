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
        $reminded = 0;

        AiDecision::query()
            ->whereNull('released_at')
            ->where('created_at', '<=', now()->subHours(self::REMIND_AFTER_HOURS))
            ->when($interviewId, fn ($q) => $q->where('ai_interview_id', $interviewId))
            ->with('interview.config', 'interview.vacancy.employer', 'interview.applicant.user')
            ->get()
            // действует только последнее решение собеседования
            ->filter(fn (AiDecision $d) => $d->interview?->latestDecision?->is($d))
            ->filter(fn (AiDecision $d) => $d->interview?->config?->remindsEmployer())
            ->each(function (AiDecision $decision) use (&$reminded) {
                if (! Cache::add('decision-reminder-'.$decision->id, true, now()->addHours(self::REMIND_AFTER_HOURS))) {
                    return;
                }

                $interview = $decision->interview;

                UserNotification::deliver(
                    $interview->vacancy?->employer?->user_id,
                    'interview',
                    UserNotification::text('Кандидат ждёт вашего ответа'),
                    UserNotification::text(':name ждёт ответа по вакансии «:title» больше суток. ИИ подготовил рекомендацию, но без вашего разрешения ничего не отправит.', [
                        'name' => $interview->applicant?->user?->name ?? UserNotification::text('Кандидат'),
                        'title' => $interview->vacancy?->title ?? '—',
                    ]),
                    route('employer.ai.candidate', [$interview->vacancy_id, $interview->id]),
                );

                $reminded++;
            });

        return $reminded;
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
