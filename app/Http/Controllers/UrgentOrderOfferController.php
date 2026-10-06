<?php

namespace App\Http\Controllers;

use App\Models\UrgentOrder;
use App\Models\UrgentOrderOffer;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Отклик мастера на срочную заявку и его отзыв.
 *
 * Откликаться может соискатель с портфолио: заказчик выбирает по фото
 * сделанных работ, и отклик без них ему ничего не говорит.
 */
class UrgentOrderOfferController extends Controller
{
    public function store(Request $request, UrgentOrder $order)
    {
        $applicant = $request->user()->applicant;

        if ($reason = self::cannotOffer($request->user(), $order)) {
            return back()->with('error', $reason);
        }

        $data = $request->validate([
            'message' => 'required|string|min:5|max:1000',
            'price' => 'nullable|integer|min:1|max:10000000',
            'can_start' => 'required|in:'.implode(',', array_keys(UrgentOrderOffer::CAN_START)),
        ]);

        $existing = UrgentOrderOffer::where('urgent_order_id', $order->id)
            ->where('applicant_id', $applicant->id)
            ->first();

        if ($existing && $existing->status !== UrgentOrderOffer::STATUS_WITHDRAWN) {
            return back()->with('error', __('Вы уже откликнулись на эту заявку.'));
        }

        // отозванный отклик возвращается той же строкой — дубли запрещены уникальным ключом
        $offer = UrgentOrderOffer::updateOrCreate(
            ['urgent_order_id' => $order->id, 'applicant_id' => $applicant->id],
            $data + ['status' => UrgentOrderOffer::STATUS_PENDING],
        );

        $count = $order->offers()->where('status', UrgentOrderOffer::STATUS_PENDING)->count();

        // одно уведомление на заявку, обновляется с каждым новым откликом
        UserNotification::deliver(
            $order->user_id,
            'order',
            UserNotification::text('Новый отклик на заявку «:title»', ['title' => Str::limit($order->title, 60)]),
            UserNotification::text(':name · :price · откликов: :count', [
                'name' => $request->user()->name,
                'price' => $offer->price ? number_format($offer->price, 0, ',', ' ').' '.__('сомони') : __('цена после осмотра'),
                'count' => $count,
            ]),
            route('public.orders.show', $order),
            collapse: true,
        );

        return redirect()->route('public.orders.show', $order)
            ->with('status', __('Отклик отправлен. Заказчик посмотрит ваши работы и, если выберет вас, мы сообщим.'));
    }

    public function withdraw(Request $request, UrgentOrder $order)
    {
        $offer = UrgentOrderOffer::where('urgent_order_id', $order->id)
            ->where('applicant_id', $request->user()->applicant?->id)
            ->where('status', UrgentOrderOffer::STATUS_PENDING)
            ->first();

        abort_unless($offer, 404);

        $offer->update(['status' => UrgentOrderOffer::STATUS_WITHDRAWN]);

        return redirect()->route('public.orders.show', $order)->with('status', __('Отклик отозван.'));
    }

    /**
     * Почему этот пользователь не может откликнуться — или null, если может.
     * Тем же текстом объясняет и страница заявки, вместо формы.
     */
    public static function cannotOffer(?\App\Models\User $user, UrgentOrder $order): ?string
    {
        if (! $user) {
            return __('Войдите как соискатель-мастер, чтобы откликнуться.');
        }

        if ($order->isOwnedBy($user)) {
            return __('Это ваша заявка.');
        }

        if (! $order->isOpen() || $order->deadline_at->isPast()) {
            return __('Заявка уже закрыта.');
        }

        $applicant = $user->applicant;

        if (! $applicant) {
            return __('Откликаться могут мастера — соискатели с портфолио работ.');
        }

        if (! $applicant->portfolioWorks()->exists()) {
            return __('Чтобы откликаться на заявки, добавьте в портфолио хотя бы одну выполненную работу: заказчик выбирает мастера по фото его работ.');
        }

        return null;
    }
}
