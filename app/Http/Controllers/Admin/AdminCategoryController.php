<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class AdminCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $categories = Category::query()->when($request->filled('search'), function ($query) use ($request) {
$query->where('name' , 'like' , '%' . $request->input('search') . '%');
        })->get();
        return view('admin.pages.categories.index' , compact('categories'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.pages.categories.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
        ]);
            Category::create($validatedData);
            return redirect()->route('superadmin.categories.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Category $category)
    {
        return view('admin.pages.categories.edit' , compact('category'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
        ]) ;
        $category->update($validatedData);
        return redirect()->route('superadmin.categories.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        // индустрии удалятся каскадом, а вот компании держат категорию жёстко
        $employers = \App\Models\Employer::where('category_id', $category->id)->count();
        $industries = \App\Models\Industry::where('category_id', $category->id)->count();

        if ($employers || $industries) {
            return back()->with('error', "Категория используется: компаний — {$employers}, индустрий — {$industries}. Сначала перенесите их в другую категорию.");
        }

        $category->delete();
        return redirect()->route('superadmin.categories.index');
    }
}
