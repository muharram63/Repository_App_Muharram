<?php

namespace App\Support;

use App\Mail\DecisionLetter;
use App\Models\AiDecision;
use App\Services\Scoring\DecisionMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Письма кандидату на почту — когда почта настроена.
 *
 * Пока в .env стоит MAIL_MAILER=log (или array), письма никуда не уходят:
 * ответ кандидат получает уведомлением на сайте, как и раньше. Как только в
 * .env появятся настройки SMTP (MAIL_MAILER=smtp, MAIL_HOST, MAIL_PORT,
 * MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS), то же письмо сразу уйдёт
 * и на почту — без правок кода.
 *
 * Сбой почты ответ кандидату не отменяет: уведомление на сайте уже ушло, а
 * ошибка пишется в журнал. Почтовый сервер не должен решать судьбу человека.
 */
class CandidateMail
{
    /** Почта настроена: письма уходят на самом деле, а не в журнал. */
    public static function enabled(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', null, ''], true);
    }

    /** Отправить кандидату письмо с ответом по решению. */
    public static function decision(AiDecision $decision): bool
    {
        $interview = $decision->interview;
        $email = $interview?->applicant?->user?->email;
        $text = trim((string) $decision->candidateMessage());

        if (! self::enabled() || blank($email) || $text === '') {
            return false;
        }

        $locale = $interview->config?->candidateLocale() ?? 'ru';

        try {
            Mail::to($email)->locale($locale)->send(new DecisionLetter(
                __(DecisionMessage::headline($decision->effectiveOutcome()), [], $locale),
                $text,
                (string) $interview->vacancy?->title,
                route('applicant.ai.result', $interview),
            ));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Письмо кандидату не ушло', [
                'interview' => $interview->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
