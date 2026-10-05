<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Мои ИИ-собеседования') }}</title>
    @include('applicant.partials.css')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@600;700;800&family=Inter:wght@400;500;600&display=swap"
          rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1 { font-family: 'Manrope', 'Inter', sans-serif; }

        .iv-wrap { padding: 0 3vh 4vh 2vh; display: flex; flex-direction: column; gap: 16px; max-width: 900px; }
        .iv-lead { margin: 0; font-size: 13px; color: var(--gray-500); }

        .iv-row {
            display: grid; grid-template-columns: minmax(0,1fr) auto; gap: 14px; align-items: center;
            background: var(--white); border: 1px solid var(--gray-200);
            border-radius: var(--radius); padding: 14px 16px;
        }
        @media (max-width: 680px) { .iv-row { grid-template-columns: 1fr; } }

        .iv-title { font-weight: 700; color: var(--ink); overflow-wrap: anywhere; }
        .iv-meta { margin-top: 4px; font-size: 13px; color: var(--gray-500); }

        .iv-bar { margin-top: 8px; height: 6px; border-radius: 999px; background: var(--gray-100); overflow: hidden; }
        .iv-bar i { display: block; height: 100%; background: var(--indigo-600); }

        .iv-badge { display: inline-flex; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .iv-pass { background: var(--wash-good); color: var(--ink-good); }
        .iv-fail { background: var(--wash-danger); color: var(--danger); }
        .iv-wait { background: var(--wash-warn); color: var(--ink-warn); }
        .iv-go   { background: var(--gray-100); color: var(--gray-500); }

        .iv-btn {
            display: inline-flex; align-items: center; padding: 8px 14px; border-radius: 999px;
            font-size: 13px; font-weight: 600; text-decoration: none;
            background: var(--indigo-600); color: #fff;
        }
        .iv-empty {
            background: var(--white); border: 1px dashed var(--gray-300);
            border-radius: var(--radius); padding: 22px; text-align: center; color: var(--gray-500);
        }
        .iv-note { font-size: 13px; padding: 10px 12px; border-radius: 10px; background: var(--wash-good); color: var(--ink-good); }
            .iv-progress { display: flex; align-items: center; gap: 10px; margin-top: 8px; }
        .iv-progress .iv-bar { flex: 1; margin-top: 0; }
        .iv-percent { font-size: 12px; font-weight: 700; color: var(--indigo-600); min-width: 38px; text-align: right; }
</style>
</head>
<body>
<div class="app">
    @include('applicant.partials.sidebar')

    <main class="main">
        <div class="iv-wrap">
            @if(session('status'))
                <div class="iv-note">{{ session('status') }}</div>
            @endif

            <div>
                <h1 style="margin:0 0 4px; font-size:20px;">{{ __('Мои ИИ-собеседования') }}</h1>
                <p class="iv-lead">
                    {{ __('Начатое собеседование можно продолжить с того места, где вы остановились: прогресс сохраняется.') }}
                </p>
                {{-- Вход на страницу проверки. Раньше на неё не вело ничего, и
                     убедиться, что звук работает, человек мог только случайно
                     угадав адрес, — а проверять лучше до собеседования, а не
                     во время него. --}}
                <p class="iv-lead" style="margin-top:6px">
                    <a href="{{ route('applicant.ai.avatar') }}" style="color:var(--indigo-600)">
                        {{ __('Проверить звук и аватар до начала') }}
                    </a>
                </p>
            </div>

            {{-- Ждущие собеседования — впереди начатых: это то, по чему
                 человеку нужно действовать, а не то, что он уже сделал. --}}
            @foreach($waiting as $vacancy)
                <div class="iv-row">
                    <div>
                        <div class="iv-title">{{ $vacancy->title }}</div>
                        <div class="iv-meta">
                            {{ $vacancy->employer?->company_name }}
                            <span class="iv-badge iv-wait" style="margin-left:6px">
                                {{ __('Ждёт вас') }}
                            </span>
                        </div>
                    </div>

                    <div>
                        <a class="iv-btn" href="{{ route('applicant.ai.start', $vacancy) }}">
                            {{ __('Пройти собеседование') }}
                        </a>
                    </div>
                </div>
            @endforeach

            @if($interviews->isEmpty() && $waiting->isEmpty())
                <div class="iv-empty">
                    {{ __('Собеседований пока нет. Они появляются, когда вы откликаетесь на вакансию, где отбор проводит ИИ.') }}
                    <div style="margin-top:12px">
                        <a class="iv-btn" href="{{ route('public.vacancies.index') }}">{{ __('Смотреть вакансии') }}</a>
                    </div>
                </div>
            @endif

            @foreach($interviews as $interview)
                    <div class="iv-row">
                        <div>
                            <div class="iv-title">{{ $interview->vacancy?->title ?? __('Вакансия удалена') }}</div>
                            <div class="iv-meta">
                                {{ $interview->vacancy?->employer?->company_name }}
                                {{-- Итог кандидат видит, только когда ответ отправлен: до этого
                                     решение ИИ смотрит работодатель. --}}
                                @if($interview->isAnnounced())
                                    @php $shown = $interview->latestDecision->effectiveOutcome(); @endphp
                                    <span class="iv-badge {{ match($shown) {
                                        'passed' => 'iv-pass',
                                        'rejected' => 'iv-fail',
                                        default => 'iv-wait',
                                    } }}" style="margin-left:6px">{{ $interview->latestDecision->outcomeLabel() }}</span>
                                @elseif($interview->candidateFinished())
                                    <span class="iv-badge iv-pass" style="margin-left:6px">
                                        {{ __('Вы прошли все шаги — ждём ответа, он придёт в уведомления') }}
                                    </span>
                                @else
                                    <span class="iv-badge iv-go" style="margin-left:6px">
                                        {{ $interview->stageLabel() }}
                                    </span>
                                @endif
                            </div>
                            {{-- процент по настоящим шагам: согласие, документы, каждый
                                 ответ в разговоре, тестовая часть, проверка, ответ --}}
                            @php $percent = $interview->progress(); @endphp
                            <div class="iv-progress">
                                <div class="iv-bar"><i style="width: {{ $percent }}%"></i></div>
                                <span class="iv-percent">{{ $percent }}%</span>
                            </div>
                        </div>

                        <div>
                            @if($interview->isAnnounced())
                                <a class="iv-btn" href="{{ route('applicant.ai.result', $interview) }}">
                                    {{ __('Смотреть результат') }}
                                </a>
                            @elseif($interview->candidateFinished())
                                {{-- всё пройдено, ответ у работодателя — идти некуда --}}
                            @else
                                <a class="iv-btn" href="{{ route('applicant.ai.interview', $interview) }}">
                                    {{ $interview->consented() ? __('Продолжить') : __('Начать') }}
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
        </div>
    </main>
</div>

@php
    // кандидат всё прошёл, а ИИ ещё не закончил: проверка решений или решение
    $working = $interviews->filter(fn ($i) => $i->candidateFinished() && ! $i->isAnnounced() && ! $i->latestDecision);
@endphp

@if($working->isNotEmpty())
<script>
    /*
     * ИИ ведёт собеседование до конца сам.
     *
     * Кандидат всё прошёл, но проверка решений и рекомендация для
     * работодателя ещё не готовы. Пока открыт этот список, страница просит
     * сервер сделать следующий шаг — по очереди для каждого собеседования.
     * Кандидат ничего не ждёт: ответ придёт в уведомления.
     */
    (function () {
        const urls = @json($working->map(fn ($i) => route('applicant.ai.pump', $i))->values());
        const token = @json(csrf_token());

        function run(url, tries) {
            if (tries <= 0) { return; }

            fetch(url, {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    // шаг сделан — следующий; ждём модель — реже; всё готово — хватит
                    if (!data || data.decided) { return; }
                    if (data.did === null && data.stage === 'done') { return; }
                    setTimeout(function () { run(url, tries - 1); }, data.did && data.did !== 'busy' ? 800 : 5000);
                })
                .catch(function () { setTimeout(function () { run(url, tries - 1); }, 6000); });
        }

        urls.forEach(function (url) { run(url, 40); });
    })();
</script>
@endif

@include('applicant.partials.js')
@include('applicant.partials.script')
</body>
</html>
