<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function profileUpdate(Request $request)
    {
        $user = auth()->user();
        $applicant = $user->applicant;

        if (! $applicant) {
            return redirect()->route('public.applicants.create')
                ->with('error', 'Сначала заполните анкету соискателя.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female',
            'phone' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'education' => 'required|string|max:255',
            'about_me' => 'nullable|string|max:600',
            'avatar' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        $userData = ['name' => $validated['name']];

        if ($request->hasFile('avatar')) {
            // старый файл удаляем, только если его загружали через эту же форму
            // сначала сохраняем новый файл, старый удаляем только после этого
            $newPath = 'storage/'.$request->file('avatar')->store('avatars', 'public');

            if ($user->avatar && $user->avatar !== $newPath && Str::startsWith($user->avatar, 'storage/')) {
                Storage::disk('public')->delete(Str::after($user->avatar, 'storage/'));
            }

            $userData['avatar'] = $newPath;
        }

        $user->update($userData);

        $applicant->update([
            'birth' => $validated['birth'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'city' => $validated['city'] ?? null,
            'address' => $validated['address'] ?? null,
            'education' => $validated['education'],
            'about_me' => $validated['about_me'] ?? null,
        ]);

        return redirect()->route('applicant.dashboard')
            ->with('status', 'Профиль обновлён.');
    }

    public function profile()
    {
        $user = auth()->user();
        $applicant = $user->applicant;

        if (! $applicant) {
            return redirect()->route('public.applicants.create')
                ->with('error', 'Сначала заполните анкету соискателя.');
        }

        $resumes = $applicant->resume()->withCount('responses')->get();

        return view('applicant.pages.dashboard', [
            'user' => $user,
            'applicant' => $applicant,
            'profileViews' => (int) $resumes->sum('views'),
            'resumesCount' => $resumes->count(),
            // отправлено: отклики соискателя на вакансии
            'responsesCount' => $applicant->vacancyResponses()->count(),
            'responsesPending' => $applicant->vacancyResponses()->where('status', 'new')->count(),
            // пришло: приглашения работодателей по резюме соискателя
            'invitesCount' => (int) $resumes->sum('responses_count'),
            'invitesPending' => \App\Models\ResumeResponse::whereIn('resume_id', $resumes->pluck('id'))
                ->where('status', 'new')->count(),
            'companyResponses' => \App\Models\ResumeResponse::with('employer.user', 'resume')
                ->whereIn('resume_id', $resumes->pluck('id'))
                ->latest()
                ->take(5)
                ->get(),
        ]);
    }
}
