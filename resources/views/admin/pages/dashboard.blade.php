
@extends('admin.layouts.app')

@section('main')
    <style>
        .kpi-grid{
            display:grid; grid-template-columns:repeat(4,1fr); gap:16px;
            margin:0 3vh 2.5vh 2vh;
        }
        .a-card{
            background:#fff; border:1px solid #e2e8f0; border-radius:12px;
            box-shadow:0 1px 3px rgba(0,0,0,.02);
        }
        .kpi{padding:20px 22px;}
        .kpi-top{display:flex; align-items:flex-start; justify-content:space-between; gap:12px;}
        .kpi-label{font-size:12.5px; color:#94a3b8; font-weight:600; margin-bottom:6px;}
        .kpi-value{font-size:26px; font-weight:800; color:#1e293b; line-height:1;}
        .kpi-icon{
            width:38px; height:38px; border-radius:10px; flex-shrink:0;
            background:#EAF2FF; color:#1656D6;
            display:flex; align-items:center; justify-content:center; font-size:15px;
        }
        .kpi-sub{margin-top:12px; font-size:12.5px; color:#64748b; font-weight:500;}
        .kpi-sub b{color:#15803D;}

        .a-row{display:grid; grid-template-columns:1fr 1fr; gap:18px; margin:0 3vh 2.5vh 2vh;}
        .a-head{
            padding:16px 22px; border-bottom:1px solid #e2e8f0;
            font-weight:700; font-size:14.5px; color:#1e293b;
            display:flex; align-items:center; justify-content:space-between; gap:10px;
        }
        .a-head .hint{font-size:12.5px; color:#94a3b8; font-weight:500;}
        .a-body{padding:20px 22px;}

        .chart-x{display:flex; justify-content:space-between; font-size:11.5px; color:#94a3b8; margin-top:6px;}
        .chart-legend{display:flex; gap:18px; margin-top:12px; font-size:12.5px; color:#475569;}
        .chart-legend .dot{width:9px; height:9px; border-radius:2px; display:inline-block; margin-right:6px;}

        .donut-wrap{display:flex; align-items:center; gap:26px; flex-wrap:wrap;}
        .donut{width:150px; height:150px; border-radius:50%; flex-shrink:0; position:relative;}
        .donut::after{content:""; position:absolute; inset:26%; background:#fff; border-radius:50%;}
        .donut-legend{display:flex; flex-direction:column; gap:9px; font-size:12.5px; color:#475569; flex:1; min-width:180px;}
        .donut-legend .row-l{display:flex; align-items:center; gap:8px;}
        .donut-legend .pct{margin-left:auto; font-weight:700; color:#1e293b;}

        .a-table{width:100%; border-collapse:collapse; font-size:13.5px;}
        .a-table thead th{
            text-align:left; font-size:11.5px; text-transform:uppercase; letter-spacing:.04em;
            color:#94a3b8; font-weight:700; padding:0 12px 10px; border-bottom:1px solid #e2e8f0;
        }
        .a-table tbody td{padding:13px 12px; border-bottom:1px solid #f1f5f9; color:#475569;}
        .a-table tbody tr:last-child td{border-bottom:none;}
        .a-table .bold{font-weight:700; color:#1e293b;}
        .a-empty{padding:26px 12px; text-align:center; color:#94a3b8;}

        .mini-grid{display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin:0 3vh 4vh 2vh;}
        .mini{padding:18px 20px;}
        .mini .n{font-size:22px; font-weight:800; color:#1e293b;}
        .mini .l{font-size:12.5px; color:#94a3b8; margin-top:4px; font-weight:600;}

        @media (max-width:1200px){
            .kpi-grid, .mini-grid{grid-template-columns:repeat(2,1fr);}
            .a-row{grid-template-columns:1fr;}
        }
    </style>

    @php
        // максимум для масштабирования графика
        $maxReg = max(1, $registrations->max(fn ($m) => max($m['applicants'], $m['employers'])));

        $palette = ['#1656D6', '#2F6FEF', '#6FA0F5', '#9CBEF7', '#C98A12', '#8592A6'];

        // доли категорий для кольцевой диаграммы. Показываем шесть крупнейших,
        // остальные сводим в «Прочие»: доли только от показанных категорий
        // не давали 100%, круг не замыкался, а легенда не сходилась
        $parts = $categories->take(6)
            ->map(fn ($category) => ['name' => $category->name, 'count' => (int) $category->vacancies_count])
            ->values()->all();

        $rest = $categoriesSum - array_sum(array_column($parts, 'count'));

        if ($rest > 0) {
            $parts[] = ['name' => 'Прочие категории', 'count' => $rest];
            $palette[] = '#CBD5E1';
        }

        $percents = array_values(\App\Support\AdminNumbers::shares(array_column($parts, 'count')));

        $slices = [];
        $offset = 0;
        foreach ($parts as $i => $part) {
            $slices[] = [
                'name' => $part['name'],
                'count' => $part['count'],
                'percent' => $percents[$i],
                'color' => $palette[$i] ?? '#8592A6',
                'from' => $offset,
                'to' => $offset + $percents[$i],
            ];
            $offset += $percents[$i];
        }

        $gradient = count($slices)
            ? 'conic-gradient(' . collect($slices)
                ->map(fn ($s) => $s['color'] . ' ' . round($s['from'], 2) . '% ' . round($s['to'], 2) . '%')
                ->implode(', ') . ')'
            : 'conic-gradient(#E4E9F2 0% 100%)';
    @endphp

    <section class="page" id="page-dashboard" style="display:block !important; background:#fcfcfd; border-radius:2vh;">

        <div class="section-header mb-4">
            <h1 class="disp font-weight-bold" style="color:#1e293b; font-size:28px; margin-left:5vh; margin-top:5vh;">Обзор</h1>
            <div class="sub text-muted" style="font-size:.95rem; color:#64748b; margin-right:5vh;">
                Реальные показатели платформы на {{ now()->format('d.m.Y H:i') }}
            </div>
        </div>

        {{-- ===== KPI ===== --}}
        <div class="kpi-grid">
            <div class="a-card kpi">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Активные вакансии</div>
                        <div class="kpi-value" data-live="activeVacancies">{{ $activeVacancies }}</div>
                    </div>
                    <div class="kpi-icon"><i class="fa-solid fa-briefcase"></i></div>
                </div>
                <div class="kpi-sub"><span data-live="vacanciesTotal">{{ $vacanciesTotal }}</span> всего · <b>+<span data-live="vacanciesMonth">{{ $vacanciesMonth }}</span></b> за месяц</div>
            </div>

            <div class="a-card kpi">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Пользователи</div>
                        <div class="kpi-value" data-live="usersTotal">{{ $usersTotal }}</div>
                    </div>
                    <div class="kpi-icon"><i class="fa-solid fa-users"></i></div>
                </div>
                <div class="kpi-sub">
                    <span data-live="applicantsTotal">{{ $applicantsTotal }}</span> соискателей · <span data-live="employersTotal">{{ $employersTotal }}</span> компаний · <b>+<span data-live="usersMonth">{{ $usersMonth }}</span></b> за месяц
                </div>
            </div>

            <div class="a-card kpi">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Резюме</div>
                        <div class="kpi-value" data-live="resumesTotal">{{ $resumesTotal }}</div>
                    </div>
                    <div class="kpi-icon"><i class="fa-solid fa-file-lines"></i></div>
                </div>
                <div class="kpi-sub"><b>+<span data-live="resumesMonth">{{ $resumesMonth }}</span></b> за месяц</div>
            </div>

            <div class="a-card kpi">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Отклики</div>
                        <div class="kpi-value" data-live="responsesTotal">{{ $responsesTotal }}</div>
                    </div>
                    <div class="kpi-icon"><i class="fa-solid fa-inbox"></i></div>
                </div>
                <div class="kpi-sub"><span data-live="responsesNew">{{ $responsesNew }}</span> со статусом «новый»</div>
            </div>
        </div>

        {{-- ===== графики ===== --}}
        <div class="a-row">
            <div class="a-card">
                <div class="a-head">
                    Регистрации по месяцам
                    <span class="hint">последние 6 месяцев</span>
                </div>
                <div class="a-body">
                    @php
                        $step = 600 / max(1, $registrations->count() - 1);
                        $pointsApplicants = [];
                        $pointsEmployers = [];
                        foreach ($registrations as $i => $month) {
                            $x = 30 + $i * $step;
                            $pointsApplicants[] = round($x) . ',' . round(200 - ($month['applicants'] / $maxReg) * 160);
                            $pointsEmployers[] = round($x) . ',' . round(200 - ($month['employers'] / $maxReg) * 160);
                        }
                    @endphp

                    <svg viewBox="0 0 660 210" width="100%" style="display:block;">
                        <line x1="30" y1="20" x2="30" y2="200" stroke="#e2e8f0"/>
                        <line x1="30" y1="200" x2="630" y2="200" stroke="#e2e8f0"/>
                        <polyline points="{{ implode(' ', $pointsApplicants) }}" fill="none" stroke="#1656D6" stroke-width="2.5"/>
                        <polyline points="{{ implode(' ', $pointsEmployers) }}" fill="none" stroke="#C98A12" stroke-width="2.5"/>
                        @foreach($pointsApplicants as $p)
                            <circle cx="{{ explode(',', $p)[0] }}" cy="{{ explode(',', $p)[1] }}" r="3.5" fill="#1656D6"/>
                        @endforeach
                        @foreach($pointsEmployers as $p)
                            <circle cx="{{ explode(',', $p)[0] }}" cy="{{ explode(',', $p)[1] }}" r="3" fill="#C98A12"/>
                        @endforeach
                    </svg>

                    <div class="chart-x">
                        @foreach($registrations as $month)
                            <span>{{ $month['label'] }}</span>
                        @endforeach
                    </div>
                    <div class="chart-legend">
                        <span><span class="dot" style="background:#1656D6"></span>Соискатели</span>
                        <span><span class="dot" style="background:#C98A12"></span>Работодатели</span>
                        <span style="margin-left:auto; color:#94a3b8;">максимум за месяц: {{ $maxReg }}</span>
                    </div>
                </div>
            </div>

            <div class="a-card">
                <div class="a-head">
                    Вакансии по категориям
                    <span class="hint">{{ $categoriesSum }} вакансий с категорией</span>
                </div>
                <div class="a-body">
                    @if(count($slices))
                        <div class="donut-wrap">
                            <div class="donut" style="background:{{ $gradient }};"></div>
                            <div class="donut-legend">
                                @foreach($slices as $slice)
                                    <div class="row-l">
                                        <span class="dot" style="width:9px; height:9px; border-radius:2px; background:{{ $slice['color'] }};"></span>
                                        {{ $slice['name'] }}
                                        <span class="pct">{{ $slice['percent'] }}% · {{ $slice['count'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="a-empty">Пока нет вакансий, привязанных к категориям</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ===== таблицы ===== --}}
        <div class="a-row">
            <div class="a-card">
                <div class="a-head">Последние вакансии</div>
                <div class="a-body" style="padding:20px 10px 10px;">
                    <table class="a-table">
                        <thead>
                        <tr><th>Вакансия</th><th>Компания</th><th>Отклики</th><th>Дата</th></tr>
                        </thead>
                        <tbody>
                        @forelse($latestVacancies as $vacancy)
                            <tr>
                                <td class="bold">
                                    <a href="{{ route('superadmin.vacancy.show', $vacancy) }}" style="color:inherit;">{{ $vacancy->title }}</a>
                                </td>
                                <td>{{ $vacancy->employer->company_name ?? '—' }}</td>
                                <td>{{ $vacancy->responses_count }}</td>
                                <td>{{ $vacancy->created_at->format('d.m.Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="a-empty">Вакансий пока нет</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="a-card">
                <div class="a-head">Последние отклики</div>
                <div class="a-body" style="padding:20px 10px 10px;">
                    <table class="a-table">
                        <thead>
                        <tr><th>Соискатель</th><th>Вакансия</th><th>Статус</th><th>Дата</th></tr>
                        </thead>
                        <tbody>
                        @forelse($latestResponses as $response)
                            <tr>
                                <td class="bold">{{ $response->applicant?->user?->name ?? '—' }}</td>
                                <td>{{ $response->vacancy?->title ?? '—' }}</td>
                                <td>
                                    {{ ['new' => 'Новый', 'viewed' => 'Просмотрен', 'accepted' => 'Принят', 'rejected' => 'Отклонён'][$response->status] ?? $response->status }}
                                </td>
                                <td>{{ $response->created_at->format('d.m.Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="a-empty">Откликов пока нет</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ===== вторичные показатели ===== --}}
        <div class="mini-grid">
            <div class="a-card mini">
                <div class="n" data-live="interviewsTotal">{{ $interviewsTotal }}</div>
                <div class="l">собеседований, <span data-live="interviewsUpcoming">{{ $interviewsUpcoming }}</span> впереди</div>
            </div>
            <div class="a-card mini">
                <div class="n" data-live="conversationsTotal">{{ $conversationsTotal }}</div>
                <div class="l">диалогов в чате</div>
            </div>
            <div class="a-card mini">
                <div class="n" data-live="messagesTotal">{{ $messagesTotal }}</div>
                <div class="l">сообщений отправлено</div>
            </div>
        </div>

    </section>
@endsection
