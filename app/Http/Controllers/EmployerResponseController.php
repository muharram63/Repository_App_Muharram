<?php

namespace App\Http\Controllers;

use App\Models\Resume;
use App\Models\ResumeResponse;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class EmployerResponseController extends Controller
{
    /**
     * Работодатель откликается (приглашает) на резюме.
     */
    public function store(Request $request, Resume $resume)
    {
        $user = auth()->user();

        if ($user->role !== 'employer') {
            return back()->with('error', 'Приглашать соискателей могут только работодатели.');
        }

        $employer = $user->employer;

        if (! $employer) {
            return redirect()->route('public.employers.create')
                ->with('error', 'Сначала заполните анкету работодателя.');
        }

        if ($resume->applicant && $resume->applicant->user_id === $user->id) {
            return back()->with('error', 'Нельзя откликнуться на собственное резюме.');
        }

        $validated = $request->validate([
            'message' => 'nullable|string|max:1000',
        ]);

        $response = ResumeResponse::firstOrCreate(
            ['employer_id' => $employer->id, 'resume_id' => $resume->id],
            ['message' => $validated['message'] ?? null]
        );

        if (! $response->wasRecentlyCreated) {
            return back()->with('status', 'Вы уже приглашали этого соискателя.');
        }

        // обе стороны: соискателю — новое, работодателю — отметка о своём действии
        UserNotification::deliver(
            $resume->applicant?->user_id,
            'response',
            'Приглашение от работодателя',
            $employer->company_name.' заинтересовалась вашим резюме «'.$resume->profession.'»',
            route('public.responses.index'),
            'mail_responses',
        );

        UserNotification::deliver(
            $user->id,
            'response',
            'Вы отправили приглашение',
            '«'.$resume->profession.'» · '.($resume->applicant?->user?->name ?? 'соискатель'),
            route('public.responses.index'),
            'mail_responses',
            read: true,
        );

        return back()->with('status', 'Приглашение отправлено.');
    }

    /**
     * Владелец резюме (соискатель) меняет статус приглашения.
     */
    public function updateStatus(Request $request, ResumeResponse $resumeResponse)
    {
        $user = auth()->user();

        abort_if(
            ! $resumeResponse->resume
            || ! $resumeResponse->resume->applicant
            || $resumeResponse->resume->applicant->user_id !== $user->id,
            403
        );

        $validated = $request->validate([
            'status' => 'required|in:viewed,accepted,rejected',
        ]);

        $resumeResponse->update(['status' => $validated['status']]);

        $labels = [
            'viewed' => 'Приглашение отмечено просмотренным.',
            'accepted' => 'Приглашение принято.',
            'rejected' => 'Приглашение отклонено.',
        ];

        UserNotification::deliver(
            $resumeResponse->employer?->user_id,
            'response_status',
            'Ответ на ваше приглашение',
            ($resumeResponse->resume?->profession ?? 'Резюме').': '.$labels[$validated['status']],
            route('public.responses.index'),
            'mail_responses',
        );

        return back()->with('status', $labels[$validated['status']]);
    }

    /**
     * Работодатель отзывает своё приглашение.
     */
    public function destroy(Resume $resume)
    {
        $employer = auth()->user()->employer;

        abort_if(! $employer, 403);

        ResumeResponse::where('employer_id', $employer->id)
            ->where('resume_id', $resume->id)
            ->delete();

        return back()->with('status', 'Приглашение отозвано.');
    }
}
