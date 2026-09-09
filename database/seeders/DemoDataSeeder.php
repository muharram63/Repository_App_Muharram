<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\City;
use App\Models\Industry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Демо-данные: 200 соискателей и 200 работодателей с полностью
 * заполненными анкетами, резюме, вакансиями и откликами.
 */
class DemoDataSeeder extends Seeder
{
    private const PER_ROLE = 200;

    private array $maleNames = ['Далер', 'Фаррух', 'Бехруз', 'Сомон', 'Джамшед', 'Рустам', 'Азиз', 'Шухрат', 'Умед', 'Илхом', 'Максим', 'Артём', 'Дмитрий', 'Сергей', 'Иван'];
    private array $femaleNames = ['Мадина', 'Нилуфар', 'Зарина', 'Гулнора', 'Сабрина', 'Лола', 'Фируза', 'Мехри', 'Шахло', 'Анна', 'Мария', 'Екатерина', 'Ольга', 'Дарья', 'Ирина'];
    private array $surnames = ['Рахимов', 'Каримов', 'Назаров', 'Юсупов', 'Сафаров', 'Шарипов', 'Одинаев', 'Холов', 'Иванов', 'Петров', 'Смирнов', 'Кузнецов', 'Соколов', 'Мирзоев', 'Ашуров'];

    private array $professions = [
        'Backend-разработчик', 'Frontend-разработчик', 'Fullstack-разработчик', 'Мобильный разработчик',
        'QA-инженер', 'DevOps-инженер', 'Системный администратор', 'Аналитик данных',
        'UX/UI дизайнер', 'Графический дизайнер', 'Продуктовый менеджер', 'Проектный менеджер',
        'Бухгалтер', 'Экономист', 'Юрист', 'HR-менеджер', 'Менеджер по продажам',
        'Маркетолог', 'SMM-специалист', 'Копирайтер', 'Логист', 'Инженер-строитель',
        'Врач-терапевт', 'Учитель математики', 'Переводчик',
    ];

    private array $skillPool = [
        'PHP', 'Laravel', 'MySQL', 'JavaScript', 'Vue', 'React', 'TypeScript', 'Docker', 'Linux',
        'Git', 'Python', 'Django', 'Java', 'Kotlin', 'Swift', 'Figma', 'Photoshop', 'SQL',
        'Excel', '1С', 'Битрикс24', 'Google Ads', 'SEO', 'Управление командой', 'Переговоры',
    ];

    private array $languagePool = ['Таджикский', 'Русский', 'Английский', 'Узбекский', 'Персидский', 'Турецкий', 'Китайский'];

    private array $educations = [
        'Таджикский технический университет, программная инженерия',
        'Таджикский национальный университет, экономика',
        'Российско-Таджикский славянский университет, юриспруденция',
        'Таджикский государственный медицинский университет, лечебное дело',
        'Таджикский государственный педагогический университет, математика',
        'Технологический университет Таджикистана, информационные системы',
        'Колледж связи, техник-программист',
    ];

    private array $companyPrefixes = ['ООО', 'ЗАО', 'ИП', 'Группа компаний'];
    private array $companyNames = [
        'ТехноСофт', 'Памир Диджитал', 'Сомон Технолоджис', 'Ориён Групп', 'Зарафшон Трейд',
        'Вароруд Медиа', 'Истиклол Строй', 'Сино Фарм', 'Барака Логистик', 'Нур Банк Сервис',
        'Файз Маркет', 'Дилшод Агро', 'Хает Телеком', 'Ватан Финанс', 'Рушд Консалтинг',
        'Авеста Софт', 'Пойтахт Ритейл', 'Кухистон Турс', 'Мехр Энерго', 'Тоджикон Инжиниринг',
    ];

    private array $positions = ['HR-директор', 'Руководитель отдела кадров', 'Директор', 'Менеджер по персоналу', 'Основатель'];

    private array $employmentTypes = ['full-time', 'part-time', 'project_work', 'internship'];
    private array $schedules = ['full_day', 'flexible_schedule', 'remote_work'];
    private array $experiences = ['not', 'year', '3_years', '3-6years', 'more_6years'];
    private array $vacancyStatuses = ['active', 'active', 'active', 'inactive', 'closed', 'in_archived'];

    public function run(): void
    {
        $this->command->info('Готовим справочники…');
        $cityIds = $this->ensureCities();
        $categoryIds = $this->ensureCategories();
        $industryIds = $this->ensureIndustries($categoryIds);

        $password = Hash::make('password');
        $now = now();

        DB::transaction(function () use ($cityIds, $categoryIds, $industryIds, $password, $now) {
            $this->command->info('Создаём соискателей…');
            [$applicantIds, $resumeIds] = $this->seedApplicants($password, $now);

            $this->command->info('Создаём работодателей…');
            [$employerIds, $vacancyIds] = $this->seedEmployers($password, $now, $cityIds, $categoryIds, $industryIds);

            $this->command->info('Создаём отклики…');
            $this->seedResponses($applicantIds, $vacancyIds, $employerIds, $resumeIds, $now);
        });

        $this->command->info('Готово.');
    }

    // ==================== справочники ====================

    private function ensureCities(): array
    {
        $regions = [
            'город Душанбе', 'Согдийская область, город Худжанд', 'Хатлонская область, город Бохтар',
            'Хатлонская область, город Куляб', 'ГБАО, город Хорог', 'Согдийская область, город Истаравшан',
            'Согдийская область, город Канибадам', 'районы республиканского подчинения, город Гиссар',
            'районы республиканского подчинения, город Турсунзаде', 'Согдийская область, город Исфара',
        ];

        foreach ($regions as $region) {
            City::firstOrCreate(['region' => $region], ['country' => 'Таджикистан']);
        }

        return City::pluck('id')->all();
    }

    private function ensureCategories(): array
    {
        $names = ['IT и разработка', 'Продажи', 'Маркетинг', 'Финансы', 'Логистика',
            'Строительство', 'Медицина', 'Образование', 'Производство', 'Сфера услуг'];

        foreach ($names as $name) {
            Category::firstOrCreate(
                ['name' => $name],
                ['description' => 'Вакансии и компании направления «'.$name.'»', 'slug' => Str::slug($name)]
            );
        }

        return Category::pluck('id')->all();
    }

    private function ensureIndustries(array $categoryIds): array
    {
        $names = ['Программное обеспечение', 'Розничная торговля', 'Реклама и PR', 'Банки и финансы',
            'Перевозки и склад', 'Строительство и ремонт', 'Здравоохранение', 'Обучение и курсы',
            'Промышленность', 'Общественное питание'];

        foreach ($names as $i => $name) {
            Industry::firstOrCreate(
                ['name' => $name],
                [
                    'category_id' => $categoryIds[$i % count($categoryIds)],
                    'parent_id' => 0,
                    'description' => 'Отрасль «'.$name.'»',
                ]
            );
        }

        return Industry::pluck('id')->all();
    }

    // ==================== соискатели ====================

    private function seedApplicants(string $password, $now): array
    {
        $cities = ['Душанбе', 'Худжанд', 'Бохтар', 'Куляб', 'Хорог', 'Истаравшан', 'Канибадам', 'Гиссар', 'Турсунзаде', 'Исфара'];
        $streets = ['ул. Рудаки', 'ул. Айни', 'пр. Сомони', 'ул. Фирдавси', 'ул. Борбад', 'ул. Джаббор Расулов'];

        $users = [];
        for ($i = 1; $i <= self::PER_ROLE; $i++) {
            $isMale = $i % 2 === 0;
            $first = $isMale ? $this->pick($this->maleNames) : $this->pick($this->femaleNames);
            $last = $this->pick($this->surnames).($isMale ? '' : 'а');

            $users[] = [
                'name' => $first.' '.$last,
                'email' => 'applicant'.$i.'@workio.tj',
                'password' => $password,
                'role' => 'applicant',
                'status' => 'active',
                'date_register' => $now->copy()->subDays(random_int(0, 300))->format('Y-m-d'),
                'created_at' => $now->copy()->subDays(random_int(0, 300)),
                'updated_at' => $now,
            ];
        }
        DB::table('users')->insert($users);

        $userIds = DB::table('users')->where('role', 'applicant')
            ->where('email', 'like', 'applicant%@workio.tj')
            ->pluck('id', 'email');

        $applicants = [];
        for ($i = 1; $i <= self::PER_ROLE; $i++) {
            $isMale = $i % 2 === 0;
            $city = $this->pick($cities);

            $applicants[] = [
                'user_id' => $userIds['applicant'.$i.'@workio.tj'],
                'birth' => $now->copy()->subYears(random_int(20, 48))->subDays(random_int(0, 364))->format('Y-m-d'),
                'gender' => $isMale ? 'male' : 'female',
                'phone' => '+992 '.random_int(90, 93).' '.random_int(100, 999).'-'.random_int(10, 99).'-'.random_int(10, 99),
                'city' => $city,
                'address' => $this->pick($streets).', д. '.random_int(1, 120).', кв. '.random_int(1, 90),
                'education' => $this->pick($this->educations),
                'about_me' => 'Специалист из города '.$city.'. Ответственно отношусь к задачам, быстро учусь, ищу команду, где можно расти профессионально.',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('applicants')->insert($applicants);

        $applicantIds = DB::table('applicants')->whereIn('user_id', $userIds->values())->pluck('id')->all();

        // 1–2 резюме каждому
        $resumes = [];
        foreach ($applicantIds as $applicantId) {
            $count = random_int(1, 2);
            for ($r = 0; $r < $count; $r++) {
                $profession = $this->pick($this->professions);
                $years = random_int(0, 12);
                $skills = collect($this->skillPool)->shuffle()->take(random_int(3, 6))->implode(', ');

                $resumes[] = [
                    'applicant_id' => $applicantId,
                    'profession' => $profession,
                    'experience_years' => $years,
                    'desired_position' => $profession.($years > 5 ? ' (Senior)' : ($years > 2 ? ' (Middle)' : ' (Junior)')),
                    'desired_salary' => random_int(2, 25) * 1000,
                    'url_website' => 'https://github.com/user'.$applicantId.$r,
                    'skills' => $skills,
                    'languages' => collect($this->languagePool)->shuffle()->take(random_int(2, 3))->implode(', '),
                    'place_work' => $this->pick($this->companyNames),
                    'description' => 'Опыт работы '.$years.' лет по направлению «'.$profession.'». Участвовал в проектах разного масштаба, готов к задачам с высокой ответственностью.',
                    'documents' => null,
                    'views' => random_int(0, 400),
                    'created_at' => $now->copy()->subDays(random_int(0, 200)),
                    'updated_at' => $now,
                ];
            }
        }
        DB::table('resumes')->insert($resumes);

        $resumeIds = DB::table('resumes')->whereIn('applicant_id', $applicantIds)->pluck('id')->all();

        return [$applicantIds, $resumeIds];
    }

    // ==================== работодатели ====================

    private function seedEmployers(string $password, $now, array $cityIds, array $categoryIds, array $industryIds): array
    {
        $users = [];
        for ($i = 1; $i <= self::PER_ROLE; $i++) {
            $first = $i % 2 === 0 ? $this->pick($this->maleNames) : $this->pick($this->femaleNames);

            $users[] = [
                'name' => $first.' '.$this->pick($this->surnames),
                'email' => 'employer'.$i.'@workio.tj',
                'password' => $password,
                'role' => 'employer',
                'status' => 'active',
                'date_register' => $now->copy()->subDays(random_int(0, 300))->format('Y-m-d'),
                'created_at' => $now->copy()->subDays(random_int(0, 300)),
                'updated_at' => $now,
            ];
        }
        DB::table('users')->insert($users);

        $userIds = DB::table('users')->where('role', 'employer')
            ->where('email', 'like', 'employer%@workio.tj')
            ->pluck('id', 'email');

        $employers = [];
        for ($i = 1; $i <= self::PER_ROLE; $i++) {
            $name = $this->pick($this->companyPrefixes).' «'.$this->pick($this->companyNames).' '.$i.'»';

            $employers[] = [
                'logo' => null,
                'user_id' => $userIds['employer'.$i.'@workio.tj'],
                'company_name' => $name,
                'job' => $this->pick($this->positions),
                'email_company' => 'hr'.$i.'@workio.tj',
                'phone' => '+992 '.random_int(37, 44).' '.random_int(100, 999).'-'.random_int(10, 99).'-'.random_int(10, 99),
                'category_id' => $this->pick($categoryIds),
                'description' => 'Компания работает на рынке Таджикистана, развивает направление и регулярно расширяет команду. Открыты к специалистам разного уровня.',
                'city_id' => $this->pick($cityIds),
                'industry_id' => $this->pick($industryIds),
                'website_url' => 'https://company'.$i.'.tj',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('employers')->insert($employers);

        $employerRows = DB::table('employers')->whereIn('user_id', $userIds->values())->get(['id', 'city_id']);

        $vacancies = [];
        foreach ($employerRows as $employer) {
            $count = random_int(1, 4);
            for ($v = 0; $v < $count; $v++) {
                $profession = $this->pick($this->professions);
                $from = random_int(2, 18) * 1000;

                $vacancies[] = [
                    'title' => $profession,
                    'description' => "Мы ищем специалиста на позицию «{$profession}».\n\nЗадачи: развитие текущих проектов, работа в команде, соблюдение сроков.\nМы предлагаем: официальное оформление, обучение за счёт компании и понятную систему роста.",
                    'employer_id' => $employer->id,
                    'salary_from' => $from,
                    'salary_to' => $from + random_int(1, 8) * 1000,
                    'currency' => 'TJS',
                    'employment_type' => $this->pick($this->employmentTypes),
                    'work_schedule' => $this->pick($this->schedules),
                    'experience_required' => $this->pick($this->experiences),
                    'skill' => collect($this->skillPool)->shuffle()->take(random_int(3, 5))->implode(', '),
                    'status' => $this->pick($this->vacancyStatuses),
                    'views' => random_int(0, 800),
                    'city_id' => $employer->city_id,
                    'created_by' => $employer->id,
                    'created_at' => $now->copy()->subDays(random_int(0, 180)),
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($vacancies, 200) as $chunk) {
            DB::table('vacancies')->insert($chunk);
        }

        $vacancyIds = DB::table('vacancies')->whereIn('employer_id', $employerRows->pluck('id'))->pluck('id')->all();

        return [$employerRows->pluck('id')->all(), $vacancyIds];
    }

    // ==================== отклики ====================

    private function seedResponses(array $applicantIds, array $vacancyIds, array $employerIds, array $resumeIds, $now): void
    {
        $statuses = ['new', 'new', 'viewed', 'accepted', 'rejected'];

        $pairs = [];
        for ($i = 0; $i < 600; $i++) {
            $applicantId = $this->pick($applicantIds);
            $vacancyId = $this->pick($vacancyIds);
            $key = $applicantId.'-'.$vacancyId;

            if (isset($pairs[$key])) {
                continue;
            }

            $pairs[$key] = [
                'applicant_id' => $applicantId,
                'vacancy_id' => $vacancyId,
                'message' => 'Здравствуйте! Мой опыт соответствует требованиям вакансии, готов обсудить детали.',
                'status' => $this->pick($statuses),
                'created_at' => $now->copy()->subDays(random_int(0, 60)),
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk(array_values($pairs), 200) as $chunk) {
            DB::table('vacancy_responses')->insert($chunk);
        }

        $invitePairs = [];
        for ($i = 0; $i < 300; $i++) {
            $employerId = $this->pick($employerIds);
            $resumeId = $this->pick($resumeIds);
            $key = $employerId.'-'.$resumeId;

            if (isset($invitePairs[$key])) {
                continue;
            }

            $invitePairs[$key] = [
                'employer_id' => $employerId,
                'resume_id' => $resumeId,
                'message' => 'Здравствуйте! Ваше резюме нам подходит, приглашаем обсудить вакансию.',
                'status' => $this->pick($statuses),
                'created_at' => $now->copy()->subDays(random_int(0, 60)),
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk(array_values($invitePairs), 200) as $chunk) {
            DB::table('resume_responses')->insert($chunk);
        }
    }

    private function pick(array $items)
    {
        return $items[array_rand($items)];
    }
}
