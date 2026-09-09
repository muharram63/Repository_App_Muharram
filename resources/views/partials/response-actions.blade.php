{{--
    Кнопки «Принять» / «Отклонить» для владельца вакансии или резюме.
    Использование:
        @include('partials.response-actions', [
            'response' => $response,
            'route' => route('public.vacancy_responses.status', $response),
        ])
--}}
<div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:12px;">
    @if($response->status !== 'accepted')
        <form action="{{ $route }}" method="post">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="accepted">
            <button type="submit"
                    style="font-family:inherit; font-size:13px; font-weight:600; cursor:pointer;
                           padding:7px 16px; border-radius:10px; border:1px solid #BBF7D0;
                           background:#F0FDF4; color:#15803D;">
                {{ __('Принять') }}
            </button>
        </form>
    @endif

    @if($response->status !== 'rejected')
        <form action="{{ $route }}" method="post">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="rejected">
            <button type="submit"
                    style="font-family:inherit; font-size:13px; font-weight:600; cursor:pointer;
                           padding:7px 16px; border-radius:10px; border:1px solid #FECACA;
                           background:#FEF2F2; color:#B91C1C;">
                {{ __('Отклонить') }}
            </button>
        </form>
    @endif

    @if($response->status === 'new')
        <form action="{{ $route }}" method="post">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="viewed">
            <button type="submit"
                    style="font-family:inherit; font-size:13px; font-weight:600; cursor:pointer;
                           padding:7px 16px; border-radius:10px; border:1px solid #E5E7EB;
                           background:#fff; color:#6B7280;">
                {{ __('Отметить просмотренным') }}
            </button>
        </form>
    @endif
</div>
