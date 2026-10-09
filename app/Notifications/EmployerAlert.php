<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\SiteChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Уведомление работодателю о кандидате — общая часть.
 *
 * Каналы выбирает сам работодатель в настройках кабинета: на сайте
 * (колокольчик и всплывающее окно) и на почту. Третий канал — уведомление в
 * браузере — показывает сама страница из того же уведомления на сайте, когда
 * вкладка свёрнута: серверу для него ничего отправлять не нужно.
 *
 * Почта — стандартный канал mail. Пока в .env MAIL_MAILER=log, письма пишутся
 * в журнал; как только настроен SMTP, они начнут уходить без правок кода.
 */
abstract class EmployerAlert extends Notification
{
    /** Ключ настройки вида уведомления: notify_new_candidates и т. п. */
    abstract public function setting(): string;

    /** Заголовок и текст — ключи перевода с подстановками. */
    abstract protected function title(): array;

    abstract protected function body(): array;

    abstract protected function url(): string;

    public function via(object $notifiable): array
    {
        $channels = [];

        if ($notifiable->setting('channel_site')) {
            $channels[] = SiteChannel::class;
        }

        if ($notifiable->setting('channel_email') && filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toSite(object $notifiable): array
    {
        return [
            'type' => 'candidate',
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $this->localeOf($notifiable);

        return (new MailMessage)
            ->subject($this->translate($this->title(), $locale))
            ->line($this->translate($this->body(), $locale))
            ->action(__('Открыть в кабинете', [], $locale), $this->absolute($this->url()));
    }

    /** Ключ перевода с подстановками — в текст на языке получателя. */
    protected function translate(array $text, string $locale): string
    {
        [$key, $replace] = $text + [1 => []];

        $replace = array_map(
            fn ($value) => is_array($value) ? $this->translate($value, $locale) : $value,
            $replace,
        );

        return __($key, $replace, $locale);
    }

    private function localeOf(object $notifiable): string
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : null;

        return in_array($locale, config('app.available_locales', ['ru']), true) ? $locale : config('app.locale');
    }

    private function absolute(string $url): string
    {
        return str_starts_with($url, 'http') ? $url : url($url);
    }
}
