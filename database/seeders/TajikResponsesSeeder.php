<?php

namespace Database\Seeders;

use App\Support\DemoViews;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Отклики, приглашения и собеседования между уже загруженными таджикскими
 * соискателями и компаниями.
 *
 * Нужен, чтобы витрина показывала живую площадку: на странице статистики
 * «вышли на работу» — это принятые отклики в обе стороны, и без них цифра
 * держалась на единицах. Считается она по реальным строкам в базе, поэтому
 * поднять её можно только настоящими записями, а не правкой счётчика.
 *
 * Работает исключительно с записями таджикских сидеров: личные аккаунты
 * и их отклики не трогает.
 *
 *   php artisan db:seed --class=TajikResponsesSeeder
 *
 * Повторный запуск безопасен: сидер видит собственные записи и выходит.
 */
class TajikResponsesSeeder extends Seeder
{
    /** Почтовые домены наборов, с которыми работаем. */
    private const DOMAINS = ['%@tj.workio.tj', '%@edu.workio.tj', '%@pro.workio.tj'];

    /**
     * Сколько откликов какого статуса создаём.
     *
     * Принятых заметно меньше остальных: так и бывает на реальной площадке,
     * где большинство откликов остаются без ответа или получают отказ.
     */
    private const VACANCY_MIX = ['accepted' => 14, 'rejected' => 26, 'viewed' => 35, 'new' => 35];

    private const RESUME_MIX = ['accepted' => 12, 'rejected' => 12, 'viewed' => 16, 'new' => 15];

    /** Сколько принятых откликов дошли до собеседования. */
    private const INTERVIEWS = 12;

    private array $applicantMessages = [
        'Салом! Ба ин ҷойи корӣ таваҷҷуҳ дорам, таҷрибаи мувофиқ дорам.',
        'Резюмеи ман замима шудааст. Омодаам ба мусоҳиба дар вақти ба шумо қулай.',
        'Дар ин самт таҷрибаи амалӣ дорам ва мехоҳам дар дастаи шумо кор кунам.',
        'Бо шароити пешниҳодшуда розӣ ҳастам, интизори ҷавоби шумо мемонам.',
        'Малакаҳои дар эълон зикршударо дорам, метавонам зуд ба кор шурӯъ кунам.',
    ];

    private array $employerMessages = [
        'Салом! Резюмеи шумо ба мо писанд омад, мехоҳем сӯҳбат кунем.',
        'Таҷрибаи шумо ба ҷойи холии мо мувофиқ аст. Вақти мусоҳибаро муайян кунем?',
        'Мо номзадҳоро баррасӣ карда истодаем, резюмеи шумо диққати моро ҷалб кард.',
        'Пешниҳод мекунем, ки дар бораи ҳамкорӣ муфассал сӯҳбат намоем.',
    ];

    public function run(): void
    {
        [$applicantIds, $employerIds, $vacancyIds, $resumeIds] = $this->seededSets();

        if ($applicantIds->isEmpty() || $vacancyIds->isEmpty()) {
            $this->command->warn('Таджикские данные не загружены — сначала запустите TajikDataSeeder.');

            return;
        }

        if ($this->alreadySeeded($applicantIds, $employerIds)) {
            $this->command->warn('Отклики уже созданы — повторно не добавляю.');

            return;
        }

        $now = now();

        DB::transaction(function () use ($applicantIds, $employerIds, $vacancyIds, $resumeIds, $now) {
            $this->command->info('Создаём отклики соискателей…');
            $vacancyRows = $this->seedVacancyResponses($applicantIds, $vacancyIds, $now);

            $this->command->info('Создаём приглашения компаний…');
            $this->seedResumeResponses($employerIds, $resumeIds, $now);

            $this->command->info('Создаём собеседования по принятым откликам…');
            $this->seedInterviews($vacancyRows, $now);

            $this->command->info('Считаем просмотры по активности…');
            $this->seedViews($employerIds, $applicantIds);
        });

        $accepted = self::VACANCY_MIX['accepted'] + self::RESUME_MIX['accepted'];
        $this->command->info("Готово: принятых откликов добавлено {$accepted}.");
    }

    /**
     * Просмотры по числу откликов: каждый откликнувшийся сначала открыл
     * страницу, плюс те, кто посмотрел и прошёл мимо. Выдумывать число
     * незачем — оно выводится из того, что уже есть в базе.
     */
    private function seedViews($employerIds, $applicantIds): void
    {
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
    }

    /**
     * Соискатели, компании, вакансии и резюме из таджикских наборов.
     *
     * @return array{0:\Illuminate\Support\Collection,1:\Illuminate\Support\Collection,2:\Illuminate\Support\Collection,3:\Illuminate\Support\Collection}
     */
    private function seededSets(): array
    {
        $userIds = DB::table('users')
            ->where(function ($q) {
                foreach (self::DOMAINS as $domain) {
                    $q->orWhere('email', 'like', $domain);
                }
            })
            ->pluck('id');

        $applicantIds = DB::table('applicants')->whereIn('user_id', $userIds)->pluck('id');
        $employerIds = DB::table('employers')->whereIn('user_id', $userIds)->pluck('id');

        return [
            $applicantIds,
            $employerIds,
            DB::table('vacancies')->whereIn('employer_id', $employerIds)->pluck('id'),
            DB::table('resumes')->whereIn('applicant_id', $applicantIds)->pluck('id'),
        ];
    }

    private function alreadySeeded($applicantIds, $employerIds): bool
    {
        return DB::table('vacancy_responses')->whereIn('applicant_id', $applicantIds)->exists()
            || DB::table('resume_responses')->whereIn('employer_id', $employerIds)->exists();
    }

    /**
     * Отклики соискателей на вакансии.
     *
     * @return array<int,array{applicant_id:int,vacancy_id:int}> принятые пары
     */
    private function seedVacancyResponses($applicantIds, $vacancyIds, $now): array
    {
        $statuses = $this->statusQueue(self::VACANCY_MIX);
        $pairs = $this->uniquePairs($applicantIds, $vacancyIds, count($statuses));

        $rows = [];
        $accepted = [];

        foreach ($pairs as $i => [$applicantId, $vacancyId]) {
            $status = $statuses[$i];

            // дата отклика и дата решения — разные: решение приходит
            // через несколько дней, и в списке трудоустройств видна именно она
            $sent = $now->copy()->subDays(random_int(5, 150));
            $answered = $status === 'new'
                ? $sent
                : $sent->copy()->addDays(random_int(1, 21))->min($now);

            $rows[] = [
                'applicant_id' => $applicantId,
                'vacancy_id' => $vacancyId,
                'message' => $this->pick($this->applicantMessages),
                'status' => $status,
                'created_at' => $sent,
                'updated_at' => $answered,
            ];

            if ($status === 'accepted') {
                $accepted[] = ['applicant_id' => $applicantId, 'vacancy_id' => $vacancyId];
            }
        }

        DB::table('vacancy_responses')->insert($rows);

        return $accepted;
    }

    /** Приглашения компаний по резюме. */
    private function seedResumeResponses($employerIds, $resumeIds, $now): void
    {
        $statuses = $this->statusQueue(self::RESUME_MIX);
        $pairs = $this->uniquePairs($employerIds, $resumeIds, count($statuses));

        $rows = [];
        foreach ($pairs as $i => [$employerId, $resumeId]) {
            $status = $statuses[$i];
            $sent = $now->copy()->subDays(random_int(5, 150));
            $answered = $status === 'new'
                ? $sent
                : $sent->copy()->addDays(random_int(1, 21))->min($now);

            $rows[] = [
                'employer_id' => $employerId,
                'resume_id' => $resumeId,
                'message' => $this->pick($this->employerMessages),
                'status' => $status,
                'created_at' => $sent,
                'updated_at' => $answered,
            ];
        }

        DB::table('resume_responses')->insert($rows);
    }

    /**
     * Собеседования по принятым откликам: приняли — значит, разговаривали.
     */
    private function seedInterviews(array $acceptedPairs, $now): void
    {
        $rows = [];

        foreach (array_slice($acceptedPairs, 0, self::INTERVIEWS) as $n => $pair) {
            $vacancy = DB::table('vacancies')->where('id', $pair['vacancy_id'])->first(['employer_id']);

            if (! $vacancy) {
                continue;
            }

            $past = $n % 3 !== 0;

            $rows[] = [
                'employer_id' => $vacancy->employer_id,
                'applicant_id' => $pair['applicant_id'],
                'vacancy_id' => $pair['vacancy_id'],
                'scheduled_at' => $past
                    ? $now->copy()->subDays(random_int(3, 60))
                    : $now->copy()->addDays(random_int(1, 14)),
                'duration_minutes' => 30,
                // комната уникальна по индексу, поэтому берём случайную строку
                'room' => 'workio-'.Str::lower(Str::random(12)),
                'note' => 'Мусоҳиба аз рӯи ҷавоби қабулшуда.',
                'status' => $past ? 'finished' : 'confirmed',
                'answered_at' => $now->copy()->subDays(random_int(1, 3)),
                'created_at' => $now->copy()->subDays(random_int(4, 70)),
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('interviews')->insert($rows);
        }
    }

    /**
     * Очередь статусов: сначала собираем нужное количество каждого,
     * потом перемешиваем, чтобы принятые не шли подряд по датам.
     *
     * @return array<int,string>
     */
    private function statusQueue(array $mix): array
    {
        $queue = [];

        foreach ($mix as $status => $count) {
            $queue = array_merge($queue, array_fill(0, $count, $status));
        }

        shuffle($queue);

        return $queue;
    }

    /**
     * Неповторяющиеся пары: на обе таблицы стоит уникальный индекс,
     * и повтор пары уронил бы вставку.
     *
     * @return array<int,array{0:int,1:int}>
     */
    private function uniquePairs($left, $right, int $need): array
    {
        $pairs = [];

        foreach ($left as $a) {
            foreach ($right as $b) {
                $pairs[] = [$a, $b];
            }
        }

        shuffle($pairs);

        return array_slice($pairs, 0, $need);
    }

    private function pick(array $values)
    {
        return $values[array_rand($values)];
    }
}
