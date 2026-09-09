<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminComment;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class AdminCommentController extends Controller
{
    /**
     * Комментарии пользователей администрации.
     * Читаем ровно одну таблицу admin_comments: сообщения из чата,
     * тексты жалоб и что-либо ещё сюда не попадают.
     */
    public function index(Request $request)
    {
        $counts = AdminComment::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $query = AdminComment::with('user', 'admin');

        $status = $request->query('status');
        $topic = $request->query('topic');
        $search = trim((string) $request->query('q', ''));

        if (in_array($status, array_keys(AdminComment::STATUSES), true)) {
            $query->where('status', $status);
        }

        if (in_array($topic, array_keys(AdminComment::TOPICS), true)) {
            $query->where('topic', $topic);
        }

        // поиск по тексту обращения и по автору — на сервере, чтобы работал с постраничной выдачей
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('body', 'like', '%'.$search.'%')
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
            });
        }

        $comments = $query->latest('id')->paginate(20)->withQueryString();

        return view('admin.pages.comments.index', [
            'comments' => $comments,
            'newCount' => (int) ($counts['new'] ?? 0),
            'inReviewCount' => (int) ($counts['in_review'] ?? 0),
            'answeredCount' => (int) ($counts['answered'] ?? 0),
            'closedCount' => (int) ($counts['closed'] ?? 0),
            'filterStatus' => in_array($status, array_keys(AdminComment::STATUSES), true) ? $status : 'all',
            'filterTopic' => in_array($topic, array_keys(AdminComment::TOPICS), true) ? $topic : 'all',
            'search' => $search,
        ]);
    }

    /**
     * Ответ администратора. Текст пользователя при этом не меняется.
     */
    public function reply(Request $request, AdminComment $comment)
    {
        $validated = $request->validate([
            'reply' => 'required|string|min:2|max:2000',
        ]);

        $comment->update([
            'admin_id' => $request->user()->id,
            'admin_reply' => $validated['reply'],
            'answered_at' => now(),
            'status' => 'answered',
            // автор должен увидеть ответ как новый
            'seen_at' => null,
        ]);

        UserNotification::deliver(
            $comment->user_id,
            'admin_reply',
            'Ответ администратора',
            \Illuminate\Support\Str::limit($validated['reply'], 120),
            AdminComment::cabinetUrl($comment->user),
        );

        return back()->with('status', 'Ответ отправлен.');
    }

    /**
     * Состояние очереди: взял в работу, закрыл, вернул в новые.
     */
    public function updateStatus(Request $request, AdminComment $comment)
    {
        // «Отвечено» вручную не ставится: этот статус означает, что ответ
        // действительно написан, и выставляется методом reply(). Иначе у автора
        // плашка говорила «Отвечено», а счётчик «Ждут ответа» не менялся
        $validated = $request->validate([
            'status' => 'required|in:new,in_review,closed',
        ]);

        $comment->update(['status' => $validated['status']]);

        return back()->with('status', 'Статус обращения обновлён: '.$comment->statusLabel().'.');
    }

    public function destroy(AdminComment $comment)
    {
        $comment->delete();

        return back()->with('status', 'Обращение удалено.');
    }
}
