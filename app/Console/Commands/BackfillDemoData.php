<?php

namespace App\Console\Commands;

use App\Support\DemoViews;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Приводит записи сидеров в порядок после правок схемы.
 *
 * Две вещи, которые нельзя было сделать в самих сидерах:
 *
 * 1. Просмотры. Сидеры проставляли случайные числа, и в каталоге у вакансии
 *    стояло «372 просмотра», хотя её никто не открывал. Ноль оказался не лучше:
 *    у вакансии с четырнадцатью откликами просмотров не может быть ноль.
 *    Считаем от активности — сколько откликов пришло, столько человек точно
 *    заходили, плюс те, кто посмотрел и не откликнулся.
 *
 * 2. Языки вакансий. Поле появилось позже самих вакансий, поэтому у всех
 *    загруженных строк оно пустое, и блок «Требуемые языки» не показывался.
 *    Заполняем по тому же правилу, что и сидеры.
 *
 * Живые аккаунты не трогаем: у них просмотры настоящие.
 *
 *   php artisan demo:backfill --dry-run
 *   php artisan demo:backfill
 */
class BackfillDemoData extends Command
{
    protected $signature = 'demo:backfill {--dry-run : Только показать, ничего не меняя}';

    protected $description = 'Пересчитать просмотры по активности и заполнить языки у демо-вакансий';

    /** Почтовые домены наборов сидеров. */
    private const DOMAINS = ['%@tj.workio.tj', '%@edu.workio.tj', '%@pro.workio.tj'];

    /** Языки, которыми заполняем вакансии. */
    private const LANGUAGES = ['Тоҷикӣ', 'Тоҷикӣ, Русӣ', 'Тоҷикӣ, Русӣ, Англисӣ', 'Русӣ, Англисӣ'];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $userIds = DB::table('users')
            ->where(function ($q) {
                foreach (self::DOMAINS as $domain) {
                    $q->orWhere('email', 'like', $domain);
                }
            })
            ->pluck('id');

        if ($userIds->isEmpty()) {
            $this->warn('Записей сидеров не найдено.');

            return self::SUCCESS;
        }

        $employerIds = DB::table('employers')->whereIn('user_id', $userIds)->pluck('id');
        $applicantIds = DB::table('applicants')->whereIn('user_id', $userIds)->pluck('id');

        $vacancies = DB::table('vacancies')->whereIn('employer_id', $employerIds);
        $resumes = DB::table('resumes')->whereIn('applicant_id', $applicantIds);

        $withViews = (clone $vacancies)->count() + (clone $resumes)->count();

        $withoutLanguages = (clone $vacancies)
            ->where(fn ($q) => $q->whereNull('languages')->orWhere('languages', ''))
            ->count();

        $this->table(['Что правим', 'Сколько'], [
            ['пересчитаем просмотров', $withViews],
            ['заполним языков у вакансий', $withoutLanguages],
        ]);

        if ($dry) {
            $this->comment('Пробный запуск: ничего не изменено.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($employerIds, $applicantIds) {
            // просмотры считаем от откликов: у вакансии с четырнадцатью
            // откликами их не может быть ноль — каждый откликнувшийся
            // сначала открыл страницу
            $perVacancy = DB::table('vacancy_responses')
                ->selectRaw('vacancy_id, count(*) as c')
                ->groupBy('vacancy_id')
                ->pluck('c', 'vacancy_id');

            foreach (DB::table('vacancies')->whereIn('employer_id', $employerIds)->pluck('id') as $id) {
                DB::table('vacancies')->where('id', $id)
                    ->update(['views' => DemoViews::forResponses((int) ($perVacancy[$id] ?? 0))]);
            }

            $perResume = DB::table('resume_responses')
                ->selectRaw('resume_id, count(*) as c')
                ->groupBy('resume_id')
                ->pluck('c', 'resume_id');

            foreach (DB::table('resumes')->whereIn('applicant_id', $applicantIds)->pluck('id') as $id) {
                DB::table('resumes')->where('id', $id)
                    ->update(['views' => DemoViews::forResponses((int) ($perResume[$id] ?? 0))]);
            }

            // язык нужен не каждой вакансии: у каждой четвёртой оставляем пусто
            $rows = DB::table('vacancies')
                ->whereIn('employer_id', $employerIds)
                ->where(fn ($q) => $q->whereNull('languages')->orWhere('languages', ''))
                ->pluck('id');

            foreach ($rows as $i => $id) {
                DB::table('vacancies')->where('id', $id)->update([
                    'languages' => $i % 4 === 0 ? null : self::LANGUAGES[$i % count(self::LANGUAGES)],
                ]);
            }
        });

        $this->info('Готово: просмотры пересчитаны по откликам, языки у вакансий заполнены.');

        return self::SUCCESS;
    }
}
