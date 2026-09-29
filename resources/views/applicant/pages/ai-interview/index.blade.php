<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Мои ИИ-собеседования') }}</title>
    @include('applicant.partials.css')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@600;700;800&family=Inter:wght@400;500;600&display=swap"
          rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1 { font-family: 'Manrope', 'Inter', sans-serif; }

        .iv-wrap { padding: 0 3vh 4vh 2vh; display: flex; flex-direction: column; gap: 16px; max-width: 900px; }
        .iv-lead { margin: 0; font-size: 13px; color: var(--gray-500); }

        .iv-row {
            display: grid; grid-template-columns: minmax(0,1fr) auto; gap: 14px; align-items: center;
            background: var(--white); border: 1px solid var(--gray-200);
            border-radius: var(--radius); padding: 14px 16px;
        }
        @media (max-width: 680px) { .iv-row { grid-template-columns: 1fr; } }

        .iv-title { font-weight: 700; color: var(--ink); overflow-wrap: anywhere; }
        .iv-meta { margin-top: 4px; font-size: 13px; color: var(--gray-500); }

        .iv-bar { margin-top: 8px; height: 6px; border-radius: 999px; background: var(--gray-100); overflow: hidden; }
        .iv-bar i { display: block; height: 100%; background: var(--indigo-600); }

        .iv-badge { display: inline-flex; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .iv-pass { background: var(--wash-good); color: var(--ink-good); }
        .iv-fail { background: var(--wash-danger); color: var(--danger); }
        .iv-wait { background: var(--wash-warn); color: var(--ink-warn); }
        .iv-go   { background: var(--gray-100); color: var(--gray-500); }

        .iv-btn {
            display: inline-flex; align-items: center; padding: 8px 14px; border-radius: 999px;
            font-size: 13px; font-weight: 600; text-decoration: none;
            background: var(--indigo-600); color: #fff;
        }
        .iv-empty {
            background: var(--white); border: 1px dashed var(--gray-300);
            border-radius: var(--radius); padding: 22px; text-align: center; color: var(--gray-500);
        }
        .iv-note { font-size: 13px; padding: 10px 12px; border-radius: 10px; background: var(--wash-good); color: var(--ink-good); }
    </style>
</head>
<body>
<div class="app">
    @include('applicant.partials.sidebar')

    <main class="main">
        <div class="iv-wrap">
            @if(session('status'))
                <div class="iv-note">{{ session('status') }}</div>
            @endif

            <div>
                <h1 style="margin:0 0 4px; font-size:20px;">{{ __('Мои ИИ-собеседования') }}</h1>
                <p class="iv-lead">
                    {{ __('Начатое собеседование можно продолжить с того места, где вы остановились: прогресс сохраняется.') }}
                </p>
            </div>

            @if($interviews->isEmpty())
                <div class="iv-empty">
                    {{ __('Собеседований пока нет. Они появляются, когда вы откликаетесь на вакансию, где отбор проводит ИИ.') }}
                    <div style="margin-top:12px">
                        <a class="iv-btn" href="{{ route('public.vacancies.index') }}">{{ __('Смотреть вакансии') }}</a>
                    </div>
                </div>
            @else
                @foreach($interviews as $interview)
                    <div class="iv-row">
                        <div>
                            <div class="iv-title">{{ $interview->vacancy?->title ?? __('Вакансия удалена') }}</div>
                            <div class="iv-meta">
                                {{ $interview->vacancy?->employer?->company_name }}
                                @if($interview->outcome)
                                    <span class="iv-badge {{ match($interview->outcome) {
                                        'passed' => 'iv-pass',
                                        'rejected' => 'iv-fail',
                                        default => 'iv-wait',
                                    } }}" style="margin-left:6px">{{ $interview->outcomeLabel() }}</span>
                                @else
                                    <span class="iv-badge iv-go" style="margin-left:6px">
                                        {{ $interview->stageLabel() }}
                                    </span>
                                @endif
                            </div>
                            <div class="iv-bar"><i style="width: {{ $interview->progress() }}%"></i></div>
                        </div>

                        <div>
                            @if($interview->isDecided())
                                <a class="iv-btn" href="{{ route('applicant.ai.result', $interview) }}">
                                    {{ __('Смотреть результат') }}
                                </a>
                            @else
                                <a class="iv-btn" href="{{ route('applicant.ai.interview', $interview) }}">
                                    {{ $interview->consented() ? __('Продолжить') : __('Начать') }}
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </main>
</div>

@include('applicant.partials.js')
@include('applicant.partials.script')
</body>
</html>
