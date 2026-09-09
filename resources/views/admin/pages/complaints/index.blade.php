
@extends('admin.layouts.app')

@section('main')
    <style>
        .cp-wrap { margin: 0 3vh 2.5vh 2vh; }
        .cp-card {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .02);
        }
        .cp-kpi { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
        .cp-kpi .box { padding: 18px 20px; }
        .cp-kpi .l { font-size: 12.5px; color: #94a3b8; font-weight: 600; }
        .cp-kpi .v { font-size: 24px; font-weight: 800; color: #1e293b; margin-top: 6px; }

        .cp-filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin: 0 3vh 18px 2vh; }
        .pill {
            padding: 9px 18px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
            cursor: pointer; font-weight: 500; color: #64748b; font-size: 14px; transition: .15s;
        }
        .pill:hover { background: #f8fafc; color: #334155; }
        .pill.on { background: #1656D6; color: #fff; border-color: #1656D6; }

        .cp-item { padding: 18px 22px; border-bottom: 1px solid #f1f5f9; }
        .cp-item:last-child { border-bottom: none; }
        .cp-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
        .cp-target { font-weight: 700; font-size: 15px; color: #1e293b; }
        .cp-meta { font-size: 12.5px; color: #94a3b8; margin-top: 4px; }
        .cp-reason { font-size: 13.5px; color: #475569; margin-top: 8px; }
        .cp-message {
            margin-top: 10px; padding: 12px 14px; background: #f8fafc; border-radius: 10px;
            font-size: 13.5px; color: #475569; line-height: 1.6; white-space: pre-line;
        }
        .cp-answer {
            margin-top: 10px; padding: 12px 14px; background: #F0FDF4; border-radius: 10px;
            font-size: 13.5px; color: #15803D; line-height: 1.6;
        }

        .tag { font-size: 11.5px; font-weight: 700; padding: 5px 11px; border-radius: 999px; white-space: nowrap; }
        .tag.vacancy { background: #EAF2FF; color: #1656D6; }
        .tag.resume { background: #F5EEFF; color: #6D28D9; }
        .tag.company { background: #FFF7E6; color: #B7791F; }
        .tag.user { background: #F1F5F9; color: #475569; }

        .st { font-size: 11.5px; font-weight: 700; padding: 5px 11px; border-radius: 999px; white-space: nowrap; }
        .st.new { background: #FEF3C7; color: #B45309; }
        .st.in_review { background: #EAF2FF; color: #1656D6; }
        .st.resolved { background: #DCFCE7; color: #15803D; }
        .st.rejected { background: #FEE2E2; color: #B91C1C; }

        .cp-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; align-items: flex-start; }
        .cp-actions form { margin: 0; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .cp-actions button, .cp-actions .lnk {
            font-family: inherit; font-size: 13px; font-weight: 700; cursor: pointer;
            padding: 8px 15px; border-radius: 9px; border: 1px solid #e2e8f0; background: #fff; color: #475569;
        }
        .cp-actions button:hover, .cp-actions .lnk:hover { border-color: #1656D6; color: #1656D6; }
        .cp-actions .ok { background: #F0FDF4; border-color: #BBF7D0; color: #15803D; }
        .cp-actions .no { background: #FEF2F2; border-color: #FECACA; color: #B91C1C; }
        .cp-actions input[type="text"] {
            padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 9px;
            font-family: inherit; font-size: 13px; min-width: 220px;
        }
        .cp-empty { padding: 48px 20px; text-align: center; color: #94a3b8; }
        @media (max-width: 1100px) { .cp-kpi { grid-template-columns: repeat(2, 1fr); } }
    </style>

    <section class="page" id="page-complaints" style="display:block !important; background:#fcfcfd; border-radius:2vh;">

        <div class="section-header mb-4">
            <h1 class="disp font-weight-bold" style="color:#1e293b; font-size:28px; margin-left:5vh; margin-top:5vh;">Жалобы</h1>
            <div class="sub text-muted" style="font-size:.95rem; color:#64748b; margin-right:5vh;">
                Обращения пользователей на вакансии, резюме и компании
            </div>
        </div>

        @if(session('status'))
            <div class="cp-wrap cp-card" style="padding:14px 20px; border-color:#BBF7D0; background:#F0FDF4; color:#15803D; font-weight:600;">
                {{ session('status') }}
            </div>
        @endif

        <div class="cp-wrap cp-kpi">
            <div class="cp-card box"><div class="l">Всего жалоб</div><div class="v" data-live="complaints-total">{{ $counts['total'] }}</div></div>
            <div class="cp-card box"><div class="l">Поступило сегодня</div><div class="v" data-live="complaints-today">{{ $counts['today'] }}</div></div>
            <div class="cp-card box"><div class="l">За неделю</div><div class="v" data-live="complaints-week">{{ $counts['week'] }}</div></div>
            <div class="cp-card box"><div class="l">В корзине</div><div class="v" data-live="complaints-trashed">{{ $counts['trashed'] }}</div></div>
        </div>

        @php
            $link = function (array $params) {
                $query = array_filter(array_merge(request()->query(), $params), fn ($v) => $v !== null && $v !== 'all');
                unset($query['page']);

                return request()->url().($query ? '?'.http_build_query($query) : '');
            };
        @endphp

        <div class="cp-filters">
            <span style="font-size:12px; text-transform:uppercase; letter-spacing:.5px; color:#94a3b8; font-weight:700;">Список</span>
            <a class="pill {{ ! $trashed ? 'on' : '' }}" href="{{ $link(['trashed' => null]) }}">Все жалобы</a>
            <a class="pill {{ $trashed ? 'on' : '' }}" href="{{ $link(['trashed' => 1]) }}">
                Корзина <b data-live="complaints-trashed" data-live-hide-zero style="margin-left:4px;" @if(! $counts['trashed']) hidden @endif>{{ $counts['trashed'] }}</b>
            </a>

            <span style="font-size:12px; text-transform:uppercase; letter-spacing:.5px; color:#94a3b8; font-weight:700; margin-left:12px;">Объект</span>
            <a class="pill {{ $filterTarget === 'all' ? 'on' : '' }}" href="{{ $link(['target' => 'all']) }}">Все</a>
            @foreach(\App\Models\Complaint::TARGETS as $key => $label)
                <a class="pill {{ $filterTarget === $key ? 'on' : '' }}" href="{{ $link(['target' => $key]) }}">{{ $label }}</a>
            @endforeach
        </div>

        <div class="cp-filters">
            <form method="get" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                @foreach(['target' => $filterTarget, 'reason' => $filterReason] as $name => $value)
                    @if($value !== 'all')
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endif
                @endforeach

                <input type="text" name="q" value="{{ $search }}"
                       placeholder="Поиск по автору, тексту жалобы или названию объекта"
                       style="padding:8px 12px; border:1px solid #e2e8f0; border-radius:9px;
                              font-family:inherit; font-size:13px; min-width:260px;">

                <select name="sort" style="padding:8px 12px; border:1px solid #e2e8f0; border-radius:9px;
                                            font-family:inherit; font-size:13px;">
                    <option value="newest" @selected($sort === 'newest')>Сначала новые</option>
                    <option value="oldest" @selected($sort === 'oldest')>Сначала старые</option>
                </select>

                <button type="submit" style="padding:8px 16px; border:none; border-radius:9px; cursor:pointer;
                        background:#1656D6; color:#fff; font-family:inherit; font-size:13px; font-weight:600;">
                    Найти
                </button>

                @if($search !== '' || $sort !== 'newest')
                    <a href="{{ route('superadmin.complaints') }}"
                       style="font-size:13px; color:#64748b; text-decoration:none;">Сбросить</a>
                @endif
            </form>
        </div>

        <div class="cp-filters">
            <span style="font-size:12px; text-transform:uppercase; letter-spacing:.5px; color:#94a3b8; font-weight:700;">Причина</span>
            <a class="pill {{ $filterReason === 'all' ? 'on' : '' }}" href="{{ $link(['reason' => 'all']) }}">Все</a>
            @foreach(\App\Models\Complaint::REASONS as $key => $label)
                <a class="pill {{ $filterReason === $key ? 'on' : '' }}" href="{{ $link(['reason' => $key]) }}">{{ $label }}</a>
            @endforeach
        </div>

        <div class="cp-wrap cp-card" style="margin-bottom:4vh;">
            <div id="complaints-list">
                @forelse($complaints as $complaint)
                    <div class="cp-item" data-status="{{ $complaint->status }}" data-target="{{ $complaint->target_type }}">
                        <div class="cp-top">
                            <div style="min-width:0;">
                                <div class="cp-target">
                                    @if($complaint->targetUrl())
                                        <a href="{{ $complaint->targetUrl() }}" style="color:inherit;">{{ $complaint->targetTitle() }}</a>
                                    @else
                                        {{ $complaint->targetTitle() }}
                                    @endif
                                </div>
                                <div class="cp-meta">
                                    Жалоба №{{ $complaint->id }} ·
                                    от {{ $complaint->user?->name ?? 'пользователь удалён' }}
                                    ({{ $complaint->user?->email ?? '—' }}) ·
                                    {{ $complaint->created_at->format('d.m.Y H:i') }}
                                    @if($complaint->resolved_at)
                                        · закрыта {{ $complaint->resolved_at->format('d.m.Y H:i') }}
                                    @endif
                                </div>
                                <div class="cp-reason">Причина: <b>{{ $complaint->reasonLabel() }}</b></div>
                            </div>

                            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; justify-content:flex-end;">
                                @php
                                    $repeats = $duplicates[$complaint->target_type.'-'.$complaint->target_id] ?? 1;
                                @endphp
                                @if($repeats > 1)
                                    <a href="{{ $link(['object_type' => $complaint->target_type, 'object_id' => $complaint->target_id]) }}"
                                       style="font-size:11.5px; font-weight:700; padding:5px 10px; border-radius:999px;
                                              background:#FEF3C7; color:#B45309; text-decoration:none; white-space:nowrap;">
                                        ещё {{ $repeats - 1 }} на этот объект
                                    </a>
                                @endif
                                <span class="tag {{ $complaint->target_type }}">{{ $complaint->targetLabel() }}</span>

                                {{-- статус ведёт владелец объекта, администратор его не ставит --}}
                                <span class="st {{ $complaint->status }}" title="Ответ владельца объекта">
                                    владелец: {{ $complaint->statusLabel() }}
                                </span>
                            </div>
                        </div>

                        @if($complaint->message)
                            <div class="cp-message">{{ $complaint->message }}</div>
                        @endif

                        @if($complaint->admin_comment)
                            <div class="cp-answer">Последняя мера: {{ $complaint->admin_comment }}</div>
                        @endif

                        @if($complaint->target_type === 'user')
                            @php
                                $chat = \App\Models\Conversation::where(function ($q) use ($complaint) {
                                    $q->whereHas('applicant', fn ($a) => $a->where('user_id', $complaint->user_id))
                                        ->orWhereHas('employer', fn ($e) => $e->where('user_id', $complaint->user_id));
                                })->where(function ($q) use ($complaint) {
                                    $q->whereHas('applicant', fn ($a) => $a->where('user_id', $complaint->target_id))
                                        ->orWhereHas('employer', fn ($e) => $e->where('user_id', $complaint->target_id));
                                })->first();
                            @endphp

                            @if($chat)
                                <details style="margin-top:12px;">
                                    <summary style="cursor:pointer; font-size:13px; font-weight:600; color:#1656D6;">
                                        Переписка между участниками
                                    </summary>
                                    <div style="margin-top:10px; display:flex; flex-direction:column; gap:8px;">
                                        @foreach($chat->messages()->with('user')->latest('id')->take(5)->get()->reverse() as $m)
                                            <div style="background:#f8fafc; border-radius:10px; padding:9px 12px; font-size:13px;">
                                                <b style="font-size:12.5px; color:#334155;">{{ $m->user?->name ?? 'Пользователь' }}</b>
                                                <span style="color:#94a3b8; font-size:11.5px;"> · {{ $m->created_at->format('d.m.Y H:i') }}</span>
                                                <div style="color:#475569; margin-top:3px;">
                                                    {{ $m->body ?: ('📎 '.($m->attachment_name ?: 'вложение')) }}
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        @endif

                        @if($complaint->actions->isNotEmpty())
                            <details style="margin-top:12px;">
                                <summary style="cursor:pointer; font-size:13px; font-weight:600; color:#64748b;">
                                    История решений ({{ $complaint->actions->count() }})
                                </summary>
                                <div style="margin-top:10px; display:flex; flex-direction:column; gap:6px;">
                                    @foreach($complaint->actions as $entry)
                                        <div style="font-size:12.5px; color:#475569;">
                                            {{ $entry->created_at->format('d.m.Y H:i') }} ·
                                            <b>{{ $entry->admin?->name ?? 'модератор' }}</b> —
                                            {{ $entry->label() }}
                                            @if($entry->comment)
                                                <span style="color:#94a3b8;">({{ $entry->comment }})</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endif

                        <div class="cp-actions">
                            {{-- Права администратора: смотреть, блокировать и удалять.
                                 Ни содержимое пользователей, ни судьбу жалобы он не решает.
                                 Тот же список проверяется на сервере в act(). --}}
                            @php
                                $allowed = $complaint->allowedActions();
                                $labels = [
                                    'block_user' => ['Заблокировать', 'no', 'Заблокировать владельца объекта?'],
                                    'unblock_user' => ['Разблокировать', 'ok', 'Разблокировать владельца объекта?'],
                                ];
                            @endphp

                            @forelse($allowed as $action)
                                <form action="{{ route('superadmin.complaints.act', $complaint) }}" method="post"
                                      onsubmit="return confirm('{{ $labels[$action][2] }}');">
                                    @csrf
                                    <input type="hidden" name="action" value="{{ $action }}">
                                    <button class="{{ $labels[$action][1] }}" type="submit">{{ $labels[$action][0] }}</button>
                                </form>
                            @empty
                                <span style="font-size:12.5px; color:#94a3b8;">Блокировка недоступна: объект удалён либо принадлежит администратору</span>
                            @endforelse

                            @if($complaint->targetUrl())
                                <a class="lnk" href="{{ $complaint->targetUrl() }}">Открыть объект</a>
                            @endif

                            @if($trashed)
                                <form action="{{ route('superadmin.complaints.restore', $complaint->id) }}" method="post">
                                    @csrf
                                    <button class="ok" type="submit">Вернуть из корзины</button>
                                </form>
                            @else
                            <form action="{{ route('superadmin.complaints.destroy', $complaint) }}" method="post"
                                  onsubmit="return confirm('Убрать жалобу в корзину?');">
                                @csrf @method('DELETE')
                                <button class="no" type="submit">В корзину</button>
                            </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="cp-empty">
                        <div style="font-size:26px; margin-bottom:10px;">🎉</div>
                        Жалоб пока нет — пользователи ни на что не жаловались.
                    </div>
                @endforelse
            </div>

            @if($complaints->hasPages())
                <div style="padding:16px 20px; border-top:1px solid #e2e8f0;">
                    {{ $complaints->links() }}
                </div>
            @endif

        </div>
    </section>

@endsection
