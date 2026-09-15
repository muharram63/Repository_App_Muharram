
@extends('admin.layouts.app')

@section('main')
    <style>
        .filter-radio { display: none; }
        .filters { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; align-items: center; }
        .pill {
            padding: 9px 18px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            font-weight: 500;
            color: #64748b;
            font-size: 14px;
        }
        .pill:hover { background: #f8fafc; border-color: #cbd5e1; color: #334155; }
        .pill.on {
            background: #1656D6;
            color: #fff;
            border-color: #1656D6;
            box-shadow: 0 4px 12px rgba(22, 86, 214, 0.15);
        }
        .filter-label {
            font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;
            color: #94a3b8; font-weight: 700; margin-right: 4px;
        }
        .search-wrap { position: relative; flex: 1 1 260px; max-width: 340px; margin-left: auto; margin-right: 3vh; }
        .search-wrap input {
            width: 100%;
            padding: 9px 14px 9px 36px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            color: #334155;
            outline: none;
            box-sizing: border-box;
        }
        .search-wrap input:focus { border-color: #1656D6; box-shadow: 0 0 0 3px rgba(22, 86, 214, 0.12); }
        .search-wrap svg {
            position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
            width: 16px; height: 16px; stroke: #94a3b8; pointer-events: none;
        }
        .type-tag {
            font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: 999px; white-space: nowrap;
        }
        .type-tag.vacancy { background: #EAF2FF; color: #1656D6; }
        .type-tag.resume { background: #F5EEFF; color: #6D28D9; }
        .status-tag {
            font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: 999px; white-space: nowrap;
        }
        .status-tag.new { background: #FEF3C7; color: #B45309; }
        .status-tag.viewed { background: #F1F5F9; color: #475569; }
        .status-tag.accepted { background: #DCFCE7; color: #15803D; }
        .status-tag.rejected { background: #FEE2E2; color: #B91C1C; }
        .msg-cell { max-width: 320px; color: #475569; font-size: 13px; line-height: 1.5; }
    </style>

    @php
        $statusLabels = [
            'new' => __('Новый'),
            'viewed' => __('Просмотрен'),
            'accepted' => __('Принят'),
            'rejected' => __('Отклонён'),
        ];
        $newCount = $rows->where('status', 'new')->count();
        $acceptedCount = $rows->where('status', 'accepted')->count();
        $rejectedCount = $rows->where('status', 'rejected')->count();
    @endphp

    <section class="page" id="page-responses" style="display: block !important; background-color: #fcfcfd;border-radius: 2vh;">
        <div class="section-header mb-4">
            <h1 class="disp font-weight-bold" style="color: #1e293b; font-size: 28px;margin-left: 5vh;margin-top: 5vh;">Отклики</h1>
            <div class="sub text-muted" style="font-size: 0.95rem; color: #64748b;margin-right: 5vh;">Все отклики соискателей и приглашения работодателей</div>
        </div>

        <div class="kpi-row" style="margin-left: 2vh; margin-right: 3vh;">
            <div class="card kpi">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Всего откликов</div>
                        <div class="kpi-value">{{ $rows->count() }}</div>
                    </div>
                    <div class="kpi-icon"><i class="fa-solid fa-inbox"></i></div>
                </div>
                <div class="kpi-delta"><span>{{ $vacancyCount }} на вакансии · {{ $resumeCount }} на резюме</span></div>
            </div>
            <div class="card kpi">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Новые</div>
                        <div class="kpi-value">{{ $newCount }}</div>
                    </div>
                    <div class="kpi-icon"><i class="fa-solid fa-bell"></i></div>
                </div>
                <div class="kpi-delta"><span>Ещё не обработаны</span></div>
            </div>
            <div class="card kpi">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Принятые</div>
                        <div class="kpi-value">{{ $acceptedCount }}</div>
                    </div>
                    <div class="kpi-icon"><i class="fa-solid fa-check"></i></div>
                </div>
                <div class="kpi-delta up"><span>Успешные контакты</span></div>
            </div>
            <div class="card kpi">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Отклонённые</div>
                        <div class="kpi-value">{{ $rejectedCount }}</div>
                    </div>
                    <div class="kpi-icon"><i class="fa-solid fa-xmark"></i></div>
                </div>
                <div class="kpi-delta down"><span>Отказы</span></div>
            </div>
        </div>

        <div class="filters" style="margin-left: 2vh;">
            <span class="filter-label">Тип</span>
            <span class="pill on" data-type="all">Все</span>
            <span class="pill" data-type="vacancy">На вакансии</span>
            <span class="pill" data-type="resume">На резюме</span>

            <div class="search-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="response-search" placeholder="Поиск по имени, компании, вакансии...">
            </div>
        </div>

        <div class="filters" style="margin-left: 2vh;">
            <span class="filter-label">Статус</span>
            <span class="pill on" data-status="all">Все</span>
            <span class="pill" data-status="new">Новые</span>
            <span class="pill" data-status="viewed">Просмотренные</span>
            <span class="pill" data-status="accepted">Принятые</span>
            <span class="pill" data-status="rejected">Отклонённые</span>
        </div>

        <div class="card" style="border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02); overflow: hidden; background: #fff;margin-left: 2vh;margin-right: 3vh;">
            <div class="card-body" style="padding:0;">
                <table class="table table-hover align-middle mb-0" id="responses-table" style="vertical-align: middle;">
                    <thead style="background-color: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                    <tr>
                        <th style="padding: 16px 16px; color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Тип</th>
                        <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Кто откликнулся</th>
                        <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">На что</th>
                        <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Сообщение</th>
                        <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Дата</th>
                        <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Статус</th>
                    </tr>
                    </thead>
                    <tbody style="border-top: none;">
                    @forelse($rows as $row)
                        @php
                            $searchBlob = mb_strtolower(
                                $row['from_name'].' '.
                                $row['from_email'].' '.
                                $row['target_title'].' '.
                                $row['target_sub'].' '.
                                $row['message']
                            );
                        @endphp
                        <tr style="border-bottom: 1px solid #f1f5f9;"
                            data-type="{{ $row['type'] }}"
                            data-status="{{ $row['status'] }}"
                            data-search="{{ $searchBlob }}">
                            <td><span class="type-tag {{ $row['type'] }}">{{ $row['type_label'] }}</span></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    @if($row['from_avatar'])
                                        <img src="{{ asset($row['from_avatar']) }}" alt=""
                                             style="width:36px; height:36px; border-radius:50%; object-fit:cover;">
                                    @else
                                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white"
                                             style="width:36px; height:36px; font-size:12px; font-weight:700; background:#1656D6;">
                                            {{ mb_substr($row['from_name'], 0, 1) }}
                                        </div>
                                    @endif
                                    <div>
                                        <div style="font-weight:600; color:#1e293b;">{{ $row['from_name'] }}</div>
                                        <div style="color:#94a3b8; font-size:12px;">{{ $row['from_role'] }} · {{ $row['from_email'] ?: '—' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight:600; color:#1e293b;">
                                    @if($row['target_link'])
                                        <a href="{{ $row['target_link'] }}" style="color:inherit;">{{ $row['target_title'] }}</a>
                                    @else
                                        {{ $row['target_title'] }}
                                    @endif
                                </div>
                                <div style="color:#94a3b8; font-size:12px;">{{ $row['target_sub'] ?: '—' }}</div>
                            </td>
                            <td class="msg-cell">{{ $row['message'] ? \Illuminate\Support\Str::limit($row['message'], 140) : '—' }}</td>
                            <td style="color:#475569; font-size:13px; white-space:nowrap;">{{ $row['created_at']->format('d.m.Y H:i') }}</td>
                            <td><span class="status-tag {{ $row['status'] }}">{{ $statusLabels[$row['status']] ?? $row['status'] }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center; color:#94a3b8; padding:28px 16px; font-weight:500;">
                                Откликов пока нет
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
                <div id="no-results-row" style="display:none; padding: 24px 16px; text-align:center; color:#94a3b8; font-weight:500;">
                    Ничего не найдено
                </div>
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const rows = document.querySelectorAll('#responses-table tbody tr[data-type]');
            const typePills = document.querySelectorAll('.pill[data-type]');
            const statusPills = document.querySelectorAll('.pill[data-status]');
            const searchInput = document.getElementById('response-search');
            const noResults = document.getElementById('no-results-row');

            let activeType = 'all';
            let activeStatus = 'all';

            function applyFilter() {
                const query = searchInput.value.trim().toLowerCase();
                let visibleCount = 0;

                rows.forEach(function (row) {
                    const matchesType = activeType === 'all' || row.dataset.type === activeType;
                    const matchesStatus = activeStatus === 'all' || row.dataset.status === activeStatus;
                    const matchesSearch = query === '' || (row.dataset.search || '').includes(query);

                    if (matchesType && matchesStatus && matchesSearch) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                noResults.style.display = (rows.length && visibleCount === 0) ? '' : 'none';
            }

            typePills.forEach(function (pill) {
                pill.addEventListener('click', function () {
                    typePills.forEach(p => p.classList.remove('on'));
                    pill.classList.add('on');
                    activeType = pill.dataset.type;
                    applyFilter();
                });
            });

            statusPills.forEach(function (pill) {
                pill.addEventListener('click', function () {
                    statusPills.forEach(p => p.classList.remove('on'));
                    pill.classList.add('on');
                    activeStatus = pill.dataset.status;
                    applyFilter();
                });
            });

            let debounceTimer;
            searchInput.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(applyFilter, 120);
            });

            applyFilter();
        });
    </script>
@endsection
