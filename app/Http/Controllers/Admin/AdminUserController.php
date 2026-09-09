<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $role = in_array($request->query('role'), ['applicant', 'employer', 'admin'], true)
            ? $request->query('role')
            : null;
        $status = in_array($request->query('status'), ['active', 'blocked'], true)
            ? $request->query('status')
            : null;
        $search = trim((string) $request->query('q', ''));

        // Список отдаём целиком, без постраничности. Фильтры и поиск
        // остаются серверными: так они охватывают всех пользователей,
        // а не только загруженные строки.
        $users = User::query()
            ->when($role, fn ($q) => $q->where('role', $role))
            ->when($status === 'active', fn ($q) => $q->activeAccount())
            ->when($status === 'blocked', fn ($q) => $q->whereIn('status', User::BLOCKED_STATUSES))
            ->when($search !== '', fn ($q) => $q->where(function ($x) use ($search) {
                $x->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            }))
            ->latest('id')
            ->get();

        $byRole = User::selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

        return view('admin.pages.users.index', [
            'users' => $users,
            'filterRole' => $role ?? 'all',
            'filterStatus' => $status ?? 'all',
            'search' => $search,
            'roleCounts' => [
                'all' => (int) $byRole->sum(),
                'applicant' => (int) ($byRole['applicant'] ?? 0),
                'employer' => (int) ($byRole['employer'] ?? 0),
                'admin' => (int) ($byRole['admin'] ?? 0),
            ],
            'blockedCount' => User::whereIn('status', User::BLOCKED_STATUSES)->count(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        // карточка только на просмотр: данные пользователя правит он сам
        $user->load('employer', 'applicant');

        return view('admin.pages.users.edit', [
            'user' => $user,
            'adminsCount' => User::where('role', 'admin')->count(),
        ]);
    }

    /**
     * Единственное, что админ меняет у чужого аккаунта, — статус:
     * имя, email, профиль и роль правит только сам владелец.
     */
    public function update(Request $request, User $user)
    {
        // осмысленных состояния два: доступ открыт или закрыт.
        // inactive и «under review» ничего внятного не означали:
        // первый молча закрывал вход, второй не делал ничего
        $validated = $request->validate([
            'status' => 'required|in:active,blocked',
        ]);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Собственный статус менять нельзя.');
        }

        if ($user->role === 'admin' && $validated['status'] !== 'active') {
            return back()->with('error', 'Администратора заблокировать нельзя.');
        }

        $user->status = $validated['status'];
        $user->save();

        return redirect()->route('superadmin.users')->with('status', 'Статус пользователя обновлён.');
    }

    /**
     * Админ снимает роль с самого себя. Чужие роли не трогает никто,
     * и последний администратор выйти из роли не может.
     */
    public function resign(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'role' => 'required|in:applicant,employer',
        ]);

        if (User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'Вы единственный администратор — снять с себя роль нельзя.');
        }

        $user->role = $validated['role'];
        $user->save();

        return redirect()->route('dashboard')
            ->with('status', 'Вы больше не администратор.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Нельзя удалить собственный аккаунт.');
        }

        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'Это последний администратор — удалять нельзя.');
        }

        $user->delete();

        return redirect()->route('superadmin.users')->with('status', 'Пользователь удалён.');
    }
}
