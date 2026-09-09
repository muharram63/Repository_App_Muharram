<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\Employer;
use App\Models\Industry;
use App\Models\User;
use Illuminate\Http\Request;

class EmployerController extends Controller
{

    public function index()
    {
        $employers = Employer::all();
        $categories = Category::all();
        $industries = Industry::all();
        return view('employer.pages.employers.index' , compact('employers' , 'categories' , 'industries'));
    }
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
            return back()->with('error', 'Анкету компании может заполнить только работодатель.');
        }

        $validated = $request->validate([
            'company_name' => 'string|required|max:255',
            'job' => 'string|required|max:255',
            'email_company' => 'string|required|max:255|email',
            'phone' => 'string|required|max:255',
            'category_id' => 'required|exists:categories,id',
            'description' => 'string|required|max:255',
            'city_id' => 'required|exists:cities,id',
            'industry_id' => 'required|exists:industries,id',
            'website_url' => 'string|required|max:255',
        ]);
        $this->storeAvatar($request, $user);

        // повторное сохранение анкеты не должно падать на уникальном user_id
        Employer::updateOrCreate(['user_id' => $user->id], $validated);

        return redirect()->route('employer.dashboard')
            ->with('status', 'Анкета компании сохранена.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Employer $employer)
    {
        $categories = Category::all();
        $industries = Industry::all();
        $users = User::where('role', 'employer')->latest()->get();

        return view('employer.pages.employers.show', compact('employer', 'users', 'categories', 'industries'));

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Employer $employer)
    {

        // анкету компании правит только её владелец: админу доступны просмотр и удаление
        abort_if($employer->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'company_name' => 'string|required|max:255',
            'job' => 'string|required|max:255',
            'email_company' => 'string|required|max:255|email',
            'phone' => 'string|required|max:255',
            'category_id' => 'required|exists:categories,id',
            'description' => 'string|required|max:255',
            'city_id' => 'required|exists:cities,id',
            'industry_id' => 'required|exists:industries,id',
            'website_url' => 'string|required|max:255',
        ]);
        $employer->update($validated);

        return redirect()->route('employer.dashboard');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employer $employer)
    {
        // удалять анкету компании может владелец или админ
        abort_if($employer->user_id !== auth()->id() && auth()->user()?->role !== 'admin', 403);

        $employer->delete();
        return redirect()->route('employer.dashboard');
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