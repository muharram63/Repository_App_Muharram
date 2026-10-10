<!DOCTYPE html>
<html lang="ru" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Мои вакансии') }}</title>
    @include('employer.partials.css')
</head>
<body>

<div class="app">

    <!-- ================= SIDEBAR ================= -->
    @include('employer.partials.sidebar')

    <!-- ================= MAIN ================= -->
    <main class="main">

        <div class="topbar">
            <div>
                <h1 id="pageTitle">{{ __('Мои вакансии7') }}</h1>
                <p id="pageSubtitle">{{ __('Управляйте вакансиями компании и просматривайте отклики') }}</p>
            </div>
            @include('partials.notification-bell')
            <div class="user-chip">
                <div class="avatar" style="padding: 14px 16px;">

                    <div class="avatar text-white d-flex align-items-center justify-content-center"
                         style="font-size:13px; font-weight:700; background:#1656D6;">
                        {{ $user->initials(1) }}
                    </div>

                </div>
                <div>
                    <div class="name">{{ $employer->user->name }}</div>
                    <div class="role">{{ $employer->user->email }}</div>
                </div>
            </div>
        </div>

        <!-- ---------- SECTION: VACANCIES ---------- -->
        <section class="section active" id="section-vacancies">

            {{--
                Вакансия только что создана — предлагаем включить ИИ-собеседование.
                Сами не включаем: решение за работодателем. Без ИИ кандидат после
                отклика просто ждёт ответа; с ним — сразу проходит отбор.
            --}}
            @if($aiOffer ?? null)
                <div class="card" id="aiOffer" style="border:1px solid #C7D2FE; background:var(--indigo-50,#EEF0FF); margin-bottom:16px">
                    <div style="display:flex; flex-wrap:wrap; gap:14px; align-items:center; justify-content:space-between">
                        <div style="flex:1; min-width:240px">
                            <div style="font-weight:700; font-size:15px; margin-bottom:4px">
                                🤖 {{ __('Вакансия «:title» создана. Включить для неё ИИ-собеседование?', ['title' => $aiOffer->title]) }}
                            </div>
                            <div class="hint">
                                {{ __('ИИ сам проведёт первичный отбор: проверит документы, поговорит с кандидатами голосом, даст тестовое задание и подготовит для вас отчёт. Без него кандидаты после отклика просто ждут вашего ответа.') }}
                            </div>
                        </div>
                        <div style="display:flex; gap:8px; flex-wrap:wrap">
                            <a class="btn btn-primary" href="{{ route('employer.ai.show', $aiOffer) }}">{{ __('Настроить ИИ-собеседование') }}</a>
                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('aiOffer').remove()">{{ __('Не сейчас') }}</button>
                        </div>
                    </div>
                </div>
            @endif

            <div class="stat-row">
                <div class="stat-card">
                    <div class="label">{{ __('Всего вакансий') }}</div>
                    <div class="value">{{ $vacancies->count() }}</div>
                    <div class="trend">{{ __('За всё время') }}</div>
                </div>
                <div class="stat-card">
                    <div class="label">{{ __('Активные') }}</div>
                    <div class="value">{{ $vacancies->where('status', 'active')->count() }}</div>
                    <div class="trend">{{ __('Видны соискателям') }}</div>
                </div>
                <div class="stat-card">
                    <div class="label">{{ __('Отклики') }}</div>
                    <div class="value">{{ $vacancies->sum('responses_count') ?? 0 }}</div>
                    <div class="trend">{{ __('По всем вакансиям') }}</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>{{ __('Список вакансий') }}</h2>
                    <span class="hint">{{ $vacancies->count() }} вакансий</span>
                </div>

                <div class="field" style="max-width:360px; margin-bottom:20px;">
                    <input id="vacancySearch" type="text" placeholder="{{ __('Поиск по названию...') }}">
                </div>
                <a href="{{route('employer.vacancies.create')}}" class="btn btn-primary" style="margin-left: 63vh;position:relative;bottom: 9.5vh; ">{{ __('Добавить Вакансию') }}</a>

                @forelse($vacancies as $vacancy)
                    <div class="vacancy-card"
                         style="display:flex; align-items:center; justify-content:space-between; padding:16px 0; border-bottom:1px solid #EEF1F6;position:relative;bottom: 3.7vh;">

                        <div style="display:flex; align-items:center; gap:14px;">
                            <div class="company-logo">📄</div>
                            <div>
                                <div style="font-weight:600;">
                                    <a href="{{ route('employer.vacancies.show', $vacancy) }}"
                                       style="color:inherit; text-decoration:none;">
                                        {{ $vacancy->title }}
                                    </a>
                                </div>
                                <div class="hint" style="display:flex; gap:12px; margin-top:4px;">
                                    <span>📍 {{ __($vacancy->city->country) }} , {{ __($vacancy->city->region) }}</span>| <span>👥 {{ $vacancy->responses_count ?? 0 }} {{ __('откликов') }}</span>| <span>👁 {{ $vacancy->views }} {{ __('просмотров') }}</span>|
                                    <span>{{ $vacancy->created_at->format('d M Y') }}</span>
                                </div>
                                {{-- активная вакансия без работающего ИИ-собеседования — предлагаем включить --}}
                                @if($vacancy->status === 'active' && ! in_array($vacancy->id, $aiReady ?? [], true))
                                    <div class="hint" style="margin-top:6px">
                                        🤖 {{ __('ИИ-собеседование не включено.') }}
                                        <a href="{{ route('employer.ai.show', $vacancy) }}" style="font-weight:600">{{ __('Включить') }}</a>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div style="display:flex; align-items:center; gap:10px;">
                            @switch($vacancy->status)
                                @case('active')
                                    <span class="badge badge-active" style="text-align: center;">{{ __('✅ Активна') }}</span>
                                    @break
                                @case('inactive')
                                    <span class="badge badge-pending" style="text-align: center;">{{ __('⏸ Неактивна') }}</span>
                                    @break
                                @case('closed')
                                    <span class="badge badge-pending"
                                          style="background:#FDECEC; color:#E14A4A;text-align: center;">{{ __('✖ Закрыта') }}</span>
                                    @break
                                @case('in_archived')
                                    <span class="badge badge-pending"
                                          style="background:#F0F1F3; color:#6B7280;text-align: center;">🗄<br>{{ __('Архирован') }}</span>
                                    @break
                            @endswitch

                            <a href="{{ route('employer.vacancies.edit', $vacancy) }}" class="btn btn-secondary"
                               style="border-color: #ffc107;">{{ __('Изменить') }}</a>

                            <form action="{{ route('employer.vacancies.destroy', $vacancy) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-secondary" style="color:#E14A4A;">{{ __('Удалить') }}</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div style="text-align:center; padding:48px 16px;">
                        <div style="font-size:15px; font-weight:600; margin-bottom:6px;">{{ __('Пока нет вакансий') }}</div>
                        <p style="color:var(--gray-500); font-size:14px; margin-bottom:20px;">
                            {{ __('Создайте первую вакансию, чтобы начать получать отклики') }}
                        </p>
                        <a href="{{ route('employer.vacancies.create') }}" class="btn btn-primary">{{ __('+ Добавить вакансию') }}</a>
                    </div>
                @endforelse

                @if(method_exists($vacancies, 'links'))
                    <div style="margin-top:20px;">
                        {{ $vacancies->links() }}
                    </div>
                @endif
            </div>
        </section>

    </main>
</div>

@include('employer.partials.js')

<script>
    // Простой клиентский поиск по названию (можно заменить на серверный)
    (function () {
        const input = document.getElementById('vacancySearch');
        const cards = document.querySelectorAll('.vacancy-card');
        if (input) {
            input.addEventListener('input', () => {
                const q = input.value.toLowerCase();
                cards.forEach(card => {
                    const title = card.textContent.toLowerCase();
                    card.style.display = title.includes(q) ? '' : 'none';
                });
            });
        }
    })();
</script>

</body>
</html>
