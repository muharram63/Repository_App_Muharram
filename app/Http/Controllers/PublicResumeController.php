<?php

namespace App\Http\Controllers;

use App\Models\Resume;
use App\Models\Skill;
use App\Models\SkillAttempt;
use Illuminate\Http\Request;

class PublicResumeController extends Controller
{
    /**
     * Каталог резюме соискателей.
     */
    public function index(Request $request)
    {
        $query = trim((string) $request->input('q'));
        $exp = in_array($request->input('exp'), ['noexp', 'junior', 'middle', 'senior'], true)
            ? $request->input('exp')
            : null;

        $ranges = [
            'noexp' => [0, 0],
            'junior' => [1, 2],
            'middle' => [3, 5],
            'senior' => [6, 99],
        ];

        // фильтр по навыку, подтверждённому заданием на платформе
        $proven = (int) $request->input('proven') ?: null;

        // портфолио мастера: any — есть хоть одна работа, slug — работы этого вида
        $work = (string) $request->input('work');
        $workCategory = $work !== '' && $work !== 'any'
            ? \App\Models\WorkCategory::where('slug', $work)->first()
            : null;
        $work = $work === 'any' || $workCategory ? $work : null;

        // поиск и фильтр считаем на сервере: постранично клиентский
        // перебор карточек работал бы только внутри одной страницы
        $resumes = Resume::with('applicant.user')
            // подтверждения грузим сразу: иначе каждая карточка ходила бы в базу сама
            ->with(['applicant.skillAttempts' => fn ($a) => $a
                ->whereNotNull('finished_at')
                ->where('score', '>=', SkillAttempt::PASSING)
                ->with('test.skill')])
            // число работ в портфолио — на карточку, без запроса из каждой.
            // Стоит после вложенных with('applicant.…'): поставленный после
            // него вложенный with перезаписал бы ограничение на 'applicant' пустым
            ->with(['applicant' => fn ($a) => $a->withCount(['portfolioWorks', 'reviews'])->withAvg('reviews', 'rating')])
            ->withCount('responses')
            ->whereHas('applicant.user', fn ($q) => $q->activeAccount()->whereDoesntHave(
                'settings',
                fn ($s) => $s->where('hide_profile', true)
            ))
            ->when($proven, fn ($q) => $q->whereHas(
                'applicant.skillAttempts',
                fn ($a) => $a->whereNotNull('finished_at')
                    ->where('score', '>=', SkillAttempt::PASSING)
                    ->whereHas('test', fn ($t) => $t->where('skill_id', $proven))
            ))
            ->when($work, fn ($q) => $q->whereHas(
                'applicant.portfolioWorks',
                fn ($w) => $w->when($workCategory, fn ($c) => $c->where('work_category_id', $workCategory->id))
            ))
            ->when($exp, fn ($q) => $q->whereBetween('experience_years', $ranges[$exp]))
            ->when($query !== '', fn ($q) => $q->where(function ($x) use ($query) {
                $x->where('profession', 'like', "%{$query}%")
                    ->orWhere('desired_position', 'like', "%{$query}%")
                    ->orWhere('skills', 'like', "%{$query}%")
                    ->orWhereHas('applicant', fn ($a) => $a->where('city', 'like', "%{$query}%"));
            }))
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return view('public.pages.resumes.index', [
            'resumes' => $resumes,
            'query' => $query,
            'exp' => $exp ?? 'all',
            'proven' => $proven,
            'work' => $work,
            // в фильтре — только виды работ, по которым у кого-то есть портфолио
            'workCategories' => \App\Models\WorkCategory::ordered()->whereIn(
                'id',
                \App\Models\PortfolioWork::query()->select('work_category_id')->whereNotNull('work_category_id')
            )->get(),
            // в фильтре только навыки, по которым кто-то реально прошёл задание:
            // выбор из пустых вариантов ничего бы не находил
            'provenSkills' => Skill::whereHas(
                'tests.attempts',
                fn ($a) => $a->whereNotNull('finished_at')->where('score', '>=', SkillAttempt::PASSING)
            )->orderBy('name')->get(),
        ]);
    }

    /**
     * Страница одного резюме.
     */
    public function show(Request $request, Resume $resume)
    {
        $owner = $resume->applicant?->user;
        $privileged = $this->isPrivileged($owner);

        // скрытую анкету и закрытый аккаунт видят только владелец и администратор
        abort_if(
            ! $privileged && $owner && ($owner->setting('hide_profile') || $owner->isBlocked()),
            404
        );

        $this->countView($request, $resume);

        $resume->load('applicant.user', 'responses.employer.user')->loadCount('responses');

        return view('public.pages.resumes.show', [
            'resume' => $resume,
            // портфолио дополняет резюме: работы мастера показываются под ним
            'works' => $resume->applicant
                ? $resume->applicant->portfolioWorks()->with(['media', 'category'])->get()
                : collect(),
            'ratingAvg' => ($rating = \App\Models\MasterReview::summaryFor($resume->applicant?->id))['avg'],
            'ratingCount' => $rating['count'],
            'hideContacts' => (bool) $owner && $owner->setting('hide_contacts') && ! $privileged,
        ]);
    }

    /**
     * Владелец анкеты или администратор.
     */
    private function isPrivileged(?\App\Models\User $owner): bool
    {
        $user = auth()->user();

        return $user && ($user->role === 'admin' || ($owner && $user->id === $owner->id));
    }

    /**
     * Один просмотр на сессию и не считаем владельца резюме.
     */
    private function countView(Request $request, Resume $resume): void
    {
        $user = $request->user();

        if ($user && $resume->applicant && $resume->applicant->user_id === $user->id) {
            return;
        }

        $seen = $request->session()->get('viewed_resumes', []);

        if (in_array($resume->id, $seen, true)) {
            return;
        }

        $resume->increment('views');
        $seen[] = $resume->id;
        $request->session()->put('viewed_resumes', $seen);
    }
}
