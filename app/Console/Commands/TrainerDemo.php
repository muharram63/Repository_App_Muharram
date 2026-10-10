<?php

namespace App\Console\Commands;

use App\Models\AiDecision;
use App\Models\AiInterview;
use App\Models\AiInterviewTurn;
use App\Models\AiTestSubmission;
use App\Models\AiTestTask;
use App\Models\Applicant;
use App\Models\Resume;
use App\Models\Trainer\TrainerLedgerEntry;
use App\Models\Trainer\TrainerPackage;
use App\Models\Trainer\TrainerPayment;
use App\Models\Trainer\TrainerSession;
use App\Models\Trainer\TrainerTrack;
use App\Models\User;
use App\Services\Trainer\TrainerCoach;
use App\Services\Trainer\TrainerPayments;
use App\Services\Trainer\TrainerSessions;
use App\Services\Trainer\TrainerWallet;
use Database\Seeders\TrainerSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Демо тренажёра для показа: кандидат с двумя законченными тренировками
 * (с разбором) и одной заявкой на оплату, ожидающей проверки.
 *
 * К модели команда не обращается: ответы, оценки и текст разбора заранее
 * написаны здесь. Демо-тренировки помечены demo и в статистику и дневной
 * лимит не входят. Повторный запуск пересоздаёт демо с нуля.
 */
class TrainerDemo extends Command
{
    protected $signature = 'trainer:demo';

    protected $description = 'Тренажёр: демо-кандидат с двумя тренировками и заявкой на оплату';

    public const EMAIL = 'demo.trainer@workio.tj';

    public const PASSWORD = 'demo-trainer-2026';

    public function handle(TrainerSessions $sessions, TrainerWallet $wallet, TrainerPayments $payments, TrainerCoach $coach): int
    {
        $this->call('db:seed', ['--class' => TrainerSeeder::class, '--force' => true]);

        $user = $this->candidate();
        $this->wipe($user);

        $php = TrainerTrack::where('title', 'PHP-разработчик')->firstOrFail();
        $sales = TrainerTrack::where('title', 'Менеджер по продажам')->firstOrFail();

        // попытки на две демо-тренировки — вручную, с пометкой
        $wallet->credit($user, 2, 'manual', null, 'Демо тренажёра');

        $first = $this->play($sessions, $coach, $user, $php, 'junior', self::PHP, now()->subDays(3));
        $second = $this->play($sessions, $coach, $user, $sales, 'middle', self::SALES, now()->subDay());

        // заявка: кандидат выбрал пакет и нажал «Я оплатил» — ждёт админа
        $package = TrainerPackage::where('active', true)->orderBy('position')->firstOrFail();
        $payment = $payments->create($user, $package);
        $payments->claim($payment, '+992 93 *** 45 67');

        $this->info('Демо готово.');
        $this->table(['', ''], [
            ['Вход кандидата', self::EMAIL.' / '.self::PASSWORD],
            ['Тренировки', $first->track_title.' — '.$first->score.'/100; '.$second->track_title.' — '.$second->score.'/100'],
            ['Заявка на оплату', $payment->reference().' · '.$payment->package_title.' · '.$payment->amount.' сомони — «Я оплатил»'],
            ['Админка', route('superadmin.trainer')],
        ]);

        return self::SUCCESS;
    }

    private function candidate(): User
    {
        $user = User::where('email', self::EMAIL)->first();

        if (! $user) {
            $user = new User(['name' => 'Демо Кандидат', 'email' => self::EMAIL, 'password' => Hash::make(self::PASSWORD)]);
            $user->role = 'applicant';
            $user->email_verified_at = now();
            $user->save();
        }

        $applicant = Applicant::firstOrCreate(['user_id' => $user->id], ['education' => 'Высшее']);

        if (! Resume::where('applicant_id', $applicant->id)->exists()) {
            Resume::create([
                'applicant_id' => $applicant->id,
                'profession' => 'PHP-разработчик',
                'experience_years' => 1,
                'desired_position' => 'Junior Backend',
                'desired_salary' => 4000,
                'languages' => 'русский, таджикский',
            ]);
        }

        return $user->fresh();
    }

    /** Прошлое демо — целиком: тренировки с учебными собеседованиями, оплаты, журнал. */
    private function wipe(User $user): void
    {
        foreach (TrainerSession::where('user_id', $user->id)->get() as $session) {
            $session->interview?->eraseCompletely();
            $vacancy = $session->vacancy;
            $session->delete();
            $vacancy?->delete();
        }

        TrainerLedgerEntry::where('user_id', $user->id)->delete();
        TrainerPayment::where('user_id', $user->id)->delete();
    }

    /** Тренировка от старта до разбора — на готовых ответах, без модели. */
    private function play(TrainerSessions $sessions, TrainerCoach $coach, User $user, TrainerTrack $track, string $level, array $script, $when): TrainerSession
    {
        $session = $sessions->start($user, $track, $level);
        $interview = $session->interview;

        $position = 0;
        foreach ($script['turns'] as [$question, $answer, $score, $rationale]) {
            AiInterviewTurn::create(['ai_interview_id' => $interview->id, 'position' => ++$position, 'role' => 'ai', 'text' => $question]);
            AiInterviewTurn::create(['ai_interview_id' => $interview->id, 'position' => ++$position, 'role' => 'candidate',
                'text' => $answer, 'score' => $score, 'max_score' => 10, 'rationale' => $rationale, 'scored_by' => 'ai']);
        }

        $task = AiTestTask::create([
            'ai_interview_id' => $interview->id, 'kind' => AiTestTask::KIND_MAIN,
            'statement' => $script['task']['statement'], 'format' => $script['task']['format'], 'language' => $script['task']['language'],
            'reference_solution' => $script['task']['reference'],
            'rubric' => [['point' => $script['task']['review'][0]['point'], 'weight' => 100]],
            'quiz' => $script['quiz'], 'time_limit_minutes' => 30, 'issued_at' => $when,
        ]);

        AiTestSubmission::create([
            'ai_test_task_id' => $task->id, 'content' => $script['task']['answer'],
            'answers' => ['quiz' => array_column($script['quiz'], 'given')],
            'details' => [
                'quiz' => [
                    'correct' => count(array_filter($script['quiz'], fn ($q) => $q['given'] === $q['answer'])),
                    'total' => count($script['quiz']),
                    'items' => array_map(fn ($q) => ['correct' => $q['given'] === $q['answer']], $script['quiz']),
                ],
                'parts' => [['score' => $script['task']['score'], 'summary' => $script['task']['summary'],
                    'executed' => $script['task']['format'] === 'code', 'review' => $script['task']['review']]],
            ],
            'score' => $script['task']['score'], 'submitted_at' => $when, 'graded_at' => $when,
        ]);

        AiDecision::create(['ai_interview_id' => $interview->id, 'round' => 1, 'total' => $script['total'],
            'interview_score' => $script['interview'], 'test_score' => $script['test'], 'documents_score' => 0,
            'outcome' => 'passed', 'gate_reason' => 'above_accept_threshold', 'reasons' => [],
            'message_to_candidate' => '—', 'requires_manual_review' => false]);

        // без наблюдателя: разбор кладём готовый, очередь и модель не нужны
        AiInterview::withoutEvents(fn () => $interview->update([
            'outcome' => 'passed', 'total_score' => $script['total'], 'interview_score' => $script['interview'],
            'test_score' => $script['test'], 'decided_at' => $when, 'stage' => 'done',
        ]));

        $session->forceFill([
            'demo' => true,
            'status' => TrainerSession::STATUS_FINISHED,
            'score' => $script['total'],
            'finished_at' => $when,
            'created_at' => $when->copy()->subMinutes(35),
        ])->save();

        $feedback = $coach->basic($session->fresh());
        $feedback['source'] = 'demo';
        foreach ($script['feedback'] as $key => $value) {
            $feedback[$key] = $value;
        }
        foreach ($script['better'] as $i => $better) {
            $feedback['questions'][$i]['better'] = $better;
        }
        $feedback['tasks'][0]['better'] = $script['task']['how_to'];

        $session->update(['feedback' => $feedback, 'feedback_status' => 'ready']);

        return $session->fresh();
    }

    public const PHP = [
        'total' => 68, 'interview' => 72, 'test' => 64,
        'turns' => [
            ['Расскажите, чем отличается INNER JOIN от LEFT JOIN.', 'INNER JOIN возвращает только совпадающие строки из обеих таблиц, а LEFT JOIN — все строки левой таблицы, и если пары нет, справа будут NULL.', 9, 'Точный и полный ответ.'],
            ['Что такое миграции в Laravel и зачем они нужны?', 'Это файлы, которые создают таблицы в базе. Запускаются командой migrate.', 6, 'Назначение верно, но не сказано про версионирование схемы и откат (rollback).'],
            ['Как бы вы защитили форму от CSRF-атаки?', 'Не уверен, кажется, через валидацию полей.', 3, 'Путает CSRF с валидацией; не назван токен @csrf.'],
            ['Что такое N+1 и как его избежать в Eloquent?', 'Это когда в цикле на каждую запись делается отдельный запрос. Лечится через with().', 8, 'Верно, с примером решения.'],
            ['Расскажите о проекте, которым гордитесь.', 'Делал сайт объявлений на Laravel: регистрация, поиск, загрузка фото.', 7, 'Конкретно, но мало о своей роли и трудностях.'],
        ],
        'quiz' => [
            ['question' => 'Какой HTTP-метод идемпотентен?', 'options' => ['POST', 'PUT', 'PATCH'], 'answer' => 1, 'given' => 1],
            ['question' => 'Что вернёт count([])?', 'options' => ['0', 'null', 'ошибка'], 'answer' => 0, 'given' => 0],
            ['question' => 'Какой код ответа означает «не найдено»?', 'options' => ['401', '403', '404'], 'answer' => 2, 'given' => 1],
        ],
        'task' => [
            'statement' => 'Напишите функцию evenSum(array $numbers): int, которая возвращает сумму чётных чисел массива.',
            'format' => 'code', 'language' => 'php',
            'answer' => "function evenSum(array \$numbers): int {\n    \$sum = 0;\n    foreach (\$numbers as \$n) {\n        if (\$n % 2 == 0) \$sum += \$n;\n    }\n    return \$sum;\n}",
            'reference' => "function evenSum(array \$numbers): int\n{\n    return array_sum(array_filter(\$numbers, fn (int \$n) => \$n % 2 === 0));\n}",
            'score' => 64,
            'summary' => 'Решение работает на обычных данных, но не проверяет тип элементов и сравнивает нестрого.',
            'review' => [
                ['point' => 'Верная сумма на обычных данных', 'passed' => true, 'note' => ''],
                ['point' => 'Строгое сравнение и типы', 'passed' => false, 'note' => '== вместо ===, строки в массиве дадут неожиданный результат'],
            ],
            'how_to' => "1. Отфильтруйте чётные: array_filter с условием \$n % 2 === 0.\n2. Сложите: array_sum.\n3. Объявите типы (int \$n) — тогда строка в массиве сразу даст ошибку, а не тихий неверный ответ.",
        ],
        'feedback' => [
            'summary' => 'Хорошая база для Junior: SQL и Eloquent вы понимаете уверенно. Просели безопасность (CSRF) и строгость в коде — это быстро подтягивается.',
            'strengths' => ['Чётко объяснили разницу JOIN', 'Знаете проблему N+1 и как её решить через with()', 'Код задания рабочий и читаемый'],
            'improvements' => ['Защита форм: CSRF-токен и @csrf в Blade', 'Строгое сравнение === и типы аргументов', 'Рассказ о проекте: своя роль, трудность и как её решили'],
            'next_steps' => ['Прочитайте раздел CSRF Protection в документации Laravel', 'Перепишите 3 своих функции со строгими типами', 'Подготовьте рассказ о проекте по схеме «задача — что сделал я — результат»'],
        ],
        'better' => [
            1 => 'Миграции — это версионирование схемы базы в коде: каждая описывает изменение (up) и его откат (down). Команда migrate применяет новые, rollback откатывает — так у всей команды одинаковая база.',
            2 => 'Laravel защищает от CSRF токеном: в каждую форму добавляется @csrf, а middleware VerifyCsrfToken сверяет токен из формы с токеном сессии. Чужой сайт этот токен не знает, и подделанный запрос отклоняется.',
        ],
    ];

    public const SALES = [
        'total' => 81, 'interview' => 84, 'test' => 78,
        'turns' => [
            ['Клиент говорит: «Дорого». Что вы ответите?', 'Сначала уточню, с чем он сравнивает, а потом покажу, что он получает за эту цену — сервис, гарантию, сроки.', 9, 'Отлично: уточнение и ценность вместо скидки.'],
            ['Как вы ищете новых клиентов?', 'Звоню по базе, прошу рекомендации у довольных клиентов, слежу за новыми компаниями в городе.', 8, 'Несколько каналов, конкретно.'],
            ['Как вы ведёте клиента в CRM?', 'Записываю звонки и договорённости.', 6, 'Не хватает этапов воронки и следующего шага с датой.'],
            ['Расскажите о самой трудной продаже.', 'Клиент полгода не отвечал, я раз в месяц присылал полезные материалы, и он вернулся сам.', 9, 'Сильная история с результатом.'],
            ['Как вы планируете день?', 'С утра звонки, после обеда встречи, вечером отчёт.', 7, 'Есть структура, нет приоритетов по воронке.'],
            ['Что делаете, если план месяца не выполняется?', 'Смотрю, на каком этапе теряются клиенты, и усиливаю его.', 8, 'Аналитический подход.'],
        ],
        'quiz' => [
            ['question' => 'Что такое конверсия воронки?', 'options' => ['Доля клиентов, перешедших на следующий этап', 'Сумма сделок', 'Число звонков'], 'answer' => 0, 'given' => 0],
            ['question' => 'Скидка 10% при марже 20% — на сколько нужно поднять объём, чтобы прибыль не упала?', 'options' => ['на 10%', 'на 50%', 'на 100%'], 'answer' => 2, 'given' => 1],
        ],
        'task' => [
            'statement' => 'Клиент — сеть из 5 аптек — хочет перейти к вам от конкурента, но просит скидку 15%. Опишите, как проведёте переговоры.',
            'format' => 'case', 'language' => null,
            'answer' => 'Узнаю, что не устраивает у конкурента. Предложу пробный месяц без скидки, но с бесплатной доставкой. Если нужно — скидка 5% за годовой договор.',
            'reference' => "1. Выяснить настоящую причину ухода от конкурента.\n2. Предложить ценность вместо скидки: доставка, отсрочка, персональный менеджер.\n3. Если скидка неизбежна — только в обмен: объём, срок договора, предоплата.\n4. Зафиксировать следующий шаг с датой.",
            'score' => 78,
            'summary' => 'Хороший обмен уступок, но не зафиксирован следующий шаг.',
            'review' => [
                ['point' => 'Ценность вместо прямой скидки', 'passed' => true, 'note' => ''],
                ['point' => 'Уступка в обмен на обязательство', 'passed' => true, 'note' => 'Годовой договор — верно'],
                ['point' => 'Следующий шаг с датой', 'passed' => false, 'note' => 'Не сказано, когда и о чём договариваетесь дальше'],
            ],
            'how_to' => "Ваш план почти полный. Добавьте последний шаг: «Предлагаю в четверг встретиться с вашим закупщиком и подписать пробный договор на месяц» — переговоры без даты часто так и не заканчиваются.",
        ],
        'feedback' => [
            'summary' => 'Сильный результат для Middle: вы работаете с возражениями через ценность, а не через скидку. Добрать стоит дисциплину в CRM и расчёты маржи.',
            'strengths' => ['Работа с возражением «дорого» через уточнение и ценность', 'Сильная история трудной продажи', 'Уступки только в обмен на обязательства'],
            'improvements' => ['CRM: этап воронки и следующий шаг с датой у каждого клиента', 'Расчёт влияния скидки на прибыль', 'Фиксировать следующий шаг в конце переговоров'],
            'next_steps' => ['Посчитайте 3 примера: скидка X% при марже Y% — какой нужен рост объёма', 'Проверьте свою CRM: у каждого ли клиента есть дата следующего шага', 'Пройдите тренировку уровня Senior'],
        ],
        'better' => [
            2 => 'В CRM у каждого клиента — этап воронки, сумма и обязательно следующий шаг с датой: «позвонить 12-го, обсудить пробную поставку». Тогда ни один клиент не теряется, а руководитель видит прогноз.',
        ],
    ];
}
