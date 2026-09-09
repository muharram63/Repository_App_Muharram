{{-- Уведомления владельцу объекта о мерах модератора. Кто пожаловался — не показываем. --}}
@auth
    @php
        $moderationNotices = \App\Models\ModerationNotice::where('user_id', auth()->id())
            ->whereNull('seen_at')
            ->latest('id')
            ->take(5)
            ->get();
    @endphp

    @if($moderationNotices->isNotEmpty())
        <style>
            .mn-wrap{
                position:fixed; top:18px; right:18px; z-index:9998;
                width:min(420px, calc(100vw - 36px));
                display:flex; flex-direction:column; gap:10px;
            }
            .mn-card{
                background:#fff; border:1px solid #FECACA; border-left:4px solid #DC2626;
                border-radius:14px; padding:14px 16px;
                box-shadow:0 18px 40px -18px rgba(15,23,42,.35);
                font-family:'Inter', -apple-system, Segoe UI, sans-serif;
            }
            .mn-card b{display:block; font-size:14px; color:#b91c1c; margin-bottom:4px;}
            .mn-card .sub{font-size:13px; color:#0f172a; font-weight:600;}
            .mn-card .row{font-size:12.5px; color:#64748b; margin-top:6px; line-height:1.5;}
            .mn-card .hint{font-size:12.5px; color:#334155; margin-top:8px;}
            .mn-actions{display:flex; justify-content:flex-end; margin-top:10px;}
            .mn-actions button{
                border:none; border-radius:9px; cursor:pointer; padding:7px 14px;
                background:#1656D6; color:#fff; font-family:inherit; font-size:12.5px; font-weight:700;
            }
            .mn-actions button:hover{background:#1247b0;}
        </style>

        <div class="mn-wrap">
            @foreach($moderationNotices as $notice)
                <div class="mn-card">
                    <b>{{ __($notice->title()) }}</b>
                    <span class="sub">{{ $notice->subject }}</span>

                    @if($notice->reason)
                        <div class="row">{{ __('Причина жалобы') }}: {{ __($notice->reason) }}</div>
                    @endif

                    @if($notice->comment)
                        <div class="row">{{ $notice->comment }}</div>
                    @endif

                    <div class="hint">{{ __($notice->hint()) }}</div>
                </div>
            @endforeach

            <div class="mn-card" style="border-left-color:#1656D6; border-color:#BFDBFE;">
                <div class="mn-actions" style="margin-top:0;">
                    <form action="{{ route('public.notices.seen') }}" method="post">
                        @csrf
                        <button type="submit">{{ __('Понятно') }}</button>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endauth
