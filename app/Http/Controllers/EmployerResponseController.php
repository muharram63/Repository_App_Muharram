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
            return back()->with('error', __('Приглашать соискателей могут только работодатели.'));
        }

        $employer = $user->employer;

        if (! $employer) {
            return redirect()->route('public.employers.create')
                ->with('error', __('Сначала заполните анкету работодателя.'));
        }

        if ($resume->applicant && $resume->applicant->user_id === $user->id) {
            return back()->with('error', __('Нельзя откликнуться на собственное резюме.'));
        }

        // скрытое резюме и закрытый аккаунт публично не видны — по номеру из
        // адреса пригласить их тоже нельзя: соискатель прятался от поиска
        $owner = $resume->applicant?->user;
        if (! $owner || $owner->isBlocked() || $owner->setting('hide_profile')) {
            abort(404);
        }

        $validated = $request->validate([
            'message' => 'nullable|string|max:1000',
        ]);

        $response = ResumeResponse::firstOrCreate(
            ['employer_id' => $employer->id, 'resume_id' => $resume->id],
            ['message' => $validated['message'] ?? null]
        );

        if (! $response->wasRecentlyCreated) {
            return back()->with('status', __('Вы уже приглашали этого соискателя.'));
        }

        // обе стороны: соискателю — новое, работодателю — отметка о своём действии
        UserNotification::deliver(
            $resume->applicant?->user_id,
            'response',
            UserNotification::text('Приглашение от работодателя'),
            UserNotification::text(':company заинтересовалась вашим резюме «:profession»', [
                'company' => $employer->company_name,
                'profession' => $resume->profession,
            ]),
            route('public.responses.index'),
            'mail_responses',
        );

        UserNotification::deliver(
            $user->id,
            'response',
            UserNotification::text('Вы отправили приглашение'),
            UserNotification::text('«:title» · :company', [
                'title' => $resume->profession,
                'company' => $resume->applicant?->user?->name ?? UserNotification::text('соискатель'),
            ]),
            route('public.responses.index'),
            'mail_responses',
            read: true,
        );

        return back()->with('status', __('Приглашение отправлено.'));
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
            UserNotification::text('Ответ на ваше приглашение'),
            UserNotification::text(':title: :status', [
                'title' => $resumeResponse->resume?->profession ?? UserNotification::text('Резюме'),
                'status' => UserNotification::text($labels[$validated['status']]),
            ]),
            route('public.responses.index'),
            'mail_responses',
        );

        return back()->with('status', __($labels[$validated['status']]));
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

        return back()->with('status', __('Приглашение отозвано.'));
    }
}
