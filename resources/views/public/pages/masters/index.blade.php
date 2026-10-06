@extends('public.layouts.app')
@section('content')

    <style>
        .mc-page { padding: 28px 0 56px; }
        .mc-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
        .mc-top h1 { font-size: clamp(22px, 3vw, 30px); font-weight: 800; margin: 0 0 6px; color: var(--text); }
        .mc-top p { margin: 0; color: var(--text-muted); font-size: 14.5px; line-height: 1.55; max-width: 640px; }
        .mc-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 11px 18px; border-radius: 12px;
            font-weight: 700; font-size: 14.5px; text-decoration: none; border: 1px solid var(--border);
            background: var(--surface); color: var(--text); cursor: pointer; font-family: inherit;
        }
        .mc-btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; }

        .mc-tools { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
        .mc-search { flex: 1; min-width: 220px; display: flex; gap: 8px; }
        .mc-tools input, .mc-tools select {
            padding: 10px 12px; border-radius: 12px; border: 1px solid var(--border); background: var(--surface);
            color: var(--text); font: inherit; font-size: 14.5px;
        }
        .mc-search input { flex: 1; min-width: 0; }
        .mc-chips { display: flex; gap: 8px; overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 2px; margin-bottom: 18px; }
        .mc-chip {
            flex: none; padding: 8px 13px; border-radius: 999px; border: 1px solid var(--border); background: var(--surface);
            color: var(--text); text-decoration: none; font-size: 13px; font-weight: 600; white-space: nowrap;
        }
        .mc-chip.on { background: var(--accent); border-color: var(--accent); color: #fff; }

        .mc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; }
        .mc-card {
            display: flex; flex-direction: column; border: 1px solid var(--border); border-radius: 18px; overflow: hidden;
            background: var(--surface); color: var(--text); text-decoration: none; transition: border-color .15s, transform .15s;
        }
        .mc-card:hover { border-color: var(--accent); transform: translateY(-2px); }
        .mc-covers { display: grid; grid-template-columns: 2fr 1fr; grid-template-rows: 1fr 1fr; gap: 2px; aspect-ratio: 16 / 9; background: var(--surface-alt, #F1F3F8); }
        .mc-covers span { background: var(--surface-alt, #F1F3F8); overflow: hidden; display: flex; align-items: center; justify-content: center; color: var(--text-muted); }
        .mc-covers span:first-child { grid-row: 1 / 3; font-size: 30px; }
        .mc-covers img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .mc-body { padding: 14px 16px 16px; display: flex; flex-direction: column; gap: 8px; flex: 1; }
        .mc-who { display: flex; gap: 10px; align-items: center; }
        .mc-ava {
            width: 44px; height: 44px; border-radius: 50%; flex: none; object-fit: cover;
            background: var(--accent-soft); color: var(--accent-text); display: flex; align-items: center; justify-content: center; font-weight: 800;
        }
        .mc-name { font-weight: 800; font-size: 15.5px; overflow-wrap: anywhere; }
        .mc-city { font-size: 13px; color: var(--text-muted); }
        .mc-cats { display: flex; flex-wrap: wrap; gap: 5px; }
        .mc-cat { padding: 3px 9px; border-radius: 999px; background: var(--accent-soft); color: var(--accent-text); font-size: 12px; font-weight: 700; }
        .mc-meta { display: flex; flex-wrap: wrap; gap: 4px 12px; font-size: 13px; color: var(--text-muted); margin-top: auto; align-items: center; }
        .mc-empty { text-align: center; padding: 44px 18px; border: 1px dashed var(--border); border-radius: 18px; color: var(--text-muted); }

        @media (max-width: 640px) {
            .mc-page { padding: 16px 0 40px; }
            .mc-top .mc-btn { width: 100%; }
            .mc-tools input, .mc-tools select { font-size: 16px; width: 100%; }
            .mc-tools select { flex: 1 1 100%; }
            .mc-grid { grid-template-columns: 1fr; gap: 12px; }
        }
        @media (prefers-reduced-motion: reduce) { .mc-card { transition: none; } }
    </style>

    <section class="mc-page">
        <div class="container">
            <div class="mc-top">
                <div>
                    <h1>🛠 {{ __('Мастера') }}</h1>
                    <p>{{ __('Сантехники, электрики, маляры, мастера по ремонту — с фото выполненных работ и отзывами заказчиков. Найдите мастера или опишите задачу, и мастера откликнутся сами.') }}</p>
                </div>
                <a class="mc-btn primary" href="{{ route('public.orders.create') }}">🛠 {{ __('Нужен мастер') }}</a>
            </div>

            <form class="mc-tools" method="GET" action="{{ route('public.masters.index') }}">
                @if($category)<input type="hidden" name="category" value="{{ $category->slug }}">@endif
                <div class="mc-search">
                    <input type="search" name="q" value="{{ $query }}" placeholder="{{ __('Имя, город или работа: «смеситель», «Худжанд»…') }}"
                           aria-label="{{ __('Поиск мастера') }}">
                    <button type="submit" class="mc-btn">{{ __('Найти') }}</button>
                </div>
                <select name="sort" onchange="this.form.submit()" aria-label="{{ __('Порядок') }}">
                    @foreach($sorts as $value => $label)
                        <option value="{{ $value }}" @selected($sort === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </form>

            @php
                $keep = array_filter(['q' => $query ?: null, 'sort' => $sort === 'rating' ? null : $sort]);
            @endphp
            @if($categories->isNotEmpty())
                <div class="mc-chips" role="group" aria-label="{{ __('Вид работ') }}">
                    <a class="mc-chip @if(! $category) on @endif" href="{{ route('public.masters.index', $keep) }}">{{ __('Все') }}</a>
                    @foreach($categories as $item)
                        <a class="mc-chip @if($category?->is($item)) on @endif"
                           href="{{ route('public.masters.index', $keep + ['category' => $item->slug]) }}">{{ $item->title() }}</a>
                    @endforeach
                </div>
            @endif

            @if($masters->isEmpty())
                <div class="mc-empty">
                    <div style="font-size:40px; margin-bottom:8px;">🔎</div>
                    <div style="font-weight:800; font-size:17px; color:var(--text); margin-bottom:6px;">
                        {{ ($query !== '' || $category) ? __('Таких мастеров не нашлось') : __('Мастеров пока нет') }}
                    </div>
                    <p style="max-width:460px; margin:0 auto 16px;">
                        {{ __('Опишите задачу в заявке — её увидят все мастера нужного профиля и откликнутся сами.') }}
                    </p>
                    <a class="mc-btn primary" href="{{ route('public.orders.create') }}">🛠 {{ __('Нужен мастер') }}</a>
                </div>
            @else
                <div class="mc-grid">
                    @foreach($masters as $master)
                        @php
                            $covers = $master->portfolioWorks->map(fn ($w) => $w->cover()?->previewUrl())->filter()->values();
                            $cats = $masterCategories->get($master->id, collect());
                        @endphp
                        <a class="mc-card" href="{{ route('public.masters.show', $master) }}">
                            <span class="mc-covers" aria-hidden="true">
                                @for($i = 0; $i < 3; $i++)
                                    <span>@if($covers->get($i))<img src="{{ $covers->get($i) }}" alt="" loading="lazy">@elseif($i === 0)📸@endif</span>
                                @endfor
                            </span>
                            <span class="mc-body">
                                <span class="mc-who">
                                    @if($master->user?->hasAvatar())
                                        <img class="mc-ava" src="{{ asset($master->user->avatar) }}" alt="">
                                    @else
                                        <span class="mc-ava">{{ $master->user?->initials(2) }}</span>
                                    @endif
                                    <span style="min-width:0;">
                                        <span class="mc-name" style="display:block;">{{ $master->user?->name }}</span>
                                        @if($master->city)<span class="mc-city">📍 {{ __($master->city) }}</span>@endif
                                    </span>
                                </span>
                                @if($cats->isNotEmpty())
                                    <span class="mc-cats">
                                        @foreach($cats as $cat)
                                            <span class="mc-cat">{{ $cat->title() }}</span>
                                        @endforeach
                                    </span>
                                @endif
                                <span class="mc-meta">
                                    @include('partials.master-rating', ['avg' => $master->reviews_avg_rating, 'count' => (int) $master->reviews_count])
                                    <span>📸 {{ __('Работ в портфолио: :count', ['count' => $master->portfolio_works_count]) }}</span>
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>

                @if($masters->hasPages())
                    <div style="margin-top:18px;">{{ $masters->links() }}</div>
                @endif
            @endif
        </div>
    </section>

@endsection
