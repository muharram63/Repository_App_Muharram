<?php

namespace App\Services\Payments;

use App\Models\Trainer\TrainerPayment;

/**
 * Способ оплаты пакета попыток.
 *
 * Сейчас один — ручной перевод по реквизитам (ManualTransferGateway): кандидат
 * переводит деньги сам и нажимает «Я оплатил», администратор подтверждает.
 * Банк подключается новой реализацией этого интерфейса и одной строкой
 * привязки в AppServiceProvider — ни страницы, ни попытки, ни журнал не
 * меняются: они работают с интерфейсом, а не с конкретным способом.
 */
interface PaymentGateway
{
    /** Короткий ключ способа: пишется в заявку (trainer_payments.gateway). */
    public function key(): string;

    /** Что показать кандидату: реквизиты, сумма, назначение платежа, пояснение. */
    public function instructions(TrainerPayment $payment): array;

    /**
     * Подтверждает ли способ оплату сам (банк по обратному вызову). Ручной
     * перевод — нет: его подтверждает администратор.
     */
    public function confirmsAutomatically(): bool;
}
