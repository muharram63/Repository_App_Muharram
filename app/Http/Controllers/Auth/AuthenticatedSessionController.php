<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login', [
            'activeVacancies' => \App\Models\Vacancy::where('status', 'active')->count(),
            'applicantsCount' => \App\Models\Applicant::count(),
            'companiesCount' => \App\Models\Employer::count(),
            'responsesCount' => \App\Models\VacancyResponse::count() + \App\Models\ResumeResponse::count(),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        // Адрес, ради которого гостя отправили на вход, забираем сразу и всегда:
        // сессия переживает вход, и оставленный в ней адрес чужого кабинета
        // уводил туда следующего вошедшего — тот получал 403 вместо кабинета.
        $intended = $this->intendedFor($user, $request->session()->pull('url.intended'));

        if ($user->role === 'employer'
            && ! \App\Models\Employer::where('user_id', $user->id)->exists()) {
            return redirect()->route('public.employers.create');
        }

        $home = match ($user->role) {
            'admin' => route('superadmin.dashboard', absolute: false),
            'employer' => route('employer.dashboard', absolute: false),
            'applicant' => route('applicant.dashboard', absolute: false),
            // на всякий случай, если роль не распознана
            default => abort(403, 'Неизвестная роль пользователя.'),
        };

        return redirect()->to($intended ?: $home);
    }

    /**
     * Сохранённый адрес годится, только если он открыт этой роли: кабинеты
     * чужих ролей отвечают 403, и вход заканчивался ошибкой вместо кабинета.
     */
    private function intendedFor(\App\Models\User $user, ?string $intended): ?string
    {
        if (blank($intended)) {
            return null;
        }

        // раздел кабинета определяется первым сегментом пути; публичные адреса
        // вроде /employers/create ничьи и остаются доступными любой роли
        $section = explode('/', trim((string) parse_url($intended, PHP_URL_PATH), '/'))[0];

        $owner = match ($section) {
            'superadmin' => 'admin',
            'employer' => 'employer',
            'applicant' => 'applicant',
            default => null,
        };

        return $owner === null || $owner === $user->role ? $intended : null;
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
