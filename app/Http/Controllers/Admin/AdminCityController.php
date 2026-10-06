<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Services\Cities\CityDirectory;
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
        })->orderBy('region')->get();

        $directory = app(CityDirectory::class);

        return view('admin.pages.cities.index', [
            'cities' => $cities,
            // возможные дубли: тот же город, записанный иначе
            'duplicates' => $directory->duplicates(),
            'usage' => $cities->mapWithKeys(fn (City $city) => [$city->id => $directory->usage($city)]),
            'allCities' => City::orderBy('region')->get(),
        ]);
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
            'region' => ['required', 'string', 'max:255', $this->notDuplicate()],
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
            'region' => ['required', 'string', 'max:255', $this->notDuplicate($city->id)],
        ]);
        $city->update($validated);
        return redirect()->route('superadmin.cities.index');
    }

    /**
     * Объединить дубль с правильной записью: компании, вакансии и заявки
     * переходят на выбранный город, дубль удаляется.
     */
    public function merge(Request $request, City $city, CityDirectory $directory)
    {
        $data = $request->validate([
            'into' => 'required|integer|exists:cities,id|different:city_id',
        ], [
            'into.required' => 'Выберите, с каким городом объединить.',
        ]);

        $target = City::findOrFail($data['into']);

        if ($target->is($city)) {
            return back()->with('error', 'Нельзя объединить город с самим собой.');
        }

        $moved = $directory->merge($city, $target);

        return redirect()->route('superadmin.cities.index')->with('status', sprintf(
            '«%s» объединён с «%s»: перенесено компаний — %d, вакансий — %d, заявок — %d.',
            $city->region, $target->region, $moved['employers'], $moved['vacancies'], $moved['urgent_orders'],
        ));
    }

    /**
     * Правило: такого города ещё нет — в том числе в другом написании
     * («Кулоб» и «Хатлонская область, город Куляб»).
     */
    private function notDuplicate(?int $exceptId = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($exceptId) {
            $existing = app(CityDirectory::class)->existing((string) $value, $exceptId);

            if ($existing) {
                $fail("Такой город уже есть: «{$existing->region}». Используйте его, а не создавайте второй.");
            }
        };
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
