<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        $employersCount = User::where('role', 'employer')->count();
        $applicantsCount = User::where('role', 'applicant')->count();
        $totalUser = $employersCount + $applicantsCount;

        return view('auth.register', [
            'totalUser' => $totalUser,
            'employersCount' => $employersCount,
            'applicantsCount' => $applicantsCount,
            'activeVacancies' => \App\Models\Vacancy::where('status', 'active')->count(),
            'resumesCount' => \App\Models\Resume::count(),
            'latestUsers' => User::whereIn('role', ['applicant', 'employer'])
                ->latest()
                ->take(3)
                ->get(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            // роль выбирает пользователь, но админа так получить нельзя
            'role' => ['required', 'in:applicant,employer'],
        ]);

        $user = new User([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // роль вне массового заполнения — назначаем её явно
        $user->role = $request->role;
        $user->save();

        event(new Registered($user));

        Auth::login($user);

        if ($user->role == 'employer') {
            return redirect()->route('public.employers.create');
        }
        if ($user->role == 'applicant') {
            return redirect()->route('public.applicants.create');
        }
        if($user->role == 'admin'){
            return redirect()->route('superadmin.dashboard');
        }
        return redirect()->route('public.home');



    }


}
