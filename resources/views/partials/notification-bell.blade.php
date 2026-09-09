{{-- Ящик уведомлений в шапке кабинета: колокольчик со счётчиком и последние записи. --}}
@auth
    @php
        $bellItems = \App\Models\UserNotification::where('user_id', auth()->id())
            ->latest('id')
            ->take(8)
            ->get();
        $bellUnread = auth()->user()->unreadNotificationsCount();
    @endphp

    <style>
        .nb{position:relative;}
        .nb-btn{
            position:relative; width:42px; height:42px; border-radius:50%;
            border:1px solid var(--gray-200); background:var(--white);
            cursor:pointer; font-size:18px; line-height:1;
            display:flex; align-items:center; justify-content:center;
        }
        .nb-btn:hover{border-color:var(--gray-300);}
        .nb-count{
            position:absolute; top:-4px; right:-4px; min-width:19px; height:19px;
            padding:0 5px; border-radius:999px; background:#DC2626; color:#fff;
            font-size:11px; font-weight:800; line-height:19px; text-align:center;
        }
        .nb-count[hidden]{display:none !important;}

        .nb-panel{
            position:absolute; top:52px; right:0; z-index:9997;
            width:min(380px, calc(100vw - 32px));
            background:var(--white); border:1px solid var(--gray-200); border-radius:14px;
            box-shadow:0 24px 60px -22px rgba(15,23,42,.4);
            overflow:hidden; display:none;
        }
        .nb-panel.on{display:block;}
        .nb-head{
            display:flex; align-items:center; justify-content:space-between;
            padding:13px 16px; border-bottom:1px solid var(--gray-100);
            font-size:14px; font-weight:700;
        }
        .nb-head form{margin:0;}
        .nb-head button{
            border:none; background:none; cursor:pointer; color:#1656D6;
            font-family:inherit; font-size:12.5px; font-weight:700; padding:0;
        }
        .nb-list{max-height:60vh; overflow-y:auto;}
        .nb-item{
            display:flex; gap:11px; padding:12px 16px; text-decoration:none; color:inherit;
            border-bottom:1px solid var(--gray-100);
        }
        .nb-item:last-child{border-bottom:none;}
        .nb-item:hover{background:var(--gray-100);}
        .nb-item.unread{background:#F5F7FF;}
        .nb-item.unread:hover{background:#EEF0FF;}
        .nb-ico{font-size:17px; line-height:1.3; flex-shrink:0;}
        .nb-txt{min-width:0;}
        .nb-txt b{display:block; font-size:13.5px; font-weight:700; line-height:1.4;}
        .nb-txt span{
            display:block; font-size:12.5px; color:var(--gray-500); margin-top:3px; line-height:1.5;
            overflow:hidden; text-overflow:ellipsis; display:-webkit-box;
            -webkit-line-clamp:2; -webkit-box-orient:vertical;
        }
        .nb-txt time{display:block; font-size:11.5px; color:var(--gray-300); margin-top:4px;}
        .nb-empty{padding:34px 16px; text-align:center; color:var(--gray-500); font-size:13.5px;}
        .nb-foot{
            padding:11px 16px; border-top:1px solid var(--gray-100); text-align:center;
        }
        .nb-foot a{color:#1656D6; text-decoration:none; font-size:13px; font-weight:700;}
    </style>

    <div class="nb" id="notificationBox">
        <button type="button" class="nb-btn" id="nbToggle"
                aria-haspopup="true" aria-expanded="false" title="{{ __('Уведомления') }}">
            🔔
            <span class="nb-count" id="nbCount" data-live="nav-notifications" data-live-hide-zero @if($bellUnread === 0) hidden @endif>{{ $bellUnread }}</span>
        </button>

        <div class="nb-panel" id="nbPanel" role="menu">
            <div class="nb-head">
                <span>{{ __('Уведомления') }}</span>
                @if($bellUnread > 0)
                    <form action="{{ route('public.notifications.read') }}" method="post">
                        @csrf
                        <button type="submit">{{ __('Прочитано') }}</button>
                    </form>
                @endif
            </div>

            <div class="nb-list">
                @forelse($bellItems as $item)
                    <a class="nb-item {{ $item->isUnread() ? 'unread' : '' }}"
                       href="{{ route('public.notifications.open', $item) }}">
                        <span class="nb-ico">{{ $item->icon() }}</span>
                        <span class="nb-txt">
                            <b>{{ $item->title }}</b>
                            @if($item->body)<span>{{ $item->body }}</span>@endif
                            <time>{{ $item->created_at->format('d.m.Y H:i') }}</time>
                        </span>
                    </a>
                @empty
                    <div class="nb-empty">{{ __('Новых уведомлений нет') }}</div>
                @endforelse
            </div>

            <div class="nb-foot">
                <a href="{{ route('public.notifications.index') }}">{{ __('Все уведомления') }}</a>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const box = document.getElementById('notificationBox');
            const toggle = document.getElementById('nbToggle');
            const panel = document.getElementById('nbPanel');
            const count = document.getElementById('nbCount');

            if (!box || !toggle || !panel) { return; }

            toggle.addEventListener('click', function (event) {
                event.stopPropagation();
                const open = panel.classList.toggle('on');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });

            document.addEventListener('click', function (event) {
                if (!box.contains(event.target)) {
                    panel.classList.remove('on');
                    toggle.setAttribute('aria-expanded', 'false');
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') { panel.classList.remove('on'); }
            });

            // счётчик обновляем без перезагрузки страницы
            setInterval(function () {
                fetch('{{ route('public.notifications.count') }}', {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                    .then(r => r.ok ? r.json() : null)
                    .then(function (data) {
                        if (!data) { return; }
                        count.textContent = data.unread;
                        count.hidden = data.unread === 0;
                    })
                    .catch(() => {});
            }, 30000);
        })();
    </script>
@endauth
