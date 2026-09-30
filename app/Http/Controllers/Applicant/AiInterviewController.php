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

        return view('applicant.pages.ai-interview.index', [
            'user' => auth()->user(),
            'applicant' => $applicant,
            'interviews' => AiInterview::where('applicant_id', $applicant->id)
                ->with('vacancy.employer', 'latestDecision')
                ->latest('updated_at')
                ->get(),
        ]);
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
                ->with('status', 'По этой вакансии ИИ-собеседование не проводится — '
                    .'ваш отклик рассмотрит работодатель.');
        }

        // резюме — источник данных для разбора, без него собеседовать не о чем
        if (! $applicant->resume()->exists()) {
            return redirect()->route('applicant.resume.create')
                ->with('error', 'Сначала создайте резюме: ИИ-собеседование опирается на него.');
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
                ->with('error', 'Сначала откликнитесь на вакансию — '
                    .'собеседование начинается с отклика.');
        }

        $interview = AiInterview::firstOrCreate(
            ['vacancy_id' => $vacancy->id, 'applicant_id' => $applicant->id],
            ['vacancy_response_id' => $responseId],
        );

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
        if ($interview->isDecided() && $interview->latestDecision) {
            return redirect()->route('applicant.ai.result', $interview);
        }

        // Ведём туда, где кандидат нужен сейчас. Разбор мог закончиться, пока
        // он ходил по другим страницам, — возвращать его к документам значило
        // бы заставлять искать продолжение самому.
        if ($interview->analysis_status === 'ready') {
            if ($interview->stage === 'interview') {
                return redirect()->route('applicant.ai.chat', $interview);
            }

            if (in_array($interview->stage, ['test', 'decision', 'done'], true)) {
                return redirect()->route('applicant.ai.task', $interview);
            }
        }

        return redirect()->route('applicant.ai.documents', $interview);
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

        // решения ещё нет — возвращаем туда, где кандидат нужен
        if (! $decision) {
            return redirect()->route('applicant.ai.interview', $interview);
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
            ->with('status', 'Ваши данные по этому собеседованию удалены.');
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
            'consent.accepted' => 'Без согласия ИИ-собеседование начать нельзя.',
        ]);

        if (! $interview->consented()) {
            $interview->update([
                'consent_at' => now(),
                'consent_ip' => $request->ip(),
                'stage' => 'documents',
            ]);
        }

        return redirect()->route('applicant.ai.documents', $interview)
            ->with('status', 'Согласие получено. Загрузите дипломы и сертификаты, если они есть.');
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
            return redirect()->route('applicant.ai.result', $interview)
                ->with('error', 'Решение по вам уже принято — отказываться от собеседования поздно. '
                    .'Удалить свои данные можно кнопкой ниже.');
        }

        // Собеседование удаляем целиком, а не помечаем отказом: несостоявшийся
        // разговор хранить незачем, а отклик живёт своей жизнью. Вернуться и
        // согласиться позже можно — запись создастся заново. Файлы уходят
        // вместе со строками: иначе диплом остался бы на диске у человека,
        // который считает, что отказался.
        $interview->eraseCompletely();

        return redirect()->route('public.responses.index')
            ->with('status', 'Вы отказались от ИИ-собеседования. Отклик остался — '
                .'его рассмотрит работодатель.');
    }

    /**
     * Документы: дипломы и сертификаты.
     */
    public function documents(AiInterview $interview)
    {
        $this->mine($interview);

        abort_unless($interview->consented(), 403, 'Сначала нужно согласие.');

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

        $validated = $request->validate([
            'kind' => 'required|in:'.implode(',', array_keys(AiCandidateDocument::KINDS)),
            'file' => 'required|file|max:'.self::MAX_KB.'|mimes:pdf,jpg,jpeg,png,docx,txt',
        ], [], ['file' => 'файл']);

        if ($interview->documents()->count() >= self::MAX_DOCUMENTS) {
            return back()->with('error', 'Больше '.self::MAX_DOCUMENTS
                .' документов не нужно — уберите лишние, если хотите заменить.');
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
                ? 'Файл сохранён, но прочитать его не удалось: '.$document->failure_reason
                : 'Документ загружен.',
        );
    }

    /**
     * Удалить документ вместе с файлом.
     */
    public function destroyDocument(AiInterview $interview, AiCandidateDocument $document)
    {
        $this->mine($interview);

        abort_if($document->ai_interview_id !== $interview->id, 403);

        Storage::disk(TextExtractor::DISK)->delete($document->path);
        $document->delete();

        return back()->with('status', 'Документ удалён.');
    }

    /**
     * Отдать файл владельцу. Работодатель увидит его в отчёте позже —
     * сейчас доступ только у самого кандидата.
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

        // повторное нажатие не должно ставить разбор в очередь второй раз
        if ($interview->analysis_status === 'pending') {
            return redirect()->route('applicant.ai.documents', $interview)
                ->with('status', 'Разбор документов уже идёт — это занимает до минуты.');
        }

        /*
         * Разбор делается один раз.
         *
         * Кнопка «продолжить» остаётся достижимой и после того, как разбор
         * прошёл: стадия сменилась, но адрес никуда не делся. Повторный запуск
         * не просто тратил бы квоту — он переписывал бы сверку требований и
         * балл за документы у собеседования, решение по которому уже объявлено,
         * и в отчёте работодателя баллы разошлись бы с решением. Поэтому здесь
         * не «уже идёт», а «уже сделано»: дальше кандидата ведёт show().
         *
         * Неудавшийся разбор — другое дело: его и надо пробовать снова, иначе
         * сбой сети означал бы тупик.
         */
        if ($interview->analysis_status === 'ready' || $interview->isDecided()) {
            return redirect()->route('applicant.ai.interview', $interview);
        }

        $interview->update(['analysis_status' => 'pending']);

        /*
         * Цепочкой, а не пачкой независимых задач: сверка требований опирается
         * на вердикты по документам, и запустить её раньше — значит сверять с
         * пустотой. Bus::chain выполняет их по очереди и обрывает цепочку, если
         * звено упало окончательно.
         */
        $chain = $interview->documents()
            ->whereIn('status', ['pending', 'failed'])
            ->pluck('id')
            ->map(fn (int $id) => new AnalyzeCandidateDocument($id))
            ->push(new BuildRequirementMatrix($interview->id))
            ->all();

        Bus::chain($chain)->dispatch();

        return redirect()->route('applicant.ai.documents', $interview)
            ->with('status', 'Документы приняты. ИИ разбирает их — это занимает до минуты.');
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
            ->with('error', 'Сначала заполните анкету соискателя.');
    }
}
