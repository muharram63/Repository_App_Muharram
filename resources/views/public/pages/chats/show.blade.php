@extends('public.layouts.app')
@section('content')

    <style>
        .chat-layout{display:grid; grid-template-columns:300px minmax(0,1fr); gap:20px; align-items:start;}

        .chat-side{
            background:var(--surface); border:1px solid var(--border);
            border-radius:18px; overflow:hidden; max-height:70vh; overflow-y:auto;
        }
        .chat-side-item{
            display:flex; align-items:center; gap:11px; padding:14px 16px;
            border-bottom:1px solid var(--border); transition:background .15s ease;
        }
        .chat-side-item:last-child{border-bottom:none;}
        .chat-side-item:hover{background:var(--surface-alt);}
        .chat-side-item.active{background:var(--accent-soft);}
        .chat-side-name{font-weight:700; font-size:14px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;}
        .chat-side-last{
            font-size:12.5px; color:var(--text-muted); margin-top:2px;
            overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
        }

        .chat-box{
            background:var(--surface); border:1px solid var(--border);
            border-radius:18px; overflow:hidden; display:flex; flex-direction:column;
            height:70vh; min-height:480px;
        }
        .chat-head{
            display:flex; align-items:center; gap:13px; padding:16px 20px;
            border-bottom:1px solid var(--border);
            background:linear-gradient(180deg, var(--surface-alt), var(--surface));
        }
        .chat-head-name{font-weight:800; font-size:15.5px;}
        .chat-head-sub{font-size:12.5px; color:var(--text-muted); margin-top:2px;}

        .chat-ava, .chat-ava-img{width:42px; height:42px; border-radius:50%; flex-shrink:0; object-fit:cover;}
        .chat-ava{
            background:var(--accent-soft); color:var(--accent-text);
            display:flex; align-items:center; justify-content:center; font-weight:800; font-size:16px;
        }
        .chat-ava.sm, .chat-ava-img.sm{width:38px; height:38px; font-size:14px;}

        .chat-feed{
            flex:1; overflow-y:auto; padding:16px 20px;
            display:flex; flex-direction:column; gap:7px;
            background:
                radial-gradient(600px 220px at 100% 0%, var(--accent-soft), transparent 70%),
                var(--surface);
        }
        .msg{max-width:74%; min-width:0; display:flex; flex-direction:column; gap:2px;}
        .msg.mine{align-self:flex-end; align-items:flex-end;}
        .msg-bubble{
            padding:6px 12px; border-radius:14px; font-size:14px; line-height:1.4;
            background:var(--surface-alt); border:1px solid var(--border);
            overflow-wrap:anywhere;
            max-width:100%; box-sizing:border-box; overflow:hidden;
        }
        /* переносы сохраняем только в самом тексте сообщения */
        .msg-text{display:block; white-space:pre-line; overflow-wrap:anywhere;}
        .msg-bubble > :first-child{margin-top:0;}
        .msg-bubble > :last-child{margin-bottom:0;}
        .msg.mine .msg-bubble{
            background:var(--accent); border-color:var(--accent); color:#fff;
            border-bottom-right-radius:5px;
        }
        .msg:not(.mine) .msg-bubble{border-bottom-left-radius:5px;}
        .msg-meta{font-size:11px; line-height:1.3; color:var(--text-muted); display:flex; align-items:center; gap:8px;}
        .msg-del, .msg-edit, .msg-reply{
            border:none; background:none; padding:0; cursor:pointer;
            font-family:inherit; font-size:11.5px; font-weight:600;
            color:var(--text-muted); opacity:0; transition:opacity .15s ease, color .15s ease;
        }
        .msg:hover .msg-del, .msg:hover .msg-edit, .msg:hover .msg-reply,
        .msg-del:focus, .msg-edit:focus, .msg-reply:focus{opacity:1;}
        .msg-reply:hover{color:var(--accent);}
        .msg-del:hover{color:#B91C1C;}
        .msg-edit:hover{color:var(--accent);}
        .msg-meta form{margin:0; display:inline;}
        .msg-mark{font-style:italic; opacity:.75;}

        /* статус доставки: одна галочка — отправлено, две — прочитано */
        .msg-check{display:inline-flex; align-items:center; gap:3px; font-weight:700; letter-spacing:-2px;}
        .msg-check.read{color:var(--accent);}
        .msg-check .lbl{letter-spacing:0; font-weight:600; margin-left:5px;}

        /* цитата внутри сообщения */
        .msg-quote{
            display:block; width:100%; text-align:left; cursor:pointer;
            border:none; border-left:3px solid var(--accent);
            background:color-mix(in srgb, var(--accent) 8%, transparent);
            border-radius:8px; padding:5px 9px; margin:3px 0 4px;
            font-family:inherit; font-size:12.5px; line-height:1.4; color:var(--text-muted);
            max-width:100%; box-sizing:border-box; overflow:hidden;
        }
        .msg-quote b{display:block; color:var(--accent-text); font-size:12px; margin-bottom:2px;}
        .msg.mine .msg-quote{
            border-left-color:rgba(255,255,255,.6);
            background:rgba(255,255,255,.14); color:rgba(255,255,255,.85);
        }
        .msg.mine .msg-quote b{color:#fff;}
        .msg.flash .msg-bubble{
            outline:2px solid var(--accent);
            outline-offset:2px;
            transition:outline-color 1.2s ease;
        }

        /* кнопка звонка в шапке диалога */
        .call-btn{
            display:inline-flex; align-items:center; gap:8px; flex-shrink:0;
            height:38px; padding:0 16px; border-radius:12px; cursor:pointer;
            background:var(--accent); color:#fff; border:none;
            font-family:inherit; font-size:13.5px; font-weight:700;
        }
        .call-btn:hover{background:var(--accent-ink);}
        .call-btn svg{width:16px; height:16px;}

        /* приглашение на звонок в ленте */
        .msg-call{
            display:flex; align-items:center; gap:10px;
            padding:8px 11px; border-radius:12px; margin:2px 0;
            width:100%; max-width:100%; box-sizing:border-box;
            background:color-mix(in srgb, var(--accent) 10%, transparent);
            border:1px solid color-mix(in srgb, var(--accent) 35%, transparent);
        }
        .msg.mine .msg-call{background:rgba(255,255,255,.16); border-color:rgba(255,255,255,.4);}
        .msg-call .ico{
            width:38px; height:38px; border-radius:12px; flex-shrink:0;
            background:var(--accent); color:#fff;
            display:flex; align-items:center; justify-content:center;
        }
        .msg-call .ico svg{width:18px; height:18px;}
        .msg-call .t{flex:1; min-width:0;}
        .msg-call .t b{display:block; font-size:14px;}
        .msg-call .t span{font-size:12.5px; opacity:.8;}
        .msg-call .join{
            flex-shrink:0; padding:8px 14px; border-radius:10px;
            background:var(--accent); color:#fff; font-size:13px; font-weight:700; white-space:nowrap;
        }
        .msg.mine .msg-call .join{background:#fff; color:var(--accent-text);}
        .msg-call.missed{
            background:color-mix(in srgb, #EF4444 10%, transparent);
            border-color:color-mix(in srgb, #EF4444 35%, transparent);
        }
        .msg.mine .msg-call.missed{background:rgba(255,255,255,.16); border-color:rgba(255,255,255,.4);}
        .msg-call.missed .ico{background:#EF4444;}
        .msg-call .again{
            flex-shrink:0; padding:8px 14px; border-radius:10px; white-space:nowrap;
            border:1px solid color-mix(in srgb, var(--accent) 35%, transparent);
            background:none; color:var(--accent-text);
            font-family:inherit; font-size:13px; font-weight:700; cursor:pointer;
        }
        .msg.mine .msg-call .again{background:#fff; border-color:#fff; color:var(--accent-text);}

        /* вложения */
        .msg-img{
            display:block; max-width:100%; max-height:200px; width:auto; border-radius:12px;
            margin:3px 0; cursor:zoom-in; object-fit:cover;
        }
        .msg-file{
            display:flex; align-items:center; gap:9px; margin:3px 0;
            padding:6px 9px; border-radius:11px;
            background:color-mix(in srgb, var(--accent) 9%, transparent);
            color:var(--text); text-decoration:none;
            width:100%; max-width:100%; box-sizing:border-box; overflow:hidden;
        }
        .msg.mine .msg-file{background:rgba(255,255,255,.16); color:#fff;}
        .msg-file .ico{
            width:28px; height:28px; border-radius:9px; flex-shrink:0;
            background:var(--surface); color:var(--accent-text);
            display:flex; align-items:center; justify-content:center;
        }
        .msg.mine .msg-file .ico{background:rgba(255,255,255,.24); color:#fff;}
        .msg-file .ico svg{width:15px; height:15px;}
        /* подпись занимает остаток ширины и обрезается многоточием */
        .msg-file .txt{flex:1 1 auto; min-width:0; display:block;}
        .msg-file .nm{
            display:block; max-width:100%; font-size:12.5px; font-weight:600;
            overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
        }
        .msg-file .sz{display:block; font-size:11px; opacity:.75;}

        /* кнопка «скрепка» и выбранный файл */
        .chat-attach{
            display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;
            width:46px; height:46px; border-radius:14px; cursor:pointer;
            background:var(--surface-alt); border:1px solid var(--border); color:var(--text-muted);
        }
        .chat-attach:hover{border-color:var(--accent); color:var(--accent);}
        .chat-attach svg{width:19px; height:19px;}
        .file-bar{
            display:none; align-items:center; gap:10px;
            padding:10px 16px; border-top:1px solid var(--border); background:var(--surface-alt);
            font-size:13px; color:var(--text-muted);
        }
        .file-bar.on{display:flex;}
        .file-bar .nm{flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--text); font-weight:600;}
        .file-bar button{
            border:none; background:none; cursor:pointer; font-size:18px; line-height:1;
            color:var(--text-muted); padding:0 4px;
        }
        .file-bar button:hover{color:#B91C1C;}

        /* панель «отвечаем на …» над полем ввода */
        .reply-bar{
            display:none; align-items:flex-start; gap:10px;
            padding:10px 16px; border-top:1px solid var(--border);
            background:var(--surface-alt);
        }
        .reply-bar.on{display:flex;}
        .reply-bar .txt{flex:1; min-width:0; font-size:13px; color:var(--text-muted); line-height:1.45;}
        .reply-bar .txt b{display:block; color:var(--text); font-size:12.5px;}
        .reply-bar .txt span{display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;}
        .reply-bar button{
            border:none; background:none; cursor:pointer; font-size:18px; line-height:1;
            color:var(--text-muted); padding:0 4px;
        }
        .reply-bar button:hover{color:#B91C1C;}
        @media (hover: none){ .msg-del, .msg-edit, .msg-reply{opacity:1;} }

        /* форма редактирования вместо пузыря */
        .msg-edit-form{display:none; flex-direction:column; gap:8px; width:100%;}
        .msg.editing .msg-edit-form{display:flex;}
        .msg.editing .msg-bubble{display:none;}
        .msg-edit-form textarea{
            width:100%; min-width:240px; min-height:70px; resize:vertical;
            padding:10px 13px; border-radius:14px;
            background:var(--surface-alt); border:1px solid var(--accent);
            color:var(--text); font-family:inherit; font-size:14.5px; line-height:1.5; outline:none;
        }
        .msg-edit-actions{display:flex; gap:8px; justify-content:flex-end;}
        .msg-edit-actions button{
            font-family:inherit; font-size:12.5px; font-weight:700; cursor:pointer;
            padding:7px 14px; border-radius:10px; border:1px solid var(--border);
            background:var(--surface); color:var(--text-muted);
        }
        .msg-edit-actions .save{background:var(--accent); border-color:var(--accent); color:#fff;}

        .chat-day{
            align-self:center; font-size:11.5px; font-weight:700; color:var(--text-muted);
            background:var(--surface-alt); border:1px solid var(--border);
            padding:4px 12px; border-radius:999px;
        }
        .chat-empty-feed{margin:auto; text-align:center; color:var(--text-muted); font-size:14px;}

        .chat-form{
            display:flex; gap:10px; padding:14px 16px; border-top:1px solid var(--border);
            background:var(--surface);
        }
        .chat-form textarea{
            flex:1; resize:none; font-family:inherit; font-size:14.5px; line-height:1.5;
            padding:11px 14px; border-radius:14px; min-height:46px; max-height:120px;
            background:var(--surface-alt); border:1px solid var(--border); color:var(--text); outline:none;
        }
        .chat-form textarea:focus{border-color:var(--accent);}
        .chat-form button{
            flex-shrink:0; font-family:inherit; font-size:14px; font-weight:700; cursor:pointer;
            padding:0 22px; border-radius:14px; border:none; background:var(--accent); color:#fff;
        }
        .chat-form button:hover{background:var(--accent-ink);}

        .back-link{
            display:inline-flex; gap:8px; font-size:14px; font-weight:600;
            color:var(--text-muted); margin-bottom:18px;
        }
        .back-link:hover{color:var(--accent);}

        @media (max-width:980px){
            .chat-layout{grid-template-columns:1fr;}
            .chat-side{display:none;}
            .chat-box{height:76vh;}
        }
    </style>

    @php
        $counterName = $isEmployer
            ? ($conversation->applicant?->user?->name ?? 'Соискатель')
            : ($conversation->employer?->company_name ?? 'Компания');
        $counterAvatar = $isEmployer
            ? $conversation->applicant?->user?->avatar
            : $conversation->employer?->user?->avatar;
        $counterSub = $isEmployer
            ? ($conversation->applicant?->city ?: 'Соискатель')
            : ($conversation->employer?->user?->name ?? 'Работодатель');
        $counterUser = $isEmployer
            ? $conversation->applicant?->user
            : $conversation->employer?->user;
        $lastId = $messages->last()->id ?? 0;
    @endphp

    <section class="section">
        <div class="container">

            <a href="{{ route('public.chats.index') }}" class="back-link">{{ __('← Все диалоги') }}</a>

            <div class="chat-layout">

                {{-- ===== список диалогов ===== --}}
                <div class="chat-side">
                    @foreach($conversations as $item)
                        @php
                            $itemName = $isEmployer
                                ? ($item->applicant?->user?->name ?? 'Соискатель')
                                : ($item->employer?->company_name ?? 'Компания');
                            $itemAvatar = $isEmployer
                                ? $item->applicant?->user?->avatar
                                : $item->employer?->user?->avatar;
                        @endphp
                        <a class="chat-side-item {{ $item->id === $conversation->id ? 'active' : '' }}"
                           href="{{ route('public.chats.show', $item) }}">
                            @if($itemAvatar)
                                <img class="chat-ava-img sm" src="{{ asset($itemAvatar) }}" alt="">
                            @else
                                <div class="chat-ava sm">{{ $isEmployer ? ($item->applicant?->user?->initials(1) ?? '?') : ($item->employer?->initials(2) ?? '?') }}</div>
                            @endif
                            <div style="min-width:0;">
                                <div class="chat-side-name">{{ $itemName }}</div>
                                <div class="chat-side-last">
                                    @if(!$item->lastMessage){{ __('Нет сообщений') }}@elseif($item->lastMessage->body){{ \Illuminate\Support\Str::limit($item->lastMessage->body, 40) }}@else📎 {{ __('Файл') }}@endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                {{-- ===== переписка ===== --}}
                <div class="chat-box">
                    <div class="chat-head">
                        @if($counterAvatar)
                            <img class="chat-ava-img" src="{{ asset($counterAvatar) }}" alt="">
                        @else
                            <div class="chat-ava">{{ $isEmployer ? ($conversation->applicant?->user?->initials(1) ?? '?') : ($conversation->employer?->initials(2) ?? '?') }}</div>
                        @endif
                        <form id="callForm" action="{{ route('public.chats.call', $conversation) }}" method="post" style="order:3; margin:0 0 0 auto;">
                            @csrf
                            <button type="submit" class="call-btn" title="{{ __('Начать видеозвонок') }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"/><rect x="2" y="6" width="14" height="12" rx="2"/></svg>
                                {{ __('Позвонить') }}
                            </button>
                        </form>

                        <div style="order:4; margin-left:12px;">
                            @include('partials.complaint-form', [
                                'targetType' => 'user',
                                'targetId' => $counterUser?->id ?? 0,
                                'compact' => true,
                            ])
                        </div>

                        <div style="min-width:0;">
                            <div class="chat-head-name">{{ $counterName }}</div>
                            <div class="chat-head-sub">
                                {{ $counterSub }}
                                @if($conversation->vacancy) · {{ $conversation->vacancy->title }} @endif
                            </div>
                        </div>
                    </div>

                    <div class="chat-feed" id="chat-feed" data-last-id="{{ $lastId }}"
                         data-poll-url="{{ route('public.chats.poll', $conversation) }}"
                         data-server-now="{{ now()->timestamp }}">
                        @if($messages->isEmpty())
                            <div class="chat-empty-feed" id="chat-placeholder">
                                {{ __('Сообщений пока нет — напишите первым.') }}
                            </div>
                        @else
                            @php $prevDay = null; @endphp
                            @foreach($messages as $message)
                                @php $day = $message->created_at->format('d.m.Y'); @endphp
                                @if($day !== $prevDay)
                                    <div class="chat-day">{{ $day }}</div>
                                    @php $prevDay = $day; @endphp
                                @endif

                                <div class="msg {{ $message->user_id === $user->id ? 'mine' : '' }}" data-id="{{ $message->id }}">
                                    <div class="msg-bubble">
                                        @if($message->interview)
                                            @php($callMine = $message->user_id === $user->id)
                                            @php($callState = $message->callState())
                                            <div class="msg-call {{ $callState === 'missed' ? 'missed' : '' }}">
                                                <span class="ico">
                                                    @if($callState === 'missed')
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"/><rect x="2" y="6" width="14" height="12" rx="2"/><line x1="2" y1="2" x2="22" y2="22"/></svg>
                                                    @else
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"/><rect x="2" y="6" width="14" height="12" rx="2"/></svg>
                                                    @endif
                                                </span>
                                                <span class="t">
                                                    <b>{{ __($message->callTitle($callMine)) }}</b>
                                                    <span>{{ __($message->callNote($callMine)) }}</span>
                                                </span>
                                                @if($message->interview->isRoomOpen() && $callState !== 'missed')
                                                    <a class="join" href="{{ route('public.interviews.show', $message->interview) }}">{{ __('Присоединиться') }}</a>
                                                @elseif($callState === 'missed')
                                                    <button type="submit" form="callForm" class="again">{{ $callMine ? __('Позвонить снова') : __('Перезвонить') }}</button>
                                                @endif
                                            </div>
                                        @endif

                                        @if($message->attachment_path)
                                            @if($message->attachmentIsImage())
                                                <a href="{{ route('public.files.chat', [$conversation, $message]) }}" target="_blank" rel="noopener">
                                                    <img class="msg-img" src="{{ route('public.files.chat', [$conversation, $message]) }}"
                                                         alt="{{ $message->attachment_name }}">
                                                </a>
                                            @else
                                                <a class="msg-file" href="{{ route('public.files.chat', [$conversation, $message]) }}"
                                                   target="_blank" rel="noopener" download>
                                                    <span class="ico">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H8a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7z"/><path d="M14 2v5h5"/></svg>
                                                    </span>
                                                    <span class="txt">
                                                        <span class="nm">{{ $message->attachment_name }}</span>
                                                        <span class="sz">{{ $message->attachmentSizeLabel() }}</span>
                                                    </span>
                                                </a>
                                            @endif
                                        @endif

                                        @if($message->replyTo)
                                            <button type="button" class="msg-quote" data-target="{{ $message->replyTo->id }}">
                                                <b>{{ $message->replyTo->user?->name ?? __('Пользователь') }}</b>
                                                @if($message->replyTo->deleted_at){{ __('Сообщение удалено') }}@elseif($message->replyTo->body){{ \Illuminate\Support\Str::limit($message->replyTo->body, 90) }}@else{{ __('Вложение') }}@endif
                                            </button>
                                        @endif
@if($message->body)<span class="msg-text">{{ $message->body }}</span>@endif</div>

                                    @if($message->user_id === $user->id)
                                        <form class="msg-edit-form"
                                              action="{{ route('public.chats.message.update', [$conversation, $message]) }}"
                                              method="post">
                                            @csrf
                                            @method('PATCH')
                                            <textarea name="body" maxlength="2000" required>{{ $message->body }}</textarea>
                                            <div class="msg-edit-actions">
                                                <button type="button" class="cancel">{{ __('Отмена') }}</button>
                                                <button type="submit" class="save">{{ __('Сохранить') }}</button>
                                            </div>
                                        </form>
                                    @endif

                                    <div class="msg-meta">
                                        <span>
                                            {{ $message->user_id === $user->id ? 'Вы' : ($message->user?->name ?? 'Собеседник') }}
                                            · {{ $message->created_at->format('H:i') }}
                                            @if($message->edited_at)
                                                · <span class="msg-mark">{{ __('изменено') }}</span>
                                            @endif
                                            @if($message->user_id === $user->id)
                                                · <span class="msg-check {{ $message->read_at ? 'read' : '' }}"
                                                        title="{{ $message->read_at ? __('Прочитано') : __('Отправлено') }}">
                                                    @if($message->read_at)
                                                        ✓✓<span class="lbl">{{ __('Прочитано') }}</span>
                                                    @else
                                                        ✓
                                                    @endif
                                                </span>
                                            @endif
                                        </span>

                                        <button type="button" class="msg-reply"
                                                data-id="{{ $message->id }}"
                                                data-author="{{ $message->user_id === $user->id ? __('Вы') : ($message->user?->name ?? __('Собеседник')) }}"
                                                data-body="{{ $message->body ? \Illuminate\Support\Str::limit($message->body, 90) : __('Вложение') }}">{{ __('Ответить') }}</button>

                                        @if($message->user_id === $user->id)
                                            <button type="button" class="msg-edit">{{ __('Изменить') }}</button>

                                            <form action="{{ route('public.chats.message.destroy', [$conversation, $message]) }}"
                                                  method="post" onsubmit="return confirm('Удалить сообщение?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="msg-del">{{ __('Удалить') }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>

                    <div class="reply-bar" id="replyBar">
                        <div class="txt">
                            <b>{{ __('Ответ на сообщение') }}</b>
                            <span id="replyPreview"></span>
                        </div>
                        <button type="button" id="replyCancel" aria-label="{{ __('Отмена') }}">&times;</button>
                    </div>

                    <div class="file-bar" id="fileBar">
                        <span>📎</span>
                        <span class="nm" id="fileName"></span>
                        <button type="button" id="fileCancel" aria-label="{{ __('Отмена') }}">&times;</button>
                    </div>

                    <form class="chat-form" action="{{ route('public.chats.send', $conversation) }}" method="post"
                          enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="reply_to_id" id="replyToId" value="">
                        <label class="chat-attach" title="{{ __('Прикрепить файл') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                            <input type="file" name="attachment" id="chatFile" style="display:none;"
                                   accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip">
                        </label>

                        <textarea name="body" maxlength="2000"
                                  placeholder="{{ __('Напишите сообщение или приложите файл…') }}"></textarea>
                        <button type="submit">{{ __('Отправить') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <script>
        // подпись рисует скрипт, поэтому переводим её здесь, а не в разметке
        const READ_LABEL = @json(__('Прочитано'));

        (function () {
            const feed = document.getElementById('chat-feed');
            if (!feed) {
                return;
            }

            const scrollDown = () => feed.scrollTop = feed.scrollHeight;
            scrollDown();

            // Ctrl/Cmd + Enter отправляет сообщение
            const form = document.querySelector('.chat-form');
            const field = form?.querySelector('textarea');
            form?.addEventListener('submit', function () {
                setTimeout(function () {
                    cancelReply();
                    if (fileInput) { fileInput.value = ''; }
                    fileBar?.classList.remove('on');
                }, 0);
            });

            field?.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    form.submit();
                }
            });

            // подтягиваем новые сообщения раз в 5 секунд
            let lastId = parseInt(feed.dataset.lastId || '0', 10);
            // время берём у сервера: при сбитых часах браузера первая пачка
            // отметок «прочитано» и правок терялась
            let sinceTs = parseInt(feed.dataset.serverNow || '0', 10) || null;
            const pollUrl = feed.dataset.pollUrl;

            const csrf = document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('input[name="_token"]')?.value;

            function appendMessage(message) {
                if (feed.querySelector('[data-id="' + message.id + '"]')) {
                    return;
                }

                const wrap = document.createElement('div');
                wrap.className = 'msg' + (message.mine ? ' mine' : '');
                wrap.dataset.id = message.id;

                const bubble = document.createElement('div');
                bubble.className = 'msg-bubble';

                if (message.call) {
                    const card = document.createElement('div');
                    card.className = 'msg-call' + (message.call.missed ? ' missed' : '');
                    card.innerHTML = '<span class="ico">' + (message.call.missed ? '📵' : '🎥') + '</span>'
                        + '<span class="t"><b></b><span></span></span>';
                    card.querySelector('b').textContent = message.call.title;
                    card.querySelector('.t span').textContent = message.call.note;
                    if (message.call.open && !message.call.missed) {
                        const join = document.createElement('a');
                        join.className = 'join';
                        join.href = message.call.url;
                        join.textContent = 'Присоединиться';
                        card.appendChild(join);
                    }
                    bubble.appendChild(card);
                }

                if (message.file) {
                    if (message.file.image) {
                        const link = document.createElement('a');
                        link.href = message.file.url;
                        link.target = '_blank';
                        link.rel = 'noopener';
                        const img = document.createElement('img');
                        img.className = 'msg-img';
                        img.src = message.file.url;
                        img.alt = message.file.name;
                        link.appendChild(img);
                        bubble.appendChild(link);
                    } else {
                        const link = document.createElement('a');
                        link.className = 'msg-file';
                        link.href = message.file.url;
                        link.target = '_blank';
                        link.rel = 'noopener';
                        link.download = '';
                        link.innerHTML = '<span class="ico">📄</span>';
                        const info = document.createElement('span');
                        info.className = 'txt';
                        info.innerHTML = '<span class="nm"></span><span class="sz"></span>';
                        info.querySelector('.nm').textContent = message.file.name;
                        info.querySelector('.sz').textContent = message.file.size;
                        link.appendChild(info);
                        bubble.appendChild(link);
                    }
                }

                if (message.reply) {
                    const quote = document.createElement('button');
                    quote.type = 'button';
                    quote.className = 'msg-quote';
                    quote.dataset.target = message.reply.id;
                    const who = document.createElement('b');
                    who.textContent = message.reply.author;
                    quote.appendChild(who);
                    quote.append(message.reply.body);
                    bubble.appendChild(quote);
                }

                if (message.body) {
                    const text = document.createElement('span');
                    text.className = 'msg-text';
                    text.textContent = message.body;
                    bubble.appendChild(text);
                }

                const meta = document.createElement('div');
                meta.className = 'msg-meta';

                const info = document.createElement('span');
                info.textContent = (message.mine ? 'Вы' : message.author) + ' · ' + message.time.slice(-5);

                if (message.mine) {
                    const check = document.createElement('span');
                    check.className = 'msg-check' + (message.read ? ' read' : '');
                    check.innerHTML = message.read ? '✓✓<span class="lbl">' + READ_LABEL + '</span>' : '✓';
                    info.append(' · ', check);
                }

                meta.appendChild(info);

                if (message.mine && message.deleteUrl && csrf) {
                    const form = document.createElement('form');
                    form.method = 'post';
                    form.action = message.deleteUrl;
                    form.onsubmit = () => confirm('Удалить сообщение?');
                    form.innerHTML = '<input type="hidden" name="_token" value="' + csrf + '">'
                        + '<input type="hidden" name="_method" value="DELETE">'
                        + '<button type="submit" class="msg-del">Удалить</button>';
                    meta.appendChild(form);
                }

                wrap.appendChild(bubble);
                wrap.appendChild(meta);
                feed.appendChild(wrap);
            }

            const fileInput = document.getElementById('chatFile');
            const fileBar = document.getElementById('fileBar');
            const fileName = document.getElementById('fileName');
            const fileCancel = document.getElementById('fileCancel');

            fileInput?.addEventListener('change', function () {
                const file = fileInput.files && fileInput.files[0];
                if (!file) { return; }
                fileName.textContent = file.name + ' · ' + Math.max(1, Math.round(file.size / 1024)) + ' КБ';
                fileBar.classList.add('on');
            });

            fileCancel?.addEventListener('click', function () {
                fileInput.value = '';
                fileBar.classList.remove('on');
            });

            const replyBar = document.getElementById('replyBar');
            const replyPreview = document.getElementById('replyPreview');
            const replyToId = document.getElementById('replyToId');
            const replyCancel = document.getElementById('replyCancel');

            function startReply(id, author, body) {
                if (!replyBar) { return; }
                replyToId.value = id;
                replyPreview.textContent = author + ': ' + body;
                replyBar.classList.add('on');
                document.querySelector('.chat-form textarea')?.focus();
            }

            function cancelReply() {
                if (!replyBar) { return; }
                replyToId.value = '';
                replyBar.classList.remove('on');
            }

            replyCancel?.addEventListener('click', cancelReply);

            // подсветка оригинала при клике на цитату
            function jumpTo(id) {
                const target = feed.querySelector('[data-id="' + id + '"]');
                if (!target) { return; }
                target.scrollIntoView({behavior: 'smooth', block: 'center'});
                target.classList.add('flash');
                setTimeout(() => target.classList.remove('flash'), 1400);
            }

            // включение и выключение режима правки
            feed.addEventListener('click', function (e) {
                const replyBtn = e.target.closest('.msg-reply');
                if (replyBtn) {
                    startReply(replyBtn.dataset.id, replyBtn.dataset.author, replyBtn.dataset.body);
                    return;
                }

                const quote = e.target.closest('.msg-quote');
                if (quote) {
                    jumpTo(quote.dataset.target);
                    return;
                }

                const editBtn = e.target.closest('.msg-edit');
                if (editBtn) {
                    const msg = editBtn.closest('.msg');
                    msg.classList.add('editing');
                    const field = msg.querySelector('.msg-edit-form textarea');
                    if (field) { field.focus(); field.selectionStart = field.value.length; }
                    return;
                }

                const cancelBtn = e.target.closest('.msg-edit-actions .cancel');
                if (cancelBtn) {
                    cancelBtn.closest('.msg').classList.remove('editing');
                }
            });

            function applyEdits(items) {
                (items || []).forEach(function (item) {
                    const el = feed.querySelector('[data-id="' + item.id + '"]');
                    if (!el) { return; }

                    // меняем только текст, вложения и цитату не трогаем
                    const bubble = el.querySelector('.msg-bubble');
                    if (bubble) {
                        let text = bubble.querySelector('.msg-text');
                        if (!text) {
                            text = document.createElement('span');
                            text.className = 'msg-text';
                            bubble.appendChild(text);
                        }
                        text.textContent = item.body;
                    }

                    const field = el.querySelector('.msg-edit-form textarea');
                    if (field && !el.classList.contains('editing')) { field.value = item.body; }

                    const info = el.querySelector('.msg-meta span');
                    if (info && !info.querySelector('.msg-mark')) {
                        const mark = document.createElement('span');
                        mark.className = 'msg-mark';
                        mark.textContent = 'изменено';
                        info.append(' · ', mark);
                    }
                });
            }

            function applyRead(ids) {
                (ids || []).forEach(function (id) {
                    const el = feed.querySelector('[data-id="' + id + '"]');
                    const check = el?.querySelector('.msg-check');
                    if (!check || check.classList.contains('read')) { return; }

                    check.classList.add('read');
                    check.innerHTML = '✓✓<span class="lbl">' + READ_LABEL + '</span>';
                    check.title = 'Прочитано';
                });
            }

            function removeDeleted(ids) {
                (ids || []).forEach(function (id) {
                    const el = feed.querySelector('[data-id="' + id + '"]');
                    if (el) { el.remove(); }
                });
            }

            setInterval(function () {
                fetch(pollUrl + '?after=' + lastId + (sinceTs ? '&since=' + sinceTs : ''), {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                    .then(r => r.ok ? r.json() : null)
                    .then(function (data) {
                        if (!data) {
                            return;
                        }

                        if (data.now) { sinceTs = data.now; }
                        applyEdits(data.edited);
                        applyRead(data.read);
                        removeDeleted(data.deleted);

                        if (!data.messages.length) {
                            return;
                        }

                        document.getElementById('chat-placeholder')?.remove();
                        data.messages.forEach(function (message) {
                            appendMessage(message);
                            lastId = Math.max(lastId, message.id);
                        });
                        scrollDown();
                    })
                    .catch(() => {});
            }, 5000);
        })();
    </script>

@endsection
