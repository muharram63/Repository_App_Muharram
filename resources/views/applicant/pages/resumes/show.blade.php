<!DOCTYPE html>
<html lang="ru" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workio — {{ $resume->profession }}</title>
    @include('applicant.partials.css')
    <style>
        .chip-row { display:flex; flex-wrap:wrap; gap:8px; }
        .chip {
            display:inline-block;
            padding:6px 12px;
            border-radius:999px;
            background:var(--indigo-50);
            color:var(--indigo-700);
            font-size:13px;
            font-weight:600;
        }
        .info-grid {
            display:grid;
            grid-template-columns:repeat(2, minmax(0, 1fr));
            gap:18px 28px;
        }
        .info-grid .label {
            font-size:12px;
            text-transform:uppercase;
            letter-spacing:.5px;
            color:var(--gray-500);
            font-weight:600;
            margin-bottom:4px;
        }
        .info-grid .value { font-size:15px; font-weight:600; }
        .about-text { font-size:14.5px; line-height:1.65; color:var(--ink); white-space:pre-line; }
        .doc-link {
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:12px 16px;
            border:1px solid var(--gray-200);
            border-radius:var(--radius);
            font-weight:600;
            font-size:14px;
        }
        .doc-link:hover { background:var(--gray-100); }
        .muted { color:var(--gray-500); font-size:14px; }
        @media (max-width:900px){
            .info-grid { grid-template-columns:1fr; }
        }
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
                <h1 id="pageTitle">{{ $resume->profession }}</h1>
                <p id="pageSubtitle">Резюме создано {{ $resume->created_at->format('d.m.Y') }}</p>
            </div>
            @include('partials.notification-bell')
            <div class="user-chip">
                <div class="avatar" style="padding: 14px 16px;">
                    @if($user->hasAvatar())
                        <img src="{{ asset($user->avatar) }}" alt="Avatar"
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

        <section class="section active" id="section-resume">

            @if(session('status'))
                <div class="card" style="border-color:#BBF7D0; background:#F0FDF4; color:#15803D; font-weight:600;">
                    {{ session('status') }}
                </div>
            @endif

            <div class="stat-row">
                <div class="stat-card">
                    <div class="label">{{ __('Опыт работы') }}</div>
                    <div class="value">
                        @if($resume->experience_years > 0)
                            {{ $resume->experience_years }} г.
                        @else
                            Без опыта
                        @endif
                    </div>
                    <div class="trend">{{ $resume->place_work ?: 'Место работы не указано' }}</div>
                </div>
                <div class="stat-card">
                    <div class="label">{{ __('Желаемая зарплата') }}</div>
                    <div class="value">{{ number_format($resume->desired_salary, 0, ',', ' ') }} {{ __('сомони') }}</div>
                    <div class="trend">{{ $resume->desired_position }}</div>
                </div>
                <div class="stat-card">
                    <div class="label">{{ __('Обновлено') }}</div>
                    <div class="value">{{ $resume->updated_at->format('d.m.Y') }}</div>
                    <div class="trend">{{ $resume->updated_at->diffForHumans() }}</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>{{ __('Резюме') }}</h2>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <a href="{{ route('applicant.resume.edit', $resume) }}" class="btn btn-secondary"
                           style="border-color:#ffc107;">{{ __('Изменить') }}</a>
                        <form action="{{ route('applicant.resume.destroy', $resume) }}" method="POST"
                              onsubmit="return confirm('Удалить резюме безвозвратно?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-secondary" style="color:#E14A4A;">{{ __('Удалить') }}</button>
                        </form>

                        <a href="{{ route('applicant.resume.index') }}" class="btn btn-primary">{{ __('К списку') }}</a>
                    </div>
                </div>

                <div class="info-grid">
                    <div>
                        <div class="label">{{ __('Профессия') }}</div>
                        <div class="value">{{ $resume->profession }}</div>
                    </div>
                    <div>
                        <div class="label">{{ __('Желаемая должность') }}</div>
                        <div class="value">{{ $resume->desired_position }}</div>
                    </div>
                    <div>
                        <div class="label">{{ __('Опыт работы') }}</div>
                        <div class="value">
                            @if($resume->experience_years > 0)
                                {{ $resume->experience_years }} г.
                            @else
                                Без опыта
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="label">{{ __('Место работы') }}</div>
                        <div class="value">{{ $resume->place_work ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="label">{{ __('Ссылка (GitHub / LinkedIn)') }}</div>
                        <div class="value">
                            @if($resume->url_website)
                                <a href="{{ $resume->url_website }}" target="_blank" rel="noopener"
                                   style="color:var(--indigo-600);">{{ $resume->url_website }}</a>
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="label">{{ __('Языки') }}</div>
                        <div class="value">
                            @if($resume->languages)
                                <div class="chip-row">
                                    @foreach(array_filter(array_map('trim', explode(',', $resume->languages))) as $language)
                                        <span class="chip">{{ $language }}</span>
                                    @endforeach
                                </div>
                            @else
                                —
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>{{ __('Навыки') }}</h2>
                </div>
                @if($resume->skills)
                    <div class="chip-row">
                        @foreach(array_filter(array_map('trim', explode(',', $resume->skills))) as $skill)
                            <span class="chip">{{ $skill }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="muted" style="margin:0;">{{ __('Навыки не указаны') }}</p>
                @endif
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>{{ __('О себе') }}</h2>
                </div>
                @if($resume->description)
                    <div class="about-text">{{ $resume->description }}</div>
                @else
                    <p class="muted" style="margin:0;">{{ __('Описание не заполнено') }}</p>
                @endif
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>{{ __('Документы') }}</h2>
                </div>
                @if($resume->documents)
                    <a class="doc-link" href="{{ route('public.files.resume', $resume) }}" target="_blank" rel="noopener">
                        <span class="company-logo">📄</span>
                        {{ basename($resume->documents) }}
                    </a>
                @else
                    <p class="muted" style="margin:0;">{{ __('Файл не загружен') }}</p>
                @endif
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>{{ __('Контакты соискателя') }}</h2>
                    <span class="hint">{{ __('Эти данные видит работодатель') }}</span>
                </div>
                <div class="info-grid">
                    <div>
                        <div class="label">{{ __('Телефон') }}</div>
                        <div class="value">{{ $applicant->phone ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="label">{{ __('Город') }}</div>
                        <div class="value">{{ $applicant->city ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="label">{{ __('Адрес') }}</div>
                        <div class="value">{{ $applicant->address ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="label">{{ __('Образование') }}</div>
                        <div class="value">{{ $applicant->education ?: '—' }}</div>
                    </div>
                </div>
            </div>

        </section>

    </main>
</div>

@include('applicant.partials.js')

@include('applicant.partials.script')
</body>
</html>
