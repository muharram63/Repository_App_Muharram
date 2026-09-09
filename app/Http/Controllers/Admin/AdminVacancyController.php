<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vacancy;
use Illuminate\Http\Request;

class AdminVacancyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ['active', 'inactive', 'closed', 'in_archived'], true)
            ? $request->query('status')
            : null;
        $search = trim((string) $request->query('q', ''));

        // employer.user и city подгружаем сразу: без этого шаблон делал
        // по три запроса на каждую строку
        $vacancies = Vacancy::with('employer.user', 'city')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(function ($x) use ($search) {
                $x->where('title', 'like', '%'.$search.'%')
                    ->orWhere('skill', 'like', '%'.$search.'%')
                    ->orWhereHas('city', fn ($c) => $c->where('country', 'like', '%'.$search.'%')
                        ->orWhere('region', 'like', '%'.$search.'%'));
            }))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.pages.vacancy.index', [
            'vacancies' => $vacancies,
            'activeStatus' => $status ?? 'all',
            'search' => $search,
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
    public function show(Vacancy $vacancy)
    {
        return view('admin.pages.vacancy.show',compact('vacancy'));
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

}
