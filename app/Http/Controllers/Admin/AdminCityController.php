<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\Request;

class AdminCityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $cities = City::query()->when($request->filled('search') , function ($query) use ($request){
           $search = $request->input('search');
           $query->where(function ($query) use ($search) {
               $query->where('country', 'like', '%' . $search . '%')->orWhere('region', 'like', '%' . $search . '%');
           });
        })->get();
        return view('admin.pages.cities.index' , compact('cities'));
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.pages.cities.create');

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
        'country' => 'required|string|max:255',
        'region' => 'required|string|max:255',
        ]);
        City::create($validated);
        return redirect()->route('superadmin.cities.index');

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(City $city)
    {
        return view('admin.pages.cities.edit', compact('city'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, City $city)
    {
        $validated = $request->validate([
            'country' => 'required|string|max:255',
            'region' => 'required|string|max:255',
        ]);
        $city->update($validated);
        return redirect()->route('superadmin.cities.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(City $city)
    {
        // внешние ключи на города каскада не имеют: удаление занятого справочника
        // валит запрос с ошибкой БД вместо понятного сообщения
        $vacancies = $city->vacancies()->count();
        $employers = \App\Models\Employer::where('city_id', $city->id)->count();

        if ($vacancies || $employers) {
            return back()->with('error', "Город используется: вакансий — {$vacancies}, компаний — {$employers}. Сначала перенесите их в другой город.");
        }

        $city->delete();
        return redirect()->route('superadmin.cities.index');
    }
}
