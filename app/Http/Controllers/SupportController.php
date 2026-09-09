<?php

namespace App\Http\Controllers;

use App\Models\AdminComment;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    /**
     * Мои обращения к администрации.
     */
    public function index(Request $request)
    {
        $user = $this->cabinetUser();

        $comments = AdminComment::where('user_id', $user->id)->latest('id')->get();

        // открыл страницу — ответы считаются прочитанными
        $unread = $comments->filter(fn (AdminComment $c) => $c->isUnread())->pluck('id');

        if ($unread->isNotEmpty()) {
            AdminComment::whereIn('id', $unread)->update(['seen_at' => now()]);
        }

        return view($user->employer ? 'employer.pages.support.index' : 'applicant.pages.support.index', [
            'user' => $user,
            'comments' => $comments,
            'answered' => $comments->filter(fn (AdminComment $c) => $c->isAnswered())->count(),
            'waiting' => $comments->filter(fn (AdminComment $c) => $c->isWaiting())->count(),
            'closed' => $comments->filter(fn (AdminComment $c) => ! $c->isAnswered() && $c->status === 'closed')->count(),
            'freshAnswers' => $unread->count(),
        ]);
    }

    /**
     * Отправить обращение администратору.
     */
    public function store(Request $request)
    {
        $user = $this->cabinetUser();

        $validated = $request->validate([
            'topic' => 'required|in:'.implode(',', array_keys(AdminComment::TOPICS)),
            'body' => 'required|string|min:10|max:2000',
        ]);

        // тот же приём от спама, что и у жалоб
        // withTrashed: отозванные обращения тоже занимают место в суточном лимите,
        // иначе его обходят отправкой и отзывом по кругу
        $lastDay = AdminComment::withTrashed()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($lastDay >= AdminComment::DAILY_LIMIT) {
            return back()->with('error', 'Вы отправили слишком много обращений за сутки. Попробуйте завтра.');
        }

        AdminComment::create($validated + ['user_id' => $user->id]);

        return back()->with('status', 'Обращение отправлено. Администратор ответит в этом же разделе.');
    }

    /**
     * Отозвать обращение, пока на него не ответили.
     */
    public function destroy(Request $request, AdminComment $comment)
    {
        abort_if($comment->user_id !== $request->user()->id, 403);

        if (! $comment->canBeWithdrawn()) {
            return back()->with('error', 'Обращение уже в работе — отозвать его нельзя.');
        }

        $comment->delete();

        return back()->with('status', 'Обращение отозвано.');
    }

    /**
     * Раздел доступен владельцам кабинета: соискателям и работодателям.
     */
    private function cabinetUser()
    {
        $user = auth()->user();

        abort_if(! $user || (! $user->employer && ! $user->applicant), 403);

        return $user;
    }
}
