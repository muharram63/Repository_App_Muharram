<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employer;
use Illuminate\Http\Request;

class AdminCompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ['active', 'inactive', 'blocked'], true)
            ? $request->query('status')
            : null;
        $search = trim((string) $request->query('q', ''));

        $companies = Employer::with('user', 'city', 'industry')
            ->when($status, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('status', $status)))
            ->when($search !== '', fn ($q) => $q->where(function ($x) use ($search) {
                $x->where('company_name', 'like', '%'.$search.'%')
                    ->orWhere('email_company', 'like', '%'.$search.'%')
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%'));
            }))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.pages.companies.index', [
            'companies' => $companies,
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
    public function show(Employer $company)
    {
        return view('admin.pages.companies.show', compact('company'));
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
