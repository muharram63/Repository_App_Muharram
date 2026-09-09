<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use Illuminate\Http\Request;

class ApplicantController extends Controller
{
    /**
     * Форма анкеты соискателя.
     */
    public function create()
    {
        return view('applicant.pages.applicants.create');
    }

    /**
     * Сохранение анкеты текущего пользователя.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'applicant') {
            return back()->with('error', 'Анкету соискателя может заполнить только соискатель.');
        }

        $validated = $this->validateApplicant($request);
        $validated['user_id'] = $user->id;

        $this->storeAvatar($request, $user);

        Applicant::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        return redirect()->route('applicant.dashboard')
            ->with('status', 'Анкета сохранена.');
    }

    /**
     * Обновление своей анкеты.
     */
    public function update(Request $request, Applicant $applicant)
    {
        // анкету правит только её владелец: админу доступны просмотр и удаление
        abort_if($applicant->user_id !== auth()->id(), 403);

        $applicant->update($this->validateApplicant($request));

        return redirect()->route('applicant.dashboard')
            ->with('status', 'Анкета обновлена.');
    }

    /**
     * Удаление анкеты — владельцем или админом.
     * Резюме и отклики уходят каскадом на уровне БД.
     */
    public function destroy(Applicant $applicant)
    {
        $this->authorizeOwner($applicant);

        $applicant->delete();

        return auth()->user()->role === 'admin'
            ? redirect()->route('superadmin.applicants')->with('status', 'Анкета удалена.')
            : redirect()->route('public.home')->with('status', 'Анкета удалена.');
    }

    /**
     * Удалять анкету может её владелец или админ.
     */
    private function authorizeOwner(Applicant $applicant): void
    {
        $user = auth()->user();

        abort_if(! $user || ($applicant->user_id !== $user->id && $user->role !== 'admin'), 403);
    }

    /**
     * Фото профиля: кладём в storage/app/public/avatars и пишем путь в users.avatar.
     */
    private function storeAvatar(Request $request, \App\Models\User $user): void
    {
        $request->validate([
            'avatar' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        if (! $request->hasFile('avatar')) {
            return;
        }

        $newPath = 'storage/'.$request->file('avatar')->store('avatars', 'public');
        $oldPath = $user->avatar;

        $user->update(['avatar' => $newPath]);

        // старый файл убираем только после успешной записи
        if ($oldPath && $oldPath !== $newPath && \Illuminate\Support\Str::startsWith($oldPath, 'storage/')) {
            \Illuminate\Support\Facades\Storage::disk('public')
                ->delete(\Illuminate\Support\Str::after($oldPath, 'storage/'));
        }
    }

    private function validateApplicant(Request $request): array
    {
        return $request->validate([
            'birth' => 'required|date|before:today',
            'gender' => 'required|in:male,female',
            'phone' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'education' => 'required|string|max:255',
            'about_me' => 'required|string|max:1000',
        ]);
    }
}
