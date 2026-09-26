<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Models\Vacancy;
use App\Models\VacancyResponse;
use Illuminate\Http\Request;

class ApplicantResponseController extends Controller
{
    /**
     * Соискатель откликается на вакансию.
     */
    public function store(Request $request, Vacancy $vacancy)
    {
        $user = auth()->user();

        if ($user->role !== 'applicant') {
            return back()->with('error', 'Откликаться на вакансии могут только соискатели.');
        }

        $applicant = $user->applicant;

        if (! $applicant) {
            return redirect()->route('public.applicants.create')
                ->with('error', 'Сначала заполните анкету соискателя.');
        }

        if ($vacancy->status !== 'active') {
            return back()->with('error', 'Эта вакансия больше не принимает отклики.');
        }

        $validated = $request->validate([
            'message' => 'nullable|string|max:1000',
        ]);

        $response = VacancyResponse::firstOrCreate(
            ['applicant_id' => $applicant->id, 'vacancy_id' => $vacancy->id],
            ['message' => $validated['message'] ?? null]
        );

        if (! $response->wasRecentlyCreated) {
            return back()->with('status', 'Вы уже откликались на эту вакансию.');
        }

        // уведомление получают обе стороны: работодателю — новое,
        // соискателю — отметка о собственном действии, уже прочитанная
        UserNotification::deliver(
            $vacancy->employer?->user_id,
            'response',
            'Новый отклик на вакансию',
            $user->name.' откликнулся на «'.$vacancy->title.'»',
            route('public.responses.index'),
            'mail_responses',
        );

        UserNotification::deliver(
            $user->id,
            'response',
            'Вы откликнулись на вакансию',
            '«'.$vacancy->title.'» · '.($vacancy->employer?->company_name ?? 'компания'),
            route('public.responses.index'),
            'mail_responses',
            read: true,
        );

        /*
         * У вакансии включено ИИ-собеседование — ведём кандидата туда сразу.
         *
         * Оставлять его ждать работодателя было бы нечестно: по такой вакансии
         * ответ даёт ИИ, и чем раньше человек это узнает, тем лучше.
         * Уведомление всё равно отправляем — вкладку закроют, а вернуться нужно.
         */
        if ($this->aiInterviewAwaits($vacancy)) {
            UserNotification::deliver(
                $user->id,
                'interview',
                'По этой вакансии собеседование проводит ИИ',
                '«'.$vacancy->title.'» — нужно дать согласие и загрузить документы',
                route('applicant.ai.start', $vacancy),
                read: true,
            );

            return redirect()->route('applicant.ai.start', $vacancy)
                ->with('status', 'Отклик отправлен. По этой вакансии собеседование проводит ИИ.');
        }

        return back()->with('status', 'Отклик отправлен.');
    }

    /**
     * У вакансии настроено и включено ИИ-собеседование.
     *
     * Спрашиваем isUsable(), а не просто enabled: включённое собеседование с
     * противоречивыми настройками никого собеседовать не сможет, и вести туда
     * кандидата — значит завести его в тупик.
     */
    private function aiInterviewAwaits(Vacancy $vacancy): bool
    {
        $config = \App\Models\AiInterviewConfig::where('vacancy_id', $vacancy->id)->first();

        return $config !== null && $config->isUsable();
    }

    /**
     * Владелец вакансии (работодатель) меняет статус отклика.
     */
    public function updateStatus(Request $request, VacancyResponse $vacancyResponse)
    {
        $employer = auth()->user()->employer;

        abort_if(! $employer || $vacancyResponse->vacancy->employer_id !== $employer->id, 403);

        $validated = $request->validate([
            'status' => 'required|in:viewed,accepted,rejected',
        ]);

        $vacancyResponse->update(['status' => $validated['status']]);

        $labels = [
            'viewed' => 'Отклик отмечен просмотренным.',
            'accepted' => 'Отклик принят.',
            'rejected' => 'Отклик отклонён.',
        ];

        UserNotification::deliver(
            $vacancyResponse->applicant?->user_id,
            'response_status',
            'Статус вашего отклика изменён',
            ($vacancyResponse->vacancy?->title ?? 'Вакансия').': '.$labels[$validated['status']],
            route('public.responses.index'),
            'mail_responses',
        );

        return back()->with('status', $labels[$validated['status']]);
    }

    /**
     * Соискатель отзывает свой отклик.
     */
    public function destroy(Vacancy $vacancy)
    {
        $applicant = auth()->user()->applicant;

        abort_if(! $applicant, 403);

        VacancyResponse::where('applicant_id', $applicant->id)
            ->where('vacancy_id', $vacancy->id)
            ->delete();

        return back()->with('status', 'Отклик отозван.');
    }
}
