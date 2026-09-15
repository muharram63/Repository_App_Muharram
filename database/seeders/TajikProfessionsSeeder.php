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
 * Рабочие профессии и сфера услуг: 37 вакансий и 37 резюме на таджикском.
 *
 * Прежние сидеры закрыли офисные направления и школу. Здесь — всё остальное:
 * медицина, стройка, транспорт, сельское хозяйство, торговля, ремёсла,
 * культура и право. По одной вакансии и одному резюме на профессию, поэтому
 * ни одна из них не теряется в случайной выборке.
 *
 * Работодатель подбирается по смыслу: повара ищет чайхана, а не банк.
 *
 * Запускается отдельно:
 *   php artisan db:seed --class=TajikProfessionsSeeder
 *
 * Повторный запуск безопасен: свои записи сидер узнаёт по префиксу почты.
 */
class TajikProfessionsSeeder extends Seeder
{
    /** Префикс почты — по нему сидер узнаёт собственные записи. */
    private const MAIL = '@pro.workio.tj';

    /**
     * Профессия и организация, которая её нанимает.
     *
     * @var array<int,array{0:string,1:string}>
     */
    private array $professions = [
        // медицина
        ['Табиби оилавӣ', 'клиника'],
        ['Ҳамшираи тиббӣ', 'клиника'],
        ['Лаборанти тиббӣ', 'клиника'],
        ['Дорусоз', 'дорухона'],

        // транспорт
        ['Ронандаи автобус', 'нақлиёт'],
        ['Ронандаи техникаи вазнин', 'нақлиёт'],
        ['Экспедитор', 'нақлиёт'],
        ['Анборчӣ', 'нақлиёт'],

        // общественное питание
        ['Ошпаз', 'тарабхона'],
        ['Қаннод', 'тарабхона'],
        ['Нонвой', 'тарабхона'],
        ['Пешхизмат', 'тарабхона'],

        // бытовые услуги и ремёсла
        ['Сартарош', 'салон'],
        ['Дӯзанда', 'ателйе'],
        ['Кафшдӯз', 'ателйе'],

        // строительство
        ['Устои сохтмон', 'сохтмон'],
        ['Ҷӯшгар', 'сохтмон'],
        ['Барқчӣ', 'сохтмон'],
        ['Сантехник', 'сохтмон'],
        ['Наҷҷор', 'сохтмон'],
        ['Челонгар', 'сохтмон'],

        // сельское хозяйство
        ['Агроном', 'хоҷагӣ'],
        ['Байтор', 'хоҷагӣ'],
        ['Зоотехник', 'хоҷагӣ'],
        ['Боғбон', 'хоҷагӣ'],

        // торговля и учёт
        ['Муҳосиб', 'савдо'],
        ['Хазинадор', 'савдо'],
        ['Фурӯшанда-маслиҳатчӣ', 'савдо'],

        // охрана и обслуживание
        ['Муҳофиз', 'муҳофизат'],
        ['Фарроши бино', 'хизматрасонӣ'],

        // право
        ['Ҳуқуқшинос', 'ҳуқуқ'],
        ['Нотариус', 'ҳуқуқ'],

        // медиа и культура
        ['Хабарнигор', 'рӯзнома'],
        ['Аксбардор', 'рӯзнома'],
        ['Китобдор', 'китобхона'],

        // проектирование и туризм
        ['Меъмор', 'студия'],
        ['Роҳбалади сайёҳӣ', 'сайёҳат'],
    ];

    /**
     * Организации: название, город, категория и отрасль справочника.
     *
     * @var array<string,array{0:string,1:string,2:string,3:string}>
     */
    private array $orgs = [
        'клиника' => ['Маркази тиббии «Шифо»', 'Душанбе', 'Медицина', 'Здравоохранение'],
        'дорухона' => ['Шабакаи дорухонаҳои «Сино»', 'Душанбе', 'Медицина', 'Здравоохранение'],
        'нақлиёт' => ['Ширкати нақлиётии «Роҳи Сафед»', 'Душанбе', 'Логистика', 'Перевозки и склад'],
        'тарабхона' => ['Тарабхонаи «Роҳат»', 'Душанбе', 'Сфера услуг', 'Общественное питание'],
        'салон' => ['Салони зебоии «Зебо»', 'Хуҷанд', 'Сфера услуг', 'Общественное питание'],
        'ателйе' => ['Ателйеи «Атлас»', 'Хуҷанд', 'Производство', 'Промышленность'],
        'сохтмон' => ['Ширкати сохтмонии «Бунёд»', 'Бохтар', 'Строительство', 'Строительство и ремонт'],
        'хоҷагӣ' => ['Хоҷагии деҳқонии «Баҳор»', 'Кӯлоб', 'Производство', 'Промышленность'],
        'савдо' => ['Ширкати савдои «Баракат»', 'Душанбе', 'Продажи', 'Розничная торговля'],
        'муҳофизат' => ['Хадамоти муҳофизатии «Посбон»', 'Душанбе', 'Сфера услуг', 'Общественное питание'],
        'хизматрасонӣ' => ['Ширкати хизматрасонии «Тозагӣ»', 'Душанбе', 'Сфера услуг', 'Общественное питание'],
        'ҳуқуқ' => ['Дафтари ҳуқуқии «Адолат»', 'Душанбе', 'Финансы', 'Банки и финансы'],
        'рӯзнома' => ['Рӯзномаи «Овози халқ»', 'Душанбе', 'Маркетинг', 'Реклама и PR'],
        'китобхона' => ['Китобхонаи миллии Тоҷикистон', 'Душанбе', 'Образование и наука', 'Обучение и курсы'],
        'студия' => ['Студияи меъмории «Тарроҳ»', 'Душанбе', 'Строительство', 'Строительство и ремонт'],
        'сайёҳат' => ['Агентии сайёҳии «Кӯҳсор»', 'Хоруғ', 'Сфера услуг', 'Общественное питание'],
    ];

    /**
     * Что делает человек на этой работе — по одной строке на профессию.
     * Без этого описания вакансий были бы одинаковыми под копирку.
     *
     * @var array<string,string>
     */
    private array $duties = [
        'Табиби оилавӣ' => 'қабули беморон, ташхис ва таъини табобат',
        'Ҳамшираи тиббӣ' => 'иҷрои таъиноти табиб ва нигоҳубини беморон',
        'Лаборанти тиббӣ' => 'гирифтани таҳлилҳо ва кор бо таҷҳизоти лабораторӣ',
        'Дорусоз' => 'фурӯши доруворӣ ва маслиҳат ба харидорон',
        'Ронандаи автобус' => 'кашонидани мусофирон аз рӯи хатсайр ва риояи ҷадвал',
        'Ронандаи техникаи вазнин' => 'кор бо техникаи вазнин дар майдончаи корӣ',
        'Экспедитор' => 'ҳамроҳии бор ва расмикунонии ҳуҷҷатҳо',
        'Анборчӣ' => 'қабул, нигоҳдорӣ ва баҳисобгирии мол дар анбор',
        'Ошпаз' => 'тайёр кардани таомҳо аз рӯи харита ва назорати сифат',
        'Қаннод' => 'тайёр кардани маҳсулоти қаннодӣ ва ороиши он',
        'Нонвой' => 'пухтани нон ва назорати реҷаи танӯр',
        'Пешхизмат' => 'қабули фармоиш ва хизматрасонӣ ба меҳмонон',
        'Сартарош' => 'мӯйсаргирӣ ва хизматрасонӣ ба муштариён',
        'Дӯзанда' => 'дӯхтани либос аз рӯи андоза ва тарҳ',
        'Кафшдӯз' => 'таъмири пойафзол ва кор бо чарм',
        'Устои сохтмон' => 'корҳои гаҷкорию сафедкорӣ дар объект',
        'Ҷӯшгар' => 'ҷӯшонидани конструксияҳои металлӣ',
        'Барқчӣ' => 'насб ва таъмири шабакаи барқӣ',
        'Сантехник' => 'насб ва таъмири шабакаи обу канализатсия',
        'Наҷҷор' => 'сохтани маснуоти чӯбӣ ва насби дару тиреза',
        'Челонгар' => 'таъмири дастгоҳҳо ва кор бо асбоби челонгарӣ',
        'Агроном' => 'банақшагирии кишт ва назорати ҳосил',
        'Байтор' => 'муоинаи ҳайвонот ва пешгирии беморӣ',
        'Зоотехник' => 'ташкили хӯроквории чорво ва баҳисобгирии рама',
        'Боғбон' => 'нигоҳубини боғ, буридан ва обёрӣ',
        'Муҳосиб' => 'пешбурди ҳисоботи муҳосибӣ ва кор бо 1С',
        'Хазинадор' => 'кор бо хазина ва расмикунонии амалиёт',
        'Фурӯшанда-маслиҳатчӣ' => 'маслиҳат ба харидорон ва ороиши рафҳо',
        'Муҳофиз' => 'назорати объект ва риояи тартиботи дохилӣ',
        'Фарроши бино' => 'тозагии бино ва ҳудуди назди он',
        'Ҳуқуқшинос' => 'таҳияи шартномаҳо ва ҳимояи манфиатҳо дар суд',
        'Нотариус' => 'тасдиқи ҳуҷҷатҳо ва машварати ҳуқуқӣ',
        'Хабарнигор' => 'омода кардани мавод ва мусоҳиба',
        'Аксбардор' => 'аксбардории чорабиниҳо ва коркарди аксҳо',
        'Китобдор' => 'кор бо фонд ва хизматрасонӣ ба хонандагон',
        'Меъмор' => 'таҳияи лоиҳа ва назорати муаллифӣ',
        'Роҳбалади сайёҳӣ' => 'гузаронидани сайри сайёҳон ва шарҳи ҷойҳои таърихӣ',
    ];

    /** Таджикский город -> запись справочника, чтобы не плодить дубли. */
    private array $cityMap = [
        'Душанбе' => 'город Душанбе',
        'Хуҷанд' => 'Согдийская область, город Худжанд',
        'Бохтар' => 'Хатлонская область, город Бохтар',
        'Кӯлоб' => 'Хатлонская область, город Куляб',
        'Хоруғ' => 'ГБАО, город Хорог',
    ];

    private array $maleNames = ['Далер', 'Фаррух', 'Беҳрӯз', 'Сомон', 'Ҷамшед', 'Рустам', 'Азиз', 'Шуҳрат', 'Умед', 'Илҳом', 'Фирдавс', 'Хуршед', 'Меҳроҷ', 'Наврӯз', 'Сироҷиддин'];

    private array $femaleNames = ['Мадина', 'Нилуфар', 'Зарина', 'Гулнора', 'Сабрина', 'Лола', 'Фирӯза', 'Меҳрӣ', 'Шаҳло', 'Дилноза', 'Мунира', 'Ситора', 'Парвина', 'Робия', 'Зебо'];

    private array $surnames = ['Раҳимов', 'Каримов', 'Назаров', 'Юсупов', 'Сафаров', 'Шарипов', 'Одинаев', 'Холов', 'Мирзоев', 'Ашӯров', 'Ҷӯраев', 'Қодиров', 'Саидов', 'Ҳакимов', 'Абдуллоев'];

    private array $hrPositions = ['Директор', 'Мудири шӯъба', 'Менеҷер оид ба кадрҳо', 'Муассис'];

    private array $softSkills = ['Масъулиятшиносӣ', 'Кор дар даста', 'Риояи мӯҳлат', 'Хизматрасонии муштариён', 'Риояи техникаи бехатарӣ', 'Диққати баланд'];

    private array $languagePool = ['Тоҷикӣ', 'Русӣ', 'Англисӣ', 'Ӯзбекӣ', 'Форсӣ'];

    private array $educations = [
        'Коллеҷи касбӣ-техникии ш. Душанбе',
        'Коллеҷи тиббии ш. Хуҷанд',
        'Донишгоҳи аграрии Тоҷикистон ба номи Ш. Шоҳтемур',
        'Донишгоҳи техникии Тоҷикистон ба номи М. Осимӣ',
        'Донишгоҳи миллии Тоҷикистон',
        'Литсейи касбии ш. Бохтар',
        'Курсҳои такмили ихтисос',
    ];

    private array $streets = ['кӯчаи Рӯдакӣ', 'кӯчаи Айнӣ', 'хиёбони Сомонӣ', 'кӯчаи Фирдавсӣ', 'кӯчаи Борбад'];

    private array $schedules = ['full_day', 'full_day', 'flexible_schedule'];

    private array $experiences = ['not', 'year', '3_years', '3-6years', 'more_6years'];

    public function run(): void
    {
        if (DB::table('users')->where('email', 'like', '%'.self::MAIL)->exists()) {
            $this->command->warn('Данные по рабочим профессиям уже загружены — повторно не создаю.');

            return;
        }

        $this->command->info('Готовим справочники…');
        $cityIds = $this->ensureCities();
        [$categoryIds, $industryIds] = $this->ensureRefs();

        $password = Hash::make('password');
        $now = now();

        DB::transaction(function () use ($cityIds, $categoryIds, $industryIds, $password, $now) {
            $this->command->info('Создаём организации и вакансии…');
            $this->seedOrgs($cityIds, $categoryIds, $industryIds, $password, $now);

            $this->command->info('Создаём резюме…');
            $this->seedWorkers($password, $now);
        });

        $count = count($this->professions);
        $this->command->info("Готово: {$count} вакансий и {$count} резюме по рабочим профессиям.");
    }

    // ==================== справочники ====================

    /** @return array<string,int> */
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

    /** @return array{0:array<string,int>,1:array<string,int>} */
    private function ensureRefs(): array
    {
        $categories = [];
        $industries = [];

        foreach ($this->orgs as [, , $categoryName, $industryName]) {
            $categories[$categoryName] ??= Category::firstOrCreate(
                ['name' => $categoryName],
                ['description' => 'Вакансии и компании направления «'.$categoryName.'»', 'slug' => Str::slug($categoryName)]
            )->id;

            $industries[$industryName] ??= Industry::firstOrCreate(
                ['name' => $industryName],
                [
                    'category_id' => $categories[$categoryName],
                    'parent_id' => 0,
                    'description' => 'Отрасль «'.$industryName.'»',
                ]
            )->id;
        }

        return [$categories, $industries];
    }

    // ==================== организации и вакансии ====================

    private function seedOrgs(array $cityIds, array $categoryIds, array $industryIds, string $password, $now): void
    {
        $keys = array_keys($this->orgs);

        $users = [];
        foreach ($keys as $i => $key) {
            $first = $i % 2 === 0 ? $this->pick($this->maleNames) : $this->pick($this->femaleNames);

            $users[] = [
                'name' => $first.' '.$this->pick($this->surnames),
                'email' => 'org'.($i + 1).self::MAIL,
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
            ->where('email', 'like', 'org%'.self::MAIL)
            ->pluck('id', 'email');

        $employers = [];
        foreach ($keys as $i => $key) {
            [$name, $city, $categoryName, $industryName] = $this->orgs[$key];

            $employers[] = [
                'logo' => null,
                'user_id' => $userIds['org'.($i + 1).self::MAIL],
                'company_name' => $name,
                'job' => $this->pick($this->hrPositions),
                'email_company' => 'job'.($i + 1).self::MAIL,
                'phone' => '+992 '.random_int(37, 44).' '.random_int(100, 999).'-'.random_int(10, 99).'-'.random_int(10, 99),
                'category_id' => $categoryIds[$categoryName],
                'description' => $name.' дар шаҳри '.$city.' фаъолият мекунад ва ба кор мутахассисони соҳа даъват менамояд. '
                    .'Шароити мувофиқи корӣ ва музди меҳнати саривақтӣ таъмин карда мешавад.',
                'city_id' => $cityIds[$city],
                'industry_id' => $industryIds[$industryName],
                'website_url' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('employers')->insert($employers);

        $employerByKey = [];
        foreach ($keys as $i => $key) {
            $employerByKey[$key] = DB::table('employers')
                ->where('user_id', $userIds['org'.($i + 1).self::MAIL])
                ->value('id');
        }

        // по одной вакансии на профессию
        $vacancies = [];
        foreach ($this->professions as [$profession, $orgKey]) {
            [$orgName, $city] = $this->orgs[$orgKey];
            $employerId = $employerByKey[$orgKey];
            $from = random_int(15, 35) * 100;

            $vacancies[] = [
                'title' => $profession,
                'description' => $this->vacancyDescription($orgName, $city, $profession),
                'employer_id' => $employerId,
                'salary_from' => $from,
                'salary_to' => $from + random_int(5, 25) * 100,
                'currency' => 'TJS',
                'employment_type' => 'full-time',
                'work_schedule' => $this->pick($this->schedules),
                'experience_required' => $this->pick($this->experiences),
                'skill' => $this->duties[$profession].', '.collect($this->softSkills)->shuffle()->take(2)->implode(', '),
                // рабочим профессиям язык нужен реже
                'languages' => count($vacancies) % 2 === 0 ? 'Тоҷикӣ' : 'Тоҷикӣ, Русӣ',
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

    private function vacancyDescription(string $org, string $city, string $profession): string
    {
        return 'Ба '.$org.' (ш. '.$city.') мутахассис барои вазифаи «'.$profession.'» лозим аст.'
            ."\n\nВазифаҳо:"
            ."\n— ".$this->duties[$profession].';'
            ."\n— риояи талаботи техникаи бехатарӣ ва тартиботи дохилӣ;"
            ."\n— кор дар якҷоягӣ бо дигар кормандони шӯъба."
            ."\n\nМо пешниҳод мекунем:"
            ."\n— бастани шартномаи расмии меҳнатӣ;"
            ."\n— музди меҳнати саривақтӣ ва ҷойи кори муҷаҳҳаз;"
            ."\n— таълим дар ҷои кор ва имкони такмили ихтисос.";
    }

    // ==================== работники и резюме ====================

    private function seedWorkers(string $password, $now): void
    {
        $cities = array_keys($this->cityMap);
        $total = count($this->professions);

        $users = [];
        for ($i = 1; $i <= $total; $i++) {
            $isMale = $i % 2 === 1;
            $first = $isMale ? $this->pick($this->maleNames) : $this->pick($this->femaleNames);
            $last = $this->pick($this->surnames).($isMale ? '' : 'а');

            $users[] = [
                'name' => $first.' '.$last,
                'email' => 'worker'.$i.self::MAIL,
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
            ->where('email', 'like', 'worker%'.self::MAIL)
            ->pluck('id', 'email');

        $applicants = [];
        for ($i = 1; $i <= $total; $i++) {
            $isMale = $i % 2 === 1;
            [$profession] = $this->professions[$i - 1];
            $city = $this->pick($cities);

            $applicants[] = [
                'user_id' => $userIds['worker'.$i.self::MAIL],
                'birth' => $now->copy()->subYears(random_int(20, 55))->subDays(random_int(0, 364))->format('Y-m-d'),
                'gender' => $isMale ? 'male' : 'female',
                'phone' => '+992 '.random_int(90, 93).' '.random_int(100, 999).'-'.random_int(10, 99).'-'.random_int(10, 99),
                'city' => $city,
                'address' => $this->pick($this->streets).', хонаи '.random_int(1, 120).', ҳуҷраи '.random_int(1, 90),
                'education' => $this->pick($this->educations),
                'about_me' => 'Мутахассиси касби «'.$profession.'» аз шаҳри '.$city.'. '
                    .'Кори худро бо масъулият иҷро мекунам, талаботи техникаи бехатариро риоя менамоям '
                    .'ва ҳамеша тайёрам чизи навро омӯзам.',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('applicants')->insert($applicants);

        $applicantIds = array_values(
            DB::table('applicants')->whereIn('user_id', $userIds->values())->pluck('id')->all()
        );

        // по одному резюме на профессию — в том же порядке, что и анкеты
        $resumes = [];
        foreach ($applicantIds as $n => $applicantId) {
            [$profession] = $this->professions[$n];
            $years = random_int(0, 25);

            $resumes[] = [
                'applicant_id' => $applicantId,
                'profession' => $profession,
                'experience_years' => $years,
                'desired_position' => $profession,
                'desired_salary' => random_int(15, 45) * 100,
                'url_website' => null,
                'skills' => $this->duties[$profession].', '.collect($this->softSkills)->shuffle()->take(2)->implode(', '),
                'languages' => collect($this->languagePool)->shuffle()->take(random_int(2, 3))->implode(', '),
                'place_work' => $this->pick(array_column(array_values($this->orgs), 0)),
                'description' => 'Касби ман «'.$profession.'», таҷрибаи корӣ '.$years.' сол. '
                    .'Ба вазифаҳои асосӣ дохил мешавад: '.$this->duties[$profession].'. '
                    .'Кор дар даста ва риояи мӯҳлатҳо барои ман муҳим аст.',
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
