<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Resume;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ResumeController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $applicant = $user->applicant;
        $resumes = $applicant ? $applicant->resume()->withCount('responses')->latest()->get() : collect();
        return view('applicant.pages.resumes.index',compact('resumes'),[
            'user' => $user,
            'applicant' => $applicant,
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        $applicant = $user->applicant;

        if (! $applicant) {
            return redirect()->route('public.applicants.create')
                ->with('error', 'Сначала заполните анкету соискателя.');
        }

        return view('applicant.pages.resumes.create', [
            'user' => $user,
            'applicant' => $applicant,
        ]);
    }

    public function store(Request $request)
    {
        $applicant = auth()->user()->applicant;

        if (! $applicant) {
            return redirect()->route('public.applicants.create')
                ->with('error', 'Сначала заполните анкету соискателя.');
        }

        $validated = $this->validateResume($request);
        $validated['applicant_id'] = $applicant->id;
        $validated = $this->handleDocuments($request, $validated);

        Resume::create($validated);

        return redirect()->route('applicant.resume.index')
            ->with('status', 'Резюме опубликовано.');
    }

    public function show(Resume $resume)
    {
        $applicant = $this->currentApplicant($resume);

        return view('applicant.pages.resumes.show', [
            'resume' => $resume,
            'user' => auth()->user(),
            'applicant' => $applicant,
        ]);
    }

    public function edit(Resume $resume)
    {
        $applicant = $this->currentApplicant($resume);

        return view('applicant.pages.resumes.edit', [
            'resume' => $resume,
            'user' => auth()->user(),
            'applicant' => $applicant,
        ]);
    }

    public function update(Request $request, Resume $resume)
    {
        $this->currentApplicant($resume);

        $resume->update($this->handleDocuments($request, $this->validateResume($request), $resume));

        return redirect()->route('applicant.resume.index')
            ->with('status', 'Резюме обновлено.');
    }

    public function destroy(Resume $resume)
    {
        $this->currentApplicant($resume);

        $resume->delete();

        return redirect()->route('applicant.resume.index')
            ->with('status', 'Резюме удалено.');
    }

    /**
     * Анкета текущего пользователя. 403, если резюме принадлежит другому соискателю.
     */
    private function currentApplicant(Resume $resume): Applicant
    {
        $applicant = auth()->user()->applicant;

        abort_if(! $applicant || $resume->applicant_id !== $applicant->id, 403);

        return $applicant;
    }

    /**
     * Общие правила для создания и изменения резюме. Публичные и статические:
     * по ним проверяется и обычная форма, и то, что собрал диалоговый помощник.
     */
    public static function rules(): array
    {
        return [
            'profession' => 'required|string|max:255',
            'experience_years' => 'required|integer|min:0|max:70',
            'desired_position' => 'required|string|max:255',
            'desired_salary' => 'required|integer|min:0',
            'url_website' => 'nullable|string|max:255',
            // колонка стала text: помощник собирает длинные перечни навыков
            'skills' => 'nullable|string|max:2000',
            'languages' => 'required|string|max:255',
            'place_work' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            // оформление приходит из помощника; обычная форма его не показывает
            'style' => 'nullable|in:'.implode(',', array_keys(Resume::STYLES)),
        ];
    }

    private function validateResume(Request $request): array
    {
        return $request->validate(static::rules() + [
            'documents' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
        ]);
    }

    /**
     * Загруженный файл кладём в storage/app/public/resumes, в колонку documents пишем путь.
     * Если файл не прислали — старое значение не трогаем.
     */
    private function handleDocuments(Request $request, array $validated, ?Resume $resume = null): array
    {
        if (! $request->hasFile('documents')) {
            unset($validated['documents']);

            return $validated;
        }

        if ($resume && $resume->documents) {
            // старые резюме лежали на публичном диске, новые — на приватном
            Storage::disk('local')->delete($resume->documents);
            Storage::disk('public')->delete($resume->documents);
        }

        // приватный диск: документ отдаётся только через SecureFileController
        $validated['documents'] = $request->file('documents')->store('resumes', 'local');

        return $validated;
    }
}
