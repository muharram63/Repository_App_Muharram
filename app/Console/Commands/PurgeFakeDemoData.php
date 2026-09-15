<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Убирает вымышленные демо-записи, оставляя данные с реальными названиями.
 *
 * DemoDataSeeder наполнял базу выдуманными компаниями вида «ООО „ТехноСофт 7“»
 * и обезличенными соискателями applicant1…applicant200. На витрине и в
 * аналитике это выглядело как настоящий рынок, которым не является.
 *
 * Свои записи сидеры помечают почтовым доменом, по нему и отличаем:
 *   applicantN@workio.tj / employerN@workio.tj — вымышленные, удаляем;
 *   @tj.workio.tj, @edu.workio.tj, @pro.workio.tj — таджикские наборы, оставляем;
 *   всё остальное — живые аккаунты, их не трогаем никогда.
 *
 *   php artisan demo:purge --dry-run   посмотреть, что удалится
 *   php artisan demo:purge             удалить
 */
class PurgeFakeDemoData extends Command
{
    protected $signature = 'demo:purge {--dry-run : Только показать, ничего не удаляя}';

    protected $description = 'Удалить вымышленные демо-данные, оставив компании с реальными названиями';

    /** Почты вымышленных записей DemoDataSeeder. */
    private const FAKE_PATTERNS = ['applicant%@workio.tj', 'employer%@workio.tj'];

    /**
     * Компании из TajikDataSeeder, которые были добавлены «для объёма»
     * и реальным организациям не соответствуют.
     */
    private const INVENTED_COMPANIES = [
        'Баракат Маркет',
        'Сомон Маркет',
        'Телеком Технолоҷӣ',
        'Барака Логистик',
        'Сохтмони Пойтахт',
        'Меъмор',
        'Академияи Рушд',
        'Шифо Клиник',
        'Кӯҳистон Турс',
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $fakeUserIds = DB::table('users')
            ->where(function ($q) {
                foreach (self::FAKE_PATTERNS as $pattern) {
                    $q->orWhere('email', 'like', $pattern);
                }
            })
            ->pluck('id');

        $inventedUserIds = DB::table('employers')
            ->whereIn('company_name', self::INVENTED_COMPANIES)
            ->pluck('user_id');

        $userIds = $fakeUserIds->merge($inventedUserIds)->unique()->values();

        if ($userIds->isEmpty()) {
            $this->info('Вымышленных записей не найдено — база уже чистая.');

            return self::SUCCESS;
        }

        $employerIds = DB::table('employers')->whereIn('user_id', $userIds)->pluck('id');
        $applicantIds = DB::table('applicants')->whereIn('user_id', $userIds)->pluck('id');

        $vacancies = DB::table('vacancies')
            ->whereIn('employer_id', $employerIds)
            ->orWhereIn('created_by', $employerIds)
            ->count();
        $resumes = DB::table('resumes')->whereIn('applicant_id', $applicantIds)->count();

        $this->table(['Что удаляется', 'Сколько'], [
            ['пользователей', $userIds->count()],
            ['  из них выдуманных (DemoDataSeeder)', $fakeUserIds->count()],
            ['  из них компаний без реального прототипа', $inventedUserIds->count()],
            ['компаний-работодателей', $employerIds->count()],
            ['анкет соискателей', $applicantIds->count()],
            ['вакансий', $vacancies],
            ['резюме', $resumes],
        ]);

        if ($dry) {
            $this->comment('Пробный запуск: ничего не удалено.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($userIds, $employerIds) {
            // вакансии сносим отдельно: created_by ссылается на employers без
            // каскада, и MySQL упрётся в ключ, если удалять работодателя первым
            DB::table('vacancies')
                ->whereIn('employer_id', $employerIds)
                ->orWhereIn('created_by', $employerIds)
                ->delete();

            // остальное уходит каскадом от users: анкеты, резюме, отклики,
            // беседы, собеседования, уведомления
            DB::table('users')->whereIn('id', $userIds)->delete();
        });

        $this->info('Готово. Остались только записи с реальными названиями и живые аккаунты.');

        return self::SUCCESS;
    }
}
