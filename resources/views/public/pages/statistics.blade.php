@extends('public.layouts.app')

@section('content')

    @php
        $h = $stats['headline'];
        $months = $stats['months'];
        $skills = $stats['skills'];

        // ---- геометрия графика динамики ----
        // Считаем координаты в PHP: так разметка приезжает готовой и график
        // виден сразу, без ожидания скрипта.
        $peak = 1;
        foreach ($months as $m) {
            $peak = max($peak, $m['users'], $m['vacancies'], $m['responses']);
        }

        // округляем потолок вверх до «круглого», иначе подписи оси выглядят рвано
        $step = (int) max(1, ceil($peak / 3));
        $magnitude = 10 ** max(0, strlen((string) $step) - 1);
        $step = (int) (ceil($step / $magnitude) * $magnitude);
        $top = $step * 3;

        $W = 760; $H = 250;
        $padL = 46; $padR = 14; $padT = 16; $padB = 30;
        $innerW = $W - $padL - $padR;
        $innerH = $H - $padT - $padB;
        $last = max(1, count($months) - 1);

        $px = fn ($i) => round($padL + $innerW * ($i / $last), 1);
        $py = fn ($v) => round($padT + $innerH - $innerH * ($v / $top), 1);

        $line = function (string $key) use ($months, $px, $py) {
            $points = [];
            foreach ($months as $i => $m) {
                $points[] = $px($i).','.$py($m[$key]);
            }
            return implode(' ', $points);
        };

        // площадь под откликами: тот же контур, замкнутый по нижней границе
        $areaPoints = $line('responses')
            .' '.$px($last).','.($padT + $innerH)
            .' '.$px(0).','.($padT + $innerH);

        // ---- бублики по условиям работы ----
        $ring = function (array $rows, int $total) {
            $R = 54; $C = 2 * M_PI * $R;
            $offset = 0; $out = [];
            $palette = ['var(--accent)', 'var(--good)', 'var(--warn)', 'var(--accent-text)', 'var(--text-muted)'];

            foreach ($rows as $i => $row) {
                $share = $total > 0 ? $row['count'] / $total : 0;
                $out[] = [
                    'label' => $row['label'],
                    'count' => $row['count'],
                    'percent' => (int) round($share * 100),
                    'dash' => round($share * $C, 2).' '.round($C - $share * $C, 2),
                    'offset' => round(-$offset * $C, 2),
                    'color' => $palette[$i % count($palette)],
                ];
                $offset += $share;
            }
            return $out;
        };
    @endphp

    <style>
        .st-page { padding: 44px 0 80px; }
        .st-head { max-width: 62ch; margin-bottom: 34px; }
        .st-eyebrow {
            display: inline-block; font-size: 12px; font-weight: 700; letter-spacing: .14em;
            text-transform: uppercase; color: var(--accent-text);
            background: var(--accent-soft); padding: 6px 12px; border-radius: 999px; margin-bottom: 16px;
        }
        .st-head h1 { font-family: 'Manrope', sans-serif; font-size: clamp(28px, 4vw, 42px); font-weight: 800; letter-spacing: -.02em; margin-bottom: 12px; }
        .st-head p { font-size: 15.5px; color: var(--text-muted); line-height: 1.6; }

        /* ---------- ключевые цифры ---------- */
        /* ровно два ряда по три: плиток шесть, и auto-fit раскладывал их
           то по четыре, то по пять — ряды получались неровными */
        .st-tiles { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 34px; }
        @media (max-width: 860px) { .st-tiles { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 520px) { .st-tiles { grid-template-columns: 1fr; } }
        .st-tile {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 16px; padding: 20px 22px; box-shadow: var(--shadow);
        }
        /* главная цифра страницы выделена, остальные тише — иначе всё кричит разом */
        .st-tile.lead { border-color: var(--accent); }
        /* плитка-ссылка: за цифрой стоит список, и это должно быть видно */
        a.st-tile { display: block; color: inherit; transition: transform .15s ease, border-color .15s ease; }
        a.st-tile:hover { transform: translateY(-2px); border-color: var(--accent-text); }
        a.st-tile:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }
        .st-tile .more {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 12.5px; font-weight: 600; color: var(--accent-text); margin-top: 8px;
        }
        .st-tile .v {
            font-family: 'Manrope', sans-serif; font-size: 34px; font-weight: 800;
            letter-spacing: -.03em; font-variant-numeric: tabular-nums; line-height: 1.1;
        }
        .st-tile.lead .v { color: var(--accent-text); font-size: 40px; }
        .st-tile .k { font-size: 13px; color: var(--text-muted); margin-top: 6px; }

        /* ---------- карточка раздела ---------- */
        .st-card {
            background: var(--surface); border: 1px solid var(--border); border-radius: 18px;
            padding: 24px; box-shadow: var(--shadow); margin-bottom: 20px;
        }
        .st-card h2 { font-family: 'Manrope', sans-serif; font-size: 18px; font-weight: 800; margin-bottom: 4px; }
        .st-card .sub { font-size: 13.5px; color: var(--text-muted); margin-bottom: 18px; }

        .st-two { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 900px) { .st-two { grid-template-columns: 1fr; } }

        /* ---------- график динамики ---------- */
        /* широкий график не должен растягивать страницу вбок */
        .st-chart { overflow-x: auto; }
        .st-chart svg { display: block; min-width: 560px; width: 100%; height: auto; }
        .st-grid line { stroke: var(--border); stroke-width: 1; }
        .st-axis { fill: var(--text-muted); font-size: 11px; font-family: 'Inter', sans-serif; }
        .st-serie { fill: none; stroke-width: 2.5; stroke-linejoin: round; stroke-linecap: round; }
        .st-legend { display: flex; flex-wrap: wrap; gap: 16px; margin-top: 14px; font-size: 13px; color: var(--text-muted); }
        .st-legend i { width: 10px; height: 10px; border-radius: 3px; display: inline-block; margin-right: 7px; vertical-align: -1px; }

        /* ---------- горизонтальные полосы ---------- */
        .st-bars { display: flex; flex-direction: column; gap: 11px; }
        .st-bar { display: grid; grid-template-columns: minmax(90px, 130px) 1fr 52px; gap: 12px; align-items: center; }
        .st-bar { align-items: center; }
        /* раньше подпись обрезалась многоточием; таджикские названия длиннее,
           поэтому даём им перенос — так видно название целиком */
        .st-bar .n { font-size: 13px; overflow-wrap: break-word; line-height: 1.35; }
        .st-bar .t { height: 9px; border-radius: 999px; background: var(--surface-alt); overflow: hidden; }
        .st-bar .t span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--accent), var(--accent-text)); min-width: 3px; }
        .st-bar .v { font-size: 13px; font-weight: 700; text-align: right; font-variant-numeric: tabular-nums; }

        /* ---------- бублики ---------- */
        .st-rings { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 22px; }
        .st-ring { text-align: center; }
        .st-ring h3 { font-size: 14px; font-weight: 700; margin-bottom: 12px; }
        .st-ring svg { width: 150px; height: 150px; }
        .st-ring .hole { fill: var(--text); font-family: 'Manrope', sans-serif; font-size: 20px; font-weight: 800; }
        .st-ring .hole-k { fill: var(--text-muted); font-size: 10px; }
        .st-keys { display: flex; flex-direction: column; gap: 6px; margin-top: 12px; font-size: 12.5px; text-align: left; }
        .st-keys div { display: flex; align-items: center; gap: 8px; }
        /* длинная подпись переносится внутри своей колонки, процент остаётся справа */
        .st-keys div span { min-width: 0; overflow-wrap: break-word; }
        .st-keys i { width: 9px; height: 9px; border-radius: 2px; flex-shrink: 0; }
        .st-keys b { margin-left: auto; font-variant-numeric: tabular-nums; }

        /* ---------- навыки ---------- */
        .st-proof { display: grid; grid-template-columns: 180px 1fr; gap: 26px; align-items: center; }
        @media (max-width: 760px) { .st-proof { grid-template-columns: 1fr; } }
        .st-gauge { position: relative; width: 170px; margin-inline: auto; }
        .st-gauge svg { width: 170px; height: 170px; }
        .st-gauge .big { fill: var(--good); font-family: 'Manrope', sans-serif; font-size: 30px; font-weight: 800; }
        .st-gauge .small { fill: var(--text-muted); font-size: 10.5px; }
        /* оговорка о малой выборке: процент от нескольких попыток
           не должен читаться как показатель всей площадки */
        .st-caveat { font-size: 12px; color: var(--warn); margin-top: 8px; text-align: center; }
        .st-empty { font-size: 13.5px; color: var(--text-muted); }

        .st-note { font-size: 12.5px; color: var(--text-muted); margin-top: 6px; }
    </style>

    <section class="st-page">
        <div class="container">

            <div class="st-head">
                <span class="st-eyebrow">{{ __('Статистика платформы') }}</span>
                <h1>{{ __('Workio в цифрах') }}</h1>
                <p>{{ __('Здесь видно, что происходит на площадке: сколько людей вышли на работу, где открыты вакансии и какие навыки кандидаты подтверждают заданием, а не словами в резюме.') }}</p>
            </div>

            {{-- ===== ключевые цифры ===== --}}
            <div class="st-tiles">
                <a href="{{ route('public.stats.hired') }}" class="st-tile lead">
                    <div class="v">{{ number_format($h['hired'], 0, ',', ' ') }}</div>
                    <div class="k">{{ __('человек вышли на работу') }}</div>
                    <span class="more">{{ __('Посмотреть список') }} →</span>
                </a>
                {{-- ведём в каталог: он показывает ровно те же вакансии,
                     что посчитаны здесь, отдельный список был бы лишним --}}
                <a href="{{ route('public.vacancies.index') }}" class="st-tile">
                    <div class="v">{{ number_format($h['vacancies'], 0, ',', ' ') }}</div>
                    <div class="k">{{ __('активных вакансий') }}</div>
                    <span class="more">{{ __('Посмотреть список') }} →</span>
                </a>
                <div class="st-tile">
                    <div class="v">{{ number_format($h['resumes'], 0, ',', ' ') }}</div>
                    <div class="k">{{ __('резюме на платформе') }}</div>
                </div>
                <div class="st-tile">
                    <div class="v">{{ number_format($h['companies'], 0, ',', ' ') }}</div>
                    <div class="k">{{ __('компаний-работодателей') }}</div>
                </div>
                <div class="st-tile">
                    <div class="v">{{ number_format($h['responses'], 0, ',', ' ') }}</div>
                    <div class="k">{{ __('откликов и приглашений') }}</div>
                </div>
                <div class="st-tile">
                    <div class="v">{{ number_format($h['cities'], 0, ',', ' ') }}</div>
                    <div class="k">{{ __('городов с открытыми вакансиями') }}</div>
                </div>
            </div>

            {{-- ===== динамика ===== --}}
            <div class="st-card">
                <h2>{{ __('Динамика за 12 месяцев') }}</h2>
                <p class="sub">{{ __('Регистрации, новые вакансии и отклики по месяцам.') }}</p>

                <div class="st-chart">
                    <svg viewBox="0 0 {{ $W }} {{ $H }}" role="img"
                         aria-label="{{ __('График регистраций, вакансий и откликов по месяцам') }}">
                        {{-- сетка и подписи оси: каждая подпись называет значение, которого график достигает --}}
                        <g class="st-grid">
                            @for($g = 0; $g <= 3; $g++)
                                @php $gy = $padT + $innerH - $innerH * ($g / 3); @endphp
                                <line x1="{{ $padL }}" y1="{{ $gy }}" x2="{{ $W - $padR }}" y2="{{ $gy }}"></line>
                                <text class="st-axis" x="{{ $padL - 8 }}" y="{{ $gy + 4 }}" text-anchor="end">{{ $step * $g }}</text>
                            @endfor
                        </g>

                        <defs>
                            <linearGradient id="stFill" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="var(--accent)" stop-opacity=".28"></stop>
                                <stop offset="100%" stop-color="var(--accent)" stop-opacity="0"></stop>
                            </linearGradient>
                        </defs>

                        <polygon points="{{ $areaPoints }}" fill="url(#stFill)"></polygon>

                        <polyline class="st-serie" points="{{ $line('responses') }}" stroke="var(--accent)"></polyline>
                        <polyline class="st-serie" points="{{ $line('vacancies') }}" stroke="var(--good)"></polyline>
                        <polyline class="st-serie" points="{{ $line('users') }}" stroke="var(--warn)"></polyline>

                        @foreach($months as $i => $m)
                            <text class="st-axis" x="{{ $px($i) }}" y="{{ $H - 9 }}" text-anchor="middle">{{ $m['label'] }}</text>
                        @endforeach
                    </svg>
                </div>

                <div class="st-legend">
                    <span><i style="background:var(--accent)"></i>{{ __('Отклики и приглашения') }}</span>
                    <span><i style="background:var(--good)"></i>{{ __('Новые вакансии') }}</span>
                    <span><i style="background:var(--warn)"></i>{{ __('Регистрации') }}</span>
                </div>
            </div>

            {{-- ===== география и отрасли ===== --}}
            <div class="st-two">
                <div class="st-card">
                    <h2>{{ __('Где открыты вакансии') }}</h2>
                    <p class="sub">{{ __('Города с наибольшим числом активных вакансий.') }}</p>

                    @php $cityMax = max(1, collect($stats['cities'])->max('count') ?? 1); @endphp
                    <div class="st-bars">
                        @forelse($stats['cities'] as $city)
                            <div class="st-bar">
                                <span class="n" title="{{ __($city['name']) }}">{{ __($city['name']) }}</span>
                                <span class="t"><span style="width: {{ round($city['count'] / $cityMax * 100, 1) }}%"></span></span>
                                <span class="v">{{ $city['count'] }}</span>
                            </div>
                        @empty
                            <p class="st-empty">{{ __('Активных вакансий пока нет.') }}</p>
                        @endforelse
                    </div>
                </div>

                <div class="st-card">
                    <h2>{{ __('Отрасли') }}</h2>
                    <p class="sub">{{ __('По направлениям компаний, разместивших вакансии.') }}</p>

                    @php $indMax = max(1, collect($stats['industries'])->max('count') ?? 1); @endphp
                    <div class="st-bars">
                        @forelse($stats['industries'] as $industry)
                            <div class="st-bar">
                                <span class="n" title="{{ __($industry['name']) }}">{{ __($industry['name']) }}</span>
                                <span class="t"><span style="width: {{ round($industry['count'] / $indMax * 100, 1) }}%"></span></span>
                                <span class="v">{{ $industry['count'] }}</span>
                            </div>
                        @empty
                            <p class="st-empty">{{ __('Данных пока нет.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- ===== условия работы ===== --}}
            <div class="st-card">
                <h2>{{ __('Что предлагают работодатели') }}</h2>
                <p class="sub">{{ __('Доли среди активных вакансий.') }}</p>

                <div class="st-rings">
                    @foreach($stats['conditions'] as $block)
                        @php $segments = $ring($block['rows'], $block['total']); @endphp
                        <div class="st-ring">
                            <h3>{{ __($block['title']) }}</h3>

                            @if($block['total'] > 0)
                                <svg viewBox="0 0 140 140" role="img" aria-label="{{ __($block['title']) }}">
                                    {{-- поворот на -90°, чтобы первый сегмент начинался сверху --}}
                                    <g transform="rotate(-90 70 70)">
                                        <circle cx="70" cy="70" r="54" fill="none" stroke="var(--surface-alt)" stroke-width="18"></circle>
                                        @foreach($segments as $seg)
                                            <circle cx="70" cy="70" r="54" fill="none"
                                                    stroke="{{ $seg['color'] }}" stroke-width="18"
                                                    stroke-dasharray="{{ $seg['dash'] }}"
                                                    stroke-dashoffset="{{ $seg['offset'] }}"></circle>
                                        @endforeach
                                    </g>
                                    <text class="hole" x="70" y="70" text-anchor="middle">{{ $block['total'] }}</text>
                                    <text class="hole-k" x="70" y="86" text-anchor="middle">{{ __('вакансий') }}</text>
                                </svg>

                                <div class="st-keys">
                                    @foreach($segments as $seg)
                                        <div>
                                            <i style="background: {{ $seg['color'] }}"></i>
                                            <span>{{ __($seg['label']) }}</span>
                                            <b>{{ $seg['percent'] }}%</b>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="st-empty">{{ __('Активных вакансий пока нет.') }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- ===== подтверждённые навыки ===== --}}
            <div class="st-card">
                <h2>{{ __('Навыки, подтверждённые заданием') }}</h2>
                <p class="sub">{{ __('Кандидат проходит короткое задание прямо на платформе, и работодатель видит результат, а не только строчку в резюме.') }}</p>

                <div class="st-proof">
                    <div class="st-gauge">
                        @php
                            $R = 60; $C = 2 * M_PI * $R;
                            $done = $skills['rate'] / 100;
                        @endphp
                        @php
                            // ниже этого числа попыток процент ещё ничего не значит:
                            // одна сданная работа сдвигает его на десятки пунктов
                            $enoughAttempts = $skills['attempts'] >= 20;
                        @endphp
                        <svg viewBox="0 0 170 170" role="img"
                             aria-label="{{ __('Заданий сдано') }}: {{ $skills['passed'] }} {{ __('из') }} {{ $skills['attempts'] }}">
                            <g transform="rotate(-90 85 85)">
                                <circle cx="85" cy="85" r="{{ $R }}" fill="none" stroke="var(--surface-alt)" stroke-width="16"></circle>
                                <circle cx="85" cy="85" r="{{ $R }}" fill="none" stroke="var(--good)" stroke-width="16"
                                        stroke-linecap="round"
                                        stroke-dasharray="{{ round($done * $C, 2) }} {{ round($C - $done * $C, 2) }}"></circle>
                            </g>
                            {{-- пока попыток мало, показываем сами числа, а не долю --}}
                            @if($enoughAttempts)
                                <text class="big" x="85" y="86" text-anchor="middle">{{ $skills['rate'] }}%</text>
                                <text class="small" x="85" y="103" text-anchor="middle">{{ __('заданий сдано') }}</text>
                            @else
                                <text class="big" x="85" y="86" text-anchor="middle">{{ $skills['passed'] }} / {{ $skills['attempts'] }}</text>
                                <text class="small" x="85" y="103" text-anchor="middle">{{ __('заданий сдано') }}</text>
                            @endif
                        </svg>

                        @unless($enoughAttempts)
                            <p class="st-caveat">{{ __('Попыток пока мало — доля будет заметно меняться') }}</p>
                        @endunless
                    </div>

                    <div>
                        <div class="st-bars" style="margin-bottom:14px;">
                            @php $skillMax = max(1, collect($skills['top'])->max('count') ?? 1); @endphp
                            @forelse($skills['top'] as $skill)
                                <div class="st-bar">
                                    <span class="n" title="{{ $skill['name'] }}">{{ $skill['name'] }}</span>
                                    <span class="t"><span style="width: {{ round($skill['count'] / $skillMax * 100, 1) }}%"></span></span>
                                    <span class="v">{{ $skill['count'] }}</span>
                                </div>
                            @empty
                                <p class="st-empty">{{ __('Задания ещё никто не проходил — здесь появятся навыки, которые кандидаты подтвердили первыми.') }}</p>
                            @endforelse
                        </div>

                        <p class="st-note">
                            {{ __('Пройдено заданий:') }} <b>{{ $skills['attempts'] }}</b> ·
                            {{ __('из них сдано:') }} <b>{{ $skills['passed'] }}</b>
                            ({{ __('это') }} {{ $skills['rate'] }}%) ·
                            {{ __('сдано — результат не ниже') }} {{ $skills['passing'] }}% ·
                            {{ __('навыков в справочнике:') }} <b>{{ $skills['catalogue'] }}</b>
                        </p>
                    </div>
                </div>
            </div>

            <p class="st-note">
                {{ __('Данные обновляются раз в') }} {{ $ttl }} {{ __('минут. Показаны только обезличенные суммы.') }}
            </p>
        </div>
    </section>

@endsection
