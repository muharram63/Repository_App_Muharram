{{--
    Карточка заявки в списке. Общая для «Моих заявок» и ленты мастеров.
    Точного адреса здесь нет никогда — только город и район.
--}}
@php
    $thumb = $order->photos->first();
    $statusClass = match ($order->status) {
        \App\Models\UrgentOrder::STATUS_OPEN, \App\Models\UrgentOrder::STATUS_ASSIGNED => 'status',
        \App\Models\UrgentOrder::STATUS_DONE => 'done',
        default => 'closed',
    };
@endphp

<a class="uo-item" href="{{ route('public.orders.show', $order) }}">
    <span class="uo-item-pic">
        @if($thumb)
            <img src="{{ $thumb->thumbUrl() }}" alt="" loading="lazy">
        @else
            {{ $order->category?->icon ?? '🛠' }}
        @endif
    </span>
    <span class="uo-item-body">
        <span class="uo-item-tags">
            @if($order->isOpen() && $order->isUrgent())
                <span class="uo-badge urgent">🔥 {{ __('Срочно') }}</span>
            @endif
            <span class="uo-badge {{ $statusClass }}">{{ $order->statusLabel() }}</span>
        </span>
        <span class="uo-item-title">{{ $order->title }}</span>
        <span class="uo-item-meta">
            @if($order->category)<span>{{ $order->category->title() }}</span>@endif
            @if($order->place())<span>📍 {{ $order->place() }}</span>@endif
            <span>⏱ {{ $order->deadlineLabel() }}@if($order->timeLeftLabel()) · {{ __('осталось :left', ['left' => $order->timeLeftLabel()]) }}@endif</span>
            @if($order->budget)<span>💰 {{ number_format($order->budget, 0, ',', ' ') }} {{ __('сомони') }}</span>@endif
            @isset($offersCount)
                <span>👥 {{ __('Откликов: :count', ['count' => $offersCount]) }}</span>
            @endisset
        </span>
    </span>
</a>

@once
<style>
    .uo-item {
        display: flex; gap: 14px; padding: 14px; border: 1px solid var(--border); border-radius: 16px;
        background: var(--surface); color: var(--text); text-decoration: none; margin-bottom: 10px;
        transition: border-color .15s, transform .15s;
    }
    .uo-item:hover { border-color: var(--accent); transform: translateY(-1px); }
    .uo-item-pic {
        flex: none; width: 84px; height: 84px; border-radius: 12px; overflow: hidden; background: var(--surface-alt, #F1F3F8);
        display: flex; align-items: center; justify-content: center; font-size: 30px;
    }
    .uo-item-pic img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .uo-item-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px; }
    .uo-item-tags { display: flex; gap: 6px; flex-wrap: wrap; }
    .uo-item-title { font-weight: 700; font-size: 15.5px; line-height: 1.35; overflow-wrap: anywhere; }
    .uo-item-meta { display: flex; flex-wrap: wrap; gap: 4px 12px; font-size: 13px; color: var(--text-muted); }
    @media (max-width: 640px) {
        .uo-item { padding: 12px; gap: 12px; }
        .uo-item-pic { width: 64px; height: 64px; font-size: 24px; }
    }
    @media (prefers-reduced-motion: reduce) { .uo-item { transition: none; } }
</style>
@endonce
