<?php

namespace App\Services\Orders;

use App\Models\UrgentOrder;
use App\Models\UrgentOrderOffer;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Закрытие заявок, к сроку которых исполнитель так и не выбран.
 *
 * Заявка со статусом «Ищем мастера» и истёкшим сроком становится «Срок
 * истёк», а заказчик получает уведомление со ссылкой «Повторить». Заявка с
 * выбранным исполнителем не трогается: мастер может прийти и чуть позже,
 * закрывает её заказчик кнопкой «Выполнено».
 *
 * Запускается планировщиком (orders:expire) и — поскольку планировщик
 * работает не всегда — при открытии ленты, «Моих заявок» и самой заявки,
 * не чаще раза в минуту (sweepSoon).
 */
class OrderExpiry
{
    /** Закрыть просроченные. Возвращает, сколько закрыто. */
    public function expireOverdue(): int
    {
        $expired = 0;

        UrgentOrder::query()
            ->open()
            ->where('deadline_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$expired) {
                foreach ($orders as $order) {
                    if ($this->expire($order)) {
                        $expired++;
                    }
                }
            });

        return $expired;
    }

    /**
     * Закрыть одну заявку. false — её уже закрыл кто-то другой.
     *
     * Статус меняется условным UPDATE «где всё ещё open»: если планировщик и
     * открытие страницы сработают одновременно, заявку закроет и уведомление
     * отправит только один из них.
     */
    public function expire(UrgentOrder $order): bool
    {
        $closed = DB::transaction(function () use ($order) {
            $changed = UrgentOrder::whereKey($order->id)
                ->where('status', UrgentOrder::STATUS_OPEN)
                ->update(['status' => UrgentOrder::STATUS_EXPIRED, 'closed_at' => now()]);

            if ($changed === 0) {
                return false;
            }

            // ждавшие ответа мастера — «срок заявки истёк», а не «выбран другой»
            UrgentOrderOffer::where('urgent_order_id', $order->id)
                ->where('status', UrgentOrderOffer::STATUS_PENDING)
                ->update(['status' => UrgentOrderOffer::STATUS_EXPIRED]);

            return true;
        });

        if (! $closed) {
            return false;
        }

        $offers = $order->offers()->where('status', UrgentOrderOffer::STATUS_EXPIRED)->count();

        UserNotification::deliver(
            $order->user_id,
            'order',
            UserNotification::text('Срок заявки истёк'),
            UserNotification::text($offers > 0
                ? '«:title»: исполнитель не выбран, откликов было :count. Повторите заявку — данные подставятся сами.'
                : '«:title»: к сроку мастер не нашёлся. Повторите заявку — данные подставятся сами.', [
                    'title' => Str::limit($order->title, 60),
                    'count' => $offers,
                ]),
            route('public.orders.create', ['repeat' => $order->id]),
        );

        return true;
    }

    /**
     * То же, но не чаще раза в минуту — для вызова при открытии страниц.
     * Сбой проверки не должен ломать страницу: это подстраховка планировщика.
     */
    public function sweepSoon(): void
    {
        try {
            if (Cache::add('orders-expire-sweep', true, now()->addMinute())) {
                $this->expireOverdue();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
