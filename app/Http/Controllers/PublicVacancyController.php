<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Vacancy;
use Illuminate\Http\Request;

class PublicVacancyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $cities = City::all();

        $query = trim((string) $request->input('q'));
        $city = trim((string) $request->input('city'));

        $vacancies = Vacancy::with('employer.user', 'city')
            ->withCount('responses')
            // в каталоге только опубликованные: снятые модератором и закрытые сюда не попадают
            ->where('status', 'active')
            // и только от компаний с открытым аккаунтом
            ->whereHas('employer.user', fn ($u) => $u->activeAccount())
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($inner) use ($query) {
                    $inner->where('title', 'like', "%{$query}%")
                        ->orWhere('skill', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%");
                });
            })
            ->when($city !== '', function ($builder) use ($city) {
                $builder->whereHas('city', function ($inner) use ($city) {
                    $inner->where('country', 'like', "%{$city}%")
                        ->orWhere('region', 'like', "%{$city}%");
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('public.pages.vacancies.index', compact('vacancies', 'cities', 'query', 'city'));
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
    public function show(Request $request, Vacancy $vacancy)
    {
        // снятую с публикации вакансию и вакансию закрытого аккаунта
        // видят только её владелец и администратор
        $ownerBlocked = (bool) $vacancy->employer?->user?->isBlocked();

        abort_if(($vacancy->status !== 'active' || $ownerBlocked) && ! $this->isPrivileged($vacancy), 404);

        $this->countView($request, $vacancy);

        $vacancy->load('employer.user', 'city', 'responses.applicant.user')->loadCount('responses');

        return view('public.pages.vacancies.show',compact('vacancy' ));

    }

    /**
     * Владелец вакансии или администратор.
     */
    private function isPrivileged(Vacancy $vacancy): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->role === 'admin'
            || ($user->employer && $user->employer->id === $vacancy->employer_id);
    }

    /**
     * Один просмотр на сессию и не считаем автора вакансии.
     */
    private function countView(Request $request, Vacancy $vacancy): void
    {
        $user = $request->user();

        if ($user && $user->employer && $user->employer->id === $vacancy->employer_id) {
            return;
        }

        $seen = $request->session()->get('viewed_vacancies', []);

        if (in_array($vacancy->id, $seen, true)) {
            return;
        }

        $vacancy->increment('views');
        $seen[] = $vacancy->id;
        $request->session()->put('viewed_vacancies', $seen);
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
