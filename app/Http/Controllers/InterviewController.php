<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\Interview;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InterviewController extends Controller
{
    /**
     * Список собеседований текущего пользователя.
     */
    public function index()
    {
        $user = auth()->user();
        $employer = $user->employer;
        $applicant = $user->applicant;

        $query = Interview::with('employer.user', 'applicant.user', 'vacancy')
            ->orderBy('scheduled_at');

        if ($employer) {
            $query->where('employer_id', $employer->id);
        } elseif ($applicant) {
            $query->where('applicant_id', $applicant->id);
        } else {
            $query->whereRaw('1 = 0');
        }

        $interviews = $query->get();

        $upcoming = $interviews->filter(fn (Interview $i) => ! $i->isPast()
            && ! in_array($i->status, ['declined', 'canceled'], true))->values();

        $archive = $interviews->filter(fn (Interview $i) => $i->isPast()
            || in_array($i->status, ['declined', 'canceled'], true))
            ->sortByDesc('scheduled_at')->values();

        return view('public.pages.interviews.index', [
            'user' => $user,
            'upcoming' => $upcoming,
            'archive' => $archive,
            'stats' => $this->stats($interviews, $upcoming),
        ]);
    }

    /**
     * Цифры над списком: первый ряд — за всё время, второй — что происходит сейчас.
     */
    private function stats($interviews, $upcoming): array
    {
        // встреча считается проведённой по факту, а не по ручной отметке:
        // время прошло, её не отменяли и в комнату кто-то заходил
        $isHeld = fn (Interview $i) => $i->isPast()
            && ! in_array($i->status, ['declined', 'canceled'], true)
            && ($i->answered_at !== null || $i->status === 'finished');

        $held = $interviews->filter($isHeld);

        $canceledStatuses = ['canceled', 'declined'];

        return [
            'total' => $interviews->count(),
            'canceled' => $interviews->whereIn('status', $canceledStatuses)->count(),
            'held' => $held->count(),
            'upcoming' => $upcoming->count(),
            // отменённая встреча в «Ближайшие» не попадает, поэтому считаем
            // по всей выборке: отменено то, что должно было ещё состояться
            'upcomingCanceled' => $interviews
                ->filter(fn (Interview $i) => ! $i->isPast()
                    && in_array($i->status, $canceledStatuses, true))
                ->count(),
            // сегодняшние встречи, которые уже состоялись
            'heldToday' => $held->filter(fn (Interview $i) => $i->scheduled_at->isToday())->count(),
        ];
    }

    /**
     * Работодатель назначает собеседование соискателю.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        $employer = $user->employer;

        if ($user->role !== 'employer' || ! $employer) {
            return back()->with('error', 'Назначать собеседования могут только работодатели.');
        }

        $validated = $request->validate([
            'applicant_id' => 'required|exists:applicants,id',
            'vacancy_id' => 'nullable|exists:vacancies,id',
            'vacancy_response_id' => 'nullable|exists:vacancy_responses,id',
            'resume_response_id' => 'nullable|exists:resume_responses,id',
            'scheduled_at' => 'required|date|after:now',
            'duration_minutes' => 'required|integer|min:15|max:180',
            'note' => 'nullable|string|max:500',
        ]);

        $applicantId = (int) $validated['applicant_id'];

        // вакансия должна принадлежать этому работодателю
        if (! empty($validated['vacancy_id'])) {
            $ownsVacancy = $employer->vacancies()->whereKey($validated['vacancy_id'])->exists();
            abort_if(! $ownsVacancy, 403);
        }

        // отклик — на вакансию этой компании и от этого же соискателя
        if (! empty($validated['vacancy_response_id'])) {
            $ownsResponse = \App\Models\VacancyResponse::whereKey($validated['vacancy_response_id'])
                ->where('applicant_id', $applicantId)
                ->whereIn('vacancy_id', $employer->vacancies()->select('id'))
                ->exists();
            abort_if(! $ownsResponse, 403);
        }

        // приглашение — от этой компании и на резюме этого же соискателя
        if (! empty($validated['resume_response_id'])) {
            $ownsInvite = \App\Models\ResumeResponse::whereKey($validated['resume_response_id'])
                ->where('employer_id', $employer->id)
                ->whereIn('resume_id', \App\Models\Resume::where('applicant_id', $applicantId)->select('id'))
                ->exists();
            abort_if(! $ownsInvite, 403);
        }

        // назначать встречу можно только тому, с кем компания уже связана
        abort_if(! $employer->isRelatedToApplicant($applicantId), 403);

        $interview = Interview::create([
            'employer_id' => $employer->id,
            'applicant_id' => $validated['applicant_id'],
            'vacancy_id' => $validated['vacancy_id'] ?? null,
            'vacancy_response_id' => $validated['vacancy_response_id'] ?? null,
            'resume_response_id' => $validated['resume_response_id'] ?? null,
            'scheduled_at' => $validated['scheduled_at'],
            'duration_minutes' => $validated['duration_minutes'],
            'note' => $validated['note'] ?? null,
            'room' => 'workio-'.Str::lower(Str::random(24)),
        ]);

        UserNotification::deliver(
            $interview->applicant?->user_id,
            'interview',
            'Вас пригласили на собеседование',
            $employer->company_name.' · '.$interview->scheduled_at->format('d.m.Y H:i'),
            route('public.interviews.show', $interview),
        );

        return redirect()->route('public.interviews.show', $interview)
            ->with('status', 'Собеседование назначено. Соискатель увидит приглашение в разделе «Собеседования».');
    }

    /**
     * Карточка собеседования с видеокомнатой.
     */
    public function show(Interview $interview)
    {
        $this->authorizeParticipant($interview);

        $this->markAnswered($interview);

        $interview->load('employer.user', 'applicant.user', 'vacancy');

        return view('public.pages.interviews.show', [
            'interview' => $interview,
            'isEmployer' => auth()->user()->employer
                && auth()->user()->employer->id === $interview->employer_id,
        ]);
    }

    /**
     * Смена статуса: соискатель подтверждает или отклоняет,
     * работодатель отменяет или закрывает встречу.
     */
    public function updateStatus(Request $request, Interview $interview)
    {
        $this->authorizeParticipant($interview);

        $validated = $request->validate([
            'status' => 'required|in:confirmed,declined,canceled,finished',
        ]);

        $user = auth()->user();
        $isEmployer = $user->employer && $user->employer->id === $interview->employer_id;

        $allowed = $isEmployer ? ['canceled', 'finished'] : ['confirmed', 'declined'];

        if (! in_array($validated['status'], $allowed, true)) {
            return back()->with('error', 'Такое действие для вас недоступно.');
        }

        $interview->update(['status' => $validated['status']]);

        $labels = [
            'confirmed' => 'Вы подтвердили участие.',
            'declined' => 'Вы отклонили приглашение.',
            'canceled' => 'Собеседование отменено.',
            'finished' => 'Собеседование отмечено завершённым.',
        ];

        return back()->with('status', $labels[$validated['status']]);
    }

    /**
     * Комнату открыл не тот, кто звонил, — значит на звонок ответили.
     */
    private function markAnswered(Interview $interview): void
    {
        if ($interview->answered_at) {
            return;
        }

        // карточку открывают и заранее — до открытия комнаты входить некуда
        if (! $interview->isRoomOpen()) {
            return;
        }

        $invite = \App\Models\Message::where('interview_id', $interview->id)
            ->latest('id')
            ->first();

        // звонок из чата: инициатор — автор приглашения
        if ($invite) {
            if ($invite->user_id === auth()->id()) {
                return;
            }

            $interview->update(['answered_at' => now()]);

            return;
        }

        // назначенное собеседование: инициатор — работодатель, поэтому встреча
        // считается состоявшейся, только когда в комнату вошёл соискатель
        $applicant = auth()->user()->applicant;

        if (! $applicant || $applicant->id !== $interview->applicant_id) {
            return;
        }

        $interview->update(['answered_at' => now()]);
    }

    /**
     * В комнату и карточку пускаем только двух участников встречи.
     */
    private function authorizeParticipant(Interview $interview): void
    {
        $user = auth()->user();

        $isEmployer = $user->employer && $user->employer->id === $interview->employer_id;
        $isApplicant = $user->applicant && $user->applicant->id === $interview->applicant_id;

        abort_if(! $isEmployer && ! $isApplicant, 403);
    }
}
