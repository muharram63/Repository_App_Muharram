@extends('admin.layouts.app')

@section('main')
    <style>
        .tn-card { border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; margin: 0 3vh 18px 2vh; overflow: hidden; }
        .tn-card h2 { font-size: 16px; font-weight: 700; color: #1e293b; margin: 0; padding: 16px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }
        .tn-card .tn-body { padding: 16px; }
        .tn-card table { width: 100%; border-collapse: collapse; }
        .tn-card th { color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; padding: 10px 16px; background: #f8fafc; text-align: left; }
        .tn-card td { padding: 10px 16px; font-size: 13px; color: #334155; border-top: 1px solid #f1f5f9; vertical-align: middle; }
        .tn-tag { font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: 999px; white-space: nowrap; }
        .tn-tag.pending { background: #F1F5F9; color: #475569; }
        .tn-tag.claimed { background: #FEF3C7; color: #B45309; }
        .tn-tag.confirmed { background: #DCFCE7; color: #15803D; }
        .tn-tag.rejected { background: #FEE2E2; color: #B91C1C; }
        .tn-tag.expired { background: #F1F5F9; color: #475569; }
        .tn-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin: 0 3vh 18px 2vh; }
        .tn-stat { border: 1px solid #e2e8f0; background: #fff; border-radius: 12px; padding: 14px 16px; }
        .tn-stat b { display: block; font-size: 24px; color: #1e293b; }
        .tn-stat span { font-size: 12.5px; color: #64748b; }
        .tn-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .tn-in { font: inherit; font-size: 13px; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 8px; }
        .tn-btn { font: inherit; font-size: 13px; font-weight: 600; padding: 7px 14px; border-radius: 8px; border: 1px solid #4F46E5; background: #4F46E5; color: #fff; cursor: pointer; }
        .tn-btn.ghost { background: #fff; color: #4F46E5; }
        .tn-btn.danger { background: #fff; color: #B91C1C; border-color: #FCA5A5; }
        .tn-note { margin: 0 3vh 18px 2vh; padding: 12px 16px; border-radius: 12px; font-size: 14px; }
        .tn-note.ok { background: #DCFCE7; color: #15803D; }
        .tn-note.bad { background: #FEF2F2; color: #991B1B; }
        .tn-muted { color: #94a3b8; font-size: 12.5px; }
        .tn-filter a { font-size: 13px; padding: 5px 10px; border-radius: 999px; text-decoration: none; color: #475569; border: 1px solid #e2e8f0; }
        .tn-filter a.on { background: #4F46E5; color: #fff; border-color: #4F46E5; }
    </style>

    <section class="page" id="page-trainer" style="display:block !important; background-color:#fcfcfd; border-radius:2vh;">
        <div class="section-header mb-4">
            <h1 class="disp font-weight-bold" style="color:#1e293b; font-size:28px; margin-left:5vh; margin-top:5vh;">Тренажёр собеседований</h1>
            <div class="sub text-muted" style="font-size:.95rem; color:#64748b; margin-right:5vh;">
                Заявки на оплату, попытки, пакеты, реквизиты и направления. Ответы и разборы кандидатов здесь не показываются — их видит только сам кандидат.
            </div>
        </div>

        @if(session('status'))<div class="tn-note ok">{{ session('status') }}</div>@endif
        @if(session('error'))<div class="tn-note bad">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="tn-note bad">{{ $errors->first() }}</div>@endif

        {{-- ==================== статистика ==================== --}}
        <div class="tn-stats">
            <div class="tn-stat"><b>{{ $stats['sessions'] }}</b><span>тренировок всего · сегодня {{ $stats['sessions_today'] }}</span></div>
            <div class="tn-stat"><b>{{ $stats['finished'] }}</b><span>завершено · возвращено {{ $stats['refunded'] }}</span></div>
            <div class="tn-stat"><b>{{ $stats['avg_score'] }}</b><span>средний балл</span></div>
            <div class="tn-stat"><b>{{ $stats['trainees'] }}</b><span>кандидатов тренировалось</span></div>
            <div class="tn-stat"><b>{{ $stats['paid'] }}</b><span>оплат подтверждено</span></div>
            <div class="tn-stat"><b>{{ number_format($stats['revenue'], 0, ',', ' ') }}</b><span>сомони дохода · за месяц {{ number_format($stats['revenue_month'], 0, ',', ' ') }}</span></div>
            <div class="tn-stat"><b>{{ $stats['claimed'] }}</b><span>«Я оплатил» ждут проверки · открыто {{ $stats['open'] }}</span></div>
        </div>

        {{-- ==================== заявки на оплату ==================== --}}
        <div class="tn-card" id="payments">
            <h2>
                Заявки на оплату
                <span class="tn-filter tn-row">
                    @foreach(['open' => 'Открытые', 'confirmed' => 'Подтверждённые', 'rejected' => 'Отклонённые', 'all' => 'Все'] as $key => $label)
                        <a href="{{ route('superadmin.trainer', ['payments' => $key]) }}#payments" class="{{ $filter === $key ? 'on' : '' }}">{{ $label }}</a>
                    @endforeach
                </span>
            </h2>
            @if($payments->isEmpty())
                <div class="tn-body tn-muted">Заявок нет.</div>
            @else
                <table>
                    <thead><tr><th>Код</th><th>Кандидат</th><th>Пакет</th><th>Сумма</th><th>Статус</th><th>Действие</th></tr></thead>
                    <tbody>
                    @foreach($payments as $payment)
                        <tr>
                            <td><b>{{ $payment->reference() }}</b><div class="tn-muted">{{ $payment->created_at->format('d.m.Y H:i') }}</div></td>
                            <td>{{ $payment->user?->name ?? '—' }}<div class="tn-muted">{{ $payment->user?->email }}</div></td>
                            <td>{{ $payment->package_title }}<div class="tn-muted">{{ $payment->attempts }} поп.</div></td>
                            <td><b>{{ number_format($payment->amount, 0, ',', ' ') }}</b> сомони
                                @if($payment->payer_note)<div class="tn-muted">платил с: {{ $payment->payer_note }}</div>@endif
                            </td>
                            <td><span class="tn-tag {{ $payment->status }}">{{ $payment->statusLabel() }}</span>
                                @if($payment->reject_reason)<div class="tn-muted">{{ $payment->reject_reason }}</div>@endif
                            </td>
                            <td>
                                @if($payment->isOpen())
                                    <div class="tn-row">
                                        <form method="POST" action="{{ route('superadmin.trainer.confirm', $payment) }}"
                                              onsubmit="return confirm('Подтвердить оплату {{ $payment->reference() }} и начислить {{ $payment->attempts }} попыток?')">
                                            @csrf
                                            <button class="tn-btn" type="submit">✓ Подтвердить</button>
                                        </form>
                                        <form method="POST" action="{{ route('superadmin.trainer.reject', $payment) }}" class="tn-row">
                                            @csrf
                                            <input class="tn-in" name="reason" placeholder="Причина отказа" required minlength="3" maxlength="255">
                                            <button class="tn-btn danger" type="submit">Отклонить</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="tn-muted">{{ $payment->decided_at?->format('d.m.Y H:i') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- ==================== начислить вручную ==================== --}}
        <div class="tn-card">
            <h2>Начислить попытки вручную</h2>
            <form method="POST" action="{{ route('superadmin.trainer.grant') }}" class="tn-body tn-row">
                @csrf
                <input class="tn-in" type="email" name="email" placeholder="Email кандидата" required value="{{ old('email') }}">
                <input class="tn-in" type="number" name="attempts" min="1" max="100" value="1" style="width:90px" required>
                <input class="tn-in" name="note" placeholder="Зачем (например: оплата наличными)" maxlength="255" style="min-width:260px">
                <button class="tn-btn" type="submit">Начислить</button>
            </form>
        </div>

        {{-- ==================== тренировки ==================== --}}
        {{-- ответы и разборы здесь не показываются: только статус, балл и возврат --}}
        <div class="tn-card" id="sessions">
            <h2>Тренировки <span class="tn-muted">последние 30</span></h2>
            @if($sessions->isEmpty())
                <div class="tn-body tn-muted">Тренировок пока не было.</div>
            @else
                <table>
                    <tr><th>Когда</th><th>Кандидат</th><th>Направление</th><th>Статус</th><th>Балл</th><th></th></tr>
                    @foreach($sessions as $s)
                        <tr>
                            <td>{{ $s->created_at->format('d.m.Y H:i') }}</td>
                            <td>{{ $s->user?->email ?? '—' }}@if($s->demo) <span class="tn-tag pending">демо</span>@endif</td>
                            <td>{{ $s->track_title }} · {{ $s->levelLabel() }}</td>
                            <td><span class="tn-tag {{ $s->isRefunded() ? 'rejected' : ($s->isFinished() ? 'confirmed' : 'claimed') }}">{{ $s->statusLabel() }}</span></td>
                            <td>{{ $s->score ?? '—' }}</td>
                            <td>
                                @unless($s->isRefunded())
                                    <form method="POST" action="{{ route('superadmin.trainer.sessions.refund', $s) }}" class="tn-row"
                                          onsubmit="return confirm('Вернуть кандидату попытку за эту тренировку?')">
                                        @csrf
                                        <input class="tn-in" name="note" placeholder="Причина" required maxlength="255" style="width:170px">
                                        <button class="tn-btn danger" type="submit">Вернуть попытку</button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>

        {{-- ==================== направления ==================== --}}
        @includeWhen(Route::has('superadmin.trainer.tracks.store'), 'admin.pages.trainer._tracks')

        {{-- ==================== пакеты ==================== --}}
        <div class="tn-card">
            <h2>Пакеты попыток</h2>
            <table>
                <thead><tr><th>Название</th><th>Попыток</th><th>Цена, сомони</th><th>Порядок</th><th>Продаётся</th><th></th></tr></thead>
                <tbody>
                {{-- форма в <tr> браузер выносит из таблицы — поля привязаны к форме атрибутом form --}}
                @foreach($packages as $package)
                    @php($fid = 'pkg-'.$package->id)
                    <tr>
                        <td><input class="tn-in" form="{{ $fid }}" name="title" value="{{ $package->title }}" required maxlength="120"></td>
                        <td><input class="tn-in" form="{{ $fid }}" type="number" name="attempts" value="{{ $package->attempts }}" min="1" max="100" style="width:80px" required></td>
                        <td><input class="tn-in" form="{{ $fid }}" type="number" name="price" value="{{ $package->price }}" min="0" style="width:110px" required></td>
                        <td><input class="tn-in" form="{{ $fid }}" type="number" name="position" value="{{ $package->position }}" min="0" style="width:70px"></td>
                        <td><input form="{{ $fid }}" type="checkbox" name="active" value="1" @checked($package->active)></td>
                        <td>
                            <form id="{{ $fid }}" method="POST" action="{{ route('superadmin.trainer.packages.update', $package) }}">@csrf</form>
                            <button class="tn-btn ghost" type="submit" form="{{ $fid }}">Сохранить</button>
                        </td>
                    </tr>
                @endforeach
                <tr>
                    <td><input class="tn-in" form="pkg-new" name="title" placeholder="Новый пакет" required maxlength="120"></td>
                    <td><input class="tn-in" form="pkg-new" type="number" name="attempts" value="3" min="1" max="100" style="width:80px" required></td>
                    <td><input class="tn-in" form="pkg-new" type="number" name="price" value="30" min="0" style="width:110px" required></td>
                    <td><input class="tn-in" form="pkg-new" type="number" name="position" value="0" min="0" style="width:70px"></td>
                    <td><input form="pkg-new" type="checkbox" name="active" value="1" checked></td>
                    <td>
                        <form id="pkg-new" method="POST" action="{{ route('superadmin.trainer.packages.store') }}">@csrf</form>
                        <button class="tn-btn" type="submit" form="pkg-new">Добавить</button>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        {{-- ==================== реквизиты ==================== --}}
        <div class="tn-card">
            <h2>Реквизиты для оплаты</h2>
            <form method="POST" action="{{ route('superadmin.trainer.settings') }}" class="tn-body" style="display:grid; gap:10px; max-width:640px">
                @csrf
                <label class="tn-muted">Номер кошелька или карты
                    <input class="tn-in" name="wallet" value="{{ $settings['wallet'] }}" maxlength="120" style="width:100%">
                </label>
                <label class="tn-muted">Получатель
                    <input class="tn-in" name="recipient" value="{{ $settings['recipient'] }}" maxlength="120" style="width:100%">
                </label>
                <label class="tn-muted">Как оплатить (видит кандидат)
                    <textarea class="tn-in" name="instructions" rows="3" maxlength="2000" style="width:100%">{{ $settings['instructions'] }}</textarea>
                </label>
                <div><button class="tn-btn" type="submit">Сохранить реквизиты</button></div>
            </form>
        </div>

        {{-- ==================== журнал попыток ==================== --}}
        <div class="tn-card">
            <h2>Журнал попыток <span class="tn-muted">последние 30</span></h2>
            @if($ledger->isEmpty())
                <div class="tn-body tn-muted">Записей пока нет.</div>
            @else
                <table>
                    <thead><tr><th>Когда</th><th>Кандидат</th><th>Изменение</th><th>Причина</th></tr></thead>
                    <tbody>
                    @foreach($ledger as $entry)
                        <tr>
                            <td>{{ $entry->created_at?->format('d.m.Y H:i') }}</td>
                            <td>{{ $entry->user?->email ?? '—' }}</td>
                            <td><b style="color:{{ $entry->delta > 0 ? '#15803D' : '#B91C1C' }}">{{ $entry->delta > 0 ? '+' : '' }}{{ $entry->delta }}</b></td>
                            <td>{{ $entry->reasonLabel() }}@if($entry->note) <span class="tn-muted">· {{ $entry->note }}</span>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>
@endsection
