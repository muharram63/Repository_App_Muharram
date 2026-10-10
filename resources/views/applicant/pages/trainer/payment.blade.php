<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Оплата пакета попыток') }}</title>
    @include('applicant.partials.css')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    @include('applicant.pages.trainer._style')
</head>
<body>
<div class="app">
    @include('applicant.partials.sidebar')

    <main class="main">
        <div class="tr-wrap">
            <a href="{{ route('applicant.trainer.index') }}" class="tr-muted" style="text-decoration:none">← {{ __('К тренажёру') }}</a>

            @if(session('status'))<div class="tr-note tr-good">{{ session('status') }}</div>@endif

            <div class="tr-card">
                <div class="tr-row" style="justify-content:space-between">
                    <h2>{{ __('Оплата: :title', ['title' => __($payment->package_title)]) }}</h2>
                    <span class="tr-tag tr-{{ $payment->status }}">{{ $payment->statusLabel() }}</span>
                </div>

                <dl class="tr-req">
                    <dt>{{ __('Сумма') }}</dt>
                    <dd>{{ number_format($pay['amount'], 0, ',', ' ') }} {{ __('сомони') }}</dd>
                    <dt>{{ __('Попыток') }}</dt>
                    <dd>{{ $payment->attempts }}</dd>
                    <dt>{{ __('Кошелёк или карта') }}</dt>
                    <dd>{{ $pay['wallet'] ?: __('реквизиты ещё не указаны — напишите в поддержку') }}</dd>
                    @if($pay['recipient'])
                        <dt>{{ __('Получатель') }}</dt>
                        <dd>{{ $pay['recipient'] }}</dd>
                    @endif
                    <dt>{{ __('Назначение платежа') }}</dt>
                    <dd>{{ $pay['reference'] }}</dd>
                </dl>

                @if($pay['text'])
                    <div class="tr-note tr-warn" style="white-space:pre-line">{{ $pay['text'] }}</div>
                @endif

                @if($payment->status === \App\Models\Trainer\TrainerPayment::PENDING)
                    <p class="tr-muted">
                        {{ __('Переведите сумму по реквизитам и укажите в назначении код :ref. Затем нажмите «Я оплатил» — мы проверим перевод и начислим попытки.', ['ref' => $pay['reference']]) }}
                    </p>
                    <form method="POST" action="{{ route('applicant.trainer.claim', $payment) }}" class="tr-form">
                        @csrf
                        <label style="grid-column:1/-1">
                            <span>{{ __('С какого номера или карты вы платили (необязательно)') }}</span>
                            <input type="text" name="payer_note" maxlength="255" placeholder="{{ __('Например: +992 93 *** 45 67') }}">
                        </label>
                        <div class="tr-row">
                            <button type="submit" class="tr-btn">✓ {{ __('Я оплатил') }}</button>
                        </div>
                    </form>
                @elseif($payment->status === \App\Models\Trainer\TrainerPayment::CLAIMED)
                    <div class="tr-note tr-warn">{{ __('Проверяем перевод. Как только подтвердим, попытки начислятся и придёт уведомление.') }}</div>
                @elseif($payment->status === \App\Models\Trainer\TrainerPayment::CONFIRMED)
                    <div class="tr-note tr-good">{{ __('Оплата подтверждена, попытки начислены.') }}</div>
                @else
                    <div class="tr-note tr-bad">{{ __('Оплата не подтверждена:') }} {{ $payment->reject_reason }}</div>
                @endif
            </div>
        </div>
    </main>
</div>
@include('applicant.partials.js')
@include('applicant.partials.script')
</body>
</html>
