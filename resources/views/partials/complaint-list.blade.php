{{-- Мои жалобы: общий блок для кабинета соискателя и работодателя --}}
<style>
    .cl-filters{display:flex; flex-wrap:wrap; gap:8px; margin-bottom:18px;}
    .cl-filters a{
        padding:8px 14px; border-radius:999px; text-decoration:none;
        border:1px solid var(--gray-200); background:var(--white);
        color:var(--ink); font-size:13px; font-weight:600;
    }
    .cl-filters a.on{background:#1656D6; border-color:transparent; color:#fff;}
    .cl-filters a span{opacity:.65; font-weight:700; margin-left:4px;}

    .cl-row{padding:16px 0; border-bottom:1px solid #EEF1F6;}
    .cl-row:last-child{border-bottom:none;}
    .cl-top{display:flex; align-items:flex-start; gap:12px; justify-content:space-between;}
    .cl-title{font-weight:700; font-size:15px;}
    .cl-title a{color:inherit; text-decoration:none;}
    .cl-title a:hover{text-decoration:underline;}
    .cl-meta{font-size:13px; color:var(--gray-500); margin-top:4px;}
    .cl-msg{
        font-size:13.5px; line-height:1.6; margin-top:10px; white-space:pre-line;
        background:var(--gray-100); border-radius:10px; padding:11px 13px;
    }
    .cl-answer{
        font-size:13.5px; line-height:1.6; margin-top:10px;
        background:#EFF6FF; color:#1D4ED8; border-radius:10px; padding:11px 13px;
    }
    .cl-tags{display:flex; align-items:center; gap:8px; flex-shrink:0; flex-wrap:wrap;}
    .cl-kind{
        font-size:12px; font-weight:700; padding:5px 11px; border-radius:999px;
        background:var(--gray-100); color:var(--gray-500); white-space:nowrap;
    }
    .cl-st{font-size:12px; font-weight:700; padding:5px 11px; border-radius:999px; white-space:nowrap;}
    .cl-st.new{background:#FEF3C7; color:#B45309;}
    .cl-st.in_review{background:#DBEAFE; color:#1D4ED8;}
    .cl-st.resolved{background:#DCFCE7; color:#15803D;}
    .cl-st.rejected{background:#FEE2E2; color:#B91C1C;}
    .cl-fresh{
        font-size:11.5px; font-weight:700; padding:4px 9px; border-radius:999px;
        background:#1656D6; color:#fff;
    }

    /* жалобы на мои объекты */
    .cl-open{
        font-size:12px; font-weight:800; padding:5px 11px; border-radius:999px;
        background:#FEE2E2; color:#B91C1C; white-space:nowrap;
    }
    .cl-open .bang{
        display:inline-flex; align-items:center; justify-content:center;
        width:15px; height:15px; border-radius:50%;
        background:#B91C1C; color:#fff; font-size:10.5px; margin-right:5px;
    }
    .cl-in-row{border-left:3px solid #FCA5A5; padding-left:13px;}
    .cl-in-row.done{border-left-color:#86EFAC;}
    .cl-form{
        display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-top:12px;
        background:var(--gray-100); border-radius:10px; padding:10px 12px;
    }
    .cl-form label{font-size:12.5px; font-weight:700; color:var(--gray-500);}
    .cl-form select, .cl-form input[type=text]{
        border:1px solid var(--gray-200); border-radius:9px; padding:8px 11px;
        font-family:inherit; font-size:13px; background:var(--white); color:var(--ink);
    }
    .cl-form input[type=text]{flex:1; min-width:180px;}
    .cl-fix{
        border:none; background:#1656D6; color:#fff; border-radius:9px;
        padding:9px 15px; cursor:pointer; font-family:inherit;
        font-size:12.5px; font-weight:700;
    }
    .cl-fix:hover{background:#1247b0;}
    .cl-reply{
        font-size:13.5px; line-height:1.6; margin-top:10px;
        background:#F0FDF4; color:#15803D; border-radius:10px; padding:11px 13px;
    }
</style>

@php
    $filters = ['all' => 'Все'] + \App\Models\Complaint::STATUSES;
@endphp

@if($freshDecisions > 0)
    <div class="card" style="border-color:#BFDBFE; background:#EFF6FF; color:#1D4ED8; font-weight:600; margin-bottom:18px;">
        {{ __('Есть новости по вашим жалобам') }}: {{ $freshDecisions }}
    </div>
@endif

<div class="stat-row">
    <div class="stat-card">
        <div class="label">{{ __('Всего жалоб') }}</div>
        <div class="value" data-live="complaints-all">{{ $counts['all'] }}</div>
        <div class="trend">{{ __('За всё время') }}</div>
    </div>
    <div class="stat-card">
        <div class="label">{{ __('На рассмотрении') }}</div>
        <div class="value">{{ $counts['new'] + $counts['in_review'] }}</div>
        <div class="trend">{{ __('Ответ ещё не получен') }}</div>
    </div>
    <div class="stat-card">
        <div class="label">{{ __('Решены') }}</div>
        <div class="value" data-live="complaints-resolved">{{ $counts['resolved'] }}</div>
        <div class="trend">{{ __('Владелец устранил замечание') }}</div>
    </div>
    <div class="stat-card">
        <div class="label">{{ __('Отклонены') }}</div>
        <div class="value" data-live="complaints-rejected">{{ $counts['rejected'] }}</div>
        <div class="trend">{{ __('Владелец не согласился с жалобой') }}</div>
    </div>
</div>

<div class="card" style="margin-bottom:18px;">
    <div class="card-header">
        <h2>{{ __('Жалобы на меня') }}</h2>
        <span class="hint">
            @if($incomingOpen > 0)
                {{ __('Не устранено') }}: <span data-live="complaints-incoming-open">{{ $incomingOpen }}</span>
            @else
                {{ $incoming->count() }} {{ __('записей') }}
            @endif
        </span>
    </div>

    @forelse($incoming as $complaint)
        <div class="cl-row cl-in-row {{ $complaint->isOpen() ? '' : 'done' }}">
            <div class="cl-top">
                <div style="min-width:0;">
                    <div class="cl-title">
                        @if($complaint->publicUrl())
                            <a href="{{ $complaint->publicUrl() }}">{{ $complaint->targetTitle() }}</a>
                        @else
                            {{ $complaint->targetTitle() }}
                        @endif
                    </div>
                    <div class="cl-meta">
                        {{ __('Причина') }}: <b>{{ __($complaint->reasonLabel()) }}</b> ·
                        {{ __('получена') }} {{ $complaint->created_at->format('d.m.Y H:i') }}
                        @if($complaint->resolved_at)
                            · {{ __('устранено') }} {{ $complaint->resolved_at->format('d.m.Y H:i') }}
                        @endif
                    </div>
                </div>

                <div class="cl-tags">
                    <span class="cl-kind">{{ __($complaint->targetLabel()) }}</span>
                    @if($complaint->status === 'new')
                        <span class="cl-open"><span class="bang">!</span>{{ __('Не устранено') }}</span>
                    @elseif($complaint->status === 'in_review')
                        <span class="cl-st in_review">{{ __('В работе') }}</span>
                    @elseif($complaint->status === 'resolved')
                        <span class="cl-st resolved">{{ __('Устранено') }}</span>
                    @else
                        <span class="cl-st rejected">{{ __('Отклонена вами') }}</span>
                    @endif
                </div>
            </div>

            @if($complaint->message)
                <div class="cl-msg">{{ $complaint->message }}</div>
            @endif

            @if($complaint->admin_comment)
                <div class="cl-answer">{{ __('Модератор') }}: {{ $complaint->admin_comment }}</div>
            @endif

            @if($complaint->owner_reply)
                <div class="cl-reply">{{ __('Ваш ответ') }}: {{ $complaint->owner_reply }}</div>
            @endif

            @unless($complaint->ownerCanSetStatus())
                <div class="cl-msg" style="background:#FEF3C7; color:#B45309;">
                    {{ __('Жалобу на вас как на пользователя рассматривает модератор — статус ставит он своими мерами.') }}
                </div>
            @else
            <form action="{{ route('public.complaints.status', $complaint) }}" method="post" class="cl-form">
                @csrf
                <label for="st-{{ $complaint->id }}">{{ __('Статус') }}</label>
                <select name="status" id="st-{{ $complaint->id }}">
                    <option value="new" @selected($complaint->status === 'new')>{{ __('Не устранено') }}</option>
                    <option value="in_review" @selected($complaint->status === 'in_review')>{{ __('В работе') }}</option>
                    <option value="resolved" @selected($complaint->status === 'resolved')>{{ __('Устранено') }}</option>
                    <option value="rejected" @selected($complaint->status === 'rejected')>{{ __('Отклонить — нарушения нет') }}</option>
                </select>
                <input type="text" name="reply" maxlength="500"
                       placeholder="{{ __('Ответ автору жалобы (необязательно)') }}"
                       value="{{ $complaint->owner_reply }}">
                <button type="submit" class="cl-fix">{{ __('Сохранить') }}</button>
            </form>
            @endunless
        </div>
    @empty
        <div style="text-align:center; padding:32px 16px; color:var(--gray-500); font-size:14px;">
            {{ __('На ваши вакансии, резюме и профиль никто не жаловался') }}
        </div>
    @endforelse
</div>

<div class="card">
    <div class="card-header">
        <h2>{{ __('Мои жалобы') }}</h2>
        <span class="hint">{{ $complaints->count() }} {{ __('записей') }}</span>
    </div>

    <div class="cl-filters">
        @foreach($filters as $key => $label)
            <a href="{{ url()->current() }}{{ $key === 'all' ? '' : '?status='.$key }}"
               class="{{ $filter === $key ? 'on' : '' }}">
                {{ __($label) }}<span>{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </div>

    @forelse($complaints as $complaint)
        <div class="cl-row">
            <div class="cl-top">
                <div style="min-width:0;">
                    <div class="cl-title">
                        @if($complaint->publicUrl())
                            <a href="{{ $complaint->publicUrl() }}">{{ $complaint->targetTitle() }}</a>
                        @else
                            {{ $complaint->targetTitle() }}
                        @endif
                    </div>
                    <div class="cl-meta">
                        {{ __('Причина') }}: <b>{{ __($complaint->reasonLabel()) }}</b> ·
                        {{ __('отправлена') }} {{ $complaint->created_at->format('d.m.Y H:i') }}
                        @if($complaint->resolved_at)
                            · {{ __('решение') }} {{ $complaint->resolved_at->format('d.m.Y H:i') }}
                        @endif
                    </div>
                </div>

                <div class="cl-tags">
                    @if($complaint->isUnread())
                        <span class="cl-fresh">{{ __('есть новости') }}</span>
                    @endif
                    <span class="cl-kind">{{ __($complaint->targetLabel()) }}</span>
                    <span class="cl-st {{ $complaint->status }}">{{ __($complaint->statusLabel()) }}</span>
                </div>
            </div>

            @if($complaint->message)
                <div class="cl-msg">{{ $complaint->message }}</div>
            @endif

            @if($complaint->owner_reply)
                <div class="cl-reply">{{ __('Ответ владельца объекта') }}: {{ $complaint->owner_reply }}</div>
            @endif

            @if($complaint->admin_comment)
                <div class="cl-answer">{{ __('Ответ модератора') }}: {{ $complaint->admin_comment }}</div>
            @endif

            @if($complaint->status === 'new')
                <form action="{{ route('public.complaints.destroy', $complaint) }}" method="post"
                      style="margin-top:10px;"
                      onsubmit="return confirm('{{ __('Отозвать жалобу?') }}');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" style="border:1px solid var(--gray-200); background:var(--white);
                            color:var(--ink); border-radius:9px; padding:7px 12px; cursor:pointer;
                            font-family:inherit; font-size:12.5px; font-weight:600;">
                        {{ __('Отозвать жалобу') }}
                    </button>
                </form>
            @endif
        </div>
    @empty
        <div style="text-align:center; padding:48px 16px;">
            <div style="font-size:15px; font-weight:600; margin-bottom:6px;">{{ __('Жалоб пока нет') }}</div>
            <p style="color:var(--gray-500); font-size:14px; margin-bottom:20px;">
                {{ __('Если увидите подозрительную вакансию, резюме или компанию — нажмите «Пожаловаться» на их странице') }}
            </p>
            <a href="{{ route('public.vacancies.index') }}" class="btn btn-primary">{{ __('Каталог вакансий') }}</a>
        </div>
    @endforelse
</div>
