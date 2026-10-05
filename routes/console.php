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
Schedule::command('queue:work --stop-when-empty --max-time=55 --sleep=1')
    ->everyMinute()
    ->withoutOverlapping();
