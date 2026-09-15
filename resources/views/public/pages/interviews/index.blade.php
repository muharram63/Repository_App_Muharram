@extends('public.layouts.app')
@section('content')

    <style>
        /* ===== верхние показатели ===== */
        .iv-stats{display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:26px;}
        .iv-row-head{
            font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase;
            color:var(--text-muted); margin:0 0 12px;
        }
        .iv-stat{
            background:var(--surface); border:1px solid var(--border); border-radius:16px;
            padding:20px 22px; display:flex; align-items:center; gap:14px;
        }
        .iv-stat .icn{
            width:48px; height:48px; border-radius:14px; flex-shrink:0;
            background:color-mix(in srgb, var(--accent) 12%, transparent);
            color:var(--accent-text);
            display:flex; align-items:center; justify-content:center;
        }
        .iv-stat .icn svg{width:24px; height:24px;}
        .iv-stat .n{font-size:24px; font-weight:800; line-height:1;}
        .iv-stat .l{font-size:12.5px; color:var(--text-muted); margin-top:4px;}

        /* ===== заголовок группы ===== */
        .iv-group-head{
            display:flex; align-items:baseline; gap:12px; margin:0 0 18px;
        }
        .iv-group-head h2{font-size:20px; font-weight:800; letter-spacing:-.3px;}
        .iv-group-head span{font-size:13.5px; color:var(--text-muted);}
        .iv-group + .iv-group{margin-top:38px;}

        /* ===== карточка встречи ===== */
        .iv-grid{display:grid; grid-template-columns:repeat(2,1fr); gap:18px;}
        .iv-card{
            position:relative; overflow:hidden;
            background:var(--surface); border:1px solid var(--border); border-radius:18px;
            padding:22px 24px 22px 26px;
            display:flex; flex-direction:column; gap:16px;
            transition:border-color .2s ease, transform .2s ease, box-shadow .2s ease;
        }
        .iv-card::before{
            content:""; position:absolute; left:0; top:0; bottom:0; width:4px;
            background:var(--border);
        }
        .iv-card:hover{border-color:var(--accent); transform:translateY(-2px); box-shadow:var(--shadow);}
        .iv-card.is-confirmed::before{background:#22C55E;}
        .iv-card.is-scheduled::before{background:var(--accent);}
        .iv-card.is-declined::before, .iv-card.is-canceled::before{background:#EF4444;}
        .iv-card.is-finished::before{background:var(--text-muted);}
        .iv-card.is-live{border-color:#22C55E;}

        .iv-top{display:flex; gap:16px; align-items:flex-start;}
        .iv-date{
            flex-shrink:0; width:78px; text-align:center; padding:12px 6px; border-radius:14px;
            background:linear-gradient(160deg, var(--accent), var(--accent-ink)); color:#fff;
            box-shadow:0 10px 22px -12px var(--accent);
        }
        .iv-date .d{font-size:23px; font-weight:800; line-height:1;}
        .iv-date .m{font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; margin-top:4px; opacity:.9;}
        .iv-date .t{font-size:13px; font-weight:700; margin-top:8px; padding-top:7px; border-top:1px solid rgba(255,255,255,.35);}

        .iv-who{display:flex; align-items:center; gap:11px; min-width:0;}
        .iv-ava, .iv-ava-img{width:40px; height:40px; border-radius:50%; flex-shrink:0; object-fit:cover;}
        .iv-ava{
            background:var(--accent-soft); color:var(--accent-text);
            display:flex; align-items:center; justify-content:center; font-weight:800; font-size:16px;
        }
        .iv-name{font-weight:700; font-size:15.5px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;}
        .iv-role{font-size:12.5px; color:var(--text-muted); margin-top:2px;}

        .iv-chips{display:flex; flex-wrap:wrap; gap:7px;}
        .iv-chip{
            font-size:12px; font-weight:600; padding:5px 11px; border-radius:999px;
            background:var(--surface-alt); border:1px solid var(--border); color:var(--text-muted);
            max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
        }
        .iv-chip.live{background:#DCFCE7; border-color:transparent; color:#15803D;}
        .iv-note{
            font-size:13.5px; line-height:1.6; color:var(--text-muted); white-space:pre-line;
            background:var(--surface-alt); border-radius:12px; padding:12px 14px;
        }

        .iv-foot{
            margin-top:auto; padding-top:14px; border-top:1px solid var(--border);
            display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;
        }
        .iv-actions{display:flex; gap:8px; flex-wrap:wrap;}
        .iv-actions form{margin:0;}
        .iv-btn{
            font-family:inherit; font-size:13px; font-weight:700; cursor:pointer;
            padding:9px 16px; border-radius:11px; border:1px solid var(--border);
            background:var(--surface); color:var(--text-muted); white-space:nowrap;
        }
        .iv-btn:hover{border-color:var(--accent); color:var(--accent);}
        .iv-btn.go{background:var(--accent); border-color:var(--accent); color:#fff;}
        .iv-btn.go:hover{background:var(--accent-ink); color:#fff;}
        .iv-btn.ok{background:#F0FDF4; border-color:#BBF7D0; color:#15803D;}
        .iv-btn.no{background:#FEF2F2; border-color:#FECACA; color:#B91C1C;}

        .iv-status{
            font-size:11.5px; font-weight:700; padding:5px 12px; border-radius:999px;
            background:var(--surface-alt); border:1px solid var(--border); color:var(--text-muted);
            white-space:nowrap;
        }
        .iv-status.confirmed{background:#DCFCE7; border-color:transparent; color:#15803D;}
        .iv-status.declined, .iv-status.canceled{background:#FEE2E2; border-color:transparent; color:#B91C1C;}
        .iv-status.finished{background:var(--surface-alt); color:var(--text-muted);}

        .iv-empty{
            grid-column:1 / -1; text-align:center; padding:48px 24px;
            background:var(--surface); border:1px dashed var(--border); border-radius:18px;
        }
        .iv-empty .e{font-size:28px; margin-bottom:10px;}
        .iv-empty p{color:var(--text-muted); font-size:14.5px; line-height:1.6; max-width:420px; margin:0 auto;}

        @media (max-width:1024px){ .iv-grid{grid-template-columns:1fr;} }
        @media (max-width:640px){
            .iv-stats{grid-template-columns:1fr;}
            .iv-top{flex-wrap:wrap;}
        }
    </style>

    @php
        $isEmployerUser = $user->role === 'employer';
        $months = [1=>'янв',2=>'фев',3=>'мар',4=>'апр',5=>'мая',6=>'июн',
                   7=>'июл',8=>'авг',9=>'сен',10=>'окт',11=>'ноя',12=>'дек'];
    @endphp

    <section class="section">
        <div class="container">

            <div class="section-head">
                <div class="eyebrow">{{ __('Собеседования') }}</div>
                <h2>{{ __('Онлайн-встречи') }}</h2>
                <p>{{ __('Видеовстречи работодателя и соискателя прямо в браузере — без установки программ.') }}</p>
            </div>

            @if(session('status') || session('error'))
                <div style="margin-bottom:22px; padding:14px 18px; border-radius:12px; font-weight:600;
                            background:{{ session('error') ? '#FEF2F2' : '#ECFDF5' }};
                            color:{{ session('error') ? '#B91C1C' : '#15803D' }};">
                    {{ session('error') ?: session('status') }}
                </div>
            @endif

            <div class="iv-row-head">{{ __('За всё время') }}</div>
            <div class="iv-stats">
                <div class="iv-stat">
                    <div class="icn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/></svg></div>
                    <div>
                        <div class="n">{{ $stats['total'] }}</div>
                        <div class="l">{{ __('собеседований') }}</div>
                    </div>
                </div>
                <div class="iv-stat">
                    <div class="icn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg></div>
                    <div>
                        <div class="n">{{ $stats['canceled'] }}</div>
                        <div class="l">{{ __('отменено') }}</div>
                    </div>
                </div>
                <div class="iv-stat">
                    <div class="icn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"/><rect x="2" y="6" width="14" height="12" rx="2"/></svg></div>
                    <div>
                        <div class="n">{{ $stats['held'] }}</div>
                        <div class="l">{{ __('проведено') }}</div>
                    </div>
                </div>
            </div>

            <div class="iv-row-head">{{ __('Сейчас') }}</div>
            <div class="iv-stats">
                <div class="iv-stat">
                    <div class="icn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/></svg></div>
                    <div>
                        <div class="n">{{ $stats['upcoming'] }}</div>
                        <div class="l">{{ __('запланировано') }}</div>
                    </div>
                </div>
                <div class="iv-stat">
                    <div class="icn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg></div>
                    <div>
                        <div class="n">{{ $stats['upcomingCanceled'] }}</div>
                        <div class="l">{{ __('отменено') }}</div>
                    </div>
                </div>
                <div class="iv-stat">
                    <div class="icn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
                    <div>
                        <div class="n">{{ $stats['heldToday'] }}</div>
                        <div class="l">{{ __('проведено сегодня') }}</div>
                    </div>
                </div>
            </div>

            @foreach([['Ближайшие', $upcoming, true], ['Архив', $archive, false]] as [$title, $list, $isUpcoming])
                <div class="iv-group">
                    <div class="iv-group-head">
                        <h2>{{ $title }}</h2>
                        <span>
                            {{ $isUpcoming ? 'встречи, которые ещё впереди' : 'прошедшие, отменённые и отклонённые' }}
                            · {{ $list->count() }}
                        </span>
                    </div>

                    <div class="iv-grid">
                        @forelse($list as $interview)
                            @php
                                $counterName = $isEmployerUser
                                    ? ($interview->applicant?->user?->name ?? 'Соискатель удалён')
                                    : ($interview->employer?->company_name ?? __('Компания удалена'));
                                $counterAvatar = $isEmployerUser
                                    ? $interview->applicant?->user?->avatar
                                    : $interview->employer?->user?->avatar;
                                $live = $interview->isRoomOpen();
                            @endphp
                            <article class="iv-card is-{{ $interview->status }} {{ $live ? 'is-live' : '' }}">
                                <div class="iv-top">
                                    <div class="iv-date">
                                        <div class="d">{{ $interview->scheduled_at->format('d') }}</div>
                                        <div class="m">{{ $months[(int) $interview->scheduled_at->format('n')] }}</div>
                                        <div class="t">{{ $interview->scheduled_at->format('H:i') }}</div>
                                    </div>

                                    <div style="flex:1; min-width:0;">
                                        <div class="iv-who">
                                            @if($counterAvatar)
                                                <img class="iv-ava-img" src="{{ asset($counterAvatar) }}" alt="">
                                            @else
                                                <div class="iv-ava">{{ $isEmployerUser ? ($interview->applicant?->user?->initials(1) ?? '?') : ($interview->employer?->initials(2) ?? '?') }}</div>
                                            @endif
                                            <div style="min-width:0;">
                                                <div class="iv-name">
                                                    <a href="{{ route('public.interviews.show', $interview) }}">{{ $counterName }}</a>
                                                </div>
                                                <div class="iv-role">{{ $isEmployerUser ? 'Соискатель' : 'Работодатель' }}</div>
                                            </div>
                                        </div>

                                        <div class="iv-chips" style="margin-top:12px;">
                                            @if($live)
                                                <span class="iv-chip live">{{ __('● Комната открыта') }}</span>
                                            @elseif($isUpcoming)
                                                <span class="iv-chip">{{ $interview->scheduled_at->diffForHumans() }}</span>
                                            @endif
                                            <span class="iv-chip">{{ $interview->duration_minutes }} мин</span>
                                            @if($interview->vacancy)
                                                <span class="iv-chip">{{ $interview->vacancy->title }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                @if($interview->note)
                                    <div class="iv-note">{{ $interview->note }}</div>
                                @endif

                                <div class="iv-foot">
                                    <div class="iv-actions">
                                        <a class="iv-btn go" href="{{ route('public.interviews.show', $interview) }}">
                                            {{ $live ? 'Войти в комнату' : 'Открыть' }}
                                        </a>

                                        @if(! $isEmployerUser && $interview->status === 'scheduled')
                                            <form action="{{ route('public.interviews.status', $interview) }}" method="post">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" value="confirmed">
                                                <button class="iv-btn ok" type="submit">{{ __('Подтвердить') }}</button>
                                            </form>
                                            <form action="{{ route('public.interviews.status', $interview) }}" method="post">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" value="declined">
                                                <button class="iv-btn no" type="submit">{{ __('Отклонить') }}</button>
                                            </form>
                                        @endif

                                        @if($isEmployerUser && $isUpcoming && $interview->status !== 'canceled')
                                            <form action="{{ route('public.interviews.status', $interview) }}" method="post"
                                                  onsubmit="return confirm('Отменить собеседование?');">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" value="canceled">
                                                <button class="iv-btn no" type="submit">{{ __('Отменить') }}</button>
                                            </form>
                                        @endif
                                    </div>

                                    <span class="iv-status {{ $interview->status }}">{{ $interview->statusLabel() }}</span>
                                </div>
                            </article>
                        @empty
                            <div class="iv-empty">
                                <div class="e">{{ $isUpcoming ? '📅' : '🗂' }}</div>
                                <p>
                                    @if($isUpcoming)
                                        {{ $isEmployerUser
                                            ? 'Назначьте собеседование из отклика соискателя — кнопка «Назначить онлайн-собеседование» есть в разделе «Отклики» и на странице вашей вакансии.'
                                            : 'Приглашений на собеседование пока нет. Откликайтесь на вакансии — работодатель сможет позвать вас на встречу.' }}
                                    @else
                                        Здесь появятся прошедшие и отменённые встречи.
                                    @endif
                                </p>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach

        </div>
    </section>

@endsection
