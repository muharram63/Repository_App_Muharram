<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Employer;
use App\Models\Vacancy;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class VacancyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user();
        $employer = $this->currentEmployer();
        $vacancies = $employer->vacancies()->withCount('responses')->latest()->get();
        return view('employer.pages.vacancies.index',compact('vacancies'),[
            'user' => $user,
            'employer' => $employer,

        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = auth()->user();
        $employer = $this->currentEmployer();
        $cities = City::all();
        return view('employer.pages.vacancies.create',compact('cities'),
           [ 'user' => $user,
            'employer' => $employer ]
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // анкету проверяем до валидации формы: без неё вакансию всё равно некуда привязать
        $employer = $this->currentEmployer();

        $validated = $request->validate([
           'title' => 'required|string|max:255',
           'description' => 'required|string|max:600',
           'salary_from' => 'required|numeric',
           'salary_to' => 'required|numeric',
           'currency' => 'required|string|max:255',
           'employment_type' => 'required|string|max:255',
            'work_schedule' => 'required|string|max:255',
            'experience_required' => 'required|string|max:255',
            'skill' => 'required|string|max:255',
            'status' => 'required|string|max:255',
            'city_id' => 'required|exists:cities,id',
              ]);
        $validated['employer_id'] = $employer->id;
        Vacancy::create($validated);
        return redirect()->route('employer.vacancies.index');
    }

    /**
     * Display the specified resource.
     */
        public function show(Vacancy $vacancy)
        {
            abort_if($vacancy->employer_id !== $this->currentEmployer()->id, 403);

            return view('employer.pages.vacancies.show',compact('vacancy' ));
        }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Vacancy $vacancy)
    {
        abort_if($vacancy->employer_id !== $this->currentEmployer()->id, 403);

        $user = auth()->user();
        $employer = $this->currentEmployer();
        $cities = City::all();
        $employers = Employer::all();
        return view('employer.pages.vacancies.edit',compact('vacancy','employers','cities'),
            [ 'user' => $user,
                'employer' => $employer ]
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Vacancy $vacancy)
    {
        abort_if($vacancy->employer_id !== $this->currentEmployer()->id, 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'salary_from' => 'required|numeric',
            'salary_to' => 'required|numeric',
            'currency' => 'required|string|max:255',
            'employment_type' => 'required|string|max:255',
            'work_schedule' => 'required|string|max:255',
            'experience_required' => 'required|string|max:255',
            'skill' => 'required|string|max:255',
            'status' => 'required|string|max:255',
            'city_id' => 'required|exists:cities,id',

        ]);
        $vacancy->update($validated);
        return redirect()->route('employer.vacancies.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Vacancy $vacancy)
    {
        abort_if($vacancy->employer_id !== $this->currentEmployer()->id, 403);

        $vacancy->delete();
        return redirect()->route('employer.vacancies.index');
    }

    /**
     * Анкета компании текущего пользователя.
     * Роль employer ещё не значит, что анкета заполнена, а без неё вакансию не к чему привязать.
     */
    private function currentEmployer(): Employer
    {
        $employer = auth()->user()?->employer;

        if (! $employer) {
            throw new HttpResponseException(
                redirect()->route('public.employers.create')
                    ->with('error', 'Сначала заполните анкету работодателя.')
            );
        }

        return $employer;
    }
}
