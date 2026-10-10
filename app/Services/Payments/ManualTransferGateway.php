<?php

namespace App\Services\Payments;

use App\Models\Trainer\TrainerPayment;
use App\Models\Trainer\TrainerSetting;

/**
 * Ручной перевод: реквизиты и пояснение задаёт администратор в админке.
 *
 * Назначение платежа — код заявки (WK-00012): по нему администратор
 * находит перевод в выписке кошелька и подтверждает нужную заявку.
 */
class ManualTransferGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'manual';
    }

    public function instructions(TrainerPayment $payment): array
    {
        return [
            'wallet' => TrainerSetting::get('wallet'),
            'recipient' => TrainerSetting::get('recipient'),
            'amount' => $payment->amount,
            'reference' => $payment->reference(),
            'text' => TrainerSetting::get('instructions'),
        ];
    }

    public function confirmsAutomatically(): bool
    {
        return false;
    }
}
