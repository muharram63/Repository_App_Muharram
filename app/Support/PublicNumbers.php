<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Цифры для публичной страницы статистики.
 *
 * Считается всё одним набором и кладётся в кэш: страница открыта всем,
 * и пересчитывать полтора десятка агрегатов на каждый заход незачем —
 * данные меняются медленнее, чем приходят посетители.
 *
 * Наружу отдаются только обезличенные суммы: ни имён, ни контактов,
 * ни жалоб и модерации — это витрина, а не выгрузка базы.
 */
class PublicNumbers
{
    /** Насколько кэшируем. Статистика — не биржевые котировки. */
    public const TTL_MINUTES = 15;

    private const CACHE_KEY = 'public.stats.v1';

    /** Сколько строк показываем в рейтингах городов и отраслей. */
    private const TOP = 8;

    public static function all(): array
    {
        // Ключевые цифры считаем на каждый заход: это семь простых COUNT,
        // зато счётчик всегда совпадает с базой. Под кэшем они расходились —
        // статус отклика меняется в любую минуту, а плитка показывала
        // значение пятнадцатиминутной давности.
        $heavy = Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(self::TTL_MINUTES),
            fn () => [
                'months' => self::months(),
                'cities' => self::cities(),
                'industries' => self::industries(),
                'conditions' => self::conditions(),
                'skills' => self::skills(),
                'generated_at' => now()->toDateTimeString(),
            ]
        );

        return ['headline' => self::headline()] + $heavy;
    }

    /**
     * Активные вакансии, которые действительно видны в каталоге.
     *
     * Одного status = active мало: вакансии заблокированных компаний каталог
     * прячет. Раньше статистика их считала, и цифра на плитке разошлась бы
     * со списком, который открывается по клику. Правило должно быть одно.
     */
    private static function visibleVacancies(): \Illuminate\Database\Query\Builder
    {
        return DB::table('vacancies')
            ->join('employers', 'vacancies.employer_id', '=', 'employers.id')
            ->join('users', 'employers.user_id', '=', 'users.id')
            ->where('vacancies.status', 'active')
            ->whereNotIn('users.status', \App\Models\User::BLOCKED_STATUSES);
    }

    /** Сбросить кэш — пригодится в тестах и после массовой заливки данных. */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    // ==================== ключевые цифры ====================

    private static function headline(): array
    {
        // «нашли работу» — принятые отклики в обе стороны: и когда откликнулся
        // соискатель, и когда предложила компания
        $hired = DB::table('vacancy_responses')->where('status', 'accepted')->count()
            + DB::table('resume_responses')->where('status', 'accepted')->count();

        $responses = DB::table('vacancy_responses')->count()
            + DB::table('resume_responses')->count();

        return [
            'hired' => $hired,
            'vacancies' => self::visibleVacancies()->count(),
            'resumes' => DB::table('resumes')->count(),
            'companies' => DB::table('employers')->count(),
            'responses' => $responses,
            'interviews' => DB::table('interviews')->count(),
            // сколько городов реально охвачено активными вакансиями
            'cities' => self::visibleVacancies()
                ->whereNotNull('vacancies.city_id')->distinct()->count('vacancies.city_id'),
        ];
    }

    /**
     * Список трудоустройств для отдельной страницы.
     *
     * Показываем имя кандидата и вакансию — так решил владелец площадки.
     * Страница публичная, поэтому наружу идёт минимум: имя, должность,
     * компания, город и дата. Ни контактов, ни почты, ни зарплаты.
     *
     * Две половины: соискатель откликнулся на вакансию и его приняли — и
     * компания пригласила по резюме, а кандидат согласился. Во втором случае
     * вакансии нет вовсе, поэтому в колонке должности стоит профессия
     * из резюме, а метка говорит, что это приглашение.
     */
    public static function hires(int $perPage = 30): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $byVacancy = DB::table('vacancy_responses')
            ->join('vacancies', 'vacancy_responses.vacancy_id', '=', 'vacancies.id')
            ->join('employers', 'vacancies.employer_id', '=', 'employers.id')
            ->join('applicants', 'vacancy_responses.applicant_id', '=', 'applicants.id')
            ->join('users', 'applicants.user_id', '=', 'users.id')
            ->leftJoin('cities', 'vacancies.city_id', '=', 'cities.id')
            ->where('vacancy_responses.status', 'accepted')
            ->selectRaw("users.name as person, vacancies.title as role,
                employers.company_name as company, cities.region as city,
                vacancy_responses.updated_at as hired_at, 'vacancy' as source");

        // приглашение по резюме: вакансии нет, поэтому в должности —
        // профессия из резюме, а имя берём через владельца резюме
        $byResume = DB::table('resume_responses')
            ->join('resumes', 'resume_responses.resume_id', '=', 'resumes.id')
            ->join('applicants', 'resumes.applicant_id', '=', 'applicants.id')
            ->join('users', 'applicants.user_id', '=', 'users.id')
            ->join('employers', 'resume_responses.employer_id', '=', 'employers.id')
            ->leftJoin('cities', 'employers.city_id', '=', 'cities.id')
            ->where('resume_responses.status', 'accepted')
            ->selectRaw("users.name as person, resumes.profession as role,
                employers.company_name as company, cities.region as city,
                resume_responses.updated_at as hired_at, 'resume' as source");

        return $byVacancy->unionAll($byResume)
            ->orderByDesc('hired_at')
            ->paginate($perPage);
    }

    // ==================== динамика за 12 месяцев ====================

    /**
     * По месяцам: регистрации, новые вакансии и отклики.
     *
     * Один запрос на таблицу вместо двенадцати: группировка идёт в базе.
     *
     * @return array<int,array{label:string,users:int,vacancies:int,responses:int}>
     */
    private static function months(): array
    {
        $from = now()->copy()->startOfMonth()->subMonths(11);

        $users = self::byMonth('users', $from);
        $vacancies = self::byMonth('vacancies', $from);
        $responses = self::byMonth('vacancy_responses', $from);
        $invites = self::byMonth('resume_responses', $from);

        $rows = [];
        $names = ['янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];

        for ($i = 0; $i < 12; $i++) {
            $month = $from->copy()->addMonths($i);
            $key = $month->format('Y-m');

            $rows[] = [
                'label' => $names[(int) $month->format('n') - 1],
                'users' => (int) ($users[$key] ?? 0),
                'vacancies' => (int) ($vacancies[$key] ?? 0),
                'responses' => (int) ($responses[$key] ?? 0) + (int) ($invites[$key] ?? 0),
            ];
        }

        return $rows;
    }

    /** @return array<string,int> «2026-09» => сколько */
    private static function byMonth(string $table, $from): array
    {
        // date_format есть в MySQL, но не в SQLite, на котором идут тесты
        $month = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "date_format(created_at, '%Y-%m')";

        return DB::table($table)
            ->selectRaw($month.' as m, count(*) as c')
            ->where('created_at', '>=', $from)
            ->groupBy('m')
            ->pluck('c', 'm')
            ->all();
    }

    // ==================== география и отрасли ====================

    /** @return array<int,array{name:string,count:int}> */
    private static function cities(): array
    {
        return self::visibleVacancies()
            ->join('cities', 'vacancies.city_id', '=', 'cities.id')
            ->selectRaw('cities.region as name, count(*) as count')
            ->groupBy('cities.region')
            ->orderByDesc('count')
            ->limit(self::TOP)
            ->get()
            ->map(fn ($row) => ['name' => self::shortCity($row->name), 'count' => (int) $row->count])
            ->all();
    }

    /**
     * «Согдийская область, город Худжанд» -> «Худжанд»: в подписи столбика
     * длинное официальное название не помещается и читается хуже.
     */
    public static function shortCity(string $region): string
    {
        if (preg_match('/город\s+(.+)$/u', $region, $m)) {
            return trim($m[1]);
        }

        return $region;
    }

    /** @return array<int,array{name:string,count:int}> */
    private static function industries(): array
    {
        return self::visibleVacancies()
            ->join('industries', 'employers.industry_id', '=', 'industries.id')
            ->selectRaw('industries.name as name, count(*) as count')
            ->groupBy('industries.name')
            ->orderByDesc('count')
            ->limit(self::TOP)
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'count' => (int) $row->count])
            ->all();
    }

    // ==================== условия работы ====================

    /**
     * Занятость, график и требуемый опыт по активным вакансиям.
     * Подписи берём из того же справочника, что и админка, — чтобы
     * одни и те же значения не назывались в двух местах по-разному.
     */
    private static function conditions(): array
    {
        $sets = [
            'employment' => ['Занятость', AdminNumbers::DISTRIBUTIONS['employment'][2]],
            'schedule' => ['График работы', AdminNumbers::DISTRIBUTIONS['schedule'][2]],
            'experience' => ['Требуемый опыт', AdminNumbers::DISTRIBUTIONS['experience'][2]],
        ];

        $columns = [
            'employment' => 'employment_type',
            'schedule' => 'work_schedule',
            'experience' => 'experience_required',
        ];

        $result = [];

        foreach ($sets as $key => [$title, $labels]) {
            $counts = self::visibleVacancies()
                ->selectRaw('vacancies.'.$columns[$key].' as k, count(*) as c')
                ->groupBy('k')
                ->pluck('c', 'k')
                ->all();

            $rows = [];
            foreach ($labels as $value => $label) {
                $rows[] = ['label' => $label, 'count' => (int) ($counts[$value] ?? 0)];
            }

            // значения вне справочника не выбрасываем молча — иначе сумма
            // долей не сойдётся со «всего вакансий» и проценты соврут
            $known = array_sum(array_column($rows, 'count'));
            $other = array_sum($counts) - $known;

            if ($other > 0) {
                $rows[] = ['label' => 'Прочее', 'count' => $other];
            }

            $result[$key] = [
                'title' => $title,
                'total' => $known + max(0, $other),
                'rows' => array_values(array_filter($rows, fn ($r) => $r['count'] > 0)),
            ];
        }

        return $result;
    }

    // ==================== подтверждённые навыки ====================

    /**
     * То, чего нет у обычной доски вакансий: навык, доказанный заданием.
     */
    private static function skills(): array
    {
        $passing = \App\Models\SkillAttempt::PASSING;

        $finished = DB::table('skill_attempts')->whereNotNull('finished_at');

        $total = (clone $finished)->count();
        $passed = (clone $finished)->where('score', '>=', $passing)->count();

        $top = DB::table('skill_attempts')
            ->join('skill_tests', 'skill_attempts.skill_test_id', '=', 'skill_tests.id')
            ->join('skills', 'skill_tests.skill_id', '=', 'skills.id')
            ->whereNotNull('skill_attempts.finished_at')
            ->where('skill_attempts.score', '>=', $passing)
            ->selectRaw('skills.name as name, count(*) as count')
            ->groupBy('skills.name')
            ->orderByDesc('count')
            ->limit(6)
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'count' => (int) $row->count])
            ->all();

        return [
            'attempts' => $total,
            'passed' => $passed,
            'rate' => $total > 0 ? (int) round($passed / $total * 100) : 0,
            'passing' => $passing,
            'catalogue' => DB::table('skills')->count(),
            'top' => $top,
        ];
    }
}
