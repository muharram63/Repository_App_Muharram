<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Resume;
use Illuminate\Http\Request;

class AdminResumeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $exp = in_array($request->query('exp'), ['noexp', 'junior', 'middle', 'senior'], true)
            ? $request->query('exp')
            : null;
        $search = trim((string) $request->query('q', ''));

        $ranges = [
            'noexp' => [0, 0],
            'junior' => [1, 2],
            'middle' => [3, 5],
            'senior' => [6, 99],
        ];

        $resumes = Resume::with('applicant.user')
            ->when($exp, fn ($q) => $q->whereBetween('experience_years', $ranges[$exp]))
            ->when($search !== '', fn ($q) => $q->where(function ($x) use ($search) {
                $x->where('profession', 'like', '%'.$search.'%')
                    ->orWhere('desired_position', 'like', '%'.$search.'%')
                    ->orWhere('skills', 'like', '%'.$search.'%');
            }))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.pages.resumes.index', [
            'resumes' => $resumes,
            'activeExp' => $exp ?? 'all',
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
    public function show(Resume $resume)
    {
        return view('admin.pages.resumes.show',compact('resume'));
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
