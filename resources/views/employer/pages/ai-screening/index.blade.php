<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — ИИ-собеседование') }}</title>
    @include('employer.partials.css')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@600;700;800&family=Inter:wght@400;500;600&display=swap"
          rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2 { font-family: 'Manrope', 'Inter', sans-serif; }

        .sc-wrap { padding: 0 3vh 4vh 2vh; display: flex; flex-direction: column; gap: 18px; }
        .sc-head h1 { margin: 0 0 4px; font-size: 20px; color: var(--ink); }
        .sc-lead { margin: 0; font-size: 13px; color: var(--gray-500); max-width: 76ch; }

        .sc-list { display: flex; flex-direction: column; gap: 10px; }

        .sc-row {
            display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 14px; align-items: center;
            background: var(--white); border: 1px solid var(--gray-200);
            border-radius: var(--radius); padding: 14px 16px;
        }
        @media (max-width: 720px) { .sc-row { grid-template-columns: 1fr; } }

        .sc-title { font-weight: 700; color: var(--ink); overflow-wrap: anywhere; }
        .sc-facts { margin-top: 4px; font-size: 13px; color: var(--gray-500); }

        .sc-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600;
        }
        .sc-on  { background: var(--wash-good); color: var(--ink-good); }
        .sc-off { background: var(--gray-100); color: var(--gray-500); }
        .sc-warn { background: var(--wash-warn); color: var(--ink-warn); }

        .sc-btn {
            display: inline-flex; align-items: center; padding: 8px 14px; border-radius: 999px;
            font-size: 13px; font-weight: 600; text-decoration: none;
            background: var(--indigo-600); color: #fff; border: 1px solid transparent;
        }
        .sc-btn:hover { filter: brightness(1.06); }
        .sc-btn-ghost { background: transparent; color: var(--gray-500); border-color: var(--gray-200); }

        .sc-empty {
            background: var(--white); border: 1px dashed var(--gray-300);
            border-radius: var(--radius); padding: 22px; text-align: center; color: var(--gray-500);
        }
        .sc-note {
            font-size: 13px; padding: 10px 12px; border-radius: 10px;
            background: var(--wash-warn); color: var(--ink-warn);
        }
    </style>
</head>
<body>
<div class="app">
    @include('employer.partials.sidebar')

    <main class="main">
        <div class="sc-wrap">
            <div class="sc-head">
                <h1>{{ __('ИИ-собеседование') }}</h1>
                <p class="sc-lead">
                    {{ __('Включите собеседование у вакансии, и каждый откликнувшийся пройдёт разговор с ИИ: он проверит документы, задаст вопросы, даст задание и сообщит результат. Вы получите отчёт по каждому кандидату и сможете изменить решение.') }}
                </p>
            </div>

            @unless($aiAvailable)
                <div class="sc-note">
                    {{ __('ИИ недоступен: в .env не задан GEMINI_API_KEY. Настройки сохранить можно, но критерии придётся добавить вручную.') }}
                </div>
            @endunless

            @if(session('status'))
                <div class="sc-note" style="background:var(--wash-good); color:var(--ink-good)">{{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="sc-note" style="background:var(--wash-danger); color:var(--danger)">{{ session('error') }}</div>
            @endif

            @if($vacancies->isEmpty())
                <div class="sc-empty">
                    {{ __('У вас пока нет вакансий. Создайте вакансию, и её можно будет отдать ИИ-собеседованию.') }}
                    <div style="margin-top:12px">
                        <a class="sc-btn" href="{{ route('employer.vacancies.create') }}">{{ __('Создать вакансию') }}</a>
                    </div>
                </div>
            @else
                <div class="sc-list">
                    @foreach($vacancies as $vacancy)
                        @php
                            $config = $configs->get($vacancy->id);
                            $confirmed = (int) ($criteriaCounts[$vacancy->id] ?? 0);
                            $candidates = (int) ($candidateCounts[$vacancy->id] ?? 0);
                            $problems = $config ? $config->problems() : [];
                        @endphp

                        <div class="sc-row">
                            <div>
                                <div class="sc-title">{{ $vacancy->title }}</div>
                                <div class="sc-facts">
                                    @if($config && $config->enabled)
                                        <span class="sc-badge sc-on">{{ __('Включено') }}</span>
                                    @elseif($config && $problems)
                                        <span class="sc-badge sc-warn">{{ __('Не настроено') }}</span>
                                    @else
                                        <span class="sc-badge sc-off">{{ __('Выключено') }}</span>
                                    @endif

                                    <span style="margin-left:8px">
                                        {{ __('Критериев подтверждено:') }} {{ $confirmed }}
                                    </span>
                                    @if($candidates > 0)
                                        <span style="margin-left:8px">
                                            {{ __('Кандидатов:') }} {{ $candidates }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                                @if($candidates > 0)
                                    <a class="sc-btn" href="{{ route('employer.ai.candidates', $vacancy) }}">
                                        {{ __('Кандидаты') }}
                                    </a>
                                @endif
                                <a class="sc-btn {{ $candidates > 0 ? 'sc-btn-ghost' : '' }}"
                                   href="{{ route('employer.ai.show', $vacancy) }}">
                                    {{ $config && $config->enabled ? __('Настроить') : __('Настроить и включить') }}
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </main>
</div>

@include('employer.partials.js')
</body>
</html>
