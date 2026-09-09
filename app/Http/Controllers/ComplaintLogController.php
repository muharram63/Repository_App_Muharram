<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use Illuminate\Http\Request;

class ComplaintLogController extends Controller
{
    /**
     * Мои жалобы: что отправлял пользователь и чем это закончилось.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        abort_if(! $user || (! $user->employer && ! $user->applicant), 403);

        $all = Complaint::where('user_id', $user->id)->latest()->get();

        $counts = [
            'all' => $all->count(),
            'new' => $all->where('status', 'new')->count(),
            'in_review' => $all->where('status', 'in_review')->count(),
            'resolved' => $all->where('status', 'resolved')->count(),
            'rejected' => $all->where('status', 'rejected')->count(),
        ];

        $filter = in_array($request->query('status'), array_keys(Complaint::STATUSES), true)
            ? $request->query('status')
            : 'all';

        $visible = $filter === 'all' ? $all : $all->where('status', $filter);

        // открыли страницу — решения считаются прочитанными
        $unreadIds = $all->filter(fn (Complaint $c) => $c->isUnread())->pluck('id');

        if ($unreadIds->isNotEmpty()) {
            Complaint::whereIn('id', $unreadIds)->update(['seen_at' => now()]);
        }

        \App\Models\Complaint::preloadTargets($visible);

        // жалобы на мои объекты: статус по ним ставлю я, а не модератор
        $incoming = Complaint::againstUser($user)->latest()->get();
        Complaint::preloadTargets($incoming);

        // открыл страницу — уведомления о жалобах считаются прочитанными
        \App\Models\ModerationNotice::where('user_id', $user->id)
            ->where('action', 'complaint_received')
            ->whereNull('seen_at')
            ->update(['seen_at' => now()]);

        return view($user->employer ? 'employer.pages.complaints.index' : 'applicant.pages.complaints.index', [
            'user' => $user,
            'complaints' => $visible->values(),
            'counts' => $counts,
            'filter' => $filter,
            'freshDecisions' => $unreadIds->count(),
            'incoming' => $incoming,
            'incomingOpen' => $incoming->filter(fn (Complaint $c) => $c->isOpen())->count(),
        ]);
    }
}
