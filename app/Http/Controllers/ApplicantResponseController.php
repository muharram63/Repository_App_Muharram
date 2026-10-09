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
            return back()->with('error', __('Откликаться на вакансии могут только соискатели.'));
        }

        $applicant = $user->applicant;

        if (! $applicant) {
            return redirect()->route('public.applicants.create')
                ->with('error', __('Сначала заполните анкету соискателя.'));
        }

        // вакансия закрытого аккаунта не показывается — и отклик по прямому
        // запросу на неё тоже не принимается
        if ($vacancy->status !== 'active' || $vacancy->employer?->user?->isBlocked()) {
            return back()->with('error', __('Эта вакансия больше не принимает отклики.'));
        }

        $validated = $request->validate([
            'message' => 'nullable|string|max:1000',
        ]);

        $response = VacancyResponse::firstOrCreate(
            ['applicant_id' => $applicant->id, 'vacancy_id' => $vacancy->id],
            ['message' => $validated['message'] ?? null]
        );

        if (! $response->wasRecentlyCreated) {
            return back()->with('status', __('Вы уже откликались на эту вакансию.'));
        }

        /*
         * Кандидат отзывал отклик, а собеседование по вакансии у него уже
         * было: привязываем его к новому отклику. Иначе решение ставило бы
         * статус «в никуда», а новый отклик так и висел необработанным.
         */
        \App\Models\AiInterview::where('vacancy_id', $vacancy->id)
            ->where('applicant_id', $applicant->id)
            ->update(['vacancy_response_id' => $response->id]);

        // уведомление получают обе стороны: работодателю — «новый кандидат»
        // (на сайте, в браузере и на почту — как он настроил), соискателю —
        // отметка о собственном действии, уже прочитанная
        \App\Support\EmployerAlerts::newCandidate($response->setRelation('vacancy', $vacancy));

        UserNotification::deliver(
            $user->id,
            'response',
            UserNotification::text('Вы откликнулись на вакансию'),
            UserNotification::text('«:title» · :company', [
                'title' => $vacancy->title,
                'company' => $vacancy->employer?->company_name ?? UserNotification::text('компания'),
            ]),
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
            /*
             * Это уведомление приходит непрочитанным, в отличие от соседнего.
             *
             * «Вы откликнулись» — запись о собственном действии, его человек
             * только что совершил, и зажигать ради неё колокольчик незачем.
             * А здесь приглашение, к которому нужно вернуться: вкладку закроют,
             * и непрочитанная отметка остаётся единственным, что напомнит.
             * Пометка read: true была скопирована у соседа, и из-за неё
             * закрывший вкладку не получал ни одного сигнала вовсе.
             */
            UserNotification::deliver(
                $user->id,
                'interview',
                UserNotification::text('По этой вакансии собеседование проводит ИИ'),
                UserNotification::text('«:title» — нужно дать согласие и загрузить документы', ['title' => $vacancy->title]),
                route('applicant.ai.start', $vacancy),
            );

            return redirect()->route('applicant.ai.start', $vacancy)
                ->with('status', __('Отклик отправлен. По этой вакансии собеседование проводит ИИ.'));
        }

        return back()->with('status', __('Отклик отправлен.'));
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
            UserNotification::text('Статус вашего отклика изменён'),
            UserNotification::text(':title: :status', [
                'title' => $vacancyResponse->vacancy?->title ?? UserNotification::text('Вакансия'),
                'status' => UserNotification::text($labels[$validated['status']]),
            ]),
            route('public.responses.index'),
            'mail_responses',
        );

        return back()->with('status', __($labels[$validated['status']]));
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

        return back()->with('status', __('Отклик отозван.'));
    }
}
