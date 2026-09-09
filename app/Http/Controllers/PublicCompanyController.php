<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Company;
use App\Models\Employer;
use App\Models\Industry;
use Illuminate\Http\Request;

class PublicCompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = trim((string) $request->input('q'));
        $categoryId = $request->input('category');
        $industryId = $request->input('industry');

        // поиск и фильтры на сервере: постранично клиентский перебор
        // карточек видел бы только текущую страницу
        $companies = Employer::with('user', 'city', 'category', 'industry')
            ->withCount('vacancies')
            ->whereHas('user', fn ($u) => $u->activeAccount())
            ->whereDoesntHave('user.settings', fn ($s) => $s->where('hide_company', true))
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($industryId, fn ($q) => $q->where('industry_id', $industryId))
            ->when($query !== '', fn ($q) => $q->where(function ($x) use ($query) {
                $x->where('company_name', 'like', "%{$query}%")
                    ->orWhere('email_company', 'like', "%{$query}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%"))
                    ->orWhereHas('city', fn ($c) => $c->where('country', 'like', "%{$query}%")
                        ->orWhere('region', 'like', "%{$query}%"));
            }))
            ->orderBy('company_name')
            ->paginate(24)
            ->withQueryString();

        return view('public.pages.companies.index', [
            'companies' => $companies,
            'categoriesList' => Category::orderBy('name')->get(),
            'industriesList' => Industry::orderBy('name')->get(),
            'query' => $query,
            'categoryId' => $categoryId,
            'industryId' => $industryId,
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
    public function show(Employer $company)
    {
        $owner = $company->user;
        $user = auth()->user();
        $privileged = $user && ($user->role === 'admin' || ($owner && $user->id === $owner->id));

        // скрытую компанию и компанию закрытого аккаунта видят
        // только владелец и администратор
        abort_if(
            ! $privileged && $owner && ($owner->setting('hide_company') || $owner->isBlocked()),
            404
        );

        return view('public.pages.companies.show', compact('company'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
