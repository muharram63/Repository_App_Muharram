<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Тестовое задание') }}</title>
    @include('applicant.partials.css')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@600;700;800&family=Inter:wght@400;500;600&display=swap"
          rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2 { font-family: 'Manrope', 'Inter', sans-serif; }

        .tk-wrap { padding: 0 3vh 4vh 2vh; display: flex; flex-direction: column; gap: 16px; max-width: 980px; }
        .tk-panel {
            background: var(--white); border: 1px solid var(--gray-200);
            border-radius: var(--radius); padding: 18px;
            display: flex; flex-direction: column; gap: 12px; min-width: 0;
        }
        .tk-panel h1 { margin: 0; font-size: 20px; color: var(--ink); }
        .tk-panel h2 { margin: 0; font-size: 15px; color: var(--ink); }
        .tk-hint { margin: 0; font-size: 13px; color: var(--gray-500); }

        .tk-head { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; }
        .tk-chip {
            padding: 4px 11px; border-radius: 999px; font-size: 12px; font-weight: 600;
            background: var(--indigo-50); color: var(--indigo-700);
        }

        /* таймер */
        .tk-timer {
            font-variant-numeric: tabular-nums; font-weight: 700; font-size: 20px;
            padding: 6px 14px; border-radius: 999px;
            background: var(--gray-100); color: var(--ink);
        }
        .tk-timer[data-low="1"] { background: var(--wash-warn); color: var(--ink-warn); }
        .tk-timer[data-low="2"] { background: var(--wash-danger); color: var(--danger); }

        .tk-statement {
            font-size: 15px; line-height: 1.65; color: var(--ink);
            white-space: pre-wrap; overflow-wrap: anywhere;
        }

        .tk-area {
            width: 100%; min-height: 260px; resize: vertical; font-size: 14px;
            font-family: ui-monospace, Consolas, monospace; line-height: 1.5;
            padding: 12px 14px; border-radius: 12px; color: var(--ink);
            border: 1px solid var(--gray-300); background: var(--white);
        }

        .tk-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; }
        .tk-btn {
            display: inline-flex; align-items: center; padding: 10px 18px; border-radius: 999px;
            cursor: pointer; font: inherit; font-size: 14px; font-weight: 600; text-decoration: none;
            background: var(--indigo-600); color: #fff; border: 1px solid transparent;
        }
        .tk-btn:hover { filter: brightness(1.06); }
        .tk-btn:disabled { opacity: .55; cursor: default; }
        .tk-btn-ghost { background: transparent; color: var(--gray-500); border-color: var(--gray-200); }

        .tk-note { font-size: 13px; padding: 10px 12px; border-radius: 10px; }
        .tk-good { background: var(--wash-good); color: var(--ink-good); }
        .tk-bad  { background: var(--wash-danger); color: var(--danger); }
        .tk-warn { background: var(--wash-warn); color: var(--ink-warn); }

        .tk-wait { display: flex; align-items: center; gap: 10px; }
        .tk-dots { display: flex; gap: 4px; }
        .tk-dots i { width: 7px; height: 7px; border-radius: 50%; background: var(--indigo-600);
                     animation: tk-dot 1.2s infinite ease-in-out; }
        .tk-dots i:nth-child(2) { animation-delay: .15s; }
        .tk-dots i:nth-child(3) { animation-delay: .3s; }
        @keyframes tk-dot { 0%,100% { opacity: .3; } 50% { opacity: 1; } }
    </style>
</head>
<body>
<div class="app">
    @include('applicant.partials.sidebar')

    <main class="main">
        <div class="tk-wrap">
            @if(session('status'))
                <div class="tk-note tk-good">{{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="tk-note tk-bad">{{ session('error') }}</div>
            @endif

            @if(! $task && ($interview->requires_review || $interview->isDecided()))
                {{-- Задания не будет: составить его не удалось. Молчать об этом
                     нельзя — человек иначе ждёт вечно. --}}
                <div class="tk-panel">
                    <h1>{{ __('Задание не понадобится') }}</h1>
                    <p class="tk-hint">
                        {{ __('Составить задание не удалось, и система не стала решать сама — вашу кандидатуру рассматривает человек.') }}
                    </p>
                    <p class="tk-hint">
                        {{ __('Ответ придёт в срок, обещанный работодателем. Ваши ответы на собеседовании сохранены.') }}
                    </p>
                    <div class="tk-row">
                        <a class="tk-btn" href="{{ route('applicant.ai.index') }}">
                            {{ __('К моим собеседованиям') }}
                        </a>
                    </div>
                </div>
            @elseif(! $task)
                {{-- Задание готовится. Самый долгий вызов в модуле: модель пишет
                     условие, эталон и рубрику разом. --}}
                <div class="tk-panel">
                    <h1>{{ __('Тестовое задание готовится') }}</h1>
                    <div class="tk-wait">
                        <span class="tk-dots"><i></i><i></i><i></i></span>
                        <p class="tk-hint" style="margin:0">
                            {{ __('ИИ составляет задание под эту вакансию — это занимает до минуты. Страница обновится сама.') }}
                        </p>
                    </div>
                    <p class="tk-hint">
                        {{ __('Отсчёт времени начнётся, только когда задание появится на экране, — можно спокойно подождать.') }}
                    </p>
                </div>
            @elseif($submission && $submission->submitted_at)
                {{-- решение отправлено --}}
                <div class="tk-panel">
                    <h1>{{ __('Решение отправлено') }}</h1>
                    <p class="tk-hint">
                        @if($submission->isGraded())
                            {{ __('ИИ проверил ваше решение. Итог по всему собеседованию появится в разделе «Мои ИИ-собеседования».') }}
                        @else
                            {{ __('ИИ проверяет решение — это занимает до минуты. Страница обновится сама.') }}
                        @endif
                    </p>
                    <p class="tk-hint">
                        {{ __('Оценка вам не показывается: её увидит работодатель в отчёте.') }}
                    </p>
                    <div class="tk-row">
                        <a class="tk-btn" href="{{ route('applicant.ai.index') }}">
                            {{ __('К моим собеседованиям') }}
                        </a>
                    </div>
                </div>

                <div class="tk-panel">
                    <h2>{{ __('Ваше решение') }}</h2>
                    <div class="tk-statement" style="font-family: ui-monospace, Consolas, monospace; font-size:13px;">{{ $submission->content }}</div>
                </div>
            @elseif($task->expired())
                <div class="tk-panel">
                    <h1>{{ __('Время на задание вышло') }}</h1>
                    <p class="tk-hint">
                        {{ __('Решение не было отправлено вовремя. Это не отказ: задание — лишь часть итога, и решение принимается по совокупности с документами и собеседованием.') }}
                    </p>
                    <div class="tk-row">
                        <a class="tk-btn" href="{{ route('applicant.ai.index') }}">
                            {{ __('К моим собеседованиям') }}
                        </a>
                    </div>
                </div>
            @else
                {{-- задание открыто, идёт отсчёт --}}
                <div class="tk-panel">
                    <div class="tk-head">
                        <h1>{{ __('Тестовое задание') }}</h1>
                        <div class="tk-timer" id="tkTimer" data-left="{{ $task->secondsLeft() }}">—</div>
                    </div>
                    <div class="tk-row" style="justify-content:flex-start">
                        <span class="tk-chip">{{ $task->formatLabel() }}</span>
                        @if($task->language)
                            <span class="tk-chip">{{ $task->language }}</span>
                        @endif
                        <span class="tk-chip">{{ $task->time_limit_minutes }} {{ __('мин') }}</span>
                    </div>
                    <div class="tk-statement">{{ $task->statement }}</div>
                </div>

                <form class="tk-panel" method="POST" action="{{ route('applicant.ai.task.submit', $interview) }}"
                      id="tkForm">
                    @csrf
                    <h2>{{ __('Ваше решение') }}</h2>
                    <textarea class="tk-area" name="solution" id="tkSolution"
                              maxlength="{{ $maxSolution }}"
                              placeholder="{{ $task->isCode()
                                  ? __('Вставьте код решения') : __('Напишите решение здесь') }}"
                              required>{{ old('solution') }}</textarea>

                    @if($task->isCode())
                        <p class="tk-hint">
                            {{ __('Код будет запущен и проверен по-настоящему, а не прочитан: важно, чтобы он работал.') }}
                        </p>
                    @endif

                    <div class="tk-row">
                        <span class="tk-hint" id="tkSaved"></span>
                        <button type="submit" class="tk-btn" id="tkSend">{{ __('Отправить решение') }}</button>
                    </div>
                    <p class="tk-hint">
                        {{ __('Отправить можно один раз. Черновик сохраняется в браузере, пока вы печатаете.') }}
                    </p>
                </form>
            @endif
        </div>
    </main>
</div>

@if($task && ! $task->expired() && ! ($submission && $submission->submitted_at))
<script>
    (function () {
        const timer = document.getElementById('tkTimer');
        const area = document.getElementById('tkSolution');
        const form = document.getElementById('tkForm');
        const saved = document.getElementById('tkSaved');
        const key = 'ai-task-draft-{{ $task->id }}';

        let left = parseInt(timer.dataset.left, 10) || 0;

        function paint() {
            const m = Math.floor(left / 60);
            const s = left % 60;
            timer.textContent = m + ':' + String(s).padStart(2, '0');

            // за пять минут предупреждаем, за минуту — тревожим
            timer.dataset.low = left <= 60 ? '2' : (left <= 300 ? '1' : '0');
        }

        paint();

        const tick = setInterval(function () {
            left--;

            if (left <= 0) {
                clearInterval(tick);
                timer.textContent = '0:00';
                // Время вышло: перезагружаем, и сервер закроет попытку сам.
                // Считать окончание на стороне браузера нельзя — часы у всех свои.
                window.location.reload();

                return;
            }

            paint();
        }, 1000);

        /*
         * Черновик в браузере. Ответ кандидат пишет десять минут, и потерять
         * его из-за случайно закрытой вкладки было бы обиднее всего. На сервер
         * не отправляем: недописанное решение — не решение.
         */
        try {
            const draft = localStorage.getItem(key);
            if (draft && !area.value) { area.value = draft; }
        } catch (e) {}

        let saveTimer = null;

        area.addEventListener('input', function () {
            clearTimeout(saveTimer);
            saveTimer = setTimeout(function () {
                try {
                    localStorage.setItem(key, area.value);
                    saved.textContent = @json(__('Черновик сохранён'));
                    setTimeout(function () { saved.textContent = ''; }, 1500);
                } catch (e) {}
            }, 600);
        });

        /*
         * Черновик снимаем не здесь.
         *
         * Кнопку могли нажать, а сервер — не принять решение: вышел срок,
         * сменилась стадия. Стирая черновик по нажатию, мы теряли работу
         * человека именно в тот момент, когда она ещё нужна. Отправленное
         * решение убирает черновик само — блоком ниже, который отдаётся уже
         * после приёма.
         */
        form.addEventListener('submit', function () {
            document.getElementById('tkSend').disabled = true;
        });
    })();
</script>
@endif

@php
    /*
     * Перезагружаемся, только пока чего-то ждём.
     *
     * Условие было «задания нет или решение не проверено», и этого хватало,
     * чтобы страница крутилась вечно: составление задания могло не удаться
     * совсем, и тогда задания не появлялось никогда. Пометка о ручной проверке
     * и объявленное решение означают, что ждать больше нечего.
     */
    $awaiting = ! $interview->requires_review && ! $interview->isDecided()
        && (! $task || ($submission && $submission->submitted_at && ! $submission->isGraded()));
@endphp

@if($task && $submission && $submission->submitted_at)
<script>
    // Решение принято — черновик в браузере больше не нужен. Убираем его
    // здесь, а не по нажатию кнопки: до этого места доходит только то, что
    // сервер действительно принял.
    try { localStorage.removeItem('ai-task-draft-{{ $task->id }}'); } catch (e) {}
</script>
@endif

@if($awaiting)
<script>
    // Ждём, пока очередь составит задание или проверит решение.
    setTimeout(function () { window.location.reload(); }, 5000);
</script>
@endif

@include('applicant.partials.js')
@include('applicant.partials.script')
</body>
</html>
