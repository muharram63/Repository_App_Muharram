<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\Employer;
use App\Models\Industry;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Первое заполнение анкеты компании после регистрации.
 *
 * Правка анкеты живёт в ProfileController (employer.profile.update), удаление —
 * в админке, поэтому здесь только create и store.
 */
class EmployerController extends Controller
{
    public function create()
    {
        $categories = Category::all();
        $industries = Industry::all();
        $cities = City::all();
        return view('employer.pages.employers.create',compact('categories','industries','cities'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user= auth()->user();

        // анкету компании заводит только работодатель: иначе у соискателя появляется
        // строка в employers, и всё приложение начинает считать его работодателем
        if ($user->role !== 'employer') {
            return back()->with('error', __('Анкету компании может заполнить только работодатель.'));
        }

        $validated = $request->validate([
            'company_name' => 'string|required|max:255',
            'job' => 'string|required|max:255',
            'email_company' => 'string|required|max:255|email',
            'phone' => 'string|required|max:255',
            'category_id' => 'required|exists:categories,id',
            // как при правке профиля: колонка text, а предел 255 не давал
            // сохранить анкету, которую потом можно было бы отредактировать
            'description' => 'string|required|max:600',
            'city_id' => 'required|exists:cities,id',
            'industry_id' => 'required|exists:industries,id',
            'website_url' => 'string|required|max:255',
        ]);
        $this->storeAvatar($request, $user);

        // повторное сохранение анкеты не должно падать на уникальном user_id
        Employer::updateOrCreate(['user_id' => $user->id], $validated);

        return redirect()->route('employer.dashboard')
            ->with('status', __('Анкета компании сохранена.'));
    }

    /**
     * Логотип компании: кладём в storage/app/public/avatars и пишем путь в users.avatar.
     */
    private function storeAvatar(Request $request, User $user): void
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
}