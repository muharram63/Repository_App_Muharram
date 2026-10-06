<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\PortfolioMedia;
use App\Models\PortfolioWork;
use App\Models\WorkCategory;
use App\Services\Media\MediaRejected;
use App\Services\Media\MediaStore;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Портфолио мастера в кабинете соискателя: выполненные работы с фото и видео.
 *
 * Форма отправляется скриптом страницы (с полосой загрузки и обложками видео),
 * но работает и обычной отправкой — тогда просто без обложек. Поэтому каждое
 * действие отвечает и JSON, и переадресацией.
 */
class PortfolioController extends Controller
{
    public function __construct(private readonly MediaStore $store)
    {
    }

    public function index()
    {
        $applicant = $this->applicant();

        $works = $applicant->portfolioWorks()->with(['media', 'category'])->get();

        return view('applicant.pages.portfolio.index', [
            'user' => auth()->user(),
            'applicant' => $applicant,
            'works' => $works,
            'maxWorks' => PortfolioWork::MAX_WORKS,
        ]);
    }

    public function create()
    {
        $applicant = $this->applicant();

        if ($applicant->portfolioWorks()->count() >= PortfolioWork::MAX_WORKS) {
            return redirect()->route('applicant.portfolio.index')
                ->with('error', __('В портфолио уже :count работ — это предел. Удалите старую работу, чтобы добавить новую.', ['count' => PortfolioWork::MAX_WORKS]));
        }

        return view('applicant.pages.portfolio.create', $this->formData($applicant, new PortfolioWork));
    }

    public function store(Request $request)
    {
        $applicant = $this->applicant();

        if ($applicant->portfolioWorks()->count() >= PortfolioWork::MAX_WORKS) {
            throw ValidationException::withMessages([
                'title' => __('В портфолио уже :count работ — это предел. Удалите старую работу, чтобы добавить новую.', ['count' => PortfolioWork::MAX_WORKS]),
            ]);
        }

        $data = $this->validated($request);
        $files = $this->classify($request, new PortfolioWork);

        $work = PortfolioWork::create(Arr::only($data, ['title', 'work_category_id', 'description', 'done_at']) + [
            'applicant_id' => $applicant->id,
        ]);

        try {
            $this->saveFiles($work, $files, $request);
        } catch (ValidationException $e) {
            // работа без своих файлов не нужна — человек отправит форму заново
            $work->delete();

            throw $e;
        }

        return $this->done($request, __('Работа добавлена в портфолио.'));
    }

    public function edit(PortfolioWork $work)
    {
        $applicant = $this->owned($work);

        return view('applicant.pages.portfolio.edit', $this->formData($applicant, $work->load('media')));
    }

    public function update(Request $request, PortfolioWork $work)
    {
        $this->owned($work);

        $data = $this->validated($request);
        $files = $this->classify($request, $work->load('media'));

        $work->update(Arr::only($data, ['title', 'work_category_id', 'description', 'done_at']));

        // отметки «до / после» у уже загруженных файлов
        foreach ((array) $request->input('existing_stage', []) as $id => $stage) {
            $work->media->firstWhere('id', (int) $id)?->update([
                'stage' => in_array($stage, array_keys(PortfolioMedia::STAGES), true) ? $stage : null,
            ]);
        }

        $this->saveFiles($work, $files, $request);

        return $this->done($request, __('Работа сохранена.'));
    }

    public function destroy(PortfolioWork $work)
    {
        $this->owned($work);

        $work->delete();

        return redirect()->route('applicant.portfolio.index')->with('status', __('Работа удалена из портфолио.'));
    }

    public function destroyMedia(PortfolioWork $work, PortfolioMedia $media)
    {
        $this->owned($work);

        abort_unless($media->portfolio_work_id === $work->id, 404);

        $media->delete();

        return redirect()->route('applicant.portfolio.edit', $work)->with('status', __('Файл удалён.'));
    }

    /**
     * Поля работы. Файлы проверяются отдельно — по содержимому.
     */
    private function validated(Request $request): array
    {
        $stages = implode(',', array_keys(PortfolioMedia::STAGES));

        return $request->validate([
            'title' => 'required|string|min:3|max:120',
            'work_category_id' => 'required|integer|exists:work_categories,id',
            'description' => 'nullable|string|max:1000',
            'done_at' => 'nullable|date|before_or_equal:today',
            'media' => 'nullable|array|max:'.(PortfolioWork::MAX_PHOTOS + PortfolioWork::MAX_VIDEOS),
            // тип здесь не проверяем: заголовок браузера и расширение врут, а
            // MimeType по имени .mp4 даёт application/mp4 — тип определяет
            // MediaStore по содержимому файла (classify), с сообщением по каждому
            'media.*' => 'file|max:'.MediaStore::MAX_VIDEO_KB,
            'stages' => 'nullable|array',
            'stages.*' => 'nullable|in:'.$stages,
            'posters' => 'nullable|array',
            'posters.*' => 'nullable|file|max:'.MediaStore::MAX_POSTER_KB,
            'existing_stage' => 'nullable|array',
            'existing_stage.*' => 'nullable|in:'.$stages,
        ], [
            'media.max' => __('За один раз можно загрузить не больше :max файлов.'),
            'media.*.max' => __('Файл больше 100 МБ. Видео до 100 МБ, фото до 10 МБ.'),
            'media.*.uploaded' => __('Файл не загрузился: он слишком большой или связь прервалась.'),
        ]);
    }

    /**
     * Разложить новые файлы на фото и видео и проверить пределы.
     *
     * @return array<int, array{file:UploadedFile, kind:string}>
     */
    private function classify(Request $request, PortfolioWork $work): array
    {
        $files = [];
        $errors = [];
        $photos = $work->exists ? $work->photosCount() : 0;
        $videos = $work->exists ? $work->videosCount() : 0;

        foreach ((array) $request->file('media', []) as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $kind = $this->store->kind($file);

            if ($kind === null) {
                $errors['media.'.$index] = __('«:name» — не фото и не видео. Подходят JPG, PNG, WEBP, MP4, WEBM, MOV.', ['name' => $file->getClientOriginalName()]);

                continue;
            }

            if ($kind === 'photo' && $file->getSize() > MediaStore::MAX_PHOTO_KB * 1024) {
                $errors['media.'.$index] = __('Фото «:name» больше 10 МБ.', ['name' => $file->getClientOriginalName()]);

                continue;
            }

            $kind === 'photo' ? $photos++ : $videos++;
            $files[$index] = ['file' => $file, 'kind' => $kind];
        }

        if ($photos > PortfolioWork::MAX_PHOTOS) {
            $errors['media'] = __('В одной работе не больше :count фото.', ['count' => PortfolioWork::MAX_PHOTOS]);
        } elseif ($videos > PortfolioWork::MAX_VIDEOS) {
            $errors['media'] = __('В одной работе не больше :count видео.', ['count' => PortfolioWork::MAX_VIDEOS]);
        }

        if (! $work->exists && $files === [] && ! $errors) {
            $errors['media'] = __('Добавьте хотя бы одно фото или видео работы.');
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $files;
    }

    /**
     * Сохранить проверенные файлы. Файл, который не открылся при обработке,
     * отменяет всю загрузку — сохранённые до него файлы этого раза удаляются.
     */
    private function saveFiles(PortfolioWork $work, array $files, Request $request): void
    {
        $position = (int) $work->media()->max('position');
        $stages = (array) $request->input('stages', []);
        $posters = (array) $request->file('posters', []);
        $saved = [];

        foreach ($files as $index => $item) {
            try {
                $stored = $item['kind'] === 'photo'
                    ? $this->store->storePhoto($item['file'], $work->folder())
                    : $this->store->storeVideo($item['file'], $work->folder(), $posters[$index] ?? null);
            } catch (MediaRejected $e) {
                foreach ($saved as $media) {
                    $media->delete();
                }

                throw ValidationException::withMessages([
                    'media.'.$index => $item['file']->getClientOriginalName().': '.$e->getMessage(),
                ]);
            }

            $stage = $stages[$index] ?? null;

            $saved[] = $work->media()->create($stored + [
                'type' => $item['kind'],
                'stage' => in_array($stage, array_keys(PortfolioMedia::STAGES), true) ? $stage : null,
                'position' => ++$position,
            ]);
        }
    }

    private function done(Request $request, string $message)
    {
        $redirect = route('applicant.portfolio.index');

        if ($request->expectsJson()) {
            session()->flash('status', $message);

            return response()->json(['redirect' => $redirect]);
        }

        return redirect($redirect)->with('status', $message);
    }

    private function formData(Applicant $applicant, PortfolioWork $work): array
    {
        return [
            'user' => auth()->user(),
            'applicant' => $applicant,
            'work' => $work,
            'categories' => WorkCategory::ordered()->get(),
            'limits' => [
                'photos' => PortfolioWork::MAX_PHOTOS,
                'videos' => PortfolioWork::MAX_VIDEOS,
                'photoMb' => MediaStore::MAX_PHOTO_KB / 1024,
                'videoMb' => MediaStore::MAX_VIDEO_KB / 1024,
                'photoMimes' => MediaStore::PHOTO_MIMES,
                'videoMimes' => MediaStore::VIDEO_MIMES,
            ],
        ];
    }

    private function applicant(): Applicant
    {
        $applicant = auth()->user()->applicant;

        // анкеты ещё нет — сначала её; без неё портфолио не к чему привязать
        abort_unless($applicant, 403, __('Сначала заполните анкету соискателя.'));

        return $applicant;
    }

    private function owned(PortfolioWork $work): Applicant
    {
        $applicant = $this->applicant();

        // чужую работу не показываем вовсе — даже факт её существования
        abort_unless($work->applicant_id === $applicant->id, 404);

        return $applicant;
    }
}
