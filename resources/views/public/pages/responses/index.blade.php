@extends('public.layouts.app')
@section('content')

    <style>
        .resp-panel{
            background:var(--surface); border:1px solid var(--border);
            border-radius:18px; padding:28px; margin-bottom:22px;
        }
        .resp-panel h2{
            font-size:18px; font-weight:800; margin-bottom:6px;
        }
        .resp-panel .sub{font-size:14px; color:var(--text-muted); margin-bottom:18px;}

        .resp-row{
            display:flex; gap:14px; padding:16px 0;
            border-bottom:1px solid var(--border);
        }
        .resp-row:last-child{border-bottom:none;}
        .resp-ava{
            width:44px; height:44px; border-radius:50%; flex-shrink:0; overflow:hidden;
            background:var(--accent-soft); color:var(--accent-ink);
            display:flex; align-items:center; justify-content:center; font-weight:800;
        }
        .resp-ava.square{border-radius:12px;}
        :root[data-theme="dark"] .resp-ava{color:var(--accent);}
        .resp-body{flex:1; min-width:0;}
        .resp-title{font-weight:700; font-size:15.5px;}
        .resp-meta{font-size:13px; color:var(--text-muted); margin-top:3px;}
        .resp-msg{font-size:14px; margin-top:8px; line-height:1.6; white-space:pre-line;}
        .resp-status{
            font-size:12px; font-weight:700; padding:4px 11px; border-radius:999px;
            background:var(--surface-alt); border:1px solid var(--border); color:var(--text-muted);
            white-space:nowrap; align-self:flex-start;
        }
        .resp-status.accepted{background:#DCFCE7; border-color:transparent; color:#15803D;}
        .resp-status.rejected{background:#FEE2E2; border-color:transparent; color:#B91C1C;}
        .resp-empty{color:var(--text-muted); font-size:14.5px;}
        .resp-actions{margin-top:10px;}
        .resp-actions button{
            background:none; border:1px solid var(--border); border-radius:10px;
            padding:7px 14px; font-size:13px; font-weight:600; color:var(--text-muted);
            cursor:pointer; font-family:inherit;
        }
        .resp-actions button:hover{border-color:var(--accent); color:var(--accent);}
    </style>

    @php
        $statusLabels = [
            'new' => 'Новый',
            'viewed' => 'Просмотрен',
            'accepted' => 'Принят',
            'rejected' => 'Отклонён',
        ];
        $isApplicant = $user->role === 'applicant';
    @endphp

    <section class="section">
        <div class="container">

            <div class="section-head">
                <div class="eyebrow">{{ __('Отклики') }}</div>
                <h2>{{ $isApplicant ? 'Ваши отклики и приглашения' : 'Отклики соискателей и ваши приглашения' }}</h2>
                <p>{{ __('Кто откликнулся, когда и на что — в одном месте.') }}</p>
            </div>

            @if(session('status') || session('error'))
                <div style="margin-bottom:20px; padding:14px 18px; border-radius:12px; font-weight:600;
                            background:{{ session('error') ? '#FEF2F2' : '#ECFDF5' }};
                            color:{{ session('error') ? '#B91C1C' : '#15803D' }};">
                    {{ session('error') ?: session('status') }}
                </div>
            @endif

            {{-- ===== МОИ ОТКЛИКИ ===== --}}
            <div class="resp-panel">
                <h2>{{ $isApplicant ? 'Мои отклики' : 'Мои приглашения' }} ({{ $myResponses->count() }})</h2>
                <div class="sub">
                    {{ $isApplicant
                        ? 'Вакансии, на которые вы откликнулись'
                        : 'Резюме, которым вы отправили приглашение' }}
                </div>

                @forelse($myResponses as $response)
                    @if($isApplicant)
                        @php $vacancy = $response->vacancy; @endphp
                        <div class="resp-row">
                            @if($vacancy?->employer?->user?->hasAvatar())
                                <img class="resp-ava square" src="{{ asset($vacancy->employer->user->avatar) }}" alt="">
                            @else
                                <div class="resp-ava square">{{ $vacancy?->employer?->initials(2) ?? '?' }}</div>
                            @endif
                            <div class="resp-body">
                                <div class="resp-title">
                                    @if($vacancy)
                                        <a href="{{ route('public.vacancies.show', $vacancy) }}">{{ $vacancy->title }}</a>
                                    @else
                                        Вакансия удалена
                                    @endif
                                </div>
                                <div class="resp-meta">
                                    {{ $vacancy?->employer?->company_name ?: '—' }}
                                    @if($vacancy?->city) · {{ $vacancy->city->country }}, {{ $vacancy->city->region }} @endif
                                    · отклик от {{ $response->created_at->format('d.m.Y H:i') }}
                                </div>
                                @if($response->message)
                                    <div class="resp-msg">{{ $response->message }}</div>
                                @endif
                                @if($vacancy)
                                    <div class="resp-actions">
                                        <form action="{{ route('public.vacancies.respond.destroy', $vacancy) }}" method="post">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit">{{ __('Отозвать отклик') }}</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                            <span class="resp-status {{ $response->status }}">{{ $statusLabels[$response->status] ?? $response->status }}</span>
                        </div>
                    @else
                        @php $resume = $response->resume; @endphp
                        <div class="resp-row">
                            @if($resume?->applicant?->user?->hasAvatar())
                                <img class="resp-ava" src="{{ asset($resume->applicant->user->avatar) }}" alt="">
                            @else
                                <div class="resp-ava">{{ $resume?->applicant?->user?->initials(1) ?? '?' }}</div>
                            @endif
                            <div class="resp-body">
                                <div class="resp-title">
                                    @if($resume)
                                        <a href="{{ route('public.resumes.show', $resume) }}">{{ $resume->profession }}</a>
                                    @else
                                        Резюме удалено
                                    @endif
                                </div>
                                <div class="resp-meta">
                                    {{ $resume?->applicant?->user?->name ?: '—' }}
                                    · приглашение от {{ $response->created_at->format('d.m.Y H:i') }}
                                </div>
                                @if($response->message)
                                    <div class="resp-msg">{{ $response->message }}</div>
                                @endif
                                @if($resume)
                                    <div class="resp-actions">
                                        <form action="{{ route('public.resumes.respond.destroy', $resume) }}" method="post">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit">{{ __('Отозвать приглашение') }}</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                            <span class="resp-status {{ $response->status }}">{{ $statusLabels[$response->status] ?? $response->status }}</span>
                        </div>
                    @endif
                @empty
                    <div class="resp-empty">
                        {{ $isApplicant
                            ? 'Вы пока никуда не откликались.'
                            : 'Вы пока никого не приглашали.' }}
                    </div>
                @endforelse
            </div>

            {{-- ===== ВХОДЯЩИЕ ===== --}}
            <div class="resp-panel">
                <h2>{{ $isApplicant ? 'Приглашения работодателей' : 'Отклики на мои вакансии' }} ({{ $incomingResponses->count() }})</h2>
                <div class="sub">
                    {{ $isApplicant
                        ? 'Компании, которые откликнулись на ваши резюме'
                        : 'Соискатели, которые откликнулись на ваши вакансии' }}
                </div>

                @forelse($incomingResponses as $response)
                    @if($isApplicant)
                        @php $employer = $response->employer; @endphp
                        <div class="resp-row">
                            @if($employer?->user?->hasAvatar())
                                <img class="resp-ava square" src="{{ asset($employer->user->avatar) }}" alt="">
                            @else
                                <div class="resp-ava square">{{ $employer?->initials(2) ?? '?' }}</div>
                            @endif
                            <div class="resp-body">
                                <div class="resp-title">
                                    @if($employer)
                                        <a href="{{ route('public.companies.show', $employer) }}">{{ $employer->company_name }}</a>
                                    @else
                                        Компания удалена
                                    @endif
                                </div>
                                <div class="resp-meta">
                                    {{ $employer?->email_company ?: '—' }} · {{ $employer?->phone ?: '—' }}
                                    · по резюме «{{ $response->resume?->profession ?? '—' }}»
                                    · {{ $response->created_at->format('d.m.Y H:i') }}
                                </div>
                                @if($response->message)
                                    <div class="resp-msg">{{ $response->message }}</div>
                                @endif

                                @include('partials.response-actions', [
                                    'response' => $response,
                                    'route' => route('public.resume_responses.status', $response),
                                ])
                            </div>
                            <span class="resp-status {{ $response->status }}">{{ $statusLabels[$response->status] ?? $response->status }}</span>
                        </div>
                    @else
                        @php $respApplicant = $response->applicant; @endphp
                        <div class="resp-row">
                            <div class="resp-ava">
                                @if($respApplicant?->user?->hasAvatar())
                                    <img src="{{ asset($respApplicant->user->avatar) }}" alt=""
                                         style="width:100%; height:100%; object-fit:cover;">
                                @else
                                    {{ $respApplicant?->user?->initials(1) ?? '?' }}
                                @endif
                            </div>
                            <div class="resp-body">
                                <div class="resp-title">{{ $respApplicant?->user?->name ?? 'Соискатель удалён' }}</div>
                                <div class="resp-meta">
                                    📍 {{ $respApplicant?->city ?: '—' }}
                                    · на вакансию
                                    @if($response->vacancy)
                                        «<a href="{{ route('public.vacancies.show', $response->vacancy) }}">{{ $response->vacancy->title }}</a>»
                                    @else
                                        «—»
                                    @endif
                                    · {{ $response->created_at->format('d.m.Y H:i') }}
                                </div>
                                @if($response->message)
                                    <div class="resp-msg">{{ $response->message }}</div>
                                @endif
                                @if($respApplicant && $respApplicant->resume->count())
                                    <div class="resp-actions" style="display:flex; gap:10px; flex-wrap:wrap;">
                                        @foreach($respApplicant->resume as $applicantResume)
                                            <a href="{{ route('public.resumes.show', $applicantResume) }}"
                                               class="resp-status">Резюме: {{ $applicantResume->profession }}</a>
                                        @endforeach
                                    </div>
                                @endif

                                @include('partials.response-actions', [
                                    'response' => $response,
                                    'route' => route('public.vacancy_responses.status', $response),
                                ])
                            </div>
                            <span class="resp-status {{ $response->status }}">{{ $statusLabels[$response->status] ?? $response->status }}</span>
                        </div>
                    @endif
                @empty
                    <div class="resp-empty">
                        {{ $isApplicant
                            ? 'Работодатели пока не откликались на ваши резюме.'
                            : 'На ваши вакансии пока никто не откликнулся.' }}
                    </div>
                @endforelse
            </div>

        </div>
    </section>

@endsection
