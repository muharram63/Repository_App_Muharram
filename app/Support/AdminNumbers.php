<?php

namespace App\Support;

use App\Models\Applicant;
use App\Models\Complaint;
use App\Models\Conversation;
use App\Models\Employer;
use App\Models\Interview;
use App\Models\Message;
use App\Models\Resume;
use App\Models\ResumeResponse;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyResponse;
use Illuminate\Support\Facades\DB;

/**
 * Числа админ-панели в одном месте. Их считают и страницы (обзор, аналитика),
 * и живые счётчики — если бы формулы жили в двух местах, значение на экране
 * и значение после обновления рано или поздно разошлись бы.
 *
 * Каждый набор считается не больше одного раза за запрос.
 */
class AdminNumbers
{
    /**
     * Распределения аналитики: модель, колонка и подписи значений.
     * Один список на страницу и на живые счётчики.
     */
    public const DISTRIBUTIONS = [
        'employment' => [Vacancy::class, 'employment_type', [
            'full-time' => 'Полная занятость',
            'part-time' => 'Частичная занятость',
            'project_work' => 'Проектная работа',
            'internship' => 'Стажировка',
        ]],
        'schedule' => [Vacancy::class, 'work_schedule', [
            'full_day' => 'Полный день',
            'flexible_schedule' => 'Гибкий график',
            'remote_work' => 'Удалённо',
        ]],
        'experience' => [Vacancy::class, 'experience_required', [
            'not' => 'Без опыта',
            'year' => 'От 1 года',
            '3_years' => 'От 3 лет',
            '3-6years' => '3–6 лет',
            'more_6years' => 'Более 6 лет',
        ]],
        'status' => [Vacancy::class, 'status', [
            'active' => 'Активные',
            'inactive' => 'Неактивные',
            'closed' => 'Закрытые',
            'in_archived' => 'В архиве',
        ]],
        'response' => [VacancyResponse::class, 'status', [
            'new' => 'Новые',
            'viewed' => 'Просмотренные',
            'accepted' => 'Принятые',
            'rejected' => 'Отклонённые',
        ]],
    ];

    private array $memo = [];

    private function once(string $key, callable $fn)
    {
        return $this->memo[$key] ??= $fn();
    }

    /**
     * Простые итоги: обзор и верхние плитки аналитики.
     */
    public function totals(): array
    {
        return $this->once('totals', function () {
            $monthAgo = now()->subMonth();

            $responsesTotal = VacancyResponse::count() + ResumeResponse::count();
            $accepted = VacancyResponse::where('status', 'accepted')->count()
                + ResumeResponse::where('status', 'accepted')->count();
            $vacanciesTotal = Vacancy::count();
            $responsesVacancy = VacancyResponse::count();
            $responsesOnActive = VacancyResponse::whereIn(
                'vacancy_id',
                Vacancy::where('status', 'active')->select('id')
            )->count();
            return [
                'activeVacancies' => Vacancy::where('status', 'active')->count(),
                'vacanciesTotal' => $vacanciesTotal,
                'vacanciesMonth' => Vacancy::where('created_at', '>=', $monthAgo)->count(),
                'usersTotal' => User::count(),
                'usersMonth' => User::where('created_at', '>=', $monthAgo)->count(),
                'applicantsTotal' => Applicant::count(),
                'employersTotal' => Employer::count(),
                'resumesTotal' => Resume::count(),
                'resumesMonth' => Resume::where('created_at', '>=', $monthAgo)->count(),
                'responsesTotal' => $responsesVacancy,
                'invitesTotal' => ResumeResponse::count(),
                'obligationsTotal' => $responsesTotal,
                'responsesNew' => VacancyResponse::where('status', 'new')->count()
                    + ResumeResponse::where('status', 'new')->count(),
                'acceptedTotal' => $accepted,
                'conversion' => $responsesTotal > 0 ? round($accepted / $responsesTotal * 100, 1) : 0,
                // сколько откликов пришло на вакансии, опубликованные сейчас
                'responsesOnActive' => $responsesOnActive,
                'responsesActiveShare' => $responsesVacancy > 0
                    ? round($responsesOnActive / $responsesVacancy * 100, 1)
                    : 0,
                'interviewsTotal' => Interview::count(),
                'interviewsUpcoming' => Interview::where('scheduled_at', '>=', now())
                    ->whereNotIn('status', ['canceled', 'declined'])->count(),
                'conversationsTotal' => Conversation::count(),
                'messagesTotal' => Message::count(),
                'avgExperience' => round(Resume::avg('experience_years') ?? 0, 1),
            ];
        });
    }

    /**
     * Динамика за 30 дней в сравнении с предыдущими 30.
     */
    public function dynamics(): array
    {
        return $this->once('dynamics', function () {
            $curFrom = now()->copy()->subDays(30);
            $prevFrom = now()->copy()->subDays(60);
            $today = now()->copy()->startOfDay();

            // один запрос на таблицу: текущий период, предыдущий и сегодня сразу.
            // case when вместо sum(условие): булево сложение понимает только MySQL
            $count = function (string $model) use ($curFrom, $prevFrom, $today) {
                $row = $model::selectRaw(
                    'sum(case when created_at >= ? then 1 else 0 end) as cur,
                     sum(case when created_at >= ? and created_at < ? then 1 else 0 end) as prev,
                     sum(case when created_at >= ? then 1 else 0 end) as today',
                    [$curFrom, $prevFrom, $curFrom, $today]
                )->first();

                return [(int) $row->cur, (int) $row->prev, (int) $row->today];
            };

            $metric = function (string $label, array $models) use ($count) {
                $current = $previous = $todayCount = 0;

                foreach ($models as $model) {
                    [$cur, $prev, $day] = $count($model);
                    $current += $cur;
                    $previous += $prev;
                    $todayCount += $day;
                }

                return [
                    'label' => $label,
                    'value' => $current,
                    'previous' => $previous,
                    'today' => $todayCount,
                    // null означает «в прошлом периоде не было, сравнивать не с чем»
                    'delta' => $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null,
                ];
            };

            return [
                'users' => $metric('Регистрации', [User::class]),
                'vacancies' => $metric('Новые вакансии', [Vacancy::class]),
                'resumes' => $metric('Новые резюме', [Resume::class]),
                'responses' => $metric('Отклики и приглашения', [VacancyResponse::class, ResumeResponse::class]),
                'interviews' => $metric('Собеседования', [Interview::class]),
            ];
        });
    }

    /**
     * Конверсия за 30 дней и за предыдущие 30.
     */
    public function conversion(): array
    {
        return $this->once('conversion', function () {
            $curFrom = now()->copy()->subDays(30);
            $prevFrom = now()->copy()->subDays(60);

            // строковые литералы в одинарных кавычках: двойные — это идентификатор
            $row = fn (string $model) => $model::selectRaw(
                "sum(case when created_at >= ? then 1 else 0 end) as cur_all,
                 sum(case when created_at >= ? and status = 'accepted' then 1 else 0 end) as cur_ok,
                 sum(case when created_at >= ? and created_at < ? then 1 else 0 end) as prev_all,
                 sum(case when created_at >= ? and created_at < ? and status = 'accepted' then 1 else 0 end) as prev_ok",
                [$curFrom, $curFrom, $prevFrom, $curFrom, $prevFrom, $curFrom]
            )->first();

            $vacancy = $row(VacancyResponse::class);
            $resume = $row(ResumeResponse::class);

            $all = (int) $vacancy->cur_all + (int) $resume->cur_all;
            $ok = (int) $vacancy->cur_ok + (int) $resume->cur_ok;
            $prevAll = (int) $vacancy->prev_all + (int) $resume->prev_all;
            $prevOk = (int) $vacancy->prev_ok + (int) $resume->prev_ok;

            return [
                'responses30' => $all,
                'accepted30' => $ok,
                'conversion30' => $all > 0 ? round($ok / $all * 100, 1) : 0,
                'conversionPrev' => $prevAll > 0 ? round($prevOk / $prevAll * 100, 1) : 0,
            ];
        });
    }

    /**
     * Жалобы: итоги, динамика и разрезы по причине и объекту.
     */
    public function complaints(): array
    {
        return $this->once('complaints', function () {
            $curFrom = now()->copy()->subDays(30);
            $prevFrom = now()->copy()->subDays(60);

            $row = Complaint::selectRaw(
                "count(*) as total,
                 sum(case when status = 'new' then 1 else 0 end) as fresh,
                 sum(case when status = 'resolved' then 1 else 0 end) as resolved,
                 sum(case when status = 'rejected' then 1 else 0 end) as rejected,
                 sum(case when created_at >= ? then 1 else 0 end) as cur,
                 sum(case when created_at >= ? and created_at < ? then 1 else 0 end) as prev",
                [$curFrom, $prevFrom, $curFrom]
            )->first();

            $total = (int) $row->total;
            $cur = (int) $row->cur;
            $prev = (int) $row->prev;

            return [
                'total' => $total,
                'new' => (int) $row->fresh,
                'resolved' => (int) $row->resolved,
                'rejected' => (int) $row->rejected,
                'cur' => $cur,
                'prev' => $prev,
                'delta' => $prev > 0 ? round(($cur - $prev) / $prev * 100, 1) : null,
                'share' => $total > 0 ? round((int) $row->resolved / $total * 100, 1) : 0,
                'byReason' => Complaint::selectRaw('reason, count(*) as total')
                    ->groupBy('reason')->pluck('total', 'reason'),
                'byTarget' => Complaint::selectRaw('target_type, count(*) as total')
                    ->groupBy('target_type')->pluck('total', 'target_type'),
                'today' => Complaint::whereDate('created_at', today())->count(),
                'week' => Complaint::where('created_at', '>=', now()->subWeek())->count(),
                'trashed' => Complaint::onlyTrashed()->count(),
            ];
        });
    }

    /**
     * Распределение записей по колонке с человекочитаемыми подписями.
     */
    public function distribution(string $model, string $column, array $labels): array
    {
        return $this->once('dist:'.$model.':'.$column, function () use ($model, $column, $labels) {
            $rows = $model::select($column, DB::raw('count(*) as total'))
                ->groupBy($column)
                ->pluck('total', $column);

            // база — только показанные значения: строки с неизвестным значением
            // раньше съедали часть шкалы, и доли на экране не давали 100%
            $counts = [];

            foreach (array_keys($labels) as $key) {
                $counts[$key] = (int) ($rows[$key] ?? 0);
            }

            $percents = self::shares($counts);

            $result = [];

            foreach ($labels as $key => $label) {
                $result[$key] = [
                    'label' => $label,
                    'count' => $counts[$key],
                    'percent' => $percents[$key],
                ];
            }

            return $result;
        });
    }

    /**
     * Все распределения аналитики одним набором: подписи для страницы и ключи
     * для живых счётчиков берутся отсюда, иначе списки разъезжаются и счётчик
     * молча перестаёт обновляться.
     */
    public function distributions(): array
    {
        $result = [];

        foreach (self::DISTRIBUTIONS as $name => [$model, $column, $labels]) {
            $rows = $this->distribution($model, $column, $labels);

            foreach ($rows as $key => $row) {
                $rows[$key]['slug'] = self::slug($name, $key);
            }

            $result[$name] = $rows;
        }

        return $result;
    }

    /**
     * Ключ живого счётчика для одной строки распределения.
     */
    public static function slug(string $name, string $key): string
    {
        return 'an-dist-'.$name.'-'.str_replace('_', '', $key);
    }

    /**
     * Доли в процентах с одним знаком после запятой. Сумма ровно 100:
     * независимое округление каждой доли давало 99 или 101, а мелкая, но
     * не нулевая доля превращалась в 0% и полосу нулевой ширины.
     *
     * @param  array<string,int>  $counts
     * @return array<string,float>
     */
    public static function shares(array $counts): array
    {
        $total = array_sum($counts);

        if ($total <= 0) {
            return array_map(fn () => 0.0, $counts);
        }

        // считаем в десятых долях процента, ненулевому значению даём минимум 0,1%
        $tenths = [];

        foreach ($counts as $key => $count) {
            $tenths[$key] = $count > 0 ? max(1, (int) round($count / $total * 1000)) : 0;
        }

        // накопленную погрешность забирает самая крупная доля: там она незаметна
        $rest = 1000 - array_sum($tenths);

        if ($rest !== 0) {
            $biggest = array_search(max($counts), $counts, true);
            $tenths[$biggest] = max(1, $tenths[$biggest] + $rest);
        }

        return array_map(fn (int $value) => round($value / 10, 1), $tenths);
    }
}
