<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Ящик уведомлений кабинета.
     */
    public function index(Request $request)
    {
        $user = $this->cabinetUser();

        $filter = in_array($request->query('filter'), ['unread'], true) ? 'unread' : 'all';

        // показываем последние 200, но считаем по всей таблице — иначе счётчик
        // на странице расходился бы со счётчиком в колокольчике
        $visible = $user->notificationsInbox()
            ->when($filter === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->limit(200)
            ->get();

        return view($user->employer ? 'employer.pages.notifications.index' : 'applicant.pages.notifications.index', [
            'user' => $user,
            'notifications' => $visible,
            'unread' => $user->unreadNotificationsCount(),
            'total' => $user->notificationsInbox()->count(),
            'filter' => $filter,
        ]);
    }

    /**
     * Открыть уведомление: помечаем прочитанным и уводим к объекту.
     */
    public function open(Request $request, UserNotification $notification)
    {
        abort_if($notification->user_id !== $request->user()->id, 403);

        $notification->update(['read_at' => now()]);

        return redirect($notification->url ?: route('public.notifications.index'));
    }

    /**
     * Отметить всё прочитанным.
     */
    public function readAll(Request $request)
    {
        UserNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('status', 'Уведомления отмечены прочитанными.');
    }

    /**
     * Счётчик для колокольчика — опрашивается со всех страниц кабинета.
     */
    public function unreadCount(Request $request)
    {
        return response()->json([
            'unread' => $request->user()->unreadNotificationsCount(),
        ]);
    }

    private function cabinetUser()
    {
        $user = auth()->user();

        abort_if(! $user || (! $user->employer && ! $user->applicant), 403);

        return $user;
    }
}
