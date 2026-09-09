{{-- Полный список уведомлений: общий для кабинета соискателя и работодателя --}}
<style>
    .nl-filters{display:flex; flex-wrap:wrap; gap:8px; margin-bottom:18px;}
    .nl-filters a{
        padding:8px 14px; border-radius:999px; text-decoration:none;
        border:1px solid var(--gray-200); background:var(--white);
        color:var(--ink); font-size:13px; font-weight:600;
    }
    .nl-filters a.on{background:#1656D6; border-color:transparent; color:#fff;}
    .nl-filters a span{opacity:.65; font-weight:700; margin-left:4px;}

    .nl-row{
        display:flex; gap:13px; padding:15px 0; border-bottom:1px solid #EEF1F6;
        text-decoration:none; color:inherit;
    }
    .nl-row:last-child{border-bottom:none;}
    .nl-row:hover .nl-title{text-decoration:underline;}
    .nl-ico{
        width:38px; height:38px; border-radius:11px; flex-shrink:0;
        background:var(--gray-100); display:flex; align-items:center; justify-content:center; font-size:17px;
    }
    .nl-row.unread .nl-ico{background:#EEF0FF;}
    .nl-title{font-weight:700; font-size:14.5px;}
    .nl-body{font-size:13.5px; color:var(--gray-500); margin-top:4px; line-height:1.6;}
    .nl-time{font-size:12.5px; color:var(--gray-300); margin-top:5px;}
    .nl-dot{
        width:9px; height:9px; border-radius:50%; background:#1656D6;
        flex-shrink:0; align-self:center;
    }
</style>

<div class="stat-row">
    <div class="stat-card">
        <div class="label">{{ __('Всего уведомлений') }}</div>
        <div class="value" data-live="notifications-total">{{ $total }}</div>
        <div class="trend">{{ __('Последние 200') }}</div>
    </div>
    <div class="stat-card">
        <div class="label">{{ __('Непрочитанных') }}</div>
        <div class="value" data-live="notifications-unread">{{ $unread }}</div>
        <div class="trend">{{ __('Ждут вашего внимания') }}</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>{{ __('Ящик уведомлений') }}</h2>
        @if($unread > 0)
            <form action="{{ route('public.notifications.read') }}" method="post" style="margin:0;">
                @csrf
                <button type="submit" style="border:1px solid var(--gray-200); background:var(--white);
                        color:var(--ink); border-radius:9px; padding:7px 12px; cursor:pointer;
                        font-family:inherit; font-size:12.5px; font-weight:600;">
                    {{ __('Отметить всё прочитанным') }}
                </button>
            </form>
        @endif
    </div>

    <div class="nl-filters">
        <a href="{{ url()->current() }}" class="{{ $filter === 'all' ? 'on' : '' }}">
            {{ __('Все') }}<span>{{ $total }}</span>
        </a>
        <a href="{{ url()->current() }}?filter=unread" class="{{ $filter === 'unread' ? 'on' : '' }}">
            {{ __('Непрочитанные') }}<span>{{ $unread }}</span>
        </a>
    </div>

    @forelse($notifications as $item)
        <a class="nl-row {{ $item->isUnread() ? 'unread' : '' }}"
           href="{{ route('public.notifications.open', $item) }}">
            <span class="nl-ico">{{ $item->icon() }}</span>
            <span style="flex:1; min-width:0;">
                <span class="nl-title">{{ $item->title }}</span>
                @if($item->body)<span class="nl-body">{{ $item->body }}</span>@endif
                <span class="nl-time">{{ $item->created_at->format('d.m.Y H:i') }}</span>
            </span>
            @if($item->isUnread())<span class="nl-dot" title="{{ __('Не прочитано') }}"></span>@endif
        </a>
    @empty
        <div style="text-align:center; padding:48px 16px;">
            <div style="font-size:15px; font-weight:600; margin-bottom:6px;">{{ __('Уведомлений нет') }}</div>
            <p style="color:var(--gray-500); font-size:14px;">
                {{ __('Здесь появятся отклики, сообщения, приглашения на собеседования и решения по жалобам') }}
            </p>
        </div>
    @endforelse
</div>
