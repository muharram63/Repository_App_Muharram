<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Планировщик. Работает, только если запущен сам планировщик:
 *   разработка — php artisan schedule:work (или composer dev);
 *   сервер — раз в минуту `php artisan schedule:run` (cron, на Windows —
 *   Планировщик заданий). Подробнее в README.
 */

// Просроченные задания тех, кто не вернулся: без этого собеседование
// навсегда оставалось без решения.
Schedule::command('ai:sweep')->everyMinute()->withoutOverlapping();

/*
 * Обработчик очереди. Весь модуль ИИ-собеседования живёт в очереди: разбор
 * документов, оценка ответов, задание, решение. Без обработчика задачи
 * копились в таблице jobs, а кандидат часами смотрел на «разбор идёт».
 *
 * Здесь он запускается планировщиком и сам завершается, когда очередь пуста
 * или вышла минута, — так он не требует отдельного постоянного процесса.
 * Если на сервере уже настроен постоянный `queue:work` (supervisor), эта
 * строка ему не мешает: задачи просто разберёт тот, кто успел первым.
 */
// Срочные заявки, к сроку которых мастер не выбран: «Срок истёк» и
// уведомление заказчику с кнопкой «Повторить». То же делается и при
// открытии ленты заказов — на случай, если планировщик не запущен.
Schedule::command('orders:expire')->everyFiveMinutes()->withoutOverlapping();

// Кандидаты ждут решения работодателя больше суток — одно напоминание
// «У вас N кандидатов ждут решения» в сутки на работодателя. Каждый час, чтобы
// не ждать до конкретного времени; дубль в тот же день не уйдёт.
Schedule::command('employers:remind-awaiting')->hourly()->withoutOverlapping();

// Тренажёр: заявки на оплату, по которым за неделю не нажали «Я оплатил», —
// «Срок заявки истёк», чтобы не висели в открытых у администратора.
Schedule::command('trainer:expire-payments')->hourly()->withoutOverlapping();

Schedule::command('queue:work --stop-when-empty --max-time=55 --sleep=1')
    ->everyMinute()
    ->withoutOverlapping();
