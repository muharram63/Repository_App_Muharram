@extends('public.layouts.app')
@section('content')

    <style>
        .chat-list{
            background:var(--surface); border:1px solid var(--border);
            border-radius:18px; overflow:hidden;
        }
        .chat-row{
            display:flex; align-items:center; gap:14px;
            padding:18px 22px; border-bottom:1px solid var(--border);
            transition:background .15s ease;
        }
        .chat-row:last-child{border-bottom:none;}
        .chat-row:hover{background:var(--surface-alt);}
        .chat-ava, .chat-ava-img{
            width:46px; height:46px; border-radius:50%; flex-shrink:0; object-fit:cover;
        }
        .chat-ava{
            background:var(--accent-soft); color:var(--accent-ink);
            display:flex; align-items:center; justify-content:center; font-weight:800; font-size:17px;
        }
        :root[data-theme="dark"] .chat-ava{color:var(--accent);}
        .chat-body{flex:1; min-width:0;}
        .chat-name{font-weight:700; font-size:15.5px;}
        .chat-last{
            font-size:13.5px; color:var(--text-muted); margin-top:3px;
            overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
        }
        .chat-meta{text-align:right; flex-shrink:0; display:flex; flex-direction:column; align-items:flex-end; gap:6px;}
        .chat-time{font-size:12px; color:var(--text-muted); white-space:nowrap;}
        .chat-check{font-weight:700; letter-spacing:-2px; color:var(--text-muted); margin-right:3px;}
        .chat-check.read{color:var(--accent);}
        .chat-unread{
            min-width:22px; height:22px; padding:0 7px; border-radius:999px;
            background:var(--accent); color:#fff; font-size:11.5px; font-weight:800;
            display:inline-flex; align-items:center; justify-content:center;
        }
        .chat-empty{
            text-align:center; padding:56px 24px;
            background:var(--surface); border:1px dashed var(--border); border-radius:18px;
        }
        .chat-empty .e{font-size:28px; margin-bottom:10px;}
        .chat-empty p{color:var(--text-muted); font-size:14.5px; line-height:1.6; max-width:440px; margin:0 auto;}
    </style>

    <section class="section">
        <div class="container">

            <div class="section-head">
                <div class="eyebrow">{{ __('Сообщения') }}</div>
                <h2>{{ __('Чат') }}</h2>
                <p>{{ __('Переписка между работодателем и соискателем — по одному диалогу на каждого собеседника.') }}</p>
            </div>

            @if(session('status') || session('error'))
                <div style="margin-bottom:20px; padding:14px 18px; border-radius:12px; font-weight:600;
                            background:{{ session('error') ? '#FEF2F2' : '#ECFDF5' }};
                            color:{{ session('error') ? '#B91C1C' : '#15803D' }};">
                    {{ session('error') ?: session('status') }}
                </div>
            @endif

            @if($conversations->isEmpty())
                <div class="chat-empty">
                    <div class="e">💬</div>
                    <p>
                        @if($isEmployer)
                            Диалогов пока нет. Откройте резюме кандидата или отклик на вакансию — там есть кнопка «Написать».
                        @else
                            Диалогов пока нет. Напишите компании со страницы вакансии — кнопка «Написать работодателю».
                        @endif
                    </p>
                </div>
            @else
                <div class="chat-list">
                    @foreach($conversations as $conversation)
                        @php
                            $counterName = $isEmployer
                                ? ($conversation->applicant?->user?->name ?? 'Соискатель удалён')
                                : ($conversation->employer?->company_name ?? 'Компания удалена');
                            $counterAvatar = $isEmployer
                                ? $conversation->applicant?->user?->avatar
                                : $conversation->employer?->user?->avatar;
                            $unread = $conversation->messages()
                                ->where('user_id', '!=', $user->id)
                                ->whereNull('read_at')
                                ->count();
                        @endphp
                        <a class="chat-row" href="{{ route('public.chats.show', $conversation) }}">
                            @if($counterAvatar)
                                <img class="chat-ava-img" src="{{ asset($counterAvatar) }}" alt="">
                            @else
                                <div class="chat-ava">{{ $isEmployer ? ($conversation->applicant?->user?->initials(1) ?? '?') : ($conversation->employer?->initials(2) ?? '?') }}</div>
                            @endif

                            <div class="chat-body">
                                <div class="chat-name">{{ $counterName }}</div>
                                <div class="chat-last">
                                    @if($conversation->lastMessage)
                                        @if($conversation->lastMessage->user_id === $user->id)
                                            <span class="chat-check {{ $conversation->lastMessage->read_at ? 'read' : '' }}"
                                                  title="{{ $conversation->lastMessage->read_at ? __('Прочитано') : __('Отправлено') }}">{{ $conversation->lastMessage->read_at ? '✓✓' : '✓' }}</span>
                                            {{ __('Вы') }}:
                                        @endif
                                        {{ $conversation->lastMessage->previewText() }}
                                    @else
                                        {{ __('Сообщений пока нет') }}
                                    @endif
                                </div>
                            </div>

                            <div class="chat-meta">
                                @if($conversation->last_message_at)
                                    <span class="chat-time">{{ $conversation->last_message_at->format('d.m.Y H:i') }}</span>
                                @endif
                                @if($unread > 0)
                                    <span class="chat-unread">{{ $unread }}</span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif

        </div>
    </section>

@endsection
