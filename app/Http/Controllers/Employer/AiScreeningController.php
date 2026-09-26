<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\AiInterview;
use App\Models\AiInterviewConfig;
use App\Models\AiInterviewCriterion;
use App\Models\Employer;
use App\Models\Vacancy;
use App\Services\Ai\AiInvalidAnswerException;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\CriteriaAdvisor;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

/**
 * Настройка ИИ-собеседования у вакансии.
 *
 * Работодатель описывает вакансию один раз, дальше каждый кандидат общается
 * только с моделью. Здесь он решает, включать ли это вообще, по каким критериям
 * оценивать и где проходят пороги.
 *
 * Критерии предлагает модель, но подтверждает человек. Неподтверждённый
 * критерий в оценке не участвует: правила, по которым потом отказывают людям,
 * должен утвердить человек, иначе объяснить отказ будет нечем.
 */
class AiScreeningController extends Controller
{
    public function __construct(private readonly CriteriaAdvisor $advisor)
    {
    }

    /**
     * Список вакансий работодателя с состоянием собеседования.
     */
    public function index()
    {
        $employer = $this->currentEmployer();

        $vacancies = $employer->vacancies()->latest()->get();

        // настройки и счётчики одним запросом на всё: иначе строка списка
        // ходила бы в базу трижды
        $configs = AiInterviewConfig::whereIn('vacancy_id', $vacancies->pluck('id'))
            ->get()->keyBy('vacancy_id');

        $criteriaCounts = AiInterviewCriterion::whereIn('vacancy_id', $vacancies->pluck('id'))
            ->confirmed()
            ->selectRaw('vacancy_id, count(*) as total')
            ->groupBy('vacancy_id')
            ->pluck('total', 'vacancy_id');

        $candidateCounts = AiInterview::whereIn('vacancy_id', $vacancies->pluck('id'))
            ->selectRaw('vacancy_id, count(*) as total')
            ->groupBy('vacancy_id')
            ->pluck('total', 'vacancy_id');

        return view('employer.pages.ai-screening.index', [
            'user' => auth()->user(),
            'employer' => $employer,
            'vacancies' => $vacancies,
            'configs' => $configs,
            'criteriaCounts' => $criteriaCounts,
            'candidateCounts' => $candidateCounts,
            'aiAvailable' => $this->advisor->available(),
        ]);
    }

    /**
     * Страница настройки одной вакансии.
     */
    public function show(Vacancy $vacancy)
    {
        $this->authorizeVacancy($vacancy);

        $config = $this->configFor($vacancy);

        return view('employer.pages.ai-screening.show', [
            'user' => auth()->user(),
            'employer' => $this->currentEmployer(),
            'vacancy' => $vacancy,
            'config' => $config,
            'criteria' => AiInterviewCriterion::where('vacancy_id', $vacancy->id)->ordered()->get(),
            'problems' => $config->problems(),
            'aiAvailable' => $this->advisor->available(),
            'levels' => AiInterviewConfig::LEVELS,
            'modes' => AiInterviewConfig::MODES,
            'locales' => config('app.available_locales', ['ru']),
        ]);
    }

    /**
     * Сохранение настроек.
     */
    public function updateConfig(Request $request, Vacancy $vacancy)
    {
        $this->authorizeVacancy($vacancy);

        $validated = $request->validate([
            'level' => 'required|in:'.implode(',', array_keys(AiInterviewConfig::LEVELS)),
            'language' => 'required|in:'.implode(',', config('app.available_locales', ['ru'])),
            'questions_count' => 'required|integer|min:3|max:20',
            'weight_documents' => 'required|integer|min:0|max:100',
            'weight_interview' => 'required|integer|min:0|max:100',
            'weight_test' => 'required|integer|min:0|max:100',
            'threshold_reject' => 'required|integer|min:0|max:100',
            'threshold_accept' => 'required|integer|min:0|max:100',
            'test_time_limit_minutes' => 'required|integer|min:10|max:480',
            'response_sla' => 'required|string|max:120',
            'decision_mode' => 'required|in:'.implode(',', array_keys(AiInterviewConfig::MODES)),
            'enabled' => 'nullable|boolean',
        ]);

        $config = $this->configFor($vacancy);
        $config->fill($validated);
        // чекбокс не приходит вовсе, когда снят
        $config->enabled = $request->boolean('enabled');

        /*
         * Включить собеседование с противоречивыми настройками нельзя.
         *
         * Проверяем на заполненной модели, а не на запросе: условия знает
         * problems(), и дублировать их в правилах валидации значило бы держать
         * два списка, которые однажды разойдутся.
         */
        if ($config->enabled && ($problems = $config->problems()) !== []) {
            $config->enabled = false;
            $config->save();

            return back()
                ->with('error', 'Настройки сохранены, но собеседование не включено: '.$problems[0])
                ->withInput();
        }

        $config->save();

        return back()->with('status', $config->enabled
            ? 'Настройки сохранены, ИИ-собеседование включено.'
            : 'Настройки сохранены.');
    }

    /**
     * Попросить модель предложить критерии.
     *
     * Прежние подтверждённые критерии не трогаем: работодатель мог уже
     * поправить их руками, и затирать эту работу нельзя. Заменяются только
     * неподтверждённые предложения — они и есть черновик.
     */
    public function suggestCriteria(Vacancy $vacancy)
    {
        $this->authorizeVacancy($vacancy);

        if (! $this->advisor->available()) {
            return back()->with('error', 'ИИ недоступен: проверьте GEMINI_API_KEY в .env.');
        }

        try {
            $suggested = $this->advisor->suggest($vacancy);
        } catch (AiInvalidAnswerException $e) {
            return back()->with('error', 'Модель ответила негодно. Попробуйте ещё раз '
                .'или добавьте критерии вручную.');
        } catch (AiUnavailableException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($suggested === []) {
            return back()->with('error', 'Не удалось разобрать вакансию. '
                .'Дополните описание или добавьте критерии вручную.');
        }

        $existing = AiInterviewCriterion::where('vacancy_id', $vacancy->id)
            ->confirmed()->pluck('key')->all();

        AiInterviewCriterion::where('vacancy_id', $vacancy->id)
            ->whereNull('confirmed_at')->delete();

        $added = 0;

        foreach ($suggested as $one) {
            // предложение, совпавшее с уже подтверждённым, не дублируем
            if (in_array($one['key'], $existing, true)) {
                continue;
            }

            AiInterviewCriterion::create($one + [
                'vacancy_id' => $vacancy->id,
                'source' => 'ai',
                // ждёт подтверждения: в оценке пока не участвует
                'confirmed_at' => null,
            ]);

            $added++;
        }

        return back()->with('status', $added > 0
            ? 'ИИ предложил критериев: '.$added.'. Проверьте их и подтвердите.'
            : 'Новых критериев не нашлось — все предложенные уже подтверждены.');
    }

    /**
     * Сохранение и подтверждение критериев.
     *
     * Работодатель правит названия, веса и вид, а подтверждает отметкой. Всё
     * приходит одной формой: подтверждать по одному было бы утомительно, а
     * смысл в том, чтобы человек посмотрел на набор целиком.
     */
    public function saveCriteria(Request $request, Vacancy $vacancy)
    {
        $this->authorizeVacancy($vacancy);

        $validated = $request->validate([
            'criteria' => 'required|array',
            'criteria.*.label' => 'required|string|min:2|max:120',
            'criteria.*.description' => 'nullable|string|max:400',
            'criteria.*.kind' => 'required|in:must,nice',
            'criteria.*.weight' => 'required|integer|min:1|max:100',
            'criteria.*.confirmed' => 'nullable|boolean',
        ]);

        $rows = AiInterviewCriterion::where('vacancy_id', $vacancy->id)->get()->keyBy('id');
        $saved = 0;

        foreach ($validated['criteria'] as $id => $fields) {
            $criterion = $rows->get((int) $id);

            // чужой идентификатор в форме просто игнорируем
            if (! $criterion) {
                continue;
            }

            $confirmed = (bool) ($fields['confirmed'] ?? false);

            $criterion->update([
                'label' => $fields['label'],
                'description' => $fields['description'] ?? null,
                'kind' => $fields['kind'],
                'weight' => $fields['weight'],
                // Снятая отметка возвращает критерий в черновик, а не удаляет:
                // работодатель мог передумать, и восстанавливать формулировку
                // заново было бы обидно.
                'confirmed_at' => $confirmed ? ($criterion->confirmed_at ?? now()) : null,
            ]);

            $saved++;
        }

        return back()->with('status', 'Критериев сохранено: '.$saved.'.');
    }

    /**
     * Добавить критерий руками.
     */
    public function addCriterion(Request $request, Vacancy $vacancy)
    {
        $this->authorizeVacancy($vacancy);

        $validated = $request->validate([
            'label' => 'required|string|min:2|max:120',
            'description' => 'nullable|string|max:400',
            'kind' => 'required|in:must,nice',
            'weight' => 'required|integer|min:1|max:100',
        ]);

        AiInterviewCriterion::create($validated + [
            'vacancy_id' => $vacancy->id,
            'key' => $this->freeKey($vacancy, $validated['label']),
            'source' => 'employer',
            // свой критерий работодателю подтверждать не нужно: он его и написал
            'confirmed_at' => now(),
            'position' => (int) AiInterviewCriterion::where('vacancy_id', $vacancy->id)->max('position') + 1,
        ]);

        return back()->with('status', 'Критерий добавлен.');
    }

    /**
     * Удалить критерий. Уже проставленные по нему оценки кандидатов остаются:
     * ссылка обнуляется, а не уносит проверку за собой.
     */
    public function destroyCriterion(Vacancy $vacancy, AiInterviewCriterion $criterion)
    {
        $this->authorizeVacancy($vacancy);

        abort_if($criterion->vacancy_id !== $vacancy->id, 403);

        $criterion->delete();

        return back()->with('status', 'Критерий удалён.');
    }

    /**
     * Свободный ключ для критерия, добавленного руками.
     */
    private function freeKey(Vacancy $vacancy, string $label): string
    {
        $base = \Illuminate\Support\Str::slug($label, '_');

        if ($base === '') {
            $base = 'k_'.substr(md5($label), 0, 8);
        }

        $base = \Illuminate\Support\Str::limit($base, 32, '');
        $key = $base;
        $suffix = 2;

        while (AiInterviewCriterion::where('vacancy_id', $vacancy->id)->where('key', $key)->exists()) {
            $key = $base.'_'.$suffix++;
        }

        return $key;
    }

    /**
     * Настройки вакансии, создавая их при первом заходе.
     *
     * Строка создаётся сразу со значениями по умолчанию и выключенным
     * собеседованием: иначе страница настроек пришлось бы рисовать в двух
     * видах — для существующих настроек и для несуществующих.
     */
    private function configFor(Vacancy $vacancy): AiInterviewConfig
    {
        return AiInterviewConfig::firstOrCreate(['vacancy_id' => $vacancy->id]);
    }

    /**
     * Вакансия принадлежит компании текущего пользователя.
     */
    private function authorizeVacancy(Vacancy $vacancy): void
    {
        abort_if($vacancy->employer_id !== $this->currentEmployer()->id, 403);
    }

    /**
     * Анкета компании. Роль employer ещё не значит, что анкета заполнена, —
     * та же проверка, что в VacancyController.
     */
    private function currentEmployer(): Employer
    {
        $employer = auth()->user()?->employer;

        if (! $employer) {
            throw new HttpResponseException(
                redirect()->route('public.employers.create')
                    ->with('error', 'Сначала заполните анкету работодателя.')
            );
        }

        return $employer;
    }
}
