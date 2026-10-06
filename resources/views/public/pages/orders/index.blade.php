@extends('public.layouts.app')
@section('content')

    <style>
        .uo-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
        .uo-filters { display: flex; flex-direction: column; gap: 10px; margin-bottom: 18px; }
        .uo-chips { display: flex; gap: 8px; overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 2px; }
        .uo-chip {
            flex: none; padding: 8px 13px; border-radius: 999px; border: 1px solid var(--border); background: var(--surface);
            color: var(--text); text-decoration: none; font-size: 13px; font-weight: 600; white-space: nowrap;
        }
        .uo-chip.on { background: var(--accent); border-color: var(--accent); color: #fff; }
        .uo-chip.hot { border-color: #FCA5A5; }
        .uo-city { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .uo-city select {
            padding: 9px 12px; border-radius: 10px; border: 1px solid var(--border); background: var(--surface);
            color: var(--text); font: inherit; font-size: 14px; max-width: 100%;
        }
        .uo-offers-count { font-size: 12.5px; color: var(--text-muted); }
        @media (max-width: 640px) {
            .uo-top .uo-btn { width: 100%; }
            .uo-city select { width: 100%; font-size: 16px; }
        }
    </style>

    <section class="uo-page">
        <div class="container">
            <div class="uo-top">
                <div>
                    <h1 class="uo-h1">🔥 {{ __('Срочные заказы') }}</h1>
                    <p class="uo-lead" style="margin:0;">{{ __('Людям нужен мастер сегодня или в ближайшие дни. Откликнитесь — заказчик посмотрит ваши работы и выберет.') }}</p>
                </div>
                <a class="uo-btn primary" href="{{ route('public.orders.create') }}">🛠 {{ __('Нужен мастер') }}</a>
            </div>

            @include('public.pages.orders._styles')

            @if($isApplicant && ! $isMaster)
                <div class="uo-card" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; padding:14px 18px;">
                    <span style="font-size:24px;">📸</span>
                    <span style="flex:1; min-width:200px; font-size:14px;">
                        {{ __('Чтобы откликаться на заказы, добавьте в портфолио хотя бы одну работу — заказчики выбирают по фото.') }}
                    </span>
                    <a class="uo-btn" href="{{ route('applicant.portfolio.create') }}">+ {{ __('Добавить работу') }}</a>
                </div>
            @endif

            @php
                $base = array_filter(['city' => $cityId]);
            @endphp
            <div class="uo-filters">
                <div class="uo-chips" role="group" aria-label="{{ __('Вид работ') }}">
                    <a class="uo-chip @if(! $category && ! $mine && ! $offered) on @endif" href="{{ route('public.orders.index', $base) }}">{{ __('Все') }}</a>
                    @if($isMaster)
                        <a class="uo-chip @if($mine && ! $category) on @endif" href="{{ route('public.orders.index', $base + ['mine' => 1]) }}">⭐ {{ __('Подходящие мне') }}</a>
                    @endif
                    @if($isApplicant)
                        <a class="uo-chip @if($offered) on @endif" href="{{ route('public.orders.index', ['offered' => 1]) }}">📨 {{ __('Мои отклики') }}</a>
                    @endif
                    @foreach($categories as $item)
                        <a class="uo-chip @if($category?->is($item)) on @endif"
                           href="{{ route('public.orders.index', $base + ['category' => $item->slug]) }}">{{ $item->title() }}</a>
                    @endforeach
                </div>

                @if(! $offered && $cities->isNotEmpty())
                    <form class="uo-city" method="GET" action="{{ route('public.orders.index') }}">
                        @if($category)<input type="hidden" name="category" value="{{ $category->slug }}">@endif
                        @if($mine)<input type="hidden" name="mine" value="1">@endif
                        <select name="city" onchange="this.form.submit()" aria-label="{{ __('Город') }}">
                            <option value="">📍 {{ __('Все города') }}</option>
                            @foreach($cities as $city)
                                <option value="{{ $city->id }}" @selected($cityId === $city->id)>{{ __($city->region) }}</option>
                            @endforeach
                        </select>
                        <noscript><button type="submit" class="uo-btn">{{ __('Показать') }}</button></noscript>
                    </form>
                @endif
            </div>

            @forelse($orders as $order)
                @include('public.pages.orders._card', ['order' => $order, 'offersCount' => $order->active_offers_count])
            @empty
                <div class="uo-card" style="text-align:center; padding:40px 18px;">
                    <div style="font-size:40px; margin-bottom:8px;">🕊</div>
                    <div style="font-weight:800; font-size:17px; margin-bottom:6px;">
                        {{ $offered ? __('Вы ещё не откликались на заказы') : __('Сейчас подходящих заказов нет') }}
                    </div>
                    <p class="uo-lead" style="max-width:440px; margin:0 auto;">
                        {{ __('Новые заявки появляются в течение дня. Мастерам с портфолио мы присылаем уведомление со звуком, как только появится заказ по их профилю.') }}
                    </p>
                </div>
            @endforelse

            @if($orders->hasPages())
                <div style="margin-top:16px;">{{ $orders->links() }}</div>
            @endif
        </div>
    </section>

@endsection
