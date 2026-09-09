<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Отклики соискателей') }}</title>
    @include('employer.partials.css')
    <style>
        .resp-card{
            display:flex; gap:16px; align-items:flex-start;
            padding:18px 0; border-bottom:1px solid #EEF1F6;
        }
        .resp-card:last-child{border-bottom:none;}
        .resp-ava{
            width:46px; height:46px; border-radius:50%; flex-shrink:0; overflow:hidden;
            background:var(--indigo-50); color:var(--indigo-700);
            display:flex; align-items:center; justify-content:center; font-weight:700; font-size:17px;
        }
        .resp-body{flex:1; min-width:0;}
        .resp-name{font-weight:700; font-size:15.5px;}
        .resp-meta{font-size:13px; color:var(--gray-500); margin-top:3px;}
        .resp-msg{
            font-size:14px; line-height:1.6; margin-top:10px; white-space:pre-line;
            background:var(--gray-100); border-radius:10px; padding:12px 14px;
        }
        .resp-links{display:flex; flex-wrap:wrap; gap:10px; margin-top:10px;}
        .link-chip{
            font-size:12.5px; font-weight:600; padding:6px 12px; border-radius:999px;
            background:var(--white); border:1px solid var(--gray-200); color:var(--ink);
        }
        .link-chip:hover{border-color:var(--indigo-600); color:var(--indigo-700);}
        .status-tag{
            font-size:12px; font-weight:700; padding:5px 12px; border-radius:999px;
            background:var(--gray-100); color:var(--gray-500); white-space:nowrap;
        }
        .status-tag.accepted{background:#DCFCE7; color:#15803D;}
        .status-tag.rejected{background:#FEE2E2; color:#B91C1C;}
    </style>
</head>
<body>

<div class="app">

    <!-- ================= SIDEBAR ================= -->
    @include('employer.partials.sidebar')

    <!-- ================= MAIN ================= -->
    <main class="main">

        <div class="topbar">
            <div>
                <h1 id="pageTitle">{{ __('Отклики соискателей') }}</h1>
                <p id="pageSubtitle">{{ __('Кто откликнулся на ваши вакансии и когда') }}</p>
            </div>
            @include('partials.notification-bell')
            <div class="user-chip">
                <div class="avatar" style="padding: 14px 16px;">
                    @if($user->hasAvatar())
                        <img src="{{ asset($user->avatar) }}" alt="Фото профиля"
                             style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                    @else
                        <div class="avatar text-white d-flex align-items-center justify-content-center"
                             style="font-size:13px; font-weight:700; background:#1656D6;">
                            {{ $user->initials(1) }}
                        </div>
                    @endif
                </div>
                <div>
                    <div class="name">{{ $employer->user->name }}</div>
                    <div class="role">{{ $employer->user->email }}</div>
                </div>
            </div>
        </div>

        <section class="section active" id="section-responses">

            @php
                $statusLabels = [
                    'new' => 'Новый',
                    'viewed' => 'Просмотрен',
                    'accepted' => 'Принят',
                    'rejected' => 'Отклонён',
                ];
                $applicantsCount = $responses->pluck('applicant_id')->unique()->count();
                $newCount = $responses->where('status', 'new')->count();
            @endphp

            @if(session('status'))
                <div class="card" style="border-color:#BBF7D0; background:#F0FDF4; color:#15803D; font-weight:600;">
                    {{ session('status') }}
                </div>
            @endif

            @if(session('error'))
                <div class="card" style="border-color:#FECACA; background:#FEF2F2; color:#B91C1C; font-weight:600;">
                    {{ session('error') }}
                </div>
            @endif

            @if(isset($errors) && $errors->any())
                <div class="card" style="border-color:#FECACA; background:#FEF2F2; color:#B91C1C; font-weight:600;">
                    <div style="margin-bottom:6px;">{{ __('Не удалось сохранить:') }}</div>
                    <ul style="margin:0; padding-left:18px; font-weight:500;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="stat-row">
                <div class="stat-card">
                    <div class="label">{{ __('Всего откликов') }}</div>
                    <div class="value">{{ $responses->count() }}</div>
                    <div class="trend">{{ __('По всем вашим вакансиям') }}</div>
                </div>
                <div class="stat-card">
                    <div class="label">{{ __('Соискателей') }}</div>
                    <div class="value">{{ $applicantsCount }}</div>
                    <div class="trend">{{ __('Уникальных кандидатов') }}</div>
                </div>
                <div class="stat-card">
                    <div class="label">{{ __('Новые') }}</div>
                    <div class="value">{{ $newCount }}</div>
                    <div class="trend">{{ __('Ещё не обработаны') }}</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>{{ __('Список откликов') }}</h2>
                    <span class="hint">
                        {{ $responses->count() }} откликов ·
                        <a href="{{ route('employer.vacancies.index') }}">{{ __('мои вакансии') }}</a>
                    </span>
                </div>

                <div class="field" style="max-width:360px; margin-bottom:20px;">
                    <input id="responseSearch" type="text" placeholder="{{ __('Поиск по имени, вакансии, городу...') }}">
                </div>

                @forelse($responses as $response)
                    @php
                        $respApplicant = $response->applicant;
                        $vacancy = $response->vacancy;
                    @endphp
                    <div class="resp-card">
                        <div class="resp-ava">
                            @if($respApplicant?->user?->hasAvatar())
                                <img src="{{ asset($respApplicant->user->avatar) }}" alt=""
                                     style="width:100%; height:100%; object-fit:cover;">
                            @else
                                {{ $respApplicant?->user?->initials(1) ?? '?' }}
                            @endif
                        </div>

                        <div class="resp-body">
                            <div class="resp-name">{{ $respApplicant?->user?->name ?? 'Соискатель удалён' }}</div>

                            <div class="resp-meta">
                                📍 {{ $respApplicant?->city ?: '—' }} ·
                                ✉ {{ $respApplicant?->user?->email ?? '—' }} ·
                                ☎ {{ $respApplicant?->phone ?: '—' }}
                            </div>

                            <div class="resp-meta">
                                На вакансию:
                                @if($vacancy)
                                    <a href="{{ route('employer.vacancies.show', $vacancy) }}">{{ $vacancy->title }}</a>
                                @else
                                    —
                                @endif
                                · откликнулся {{ $response->created_at->format('d.m.Y H:i') }}
                            </div>

                            @if($response->message)
                                <div class="resp-msg">{{ $response->message }}</div>
                            @endif

                            <div class="resp-links">
                                @forelse($respApplicant?->resume ?? [] as $applicantResume)
                                    <a class="link-chip" href="{{ route('public.resumes.show', $applicantResume) }}">
                                        Резюме: {{ $applicantResume->profession }}
                                    </a>
                                @empty
                                    <span class="link-chip">{{ __('Резюме не опубликовано') }}</span>
                                @endforelse
                            </div>
                            @include('partials.response-actions', [
                                'response' => $response,
                                'route' => route('public.vacancy_responses.status', $response),
                            ])

                            @if($respApplicant)
                                <form action="{{ route('public.chats.start') }}" method="post" style="margin-top:12px;">
                                    @csrf
                                    <input type="hidden" name="applicant_id" value="{{ $respApplicant->id }}">
                                    @if($vacancy)
                                        <input type="hidden" name="vacancy_id" value="{{ $vacancy->id }}">
                                    @endif
                                    <button type="submit" class="btn btn-secondary">{{ __('💬 Написать') }}</button>
                                </form>
                            @endif

                            @include('partials.interview-schedule', [
                                'applicantId' => $respApplicant?->id,
                                'vacancyId' => $vacancy?->id,
                                'vacancyResponseId' => $response->id,
                            ])
                        </div>

                        <span class="status-tag {{ $response->status }}">
                            {{ $statusLabels[$response->status] ?? $response->status }}
                        </span>
                    </div>
                @empty
                    <div style="text-align:center; padding:48px 16px;">
                        <div style="font-size:15px; font-weight:600; margin-bottom:6px;">{{ __('Пока никто не откликнулся') }}</div>
                        <p style="color:var(--gray-500); font-size:14px; margin-bottom:20px;">
                            {{ __('Опубликуйте вакансию — отклики соискателей появятся здесь') }}
                        </p>
                        <a href="{{ route('employer.vacancies.create') }}" class="btn btn-primary">{{ __('+ Добавить вакансию') }}</a>
                        <a href="{{ route('public.resumes.index') }}" class="btn btn-secondary">{{ __('Каталог резюме') }}</a>
                    </div>
                @endforelse
            </div>

        </section>

    </main>
</div>

@include('employer.partials.js')

<script>
    // Поиск по карточкам откликов
    (function () {
        const input = document.getElementById('responseSearch');
        const cards = document.querySelectorAll('.resp-card');
        if (!input || !cards.length) {
            return;
        }

        input.addEventListener('input', () => {
            const q = input.value.trim().toLowerCase();
            cards.forEach(card => {
                card.style.display = card.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    })();
</script>

</body>
</html>
