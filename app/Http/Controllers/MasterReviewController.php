<?php

namespace App\Http\Controllers;

use App\Models\MasterReview;
use App\Models\UrgentOrder;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Отзыв заказчика о мастере после выполненной заявки.
 *
 * Оставить можно только по своей выполненной заявке и только исполнителю —
 * оценка всегда стоит за реальной работой. Один отзыв на заявку; в течение
 * EDIT_DAYS дней его можно исправить.
 */
class MasterReviewController extends Controller
{
    public function store(Request $request, UrgentOrder $order)
    {
        abort_unless($order->isOwnedBy($request->user()), 404);

        if (! $order->canBeReviewedBy($request->user())) {
            return back()->with('error', __('Отзыв можно оставить по выполненной заявке в течение :days дней.', ['days' => MasterReview::EDIT_DAYS]));
        }

        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ], [
            'rating.required' => __('Поставьте оценку — от одной до пяти звёзд.'),
        ]);

        $master = $order->chosenOffer?->applicant;
        abort_unless($master, 404);

        $review = MasterReview::updateOrCreate(
            ['urgent_order_id' => $order->id],
            [
                'applicant_id' => $master->id,
                'user_id' => $request->user()->id,
                'rating' => $data['rating'],
                'comment' => filled($data['comment'] ?? null) ? trim($data['comment']) : null,
            ],
        );

        // мастеру — о новом отзыве; исправление того же отзыва склеивается с ним
        UserNotification::deliver(
            $master->user_id,
            'order',
            UserNotification::text('Новый отзыв: :stars', ['stars' => MasterReview::stars($review->rating)]),
            $review->comment ? Str::limit($review->comment, 120) : UserNotification::text('Заказчик оценил вашу работу по заявке «:title».', ['title' => Str::limit($order->title, 60)]),
            route('public.masters.show', $master).'#reviews',
            collapse: true,
        );

        return redirect()->to(route('public.orders.show', $order).'#review')
            ->with('status', $review->wasRecentlyCreated ? __('Спасибо за отзыв! Он поможет другим выбрать мастера.') : __('Отзыв обновлён.'));
    }

    /**
     * Ответ мастера на отзыв о нём. Публичный, виден под отзывом; его можно
     * исправить. Заказчику — уведомление.
     */
    public function reply(Request $request, MasterReview $review)
    {
        abort_unless($review->canBeRepliedBy($request->user()), 404);

        $data = $request->validate([
            'master_reply' => 'required|string|min:2|max:1000',
        ], [
            'master_reply.required' => __('Напишите ответ.'),
        ]);

        $first = $review->master_reply === null;

        $review->update([
            'master_reply' => trim($data['master_reply']),
            'replied_at' => now(),
        ]);

        if ($first) {
            UserNotification::deliver(
                $review->user_id,
                'order',
                UserNotification::text('Мастер ответил на ваш отзыв'),
                Str::limit($review->master_reply, 140),
                route('public.masters.show', $review->applicant_id).'#review-'.$review->id,
            );
        }

        return redirect()->to(route('public.masters.show', $review->applicant_id).'#review-'.$review->id)
            ->with('status', $first ? __('Ответ опубликован под отзывом.') : __('Ответ обновлён.'));
    }
}
