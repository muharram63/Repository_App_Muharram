<?php

namespace App\Http\Controllers;

use App\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    /**
     * Настройки кабинета: аккаунт, безопасность, интерфейс,
     * уведомления, приватность и удаление аккаунта.
     */
    public function index()
    {
        $user = $this->cabinetUser();

        return view($user->employer ? 'employer.pages.settings.index' : 'applicant.pages.settings.index', [
            'user' => $user,
            'settings' => $user->settingsOrDefault(),
            'locales' => config('app.available_locales', ['ru']),
        ]);
    }

    /**
     * Имя, email и аватар.
     */
    public function account(Request $request)
    {
        $user = $this->cabinetUser();

        $validated = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'avatar' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
        ])->validateWithBag('account');

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        // сменили почту — подтверждение придётся пройти заново
        if ($validated['email'] !== $user->email) {
            $data['email_verified_at'] = null;
        }

        $oldAvatar = $user->avatar;

        if ($request->hasFile('avatar')) {
            // сначала сохраняем новый файл и только потом удаляем старый
            $data['avatar'] = 'storage/'.$request->file('avatar')->store('avatars', 'public');
        } elseif ($request->boolean('remove_avatar')) {
            $data['avatar'] = null;
        }

        $user->update($data);

        if (array_key_exists('avatar', $data) && $oldAvatar && $oldAvatar !== $user->avatar
            && Str::startsWith($oldAvatar, 'storage/')) {
            Storage::disk('public')->delete(Str::after($oldAvatar, 'storage/'));
        }

        return back()->with('status', 'Данные аккаунта сохранены.');
    }

    /**
     * Смена пароля.
     */
    public function password(Request $request)
    {
        $user = $this->cabinetUser();

        $validated = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ])->validateWithBag('password');

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(
                ['current_password' => 'Текущий пароль указан неверно.'],
                'password'
            );
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        // остальные сессии этого пользователя больше не действуют
        Auth::logoutOtherDevices($validated['password']);
        $request->session()->regenerate();

        return back()->with('status', 'Пароль изменён.');
    }

    /**
     * Выход на всех остальных устройствах.
     */
    public function logoutOthers(Request $request)
    {
        $user = $this->cabinetUser();

        $validated = Validator::make($request->all(), [
            'password' => 'required|string',
        ])->validateWithBag('devices');

        if (! Hash::check($validated['password'], $user->password)) {
            return back()->withErrors(['password' => 'Пароль указан неверно.'], 'devices');
        }

        Auth::logoutOtherDevices($validated['password']);
        $request->session()->regenerate();

        return back()->with('status', 'Вы вышли на всех остальных устройствах.');
    }

    /**
     * Язык и тема интерфейса.
     */
    public function interface(Request $request)
    {
        $user = $this->cabinetUser();

        $validated = Validator::make($request->all(), [
            'locale' => ['required', Rule::in(config('app.available_locales', ['ru']))],
            'theme' => 'required|in:light,dark,system',
        ])->validateWithBag('interface');

        $user->update([
            'locale' => $validated['locale'],
            'theme' => $validated['theme'],
        ]);

        $request->session()->put('locale', $validated['locale']);
        $request->session()->put('theme', $validated['theme']);

        return back()->with('status', 'Настройки интерфейса сохранены.');
    }

    /**
     * Уведомления.
     */
    public function notifications(Request $request)
    {
        $user = $this->cabinetUser();

        UserSetting::updateOrCreate(['user_id' => $user->id], [
            'call_sound' => $request->boolean('call_sound'),
            'call_notify' => $request->boolean('call_notify'),
            'mail_responses' => $request->boolean('mail_responses'),
            'mail_messages' => $request->boolean('mail_messages'),
        ]);

        return back()->with('status', 'Настройки уведомлений сохранены.');
    }

    /**
     * Приватность: что видно в публичной части.
     */
    public function privacy(Request $request)
    {
        $user = $this->cabinetUser();

        $data = $user->employer
            ? ['hide_company' => $request->boolean('hide_company')]
            : [
                'hide_profile' => $request->boolean('hide_profile'),
                'hide_contacts' => $request->boolean('hide_contacts'),
            ];

        UserSetting::updateOrCreate(['user_id' => $user->id], $data);

        return back()->with('status', 'Настройки приватности сохранены.');
    }

    /**
     * Удаление аккаунта вместе со всеми связанными данными.
     */
    public function destroy(Request $request)
    {
        $user = $this->cabinetUser();

        $validated = Validator::make($request->all(), [
            'password' => 'required|string',
        ])->validateWithBag('destroy');

        if (! Hash::check($validated['password'], $user->password)) {
            return back()->withErrors(['password' => 'Пароль указан неверно.'], 'destroy');
        }

        $avatar = $user->avatar;

        Auth::logout();
        $user->delete();

        if ($avatar && Str::startsWith($avatar, 'storage/')) {
            Storage::disk('public')->delete(Str::after($avatar, 'storage/'));
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('public.home')->with('status', 'Аккаунт удалён.');
    }

    /**
     * Настройки доступны только владельцу кабинета.
     */
    private function cabinetUser()
    {
        $user = auth()->user();

        abort_if(! $user || (! $user->employer && ! $user->applicant), 403);

        return $user;
    }
}
