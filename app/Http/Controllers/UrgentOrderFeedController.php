<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\UrgentOrder;
use App\Models\UrgentOrderOffer;
use App\Models\WorkCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Лента срочных заявок для мастеров.
 *
 * Открыта всем, и гостям тоже: мастер должен увидеть, что работа есть, ещё до
 * регистрации. Точного адреса в ленте нет ни для кого — только город и район.
 *
 * Порядок: сначала «Срочно» (сегодня и сутки), внутри — по ближайшему сроку.
 */
class UrgentOrderFeedController extends Controller
{
    public function index(Request $request)
    {
        // просроченные закрываем до выдачи ленты — планировщик запущен не всегда
        app(\App\Services\Orders\OrderExpiry::class)->sweepSoon();

        $user = $request->user();
        $applicant = $user?->applicant;

        $category = WorkCategory::where('slug', (string) $request->input('category'))->first();
        $cityId = (int) $request->input('city') ?: null;
        // «подходящие мне» — виды работ из моего портфолио
        // запрос без сортировки связи portfolioWorks(): MySQL 8 не допускает
        // DISTINCT вместе с ORDER BY по колонке, которой нет в выборке (3065)
        $myCategories = $applicant
            ? \App\Models\PortfolioWork::where('applicant_id', $applicant->id)
                ->whereNotNull('work_category_id')->distinct()->pluck('work_category_id')
            : collect();
        $mine = $request->boolean('mine') && $myCategories->isNotEmpty();
        // «мои отклики» — заявки, на которые я откликался, в любом статусе
        $offered = $request->boolean('offered') && $applicant;

        $orders = UrgentOrder::query()
            ->with(['category', 'city', 'photos'])
            ->withCount(['offers as active_offers_count' => fn (Builder $o) => $o->whereIn('status', [
                UrgentOrderOffer::STATUS_PENDING, UrgentOrderOffer::STATUS_CHOSEN,
            ])])
            ->when($offered, fn (Builder $q) => $q
                ->whereHas('offers', fn (Builder $o) => $o->where('applicant_id', $applicant->id))
                ->latest('id'))
            ->when(! $offered, fn (Builder $q) => $q
                ->open()
                // срок вышел, а заявку ещё не закрыли — в ленте ей уже не место
                ->where('deadline_at', '>', now())
                ->when($category, fn (Builder $c) => $c->where('work_category_id', $category->id))
                ->when($mine && ! $category, fn (Builder $c) => $c->whereIn('work_category_id', $myCategories))
                ->when($cityId, fn (Builder $c) => $c->where('city_id', $cityId))
                ->orderByRaw('case when deadline_option in (?, ?) then 0 else 1 end', UrgentOrder::URGENT_DEADLINES)
                ->orderBy('deadline_at'))
            ->paginate(20)
            ->withQueryString();

        return view('public.pages.orders.index', [
            'orders' => $orders,
            'categories' => WorkCategory::ordered()->get(),
            'cities' => City::whereIn('id', UrgentOrder::open()->whereNotNull('city_id')->select('city_id'))
                ->orderBy('region')->get(),
            'category' => $category,
            'cityId' => $cityId,
            'mine' => $mine,
            'offered' => (bool) $offered,
            'isMaster' => $myCategories->isNotEmpty(),
            'isApplicant' => (bool) $applicant,
        ]);
    }
}
