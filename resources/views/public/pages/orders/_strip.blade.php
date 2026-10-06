{{--
    Полоса «Срочно нужен мастер» — над списком вакансий.
    Срочные заявки (сегодня и сутки) видны выше обычных объявлений.
--}}
@if($urgentOrders->isNotEmpty())
    <style>
        .uo-strip { margin-bottom: 28px; padding: 18px; border-radius: 18px; border: 1px solid #FECACA; background: linear-gradient(135deg, #FFF5F5, var(--surface)); }
        :root[data-theme="dark"] .uo-strip { border-color: #5B2020; background: linear-gradient(135deg, #2A1414, var(--surface)); }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) .uo-strip { border-color: #5B2020; background: linear-gradient(135deg, #2A1414, var(--surface)); }
        }
        .uo-strip-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
        .uo-strip-head h3 { margin: 0; font-size: 17px; font-weight: 800; color: var(--text); }
        .uo-strip-head a { font-size: 14px; font-weight: 700; color: var(--accent); text-decoration: none; }
        .uo-strip-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
        .uo-strip-item {
            display: flex; flex-direction: column; gap: 6px; padding: 12px; border-radius: 14px; text-decoration: none;
            background: var(--surface); border: 1px solid var(--border); color: var(--text); min-width: 0;
        }
        .uo-strip-item:hover { border-color: var(--accent); }
        .uo-strip-item b { font-size: 14px; line-height: 1.35; overflow-wrap: anywhere; }
        .uo-strip-item small { font-size: 12.5px; color: var(--text-muted); overflow-wrap: anywhere; }
        @media (max-width: 1024px) { .uo-strip-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 560px) {
            .uo-strip { padding: 14px; }
            .uo-strip-grid { display: flex; overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .uo-strip-item { flex: 0 0 78%; }
        }
    </style>

    <div class="uo-strip">
        <div class="uo-strip-head">
            <h3>🔥 {{ __('Срочно нужен мастер') }}</h3>
            <a href="{{ route('public.orders.index') }}">{{ __('Все срочные заказы →') }}</a>
        </div>
        <div class="uo-strip-grid">
            @foreach($urgentOrders as $order)
                <a class="uo-strip-item" href="{{ route('public.orders.show', $order) }}">
                    <span class="uo-badge urgent" style="align-self:flex-start; display:inline-flex; padding:3px 9px; border-radius:999px; font-size:11.5px; font-weight:800; background:#FEE2E2; color:#B91C1C;">
                        🔥 {{ $order->deadlineLabel() }}
                    </span>
                    <b>{{ $order->title }}</b>
                    <small>{{ $order->category?->title() }}</small>
                    @if($order->place())<small>📍 {{ $order->place() }}</small>@endif
                    @if($order->budget)<small>💰 {{ number_format($order->budget, 0, ',', ' ') }} {{ __('сомони') }}</small>@endif
                </a>
            @endforeach
        </div>
    </div>
@endif
