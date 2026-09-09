<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Отклики компаний') }}</title>
    @include('applicant.partials.css')
    <style>
        .company-card{
            display:flex; gap:16px; align-items:flex-start;
            padding:18px 0; border-bottom:1px solid #EEF1F6;
        }
        .company-card:last-child{border-bottom:none;}
        .company-mark{
            width:46px; height:46px; border-radius:12px; flex-shrink:0; overflow:hidden;
            background:var(--indigo-50); color:var(--indigo-700);
            display:flex; align-items:center; justify-content:center; font-weight:700; font-size:17px;
        }
        .company-body{flex:1; min-width:0;}
        .company-name{font-weight:700; font-size:15.5px;}
        .company-meta{font-size:13px; color:var(--gray-500); margin-top:3px;}
        .company-msg{
            font-size:14px; line-height:1.6; margin-top:10px; white-space:pre-line;
            background:var(--gray-100); border-radius:10px; padding:12px 14px;
        }
        .company-contacts{display:flex; flex-wrap:wrap; gap:10px; margin-top:10px;}
        .contact-chip{
            font-size:12.5px; font-weight:600; padding:6px 12px; border-radius:999px;
            background:var(--white); border:1px solid var(--gray-200); color:var(--ink);
        }
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
    @include('applicant.partials.sidebar')

    <!-- ================= MAIN ================= -->
    <main class="main">

        <div class="topbar">
            <div>
                <h1 id="pageTitle">{{ __('Отклики компаний') }}</h1>
                <p id="pageSubtitle">{{ __('Работодатели, которые заинтересовались вашими резюме') }}</p>
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
                    <div class="name">{{ $user->name }}</div>
                    <div class="role">{{ $user->email }}</div>
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
                $companiesCount = $responses->pluck('employer_id')->unique()->count();
                $newCount = $responses->where('status', 'new')->count();
            @endphp

            @if(session('status'))
                <div class="card" style="border-color:#BBF7D0; background:#F0FDF4; color:#15803D; font-weight:600;">
                    {{ session('status') }}
                </div>
            @endif

            <div class="stat-row">
                <div class="stat-card">
                    <div class="label">{{ __('Всего откликов') }}</div>
                    <div class="value">{{ $responses->count() }}</div>
                    <div class="trend">{{ __('По всем вашим резюме') }}</div>
                </div>
                <div class="stat-card">
                    <div class="label">{{ __('Компаний') }}</div>
                    <div class="value">{{ $companiesCount }}</div>
                    <div class="trend">{{ __('Уникальных работодателей') }}</div>
                </div>
                <div class="stat-card">
                    <div class="label">{{ __('Новые') }}</div>
                    <div class="value">{{ $newCount }}</div>
                    <div class="trend">{{ __('Ещё не обработаны') }}</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>{{ __('Список компаний') }}</h2>
                    <span class="hint">
                        {{ $responses->count() }} откликов ·
                        <a href="{{ route('public.companies.index') }}">{{ __('каталог компаний') }}</a>
                    </span>
                </div>

                @forelse($responses as $response)
                    @php $employer = $response->employer; @endphp
                    <div class="company-card">
                        <div class="company-mark">
                            @if($employer?->user?->hasAvatar())
                                <img src="{{ asset($employer->user->avatar) }}" alt=""
                                     style="width:100%; height:100%; object-fit:cover;">
                            @else
                                {{ $employer?->initials(2) ?? '?' }}
                            @endif
                        </div>

                        <div class="company-body">
                            <div class="company-name">
                                @if($employer)
                                    <a href="{{ route('public.companies.show', $employer) }}"
                                       style="color:inherit; text-decoration:none;">{{ $employer->company_name }}</a>
                                @else
                                    Компания удалена
                                @endif
                            </div>

                            <div class="company-meta">
                                @if($employer?->industry)
                                    {{ $employer->industry->name }} ·
                                @endif
                                @if($employer?->city)
                                    📍 {{ $employer->city->country }}, {{ $employer->city->region }} ·
                                @endif
                                откликнулись {{ $response->created_at->format('d.m.Y H:i') }}
                            </div>

                            <div class="company-meta">
                                По резюме:
                                @if($response->resume)
                                    <a href="{{ route('applicant.resume.show', $response->resume) }}">{{ $response->resume->profession }}</a>
                                @else
                                    —
                                @endif
                            </div>

                            @if($response->message)
                                <div class="company-msg">{{ $response->message }}</div>
                            @endif

                            <div class="company-contacts">
                                <span class="contact-chip">✉ {{ $employer?->email_company ?: '—' }}</span>
                                <span class="contact-chip">☎ {{ $employer?->phone ?: '—' }}</span>
                                @if($employer?->website_url)
                                    <a class="contact-chip" href="{{ $employer->website_url }}" target="_blank" rel="noopener">{{ __('🔗 Сайт') }}</a>
                                @endif
                                @if($employer)
                                    <a class="contact-chip" href="{{ route('public.companies.show', $employer) }}">{{ __('Страница компании') }}</a>
                                @endif
                            </div>

                            @include('partials.response-actions', [
                                'response' => $response,
                                'route' => route('public.resume_responses.status', $response),
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
                            {{ __('Опубликуйте резюме и заполните профиль — так работодатели найдут вас быстрее') }}
                        </p>
                        <a href="{{ route('applicant.resume.index') }}" class="btn btn-primary">{{ __('Мои резюме') }}</a>
                        <a href="{{ route('public.companies.index') }}" class="btn btn-secondary">{{ __('Каталог компаний') }}</a>
                    </div>
                @endforelse
            </div>

        </section>

    </main>
</div>

@include('applicant.partials.js')

@include('applicant.partials.script')
</body>
</html>
