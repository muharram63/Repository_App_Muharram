<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

/**
 * Вход и регистрация через Google.
 *
 * Человека узнаём сначала по номеру аккаунта Google, затем по почте — но
 * только по почте, которую Google подтвердил: иначе чужой аккаунт Google с
 * тем же адресом открыл бы чужой кабинет.
 *
 * Новому пользователю нужна роль — от неё зависит кабинет. На странице
 * регистрации её уже выбрали в списке, и кнопка передаёт её сюда; со страницы
 * входа роли нет — тогда после Google спрашиваем её отдельной страницей.
 */
class GoogleAuthController extends Controller
{
    /** Где ждут данные Google, пока человек выбирает роль. */
    private const PENDING = 'google_pending';

    private const ROLES = ['applicant', 'employer'];

    public function redirect(Request $request): RedirectResponse
    {
        if (! self::configured()) {
            return redirect()->route('login')->withErrors([
                'email' => __('Вход через Google пока не настроен. Войдите по почте и паролю.'),
            ]);
        }

        // роль со страницы регистрации — пригодится, если аккаунта ещё нет
        $role = $request->query('role');
        in_array($role, self::ROLES, true)
            ? $request->session()->put('google_role', $role)
            : $request->session()->forget('google_role');

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse|View
    {
        if (! self::configured()) {
            return redirect()->route('login');
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            // человек нажал «Отмена» у Google, истекла сессия или подменён state
            Log::info('Вход через Google не удался', ['error' => $e->getMessage()]);

            return redirect()->route('login')->withErrors([
                'email' => __('Не удалось войти через Google. Попробуйте ещё раз.'),
            ]);
        }

        $email = mb_strtolower(trim((string) $google->getEmail()));
        $verified = (bool) ($google->user['email_verified'] ?? $google->user['verified_email'] ?? false);

        $user = User::where('google_id', $google->getId())->first()
            ?? ($email !== '' && $verified ? User::where('email', $email)->first() : null);

        if ($user) {
            if (blank($user->google_id)) {
                $user->forceFill(['google_id' => $google->getId()])->save();
            }

            return $this->login($request, $user);
        }

        if ($email === '' || ! $verified) {
            return redirect()->route('login')->withErrors([
                'email' => __('Google не подтвердил адрес почты этого аккаунта. Зарегистрируйтесь по почте и паролю.'),
            ]);
        }

        $pending = [
            'id' => $google->getId(),
            'name' => $google->getName() ?: Str::before($email, '@'),
            'email' => $email,
        ];

        $role = $request->session()->pull('google_role');

        if (in_array($role, self::ROLES, true)) {
            return $this->register($request, $pending, $role);
        }

        $request->session()->put(self::PENDING, $pending);

        return redirect()->route('auth.google.role');
    }

    /** Новый пользователь пришёл со страницы входа: кто он? */
    public function role(Request $request): View|RedirectResponse
    {
        $pending = $request->session()->get(self::PENDING);

        if (! $pending) {
            return redirect()->route('login');
        }

        return view('auth.google-role', ['pending' => $pending]);
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', 'in:'.implode(',', self::ROLES)]]);
        $pending = $request->session()->pull(self::PENDING);

        if (! $pending) {
            return redirect()->route('login');
        }

        // пока выбирали роль, аккаунт с этой почтой мог появиться — тогда входим в него
        if ($user = User::where('email', $pending['email'])->first()) {
            if (blank($user->google_id)) {
                $user->forceFill(['google_id' => $pending['id']])->save();
            }

            return $this->login($request, $user);
        }

        return $this->register($request, $pending, $data['role']);
    }

    /** Ключи Google заданы в .env — без них кнопка ведёт не в Google, а к понятной ошибке. */
    public static function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    private function login(Request $request, User $user): RedirectResponse
    {
        // как при входе по паролю: заблокированного не пускаем и объясняем причину
        if ($user->isBlocked()) {
            return redirect()->route('login')->withErrors(['email' => $user->blockedMessage()]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        if ($user->role === 'employer' && ! \App\Models\Employer::where('user_id', $user->id)->exists()) {
            return redirect()->route('public.employers.create');
        }

        return redirect()->route(match ($user->role) {
            'admin' => 'superadmin.dashboard',
            'employer' => 'employer.dashboard',
            default => 'applicant.dashboard',
        });
    }

    /** Новый аккаунт: дальше, как после обычной регистрации, — анкета. */
    private function register(Request $request, array $pending, string $role): RedirectResponse
    {
        $user = new User([
            'name' => $pending['name'],
            'email' => $pending['email'],
            // пароль не нужен для входа через Google; задать свой можно через «Забыли пароль?»
            'password' => Hash::make(Str::random(48)),
        ]);
        // роль вне массового заполнения — назначаем явно; почту Google уже подтвердил
        $user->role = $role;
        $user->forceFill(['google_id' => $pending['id'], 'email_verified_at' => now()]);
        $user->save();

        event(new Registered($user));

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route($role === 'employer' ? 'public.employers.create' : 'public.applicants.create');
    }
}
