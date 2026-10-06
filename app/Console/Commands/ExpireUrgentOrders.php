<?php

namespace App\Console\Commands;

use App\Services\Orders\OrderExpiry;
use Illuminate\Console\Command;

/**
 * Закрыть срочные заявки, к сроку которых исполнитель не выбран.
 */
class ExpireUrgentOrders extends Command
{
    protected $signature = 'orders:expire';

    protected $description = 'Закрыть просроченные срочные заявки и сообщить заказчикам';

    public function handle(OrderExpiry $expiry): int
    {
        $this->info('Закрыто просроченных заявок: '.$expiry->expireOverdue());

        return self::SUCCESS;
    }
}
