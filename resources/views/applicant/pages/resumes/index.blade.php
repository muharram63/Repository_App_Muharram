<!DOCTYPE html>
<html lang="ru" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Мое Резюме') }}</title>
    @include('applicant.partials.css')
    <style>
        .resume-row{
            display:flex; align-items:center; justify-content:space-between; gap:18px;
            padding:18px 0; border-bottom:1px solid var(--gray-200); flex-wrap:wrap;
        }
        .resume-row:last-of-type{border-bottom:none;}
        .resume-main{display:flex; align-items:center; gap:14px; min-width:0;}
        .resume-icon{
            width:44px; height:44px; border-radius:12px; flex-shrink:0;
            background:var(--indigo-50); color:var(--indigo-700);
            display:flex; align-items:center; justify-content:center; font-size:19px;
        }
        .resume-title{font-weight:700; font-size:15.5px;}
        .resume-title a{color:inherit; text-decoration:none;}
        .resume-title a:hover{color:var(--indigo-600);}
        .resume-sub{font-size:13px; color:var(--gray-500); margin-top:3px;}
        .resume-chips{display:flex; flex-wrap:wrap; gap:8px; margin-top:8px;}
        .resume-chip{
            font-size:12px; font-weight:600; padding:5px 11px; border-radius:999px;
            background:var(--gray-100); color:var(--gray-500);
        }
        .resume-chip.accent{background:var(--indigo-50); color:var(--indigo-700);}
        .resume-actions{display:flex; align-items:center; gap:8px; flex-wrap:wrap;}
        .resume-actions form{margin:0;}
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
                <h1 id="pageTitle">{{ __('Мои Резюме') }}</h1>
                <p id="pageSubtitle">{{ __('Управляйте своими резюме и смотрите отклики работодателей') }}</p>
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
                    <div class="name">{{ $applicant->user->name }}</div>
                    <div class="role">{{ $applicant->user->email }}</div>
                </div>
            </div>
        </div>

        <!-- ---------- SECTION: VACANCIES ---------- -->
        <section class="section active" id="section-vacancies">

            <div class="stat-row">
                <div class="stat-card">
                    <div class="label">{{ __('Всего резюме') }}</div>
                    <div class="value">{{ $resumes->count() }}</div>
                    <div class="trend">{{ __('За всё время') }}</div>
                </div>
                <div class="stat-card">
                    <div class="label">{{ __('Отклики') }}</div>
                    <div class="value">{{ $resumes->sum('responses_count') }}</div>
                    <div class="trend">{{ __('По всем резюме') }}</div>
                </div>
                <div class="stat-card">
                    <div class="label">{{ __('Обновлено') }}</div>
                    <div class="value" style="font-size:20px;">
                        {{ $resumes->max('updated_at')?->format('d.m.Y') ?? '—' }}
                    </div>
                    <div class="trend">{{ __('Последнее изменение') }}</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>{{ __('Список резюме') }}</h2>
                    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        {{-- вход в диалогового помощника: он собирает резюме вместо формы --}}
                        <a href="{{ route('applicant.resume.assistant') }}" class="btn"
                           style="display:inline-flex; align-items:center; gap:7px; color:#fff; font-weight:700;
                                  background:linear-gradient(135deg,#2C5FE0,#5B8DEF); border:0;
                                  box-shadow:0 12px 24px -14px rgba(44,95,224,.95);">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 3l1.9 4.6L18.5 9.5l-4.6 1.9L12 16l-1.9-4.6L5.5 9.5l4.6-1.9z"/>
                                <path d="M18 15l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8z"/>
                            </svg>
                            {{ __('Собрать с помощником') }}
                        </a>
                        <a href="{{ route('applicant.resume.create') }}" class="btn btn-primary">{{ __('+ Добавить резюме') }}</a>
                    </div>
                </div>

                <div class="field" style="max-width:360px; margin-bottom:20px;">
                    <input id="resumeSearch" type="text" placeholder="{{ __('Поиск по профессии, должности или навыку...') }}">
                </div>

                @forelse($resumes as $resume)
                    @php
                        $years = (int) $resume->experience_years;
                        $place = collect([$resume->applicant->city, $resume->applicant->address])
                            ->filter()->implode(', ');
                    @endphp
                    <div class="resume-row">
                        <div class="resume-main">
                            <div class="resume-icon">📄</div>
                            <div style="min-width:0;">
                                <div class="resume-title">
                                    <a href="{{ route('applicant.resume.show', $resume) }}">{{ $resume->profession }}</a>
                                </div>
                                <div class="resume-sub">{{ __("Желаемая должность") }}: {{ $resume->desired_position }}</div>

                                <div class="resume-chips">
                                    <span class="resume-chip accent">
                                        {{ number_format($resume->desired_salary, 0, ',', ' ') }} {{ __('сомони') }}
                                    </span>
                                    <span class="resume-chip">
                                        @if($years > 0) Опыт {{ $years }} г. @else Без опыта @endif
                                    </span>
                                    <span class="resume-chip">📍 {{ $place ?: 'Город не указан' }}</span>
                                    <span class="resume-chip">👥 {{ $resume->responses_count }} {{ __('откликов') }}</span>
                                    <span class="resume-chip">👁 {{ $resume->views }} {{ __('просмотров') }}</span>
                                    <span class="resume-chip">Создано {{ $resume->created_at->format('d.m.Y') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="resume-actions">
                            <a href="{{ route('applicant.resume.show', $resume) }}" class="btn btn-secondary">{{ __('Смотреть') }}</a>
                            <a href="{{ route('applicant.resume.edit', $resume) }}" class="btn btn-secondary">{{ __('Изменить') }}</a>
                            <form action="{{ route('applicant.resume.destroy', $resume) }}" method="POST"
                                  onsubmit="return confirm('Удалить резюме безвозвратно?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-secondary" style="color:#E14A4A;">{{ __('Удалить') }}</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div style="text-align:center; padding:48px 16px;">
                        <div style="font-size:15px; font-weight:600; margin-bottom:6px;">{{ __('Пока нет резюме') }}</div>
                        <p style="color:var(--gray-500); font-size:14px; margin-bottom:20px;">
                            {{ __('Создайте первую резюме, чтобы начать получать отклики') }}
                        </p>
                        <a href="{{ route('applicant.resume.assistant') }}" class="btn"
                           style="color:#fff; font-weight:700; border:0; margin-right:8px;
                                  background:linear-gradient(135deg,#2C5FE0,#5B8DEF);">
                            {{ __('Собрать с помощником') }}
                        </a>
                        <a href="{{ route('applicant.resume.create') }}" class="btn btn-primary">{{ __('+ Добавить резюме') }}</a>
                    </div>
                @endforelse

                @if(method_exists($resumes, 'links'))
                    <div style="margin-top:20px;">
                        {{ $resumes->links() }}
                    </div>
                @endif
            </div>
        </section>

    </main>
</div>

@include('applicant.partials.js')

<script>
    // Простой клиентский поиск по названию (можно заменить на серверный)
    (function () {
        const input = document.getElementById('resumeSearch');
        const cards = document.querySelectorAll('.resume-row');
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
