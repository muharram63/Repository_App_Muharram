
@extends('admin.layouts.app')

@section('main')
    <style>
        .r-card {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            background: #fff;
            margin: 0 3vh 2.5vh 2vh;
        }
        .r-card-header {
            padding: 16px 24px;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 700;
            color: #1e293b;
            font-size: 14.5px;
        }
        .r-card-body { padding: 22px 24px; }

        .r-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px 24px;
        }
        .r-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .r-value { font-size: 15px; font-weight: 600; color: #1e293b; word-break: break-word; }
        .r-value.muted { font-weight: 500; color: #94a3b8; }

        .r-chips { display: flex; flex-wrap: wrap; gap: 8px; }
        .r-chip {
            display: inline-block;
            font-size: 12.5px;
            font-weight: 600;
            padding: 6px 13px;
            border-radius: 999px;
            background: #EAF2FF;
            color: #1656D6;
        }
        .r-chip.plain { background: #F1F5F9; color: #475569; }

        .r-text { font-size: 14.5px; line-height: 1.7; color: #475569; white-space: pre-line; }

        .r-doc {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 18px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            color: #1e293b;
            background: #f8fafc;
        }
        .r-doc:hover { border-color: #1656D6; color: #1656D6; }
    </style>

    @php
        $applicant = $resume->applicant;
        $user = $applicant->user;
        $years = (int) $resume->experience_years;
        $skills = array_filter(array_map('trim', explode(',', (string) $resume->skills)));
        $languages = array_filter(array_map('trim', explode(',', (string) $resume->languages)));
        $genderLabel = $applicant->gender === 'male' ? 'Мужской' : ($applicant->gender === 'female' ? 'Женский' : '—');
    @endphp

    <section class="page" id="page-resume-show" style="display: block !important; background-color: #fcfcfd; border-radius: 2vh;">

        <div class="section-header mb-4">
            <h1 class="disp font-weight-bold" style="color: #1e293b; font-size: 28px; margin-left: 5vh; margin-top: 5vh;">Резюме</h1>
            <div class="sub text-muted" style="font-size: 0.95rem; color: #64748b; margin-right: 5vh;">
                <a href="{{ route('superadmin.resumes') }}" class="btn btn-secondary" style="font-size: 1.8vh;">← Назад к списку</a>
            </div>
        </div>

        {{-- Шапка --}}
        <div class="r-card">
            <div class="r-card-body">
                <div style="display:flex; align-items:center; gap:18px; flex-wrap:wrap;">
                    @if($user->hasAvatar())
                        <img src="{{ asset($user->avatar) }}" alt="{{ $user->name }}"
                             style="width:72px; height:72px; border-radius:50%; object-fit:cover;">
                    @else
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white"
                             style="width:72px; height:72px; font-size:26px; font-weight:700; background:#1656D6;">
                            {{ $user->initials(1) }}
                        </div>
                    @endif
                    <div>
                        <div style="font-size:22px; font-weight:800; color:#1e293b;">{{ $user->name }}</div>
                        <div style="color:#64748b; font-size:14px; margin-top:4px;">
                            {{ $resume->profession }} · ищет «{{ $resume->desired_position }}»
                        </div>
                        <div style="color:#94a3b8; font-size:13px; margin-top:3px;">
                            ID резюме {{ $resume->id }} · {{ $user->email }} · 📍 {{ $applicant->city ?: '—' }}
                        </div>
                    </div>
                </div>

                <div class="r-grid" style="margin-top:24px; border-top:1px solid #e2e8f0; padding-top:20px;">
                    <div>
                        <div class="r-label">Опыт работы</div>
                        <div class="r-value">@if($years > 0){{ $years }} г.@else Без опыта @endif</div>
                    </div>
                    <div>
                        <div class="r-label">Желаемая зарплата</div>
                        <div class="r-value">{{ number_format($resume->desired_salary, 0, ',', ' ') }} сомони</div>
                    </div>
                    <div>
                        <div class="r-label">Желаемая должность</div>
                        <div class="r-value">{{ $resume->desired_position }}</div>
                    </div>
                    <div>
                        <div class="r-label">Обновлено</div>
                        <div class="r-value">{{ $resume->updated_at->format('d.m.Y') }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Данные резюме --}}
        <div class="r-card">
            <div class="r-card-header">Данные резюме</div>
            <div class="r-card-body">
                <div class="r-grid">
                    <div>
                        <div class="r-label">Профессия</div>
                        <div class="r-value">{{ $resume->profession }}</div>
                    </div>
                    <div>
                        <div class="r-label">Место работы</div>
                        <div class="r-value">{{ $resume->place_work ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="r-label">Образование</div>
                        <div class="r-value">{{ $applicant->education ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="r-label">Портфолио / GitHub</div>
                        <div class="r-value">
                            @if($resume->url_website)
                                <a href="{{ $resume->url_website }}" target="_blank" rel="noopener"
                                   style="color:#1656D6;">{{ $resume->url_website }}</a>
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="r-label">Создано</div>
                        <div class="r-value">{{ $resume->created_at->format('d.m.Y') }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Навыки и языки --}}
        <div class="r-card">
            <div class="r-card-header">Навыки и языки</div>
            <div class="r-card-body">
                <div class="r-label">Навыки</div>
                <div class="r-chips" style="margin-bottom:20px;">
                    @forelse($skills as $skill)
                        <span class="r-chip">{{ $skill }}</span>
                    @empty
                        <span class="r-value muted">Не указаны</span>
                    @endforelse
                </div>

                <div class="r-label">Языки</div>
                <div class="r-chips">
                    @forelse($languages as $language)
                        <span class="r-chip plain">{{ $language }}</span>
                    @empty
                        <span class="r-value muted">Не указаны</span>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- О себе --}}
        <div class="r-card">
            <div class="r-card-header">О себе</div>
            <div class="r-card-body">
                <div class="r-text">{{ $resume->description ?: ($applicant->about_me ?: 'Описание не заполнено') }}</div>

                @if($resume->description && $applicant->about_me)
                    <div class="r-label" style="margin-top:20px;">Из анкеты соискателя</div>
                    <div class="r-text">{{ $applicant->about_me }}</div>
                @endif
            </div>
        </div>

        {{-- Документы --}}
        <div class="r-card">
            <div class="r-card-header">Документы</div>
            <div class="r-card-body">
                @if($resume->documents)
                    <a class="r-doc" href="{{ route('public.files.resume', $resume) }}" target="_blank" rel="noopener">
                        📄 {{ basename($resume->documents) }}
                    </a>
                @else
                    <span class="r-value muted">Файл не загружен</span>
                @endif
            </div>
        </div>

        {{-- Личные данные соискателя --}}
        <div class="r-card" style="margin-bottom: 4vh;">
            <div class="r-card-header">Личные данные соискателя</div>
            <div class="r-card-body">
                <div class="r-grid">
                    <div>
                        <div class="r-label">Дата рождения</div>
                        <div class="r-value">{{ $applicant->birth ? $applicant->birth->format('d.m.Y') : '—' }}</div>
                    </div>
                    <div>
                        <div class="r-label">Возраст</div>
                        <div class="r-value">{{ $applicant->birth ? $applicant->birth->age : '—' }}</div>
                    </div>
                    <div>
                        <div class="r-label">Пол</div>
                        <div class="r-value">{{ $genderLabel }}</div>
                    </div>
                    <div>
                        <div class="r-label">Телефон</div>
                        <div class="r-value">{{ $applicant->phone ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="r-label">Email</div>
                        <div class="r-value">{{ $user->email }}</div>
                    </div>
                    <div>
                        <div class="r-label">Город</div>
                        <div class="r-value">{{ $applicant->city ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="r-label">Адрес</div>
                        <div class="r-value">{{ $applicant->address ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="r-label">Карточка соискателя</div>
                        <div class="r-value">
                            <a href="{{ route('superadmin.applicants.show', $applicant) }}" style="color:#1656D6;">Открыть →</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </section>
@endsection
