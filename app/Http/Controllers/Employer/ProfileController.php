<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function profileUpdate(Request $request)
    {

       $user = auth()->user();
       $employer = $user->employer;

       if (! $employer) {
           return redirect()->route('public.employers.create')
               ->with('error', 'Сначала заполните анкету работодателя.');
       }

       $validated = $request->validate([
           'name' => 'required|string|max:255',
           'company_name' => 'required|string|max:255',
           'job' => 'required|string|max:255',
           'email' => 'required|email|max:255',
           'phone' => 'required|string|max:255',
           'category_id' => 'required|exists:categories,id',
           'industry_id' => 'required|exists:industries,id',
           'city_id' => 'required|exists:cities,id',
           'website_url' => 'nullable|string|max:255',
           'description' => 'nullable|string|max:600',
           'avatar' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
       ]);

       $user->name=$validated['name'];

       if ($request->hasFile('avatar')) {
           // старый файл удаляем, только если его загружали через эту же форму
           // сначала сохраняем новый файл, старый удаляем только после этого
           $newPath = 'storage/'.$request->file('avatar')->store('avatars', 'public');

           if ($user->avatar && $user->avatar !== $newPath && Str::startsWith($user->avatar, 'storage/')) {
               Storage::disk('public')->delete(Str::after($user->avatar, 'storage/'));
           }

           $user->avatar = $newPath;
       }

       $user->save();
       $employer->email_company=$validated['email'];
       $employer->phone=$validated['phone'];
       $employer->company_name=$validated['company_name'];
       $employer->job=$validated['job'];
       $employer->category_id=$validated['category_id'];
       $employer->industry_id= $validated['industry_id'];
       $employer->website_url=$validated['website_url'] ?? $employer->website_url;
       $employer->description=$validated['description'] ?? $employer->description;
       $employer->city_id=$validated['city_id'];
       $employer->save();


       return redirect()->back();
    }
    public function profile(){
        $user = auth()->user();
        $employer = $user->employer;

        if (! $employer) {
            return redirect()->route('public.employers.create')
                ->with('error', 'Сначала заполните анкету работодателя.');
        }

        $categories = Category::all();
        $industries = \App\Models\Industry::all();
        $cities = \App\Models\City::all();
        $vacancies = $employer->vacancies()->withCount('responses')->get();
        $vacancyIds = $vacancies->pluck('id');

        return view('employer.pages.dashboard', [
            'employer' => $employer,
            'categories' => $categories,
            'industries' => $industries,
            'cities' => $cities,
            'profileViews' => (int) $vacancies->sum('views'),
            'vacanciesCount' => $vacancies->count(),
            // пришло: отклики кандидатов на вакансии компании
            'responsesCount' => (int) $vacancies->sum('responses_count'),
            'responsesPending' => \App\Models\VacancyResponse::whereIn('vacancy_id', $vacancyIds)
                ->where('status', 'new')->count(),
            // отправлено: приглашения, разосланные компанией по резюме
            'invitesCount' => \App\Models\ResumeResponse::where('employer_id', $employer->id)->count(),
            'invitesPending' => \App\Models\ResumeResponse::where('employer_id', $employer->id)
                ->where('status', 'new')->count(),
            'candidateResponses' => \App\Models\VacancyResponse::with('applicant.user', 'vacancy')
                ->whereIn('vacancy_id', $vacancyIds)
                ->latest()
                ->take(5)
                ->get(),
        ]);
    }
}
