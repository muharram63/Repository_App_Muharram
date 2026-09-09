{{-- Журнал звонков кабинета: общий для соискателя и работодателя --}}
<style>
    .call-filters{display:flex; flex-wrap:wrap; gap:8px; margin-bottom:18px;}
    .call-filters a{
        padding:8px 14px; border-radius:999px; text-decoration:none;
        border:1px solid var(--gray-200); background:var(--white);
        color:var(--ink); font-size:13px; font-weight:600;
    }
    .call-filters a.on{background:#1656D6; border-color:transparent; color:#fff;}
    .call-filters a span{opacity:.65; font-weight:700; margin-left:4px;}

    .call-row{
        display:flex; gap:14px; align-items:center;
        padding:14px 0; border-bottom:1px solid #EEF1F6;
    }
    .call-row:last-child{border-bottom:none;}
    .call-mark{
        width:44px; height:44px; border-radius:12px; flex-shrink:0; overflow:hidden;
        background:var(--indigo-50); color:var(--indigo-700);
        display:flex; align-items:center; justify-content:center; font-weight:700; font-size:15px;
    }
    .call-mark img{width:100%; height:100%; object-fit:cover;}
    .call-body{flex:1; min-width:0;}
    .call-name{font-weight:700; font-size:15px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;}
    .call-meta{font-size:13px; color:var(--gray-500); margin-top:3px;}
    .call-dir{
        display:inline-flex; align-items:center; gap:5px;
        font-size:12px; font-weight:700; color:var(--gray-500);
    }
    .call-dir svg{width:13px; height:13px;}
    .call-dir.in{color:#15803D;}
    .call-dir.out{color:#1656D6;}
    .call-dir.missed{color:#B91C1C;}
    .call-state{
        font-size:12px; font-weight:700; padding:5px 12px; border-radius:999px; white-space:nowrap;
        background:var(--gray-100); color:var(--gray-500);
    }
    .call-state.answered{background:#DCFCE7; color:#15803D;}
    .call-state.missed{background:#FEE2E2; color:#B91C1C;}
    .call-state.declined{background:#FEF3C7; color:#B45309;}
    .call-state.ringing{background:#DBEAFE; color:#1D4ED8;}
    .call-acts{display:flex; gap:8px; flex-shrink:0; flex-wrap:wrap; justify-content:flex-end;}
    .call-acts a, .call-acts button{
        padding:7px 12px; border-radius:10px; text-decoration:none; cursor:pointer;
        border:1px solid var(--gray-200); background:var(--white); color:var(--ink);
        font-family:inherit; font-size:12.5px; font-weight:600; white-space:nowrap;
    }
    .call-acts .primary{background:#1656D6; border-color:transparent; color:#fff;}
    @media (max-width:720px){
        .call-row{flex-wrap:wrap;}
        .call-acts{width:100%; justify-content:flex-start;}
    }
</style>

@php
    $stateLabels = [
        'answered' => 'Состоялся',
        'missed' => 'Пропущенный',
        'declined' => 'Отклонён',
        'canceled' => 'Отменён',
        'ringing' => 'Идёт вызов',
        'planned' => 'Запланирован',
    ];
    $filters = [
        'all' => 'Все звонки',
        'in' => 'Входящие',
        'out' => 'Исходящие',
        'missed' => 'Пропущенные',
    ];
@endphp

<div class="stat-row">
    <div class="stat-card">
        <div class="label">{{ __('Всего звонков') }}</div>
        <div class="value" data-live="calls-all">{{ $counts['all'] }}</div>
        <div class="trend">{{ __('За всё время') }}</div>
    </div>
    <div class="stat-card">
        <div class="label">{{ __('Входящие') }}</div>
        <div class="value" data-live="calls-in">{{ $counts['in'] }}</div>
        <div class="trend">{{ __('Звонили вам') }}</div>
    </div>
    <div class="stat-card">
        <div class="label">{{ __('Исходящие') }}</div>
        <div class="value" data-live="calls-out">{{ $counts['out'] }}</div>
        <div class="trend">{{ __('Звонили вы') }}</div>
    </div>
    <div class="stat-card">
        <div class="label">{{ __('Пропущенные') }}</div>
        <div class="value" data-live="calls-missed">{{ $counts['missed'] }}</div>
        <div class="trend">{{ __('Никто не ответил') }}</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>{{ __('История звонков') }}</h2>
        <span class="hint">{{ $rows->count() }} {{ __('записей') }}</span>
    </div>

    <div class="call-filters">
        @foreach($filters as $key => $label)
            <a href="{{ url()->current() }}{{ $key === 'all' ? '' : '?filter='.$key }}"
               class="{{ $filter === $key ? 'on' : '' }}">
                {{ __($label) }}<span>{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </div>

    @forelse($rows as $row)
        <div class="call-row">
            <div class="call-mark">
                @if($row['avatar'])
                    <img src="{{ $row['avatar'] }}" alt="">
                @else
                    {{ $row['initials'] }}
                @endif
            </div>

            <div class="call-body">
                <div class="call-name">
                    {{ $row['name'] }}
                    <span class="call-dir {{ $row['state'] === 'missed' ? 'missed' : $row['direction'] }}">
                        @if($row['direction'] === 'in')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 7 7 17"/><path d="M17 17H7V7"/></svg>
                            {{ $row['state'] === 'missed' ? __('Пропущенный') : __('Входящий') }}
                        @else
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7"/><path d="M7 7h10v10"/></svg>
                            {{ __('Исходящий') }}
                        @endif
                    </span>
                </div>

                <div class="call-meta">
                    {{ $row['at']?->format('d.m.Y H:i') ?? '—' }} ·
                    {{ $row['kind'] === 'chat' ? __('Звонок из чата') : __('Назначенное собеседование') }}
                    @if($row['kind'] !== 'chat')
                        · {{ $row['duration'] }} {{ __('мин') }}
                    @endif
                    @if($row['vacancy'])
                        · {{ $row['vacancy'] }}
                    @endif
                </div>
            </div>

            <span class="call-state {{ $row['state'] }}">
                {{ __($stateLabels[$row['state']] ?? $row['state']) }}
            </span>

            <div class="call-acts">
                @if($row['roomOpen'])
                    <a class="primary" href="{{ $row['roomUrl'] }}">{{ __('Войти в комнату') }}</a>
                @elseif($row['callUrl'])
                    <form action="{{ $row['callUrl'] }}" method="post" style="margin:0;">
                        @csrf
                        <button type="submit">{{ __('Позвонить') }}</button>
                    </form>
                @endif
                @if($row['chatUrl'])
                    <a href="{{ $row['chatUrl'] }}">{{ __('Чат') }}</a>
                @endif
                <a href="{{ $row['roomUrl'] }}">{{ __('Детали') }}</a>
            </div>
        </div>
    @empty
        <div style="text-align:center; padding:48px 16px;">
            <div style="font-size:15px; font-weight:600; margin-bottom:6px;">{{ __('Звонков пока нет') }}</div>
            <p style="color:var(--gray-500); font-size:14px; margin-bottom:20px;">
                {{ __('Здесь появятся все входящие, исходящие и пропущенные видеозвонки') }}
            </p>
            <a href="{{ route('public.chats.index') }}" class="btn btn-primary">{{ __('Перейти в чаты') }}</a>
        </div>
    @endforelse
</div>
