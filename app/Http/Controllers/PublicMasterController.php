<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\PortfolioWork;
use App\Models\WorkCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Публичный профиль мастера — портфолио вместо резюме.
 *
 * Сантехнику или маляру резюме часто нечем заполнить, а показать сделанное
 * есть что. Эта страница — витрина таких работ; резюме, если оно есть,
 * доступно отсюда же ссылкой.
 */
class PublicMasterController extends Controller
{
    public const SORTS = [
        'rating' => 'Сначала с лучшей оценкой',
        'works' => 'Больше всего работ',
        'new' => 'Новые мастера',
    ];

    /**
     * Каталог мастеров: соискатели, у которых есть хотя бы одна работа в
     * портфолио. Скрытые профили и закрытые аккаунты сюда не попадают.
     */
    public function index(Request $request)
    {
        $query = trim((string) $request->input('q'));
        $category = WorkCategory::where('slug', (string) $request->input('category'))->first();
        $sort = array_key_exists($request->input('sort'), self::SORTS) ? $request->input('sort') : 'rating';

        $masters = Applicant::query()
            ->whereHas('portfolioWorks', fn (Builder $w) => $w->when(
                $category, fn (Builder $c) => $c->where('work_category_id', $category->id)
            ))
            ->whereHas('user', fn (Builder $u) => $u->activeAccount()->whereDoesntHave(
                'settings', fn (Builder $s) => $s->where('hide_profile', true)
            ))
            ->when($query !== '', fn (Builder $q) => $q->where(function (Builder $x) use ($query) {
                $x->where('city', 'like', "%{$query}%")
                    ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$query}%"))
                    ->orWhereHas('portfolioWorks', fn (Builder $w) => $w->where('title', 'like', "%{$query}%"));
            }))
            ->with('user')
            // три последние работы — для обложек на карточке
            ->with(['portfolioWorks' => fn ($w) => $w->with('media')->limit(3)])
            ->withCount(['portfolioWorks', 'reviews'])
            ->withAvg('reviews', 'rating')
            ->when($sort === 'rating', fn (Builder $q) => $q
                // без отзывов — в конце: и в MySQL, и в SQLite NULL при DESC идёт последним
                ->orderByDesc('reviews_avg_rating')
                ->orderByDesc('reviews_count')
                ->orderByDesc('portfolio_works_count'))
            ->when($sort === 'works', fn (Builder $q) => $q->orderByDesc('portfolio_works_count'))
            ->orderByDesc('id')
            ->paginate(24)
            ->withQueryString();

        // виды работ каждого мастера — одним запросом, без сортировки
        // (DISTINCT с ORDER BY связи MySQL 8 не принимает)
        $categoriesByMaster = PortfolioWork::query()
            ->whereIn('applicant_id', $masters->pluck('id'))
            ->whereNotNull('work_category_id')
            ->select('applicant_id', 'work_category_id')
            ->distinct()
            ->toBase()
            ->get()
            ->groupBy('applicant_id');

        $allCategories = WorkCategory::ordered()->get()->keyBy('id');

        return view('public.pages.masters.index', [
            'masters' => $masters,
            'masterCategories' => $categoriesByMaster->map(fn ($rows) => $rows
                ->map(fn ($row) => $allCategories->get($row->work_category_id))
                ->filter()->sortBy('position')->values()),
            // в фильтре — только виды работ, по которым есть мастера
            'categories' => WorkCategory::ordered()->whereIn(
                'id', PortfolioWork::query()->select('work_category_id')->whereNotNull('work_category_id')
            )->get(),
            'category' => $category,
            'query' => $query,
            'sort' => $sort,
            'sorts' => self::SORTS,
        ]);
    }

    public function show(Applicant $applicant)
    {
        $owner = $applicant->user;
        $viewer = auth()->user();
        $privileged = $viewer && ($viewer->role === 'admin' || ($owner && $viewer->id === $owner->id));

        // скрытый профиль и закрытый аккаунт — как у резюме: только владельцу и администратору
        abort_if(! $owner, 404);
        abort_if(! $privileged && ($owner->setting('hide_profile') || $owner->isBlocked()), 404);

        $works = $applicant->portfolioWorks()->with(['media', 'category'])->get();
        $reviews = $applicant->reviews()->with(['author', 'order.category'])->limit(30)->get();

        // пустая витрина посторонним не нужна; владельцу — подсказка, как её заполнить
        abort_if($works->isEmpty() && ! $privileged, 404);

        return view('public.pages.masters.show', [
            'applicant' => $applicant,
            'owner' => $owner,
            'works' => $works,
            'resumes' => $applicant->resume()->latest()->get(['id', 'profession']),
            'isOwner' => $viewer && $viewer->id === $owner->id,
            'hideContacts' => $owner->setting('hide_contacts') && ! $privileged,
            'categories' => $works->pluck('category')->filter()->unique('id')->values(),
            'reviews' => $reviews,
            'ratingAvg' => ($rating = \App\Models\MasterReview::summaryFor($applicant->id))['avg'],
            'ratingCount' => $rating['count'],
        ]);
    }
}
