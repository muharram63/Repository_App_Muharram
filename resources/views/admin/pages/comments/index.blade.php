@extends('admin.layouts.app')

@section('main')
    <style>
        .ac-wrap{padding:22px 24px 40px;}
        .ac-kpi{display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:18px;}
        .ac-kpi .kpi{padding:18px;}

        .ac-tools{display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:16px;}
        .ac-tools form{display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin:0;}
        .ac-tools select, .ac-tools input[type=text]{
            border:1px solid var(--line); border-radius:9px; padding:8px 12px;
            font-family:inherit; font-size:13px; background:var(--surface); color:var(--ink-700);
        }
        .ac-tools input[type=text]{min-width:230px;}
        .ac-tools button{
            border:none; background:var(--blue); color:#fff; border-radius:9px;
            padding:9px 16px; font-size:13px; font-weight:700; cursor:pointer;
        }
        .ac-tools a.reset{
            border:1px solid var(--line); border-radius:9px; padding:8px 14px;
            font-size:13px; color:var(--ink-600); text-decoration:none;
        }

        .ac-item{padding:18px; border-bottom:1px solid var(--line);}
        .ac-item:last-child{border-bottom:none;}
        .ac-head{display:flex; align-items:flex-start; justify-content:space-between; gap:14px; flex-wrap:wrap;}
        .ac-author{font-weight:700; color:var(--ink-900); font-size:14.5px;}
        .ac-author a{color:inherit; text-decoration:none;}
        .ac-author a:hover{text-decoration:underline;}
        .ac-sub{font-size:12.5px; color:var(--ink-400); margin-top:3px;}
        .ac-tags{display:flex; gap:8px; align-items:center; flex-wrap:wrap;}
        .ac-topic{
            font-size:11.5px; font-weight:700; padding:5px 10px; border-radius:999px;
            background:var(--blue-50); color:var(--blue); white-space:nowrap;
        }
        .ac-body{
            margin-top:12px; padding:13px 15px; border-radius:10px;
            background:var(--bg); color:var(--ink-700);
            font-size:13.5px; line-height:1.65; white-space:pre-line;
        }
        .ac-reply{
            margin-top:10px; padding:13px 15px; border-radius:10px;
            background:var(--green-50); color:var(--green);
            font-size:13.5px; line-height:1.65; white-space:pre-line;
        }
        .ac-forms{display:flex; flex-wrap:wrap; gap:10px; align-items:flex-start; margin-top:14px;}
        .ac-forms form{display:flex; gap:8px; align-items:center; margin:0; flex-wrap:wrap;}
        .ac-forms textarea{
            border:1px solid var(--line); border-radius:9px; padding:10px 12px;
            font-family:inherit; font-size:13px; min-width:340px; min-height:44px;
            resize:vertical; color:var(--ink-700);
        }
        .ac-forms select{
            border:1px solid var(--line); border-radius:9px; padding:9px 12px;
            font-family:inherit; font-size:13px; background:var(--surface); color:var(--ink-700);
        }
        .ac-forms button{
            border:none; border-radius:9px; padding:10px 16px;
            font-family:inherit; font-size:13px; font-weight:700; cursor:pointer;
            background:var(--blue); color:#fff;
        }
        .ac-forms button.ghost{background:var(--surface); color:var(--ink-600); border:1px solid var(--line);}
        .ac-forms button.danger{background:var(--red); color:#fff;}
        .ac-empty{padding:56px 18px; text-align:center; color:var(--ink-400);}
        .ac-note{
            margin-bottom:16px; padding:12px 15px; border-radius:10px;
            background:var(--blue-50); color:var(--blue); font-size:13px; font-weight:600;
        }
        .ac-flash{
            margin-bottom:16px; padding:12px 15px; border-radius:10px;
            background:var(--green-50); color:var(--green); font-size:13px; font-weight:600;
        }
    </style>

    @php
        $roleLabels = ['applicant' => 'Соискатель', 'employer' => 'Работодатель', 'admin' => 'Администратор'];
    @endphp

    <main class="ac-wrap">
        <div class="section-header" style="margin-bottom:18px;">
            <div>
                <h1 class="disp">Комментарии</h1>
                <div class="sub">Обращения пользователей к администрации</div>
            </div>
        </div>

        @if(session('status'))
            <div class="ac-flash">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="ac-note" style="background:var(--red-50); color:var(--red);">
                @foreach($errors->all() as $message)
                    <div>{{ $message }}</div>
                @endforeach
            </div>
        @endif

        <div class="ac-note">
            Сюда попадают только обращения, отправленные через форму «Написать администратору».
            Сообщения из чатов и тексты жалоб в этот раздел не приходят.
        </div>

        <div class="ac-kpi">
            <div class="card kpi">
                <div class="kpi-label">Новые</div>
                <div class="kpi-value" data-live="comments-new">{{ $newCount }}</div>
            </div>
            <div class="card kpi">
                <div class="kpi-label">В работе</div>
                <div class="kpi-value" data-live="comments-in_review">{{ $inReviewCount }}</div>
            </div>
            <div class="card kpi">
                <div class="kpi-label">Отвечено</div>
                <div class="kpi-value" data-live="comments-answered">{{ $answeredCount }}</div>
            </div>
            <div class="card kpi">
                <div class="kpi-label">Закрыто</div>
                <div class="kpi-value" data-live="comments-closed">{{ $closedCount }}</div>
            </div>
        </div>

        <div class="ac-tools">
            <form action="{{ route('superadmin.comments') }}" method="get">
                <select name="status">
                    <option value="">Все статусы</option>
                    @foreach(\App\Models\AdminComment::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected($filterStatus === $key)>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="topic">
                    <option value="">Все темы</option>
                    @foreach(\App\Models\AdminComment::TOPICS as $key => $label)
                        <option value="{{ $key }}" @selected($filterTopic === $key)>{{ $label }}</option>
                    @endforeach
                </select>

                <input type="text" name="q" value="{{ $search }}" placeholder="Поиск по тексту, имени или email">
                <button type="submit">Найти</button>
            </form>

            @if($filterStatus !== 'all' || $filterTopic !== 'all' || $search !== '')
                <a class="reset" href="{{ route('superadmin.comments') }}">Сбросить</a>
            @endif
        </div>

        <div class="card">
            <div class="card-header">Обращения — {{ $comments->total() }}</div>

            @forelse($comments as $comment)
                <div class="ac-item">
                    <div class="ac-head">
                        <div style="min-width:0;">
                            <div class="ac-author">
                                @if($comment->user)
                                    <a href="{{ route('superadmin.users.edit', $comment->user) }}">{{ $comment->user->name }}</a>
                                @else
                                    Пользователь удалён
                                @endif
                            </div>
                            <div class="ac-sub">
                                {{ $comment->user?->email ?? '—' }} ·
                                {{ $roleLabels[$comment->user?->role] ?? '—' }} ·
                                {{ $comment->created_at->format('d.m.Y H:i') }}
                                @if($comment->answered_at)
                                    · ответ {{ $comment->answered_at->format('d.m.Y H:i') }}
                                    @if($comment->admin) от {{ $comment->admin->name }} @endif
                                @endif
                            </div>
                        </div>

                        <div class="ac-tags">
                            <span class="ac-topic">{{ $comment->topicLabel() }}</span>
                            <span class="badge {{ $comment->status === 'answered' ? 'resolved' : ($comment->status === 'new' ? 'open' : 'pending') }}">
                                {{ $comment->statusLabel() }}
                            </span>
                        </div>
                    </div>

                    <div class="ac-body">{{ $comment->body }}</div>

                    @if($comment->admin_reply)
                        <div class="ac-reply">Ответ: {{ $comment->admin_reply }}</div>
                    @endif

                    <div class="ac-forms">
                        {{-- текст пользователя админ не редактирует, только отвечает --}}
                        <form action="{{ route('superadmin.comments.reply', $comment) }}" method="post">
                            @csrf
                            <textarea name="reply" maxlength="2000" required
                                      placeholder="Ответ пользователю">{{ $comment->admin_reply }}</textarea>
                            <button type="submit">{{ $comment->admin_reply ? 'Обновить ответ' : 'Ответить' }}</button>
                        </form>

                        {{-- «Отвечено» вручную не выставляется: этот статус ставит сам ответ --}}
                        <form action="{{ route('superadmin.comments.status', $comment) }}" method="post">
                            @csrf @method('PATCH')
                            <select name="status">
                                @if($comment->isAnswered())
                                    <option value="answered" selected disabled>Отвечено</option>
                                @endif
                                @foreach(['new' => 'Новое', 'in_review' => 'В работе', 'closed' => 'Закрыто'] as $key => $label)
                                    <option value="{{ $key }}" @selected(! $comment->isAnswered() && $comment->status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="ghost">Сохранить статус</button>
                        </form>

                        <form action="{{ route('superadmin.comments.destroy', $comment) }}" method="post"
                              onsubmit="return confirm('Удалить обращение?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="danger">Удалить</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="ac-empty">
                    <div style="font-size:15px; font-weight:600; color:var(--ink-700); margin-bottom:6px;">
                        Обращений нет
                    </div>
                    Пользователи пока ничего не писали администрации
                </div>
            @endforelse
        </div>

        {{ $comments->links() }}
    </main>
@endsection
