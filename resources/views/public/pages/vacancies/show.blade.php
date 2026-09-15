{{-- resources/views/public/vacancies/show.blade.php --}}
@extends('public.layouts.app')

@section('content')

    @php
        $employmentTypeLabels = [
            'full-time'    => 'Полная занятость',
            'part-time'    => 'Частичная занятость',
            'project_work' => 'Проектная работа',
            'internship'   => 'Стажировка',
        ];

        $workScheduleLabels = [
            'full_day'          => 'Полный день',
            'flexible_schedule' => 'Гибкий график',
            'remote_work'       => 'Удалённая работа',
        ];

        $experienceLabels = [
            'not'         => 'Без опыта',
            'year'        => 'От 1 года',
            '3_years'     => 'От 3 лет',
            '3-6years'    => '3–6 лет',
            'more_6years' => 'Более 6 лет',
        ];

        $skills = collect(explode(',', $vacancy->skill))
            ->map(fn($s) => trim($s))
            ->filter();

        // языки требуются не всегда: пустое поле значит «не важно»
        $languages = collect(explode(',', (string) $vacancy->languages))
            ->map(fn($s) => trim($s))
            ->filter();

        $authUser = auth()->user();
        $myApplicant = $authUser?->applicant;
        $myEmployer = $authUser?->employer;
        $myResponse = $myApplicant
            ? $vacancy->responses->firstWhere('applicant_id', $myApplicant->id)
            : null;
        $isVacancyOwner = $myEmployer && $vacancy->employer_id === $myEmployer->id;

        // соискатель сравнивает вакансию со своими резюме
        $matchOptions = $myApplicant
            ? $myApplicant->resume()->latest('id')->get()->map(fn ($r) => [
                'url' => route('public.match', [$vacancy, $r]),
                'label' => $r->profession.($r->desired_position ? ' — '.$r->desired_position : ''),
            ])->all()
            : [];

        $statusLabels = [
            'new' => __('Новый'),
            'viewed' => __('Просмотрен'),
            'accepted' => __('Принят'),
            'rejected' => __('Отклонён'),
        ];
    @endphp

    @if(session('status') || session('error'))
        <div class="container" style="padding-top:24px;">
            <div style="padding:14px 18px; border-radius:12px; font-weight:600;
                        background:{{ session('error') ? '#FEF2F2' : '#ECFDF5' }};
                        color:{{ session('error') ? '#B91C1C' : '#15803D' }};">
                {{ session('error') ?: session('status') }}
            </div>
        </div>
    @endif

    @if(isset($errors) && $errors->any())
        <div class="container" style="padding-top:24px;">
            <div style="padding:14px 18px; border-radius:12px; font-weight:600;
                        background:#FEF2F2; color:#B91C1C;">
                <div style="margin-bottom:6px;">{{ __('Не удалось сохранить:') }}</div>
                <ul style="margin:0; padding-left:18px; font-weight:500;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <section class="section vacancy-page">
        <div class="container">

            <div class="vacancy-breadcrumb">
                <a href="{{ route('public.vacancies.index') }}">{{ __('Вакансии') }}</a>
                <span>/</span>
                <span>{{ $vacancy->title }}</span>
            </div>

            <div class="vacancy-layout">

                {{-- ===== ЛЕВАЯ КОЛОНКА ===== --}}
                <div class="vacancy-main">

                    <div class="vacancy-card vacancy-head-card">
                        <div class="vacancy-head-top">
                            <div>
                                <h1 class="vacancy-title">{{ $vacancy->title }}</h1>

                                <div class="vacancy-company-row">
                                    <div class="logo-mark vacancy-company-logo-fallback">
                                        @if($vacancy->employer?->user?->hasAvatar())
                                            <img src="{{ asset($vacancy->employer->user->avatar) }}" alt=""
                                                 style="width:100%; height:100%; border-radius:inherit; object-fit:cover;">
                                        @else
                                            {{ mb_substr($vacancy->employer?->user?->name ?? '?', 0, 1) }}
                                        @endif
                                    </div>
                                    <div>
                                        <div class="vacancy-company-name">Компания : {{ $vacancy->employer->company_name ?? 'Компания' }}</div>
                                        @if($vacancy->city)
                                            <div class="vacancy-company-loc">📍 {{ __($vacancy->city->country) }} , {{ __($vacancy->city->region) }}</div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            @if($vacancy->salary_from || $vacancy->salary_to)
                                <div class="vacancy-salary-box">
                                    <div class="fc-salary vacancy-salary">
                                        @if($vacancy->salary_from && $vacancy->salary_to)
                                            {{ number_format($vacancy->salary_from, 0, '', ' ') }} – {{ number_format($vacancy->salary_to, 0, '', ' ') }}
                                        @elseif($vacancy->salary_from)
                                            от {{ number_format($vacancy->salary_from, 0, '', ' ') }}
                                        @else
                                            до {{ number_format($vacancy->salary_to, 0, '', ' ') }}
                                        @endif
                                        <span style="color: green;"> {{ __($vacancy->currencyLabel()) }}</span>
                                    </div>
                                    <div class="stat-lbl">{{ __('в месяц') }}</div>
                                </div>
                            @endif
                        </div>

                        <div class="hero-tags vacancy-tags">
                            <span class="tag-chip">🕒 {{ $employmentTypeLabels[$vacancy->employment_type] ?? $vacancy->employment_type }}</span>
                            <span class="tag-chip">💻 {{ $workScheduleLabels[$vacancy->work_schedule] ?? $vacancy->work_schedule }}</span>
                            <span class="tag-chip">📈 {{ $experienceLabels[$vacancy->experience_required] ?? $vacancy->experience_required }}</span>
                            <span class="tag-chip">Опубликовано {{ $vacancy->created_at->format('d.m.Y') }}</span>
                            <span class="tag-chip">👥 {{ $vacancy->responses_count }} {{ __('откликов') }}</span>
                            <span class="tag-chip">👁 {{ $vacancy->views }} {{ __('просмотров') }}</span>
                        </div>
                    </div>

                    <div class="vacancy-card">
                        <h2 class="vacancy-block-title">{{ __('Описание вакансии') }}</h2>
                        <div class="vacancy-text">
                            {!! nl2br(e($vacancy->description)) !!}
                        </div>
                    </div>

                    @if($isVacancyOwner)
                        <div class="vacancy-card">
                            <h2 class="vacancy-block-title">Отклики на вакансию ({{ $vacancy->responses_count }})</h2>

                            @forelse($vacancy->responses->sortByDesc('created_at') as $response)
                                @php $respApplicant = $response->applicant; @endphp
                                <div style="display:flex; gap:14px; padding:16px 0; border-bottom:1px solid var(--border);">
                                    <div style="width:44px; height:44px; border-radius:50%; flex-shrink:0; overflow:hidden;
                                                background:var(--accent-soft); color:var(--accent-text);
                                                display:flex; align-items:center; justify-content:center; font-weight:800;">
                                        @if($respApplicant?->user?->hasAvatar())
                                            <img src="{{ asset($respApplicant->user->avatar) }}" alt=""
                                                 style="width:100%; height:100%; object-fit:cover;">
                                        @else
                                            {{ $respApplicant?->user?->initials(1) ?? '?' }}
                                        @endif
                                    </div>
                                    <div style="flex:1; min-width:0;">
                                        <div style="font-weight:700;">{{ $respApplicant?->user?->name ?? 'Соискатель удалён' }}</div>
                                        <div style="font-size:13px; color:var(--text-muted); margin-top:2px;">
                                            📍 {{ $respApplicant?->city ?: '—' }} ·
                                            откликнулся {{ $response->created_at->format('d.m.Y H:i') }} ·
                                            {{ $statusLabels[$response->status] ?? $response->status }}
                                        </div>
                                        @if($response->message)
                                            <div style="font-size:14px; margin-top:8px; line-height:1.6; white-space:pre-line;">{{ $response->message }}</div>
                                        @endif
                                        @if($respApplicant && $respApplicant->resume->count())
                                            <div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
                                                @foreach($respApplicant->resume as $applicantResume)
                                                    <a href="{{ route('public.resumes.show', $applicantResume) }}"
                                                       class="tag-chip">Резюме: {{ $applicantResume->profession }}</a>
                                                @endforeach
                                            </div>
                                        @endif

                                        @include('partials.response-actions', [
                                            'response' => $response,
                                            'route' => route('public.vacancy_responses.status', $response),
                                        ])

                                        @include('partials.interview-schedule', [
                                            'applicantId' => $respApplicant?->id,
                                            'vacancyId' => $vacancy->id,
                                            'vacancyResponseId' => $response->id,
                                        ])
                                    </div>
                                </div>
                            @empty
                                <div style="color:var(--text-muted); font-size:14.5px;">{{ __('Пока никто не откликнулся.') }}</div>
                            @endforelse
                        </div>
                    @endif

                    @if($skills->isNotEmpty())
                        <div class="vacancy-card">
                            <h2 class="vacancy-block-title">{{ __('Ключевые навыки') }}</h2>
                            <div class="hero-tags">
                                @foreach($skills as $skill)
                                    <span class="tag-chip">{{ $skill }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($languages->isNotEmpty())
                        <div class="vacancy-card">
                            <h2 class="vacancy-block-title">{{ __('Требуемые языки') }}</h2>
                            <div class="hero-tags">
                                @foreach($languages as $language)
                                    <span class="tag-chip">{{ $language }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                </div>

                {{-- ===== ПРАВАЯ КОЛОНКА ===== --}}
                <aside class="vacancy-side">

                    {{-- разбор совпадения: соискателю — до кнопки отклика,
                         чтобы решение принималось со знанием дела --}}
                    @auth
                        @if($authUser->role === 'applicant')
                            @include('partials.match-analysis', [
                                'options' => $matchOptions,
                                'emptyHint' => __('Создайте резюме, и ИИ разберёт, насколько вы подходите этой вакансии.'),
                            ])
                        @endif
                    @endauth

                    <div class="vacancy-card vacancy-apply-card">
                        @guest
                            <a href="{{ route('login') }}" class="btn-primary vacancy-apply-btn">
                                {{ __('Войдите, чтобы откликнуться') }}
                            </a>
                            <div class="vacancy-apply-hint">{{ __('Отклик рассматривается в течение 3 дней') }}</div>
                        @endguest

                        @auth
                            @if($isVacancyOwner)
                                <div style="font-weight:700; margin-bottom:6px;">{{ __('Это ваша вакансия') }}</div>
                                <div class="vacancy-apply-hint">
                                    Откликов: {{ $vacancy->responses_count }}. Список ниже, на этой же странице.
                                </div>
                            @elseif($authUser->role !== 'applicant')
                                <div class="vacancy-apply-hint">
                                    {{ __('Откликаться на вакансии могут только соискатели.') }}
                                </div>
                            @elseif($myResponse)
                                <div style="font-weight:700; margin-bottom:4px;">
                                    Вы откликнулись {{ $myResponse->created_at->format('d.m.Y H:i') }}
                                </div>
                                <div class="vacancy-apply-hint" style="margin-bottom:14px;">
                                    Статус: {{ $statusLabels[$myResponse->status] ?? $myResponse->status }}
                                </div>
                                <form action="{{ route('public.vacancies.respond.destroy', $vacancy) }}" method="post">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-ghost" style="width:100%; cursor:pointer;">
                                        {{ __('Отозвать отклик') }}
                                    </button>
                                </form>
                            @elseif($vacancy->status !== 'active')
                                <div class="vacancy-apply-hint">{{ __('Вакансия больше не принимает отклики.') }}</div>
                            @else
                                <form action="{{ route('public.vacancies.respond', $vacancy) }}" method="post">
                                    @csrf
                                    <textarea name="message" maxlength="1000" rows="4"
                                              placeholder="{{ __('Сопроводительное письмо (необязательно)') }}"
                                              style="width:100%; margin-bottom:12px; padding:12px 14px; font-family:inherit;
                                                     font-size:14px; color:var(--text); background:var(--surface-alt);
                                                     border:1px solid var(--border); border-radius:12px; resize:vertical;"></textarea>
                                    <button type="submit" class="btn-primary vacancy-apply-btn"
                                            style="width:100%; border:none; cursor:pointer;">
                                        {{ __('Откликнуться') }}
                                    </button>
                                </form>
                                <div class="vacancy-apply-hint">{{ __('Отклик рассматривается в течение 3 дней') }}</div>
                            @endif

                            @if($authUser->role === 'applicant' && $myApplicant && $vacancy->employer)
                                <form action="{{ route('public.chats.start') }}" method="post" style="margin-top:12px;">
                                    @csrf
                                    <input type="hidden" name="employer_id" value="{{ $vacancy->employer_id }}">
                                    <input type="hidden" name="vacancy_id" value="{{ $vacancy->id }}">
                                    <button type="submit" class="btn-ghost" style="width:100%; cursor:pointer;">
                                        {{ __('💬 Написать работодателю') }}
                                    </button>
                                </form>
                            @endif

                            @include('partials.complaint-form', [
                                'targetType' => 'vacancy',
                                'targetId' => $vacancy->id,
                            ])
                        @endauth
                    </div>

                    <div class="vacancy-card">
                        <h3 class="vacancy-block-title small">{{ __('О компании') }}</h3>
                        <div class="vacancy-company-row">
                            <div class="logo-mark vacancy-company-logo-fallback">
                                @if($vacancy->employer?->user?->hasAvatar())
                                    <img src="{{ asset($vacancy->employer->user->avatar) }}" alt=""
                                         style="width:100%; height:100%; border-radius:inherit; object-fit:cover;">
                                @else
                                    {{ $vacancy->employer?->initials(2) ?? '?' }}
                                @endif
                            </div>
                            <div>
                                <div class="vacancy-company-name">{{ $vacancy->employer->company_name ?? 'Компания' }}</div>
                                @if($vacancy->city)
                                    <div class="stat-lbl">{{ __($vacancy->city->country) }} , {{ __($vacancy->city->region) }}</div>
                                @endif
                            </div>
                        </div>

                        @if($vacancy->employer)
                            <a href="{{ route('public.vacancies.index', $vacancy->employer->id) }}" class="btn-ghost vacancy-company-link">
                                {{ __('Все вакансии компании') }}
                            </a>
                        @endif
                    </div>

                </aside>

            </div>
        </div>
    </section>

    <style>
        .vacancy-page{ padding-top:40px; }

        .vacancy-breadcrumb{
            display:flex; align-items:center; gap:8px;
            font-size:13px; color:var(--text-muted); margin-bottom:24px;
        }
        .vacancy-breadcrumb a:hover{ color:var(--accent-text); }

        .vacancy-layout{
            display:grid;
            grid-template-columns: 1fr 320px;
            gap:22px;
            align-items:start;
        }

        .vacancy-card{
            background:var(--surface);
            border:1px solid var(--border);
            border-radius:16px;
            padding:28px;
            margin-bottom:22px;
            box-shadow:var(--shadow);
        }

        .vacancy-head-top{
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:20px;
            flex-wrap:wrap;
        }

        .vacancy-title{
            font-size:26px;
            font-weight:800;
            letter-spacing:-.5px;
            margin:6px 0 14px;
        }

        .vacancy-company-row{
            display:flex;
            align-items:center;
            gap:12px;
        }

        .vacancy-company-logo-fallback{
            width:40px; height:40px;
            font-size:13px;
            flex-shrink:0;
        }

        .vacancy-company-name{
            font-weight:700;
            font-size:14.5px;
        }

        .vacancy-company-loc{
            font-size:12.5px;
            color:var(--text-muted);
            margin-top:2px;
        }

        .vacancy-salary-box{
            text-align:right;
            flex-shrink:0;
        }

        .vacancy-salary{
            font-size:19px;
            white-space:nowrap;
        }

        .vacancy-tags{
            margin-top:20px;
        }

        .vacancy-block-title{
            font-size:18px;
            font-weight:800;
            margin-bottom:14px;
            letter-spacing:-.3px;
        }

        .vacancy-block-title.small{
            font-size:15px;
            margin-bottom:16px;
        }

        .vacancy-text{
            font-size:14.5px;
            line-height:1.75;
            color:var(--text);
            white-space:pre-line;
        }

        .vacancy-side{
            position:sticky;
            top:94px;
        }

        .vacancy-apply-card{
            text-align:center;
        }

        .vacancy-apply-btn{
            display:block;
            width:100%;
            text-align:center;
            padding:13px 0;
        }

        .vacancy-apply-hint{
            margin-top:10px;
            font-size:12px;
            color:var(--text-muted);
        }

        .vacancy-company-link{
            display:block;
            text-align:center;
            width:100%;
            margin-top:16px;
        }

        @media (max-width:980px){
            .vacancy-layout{
                grid-template-columns:1fr;
            }
            .vacancy-side{
                position:static;
            }
        }
    </style>
@endsection
