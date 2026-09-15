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
    @endphp

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
                                            {{ mb_substr($vacancy->employer?->user?->name ?? '?', 0, 2) }}
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
                        </div>
                    </div>

                    <div class="vacancy-card">
                        <h2 class="vacancy-block-title">{{ __('Описание вакансии') }}</h2>
                        <div class="vacancy-text">
                            {!! nl2br(e($vacancy->description)) !!}
                        </div>
                    </div>

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

                    <div class="vacancy-card vacancy-apply-card">
                        <a href="#" class="btn-primary vacancy-apply-btn">
                            {{ __('Откликнуться') }}
                        </a>
                        <div class="vacancy-apply-hint">{{ __('Отклик рассматривается в течение 3 дней') }}</div>
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
        .vacancy-breadcrumb a:hover{ color:var(--accent-ink); }

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
