<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Industry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminIndustryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $industries = Industry::query()->when($request->filled('search') , function($query) use ($request){
            $query->where('name' , 'like' , '%' . $request->input('search') . '%');
    })->get();
        return view('admin.pages.industries.index', compact('industries'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categories = Category::all();
        return view('admin.pages.industries.create',compact('categories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
           'name' => 'required|string|unique:industries',
           'category_id' => 'required|exists:categories,id',
           // поле ни на что не влияет и внешним ключом не является — не требуем его
           'parent_id' => 'nullable|integer|min:0|max:99999',
           'description' => 'required|string|max:255',
        ]);
        Industry::create($validated);
        return redirect()->route('superadmin.industries.index');
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
    public function edit(Industry $industry)
    {
        $categories = Category::all();
        return view('admin.pages.industries.edit', compact('industry','categories'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Industry $industry)
    {
        $validated = $request->validate([
            // саму запись из проверки исключаем: иначе сохранение без смены
            // названия отклонялось как «такое название уже занято»
            'name' => ['required', 'string', Rule::unique('industries')->ignore($industry)],
            'category_id' => 'required|exists:categories,id',
            'parent_id' => 'nullable|integer|min:0|max:99999',
            'description' => 'required|string|max:255',
        ]);
        $industry->update($validated);
        return redirect()->route('superadmin.industries.index');

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Industry $industry)
    {
        $employers = \App\Models\Employer::where('industry_id', $industry->id)->count();

        if ($employers) {
            return back()->with('error', "Индустрия используется у {$employers} компаний. Сначала перенесите их в другую индустрию.");
        }

        $industry->delete();
        return redirect()->route('superadmin.industries.index');
    }
}
