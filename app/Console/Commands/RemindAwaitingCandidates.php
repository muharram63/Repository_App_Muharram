<?php

namespace App\Console\Commands;

use App\Support\EmployerAlerts;
use Illuminate\Console\Command;

/**
 * Напомнить работодателям о кандидатах, которые ждут решения больше суток.
 *
 * Одно уведомление на работодателя в сутки: «У вас N кандидатов ждут
 * решения». Запускается планировщиком каждый час — так напоминание приходит
 * вскоре после того, как кандидат прождал сутки, а не только в одно время дня;
 * повторно в тот же день оно не уйдёт.
 */
class RemindAwaitingCandidates extends Command
{
    protected $signature = 'employers:remind-awaiting';

    protected $description = 'Напомнить работодателям о кандидатах, ждущих решения больше суток';

    public function handle(): int
    {
        $this->info('Напомнили работодателям: '.EmployerAlerts::remindAwaiting());

        return self::SUCCESS;
    }
}
