{{-- Обращения к администрации: общий блок для кабинета соискателя и работодателя --}}
<style>
    .sp-form{display:grid; gap:12px;}
    .sp-form label{font-size:13px; font-weight:700; color:var(--ink); display:block; margin-bottom:6px;}
    .sp-form select, .sp-form textarea{
        width:100%; border:1px solid var(--gray-200); border-radius:10px;
        padding:11px 13px; font-family:inherit; font-size:14px;
        background:var(--white); color:var(--ink); resize:vertical;
    }
    .sp-form select:focus, .sp-form textarea:focus{outline:none; border-color:#1656D6;}
    .sp-err{font-size:12.5px; color:#B91C1C; margin-top:6px;}
    .sp-hint{font-size:12.5px; color:var(--gray-500);}
    .sp-send{
        border:none; background:#1656D6; color:#fff; border-radius:10px;
        padding:11px 20px; cursor:pointer; font-family:inherit;
        font-size:13.5px; font-weight:700; justify-self:start;
    }
    .sp-send:hover{background:#1247b0;}

    .sp-row{padding:16px 0; border-bottom:1px solid #EEF1F6;}
    .sp-row:last-child{border-bottom:none;}
    .sp-top{display:flex; align-items:flex-start; gap:12px; justify-content:space-between;}
    .sp-topic{font-weight:700; font-size:15px;}
    .sp-meta{font-size:13px; color:var(--gray-500); margin-top:4px;}
    .sp-body{
        font-size:13.5px; line-height:1.6; margin-top:10px; white-space:pre-line;
        background:var(--gray-100); border-radius:10px; padding:11px 13px;
    }
    .sp-answer{
        font-size:13.5px; line-height:1.6; margin-top:10px; white-space:pre-line;
        background:#EFF6FF; color:#1D4ED8; border-radius:10px; padding:11px 13px;
    }
    .sp-st{font-size:12px; font-weight:700; padding:5px 11px; border-radius:999px; white-space:nowrap;}
    .sp-st.new{background:#FEF3C7; color:#B45309;}
    .sp-st.in_review{background:#DBEAFE; color:#1D4ED8;}
    .sp-st.answered{background:#DCFCE7; color:#15803D;}
    .sp-st.closed{background:var(--gray-100); color:var(--gray-500);}
    .sp-fresh{
        font-size:11.5px; font-weight:700; padding:4px 9px; border-radius:999px;
        background:#1656D6; color:#fff;
    }
    .sp-withdraw{
        border:1px solid var(--gray-200); background:var(--white); color:var(--ink);
        border-radius:9px; padding:7px 12px; cursor:pointer; margin-top:10px;
        font-family:inherit; font-size:12.5px; font-weight:600;
    }
</style>

@if($freshAnswers > 0)
    <div class="card" style="border-color:#BFDBFE; background:#EFF6FF; color:#1D4ED8; font-weight:600; margin-bottom:18px;">
        {{ __('Есть новые ответы администратора') }}: {{ $freshAnswers }}
    </div>
@endif

@if(session('error'))
    <div class="card" style="border-color:#FECACA; background:#FEF2F2; color:#B91C1C; font-weight:600; margin-bottom:18px;">
        {{ session('error') }}
    </div>
@endif

@if(session('status'))
    <div class="card" style="border-color:#BBF7D0; background:#F0FDF4; color:#15803D; font-weight:600; margin-bottom:18px;">
        {{ session('status') }}
    </div>
@endif

<div class="stat-row">
    <div class="stat-card">
        <div class="label">{{ __('Всего обращений') }}</div>
        <div class="value" data-live="support-total">{{ $comments->count() }}</div>
        <div class="trend">{{ __('За всё время') }}</div>
    </div>
    <div class="stat-card">
        <div class="label">{{ __('Отвечено') }}</div>
        <div class="value" data-live="support-answered">{{ $answered }}</div>
        <div class="trend">{{ __('Ответ администратора получен') }}</div>
    </div>
</div>

<div class="card" style="margin-bottom:18px;">
    <div class="card-header">
        <h2>{{ __('Написать администратору') }}</h2>
        <span class="hint">{{ __('Не более') }} {{ \App\Models\AdminComment::DAILY_LIMIT }} {{ __('обращений в сутки') }}</span>
    </div>

    <form action="{{ route('public.support.store') }}" method="post" class="sp-form">
        @csrf

        <div>
            <label for="sp-topic">{{ __('Тема') }}</label>
            <select name="topic" id="sp-topic" required>
                @foreach(\App\Models\AdminComment::TOPICS as $key => $label)
                    <option value="{{ $key }}" @selected(old('topic') === $key)>{{ __($label) }}</option>
                @endforeach
            </select>
            @error('topic')<div class="sp-err">{{ $message }}</div>@enderror
        </div>

        <div>
            <label for="sp-body">{{ __('Сообщение') }}</label>
            <textarea name="body" id="sp-body" rows="5" minlength="10" maxlength="2000" required
                      placeholder="{{ __('Опишите вопрос или проблему подробно') }}">{{ old('body') }}</textarea>
            @error('body')<div class="sp-err">{{ $message }}</div>@enderror
            <div class="sp-hint" style="margin-top:6px;">
                {{ __('Обращение видит только администрация. В чат собеседникам оно не попадает.') }}
            </div>
        </div>

        <button type="submit" class="sp-send">{{ __('Отправить администратору') }}</button>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2>{{ __('Мои обращения') }}</h2>
        <span class="hint">{{ $comments->count() }} {{ __('записей') }}</span>
    </div>

    @forelse($comments as $comment)
        <div class="sp-row">
            <div class="sp-top">
                <div style="min-width:0;">
                    <div class="sp-topic">{{ __($comment->topicLabel()) }}</div>
                    <div class="sp-meta">
                        {{ __('отправлено') }} {{ $comment->created_at->format('d.m.Y H:i') }}
                        @if($comment->answered_at)
                            · {{ __('ответ') }} {{ $comment->answered_at->format('d.m.Y H:i') }}
                        @endif
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:8px; flex-shrink:0; flex-wrap:wrap;">
                    @if($comment->isUnread())
                        <span class="sp-fresh">{{ __('новый ответ') }}</span>
                    @endif
                    <span class="sp-st {{ $comment->userStatusKey() }}">{{ __($comment->userStatusLabel()) }}</span>
                </div>
            </div>

            <div class="sp-body">{{ $comment->body }}</div>

            @if($comment->admin_reply)
                <div class="sp-answer">{{ __('Администратор') }}: {{ $comment->admin_reply }}</div>
            @endif

            @if($comment->canBeWithdrawn())
                <form action="{{ route('public.support.destroy', $comment) }}" method="post"
                      onsubmit="return confirm('{{ __('Отозвать обращение?') }}');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="sp-withdraw">{{ __('Отозвать') }}</button>
                </form>
            @endif
        </div>
    @empty
        <div style="text-align:center; padding:48px 16px;">
            <div style="font-size:15px; font-weight:600; margin-bottom:6px;">{{ __('Обращений пока нет') }}</div>
            <p style="color:var(--gray-500); font-size:14px;">
                {{ __('Задайте вопрос администрации через форму выше') }}
            </p>
        </div>
    @endforelse
</div>
