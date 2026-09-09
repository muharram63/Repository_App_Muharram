
@extends('admin.layouts.app')

@section('main')
    <style>
        .an-wrap { margin: 0 3vh 2.5vh 2vh; }
        .an-card {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .02);
        }
        .an-head {
            padding: 16px 22px; border-bottom: 1px solid #e2e8f0;
            font-weight: 700; font-size: 14.5px; color: #1e293b;
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
        }
        .an-head .hint { font-size: 12.5px; color: #94a3b8; font-weight: 500; }
        .an-body { padding: 20px 22px; }

        .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .kpi { padding: 20px 22px; }
        .kpi .l { font-size: 12.5px; color: #94a3b8; font-weight: 600; margin-bottom: 6px; }
        .kpi .v { font-size: 26px; font-weight: 800; color: #1e293b; line-height: 1; }
        .kpi .s { margin-top: 10px; font-size: 12.5px; color: #64748b; }

        .an-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .an-row.thirds { grid-template-columns: repeat(3, 1fr); }

        /* горизонтальные полосы */
        .hbars { display: flex; flex-direction: column; gap: 12px; }
        .hbar { display: grid; grid-template-columns: 150px 1fr 60px; align-items: center; gap: 12px; }
        .hbar .n { font-size: 12.5px; color: #475569; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .hbar .track { background: #f1f5f9; border-radius: 999px; height: 10px; overflow: hidden; }
        /* доля меньше процента иначе выглядит как ноль */
        .hbar .fill { height: 100%; min-width: 3px; border-radius: 999px; background: linear-gradient(90deg, #2F6FEF, #1656D6); }
        .hbar .fill.empty { min-width: 0; }
        .hbar .v { font-size: 12.5px; font-weight: 700; color: #1e293b; text-align: right; }

        /* разрезы жалоб */
        .an-split { display: grid; grid-template-columns: 1fr 1fr; gap: 26px; }
        .an-sub { font-size: 13px; font-weight: 700; color: #475569; margin-bottom: 12px; }
        .dist-row { display: grid; grid-template-columns: 190px 1fr 46px; align-items: center; gap: 12px; margin-bottom: 8px; }
        .dist-row .lbl { font-size: 12.5px; color: #64748b; }
        .dist-row .bar { height: 8px; border-radius: 999px; background: #EAF2FF; overflow: hidden; display: block; }
        .dist-row .bar span { display: block; height: 100%; background: #1656D6; border-radius: 999px; }
        .dist-row .num { font-size: 12.5px; font-weight: 700; color: #1e293b; text-align: right; }
        @media (max-width: 900px) { .an-split { grid-template-columns: 1fr; } }

        .dyn-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .dyn { border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 18px; }
        .dyn .l { font-size: 12.5px; color: #94a3b8; font-weight: 600; margin-bottom: 8px; }
        .dyn .v { font-size: 23px; font-weight: 800; color: #1e293b; line-height: 1; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .dyn .s { margin-top: 10px; font-size: 12.5px; color: #64748b; }
        .dyn .badge { font-size: 12px; font-weight: 700; padding: 4px 9px; border-radius: 999px; }
        .dyn .badge.up { background: #dcfce7; color: #15803d; }
        .dyn .badge.down { background: #fee2e2; color: #b91c1c; }
        .dyn .badge.zero { background: #f1f5f9; color: #64748b; }
        @media (max-width: 1100px) { .dyn-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 700px) { .dyn-grid { grid-template-columns: 1fr; } }

        /* столбики по месяцам */
        .bars { display: flex; align-items: flex-end; gap: 10px; height: 200px; }
        .bars .col { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; }
        .bars .stack { width: 100%; max-width: 34px; display: flex; flex-direction: column-reverse; gap: 2px; }
        .bars .seg { border-radius: 4px 4px 0 0; }
        .bars .seg.v { background: #1656D6; }
        .bars .seg.r { background: #6FA0F5; }
        .bars .seg.a { background: #C98A12; }
        .bars .zero { width: 100%; max-width: 34px; height: 3px; border-radius: 2px; background: #e2e8f0; }
        .bars .lbl { font-size: 11px; color: #94a3b8; margin-top: 8px; }
        .legend { display: flex; gap: 18px; margin-top: 14px; font-size: 12.5px; color: #475569; flex-wrap: wrap; }
        .legend i { width: 9px; height: 9px; border-radius: 2px; display: inline-block; margin-right: 6px; }

        .an-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        .an-table thead th {
            text-align: left; font-size: 11.5px; text-transform: uppercase; letter-spacing: .04em;
            color: #94a3b8; font-weight: 700; padding: 0 10px 10px; border-bottom: 1px solid #e2e8f0;
        }
        .an-table tbody td { padding: 11px 10px; border-bottom: 1px solid #f1f5f9; color: #475569; }
        .an-table tbody tr:last-child td { border-bottom: none; }
        .an-table .b { font-weight: 700; color: #1e293b; }
        .an-empty { padding: 24px 10px; text-align: center; color: #94a3b8; }

        @media (max-width: 1200px) {
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .an-row, .an-row.thirds { grid-template-columns: 1fr; }
        }
    </style>

    @php
        $maxTimeline = max(1, $timeline->max(fn ($m) => $m['vacancies'] + $m['resumes'] + $m['responses']));
    @endphp

    <section class="page" id="page-analytics" style="display:block !important; background:#fcfcfd; border-radius:2vh;">

        <div class="section-header mb-4">
            <h1 class="disp font-weight-bold" style="color:#1e293b; font-size:28px; margin-left:5vh; margin-top:5vh;">Аналитика</h1>
            <div class="sub text-muted" style="font-size:.95rem; color:#64748b; margin-right:5vh;">
                Срез по платформе на {{ now()->format('d.m.Y H:i') }}
            </div>
        </div>

        {{-- ===== ключевые показатели ===== --}}
        <div class="an-wrap kpi-grid">
            <div class="an-card kpi">
                <div class="l">Конверсия в наём</div>
                <div class="v"><span data-live="an-conversion">{{ $conversion }}</span>%</div>
                <div class="s"><span data-live="an-accepted">{{ $acceptedTotal }}</span> принятых из <span data-live="an-obligations">{{ $responsesTotal + $invitesTotal }}</span> обращений</div>
            </div>
            <div class="an-card kpi">
                <div class="l">Отклики на активные вакансии</div>
                <div class="v"><span data-live="an-responses-active-share">{{ $responsesActiveShare }}</span>%</div>
                <div class="s">
                    <span data-live="an-responses-on-active">{{ $responsesOnActive }}</span> из
                    <span data-live="an-responses">{{ $responsesTotal }}</span> откликов
                </div>
            </div>
            <div class="an-card kpi">
                <div class="l">Активные вакансии</div>
                <div class="v" data-live="activeVacancies">{{ $activeVacancies }}</div>
                <div class="s">
                    из <span data-live="vacanciesTotal">{{ $vacanciesTotal }}</span> всего ·
                    <b>+<span data-live="vacanciesMonth">{{ $vacanciesMonth }}</span></b> за месяц
                </div>
            </div>
        </div>

        {{-- ===== живая динамика за 30 дней ===== --}}
        <div class="an-wrap an-card">
            <div class="an-head">
                За последние 30 дней
                <span class="hint">в сравнении с предыдущими 30 днями</span>
            </div>
            <div class="an-body">
                <div class="dyn-grid">
                    @foreach($dynamics as $i => $item)
                        @php
                            $slug = $dynamicKeys[$i];
                        @endphp
                        <div class="dyn">
                            <div class="l">{{ $item['label'] }}</div>
                            <div class="v">
                                <span data-live="an-dyn-{{ $slug }}">{{ number_format($item['value'], 0, ',', ' ') }}</span>
                                @if($item['delta'] === null)
                                    <span class="badge zero">нет базы</span>
                                @elseif($item['delta'] > 0)
                                    <span class="badge up">▲ {{ $item['delta'] }}%</span>
                                @elseif($item['delta'] < 0)
                                    <span class="badge down">▼ {{ abs($item['delta']) }}%</span>
                                @else
                                    <span class="badge zero">без изменений</span>
                                @endif
                            </div>
                            <div class="s">
                                было <span data-live="an-dyn-{{ $slug }}-prev">{{ number_format($item['previous'], 0, ',', ' ') }}</span> ·
                                сегодня <span data-live="an-dyn-{{ $slug }}-today">{{ $item['today'] }}</span>
                            </div>
                        </div>
                    @endforeach

                    <div class="dyn">
                        <div class="l">Конверсия за 30 дней</div>
                        <div class="v">
                            <span data-live="an-conv30">{{ $conversion30 }}</span>%
                            @php
                                $convDelta = round($conversion30 - $conversionPrev, 1);
                            @endphp
                            @if($convDelta > 0)
                                <span class="badge up">▲ {{ $convDelta }} п.п.</span>
                            @elseif($convDelta < 0)
                                <span class="badge down">▼ {{ abs($convDelta) }} п.п.</span>
                            @else
                                <span class="badge zero">без изменений</span>
                            @endif
                        </div>
                        <div class="s">
                            <span data-live="an-accepted30">{{ $accepted30 }}</span> принятых из <span data-live="an-responses30">{{ $responses30 }}</span> ·
                            было <span data-live="an-conv-prev">{{ $conversionPrev }}</span>%
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== жалобы ===== --}}
        <div class="an-wrap an-card">
            <div class="an-head">
                Жалобы и модерация
                <span class="hint">обращения пользователей и работа модераторов</span>
            </div>
            <div class="an-body">
                <div class="dyn-grid">
                    <div class="dyn">
                        <div class="l">Всего жалоб</div>
                        <div class="v" data-live="an-complaints-total">{{ $complaintsTotal }}</div>
                        <div class="s">новых без ответа — <span data-live="an-complaints-new">{{ $complaintsNew }}</span></div>
                    </div>

                    <div class="dyn">
                        <div class="l">Жалоб за 30 дней</div>
                        <div class="v">
                            <span data-live="an-complaints-cur">{{ $complaintsCur }}</span>
                            @if($complaintsDelta === null)
                                <span class="badge zero">нет базы</span>
                            @elseif($complaintsDelta > 0)
                                <span class="badge down">▲ {{ $complaintsDelta }}%</span>
                            @elseif($complaintsDelta < 0)
                                <span class="badge up">▼ {{ abs($complaintsDelta) }}%</span>
                            @else
                                <span class="badge zero">без изменений</span>
                            @endif
                        </div>
                        <div class="s">было <span data-live="an-complaints-prev">{{ $complaintsPrev }}</span> за предыдущие 30 дней</div>
                    </div>

                    <div class="dyn">
                        <div class="l">Доля решённых</div>
                        <div class="v"><span data-live="an-complaints-share">{{ $complaintsShare }}</span>%</div>
                        <div class="s">решено <span data-live="an-complaints-resolved">{{ $complaintsResolved }}</span> · отклонено <span data-live="an-complaints-rejected">{{ $complaintsRejected }}</span></div>
                    </div>
                </div>

                <div class="an-split" style="margin-top:18px;">
                    <div>
                        <div class="an-sub">По причинам</div>
                        @foreach(\App\Models\Complaint::REASONS as $key => $label)
                            @php
                                $value = (int) ($complaintsByReason[$key] ?? 0);
                            @endphp
                            <div class="dist-row">
                                <span class="lbl">{{ $label }}</span>
                                <span class="bar"><span data-live-width="an-reason-{{ $key }}-pct" style="width: {{ $complaintsTotal > 0 ? round($value / $complaintsTotal * 100) : 0 }}%;"></span></span>
                                <span class="num" data-live="an-reason-{{ $key }}">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div>
                        <div class="an-sub">По объектам</div>
                        @foreach(\App\Models\Complaint::TARGETS as $key => $label)
                            @php
                                $value = (int) ($complaintsByTarget[$key] ?? 0);
                            @endphp
                            <div class="dist-row">
                                <span class="lbl">{{ $label }}</span>
                                <span class="bar"><span data-live-width="an-target-{{ $key }}-pct" style="width: {{ $complaintsTotal > 0 ? round($value / $complaintsTotal * 100) : 0 }}%;"></span></span>
                                <span class="num" data-live="an-target-{{ $key }}">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== динамика ===== --}}
        <div class="an-wrap an-card">
            <div class="an-head">
                Динамика за 12 месяцев
                <span class="hint">вакансии, резюме и отклики по месяцам</span>
            </div>
            <div class="an-body">
                <div class="bars">
                    @foreach($timeline as $month)
                        @php
                            $sum = $month['vacancies'] + $month['resumes'] + $month['responses'];
                            $height = $sum ? max(6, (int) round($sum / $maxTimeline * 170)) : 0;
                            // доли сегментов дают ровно 100%: три независимых
                            // округления не заполняли столбец до конца
                            $parts = \App\Support\AdminNumbers::shares([
                                'vacancies' => $month['vacancies'],
                                'resumes' => $month['resumes'],
                                'responses' => $month['responses'],
                            ]);
                        @endphp
                        <div class="col" title="{{ $month['label'] }}: всего {{ $sum }} — вакансий {{ $month['vacancies'] }}, резюме {{ $month['resumes'] }}, откликов {{ $month['responses'] }}">
                            @if($sum)
                                <div class="stack" style="height: {{ $height }}px;">
                                    <div class="seg v" style="height: {{ $parts['vacancies'] }}%;"></div>
                                    <div class="seg r" style="height: {{ $parts['resumes'] }}%;"></div>
                                    <div class="seg a" style="height: {{ $parts['responses'] }}%;"></div>
                                </div>
                            @else
                                {{-- пустой месяц: без отметки столбец пропадал, и было не понять, есть ли данные --}}
                                <div class="zero"></div>
                            @endif
                            <div class="lbl">{{ $month['label'] }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="legend">
                    <span><i style="background:#1656D6"></i>Вакансии</span>
                    <span><i style="background:#6FA0F5"></i>Резюме</span>
                    <span><i style="background:#C98A12"></i>Отклики</span>
                    <span style="margin-left:auto; color:#94a3b8;">наибольший месяц: {{ $maxTimeline }} событий</span>
                </div>
            </div>
        </div>

        {{-- ===== распределения ===== --}}
        <div class="an-wrap an-row thirds">
            @foreach([
                ['Тип занятости', $byEmployment],
                ['График работы', $bySchedule],
                ['Требуемый опыт', $byExperience],
            ] as [$title, $rows])
                <div class="an-card">
                    <div class="an-head">{{ $title }}</div>
                    <div class="an-body">
                        <div class="hbars">
                            @foreach($rows as $row)
                                <div class="hbar" style="grid-template-columns:130px 1fr 60px;">
                                    <div class="n">{{ $row['label'] }}</div>
                                    <div class="track">
                                        <div class="fill{{ $row['count'] ? '' : ' empty' }}"
                                             data-live-width="{{ $row['slug'] }}-pct"
                                             style="width: {{ $row['percent'] }}%;"></div>
                                    </div>
                                    <div class="v" data-live="{{ $row['slug'] }}">{{ $row['count'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="an-wrap an-row">
            <div class="an-card">
                <div class="an-head">Статусы вакансий</div>
                <div class="an-body">
                    <div class="hbars">
                        @foreach($byStatus as $row)
                            <div class="hbar">
                                <div class="n">{{ $row['label'] }}</div>
                                <div class="track">
                                    <div class="fill{{ $row['count'] ? '' : ' empty' }}"
                                         data-live-width="{{ $row['slug'] }}-pct"
                                         style="width: {{ $row['percent'] }}%;"></div>
                                </div>
                                <div class="v" data-live="{{ $row['slug'] }}">{{ $row['count'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="an-card">
                <div class="an-head">Статусы откликов</div>
                <div class="an-body">
                    <div class="hbars">
                        @foreach($byResponseStatus as $row)
                            <div class="hbar">
                                <div class="n">{{ $row['label'] }}</div>
                                <div class="track">
                                    <div class="fill{{ $row['count'] ? '' : ' empty' }}"
                                         data-live-width="{{ $row['slug'] }}-pct"
                                         style="width: {{ $row['percent'] }}%;"></div>
                                </div>
                                <div class="v" data-live="{{ $row['slug'] }}">{{ $row['count'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== топы ===== --}}
        <div class="an-wrap an-row">
            <div class="an-card">
                <div class="an-head">Категории по числу вакансий</div>
                <div class="an-body">
                    @if($topCategories->isEmpty())
                        <div class="an-empty">Нет данных</div>
                    @else
                        @php $catMax = max(1, $topCategories->max('vacancies_count')); @endphp
                        <div class="hbars">
                            @foreach($topCategories as $category)
                                <div class="hbar">
                                    <div class="n">{{ $category->name }}</div>
                                    <div class="track"><div class="fill" style="width: {{ round($category->vacancies_count / $catMax * 100) }}%;"></div></div>
                                    <div class="v">{{ $category->vacancies_count }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="an-card">
                <div class="an-head">Города по числу вакансий</div>
                <div class="an-body">
                    @if($topCities->isEmpty())
                        <div class="an-empty">Нет данных</div>
                    @else
                        @php $cityMax = max(1, $topCities->max('vacancies_count')); @endphp
                        <div class="hbars">
                            @foreach($topCities as $city)
                                <div class="hbar">
                                    <div class="n">{{ $city->region }}</div>
                                    <div class="track"><div class="fill" style="width: {{ round($city->vacancies_count / $cityMax * 100) }}%;"></div></div>
                                    <div class="v">{{ $city->vacancies_count }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="an-wrap an-row">
            <div class="an-card">
                <div class="an-head">Вакансии с наибольшим числом откликов</div>
                <div class="an-body" style="padding:20px 12px 12px;">
                    <table class="an-table">
                        <thead><tr><th>Вакансия</th><th>Компания</th><th>Отклики</th></tr></thead>
                        <tbody>
                        @forelse($topVacancies as $vacancy)
                            <tr>
                                <td class="b">
                                    <a href="{{ route('superadmin.vacancy.show', $vacancy) }}" style="color:inherit;">{{ $vacancy->title }}</a>
                                </td>
                                <td>{{ $vacancy->employer->company_name ?? '—' }}</td>
                                <td class="b">{{ $vacancy->responses_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="an-empty">Нет данных</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="an-card">
                <div class="an-head">Резюме с наибольшим числом приглашений</div>
                <div class="an-body" style="padding:20px 12px 12px;">
                    <table class="an-table">
                        <thead><tr><th>Соискатель</th><th>Профессия</th><th>Опыт</th><th>Приглашения</th></tr></thead>
                        <tbody>
                        @forelse($topResumes as $resume)
                            <tr>
                                <td class="b">
                                    <a href="{{ route('superadmin.resumes.show', $resume) }}" style="color:inherit;">
                                        {{ $resume->applicant?->user?->name ?? '—' }}
                                    </a>
                                </td>
                                <td>{{ $resume->profession }}</td>
                                <td>{{ (int) $resume->experience_years }} г.</td>
                                <td class="b">{{ $resume->responses_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="an-empty">Нет данных</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="an-wrap an-card" style="margin-bottom:4vh;">
            <div class="an-head">
                Компании по числу вакансий
                <span class="hint"><span data-live="employersTotal">{{ $employersTotal }}</span> компаний · <span data-live="applicantsTotal">{{ $applicantsTotal }}</span> соискателей · средний опыт <span data-live="an-avg-experience">{{ $avgExperience }}</span> г.</span>
            </div>
            <div class="an-body" style="padding:20px 12px 12px;">
                <table class="an-table">
                    <thead><tr><th>Компания</th><th>Контактное лицо</th><th>Вакансии</th></tr></thead>
                    <tbody>
                    @forelse($topCompanies as $company)
                        <tr>
                            <td class="b">
                                <a href="{{ route('superadmin.companies.show', $company) }}" style="color:inherit;">{{ $company->company_name }}</a>
                            </td>
                            <td>{{ $company->user?->name ?? '—' }}</td>
                            <td class="b">{{ $company->vacancies_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="an-empty">Нет данных</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </section>
@endsection
