<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintAction;
use App\Models\Conversation;
use App\Models\ModerationNotice;
use App\Models\Resume;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vacancy;
use Illuminate\Http\Request;

class AdminComplaintController extends Controller
{
    public function index(Request $request)
    {
        // корзина: мягко удалённые жалобы можно посмотреть и вернуть
        $trashed = $request->boolean('trashed');

        $target = $request->query('target');
        $reason = $request->query('reason');
        $search = trim((string) $request->query('q', ''));
        $sort = $request->query('sort') === 'oldest' ? 'oldest' : 'newest';

        // «все жалобы на этот объект» — переход с бейджа повторов
        $objectType = in_array($request->query('object_type'), array_keys(Complaint::TARGETS), true)
            ? $request->query('object_type')
            : null;
        $objectId = $objectType ? (int) $request->query('object_id') : null;

        $query = Complaint::with('user', 'actions.admin')
            ->when($trashed, fn ($q) => $q->onlyTrashed())
            ->when(in_array($target, array_keys(Complaint::TARGETS), true), fn ($q) => $q->where('target_type', $target))
            ->when(in_array($reason, array_keys(Complaint::REASONS), true), fn ($q) => $q->where('reason', $reason))
            ->when($objectId, fn ($q) => $q->where('target_type', $objectType)->where('target_id', $objectId))
            ->when($search !== '', fn ($q) => $q->where(fn ($x) => $this->searchScope($x, $search)))
            ->reorder('created_at', $sort === 'oldest' ? 'asc' : 'desc');

        $complaints = $query->paginate(20)->withQueryString();
        Complaint::preloadTargets($complaints->getCollection());

        return view('admin.pages.complaints.index', [
            'complaints' => $complaints,
            'duplicates' => $this->duplicateCounts($complaints->getCollection()),
            'counts' => $this->queueCounts(),
            'filterTarget' => in_array($target, array_keys(Complaint::TARGETS), true) ? $target : 'all',
            'filterReason' => in_array($reason, array_keys(Complaint::REASONS), true) ? $reason : 'all',
            'search' => $search,
            'sort' => $sort,
            'trashed' => $trashed,
            'objectType' => $objectType,
            'objectId' => $objectId,
        ]);
    }

    /**
     * Поиск: по тексту жалобы, по автору и по названию объекта.
     * Объект полиморфный и связью Eloquent не описан, поэтому по каждому
     * типу идёт отдельный подзапрос.
     */
    private function searchScope($query, string $search)
    {
        $like = '%'.$search.'%';

        $query->where('message', 'like', $like)
            ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like))
            ->orWhere(fn ($x) => $x->where('target_type', 'vacancy')
                ->whereIn('target_id', Vacancy::where('title', 'like', $like)->select('id')))
            ->orWhere(fn ($x) => $x->where('target_type', 'resume')
                ->whereIn('target_id', Resume::where('profession', 'like', $like)->select('id')))
            ->orWhere(fn ($x) => $x->where('target_type', 'company')
                ->whereIn('target_id', \App\Models\Employer::where('company_name', 'like', $like)->select('id')))
            ->orWhere(fn ($x) => $x->where('target_type', 'user')
                ->whereIn('target_id', User::where('name', 'like', $like)->orWhere('email', 'like', $like)->select('id')));

        return $query;
    }

    /**
     * Сколько всего жалоб на каждый объект с текущей страницы —
     * одним запросом, чтобы показать бейдж «ещё N жалоб на этот объект».
     */
    private function duplicateCounts($complaints): array
    {
        if ($complaints->isEmpty()) {
            return [];
        }

        $rows = Complaint::selectRaw('target_type, target_id, count(*) as total')
            ->whereIn('target_type', $complaints->pluck('target_type')->unique()->all())
            ->whereIn('target_id', $complaints->pluck('target_id')->unique()->all())
            ->groupBy('target_type', 'target_id')
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $map[$row->target_type.'-'.$row->target_id] = (int) $row->total;
        }

        return $map;
    }

    /**
     * Цифры для шапки. Никакого вердикта: сколько обращений поступило
     * и что лежит в корзине.
     */
    private function queueCounts(): array
    {
        return [
            'total' => Complaint::count(),
            'today' => Complaint::whereDate('created_at', today())->count(),
            'week' => Complaint::where('created_at', '>=', now()->subWeek())->count(),
            'trashed' => Complaint::onlyTrashed()->count(),
        ];
    }

    /**
     * Меры по жалобе и их отмена.
     */
    public function act(Request $request, Complaint $complaint)
    {
        // администратору доступны только блокировка и разблокировка:
        // снятие вакансии и скрытие резюме — это правка чужого содержимого
        $validated = $request->validate([
            'action' => 'required|in:block_user,unblock_user',
        ]);

        $target = $complaint->target();

        if (! $target) {
            return back()->with('error', 'Объект жалобы уже удалён.');
        }

        $action = $validated['action'];

        // тот же список, по которому шаблон рисует кнопки: присланное напрямую
        // неподходящее действие сюда не пройдёт
        if (! in_array($action, $complaint->allowedActions(), true)) {
            return back()->with('error', 'Эта мера сейчас неприменима к объекту жалобы.');
        }

        $result = $this->switchUser($target, $action === 'block_user');

        if (! $result['ok']) {
            return back()->with('error', $result['message']);
        }

        // status — ответ владельца объекта, модератор его не трогает.
        // Своё состояние очереди он ведёт в review_state.
        // admin_comment хранит последнюю меру и не склеивается: полная
        // хронология и так лежит в complaint_actions
        $complaint->update([
            // автор жалобы должен увидеть новость о блокировке
            'seen_at' => null,
            'admin_comment' => $result['message'],
        ]);

        $this->log($complaint, $action, $result['message']);
        $this->notifyOwner($complaint, $action, $result);
        $this->notifyReporter($complaint, $result);

        return back()->with('status', $result['message']);
    }

    /**
     * В корзину. Запись остаётся в базе: она держит место в суточном лимите
     * автора и её можно вернуть.
     */
    public function destroy(Complaint $complaint)
    {
        $complaint->delete();

        return back()->with('status', 'Жалоба убрана в корзину.');
    }

    /**
     * Вернуть жалобу из корзины.
     */
    public function restore(int $complaint)
    {
        $found = Complaint::onlyTrashed()->findOrFail($complaint);
        $found->restore();

        return back()->with('status', 'Жалоба возвращена из корзины.');
    }

    /**
     * Блокировка и разблокировка аккаунта.
     */
    private function switchUser($target, bool $block): array
    {
        $user = $target instanceof User
            ? $target
            : ($target->user ?? $target->applicant?->user ?? $target->employer?->user ?? null);

        if (! $user) {
            return ['ok' => false, 'message' => 'Не удалось определить владельца объекта.'];
        }

        if ($block && $user->id === auth()->id()) {
            return ['ok' => false, 'message' => 'Нельзя заблокировать собственный аккаунт.'];
        }

        if ($block && $user->role === 'admin') {
            return ['ok' => false, 'message' => 'Администратора заблокировать нельзя.'];
        }

        $user->status = $block ? 'blocked' : 'active';
        $user->save();

        return [
            'ok' => true,
            'owner' => $user,
            'subject' => $user->name,
            'message' => $block
                ? 'Пользователь '.$user->name.' заблокирован модератором.'
                : 'Пользователь '.$user->name.' разблокирован модератором.',
        ];
    }

    /**
     * Автор жалобы узнаёт, что по ней приняли меры.
     * Вердикт «решена или нет» по-прежнему ставит владелец объекта.
     */
    private function notifyReporter(Complaint $complaint, array $result): void
    {
        UserNotification::deliver(
            $complaint->user_id,
            'moderation',
            'По вашей жалобе приняты меры',
            $result['message'] ?? null,
            Complaint::cabinetUrl($complaint->user),
        );
    }

    /**
     * Владелец объекта узнаёт о мере, но не о том, кто пожаловался.
     */
    private function notifyOwner(Complaint $complaint, string $action, array $result): void
    {
        $owner = $result['owner'] ?? null;

        if (! $owner) {
            return;
        }

        UserNotification::deliver(
            $owner->id,
            'moderation',
            ModerationNotice::ACTIONS[$action] ?? 'Решение модератора',
            $result['message'] ?? null,
            Complaint::cabinetUrl($owner),
        );

        ModerationNotice::create([
            'user_id' => $owner->id,
            'complaint_id' => $complaint->id,
            'action' => $action,
            'subject' => $result['subject'] ?? $complaint->targetTitle(),
            'reason' => $complaint->reasonLabel(),
            'comment' => $result['message'],
        ]);
    }

    /**
     * Запись в историю жалобы.
     */
    private function log(Complaint $complaint, string $action, ?string $comment): void
    {
        ComplaintAction::create([
            'complaint_id' => $complaint->id,
            'admin_id' => auth()->id(),
            'action' => $action,
            'comment' => $comment,
        ]);
    }
}
