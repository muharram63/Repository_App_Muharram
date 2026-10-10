<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeCandidateDocument;
use App\Jobs\BuildRequirementMatrix;
use App\Models\AiCandidateDocument;
use App\Models\AiInterview;
use App\Models\AiInterviewConfig;
use App\Models\Applicant;
use App\Models\Vacancy;
use App\Models\VacancyResponse;
use App\Services\Documents\TextExtractor;
use App\Services\Privacy\PiiRedactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

/**
 * ИИ-собеседование со стороны кандидата: согласие и документы.
 *
 * Без согласия процесс не начинается — это не формальность интерфейса, а
 * условие: человек должен знать, что разговаривает с ИИ и что его данные
 * обрабатываются для оценки. Отказаться можно, и отклик при этом остаётся: его
 * рассмотрит работодатель обычным порядком.
 *
 * Резюме кандидат заново не загружает. Оно уже есть на площадке, и просить его
 * второй раз значило бы не доверять своим же данным. Догружаются только дипломы
 * и сертификаты.
 */
class AiInterviewController extends Controller
{
    /** Сколько документов принимаем на одно собеседование. */
    public const MAX_DOCUMENTS = 10;

    /** Предел размера файла в килобайтах — как у документов резюме. */
    public const MAX_KB = 5120;

    public function __construct(
        private readonly TextExtractor $extractor,
        private readonly PiiRedactor $redactor,
    ) {
    }

    /**
     * Мои собеседования.
     *
     * Страница нужна не для полноты, а потому что кандидат закроет вкладку и
     * вернётся: прогресс сохраняется в базе, и к нему должен быть путь.
     */
    public function index()
    {
        $applicant = $this->applicant();

        if (! $applicant instanceof Applicant) {
            return $applicant;
        }

        // тренировки живут в «Тренажёре», а не среди собеседований по вакансиям
        $interviews = AiInterview::where('applicant_id', $applicant->id)
            ->withoutTraining()
            ->with('vacancy.employer', 'latestDecision')
            ->latest('updated_at')
            ->get();

        return view('applicant.pages.ai-interview.index', [
            'user' => auth()->user(),
            'applicant' => $applicant,
            'interviews' => $interviews,
            'waiting' => $this->waitingVacancies($applicant, $interviews),
        ]);
    }

    /**
     * Вакансии, где собеседование ждёт, а кандидат его ещё не начинал.
     *
     * Без них пункт меню врал названием: «ИИ-собеседования» показывал только
     * уже начатые, и человек, которого собеседование ждёт, заходил туда и видел
     * пустую страницу — ровно там, куда он пойдёт искать.
     *
     * @param  \Illuminate\Support\Collection<int,AiInterview>  $started
     * @return \Illuminate\Support\Collection<int,\App\Models\Vacancy>
     */
    private function waitingVacancies(Applicant $applicant, $started)
    {
        $responded = VacancyResponse::where('applicant_id', $applicant->id)
            ->pluck('vacancy_id')
            ->diff($started->pluck('vacancy_id'));

        if ($responded->isEmpty()) {
            return collect();
        }

        // только там, где собеседование действительно можно пройти: включённое
        // с противоречивыми настройками никого не собеседует
        $usable = AiInterviewConfig::whereIn('vacancy_id', $responded)
            ->where('enabled', true)
            ->get()
            ->filter(fn (AiInterviewConfig $config) => $config->isUsable())
            ->pluck('vacancy_id');

        return $usable->isEmpty()
            ? collect()
            : Vacancy::whereIn('id', $usable)->with('employer')->get();
    }

    /**
     * Начало для кандидата: согласие или продолжение с той стадии, где бросил.
     */
    public function start(Vacancy $vacancy)
    {
        $applicant = $this->applicant();

        if (! $applicant instanceof Applicant) {
            return $applicant;
        }

        $config = AiInterviewConfig::where('vacancy_id', $vacancy->id)->first();

        // вакансия могла выключить собеседование уже после отклика
        if (! $config || ! $config->isUsable()) {
            return redirect()->route('public.responses.index')
                ->with('status', __('По этой вакансии ИИ-собеседование не проводится — ваш отклик рассмотрит работодатель.'));
        }

        // резюме — источник данных для разбора, без него собеседовать не о чем
        if (! $applicant->resume()->exists()) {
            return redirect()->route('applicant.resume.create')
                ->with('error', __('Сначала создайте резюме: ИИ-собеседование опирается на него.'));
        }

        /*
         * Собеседование растёт из отклика, а не из адреса в браузере.
         *
         * Сюда ведут только с отправленного отклика, но адрес угадывается, и
         * без этой проверки любой соискатель заводил бы себе собеседование по
         * любой вакансии с включённым ИИ. Работодатель видел бы в отчёте
         * кандидата, который на вакансию не откликался, а отклика у записи не
         * было бы вовсе — статус по итогам решения ставить было бы некуда.
         *
         * Заодно снимается вопрос о закрытых и архивных вакансиях: откликнуться
         * на такую нельзя, а значит и собеседоваться по ней не получится.
         */
        $responseId = VacancyResponse::where('vacancy_id', $vacancy->id)
            ->where('applicant_id', $applicant->id)
            ->value('id');

        if (! $responseId) {
            return redirect()->route('public.vacancies.show', $vacancy)
                ->with('error', __('Сначала откликнитесь на вакансию — собеседование начинается с отклика.'));
        }

        $interview = AiInterview::firstOrCreate(
            ['vacancy_id' => $vacancy->id, 'applicant_id' => $applicant->id],
            ['vacancy_response_id' => $responseId],
        );

        /*
         * Кандидат отозвал отклик и откликнулся снова: собеседование осталось
         * прежним, а ссылка на отклик у него обнулилась. Без перепривязки
         * решение ставило статус «в никуда» — новый отклик так и висел
         * необработанным у работодателя.
         */
        if ($interview->vacancy_response_id !== $responseId) {
            $interview->update(['vacancy_response_id' => $responseId]);
        }

        return redirect()->route('applicant.ai.interview', $interview);
    }

    /**
     * Текущая страница собеседования — какая именно, решает стадия.
     */
    public function show(AiInterview $interview)
    {
        $this->mine($interview);

        if (! $interview->consented()) {
            return view('applicant.pages.ai-interview.consent', [
                'user' => auth()->user(),
                'applicant' => $interview->applicant,
                'interview' => $interview,
                'vacancy' => $interview->vacancy->load('employer'),
                'config' => $interview->config,
            ]);
        }

        // решение объявлено — показываем его, а не отправляем по кругу
        if ($interview->isAnnounced()) {
            return redirect()->route('applicant.ai.result', $interview);
        }

        /*
         * Ведём туда, где кандидат нужен сейчас — по стадии, а не по разбору.
         *
         * Раньше в разговор пускали только после готового разбора документов.
         * Разбор идёт в очереди, и если её обработчик не запущен или модель
         * занята, кандидат часами смотрел на «ИИ разбирает документы» и не мог
         * начать. Теперь разговор начинается сразу, а разбор догоняет в фоне.
         */
        if ($interview->stage === 'interview') {
            return redirect()->route('applicant.ai.chat', $interview);
        }

        if (in_array($interview->stage, ['test', 'decision', 'done'], true)) {
            return redirect()->route('applicant.ai.task', $interview);
        }

        return redirect()->route('applicant.ai.documents', $interview);
    }

    /**
     * Следующий шаг собеседования — если очередь его не делает.
     *
     * Страницы кандидата зовут это в фоне: после ответа в разговоре и пока
     * проверяется задание. Так оценки, проверка и решение идут и без
     * обработчика очереди (подробности — в InterviewPump).
     */
    public function pump(AiInterview $interview, \App\Services\Ai\InterviewPump $pump)
    {
        $this->mine($interview);

        @set_time_limit(180);
        ignore_user_abort(true);

        $state = $pump->step($interview);

        return response()->json($state + [
            'result_url' => $state['decided'] ? route('applicant.ai.result', $interview) : null,
            'next_url' => route('applicant.ai.interview', $interview),
        ]);
    }

    /**
     * Результат собеседования.
     *
     * Кандидат видит текст решения и ничего сверх него: ни баллов, ни разбора
     * по критериям, ни оценок своих ответов. Внутренние цифры адресованы
     * работодателю, а человеку нужен понятный ответ.
     */
    public function result(AiInterview $interview)
    {
        $this->mine($interview);

        $decision = $interview->latestDecision;

        /*
         * Ответ ещё не отправлен — его нет и для кандидата.
         *
         * Решение ИИ сначала смотрит работодатель: подтверждает или меняет.
         * До этого кандидат не видит ни итога, ни рекомендации — только то,
         * что ответ придёт в обещанный срок.
         */
        if (! $decision || ! $decision->isReleased()) {
            return redirect()->route('applicant.ai.index')
                ->with('status', __('Ответ по этому собеседованию ещё готовится — мы пришлём его в уведомления.'));
        }

        return view('applicant.pages.ai-interview.result', [
            'user' => auth()->user(),
            'applicant' => $interview->applicant,
            'interview' => $interview,
            'vacancy' => $interview->vacancy,
            'decision' => $decision,
        ]);
    }

    /**
     * Кандидат удаляет свои данные по этому собеседованию.
     *
     * Право принадлежит ему, а не работодателю, поэтому кнопка есть и здесь, не
     * только в панели: просить об удалении через того, кто тебе отказал, —
     * странная процедура.
     *
     * Удаляется всё, включая аудит обращений к модели: в промптах лежат ответы
     * человека и содержимое его документов. Отклик остаётся — он принадлежит
     * воронке найма, а не собеседованию.
     */
    public function purge(AiInterview $interview)
    {
        $this->mine($interview);

        $interview->eraseCompletely();

        return redirect()->route('applicant.ai.index')
            ->with('status', __('Ваши данные по этому собеседованию удалены.'));
    }

    /**
     * Согласие получено.
     */
    public function consent(Request $request, AiInterview $interview)
    {
        $this->mine($interview);

        $request->validate([
            // «accepted» вместо boolean: галочка обязана быть поставлена,
            // а не просто присутствовать в запросе
            'consent' => 'accepted',
        ], [
            'consent.accepted' => __('Без согласия ИИ-собеседование начать нельзя.'),
        ]);

        if (! $interview->consented()) {
            $interview->update([
                'consent_at' => now(),
                'consent_ip' => $request->ip(),
                'stage' => 'documents',
            ]);
        }

        return redirect()->route('applicant.ai.documents', $interview)
            ->with('status', __('Согласие получено. Загрузите дипломы и сертификаты, если они есть.'));
    }

    /**
     * Кандидат отказался. Отклик остаётся — его рассмотрит человек.
     */
    public function decline(AiInterview $interview)
    {
        $this->mine($interview);

        /*
         * Отказаться можно от того, что ещё не состоялось.
         *
         * После объявленного решения это уже не отказ от собеседования, а
         * удаление законченного отбора: работодатель лишился бы отчёта по
         * кандидату, которому сам же ответил, и увидел бы на месте кандидата
         * пустоту. Право удалить свои данные у человека остаётся — оно живёт
         * на странице результата отдельной кнопкой, и сообщение там говорит
         * правду о том, что происходит.
         *
         * Спрашиваем про запись решения, а не про пометку исхода. Разбор
         * документов, провалившийся дважды, ставит исход «решает человек», но
         * никакого решения при этом не объявлено и никто ничего не получил:
         * запретить отказ в этом случае значило бы запереть кандидата в
         * собеседовании, из которого нет ни выхода, ни способа удалить о себе
         * данные — страница результата без записи решения не открывается.
         */
        if ($interview->latestDecision) {
            return redirect()->route('applicant.ai.index')
                ->with('error', __('Собеседование уже завершено — отказаться от него нельзя. Ответ придёт в уведомления.'));
        }

        // Собеседование удаляем целиком, а не помечаем отказом: несостоявшийся
        // разговор хранить незачем, а отклик живёт своей жизнью. Вернуться и
        // согласиться позже можно — запись создастся заново. Файлы уходят
        // вместе со строками: иначе диплом остался бы на диске у человека,
        // который считает, что отказался.
        $interview->eraseCompletely();

        return redirect()->route('public.responses.index')
            ->with('status', __('Вы отказались от ИИ-собеседования. Отклик остался — его рассмотрит работодатель.'));
    }

    /**
     * Документы: дипломы и сертификаты.
     */
    public function documents(AiInterview $interview)
    {
        $this->mine($interview);

        abort_unless($interview->consented(), 403, __('Сначала нужно согласие.'));

        return view('applicant.pages.ai-interview.documents', [
            'user' => auth()->user(),
            'applicant' => $interview->applicant,
            'interview' => $interview,
            'vacancy' => $interview->vacancy,
            'documents' => $interview->documents()->latest('id')->get(),
            'resume' => $interview->applicant->resume()->latest('id')->first(),
            'kinds' => AiCandidateDocument::KINDS,
            'maxDocuments' => self::MAX_DOCUMENTS,
            'maxKb' => self::MAX_KB,
        ]);
    }

    /**
     * Загрузка документа.
     */
    public function upload(Request $request, AiInterview $interview)
    {
        $this->mine($interview);

        abort_unless($interview->consented(), 403);

        if ($interview->documentsLocked()) {
            return back()->with('error', __('Документы уже переданы на разбор — менять их сейчас нельзя.'));
        }

        $validated = $request->validate([
            'kind' => 'required|in:'.implode(',', array_keys(AiCandidateDocument::KINDS)),
            'file' => 'required|file|max:'.self::MAX_KB.'|mimes:pdf,jpg,jpeg,png,docx,txt',
        ], [], ['file' => __('файл')]);

        if ($interview->documents()->count() >= self::MAX_DOCUMENTS) {
            return back()->with('error', __('Больше :count документов не нужно — уберите лишние, если хотите заменить.',
                ['count' => self::MAX_DOCUMENTS]));
        }

        $file = $request->file('file');

        // приватный диск: документ отдаётся только через контроллер с проверкой
        // прав, как документы резюме
        $path = $file->store('ai/documents/'.$interview->id, TextExtractor::DISK);

        $document = AiCandidateDocument::create([
            'ai_interview_id' => $interview->id,
            'kind' => $validated['kind'],
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        $this->readDocument($document);

        return back()->with(
            $document->status === 'unreadable' ? 'error' : 'status',
            $document->status === 'unreadable'
                ? __('Файл сохранён, но прочитать его не удалось: :reason', ['reason' => __($document->failure_reason)])
                : __('Документ загружен.'),
        );
    }

    /**
     * Удалить документ вместе с файлом.
     */
    public function destroyDocument(AiInterview $interview, AiCandidateDocument $document)
    {
        $this->mine($interview);

        abort_if($document->ai_interview_id !== $interview->id, 403);

        if ($interview->documentsLocked()) {
            return back()->with('error', __('Документы уже переданы на разбор — менять их сейчас нельзя.'));
        }

        Storage::disk(TextExtractor::DISK)->delete($document->path);
        $document->delete();

        return back()->with('status', __('Документ удалён.'));
    }

    /**
     * Отдать файл владельцу. Работодатель открывает его из своего отчёта
     * (AiReportController::document).
     */
    public function file(AiInterview $interview, AiCandidateDocument $document)
    {
        $this->mine($interview);

        abort_if($document->ai_interview_id !== $interview->id, 403);
        abort_unless(Storage::disk(TextExtractor::DISK)->exists($document->path), 404);

        return Storage::disk(TextExtractor::DISK)
            ->response($document->path, $document->original_name);
    }

    /**
     * Документы собраны, идём дальше.
     *
     * Дипломы необязательны: их может не быть вовсе, и это не причина не
     * собеседовать человека — умение проверяется вопросами и заданием, а не
     * корочкой.
     */
    public function proceed(AiInterview $interview)
    {
        $this->mine($interview);

        abort_unless($interview->consented(), 403);

        // решение уже объявлено — разбирать и собеседовать поздно
        if ($interview->isDecided()) {
            return redirect()->route('applicant.ai.interview', $interview);
        }

        /*
         * Разбор запускаем, только если он не идёт и не сделан.
         *
         * Повторное нажатие не должно ставить его в очередь второй раз. Но
         * зависший или упавший разбор надо запускать снова — повтор безвреден:
         * разбор документа проверяет, не сделан ли он уже.
         */
        if (! $interview->analysisRunning() && $interview->analysis_status !== 'ready') {
            $interview->update([
                'analysis_status' => 'pending',
                'analysis_started_at' => now(),
            ]);

            /*
             * Цепочкой, а не пачкой независимых задач: сверка требований
             * опирается на вердикты по документам, и запустить её раньше —
             * значит сверять с пустотой.
             */
            $chain = $interview->documents()
                ->whereIn('status', ['pending', 'failed'])
                ->pluck('id')
                ->map(fn (int $id) => new AnalyzeCandidateDocument($id))
                ->push(new BuildRequirementMatrix($interview->id))
                ->all();

            Bus::chain($chain)->dispatch();
        }

        /*
         * В разговор — сразу.
         *
         * Ждать разбора больше не нужно: первые вопросы ИИ задаёт по резюме и
         * специальности кандидата, а когда разбор готов, подключает и его —
         * несостыковки и требования, которые документы не подтвердили.
         */
        if (in_array($interview->stage, ['consent', 'documents'], true)) {
            $interview->update(['stage' => 'interview']);
        }

        // сразу в чат: через show() сообщение потерялось бы на втором редиректе
        return redirect()->route($interview->stage === 'interview' ? 'applicant.ai.chat' : 'applicant.ai.interview', $interview)
            ->with('status', __('Документы приняты. ИИ разбирает их в фоне, а собеседование начинается сейчас.'));
    }

    /**
     * Прочитать документ: что может — сервер, остальное оставляем модели.
     */
    private function readDocument(AiCandidateDocument $document): void
    {
        $result = $this->extractor->extract($document->path, $document->mime);

        /*
         * Текст вычищается от персональных данных сразу при загрузке, а не
         * перед оценкой. Так в базе не оказывается копии диплома с возрастом и
         * национальностью: очистка на входе означает, что этих данных у нас
         * просто нет, а не что мы обещали их не смотреть.
         */
        $text = $result['text'] ? $this->redactor->clean($result['text']) : null;

        $document->update([
            'extracted_text' => $text,
            'status' => $result['readable'] ? 'pending' : 'unreadable',
            'failure_reason' => $result['reason'],
        ]);
    }

    /**
     * Собеседование принадлежит текущему соискателю.
     */
    private function mine(AiInterview $interview): void
    {
        $applicant = auth()->user()->applicant;

        abort_if(! $applicant || $interview->applicant_id !== $applicant->id, 403);
    }

    /**
     * Анкета соискателя или перенаправление на её заполнение.
     */
    private function applicant()
    {
        $applicant = auth()->user()->applicant;

        return $applicant ?: redirect()->route('public.applicants.create')
            ->with('error', __('Сначала заполните анкету соискателя.'));
    }
}
