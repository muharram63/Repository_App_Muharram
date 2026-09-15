@extends('public.layouts.app')
@section('content')

    <style>
        .room-layout{display:grid; grid-template-columns:minmax(0,1fr) 330px; gap:24px; align-items:start;}

        .room-box{
            background:var(--surface); border:1px solid var(--border);
            border-radius:20px; overflow:hidden; box-shadow:var(--shadow);
        }
        .room-head{
            display:flex; align-items:center; justify-content:space-between; gap:16px;
            padding:20px 24px; border-bottom:1px solid var(--border); flex-wrap:wrap;
            background:linear-gradient(180deg, var(--surface-alt), var(--surface));
        }
        .room-people{display:flex; align-items:center; gap:12px; min-width:0;}
        .room-ava-pair{display:flex; align-items:center;}
        .room-ava, .room-ava-img{
            width:42px; height:42px; border-radius:50%; object-fit:cover;
            border:2px solid var(--surface); flex-shrink:0;
        }
        .room-ava{
            background:var(--accent-soft); color:var(--accent-text);
            display:flex; align-items:center; justify-content:center; font-weight:800; font-size:16px;
        }
        .room-ava-pair > *:not(:first-child){margin-left:-12px;}
        .room-title{font-weight:800; font-size:16.5px; letter-spacing:-.2px;}
        .room-sub{font-size:13px; color:var(--text-muted); margin-top:3px;}

        .live-badge{
            display:inline-flex; align-items:center; gap:7px;
            font-size:12px; font-weight:800; padding:6px 13px; border-radius:999px;
            background:#DCFCE7; color:#15803D;
        }
        .live-badge .dot{
            width:7px; height:7px; border-radius:50%; background:#22C55E;
            animation:live-pulse 1.6s ease-in-out infinite;
        }
        @keyframes live-pulse{0%,100%{opacity:1; transform:scale(1);} 50%{opacity:.35; transform:scale(.85);}}
        @media (prefers-reduced-motion: reduce){ .live-badge .dot{animation:none;} }

        .status-pill{
            display:inline-block; font-size:12px; font-weight:700; padding:6px 13px; border-radius:999px;
            background:var(--surface-alt); border:1px solid var(--border); color:var(--text-muted);
        }
        .status-pill.confirmed{background:#DCFCE7; border-color:transparent; color:#15803D;}
        .status-pill.declined, .status-pill.canceled{background:#FEE2E2; border-color:transparent; color:#B91C1C;}

        #jitsi{width:100%; height:580px; background:#0B0E1A;}

        .room-closed{
            padding:64px 28px; text-align:center;
            background:
                radial-gradient(700px 240px at 50% 0%, var(--accent-soft), transparent 70%),
                var(--surface);
        }
        .room-closed .icn{
            width:64px; height:64px; margin:0 auto 18px; border-radius:20px;
            background:var(--surface-alt); border:1px solid var(--border);
            display:flex; align-items:center; justify-content:center; font-size:26px;
        }
        .room-closed b{display:block; font-size:18px; margin-bottom:8px;}
        .room-closed p{color:var(--text-muted); font-size:14.5px; line-height:1.65; max-width:460px; margin:0 auto;}
        .room-countdown{
            display:inline-block; margin-top:18px; padding:10px 18px; border-radius:12px;
            background:var(--surface-alt); border:1px solid var(--border);
            font-weight:700; font-size:14px;
        }

        .panel{
            background:var(--surface); border:1px solid var(--border);
            border-radius:18px; padding:22px; margin-bottom:18px;
        }
        .panel h3{
            font-size:12px; font-weight:800; letter-spacing:.1em; text-transform:uppercase;
            color:var(--text-muted); margin-bottom:16px;
        }
        .person{display:flex; align-items:center; gap:12px; min-width:0;}
        .person + .person{margin-top:16px; padding-top:16px; border-top:1px solid var(--border);}
        .person .n{font-weight:700; font-size:14.5px; overflow-wrap:anywhere;}
        .person .r{font-size:12.5px; color:var(--text-muted); margin-top:2px; overflow-wrap:anywhere;}

        .fact + .fact{margin-top:14px;}
        .fact .l{
            font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase;
            color:var(--text-muted); margin-bottom:4px;
        }
        .fact .v{font-size:14.5px; font-weight:600; overflow-wrap:anywhere;}

        .act{display:flex; flex-direction:column; gap:9px;}
        .act form{margin:0;}
        .act button{
            width:100%; font-family:inherit; font-size:13.5px; font-weight:700; cursor:pointer;
            padding:11px 16px; border-radius:12px; border:1px solid var(--border);
            background:var(--surface); color:var(--text-muted); transition:.15s ease;
        }
        .act button:hover{border-color:var(--accent); color:var(--accent);}
        .act .ok{background:#F0FDF4; border-color:#BBF7D0; color:#15803D;}
        .act .no{background:#FEF2F2; border-color:#FECACA; color:#B91C1C;}

        .hint-list{display:flex; flex-direction:column; gap:11px;}
        .hint{display:flex; gap:10px; font-size:13px; color:var(--text-muted); line-height:1.55;}
        .hint .b{
            width:22px; height:22px; border-radius:7px; flex-shrink:0;
            background:var(--accent-soft); color:var(--accent-text);
            display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:800;
        }

        .back-link{
            display:inline-flex; gap:8px; font-size:14px; font-weight:600;
            color:var(--text-muted); margin-bottom:20px;
        }
        .back-link:hover{color:var(--accent);}

        @media (max-width:980px){
            .room-layout{grid-template-columns:1fr;}
            #jitsi{height:420px;}
        }
    </style>

    @php
        $employerUser = $interview->employer?->user;
        $applicantUser = $interview->applicant?->user;
        $companyName = $interview->employer?->company_name ?? 'Компания';
        $applicantName = $applicantUser?->name ?? 'Соискатель';
        $me = auth()->user();
        $roomOpen = $interview->isRoomOpen();
    @endphp

    <section class="section">
        <div class="container">

            <a href="{{ route('public.interviews.index') }}" class="back-link">{{ __('← Все собеседования') }}</a>

            @if(session('status') || session('error'))
                <div style="margin-bottom:20px; padding:14px 18px; border-radius:12px; font-weight:600;
                            background:{{ session('error') ? '#FEF2F2' : '#ECFDF5' }};
                            color:{{ session('error') ? '#B91C1C' : '#15803D' }};">
                    {{ session('error') ?: session('status') }}
                </div>
            @endif

            <div class="room-layout">

                {{-- ===== видеокомната ===== --}}
                <div class="room-box">
                    <div class="room-head">
                        <div class="room-people">
                            <div class="room-ava-pair">
                                @if($employerUser?->hasAvatar())
                                    <img class="room-ava-img" src="{{ asset($employerUser->avatar) }}" alt="">
                                @else
                                    <div class="room-ava">{{ $interview->employer?->initials(2) ?? '?' }}</div>
                                @endif
                                @if($applicantUser?->hasAvatar())
                                    <img class="room-ava-img" src="{{ asset($applicantUser->avatar) }}" alt="">
                                @else
                                    <div class="room-ava">{{ $interview->applicant?->user?->initials(1) ?? '?' }}</div>
                                @endif
                            </div>

                            <div style="min-width:0;">
                                <div class="room-title">{{ $companyName }} · {{ $applicantName }}</div>
                                <div class="room-sub">
                                    {{ $interview->scheduled_at->format('d.m.Y H:i') }} ·
                                    {{ $interview->duration_minutes }} мин
                                    @if($interview->vacancy) · {{ $interview->vacancy->title }} @endif
                                </div>
                            </div>
                        </div>

                        @if($roomOpen)
                            <span class="live-badge"><span class="dot"></span> {{ __('Комната открыта') }}</span>
                        @else
                            <span class="status-pill {{ $interview->status }}">{{ $interview->statusLabel() }}</span>
                        @endif
                    </div>

                    @if($roomOpen)
                        <div id="jitsi"></div>
                    @else
                        <div class="room-closed">
                            <div class="icn">
                                @if(in_array($interview->status, ['declined', 'canceled'], true))
                                    🚫
                                @elseif($interview->isPast())
                                    ✅
                                @else
                                    🎥
                                @endif
                            </div>

                            @if(in_array($interview->status, ['declined', 'canceled'], true))
                                <b>Встреча {{ $interview->status === 'declined' ? 'отклонена' : 'отменена' }}</b>
                                <p>{{ __('Видеосвязь для этой встречи недоступна. Назначьте новое время, если переговоры продолжаются.') }}</p>
                            @elseif($interview->isPast())
                                <b>{{ __('Собеседование завершено') }}</b>
                                <p>Встреча прошла {{ $interview->scheduled_at->format('d.m.Y в H:i') }}. Комната закрыта.</p>
                            @else
                                <b>{{ __('Комната откроется за 15 минут до начала') }}</b>
                                <p>Начало {{ $interview->scheduled_at->format('d.m.Y в H:i') }}. Заранее проверьте камеру и микрофон — подключение идёт прямо в браузере.</p>
                                <div class="room-countdown">⏳ {{ $interview->scheduled_at->diffForHumans() }}</div>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- ===== боковая панель ===== --}}
                <aside>
                    <div class="panel">
                        <h3>{{ __('Участники') }}</h3>

                        <div class="person">
                            @if($employerUser?->hasAvatar())
                                <img class="room-ava-img" src="{{ asset($employerUser->avatar) }}" alt="">
                            @else
                                <div class="room-ava">{{ $interview->employer?->initials(2) ?? '?' }}</div>
                            @endif
                            <div style="min-width:0;">
                                <div class="n">{{ $companyName }}</div>
                                <div class="r">{{ $employerUser?->name ?? '—' }} · {{ $interview->employer?->email_company ?: '—' }}</div>
                            </div>
                        </div>

                        <div class="person">
                            @if($applicantUser?->hasAvatar())
                                <img class="room-ava-img" src="{{ asset($applicantUser->avatar) }}" alt="">
                            @else
                                <div class="room-ava">{{ $interview->applicant?->user?->initials(1) ?? '?' }}</div>
                            @endif
                            <div style="min-width:0;">
                                <div class="n">{{ $applicantName }}</div>
                                <div class="r">{{ $applicantUser?->email ?? '—' }} · {{ $interview->applicant?->phone ?: '—' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="panel">
                        <h3>{{ __('Детали встречи') }}</h3>
                        <div class="fact">
                            <div class="l">{{ __('Когда') }}</div>
                            <div class="v">{{ $interview->scheduled_at->format('d.m.Y, H:i') }} · {{ $interview->duration_minutes }} мин</div>
                        </div>
                        @if($interview->vacancy)
                            <div class="fact">
                                <div class="l">{{ __('Вакансия') }}</div>
                                <div class="v">
                                    <a href="{{ route('public.vacancies.show', $interview->vacancy) }}"
                                       style="color:var(--accent);">{{ $interview->vacancy->title }}</a>
                                </div>
                            </div>
                        @endif
                        <div class="fact">
                            <div class="l">{{ __('Статус') }}</div>
                            <div class="v"><span class="status-pill {{ $interview->status }}">{{ $interview->statusLabel() }}</span></div>
                        </div>
                        @if($interview->note)
                            <div class="fact">
                                <div class="l">{{ __('Комментарий') }}</div>
                                <div class="v" style="font-weight:500; white-space:pre-line;">{{ $interview->note }}</div>
                            </div>
                        @endif
                    </div>

                    <div class="panel">
                        <h3>{{ __('Действия') }}</h3>
                        <div class="act">
                            @if(! $isEmployer && $interview->status === 'scheduled')
                                <form action="{{ route('public.interviews.status', $interview) }}" method="post">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="confirmed">
                                    <button class="ok" type="submit">{{ __('Подтвердить участие') }}</button>
                                </form>
                                <form action="{{ route('public.interviews.status', $interview) }}" method="post">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="declined">
                                    <button class="no" type="submit">{{ __('Отклонить') }}</button>
                                </form>
                            @endif

                            @if($isEmployer && ! in_array($interview->status, ['canceled', 'finished'], true))
                                <form action="{{ route('public.interviews.status', $interview) }}" method="post">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="finished">
                                    <button type="submit">{{ __('Отметить завершённым') }}</button>
                                </form>
                                <form action="{{ route('public.interviews.status', $interview) }}" method="post"
                                      onsubmit="return confirm('Отменить собеседование?');">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="canceled">
                                    <button class="no" type="submit">{{ __('Отменить встречу') }}</button>
                                </form>
                            @endif

                            <button type="button" onclick="copyRoomLink(this)">{{ __('Скопировать ссылку') }}</button>
                        </div>
                    </div>

                    <div class="panel">
                        <h3>{{ __('Как это работает') }}</h3>
                        <div class="hint-list">
                            <div class="hint"><span class="b">1</span> {{ __('Комната открывается за 15 минут до начала и закрывается через полчаса после окончания.') }}</div>
                            <div class="hint"><span class="b">2</span> {{ __('Разрешите браузеру доступ к камере и микрофону — установка программ не нужна.') }}</div>
                            <div class="hint"><span class="b">3</span> {{ __('Ссылка на комнату доступна только вам двоим.') }}</div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <script>
        // если собеседник отклонил или отменил звонок — закрываем комнату
        (function () {
            const statusUrl = @json(route('public.calls.status', $interview));
            const room = document.getElementById('jitsi');

            if (!room) { return; }

            const timer = setInterval(function () {
                fetch(statusUrl, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                    .then(r => r.ok ? r.json() : null)
                    .then(function (data) {
                        if (!data || data.open) { return; }

                        clearInterval(timer);
                        room.innerHTML = '<div style="padding:64px 24px; text-align:center; color:#fff;">'
                            + '<div style="font-size:30px; margin-bottom:12px;">📵</div>'
                            + '<b style="display:block; font-size:18px; margin-bottom:6px;">' + data.label + '</b>'
                            + 'Звонок завершён, комната закрыта.</div>';
                    })
                    .catch(() => {});
            }, 5000);
        })();

        function copyRoomLink(btn) {
            navigator.clipboard.writeText(window.location.href).then(function () {
                const text = btn.textContent;
                btn.textContent = 'Ссылка скопирована';
                setTimeout(() => btn.textContent = text, 1800);
            });
        }
    </script>

    @if($roomOpen)
        @php($jitsiDomain = config('services.jitsi.domain'))
        <script src="https://{{ $jitsiDomain }}/external_api.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const container = document.getElementById('jitsi');
                const roomUrl = @json('https://'.$jitsiDomain.'/'.$interview->room);

                if (!container || typeof JitsiMeetExternalAPI === 'undefined') {
                    if (container) {
                        // запасной путь: прямая ссылка работает и тогда, когда
                        // встроенный модуль не загрузился или его заблокировали
                        container.innerHTML =
                            '<div style="padding:56px 24px; text-align:center; color:#fff;">' +
                            'Не удалось загрузить видеомодуль. ' +
                            '<a href="' + roomUrl + '" target="_blank" rel="noopener" ' +
                            'style="color:#8FB6FF; text-decoration:underline;">' +
                            'Откройте комнату в новой вкладке</a>.' +
                            '</div>';
                    }
                    return;
                }

                new JitsiMeetExternalAPI(@json($jitsiDomain), {
                    roomName: @json($interview->room),
                    parentNode: container,
                    width: '100%',
                    height: 580,
                    userInfo: {
                        displayName: @json($me->name),
                        email: @json($me->email)
                    },
                    configOverwrite: {
                        prejoinPageEnabled: true,
                        startWithAudioMuted: false,
                        startWithVideoMuted: false
                    },
                    interfaceConfigOverwrite: {
                        SHOW_JITSI_WATERMARK: false,
                        DEFAULT_REMOTE_DISPLAY_NAME: 'Собеседник'
                    }
                });
            });
        </script>
    @endif

@endsection
