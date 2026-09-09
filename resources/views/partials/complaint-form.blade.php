{{--
    Кнопка «Пожаловаться» для публичных страниц.
    Использование:
        @include('partials.complaint-form', ['targetType' => 'vacancy', 'targetId' => $vacancy->id])
--}}
@auth
    @php($compact = $compact ?? false)
    <details class="complaint-box" style="margin-top:{{ $compact ? '0' : '14px' }}; flex-shrink:0;">
        <summary style="cursor:pointer; font-size:{{ $compact ? '12.5px' : '13px' }}; font-weight:600;
                       color:var(--text-muted); list-style:none; white-space:nowrap;">
            ⚠ {{ __('Пожаловаться') }}
        </summary>

        <form action="{{ route('public.complaints.store') }}" method="post"
              style="margin-top:12px; display:flex; flex-direction:column; gap:10px;">
            @csrf
            <input type="hidden" name="target_type" value="{{ $targetType }}">
            <input type="hidden" name="target_id" value="{{ $targetId }}">

            <select name="reason" required
                    style="padding:10px 12px; border:1px solid var(--border); border-radius:10px;
                           background:var(--surface); color:var(--text); font-family:inherit; font-size:13.5px;">
                @foreach(\App\Models\Complaint::REASONS as $key => $label)
                    <option value="{{ $key }}">{{ __($label) }}</option>
                @endforeach
            </select>

            <textarea name="message" rows="3" maxlength="1000"
                      placeholder="{{ __('Что именно не так? (необязательно)') }}"
                      style="padding:10px 12px; border:1px solid var(--border); border-radius:10px;
                             background:var(--surface-alt); color:var(--text); font-family:inherit;
                             font-size:13.5px; resize:vertical;"></textarea>

            <button type="submit"
                    style="padding:10px 16px; border:1px solid var(--border); border-radius:10px; cursor:pointer;
                           background:var(--surface); color:var(--text-muted); font-family:inherit;
                           font-size:13.5px; font-weight:700;">
                {{ __('Отправить жалобу') }}
            </button>
        </form>
    </details>
@endauth
