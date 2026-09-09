<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Models\SkillAttempt;
use App\Models\SkillTest;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\SkillExaminer;
use Illuminate\Http\Request;

/**
 * Проверка навыков делом.
 *
 * Кандидат выбирает навык и уровень, проходит короткое задание и получает
 * подтверждённый результат. Варианты ответа сверяются по ключу здесь же,
 * а к модели обращаемся только за свободными ответами — там, где человек
 * написал своими словами.
 */
class SkillCheckController extends Controller
{
    public function __construct(private readonly SkillExaminer $examiner)
    {
    }

    /**
     * Справочник навыков, подтверждённые результаты и история попыток.
     */
    public function index()
    {
        $applicant = $this->applicant();

        if (! $applicant instanceof \App\Models\Applicant) {
            return $applicant;
        }

        return view('applicant.pages.skills.index', [
            'user' => auth()->user(),
            'applicant' => $applicant,
            'skills' => Skill::orderBy('area')->orderBy('name')->get()->groupBy('area'),
            'badges' => SkillAttempt::badgesFor($applicant),
            'attempts' => SkillAttempt::where('applicant_id', $applicant->id)
                ->whereNotNull('finished_at')
                ->with('test.skill')
                ->latest('finished_at')
                ->take(10)
                ->get(),
            'enabled' => $this->examiner->available(),
        ]);
    }

    /**
     * Начать проверку: подобрать вариант задания и завести попытку.
     */
    public function start(Request $request)
    {
        $applicant = $this->applicant();

        if (! $applicant instanceof \App\Models\Applicant) {
            return $applicant;
        }

        $validated = $request->validate([
            'skill_id' => 'required|exists:skills,id',
            'level' => 'required|in:'.implode(',', array_keys(Skill::LEVELS)),
        ]);

        $skill = Skill::findOrFail($validated['skill_id']);

        // незаконченная попытка по этому навыку — возвращаем в неё, а не плодим
        // новые: иначе счётчик времени обнулялся бы простым нажатием кнопки
        $running = SkillAttempt::where('applicant_id', $applicant->id)
            ->whereNull('finished_at')
            ->whereHas('test', fn ($t) => $t->where('skill_id', $skill->id))
            ->latest('id')
            ->first();

        if ($running && ! $running->expired()) {
            return redirect()->route('applicant.skills.attempt', $running);
        }

        $running?->closeExpired();

        if ($until = SkillAttempt::cooldownUntil($applicant->id, $skill->id)) {
            return back()->with('error', sprintf(
                'Следующая попытка по навыку «%s» будет доступна через %d мин.',
                $skill->name,
                max(1, (int) ceil(now()->diffInSeconds($until, false) / 60)),
            ));
        }

        try {
            $test = $this->pickTest($skill, $validated['level'], $applicant->id);
        } catch (AiUnavailableException $e) {
            return back()->with('error', $e->getMessage());
        }

        if (! $test) {
            return back()->with('error', 'Не удалось составить задание. Попробуйте ещё раз.');
        }

        $attempt = SkillAttempt::create([
            'applicant_id' => $applicant->id,
            'skill_test_id' => $test->id,
            'answers' => [],
            'expires_at' => now()->addMinutes(SkillAttempt::MINUTES),
        ]);

        return redirect()->route('applicant.skills.attempt', $attempt);
    }

    /**
     * Прохождение задания или его разбор, если попытка уже завершена.
     */
    public function attempt(SkillAttempt $attempt)
    {
        $this->mine($attempt);

        // брошенную попытку закрываем при первом же заходе: иначе задание
        // висело бы открытым бесконечно и блокировало новую попытку
        if (! $attempt->finished_at && $attempt->expired()) {
            $attempt->closeExpired();
        }

        $attempt->load('test.skill');

        return view('applicant.pages.skills.attempt', [
            'user' => auth()->user(),
            'applicant' => $attempt->applicant,
            'attempt' => $attempt,
            'test' => $attempt->test,
            // правильные ответы наружу не уходят, пока задание не сдано
            'questions' => $attempt->finished_at ? $attempt->test->questions : $attempt->test->publicQuestions(),
        ]);
    }

    /**
     * Приём ответов и проверка.
     */
    public function submit(Request $request, SkillAttempt $attempt)
    {
        $this->mine($attempt);

        abort_if($attempt->finished_at !== null, 403, 'Эта попытка уже завершена.');

        // ответ пришёл позже срока даже с учётом запаса — засчитывать нечего
        if ($attempt->expired()) {
            $attempt->closeExpired();

            return redirect()->route('applicant.skills.attempt', $attempt)
                ->with('error', 'Время на задание вышло — попытка закрыта.');
        }

        $questions = $attempt->test->questions ?? [];

        $validated = $request->validate([
            'option' => 'array',
            'option.*' => 'nullable|integer|min:0',
            'own' => 'array',
            'own.*' => 'nullable|string|max:600',
        ]);

        $answers = $this->collect($questions, $validated);

        try {
            $review = $this->check($attempt->test->skill, $questions, $answers);
        } catch (AiUnavailableException $e) {
            // попытку не закрываем: ответы сохранены, можно отправить повторно
            $attempt->update(['answers' => $answers]);

            return back()->with('error', $e->getMessage());
        }

        $correct = collect($review)->where('correct', true)->count();

        $attempt->update([
            'answers' => $answers,
            'review' => $review,
            'score' => $questions ? (int) round($correct / count($questions) * 100) : 0,
            'finished_at' => now(),
        ]);

        return redirect()->route('applicant.skills.attempt', $attempt)
            ->with('status', 'Задание проверено.');
    }

    /**
     * Ответы кандидата в едином виде: либо выбранный вариант, либо свой текст.
     *
     * @return array<int,array{option:?int,own:string}>
     */
    private function collect(array $questions, array $validated): array
    {
        $answers = [];

        foreach (array_keys($questions) as $i) {
            $own = trim((string) ($validated['own'][$i] ?? ''));
            $option = $validated['option'][$i] ?? null;

            $answers[$i] = [
                // свой ответ важнее выбранного варианта: человек не стал бы
                // его писать, если бы его устраивал готовый
                'option' => $own === '' && $option !== null ? (int) $option : null,
                'own' => $own,
            ];
        }

        return $answers;
    }

    /**
     * Проверка. Варианты сверяем по ключу, свободные ответы отдаём модели —
     * одним запросом на всю работу, а не по одному на вопрос.
     *
     * @return array<int,array{correct:bool,comment:string}>
     */
    private function check(Skill $skill, array $questions, array $answers): array
    {
        $review = [];
        $free = [];

        foreach ($questions as $i => $question) {
            $answer = $answers[$i] ?? ['option' => null, 'own' => ''];

            if ($answer['own'] !== '') {
                $free[$i] = [
                    'question' => $question['text'] ?? '',
                    'expected' => $question['options'][$question['answer']] ?? '',
                    'answer' => $answer['own'],
                ];

                continue;
            }

            if ($answer['option'] === null) {
                $review[$i] = ['correct' => false, 'comment' => 'Ответ не дан.'];

                continue;
            }

            $review[$i] = [
                'correct' => $answer['option'] === (int) ($question['answer'] ?? -1),
                'comment' => (string) ($question['explain'] ?? ''),
            ];
        }

        if ($free !== []) {
            // ключи сохраняем: модель отвечает по порядку, а вопросы — вразброс
            $graded = $this->examiner->grade($skill, array_values($free));

            foreach (array_keys($free) as $position => $index) {
                $review[$index] = $graded[$position] ?? ['correct' => false, 'comment' => 'Ответ не удалось проверить.'];
            }
        }

        ksort($review);

        return $review;
    }

    /**
     * Вариант задания, которого кандидат ещё не видел.
     *
     * Если непройденных вариантов нет, а банк не заполнен — просим модель
     * составить новый: повторно то же задание давать бессмысленно.
     */
    private function pickTest(Skill $skill, string $level, int $applicantId): ?SkillTest
    {
        $tests = SkillTest::where('skill_id', $skill->id)->where('level', $level)->get();

        $seen = SkillAttempt::where('applicant_id', $applicantId)
            ->whereIn('skill_test_id', $tests->pluck('id'))
            ->pluck('skill_test_id');

        if ($unseen = $tests->whereNotIn('id', $seen)->shuffle()->first()) {
            return $unseen;
        }

        if ($tests->count() < SkillTest::VARIANTS) {
            $variant = (int) $tests->max('variant') + 1;
            $questions = $this->examiner->compose($skill, $level, $variant);

            if ($questions === []) {
                return $tests->shuffle()->first();
            }

            return SkillTest::create([
                'skill_id' => $skill->id,
                'level' => $level,
                'variant' => $variant,
                'questions' => $questions,
            ]);
        }

        // все варианты пройдены — повторяем самый давний
        return $tests->shuffle()->first();
    }

    /**
     * Попытка принадлежит текущему соискателю.
     */
    private function mine(SkillAttempt $attempt): void
    {
        $applicant = auth()->user()->applicant;

        abort_if(! $applicant || $attempt->applicant_id !== $applicant->id, 403);
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
