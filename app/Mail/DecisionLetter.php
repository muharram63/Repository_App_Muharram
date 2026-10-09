<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Письмо кандидату с ответом по собеседованию.
 *
 * Текст — тот же, что в уведомлении на сайте: что предложил ИИ или что
 * работодатель исправил в черновике. Письмо дублирует уведомление, а не
 * заменяет его: кандидат увидит ответ и на почте, и в кабинете.
 */
class DecisionLetter extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly string $headline,
        public readonly string $text,
        public readonly string $vacancyTitle,
        public readonly ?string $url = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->headline.' — «'.$this->vacancyTitle.'»');
    }

    public function content(): Content
    {
        return new Content(text: 'emails.decision-letter');
    }
}
