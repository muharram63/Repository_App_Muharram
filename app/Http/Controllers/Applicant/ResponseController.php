<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\ResumeResponse;

class ResponseController extends Controller
{
    /**
     * Компании, откликнувшиеся на резюме соискателя.
     */
    public function index()
    {
        $user = auth()->user();
        $applicant = $user->applicant;

        if (! $applicant) {
            return redirect()->route('public.applicants.create')
                ->with('error', 'Сначала заполните анкету соискателя.');
        }

        $responses = ResumeResponse::with('employer.user', 'employer.city', 'employer.industry', 'resume')
            ->whereIn('resume_id', $applicant->resume()->pluck('id'))
            ->latest()
            ->get();

        return view('applicant.pages.responses.index', [
            'user' => $user,
            'applicant' => $applicant,
            'responses' => $responses,
        ]);
    }
}
