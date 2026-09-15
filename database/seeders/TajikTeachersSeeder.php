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
 * Учителя: 50 вакансий и 50 резюме на таджикском языке.
 *
 * Предметы раздаются по кругу от начала списка, поэтому каждый школьный
 * предмет появляется и в вакансиях, и в резюме хотя бы один раз — иначе
 * при случайной выборке половина предметов просто не попала бы в выдачу.
 *
 * Учебные заведения заводятся отдельными работодателями: школа нанимает
 * нескольких учителей сразу, поэтому на 25 заведений приходится 50 вакансий.
 *
 * Запускается отдельно:
 *   php artisan db:seed --class=TajikTeachersSeeder
 *
 * Повторный запуск безопасен: свои записи сидер узнаёт по префиксу почты.
 */
class TajikTeachersSeeder extends Seeder
{
    private const VACANCIES = 50;

    private const RESUMES = 50;

    /** Префикс почты — по нему сидер узнаёт собственные записи. */
    private const MAIL = '@edu.workio.tj';

    /**
     * Школьные и вузовские предметы. Список ведёт раздачу: сначала каждый
     * предмет получает свою вакансию и своё резюме, потом идёт второй круг.
     *
     * @var array<int,string>
     */
    private array $subjects = [
        'Забони тоҷикӣ ва адабиёт',
        'Забони русӣ',
        'Забони англисӣ',
        'Забони арабӣ',
        'Забони ӯзбекӣ',
        'Забони форсӣ',
        'Математика',
        'Алгебра',
        'Геометрия',
        'Физика',
        'Химия',
        'Биология',
        'Ҷуғрофия',
        'Астрономия',
        'Экология',
        'Таърихи халқи тоҷик',
        'Таърихи умумӣ',
        'Ҳуқуқ',
        'Иқтисод',
        'Информатика',
        'Технологияи иттилоотӣ',
        'Тарбияи ҷисмонӣ',
        'Мусиқӣ',
        'Санъати тасвирӣ',
        'Технология ва меҳнат',
        'Одобу ахлоқ',
        'Психология',
        'Мантиқ',
        'Омодагии ибтидоии ҳарбӣ',
        'Синфҳои ибтидоӣ',
    ];

    /**
     * Учебные заведения: название и город.
     *
     * @var array<int,array{0:string,1:string}>
     */
    private array $schools = [
        ['Литсейи Президентӣ барои хонандагони боистеъдод', 'Душанбе'],
        ['Мактаби миёнаи умумии №1', 'Душанбе'],
        ['Гимназияи №2', 'Душанбе'],
        ['Литсейи №3', 'Душанбе'],
        ['Мактаб-интернати ш. Душанбе', 'Душанбе'],
        ['Коллеҷи омӯзгории ш. Душанбе', 'Душанбе'],
        ['Донишгоҳи давлатии омӯзгории Тоҷикистон ба номи С. Айнӣ', 'Душанбе'],
        ['Маркази таълимии «Дониш»', 'Душанбе'],
        ['Мактаби миёнаи умумии №5', 'Хуҷанд'],
        ['Гимназияи №8', 'Хуҷанд'],
        ['Донишгоҳи давлатии Хуҷанд ба номи Б. Ғафуров', 'Хуҷанд'],
        ['Маркази таълимии «Илм»', 'Хуҷанд'],
        ['Мактаби миёнаи умумии №12', 'Бохтар'],
        ['Литсейи №4', 'Бохтар'],
        ['Мактаби байналмилалии «Хатлон»', 'Бохтар'],
        ['Мактаби миёнаи умумии №7', 'Кӯлоб'],
        ['Гимназияи №1', 'Кӯлоб'],
        ['Коллеҷи омӯзгории ш. Кӯлоб', 'Кӯлоб'],
        ['Мактаби миёнаи умумии №2', 'Хоруғ'],
        ['Мактаби миёнаи умумии №9', 'Истаравшан'],
        ['Гимназияи №6', 'Конибодом'],
        ['Мактаби миёнаи умумии №14', 'Ҳисор'],
        ['Мактаби миёнаи умумии №3', 'Турсунзода'],
        ['Мактаби миёнаи умумии №11', 'Панҷакент'],
        ['Мактаби миёнаи умумии №10', 'Норак'],
    ];

    /** Таджикский город -> запись справочника, чтобы не плодить дубли. */
    private array $cityMap = [
        'Душанбе' => 'город Душанбе',
        'Хуҷанд' => 'Согдийская область, город Худжанд',
        'Бохтар' => 'Хатлонская область, город Бохтар',
        'Кӯлоб' => 'Хатлонская область, город Куляб',
        'Хоруғ' => 'ГБАО, город Хорог',
        'Истаравшан' => 'Согдийская область, город Истаравшан',
        'Конибодом' => 'Согдийская область, город Канибадам',
        'Ҳисор' => 'районы республиканского подчинения, город Гиссар',
        'Турсунзода' => 'районы республиканского подчинения, город Турсунзаде',
        'Панҷакент' => 'Согдийская область, город Пенджикент',
        'Норак' => 'Хатлонская область, город Нурек',
    ];

    private array $maleNames = ['Далер', 'Фаррух', 'Беҳрӯз', 'Сомон', 'Ҷамшед', 'Рустам', 'Азиз', 'Шуҳрат', 'Умед', 'Илҳом', 'Фирдавс', 'Хуршед', 'Меҳроҷ', 'Наврӯз', 'Сироҷиддин'];

    private array $femaleNames = ['Мадина', 'Нилуфар', 'Зарина', 'Гулнора', 'Сабрина', 'Лола', 'Фирӯза', 'Меҳрӣ', 'Шаҳло', 'Дилноза', 'Мунира', 'Ситора', 'Парвина', 'Робия', 'Зебо'];

    private array $surnames = ['Раҳимов', 'Каримов', 'Назаров', 'Юсупов', 'Сафаров', 'Шарипов', 'Одинаев', 'Холов', 'Мирзоев', 'Ашӯров', 'Ҷӯраев', 'Қодиров', 'Саидов', 'Ҳакимов', 'Абдуллоев'];

    private array $schoolPositions = ['Директор', 'Муовини директор оид ба таълим', 'Мудири шӯъбаи кадрҳо', 'Методисти калон'];

    /** «Муаллим» и «омӯзгор» — равные слова, чередуем ради живости текста. */
    private array $teacherWords = ['Муаллими', 'Омӯзгори'];

    private array $teachingSkills = [
        'Методикаи таълим',
        'Таҳияи нақшаи дарс',
        'Идоракунии синф',
        'Санҷиши дониши хонандагон',
        'Омодагӣ ба олимпиада',
        'Таълими фосилавӣ',
        'Кор бо волидайн',
        'Технологияҳои интерактивӣ',
        'Таҳияи тестҳо',
        'Тарбияи маънавӣ',
        'Microsoft Office',
        'Тахтаи электронӣ',
    ];

    private array $languagePool = ['Тоҷикӣ', 'Русӣ', 'Англисӣ', 'Форсӣ', 'Ӯзбекӣ', 'Арабӣ'];

    private array $educations = [
        'Донишгоҳи давлатии омӯзгории Тоҷикистон ба номи С. Айнӣ, факултети математика',
        'Донишгоҳи давлатии омӯзгории Тоҷикистон ба номи С. Айнӣ, факултети филология',
        'Донишгоҳи миллии Тоҷикистон, филологияи тоҷик',
        'Донишгоҳи давлатии Хуҷанд ба номи Б. Ғафуров, физика ва математика',
        'Донишкадаи давлатии забонҳои Тоҷикистон ба номи С. Улуғзода, забони англисӣ',
        'Донишгоҳи славянии Русияву Тоҷикистон, забон ва адабиёти русӣ',
        'Коллеҷи омӯзгории ш. Кӯлоб, синфҳои ибтидоӣ',
        'Донишгоҳи давлатии тиббии Тоҷикистон, биология',
    ];

    private array $streets = ['кӯчаи Рӯдакӣ', 'кӯчаи Айнӣ', 'хиёбони Сомонӣ', 'кӯчаи Фирдавсӣ', 'кӯчаи Борбад', 'кӯчаи Ҷаббор Расулов'];

    private array $schedules = ['full_day', 'full_day', 'flexible_schedule'];

    private array $experiences = ['not', 'year', '3_years', '3-6years', 'more_6years'];

    public function run(): void
    {
        if (DB::table('users')->where('email', 'like', '%'.self::MAIL)->exists()) {
            $this->command->warn('Данные по учителям уже загружены — повторно не создаю.');

            return;
        }

        $this->command->info('Готовим справочники…');
        $cityIds = $this->ensureCities();
        [$categoryId, $industryId] = $this->ensureEducationRefs();

        $password = Hash::make('password');
        $now = now();

        DB::transaction(function () use ($cityIds, $categoryId, $industryId, $password, $now) {
            $this->command->info('Создаём учебные заведения и 50 вакансий…');
            $this->seedSchools($cityIds, $categoryId, $industryId, $password, $now);

            $this->command->info('Создаём 50 резюме учителей…');
            $this->seedTeachers($password, $now);
        });

        $this->command->info('Готово: 50 вакансий и 50 резюме учителей на таджикском.');
    }

    // ==================== справочники ====================

    /** @return array<string,int> таджикское название -> id записи */
    private function ensureCities(): array
    {
        $ids = [];

        foreach ($this->cityMap as $tajik => $region) {
            $ids[$tajik] = City::firstOrCreate(
                ['region' => $region],
                ['country' => 'Таджикистан']
            )->id;
        }

        return $ids;
    }

    /** @return array{0:int,1:int} id категории и отрасли */
    private function ensureEducationRefs(): array
    {
        $category = Category::firstOrCreate(
            ['name' => 'Образование и наука'],
            ['description' => 'Вакансии и компании направления «Образование и наука»', 'slug' => Str::slug('Образование')]
        );

        $industry = Industry::firstOrCreate(
            ['name' => 'Обучение и курсы'],
            ['category_id' => $category->id, 'parent_id' => 0, 'description' => 'Отрасль «Обучение и курсы»']
        );

        return [$category->id, $industry->id];
    }

    // ==================== школы и вакансии ====================

    private function seedSchools(array $cityIds, int $categoryId, int $industryId, string $password, $now): void
    {
        $users = [];
        foreach ($this->schools as $i => [$name, $city]) {
            $first = $i % 2 === 0 ? $this->pick($this->femaleNames) : $this->pick($this->maleNames);

            $users[] = [
                'name' => $first.' '.$this->pick($this->surnames),
                'email' => 'school'.($i + 1).self::MAIL,
                'password' => $password,
                'role' => 'employer',
                'status' => 'active',
                'theme' => 'light',
                'date_register' => $now->copy()->subDays(random_int(30, 400))->format('Y-m-d'),
                'created_at' => $now->copy()->subDays(random_int(30, 400)),
                'updated_at' => $now,
            ];
        }
        DB::table('users')->insert($users);

        $userIds = DB::table('users')
            ->where('email', 'like', 'school%'.self::MAIL)
            ->pluck('id', 'email');

        $employers = [];
        foreach ($this->schools as $i => [$name, $city]) {
            $employers[] = [
                'logo' => null,
                'user_id' => $userIds['school'.($i + 1).self::MAIL],
                'company_name' => $name,
                'job' => $this->pick($this->schoolPositions),
                'email_company' => 'edu'.($i + 1).self::MAIL,
                'phone' => '+992 '.random_int(37, 44).' '.random_int(100, 999).'-'.random_int(10, 99).'-'.random_int(10, 99),
                'category_id' => $categoryId,
                'description' => '«'.$name.'» дар шаҳри '.$city.' фаъолият мекунад. '
                    .'Барои омӯзгорони фанҳои гуногун ҷойи корӣ, сарбории мувофиқ ва имкони такмили ихтисос пешниҳод мешавад.',
                'city_id' => $cityIds[$city],
                'industry_id' => $industryId,
                'website_url' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('employers')->insert($employers);

        $employerIds = DB::table('employers')
            ->whereIn('user_id', $userIds->values())
            ->pluck('id', 'user_id');

        // 50 вакансий на 25 заведений: предмет берём по кругу от начала списка,
        // чтобы ни один предмет не остался без вакансии
        $vacancies = [];
        for ($n = 0; $n < self::VACANCIES; $n++) {
            [$schoolName, $city] = $this->schools[$n % count($this->schools)];
            $employerId = $employerIds[$userIds['school'.(($n % count($this->schools)) + 1).self::MAIL]];

            $subject = $this->subjects[$n % count($this->subjects)];
            $title = $this->pick($this->teacherWords).' '.$this->subjectForTitle($subject);
            $from = random_int(15, 30) * 100;

            $vacancies[] = [
                'title' => $title,
                'description' => $this->vacancyDescription($schoolName, $city, $subject),
                'employer_id' => $employerId,
                'salary_from' => $from,
                'salary_to' => $from + random_int(5, 20) * 100,
                'currency' => 'TJS',
                'employment_type' => $n % 7 === 0 ? 'part-time' : 'full-time',
                'work_schedule' => $this->pick($this->schedules),
                'experience_required' => $this->pick($this->experiences),
                'skill' => collect($this->teachingSkills)->shuffle()->take(random_int(3, 5))->implode(', '),
                // учителю язык преподавания важен почти всегда
                'languages' => collect($this->languagePool)->shuffle()->take(random_int(1, 2))->implode(', '),
                'status' => 'active',
                // просмотры копятся сами при открытии страницы
                'views' => 0,
                'city_id' => $cityIds[$city],
                'created_by' => $employerId,
                'created_at' => $now->copy()->subDays(random_int(0, 120)),
                'updated_at' => $now,
            ];
        }
        DB::table('vacancies')->insert($vacancies);
    }

    /**
     * «Муаллими математика», но «Муаллими фанни Забони тоҷикӣ ва адабиёт»:
     * длинные названия предметов без слова «фанни» читаются коряво.
     */
    private function subjectForTitle(string $subject): string
    {
        return mb_strpos($subject, ' ') === false ? $subject : 'фанни «'.$subject.'»';
    }

    private function vacancyDescription(string $school, string $city, string $subject): string
    {
        return 'Ба «'.$school.'» (ш. '.$city.') омӯзгори фанни «'.$subject.'» лозим аст.'
            ."\n\nВазифаҳо:"
            ."\n— гузаронидани дарсҳо мутобиқи нақшаи таълимӣ;"
            ."\n— таҳияи маводи дарсӣ ва санҷиши дониши хонандагон;"
            ."\n— кор бо волидайн ва иштирок дар чорабиниҳои таълимгоҳ."
            ."\n\nТалабот:"
            ."\n— маълумоти олии омӯзгорӣ аз рӯи фан;"
            ."\n— донистани забони тоҷикӣ дар сатҳи касбӣ."
            ."\n\nМо пешниҳод мекунем:"
            ."\n— сарбории мувофиқ ва ҷадвали устувор;"
            ."\n— музди меҳнати саривақтӣ ва рухсатии тобистона;"
            ."\n— имкони такмили ихтисос.";
    }

    // ==================== учителя и резюме ====================

    private function seedTeachers(string $password, $now): void
    {
        $cities = array_keys($this->cityMap);

        $users = [];
        for ($i = 1; $i <= self::RESUMES; $i++) {
            $isMale = $i % 3 === 0;
            $first = $isMale ? $this->pick($this->maleNames) : $this->pick($this->femaleNames);
            $last = $this->pick($this->surnames).($isMale ? '' : 'а');

            $users[] = [
                'name' => $first.' '.$last,
                'email' => 'teacher'.$i.self::MAIL,
                'password' => $password,
                'role' => 'applicant',
                'status' => 'active',
                'theme' => 'light',
                'date_register' => $now->copy()->subDays(random_int(10, 380))->format('Y-m-d'),
                'created_at' => $now->copy()->subDays(random_int(10, 380)),
                'updated_at' => $now,
            ];
        }
        DB::table('users')->insert($users);

        $userIds = DB::table('users')
            ->where('email', 'like', 'teacher%'.self::MAIL)
            ->pluck('id', 'email');

        $applicants = [];
        for ($i = 1; $i <= self::RESUMES; $i++) {
            $isMale = $i % 3 === 0;
            $city = $this->pick($cities);
            $subject = $this->subjects[($i - 1) % count($this->subjects)];

            $applicants[] = [
                'user_id' => $userIds['teacher'.$i.self::MAIL],
                'birth' => $now->copy()->subYears(random_int(23, 55))->subDays(random_int(0, 364))->format('Y-m-d'),
                'gender' => $isMale ? 'male' : 'female',
                'phone' => '+992 '.random_int(90, 93).' '.random_int(100, 999).'-'.random_int(10, 99).'-'.random_int(10, 99),
                'city' => $city,
                'address' => $this->pick($this->streets).', хонаи '.random_int(1, 120).', ҳуҷраи '.random_int(1, 90),
                'education' => $this->pick($this->educations),
                'about_me' => 'Омӯзгори фанни «'.$subject.'» аз шаҳри '.$city.'. '
                    .'Дарсро содда ва фаҳмо шарҳ медиҳам, ба ҳар хонанда алоҳида диққат медиҳам '
                    .'ва хонандагонро ба олимпиадаву озмунҳо омода месозам.',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('applicants')->insert($applicants);

        $applicantIds = DB::table('applicants')
            ->whereIn('user_id', $userIds->values())
            ->pluck('id')
            ->all();

        // одно резюме на учителя; предмет тот же, что в анкете
        $resumes = [];
        foreach (array_values($applicantIds) as $n => $applicantId) {
            $subject = $this->subjects[$n % count($this->subjects)];
            $profession = $this->pick($this->teacherWords).' '.$this->subjectForTitle($subject);
            $years = random_int(0, 30);

            $resumes[] = [
                'applicant_id' => $applicantId,
                'profession' => $profession,
                'experience_years' => $years,
                'desired_position' => $profession,
                'desired_salary' => random_int(15, 40) * 100,
                'url_website' => null,
                'skills' => collect($this->teachingSkills)->shuffle()->take(random_int(4, 6))->implode(', '),
                'languages' => collect($this->languagePool)->shuffle()->take(random_int(2, 3))->implode(', '),
                'place_work' => $this->pick(array_column($this->schools, 0)),
                'description' => 'Омӯзгори фанни «'.$subject.'» бо таҷрибаи '.$years.' сол. '
                    .'Дарсҳоро мутобиқи нақшаи таълимӣ мегузаронам, маводи худро таҳия мекунам '
                    .'ва дониши хонандагонро мунтазам месанҷам. Бо синфҳои гуногун кор кардаам.',
                'documents' => null,
                'style' => 'classic',
                // просмотры копятся сами при открытии страницы
                'views' => 0,
                'created_at' => $now->copy()->subDays(random_int(0, 150)),
                'updated_at' => $now,
            ];
        }
        DB::table('resumes')->insert($resumes);
    }

    private function pick(array $values)
    {
        return $values[array_rand($values)];
    }
}
