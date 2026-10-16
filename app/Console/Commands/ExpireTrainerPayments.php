<?php

namespace App\Console\Commands;

use App\Services\Trainer\TrainerPayments;
use Illuminate\Console\Command;

/**
 * Закрыть заявки тренажёра, по которым так и не нажали «Я оплатил».
 */
class ExpireTrainerPayments extends Command
{
    protected $signature = 'trainer:expire-payments';

    protected $description = 'Закрыть неоплаченные заявки тренажёра старше срока';

    public function handle(TrainerPayments $payments): int
    {
        $this->info('Закрыто заявок: '.$payments->expireStale());

        return self::SUCCESS;
    }
}
