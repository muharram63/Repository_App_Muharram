@extends('admin.layouts.app')

@section('main')
    <style>
        .ai-card { border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; margin: 0 3vh 18px 2vh; overflow: hidden; }
        .ai-card h2 { font-size: 16px; font-weight: 700; color: #1e293b; margin: 0; padding: 16px; border-bottom: 1px solid #f1f5f9; }
        .ai-card th { color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; padding: 12px 16px; background: #f8fafc; }
        .ai-card td { padding: 12px 16px; font-size: 13px; color: #334155; border-top: 1px solid #f1f5f9; vertical-align: middle; }
        .ai-tag { font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: 999px; white-space: nowrap; }
        .ai-tag.ok { background: #DCFCE7; color: #15803D; }
        .ai-tag.bad { background: #FEE2E2; color: #B91C1C; }
        .ai-tag.wait { background: #FEF3C7; color: #B45309; }
        .ai-tag.muted { background: #F1F5F9; color: #475569; }
        .ai-alert { margin: 0 3vh 18px 2vh; padding: 14px 16px; border-radius: 12px; background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; font-size: 14px; }
        .ai-alert code { background: #fff; padding: 1px 6px; border-radius: 4px; }
        .ai-empty { text-align: center; color: #94a3b8; padding: 24px 16px; font-weight: 500; }
        .ai-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 18px; margin: 0 3vh 0 2vh; }
        .ai-grid .ai-card { margin: 0 0 18px; }
        @media (max-width: 900px) { .ai-grid { grid-template-columns: 1fr; } }
    </style>

    <section class="page" id="page-ai" style="display: block !important; background-color: #fcfcfd; border-radius: 2vh;">
        <div class="section-header mb-4">
            <h1 class="disp font-weight-bold" style="color: #1e293b; font-size: 28px; margin-left: 5vh; margin-top: 5vh;">ИИ-собеседования</h1>
            <div class="sub text-muted" style="font-size: .95rem; color: #64748b; margin-right: 5vh;">
                Работает ли модуль, не застряли ли кандидаты и сколько уходит квоты модели.
                Ответы, документы и оценки кандидатов здесь не показываются — они доступны только работодателю вакансии.
            </div>
        </div>

        {{-- Самая частая причина «ИИ не отвечает» — не запущен обработчик очереди. --}}
        @if($queue['stale'])
            <div class="ai-alert">
                <b>Очередь не разбирается.</b> В ней {{ $queue['pending'] }} задач, старейшая ждёт дольше пяти минут.
                Запустите планировщик (<code>php artisan schedule:work</code>, на сервере — <code>schedule:run</code> раз в минуту)
                или обработчик <code>php artisan queue:work</code>. Подробнее — в README.
            </div>
        @endif

        <div class="kpi-row" style="margin-left: 2vh; margin-right: 3vh;">
            <div class="card kpi">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Всего собеседований</div>
                        <div class="kpi-value">{{ $counts['all'] }}</div>
                    </div>
                    <div class="kpi-icon"><i class="fa-solid fa-robot"></i></div>
                </div>
                <div class="kpi-delta"><span>{{ $counts['running'] }} идут · {{ $counts['passed'] }} прошли · {{ $counts['rejected'] }} отказ</span></div>
            </div>
            <div class="card kpi">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Требуют внимания</div>
                        <div class="kpi-value">{{ $stuck }}</div>
                    </div>
                    <div class="kpi-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                </div>
                <div class="kpi-delta {{ $stuck ? 'down' : 'up' }}"><span>{{ $counts['manual'] }} ждут ручной проверки работодателем</span></div>
            </div>
            <div class="card kpi">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Очередь</div>
                        <div class="kpi-value">{{ $queue['pending'] ?? '—' }}</div>
                    </div>
                    <div class="kpi-icon"><i class="fa-solid fa-list-check"></i></div>
                </div>
                <div class="kpi-delta {{ $queue['failed'] ? 'down' : '' }}"><span>драйвер {{ $queue['driver'] }} · упавших {{ $queue['failed'] ?? '—' }}</span></div>
            </div>
            <div class="card kpi">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Обращений к модели</div>
                        <div class="kpi-value">{{ number_format($usage['calls'], 0, ',', ' ') }}</div>
                    </div>
                    <div class="kpi-icon"><i class="fa-solid fa-microchip"></i></div>
                </div>
                <div class="kpi-delta {{ $usage['failed'] ? 'down' : '' }}">
                    <span>за {{ $days }} дн. · {{ number_format($usage['tokens'], 0, ',', ' ') }} токенов · сбоев {{ $usage['failed'] }} · ~{{ number_format($usage['latency'] / 1000, 1, ',', '') }} с</span>
                </div>
            </div>
        </div>

        <div class="ai-card">
            <h2>Последние собеседования</h2>
            <table class="table mb-0" style="width:100%">
                <thead>
                <tr><th>Кандидат</th><th>Вакансия</th><th>Стадия</th><th>Итог</th><th>Обновлено</th><th>Состояние</th></tr>
                </thead>
                <tbody>
                @forelse($interviews as $interview)
                    @php($trouble = $problem($interview))
                    <tr>
                        <td>{{ $interview->applicant?->user?->name ?? 'Соискатель удалён' }}</td>
                        <td>
                            @if($interview->vacancy)
                                <a href="{{ route('superadmin.vacancy.show', $interview->vacancy) }}" style="color:inherit; font-weight:600;">{{ $interview->vacancy->title }}</a>
                                <div style="color:#94a3b8; font-size:12px;">{{ $interview->vacancy->employer?->company_name }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $interview->stageLabel() }}</td>
                        <td>
                            @if($interview->outcome)
                                <span class="ai-tag {{ $interview->outcome === 'passed' ? 'ok' : ($interview->outcome === 'rejected' ? 'bad' : 'wait') }}">{{ $interview->outcomeLabel() }}</span>
                            @elseif($interview->requires_review)
                                <span class="ai-tag wait">Ручная проверка</span>
                            @else
                                <span class="ai-tag muted">Идёт</span>
                            @endif
                        </td>
                        <td style="white-space:nowrap">{{ $interview->updated_at?->format('d.m.Y H:i') }}</td>
                        <td>
                            @if($trouble)
                                <span class="ai-tag bad">{{ $trouble }}</span>
                            @elseif($quiet = $idle($interview))
                                <span class="ai-tag muted" title="Кандидат не продолжает собеседование">{{ $quiet }}</span>
                            @else
                                <span class="ai-tag ok">В порядке</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="ai-empty">ИИ-собеседований пока не было</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="ai-grid">
            <div class="ai-card">
                <h2>Обращения к модели по задачам · {{ $days }} дн.</h2>
                <table class="table mb-0" style="width:100%">
                    <thead><tr><th>Задача</th><th>Вызовов</th><th>Сбоев</th></tr></thead>
                    <tbody>
                    @forelse($byPurpose as $row)
                        <tr>
                            <td>{{ $purposes[$row->purpose] ?? $row->purpose }}</td>
                            <td>{{ $row->calls }}</td>
                            <td>@if($row->failed)<span class="ai-tag bad">{{ $row->failed }}</span>@else 0 @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="ai-empty">Обращений не было</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="ai-card">
                <h2>Модели · {{ $days }} дн.</h2>
                <table class="table mb-0" style="width:100%">
                    <thead><tr><th>Модель</th><th>Вызовов</th><th>Среднее время</th></tr></thead>
                    <tbody>
                    @forelse($byModel as $row)
                        <tr>
                            <td><code>{{ $row->model }}</code></td>
                            <td>{{ $row->calls }}</td>
                            <td>{{ number_format(((float) $row->latency) / 1000, 1, ',', '') }} с</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="ai-empty">Обращений не было</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="ai-card">
            <h2>Попытки повлиять на оценку</h2>
            {{-- Сам текст кандидата не показываем: это его ответ, он принадлежит отчёту работодателя. --}}
            <table class="table mb-0" style="width:100%">
                <thead><tr><th>Когда</th><th>Вакансия</th><th>Правило</th><th>Что сделано</th></tr></thead>
                <tbody>
                @forelse($injections as $attempt)
                    <tr>
                        <td style="white-space:nowrap">{{ $attempt->created_at?->format('d.m.Y H:i') }}</td>
                        <td>{{ $attempt->interview?->vacancy?->title ?? '—' }}</td>
                        <td><code>{{ $attempt->pattern ?? '—' }}</code></td>
                        <td>{{ $attempt->action === 'ignored' ? 'Оценка не изменилась' : $attempt->action }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="ai-empty">Попыток не было</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
