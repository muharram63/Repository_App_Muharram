<?php

namespace App\Notifications\Channels;

use App\Models\UserNotification;
use Illuminate\Notifications\Notification;

/**
 * Канал «на сайте» для уведомлений Laravel.
 *
 * Пишет в ту же таблицу user_notifications, что и все остальные уведомления
 * площадки: из неё работают колокольчик со счётчиком, всплывающее окно,
 * страница «Уведомления», «прочитано» и «прочитать все». Стандартный канал
 * database завёл бы вторую, отдельную ленту, которую колокольчик не видит, —
 * работодатель получал бы уведомления, о которых ничего не знает.
 *
 * Уведомление отдаёт данные методом toSite(): тип, заголовок, текст и адрес.
 * Заголовок и текст — ключи перевода с подстановками (UserNotification::text):
 * переводятся на язык получателя при записи.
 */
class SiteChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSite')) {
            return;
        }

        $data = $notification->toSite($notifiable);

        UserNotification::deliver(
            $notifiable->id,
            $data['type'],
            $data['title'],
            $data['body'] ?? null,
            $data['url'] ?? null,
        );
    }
}
