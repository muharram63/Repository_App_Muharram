<?php

namespace App\Http\Controllers;

use App\Models\AdminComment;
use App\Models\Applicant;
use App\Models\Complaint;
use App\Models\Conversation;
use App\Models\Employer;
use App\Models\Message;
use App\Models\Resume;
use App\Models\ResumeResponse;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\VacancyResponse;
use App\Support\AdminNumbers;
use App\Support\CallJournal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Живые счётчики. Отдаёт плоскую карту «ключ => число»; скрипт из
 * partials/live-counters подставляет их в элементы с атрибутом data-live.
 *
 * Каждый ключ — отдельное замыкание, и считаются только те, что запросила
 * страница (параметр keys). Иначе открытая вкладка кабинета каждые двадцать
 * секунд пересчитывала бы и аналитику, и журнал звонков.
 */
class LiveCounterController extends Controller
{
    /**
     * Цифры кабинета соискателя и работодателя.
     */
    public function cabinet(Request $request)
    {
        $user = $request->user();

        abort_if(! $user || (! $user->employer && ! $user->applicant), 403);

        return $this->answer($request, $this->cabinetMap($user));
    }

    /**
     * Цифры админ-панели.
     */
    public function admin(Request $request)
    {
        return $this->answer($request, $this->adminMap());
    }

    /**
     * Считаем запрошенное и отдаём. Без параметра keys — считаем всё.
     */
    private function answer(Request $request, array $map)
    {
        $asked = array_filter(explode(',', (string) $request->query('keys')));

        if ($asked) {
            $map = array_intersect_key($map, array_flip($asked));
        }

        return response()->json(array_map(fn (callable $value) => $value(), $map));
    }

    /**
     * @return array<string, callable>
     */
    private function cabinetMap(User $user): array
    {
        // цифры меню считаются пачкой и кэшируются на время запроса
        $badge = fn (string $key) => fn () => $user->cabinetBadges()[$key];

        $callCounts = null;
        $calls = function () use (&$callCounts, $user) {
            return $callCounts ??= CallJournal::counts(CallJournal::rows($user));
        };

        $mine = fn () => Complaint::where('user_id', $user->id)
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $map = [
            // бейджи меню
            'nav-notifications' => $badge('notifications'),
            'nav-support' => $badge('support'),
            'nav-complaints' => $badge('complaints'),

            // ящик уведомлений
            'notifications-unread' => $badge('notifications'),
            'notifications-total' => fn () => UserNotification::where('user_id', $user->id)->count(),

            // обращения к администрации
            'support-total' => fn () => AdminComment::where('user_id', $user->id)->count(),
            'support-answered' => fn () => AdminComment::where('user_id', $user->id)
                ->whereNotNull('answered_at')->count(),

            // жалобы: свои и на меня
            'complaints-all' => fn () => Complaint::where('user_id', $user->id)->count(),
            'complaints-resolved' => fn () => (int) ($mine()['resolved'] ?? 0),
            'complaints-rejected' => fn () => (int) ($mine()['rejected'] ?? 0),
            'complaints-incoming-open' => fn () => Complaint::againstUser($user)
                ->whereIn('status', ['new', 'in_review'])->count(),

            // журнал звонков собирается в PHP, поэтому считаем его лениво
            // и не больше одного раза за запрос
            'calls-all' => fn () => $calls()['all'],
            'calls-in' => fn () => $calls()['in'],
            'calls-out' => fn () => $calls()['out'],
            'calls-missed' => fn () => $calls()['missed'],
        ];

        return $map + $this->roleMap($user);
    }

    /**
     * Сводка кабинета — у каждой роли своя.
     *
     * @return array<string, callable>
     */
    private function roleMap(User $user): array
    {
        if ($employer = $user->employer) {
            $vacancyIds = fn () => $employer->vacancies()->select('id');

            return [
                'profileViews' => fn () => (int) $employer->vacancies()->sum('views'),
                'vacanciesCount' => fn () => $employer->vacancies()->count(),
                // пришло: отклики на вакансии компании
                'responsesCount' => fn () => VacancyResponse::whereIn('vacancy_id', $vacancyIds())->count(),
                'responsesPending' => fn () => VacancyResponse::whereIn('vacancy_id', $vacancyIds())
                    ->where('status', 'new')->count(),
                // отправлено: приглашения, разосланные компанией
                'invitesCount' => fn () => ResumeResponse::where('employer_id', $employer->id)->count(),
                'invitesPending' => fn () => ResumeResponse::where('employer_id', $employer->id)
                    ->where('status', 'new')->count(),
            ];
        }

        $applicant = $user->applicant;

        return [
            'profileViews' => fn () => (int) Resume::where('applicant_id', $applicant->id)->sum('views'),
            'resumesCount' => fn () => Resume::where('applicant_id', $applicant->id)->count(),
            'responsesCount' => fn () => VacancyResponse::where('applicant_id', $applicant->id)->count(),
            'responsesPending' => fn () => VacancyResponse::where('applicant_id', $applicant->id)
                ->where('status', 'new')->count(),
            // пришло: приглашения работодателей по резюме соискателя
            'invitesCount' => fn () => ResumeResponse::whereIn(
                'resume_id',
                Resume::where('applicant_id', $applicant->id)->select('id')
            )->count(),
            'invitesPending' => fn () => ResumeResponse::whereIn(
                'resume_id',
                Resume::where('applicant_id', $applicant->id)->select('id')
            )->where('status', 'new')->count(),
        ];
    }

    /**
     * @return array<string, callable>
     */
    private function adminMap(): array
    {
        $numbers = new AdminNumbers;

        $comments = $this->once(fn () => AdminComment::selectRaw('status, count(*) as total')
            ->groupBy('status')->pluck('total', 'status'));

        $roles = $this->once(fn () => User::selectRaw('role, count(*) as total')
            ->groupBy('role')->pluck('total', 'role'));

        $totals = fn (string $key) => fn () => $numbers->totals()[$key];

        $map = [
            // бейджи меню
            'nav-complaints' => fn () => Complaint::where('status', 'new')->count(),
            'nav-comments' => fn () => (int) ($comments()['new'] ?? 0) + (int) ($comments()['in_review'] ?? 0),

            // раздел жалоб
            'complaints-total' => fn () => $numbers->complaints()['total'],
            'complaints-today' => fn () => $numbers->complaints()['today'],
            'complaints-week' => fn () => $numbers->complaints()['week'],
            'complaints-trashed' => fn () => $numbers->complaints()['trashed'],

            // раздел обращений
            'comments-new' => fn () => (int) ($comments()['new'] ?? 0),
            'comments-in_review' => fn () => (int) ($comments()['in_review'] ?? 0),
            'comments-answered' => fn () => (int) ($comments()['answered'] ?? 0),
            'comments-closed' => fn () => (int) ($comments()['closed'] ?? 0),

            // раздел пользователей
            'users-all' => fn () => (int) $roles()->sum(),
            'users-applicant' => fn () => (int) ($roles()['applicant'] ?? 0),
            'users-employer' => fn () => (int) ($roles()['employer'] ?? 0),
            'users-admin' => fn () => (int) ($roles()['admin'] ?? 0),
            'users-blocked' => fn () => User::whereIn('status', User::BLOCKED_STATUSES)->count(),
        ];

        // простые итоги обзора и верхних плиток аналитики
        foreach ([
            'activeVacancies', 'vacanciesTotal', 'vacanciesMonth',
            'usersTotal', 'usersMonth', 'applicantsTotal', 'employersTotal',
            'resumesTotal', 'resumesMonth', 'responsesTotal', 'invitesTotal',
            'obligationsTotal', 'responsesNew', 'acceptedTotal', 'conversion',
            'responsesOnActive', 'responsesActiveShare', 'interviewsTotal', 'interviewsUpcoming',
            'conversationsTotal', 'messagesTotal', 'avgExperience',
        ] as $key) {
            $map[$key] = $totals($key);
        }

        return $map + $this->analyticsMap($numbers);
    }

    /**
     * Числа страницы аналитики: воронка, динамика, конверсия, жалобы, распределения.
     *
     * @return array<string, callable>
     */
    private function analyticsMap(AdminNumbers $numbers): array
    {
        $map = [
            'an-conversion' => fn () => $numbers->totals()['conversion'],
            'an-accepted' => fn () => $numbers->totals()['acceptedTotal'],
            'an-obligations' => fn () => $numbers->totals()['obligationsTotal'],
            'an-responses-active-share' => fn () => $numbers->totals()['responsesActiveShare'],
            'an-responses-on-active' => fn () => $numbers->totals()['responsesOnActive'],
            'an-responses' => fn () => $numbers->totals()['responsesTotal'],
            'an-avg-experience' => fn () => $numbers->totals()['avgExperience'],

            'an-conv30' => fn () => $numbers->conversion()['conversion30'],
            'an-conv-prev' => fn () => $numbers->conversion()['conversionPrev'],
            'an-accepted30' => fn () => $numbers->conversion()['accepted30'],
            'an-responses30' => fn () => $numbers->conversion()['responses30'],

            'an-complaints-total' => fn () => $numbers->complaints()['total'],
            'an-complaints-new' => fn () => $numbers->complaints()['new'],
            'an-complaints-resolved' => fn () => $numbers->complaints()['resolved'],
            'an-complaints-rejected' => fn () => $numbers->complaints()['rejected'],
            'an-complaints-share' => fn () => $numbers->complaints()['share'],
            'an-complaints-cur' => fn () => $numbers->complaints()['cur'],
            'an-complaints-prev' => fn () => $numbers->complaints()['prev'],
        ];

        // динамика за 30 дней
        foreach (array_keys($numbers->dynamics()) as $slug) {
            $map['an-dyn-'.$slug] = fn () => $numbers->dynamics()[$slug]['value'];
            $map['an-dyn-'.$slug.'-today'] = fn () => $numbers->dynamics()[$slug]['today'];
            $map['an-dyn-'.$slug.'-prev'] = fn () => $numbers->dynamics()[$slug]['previous'];
        }

        // разрезы жалоб по причине и по объекту
        $share = function (int $value) use ($numbers) {
            $total = $numbers->complaints()['total'];

            return $total > 0 ? round($value / $total * 100) : 0;
        };

        foreach (array_keys(Complaint::REASONS) as $reason) {
            $value = fn () => (int) ($numbers->complaints()['byReason'][$reason] ?? 0);
            $map['an-reason-'.$reason] = $value;
            $map['an-reason-'.$reason.'-pct'] = fn () => $share($value());
        }

        foreach (array_keys(Complaint::TARGETS) as $target) {
            $value = fn () => (int) ($numbers->complaints()['byTarget'][$target] ?? 0);
            $map['an-target-'.$target] = $value;
            $map['an-target-'.$target.'-pct'] = fn () => $share($value());
        }

        return $map + $this->distributionMap($numbers);
    }

    /**
     * Распределения вакансий и откликов: число и доля в процентах.
     *
     * @return array<string, callable>
     */
    private function distributionMap(AdminNumbers $numbers): array
    {
        $map = [];

        // наборы и подписи берём из общего источника: если бы список жил здесь
        // своей копией, страница и счётчики разошлись бы ключами
        foreach (AdminNumbers::DISTRIBUTIONS as $name => [$model, $column, $labels]) {
            foreach (array_keys($labels) as $key) {
                $slug = AdminNumbers::slug($name, $key);

                $map[$slug] = fn () => $numbers->distribution($model, $column, $labels)[$key]['count'];
                $map[$slug.'-pct'] = fn () => $numbers->distribution($model, $column, $labels)[$key]['percent'];
            }
        }

        return $map;
    }

    /**
     * Памятка: одно и то же выражение не считается дважды за запрос.
     */
    private function once(callable $fn): callable
    {
        $done = false;
        $value = null;

        return function () use (&$done, &$value, $fn) {
            if (! $done) {
                $value = $fn();
                $done = true;
            }

            return $value;
        };
    }

}
