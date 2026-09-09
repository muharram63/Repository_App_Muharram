<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use Illuminate\Http\Request;

class AdminApplicantController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $kind = in_array($request->query('kind'), ['male', 'female', 'withresume', 'noresume'], true)
            ? $request->query('kind')
            : null;
        $search = trim((string) $request->query('q', ''));

        $applicants = Applicant::with('user', 'resume')
            ->when($kind === 'male', fn ($q) => $q->where('gender', 'male'))
            ->when($kind === 'female', fn ($q) => $q->where('gender', 'female'))
            ->when($kind === 'withresume', fn ($q) => $q->has('resume'))
            ->when($kind === 'noresume', fn ($q) => $q->doesntHave('resume'))
            ->when($search !== '', fn ($q) => $q->where(function ($x) use ($search) {
                $x->where('city', 'like', '%'.$search.'%')
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%'));
            }))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.pages.applicants.index', [
            'applicants' => $applicants,
            'activeKind' => $kind ?? 'all',
            'search' => $search,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Applicant $applicant)
    {
        $applicant->load('user', 'resume');
        return view('admin.pages.applicants.show', compact('applicant'));
    }

}
