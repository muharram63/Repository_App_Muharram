{{--
    Оценка мастера одной строкой: «★ 4,8 · Отзывов: 12».

    Параметры: $avg (float|null), $count (int), $link (необязательно) — куда
    ведёт строка, обычно к отзывам в профиле мастера. Без отзывов не выводится
    ничего: «0 отзывов» рядом с новичком выглядит как упрёк.
--}}
@if(($count ?? 0) > 0)
    @php $tag = isset($link) ? 'a' : 'span'; @endphp
    <{{ $tag }} class="mr-line" @isset($link) href="{{ $link }}" @endisset
        title="{{ __('Средняя оценка заказчиков') }}">
        <span class="mr-star">★</span>
        <b>{{ \App\Models\MasterReview::formatAverage((float) $avg) }}</b>
        <span class="mr-count">· {{ __('Отзывов: :count', ['count' => $count]) }}</span>
    </{{ $tag }}>

    @once
    <style>
        .mr-line { display: inline-flex; align-items: center; gap: 4px; font-size: 13.5px; color: inherit; text-decoration: none; white-space: nowrap; }
        a.mr-line:hover b { text-decoration: underline; }
        .mr-star { color: #F59E0B; font-size: 15px; line-height: 1; }
        .mr-count { color: var(--text-muted, var(--gray-500, #6B7280)); font-weight: 500; }
    </style>
    @endonce
@endif
