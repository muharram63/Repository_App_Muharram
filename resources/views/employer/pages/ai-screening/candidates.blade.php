<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Кандидаты') }}</title>
    @include('employer.partials.css')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@600;700;800&family=Inter:wght@400;500;600&display=swap"
          rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1 { font-family: 'Manrope', 'Inter', sans-serif; }

        .cd-wrap { padding: 0 3vh 4vh 2vh; display: flex; flex-direction: column; gap: 16px; }
        .cd-back { font-size: 13px; color: var(--gray-500); text-decoration: none; }
        .cd-back:hover { color: var(--indigo-600); }

        .cd-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; }
        .cd-stat {
            background: var(--white); border: 1px solid var(--gray-200);
            border-radius: var(--radius); padding: 12px 14px;
        }
        .cd-stat b { display: block; font-size: 22px; color: var(--ink); }
        .cd-stat span { font-size: 12px; color: var(--gray-500); }

        .cd-row {
            display: grid; grid-template-columns: minmax(0,1fr) auto auto; gap: 14px; align-items: center;
            background: var(--white); border: 1px solid var(--gray-200);
            border-radius: var(--radius); padding: 13px 16px;
        }
        @media (max-width: 760px) { .cd-row { grid-template-columns: 1fr; } }
        .cd-row[data-manual="1"] { border-color: var(--ink-warn); }

        .cd-name { font-weight: 700; color: var(--ink); overflow-wrap: anywhere; }
        .cd-meta { margin-top: 3px; font-size: 13px; color: var(--gray-500); }

        .cd-mark { padding: 3px 11px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .cd-passed { background: var(--wash-good); color: var(--ink-good); }
        .cd-rejected { background: var(--wash-danger); color: var(--danger); }
        .cd-manual { background: var(--wash-warn); color: var(--ink-warn); }
        .cd-running { background: var(--gray-100); color: var(--gray-500); }

        .cd-total { font-size: 20px; font-weight: 700; color: var(--ink); text-align: right; }
        .cd-total small { display: block; font-size: 11px; font-weight: 500; color: var(--gray-500); }

        .cd-btn {
            display: inline-flex; align-items: center; padding: 8px 14px; border-radius: 999px;
            font-size: 13px; font-weight: 600; text-decoration: none;
            background: var(--indigo-600); color: #fff;
        }
        .cd-empty {
            background: var(--white); border: 1px dashed var(--gray-300);
            border-radius: var(--radius); padding: 22px; text-align: center; color: var(--gray-500);
        }
        .cd-note { font-size: 13px; padding: 10px 12px; border-radius: 10px;
                   background: var(--wash-good); color: var(--ink-good); }
    </style>
</head>
<body>
<div class="app">
    @include('employer.partials.sidebar')

    <main class="main">
        <div class="cd-wrap">
            <a class="cd-back" href="{{ route('employer.ai.show', $vacancy) }}">← {{ __('К настройкам вакансии') }}</a>

            @if(session('status'))
                <div class="cd-note">{{ session('status') }}</div>
            @endif

            <div>
                <h1 style="margin:0 0 4px; font-size:20px;">{{ __('Кандидаты') }}</h1>
                <div style="font-size:13px; color:var(--gray-500)">{{ $vacancy->title }}</div>
            </div>

            <div class="cd-stats">
                <div class="cd-stat"><b>{{ $counts['all'] }}</b><span>{{ __('всего') }}</span></div>
                <div class="cd-stat"><b>{{ $counts['passed'] }}</b><span>{{ __('прошли отбор') }}</span></div>
                <div class="cd-stat"><b>{{ $counts['rejected'] }}</b><span>{{ __('отказано') }}</span></div>
                <div class="cd-stat"><b>{{ $counts['manual'] }}</b><span>{{ __('ждут вас') }}</span></div>
                <div class="cd-stat"><b>{{ $counts['running'] }}</b><span>{{ __('в процессе') }}</span></div>
            </div>

            @if($interviews->isEmpty())
                <div class="cd-empty">
                    {{ __('По этой вакансии пока никто не проходил ИИ-собеседование.') }}
                </div>
            @else
                @foreach($interviews as $interview)
                    <div class="cd-row" data-manual="{{ $interview->requires_review ? '1' : '0' }}">
                        <div>
                            <div class="cd-name">
                                {{ $interview->applicant?->user?->name ?? __('Кандидат удалён') }}
                            </div>
                            <div class="cd-meta">
                                @if($interview->outcome)
                                    <span class="cd-mark {{ match($interview->outcome) {
                                        'passed' => 'cd-passed',
                                        'rejected' => 'cd-rejected',
                                        default => 'cd-manual',
                                    } }}">{{ $interview->outcomeLabel() }}</span>
                                @else
                                    <span class="cd-mark cd-running">{{ $interview->stageLabel() }}</span>
                                @endif

                                @if($interview->requires_review)
                                    <span class="cd-mark cd-manual">{{ __('нужно ваше решение') }}</span>
                                @endif

                                @if($interview->latestDecision?->isOverridden())
                                    <span class="cd-mark cd-running">{{ __('решение изменено вами') }}</span>
                                @endif

                                <span style="margin-left:6px">
                                    {{ $interview->decided_at?->format('d.m.Y') ?? $interview->updated_at->format('d.m.Y') }}
                                </span>
                            </div>
                        </div>

                        <div class="cd-total">
                            {{ $interview->total_score ?? '—' }}
                            <small>{{ __('итог') }}</small>
                        </div>

                        <a class="cd-btn" href="{{ route('employer.ai.candidate', [$vacancy, $interview]) }}">
                            {{ __('Отчёт') }}
                        </a>
                    </div>
                @endforeach
            @endif
        </div>
    </main>
</div>

@include('employer.partials.js')
</body>
</html>
