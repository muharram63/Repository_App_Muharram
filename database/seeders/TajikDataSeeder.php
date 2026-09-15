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
 * Демо-данные на таджикском языке: 50 компаний с вакансиями и 50 резюме.
 *
 * Названия компаний взяты из реального делового ландшафта Таджикистана,
 * а города — только таджикские, чтобы каталог не выглядел абстрактным.
 * Сами записи демонстрационные: контакты ведут на служебный домен
 * workio.tj, описания намеренно общие и ничего не утверждают о реальных
 * организациях — это витрина платформы, а не справочник о компаниях.
 *
 * Запускается отдельно и в общий DatabaseSeeder не входит: на тестах
 * лишняя сотня пользователей только замедляла бы прогон.
 *
 *   php artisan db:seed --class=TajikDataSeeder
 *
 * Повторный запуск безопасен: сидер узнаёт свои записи по префиксу почты
 * и молча выходит, а не создаёт дубли.
 */
class TajikDataSeeder extends Seeder
{
    /** Компаний столько, сколько в списке: все они — существующие организации. */
    private const COMPANIES = 41;

    /**
     * Вакансий и резюме по-прежнему 50: число компаний ограничено реальными
     * названиями, поэтому часть из них публикует по две вакансии.
     */
    private const VACANCIES = 50;

    private const RESUMES = 50;

    /** Префикс почты — по нему сидер узнаёт собственные записи. */
    private const MAIL = '@tj.workio.tj';

    /**
     * Компании: название, отрасль и город. Город указывается по-таджикски,
     * а в справочник городов подставляется существующая запись — чтобы
     * каталог не оброс дублями вроде «Худжанд» и «Хуҷанд» одновременно.
     *
     * @var array<int,array{0:string,1:string,2:string}>
     */
    private array $companies = [
        // банки, микрофинансы и страхование
        ['Алиф Банк', 'банк', 'Душанбе'],
        ['Банки Эсхата', 'банк', 'Хуҷанд'],
        ['Амонатбонк', 'банк', 'Душанбе'],
        ['Ориёнбонк', 'банк', 'Душанбе'],
        ['Спитамен Банк', 'банк', 'Душанбе'],
        ['Банки Арванд', 'банк', 'Хуҷанд'],
        ['Имон Интернешнл', 'банк', 'Хуҷанд'],
        ['Хумо', 'банк', 'Душанбе'],
        ['Душанбе Сити Банк', 'банк', 'Душанбе'],
        ['Тавҳидбонк', 'банк', 'Душанбе'],
        ['Тоҷиксодиротбонк', 'банк', 'Душанбе'],
        ['Сомон Суғурта', 'банк', 'Душанбе'],

        // связь и ИТ
        ['Tcell', 'ит', 'Душанбе'],
        ['Мегафон Тоҷикистон', 'ит', 'Душанбе'],
        ['Вавилон-М', 'ит', 'Душанбе'],
        ['ЗетМобайл', 'ит', 'Душанбе'],
        ['Тоҷиктелеком', 'ит', 'Душанбе'],
        ['Интерком', 'ит', 'Хуҷанд'],
        ['Zypl.ai', 'ит', 'Душанбе'],

        // торговля
        ['Фаровон', 'ритейл', 'Душанбе'],
        ['Арча', 'ритейл', 'Душанбе'],
        ['Пойтахт', 'ритейл', 'Душанбе'],
        ['Ориён', 'ритейл', 'Хуҷанд'],
        ['Тоҷикматлубот', 'ритейл', 'Душанбе'],

        // промышленность
        ['ТАЛКО', 'промышленность', 'Турсунзода'],
        ['ТАЛКО Голд', 'промышленность', 'Турсунзода'],
        ['Зарафшон', 'промышленность', 'Панҷакент'],
        ['Хуаксин Ғаюр Семент', 'промышленность', 'Ҳисор'],
        ['Тоҷикцемент', 'промышленность', 'Душанбе'],
        ['Оби Зулол', 'промышленность', 'Душанбе'],
        ['Нобовар', 'промышленность', 'Душанбе'],

        // энергетика
        ['Барқи Тоҷик', 'энергетика', 'Душанбе'],
        ['Помир Энержӣ', 'энергетика', 'Хоруғ'],
        ['Тоҷикгаз', 'энергетика', 'Душанбе'],
        ['Сангтӯда', 'энергетика', 'Норак'],

        // транспорт и логистика
        ['Somon Air', 'транспорт', 'Душанбе'],
        ['Тоҷик Эйр', 'транспорт', 'Душанбе'],
        ['Роҳи оҳани Тоҷикистон', 'транспорт', 'Душанбе'],

        // строительство
        ['Тоҷиксохтмон', 'строительство', 'Душанбе'],

        // медицина и образование
        ['Сино Фарм', 'медицина', 'Душанбе'],

        // медиа и услуги
        ['Азия-Плюс', 'медиа', 'Душанбе'],
    ];

    /** Отрасль компании -> название категории (колонка обязательна). */
    private array $categoryMap = [
        'банк' => 'Финансы',
        'ит' => 'IT и разработка',
        'ритейл' => 'Продажи',
        'промышленность' => 'Производство',
        'энергетика' => 'Производство',
        'транспорт' => 'Логистика',
        'строительство' => 'Строительство',
        'медицина' => 'Медицина',
        'образование' => 'Образование',
        'медиа' => 'Маркетинг',
        'услуги' => 'Сфера услуг',
    ];

    /** Отрасль компании -> название из справочника отраслей. */
    private array $industryMap = [
        'банк' => 'Банки и финансы',
        'ит' => 'Программное обеспечение',
        'ритейл' => 'Розничная торговля',
        'промышленность' => 'Промышленность',
        'энергетика' => 'Промышленность',
        'транспорт' => 'Перевозки и склад',
        'строительство' => 'Строительство и ремонт',
        'медицина' => 'Здравоохранение',
        'образование' => 'Обучение и курсы',
        'медиа' => 'Реклама и PR',
        'услуги' => 'Общественное питание',
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

    /** Вакансии по отраслям: чтобы банк не искал горного инженера. */
    private array $rolesByIndustry = [
        'банк' => ['Мутахассиси кредитӣ', 'Муҳосиб', 'Иқтисоддон', 'Менеҷер оид ба муштариён', 'Таҳлилгари маълумот'],
        'ит' => ['Барноманависи Backend', 'Барноманависи Frontend', 'Муҳандиси санҷиш (QA)', 'Маъмури система', 'Тарроҳи UX/UI'],
        'ритейл' => ['Менеҷери фурӯш', 'Мудири фурӯшгоҳ', 'Мутахассиси харид', 'Фурӯшанда-маслиҳатчӣ', 'Мутахассиси маркетинг'],
        'промышленность' => ['Муҳандиси истеҳсолот', 'Технологи истеҳсолот', 'Мутахассиси бехатарии меҳнат', 'Механик', 'Лаборант'],
        'энергетика' => ['Муҳандиси барқ', 'Оператори дастгоҳ', 'Мутахассиси шабакаҳои барқӣ', 'Диспетчер', 'Муҳандиси бехатарӣ'],
        'транспорт' => ['Мутахассиси логистика', 'Диспетчери парвоз', 'Мудири анбор', 'Экспедитор', 'Менеҷер оид ба боркашонӣ'],
        'строительство' => ['Муҳандиси сохтмон', 'Смета-созанда', 'Мудири лоиҳа', 'Меъмор', 'Устои сохтмон'],
        'медицина' => ['Табиби оилавӣ', 'Ҳамшираи тиббӣ', 'Фарматсевт', 'Лаборанти тиббӣ', 'Менеҷери клиника'],
        'образование' => ['Муаллими математика', 'Муаллими забони англисӣ', 'Методист', 'Мураббии курсҳо', 'Мутахассиси кадрҳо'],
        'медиа' => ['Хабарнигор', 'Муҳаррир', 'Мутахассиси SMM', 'Матннавис', 'Тарроҳи графикӣ'],
        'услуги' => ['Менеҷери меҳмонхона', 'Роҳбалади сайёҳӣ', 'Ошпаз', 'Менеҷер оид ба хизматрасонӣ', 'Тарҷумон'],
    ];

    private array $maleNames = ['Далер', 'Фаррух', 'Беҳрӯз', 'Сомон', 'Ҷамшед', 'Рустам', 'Азиз', 'Шуҳрат', 'Умед', 'Илҳом', 'Фирдавс', 'Хуршед', 'Меҳроҷ', 'Наврӯз', 'Сироҷиддин'];

    private array $femaleNames = ['Мадина', 'Нилуфар', 'Зарина', 'Гулнора', 'Сабрина', 'Лола', 'Фирӯза', 'Меҳрӣ', 'Шаҳло', 'Дилноза', 'Мунира', 'Ситора', 'Парвина', 'Робия', 'Зебо'];

    private array $surnames = ['Раҳимов', 'Каримов', 'Назаров', 'Юсупов', 'Сафаров', 'Шарипов', 'Одинаев', 'Холов', 'Мирзоев', 'Ашӯров', 'Ҷӯраев', 'Қодиров', 'Саидов', 'Ҳакимов', 'Абдуллоев'];

    private array $hrPositions = ['Роҳбари шӯъбаи кадрҳо', 'Мудири кадрҳо', 'Директор', 'Менеҷер оид ба кадрҳо', 'Муассис'];

    private array $professions = [
        'Барноманависи Backend', 'Барноманависи Frontend', 'Барноманависи мобилӣ', 'Муҳандиси санҷиш (QA)',
        'Маъмури система', 'Таҳлилгари маълумот', 'Тарроҳи UX/UI', 'Тарроҳи графикӣ',
        'Мудири лоиҳа', 'Муҳосиб', 'Иқтисоддон', 'Ҳуқуқшинос', 'Мутахассиси кадрҳо',
        'Менеҷери фурӯш', 'Мутахассиси маркетинг', 'Мутахассиси SMM', 'Матннавис',
        'Мутахассиси логистика', 'Муҳандиси сохтмон', 'Муҳандиси барқ', 'Табиби оилавӣ',
        'Ҳамшираи тиббӣ', 'Муаллими математика', 'Тарҷумон', 'Ошпаз',
    ];

    private array $skillPool = [
        'PHP', 'Laravel', 'MySQL', 'JavaScript', 'Vue', 'React', 'Docker', 'Linux', 'Git',
        'Python', 'Java', 'Figma', 'SQL', 'Excel', '1С',
        'Идоракунии даста', 'Гуфтушунид', 'Таҳлили маълумот', 'Коргузорӣ', 'Хизматрасонии муштариён',
    ];

    private array $languagePool = ['Тоҷикӣ', 'Русӣ', 'Англисӣ', 'Ӯзбекӣ', 'Форсӣ', 'Туркӣ', 'Чинӣ'];

    private array $educations = [
        'Донишгоҳи техникии Тоҷикистон, муҳандисии барнома',
        'Донишгоҳи миллии Тоҷикистон, иқтисод',
        'Донишгоҳи славянии Русияву Тоҷикистон, ҳуқуқшиносӣ',
        'Донишгоҳи давлатии тиббии Тоҷикистон, табобати умумӣ',
        'Донишгоҳи давлатии омӯзгории Тоҷикистон, математика',
        'Донишгоҳи технологии Тоҷикистон, системаҳои иттилоотӣ',
        'Коллеҷи алоқаи шаҳри Душанбе, техники барноманавис',
    ];

    private array $streets = ['кӯчаи Рӯдакӣ', 'кӯчаи Айнӣ', 'хиёбони Сомонӣ', 'кӯчаи Фирдавсӣ', 'кӯчаи Борбад', 'кӯчаи Ҷаббор Расулов'];

    private array $employmentTypes = ['full-time', 'full-time', 'part-time', 'project_work', 'internship'];

    private array $schedules = ['full_day', 'full_day', 'flexible_schedule', 'remote_work'];

    private array $experiences = ['not', 'year', '3_years', '3-6years', 'more_6years'];

    public function run(): void
    {
        if (DB::table('users')->where('email', 'like', '%'.self::MAIL)->exists()) {
            $this->command->warn('Таджикские демо-данные уже загружены — повторно не создаю.');

            return;
        }

        $this->command->info('Готовим справочники…');
        $cityIds = $this->ensureCities();
        $categoryIds = $this->ensureCategories();
        $industryIds = $this->ensureIndustries($categoryIds);

        $password = Hash::make('password');
        $now = now();

        DB::transaction(function () use ($cityIds, $categoryIds, $industryIds, $password, $now) {
            $this->command->info('Создаём '.self::COMPANIES.' компаний и '.self::VACANCIES.' вакансий…');
            $this->seedCompanies($cityIds, $categoryIds, $industryIds, $password, $now);

            $this->command->info('Создаём '.self::RESUMES.' резюме…');
            $this->seedApplicants($password, $now);
        });

        $this->command->info(sprintf(
            'Готово: %d компаний, %d вакансий и %d резюме на таджикском.',
            self::COMPANIES, self::VACANCIES, self::RESUMES
        ));
    }

    // ==================== справочники ====================

    /**
     * Города только таджикские. Недостающие заводим, существующие
     * переиспользуем — иначе в каталоге появились бы близнецы.
     *
     * @return array<string,int> таджикское название -> id записи
     */
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

    /** @return array<string,int> название категории -> id */
    private function ensureCategories(): array
    {
        $ids = [];

        foreach (array_unique(array_values($this->categoryMap)) as $name) {
            $ids[$name] = Category::firstOrCreate(
                ['name' => $name],
                ['description' => 'Вакансии и компании направления «'.$name.'»', 'slug' => Str::slug($name)]
            )->id;
        }

        return $ids;
    }

    /** @return array<string,int> название отрасли -> id */
    private function ensureIndustries(array $categoryIds): array
    {
        $ids = [];

        // отрасль вешаем на ту же категорию, что и компании этого направления
        $categoryFor = array_combine(
            array_values($this->industryMap),
            array_map(fn ($key) => $this->categoryMap[$key], array_keys($this->industryMap))
        );

        foreach (array_unique(array_values($this->industryMap)) as $name) {
            $ids[$name] = Industry::firstOrCreate(
                ['name' => $name],
                [
                    'category_id' => $categoryIds[$categoryFor[$name]],
                    'parent_id' => 0,
                    'description' => 'Отрасль «'.$name.'»',
                ]
            )->id;
        }

        return $ids;
    }

    // ==================== компании и вакансии ====================

    private function seedCompanies(array $cityIds, array $categoryIds, array $industryIds, string $password, $now): void
    {
        $companies = array_slice($this->companies, 0, self::COMPANIES);

        $users = [];
        foreach ($companies as $i => [$name, , ]) {
            $first = $i % 2 === 0 ? $this->pick($this->maleNames) : $this->pick($this->femaleNames);

            $users[] = [
                'name' => $first.' '.$this->pick($this->surnames),
                'email' => 'company'.($i + 1).self::MAIL,
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
            ->where('email', 'like', 'company%'.self::MAIL)
            ->pluck('id', 'email');

        $employers = [];
        foreach ($companies as $i => [$name, $industry, $city]) {
            $employers[] = [
                'logo' => null,
                'user_id' => $userIds['company'.($i + 1).self::MAIL],
                'company_name' => $name,
                'job' => $this->pick($this->hrPositions),
                'email_company' => 'hr'.($i + 1).self::MAIL,
                'phone' => '+992 '.random_int(37, 44).' '.random_int(100, 999).'-'.random_int(10, 99).'-'.random_int(10, 99),
                'category_id' => $categoryIds[$this->categoryMap[$industry]],
                'description' => $this->companyDescription($name, $city),
                'city_id' => $cityIds[$city],
                'industry_id' => $industryIds[$this->industryMap[$industry]],
                'website_url' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('employers')->insert($employers);

        $employerIds = DB::table('employers')
            ->whereIn('user_id', $userIds->values())
            ->pluck('id', 'user_id');

        // вакансий больше, чем компаний: идём по кругу, поэтому первые
        // девять организаций публикуют по две
        $vacancies = [];
        for ($n = 0; $n < self::VACANCIES; $n++) {
            $i = $n % count($companies);
            [$name, $industry, $city] = $companies[$i];
            $employerId = $employerIds[$userIds['company'.($i + 1).self::MAIL]];
            $title = $this->pick($this->rolesByIndustry[$industry]);
            $from = random_int(2, 15) * 1000;

            $vacancies[] = [
                'title' => $title,
                'description' => $this->vacancyDescription($name, $title, $city),
                'employer_id' => $employerId,
                'salary_from' => $from,
                'salary_to' => $from + random_int(1, 8) * 1000,
                'currency' => 'TJS',
                'employment_type' => $this->pick($this->employmentTypes),
                'work_schedule' => $this->pick($this->schedules),
                'experience_required' => $this->pick($this->experiences),
                'skill' => collect($this->skillPool)->shuffle()->take(random_int(3, 5))->implode(', '),
                // язык требуется не каждой вакансии: у трети поле пустое
                'languages' => $n % 3 === 0 ? null : collect($this->languagePool)->shuffle()->take(random_int(1, 2))->implode(', '),
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

    private function companyDescription(string $name, string $city): string
    {
        return '«'.$name.'» дар шаҳри '.$city.' фаъолият мекунад ва дастаи худро мунтазам вусеъ медиҳад. '
            .'Барои мутахассисони сатҳҳои гуногун ҷойи корӣ, таълим ва имкони рушди касбӣ пешниҳод мешавад.';
    }

    private function vacancyDescription(string $company, string $title, string $city): string
    {
        return 'Ба ширкати «'.$company.'» (ш. '.$city.') мутахассис барои вазифаи «'.$title.'» лозим аст.'
            ."\n\nВазифаҳо:"
            ."\n— иҷрои вазифаҳои ҷорӣ дар доираи самт;"
            ."\n— кор дар якҷоягӣ бо даста ва риояи мӯҳлатҳо;"
            ."\n— таҳияи ҳисобот барои роҳбарият."
            ."\n\nМо пешниҳод мекунем:"
            ."\n— бастани шартномаи расмии меҳнатӣ;"
            ."\n— музди меҳнати саривақтӣ ва ҷойи кори муҷаҳҳаз;"
            ."\n— таълим аз ҳисоби ширкат ва имкони рушди касбӣ.";
    }

    // ==================== соискатели и резюме ====================

    private function seedApplicants(string $password, $now): void
    {
        $cities = array_keys($this->cityMap);

        $users = [];
        for ($i = 1; $i <= self::RESUMES; $i++) {
            $isMale = $i % 2 === 0;
            $first = $isMale ? $this->pick($this->maleNames) : $this->pick($this->femaleNames);
            $last = $this->pick($this->surnames).($isMale ? '' : 'а');

            $users[] = [
                'name' => $first.' '.$last,
                'email' => 'seeker'.$i.self::MAIL,
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
            ->where('email', 'like', 'seeker%'.self::MAIL)
            ->pluck('id', 'email');

        $applicants = [];
        for ($i = 1; $i <= self::RESUMES; $i++) {
            $isMale = $i % 2 === 0;
            $city = $this->pick($cities);

            $applicants[] = [
                'user_id' => $userIds['seeker'.$i.self::MAIL],
                'birth' => $now->copy()->subYears(random_int(21, 47))->subDays(random_int(0, 364))->format('Y-m-d'),
                'gender' => $isMale ? 'male' : 'female',
                'phone' => '+992 '.random_int(90, 93).' '.random_int(100, 999).'-'.random_int(10, 99).'-'.random_int(10, 99),
                'city' => $city,
                'address' => $this->pick($this->streets).', хонаи '.random_int(1, 120).', ҳуҷраи '.random_int(1, 90),
                'education' => $this->pick($this->educations),
                'about_me' => 'Мутахассис аз шаҳри '.$city.'. Ба вазифаҳо бо масъулият муносибат мекунам, '
                    .'зуд меомӯзам ва мехоҳам дар дастае кор кунам, ки имкони рушди касбӣ медиҳад.',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('applicants')->insert($applicants);

        $applicantIds = DB::table('applicants')
            ->whereIn('user_id', $userIds->values())
            ->pluck('id')
            ->all();

        // ровно одно резюме на соискателя — итого 50
        $resumes = [];
        foreach ($applicantIds as $applicantId) {
            $profession = $this->pick($this->professions);
            $years = random_int(0, 12);

            $resumes[] = [
                'applicant_id' => $applicantId,
                'profession' => $profession,
                'experience_years' => $years,
                'desired_position' => $profession,
                'desired_salary' => random_int(2, 20) * 1000,
                'url_website' => null,
                'skills' => collect($this->skillPool)->shuffle()->take(random_int(3, 6))->implode(', '),
                'languages' => collect($this->languagePool)->shuffle()->take(random_int(2, 3))->implode(', '),
                'place_work' => $this->pick(array_column($this->companies, 0)),
                'description' => 'Таҷрибаи корӣ '.$years.' сол дар самти «'.$profession.'». '
                    .'Дар лоиҳаҳои андозаҳои гуногун иштирок кардаам, ба кор бо масъулият муносибат мекунам ва зуд меомӯзам.',
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
