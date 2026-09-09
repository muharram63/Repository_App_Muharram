@extends('admin.layouts.app')

@section('main')

    <section class="page" id="page-vacancy-show" style="display: block !important; background-color: #fcfcfd; border-radius: 2vh; padding: 24px;">

        <div class="section-header mb-4 d-flex align-items-center justify-content-between">
            <div>
                <h1 class="disp font-weight-bold" style="color: #1e293b; font-size: 26px; margin-bottom: 4px;">{{ $vacancy->title }}</h1>
                <div class="sub text-muted" style="font-size: 0.9rem; color: #64748b;">
                    Вакансия #{{ $vacancy->id }}
                    @isset($vacancy->created_at)
                        · создана {{ $vacancy->created_at->format('d.m.Y') }}
                    @endisset
                </div>
            </div>

            <div class="d-flex gap-2">
                <a href="{{ route('superadmin.vacancy') }}" class="btn btn-secondary" style="border-radius: 8px;">← Назад к списку</a>
            </div>
        </div>

        <div class="row" style="display:flex; gap:20px; flex-wrap: wrap;">

            {{-- Основная информация о вакансии --}}
            <div class="card" style="flex: 2; min-width: 320px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02); background: #fff;">
                <div class="card-body" style="padding: 24px;">
                    <h2 style="font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 16px;">Детали вакансии</h2>

                    <div style="display:grid; grid-template-columns: 180px 1fr; row-gap: 14px; column-gap: 12px; font-size: 14px;">
                        <div style="color:#64748b; font-weight:500;">Статус</div>
                        <div>
                            @switch($vacancy->status)
                                @case('active')
                                    <span style="background:#F0FDF4; color:#15803d; padding:4px 10px; border-radius:6px; font-weight:600;">✅ Активна</span>
                                    @break
                                @case('inactive')
                                    <span style="background:#F1F5F9; color:#475569; padding:4px 10px; border-radius:6px; font-weight:600;">⏸ Неактивна</span>
                                    @break
                                @case('closed')
                                    <span style="background:#FDECEC; color:#E14A4A; padding:4px 10px; border-radius:6px; font-weight:600;">✖ Закрыта</span>
                                    @break
                                @case('in_archived')
                                    <span style="background:#F0F1F3; color:#6B7280; padding:4px 10px; border-radius:6px; font-weight:600;">🗄 Архивирована</span>
                                    @break
                                @default
                                    <span>{{ $vacancy->status }}</span>
                            @endswitch
                        </div>

                        <div style="color:#64748b; font-weight:500;">Зарплата</div>
                        <div style="color:#1e293b; font-weight:600;">
                            {{ $vacancy->salary_from }} – {{ $vacancy->salary_to }} {{ $vacancy->currencyLabel() }}
                        </div>

                        @isset($vacancy->city->country)
                            <div style="color:#64748b; font-weight:500;">Город</div>
                            <div style="color:#1e293b;">{{ $vacancy->city->country }} {{$vacancy->city->region}}</div>
                        @endisset

                        @isset($vacancy->employment_type)
                            <div style="color:#64748b; font-weight:500;">Тип занятости</div>
                            <div style="color:#1e293b;">{{ $vacancy->employment_type }}</div>
                        @endisset


                        @isset($vacancy->work_schedule)
                            <div style="color:#64748b; font-weight:500;">График</div>
                            <div style="color:#1e293b;">{{ $vacancy->work_schedule }}</div>
                        @endisset

                        @isset($vacancy->experience_required)
                            <div style="color:#64748b; font-weight:500;">Опыт работы</div>
                            <div style="color:#1e293b;">{{ $vacancy->experience_required }}</div>
                        @endisset

                        @isset($vacancy->skill)
                            <div style="color:#64748b; font-weight:500;">Навыки</div>
                            <div style="color:#1e293b;">{{ $vacancy->skill }}</div>
                        @endisset
                    </div>

                    @isset($vacancy->description)
                        <hr style="margin: 20px 0; border-color:#f1f5f9;">
                        <h2 style="font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 12px;">Описание</h2>
                        <div style="color:#334155; font-size: 14px; line-height: 1.6; white-space: pre-line;">{{ $vacancy->description }}</div>
                    @endisset


                </div>
            </div>

            {{-- Карточка работодателя --}}
            <div class="card" style="flex: 1; min-width: 260px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02); background: #fff; height: fit-content;">
                <div class="card-body" style="padding: 24px;">
                    <h2 style="font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 16px;">Работодатель</h2>

                    <div class="d-flex align-items-center" style="gap: 12px; margin-bottom: 16px;">
                        @if($vacancy->employer?->user?->hasAvatar())
                            <img src="{{ asset($vacancy->employer->user->avatar) }}" alt="Avatar" style="width:48px; height:48px; border-radius:50%; object-fit:cover;">
                        @else
                            <div class="avatar text-white rounded-circle d-flex align-items-center justify-content-center" style="width:48px; height:48px; font-size:15px; font-weight:700; background:#1656D6;">
                                {{ mb_substr($vacancy->employer->user->name, 0, 1) }}
                            </div>
                        @endif
                        <div>
                            <div style="font-weight:600; color:#1e293b; font-size:14px;">{{ $vacancy->employer->user->name }}</div>
                            <div style="color:#64748b; font-size:13px;">{{ $vacancy->employer->user->email }}</div>
                        </div>
                    </div>

                    @isset($vacancy->employer->company_name)
                        <div style="font-size: 14px; margin-bottom: 8px;">
                            <span style="color:#64748b;">Компания:</span>
                            <span style="color:#1e293b; font-weight:600;">{{ $vacancy->employer->company_name }}</span>
                        </div>
                    @endisset

                    @isset($vacancy->employer->phone)
                        <div style="font-size: 14px;">
                            <span style="color:#64748b;">Телефон:</span>
                            <span style="color:#1e293b; font-weight:600;">{{ $vacancy->employer->phone }}</span>
                        </div>
                    @endisset


                    @isset($vacancy->employer->email_company)
                        <div style="font-size: 14px; margin-top: 10px;">
                            <span style="color:#64748b;">Рабочий email:</span>
                            <span style="color:#1e293b; font-weight:600;">{{ $vacancy->employer->email_company }}</span>
                        </div>
                    @endisset
                    @isset($vacancy->employer->website_url)
                        <div style="font-size: 14px; margin-top: 10px;">
                            <span style="color:#64748b;">URL:</span>
                            <span style="color:#1e293b; font-weight:600;">{{ $vacancy->employer->website_url }}</span>
                        </div>
                    @endisset



                </div>

            </div>

        </div>

    </section>
@endsection
