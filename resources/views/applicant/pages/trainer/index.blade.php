<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Тренажёр собеседований') }}</title>
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
            @if(session('status'))<div class="tr-note tr-good">{{ session('status') }}</div>@endif
            @if(session('error'))<div class="tr-note tr-bad">{{ session('error') }}</div>@endif
            @if($freeNow)
                <div class="tr-note tr-good">🎁 {{ __('Первая тренировка — бесплатно. Попытка уже начислена.') }}</div>
            @endif
            @if($freeNeedsPhone)
                {{-- бесплатная попытка привязана к телефону: без него её не выдаём --}}
                <div class="tr-note tr-warn">
                    🎁 {{ __('Первая тренировка — бесплатно. Чтобы её получить, укажите телефон в анкете.') }}
                    <a href="{{ $applicant ? route('applicant.dashboard') : route('public.applicants.create') }}">{{ $applicant ? __('Открыть анкету') : __('Заполнить анкету') }}</a>
                </div>
            @endif

            {{-- ==================== заголовок и попытки ==================== --}}
            <div class="tr-head">
                <div>
                    <h1>{{ __('Тренажёр собеседований') }}</h1>
                    <p class="tr-lead">
                        {{ __('Пройдите собеседование с Анной как настоящее: голосом, с тестовым заданием и проверкой кода. Результат видите только вы — работодателям он не показывается. После тренировки — подробный разбор: что получилось, где ошибки и как правильно.') }}
                    </p>
                </div>
                <div class="tr-balance">
                    <b id="trBalance">{{ $balance }}</b>
                    <span>{{ __('попыток') }}</span>
                </div>
            </div>

            {{-- ==================== начать тренировку ==================== --}}
            @if(Route::has('applicant.trainer.start'))
                <div class="tr-card" id="start">
                    <h2>{{ __('Новая тренировка') }}</h2>

                    @if($tracks->isEmpty())
                        <p class="tr-muted">{{ __('Направления для тренировки пока не настроены. Загляните позже.') }}</p>
                    @elseif($balance < 1)
                        <p class="tr-muted">{{ __('Попытки закончились — выберите пакет ниже.') }}</p>
                    @elseif($dailyLeft === 0)
                        <p class="tr-muted">{{ __('На сегодня тренировок достаточно. Возвращайтесь завтра.') }}</p>
                    @else
                        <form method="POST" action="{{ route('applicant.trainer.start') }}" class="tr-form">
                            @csrf
                            <label>
                                <span>{{ __('Направление') }}</span>
                                <select name="track_id" required>
                                    @foreach($tracks as $track)
                                        <option value="{{ $track->id }}" @selected(old('track_id') == $track->id)>{{ __($track->title) }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>{{ __('Уровень') }}</span>
                                <select name="level" required>
                                    @foreach($levels as $key => $level)
                                        <option value="{{ $key }}" @selected(old('level', 'junior') === $key)>{{ $level['label'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="tr-check">
                                <input type="checkbox" name="consent" value="1" required>
                                <span>{{ __('Я согласен(на), что ИИ проведёт со мной тренировочное собеседование: мои ответы обработает модель, чтобы оценить их и подготовить разбор. Результат увижу только я.') }}</span>
                            </label>
                            <div class="tr-row">
                                <button type="submit" class="tr-btn">▶ {{ __('Начать тренировку') }}</button>
                                <span class="tr-muted">
                                    {{ __('Спишется 1 попытка.') }}
                                    @if($dailyLeft !== null) {{ __('Сегодня можно ещё: :n.', ['n' => $dailyLeft]) }} @endif
                                </span>
                            </div>
                        </form>
                    @endif
                </div>
            @endif

            {{-- ==================== история ==================== --}}
            @if($sessions->isNotEmpty())
                <div class="tr-card">
                    <h2>{{ __('Мои тренировки') }}</h2>
                    @foreach($sessions as $session)
                        <div class="tr-item">
                            <div>
                                <b>{{ __($session->track_title) }} · {{ $session->levelLabel() }}</b>
                                <div class="tr-muted">{{ $session->created_at->format('d.m.Y H:i') }} · {{ $session->statusLabel() }}</div>
                            </div>
                            <div class="tr-row">
                                @if($session->score !== null)
                                    <span class="tr-score {{ $session->score >= 70 ? 'ok' : ($session->score >= 40 ? 'mid' : 'low') }}">{{ $session->score }}/100</span>
                                @endif
                                @if(Route::has('applicant.trainer.show'))
                                    <a class="tr-btn tr-ghost" href="{{ route('applicant.trainer.show', $session) }}">
                                        {{ $session->isFinished() ? __('Разбор') : __('Открыть') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- ==================== пакеты попыток ==================== --}}
            @if($packages->isNotEmpty())
                <div class="tr-card">
                    <h2>{{ __('Пакеты попыток') }}</h2>
                    <div class="tr-packs">
                        @foreach($packages as $package)
                            <form method="POST" action="{{ route('applicant.trainer.buy', $package) }}" class="tr-pack">
                                @csrf
                                <b>{{ __($package->title) }}</b>
                                <span class="tr-muted">{{ trans_choice(':count попытка|:count попытки|:count попыток', $package->attempts, ['count' => $package->attempts]) }}</span>
                                <span class="tr-price">{{ number_format($package->price, 0, ',', ' ') }} {{ __('сомони') }}</span>
                                <button type="submit" class="tr-btn">{{ __('Выбрать') }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ==================== мои оплаты ==================== --}}
            @if($payments->isNotEmpty())
                <div class="tr-card">
                    <h2>{{ __('Мои оплаты') }}</h2>
                    @foreach($payments as $payment)
                        <div class="tr-item">
                            <div>
                                <b>{{ __($payment->package_title) }} · {{ number_format($payment->amount, 0, ',', ' ') }} {{ __('сомони') }}</b>
                                <div class="tr-muted">{{ $payment->reference() }} · {{ $payment->created_at->format('d.m.Y H:i') }}</div>
                            </div>
                            <div class="tr-row">
                                <span class="tr-tag tr-{{ $payment->status }}">{{ $payment->statusLabel() }}</span>
                                {{-- истёкшую тоже можно открыть: вдруг перевод уже был --}}
                                @if($payment->isOpen() || $payment->status === \App\Models\Trainer\TrainerPayment::EXPIRED)
                                    <a class="tr-btn tr-ghost" href="{{ route('applicant.trainer.payment', $payment) }}">{{ __('Реквизиты') }}</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </main>
</div>
@include('applicant.partials.js')
@include('applicant.partials.script')
</body>
</html>
