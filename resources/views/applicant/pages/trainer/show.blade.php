<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Разбор тренировки') }}</title>
    @include('applicant.partials.css')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    @include('applicant.pages.trainer._style')
    <style>
        .tr-scores { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; }
        .tr-scores div { padding: 12px 14px; border-radius: 12px; background: var(--gray-100); }
        .tr-scores b { display: block; font-size: 24px; color: var(--ink); }
        .tr-scores span { font-size: 12.5px; color: var(--gray-500); }
        .tr-q { border-top: 1px solid var(--gray-100); padding-top: 12px; display: flex; flex-direction: column; gap: 8px; }
        .tr-q:first-of-type { border-top: 0; padding-top: 0; }
        .tr-q p { margin: 0; font-size: 14px; line-height: 1.55; color: var(--ink); white-space: pre-line; }
        .tr-label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: var(--gray-500); }
        .tr-better { padding: 10px 12px; border-radius: 10px; background: var(--indigo-50); color: var(--ink); font-size: 14px; line-height: 1.55; white-space: pre-line; }
        .tr-code { margin: 0; padding: 12px; border-radius: 10px; background: var(--gray-100); color: var(--ink); font-size: 13px; overflow-x: auto; white-space: pre-wrap; overflow-wrap: anywhere; }
        .tr-two { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 640px) { .tr-two { grid-template-columns: 1fr; } }
    </style>
    @if($session->isFinished() && ! $feedback)
        {{-- разбор готовится: страница обновится сама --}}
        <meta http-equiv="refresh" content="15">
    @endif
</head>
<body>
<div class="app">
    @include('applicant.partials.sidebar')

    @php
        $tone = fn (?int $s) => $s === null ? '' : ($s >= 70 ? 'ok' : ($s >= 40 ? 'mid' : 'low'));
    @endphp

    <main class="main">
        <div class="tr-wrap">
            <div class="tr-row"><a class="tr-btn tr-ghost" href="{{ route('applicant.trainer.index') }}">← {{ __('К тренажёру') }}</a></div>

            <div class="tr-head">
                <div>
                    <h1>{{ $session->track_title }} · {{ $session->levelLabel() }}</h1>
                    <p class="tr-lead">{{ $session->created_at->format('d.m.Y H:i') }} · {{ $session->statusLabel() }}</p>
                </div>
                @if($session->score !== null)
                    <span class="tr-score {{ $tone($session->score) }}" style="font-size:20px">{{ $session->score }}/100</span>
                @endif
            </div>

            @if($session->isRefunded())
                {{-- возвращают и автоматически (сбой ИИ), и вручную администратор — текст подходит обоим --}}
                <div class="tr-note tr-warn">
                    {{ __('Тренировка не засчитана: попытка вернулась на счёт — можно пройти заново.') }}
                </div>
            @elseif($session->isAborted())
                <div class="tr-note tr-warn">
                    {{ __('Тренировка прервана: собеседование по ней удалено. Продолжить её нельзя — начните новую.') }}
                </div>
            @elseif(! $session->isFinished())
                <div class="tr-card">
                    <h2>{{ __('Тренировка идёт') }}</h2>
                    <p class="tr-muted">{{ __('Можно вернуться к Анне и продолжить с того же места. Разбор появится здесь, когда закончите задание.') }}</p>
                    <div class="tr-row">
                        <a class="tr-btn" href="{{ route('applicant.ai.interview', $session->ai_interview_id) }}">{{ __('Продолжить тренировку') }}</a>
                    </div>
                </div>
            @elseif(! $feedback)
                <div class="tr-note tr-warn">
                    {{ __('Готовим разбор — обычно это меньше минуты. Страница обновится сама.') }}
                </div>
            @else
                {{-- ==================== итог ==================== --}}
                <div class="tr-card">
                    <h2>{{ __('Итог') }}</h2>
                    <p style="margin:0; font-size:15px; line-height:1.6; color:var(--ink)">{{ $feedback['summary'] ?? '' }}</p>
                    <div class="tr-scores">
                        <div><b>{{ $feedback['scores']['total'] ?? '—' }}</b><span>{{ __('Итог из 100') }}</span></div>
                        <div><b>{{ $feedback['scores']['interview'] ?? '—' }}</b><span>{{ __('Разговор с Анной') }}</span></div>
                        <div><b>{{ $feedback['scores']['test'] ?? '—' }}</b><span>{{ __('Тест и задания') }}</span></div>
                    </div>
                </div>

                @if(! empty($feedback['strengths']) || ! empty($feedback['improvements']))
                    <div class="tr-two">
                        <div class="tr-card">
                            <h2>✅ {{ __('Что получилось') }}</h2>
                            <ul class="tr-list">
                                @forelse($feedback['strengths'] ?? [] as $item)<li>{{ $item }}</li>@empty<li class="tr-muted">—</li>@endforelse
                            </ul>
                        </div>
                        <div class="tr-card">
                            <h2>🎯 {{ __('Что подтянуть') }}</h2>
                            <ul class="tr-list">
                                @forelse($feedback['improvements'] ?? [] as $item)<li>{{ $item }}</li>@empty<li class="tr-muted">—</li>@endforelse
                            </ul>
                        </div>
                    </div>
                @endif

                {{-- ==================== вопросы ==================== --}}
                @if(! empty($feedback['questions']))
                    <div class="tr-card">
                        <h2>{{ __('Вопросы и ответы') }}</h2>
                        @foreach($feedback['questions'] as $i => $q)
                            <div class="tr-q">
                                <div class="tr-row" style="justify-content:space-between">
                                    <b>{{ __('Вопрос :n', ['n' => $i + 1]) }}</b>
                                    @if($q['score'] !== null)
                                        <span class="tr-score {{ $tone((int) round($q['score'] / max(1, $q['max']) * 100)) }}">{{ $q['score'] }}/{{ $q['max'] }}</span>
                                    @endif
                                </div>
                                <p>{{ $q['question'] }}</p>
                                <span class="tr-label">{{ __('Ваш ответ') }}</span>
                                <p class="tr-muted">{{ $q['answer'] !== '' ? $q['answer'] : '—' }}</p>
                                @if(filled($q['comment']))
                                    <span class="tr-label">{{ __('Разбор') }}</span>
                                    <p>{{ $q['comment'] }}</p>
                                @endif
                                @if(filled($q['better']))
                                    <span class="tr-label">{{ __('Как ответить сильнее') }}</span>
                                    <div class="tr-better">{{ $q['better'] }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- ==================== тест ==================== --}}
                @if(! empty($feedback['quiz']))
                    <div class="tr-card">
                        <h2>{{ __('Тест: верно :correct из :total', ['correct' => $feedback['quiz']['correct'], 'total' => $feedback['quiz']['total']]) }}</h2>
                        @forelse($feedback['quiz']['mistakes'] as $m)
                            <div class="tr-q">
                                <p><b>{{ $m['question'] }}</b></p>
                                <p class="tr-muted">{{ __('Ваш ответ') }}: {{ $m['yours'] ?? '—' }}</p>
                                <p style="color:var(--ink-good)">{{ __('Верно') }}: {{ $m['right'] ?? '—' }}</p>
                            </div>
                        @empty
                            <p class="tr-muted">{{ __('Ошибок в тесте нет — отлично!') }}</p>
                        @endforelse
                    </div>
                @endif

                {{-- ==================== задания ==================== --}}
                @foreach($feedback['tasks'] ?? [] as $i => $task)
                    <div class="tr-card">
                        <div class="tr-row" style="justify-content:space-between">
                            <h2>{{ __('Задание :n', ['n' => $i + 1]) }}</h2>
                            @if($task['score'] !== null)
                                <span class="tr-score {{ $tone($task['score']) }}">{{ $task['score'] }}/100</span>
                            @endif
                        </div>
                        <p class="tr-muted" style="white-space:pre-line; margin:0">{{ $task['statement'] }}</p>
                        @if(filled($task['summary']))<p style="margin:0; color:var(--ink)">{{ $task['summary'] }}</p>@endif
                        @if(! empty($task['review']))
                            <ul class="tr-list" style="list-style:none; padding-left:0">
                                @foreach($task['review'] as $r)
                                    <li>{{ $r['passed'] ? '✅' : '❌' }} {{ $r['point'] }}@if(filled($r['note'] ?? null)) — <span class="tr-muted">{{ $r['note'] }}</span>@endif</li>
                                @endforeach
                            </ul>
                        @endif
                        @if($task['quality'])
                            <p class="tr-muted" style="margin:0">
                                {{ __('Качество кода: :score из 100', ['score' => $task['quality']['score'] ?? '—']) }}.
                                {{ $task['quality']['notes'] ?? '' }}
                            </p>
                        @endif
                        @if(filled($task['better']))
                            <span class="tr-label">{{ __('Как решить правильно') }}</span>
                            <div class="tr-better">{{ $task['better'] }}</div>
                        @endif
                        @if(filled($task['reference']))
                            <details>
                                <summary class="tr-label" style="cursor:pointer">{{ __('Показать эталонное решение') }}</summary>
                                <pre class="tr-code">{{ $task['reference'] }}</pre>
                            </details>
                        @endif
                    </div>
                @endforeach

                {{-- ==================== дальше ==================== --}}
                @if(! empty($feedback['next_steps']))
                    <div class="tr-card">
                        <h2>🚀 {{ __('Что сделать до следующей тренировки') }}</h2>
                        <ol class="tr-list">
                            @foreach($feedback['next_steps'] as $step)<li>{{ $step }}</li>@endforeach
                        </ol>
                    </div>
                @endif

                <div class="tr-row">
                    <a class="tr-btn" href="{{ route('applicant.trainer.index') }}#start">{{ __('Ещё одна тренировка') }}</a>
                    @if(($feedback['source'] ?? '') === 'basic')
                        <span class="tr-muted">{{ __('Разбор собран по оценкам без подробного текста ИИ — модель была недоступна.') }}</span>
                    @endif
                </div>
            @endif
        </div>
    </main>
</div>
</body>
</html>
