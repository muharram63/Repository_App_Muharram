<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Проверка аватара') }}</title>
    @include('applicant.partials.css')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@600;700;800&family=Inter:wght@400;500;600&display=swap"
          rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2 { font-family: 'Manrope', 'Inter', sans-serif; }

        .pv-wrap { padding: 0 3vh 4vh 2vh; display: flex; flex-direction: column; gap: 18px; }
        .pv-grid { display: grid; grid-template-columns: minmax(0, 340px) minmax(0, 1fr); gap: 18px; }
        @media (max-width: 900px) { .pv-grid { grid-template-columns: 1fr; } }

        .pv-panel {
            background: var(--white); border: 1px solid var(--gray-200);
            border-radius: var(--radius); padding: 18px;
            display: flex; flex-direction: column; gap: 12px; min-width: 0;
        }
        .pv-panel h2 { margin: 0; font-size: 15px; color: var(--ink); }
        .pv-hint { font-size: 13px; color: var(--gray-500); margin: 0; }

        .pv-area {
            width: 100%; min-height: 96px; resize: vertical; font: inherit; font-size: 14px;
            padding: 10px 12px; border-radius: 10px; color: var(--ink);
            border: 1px solid var(--gray-300); background: var(--white);
        }

        .pv-row { display: flex; flex-wrap: wrap; gap: 8px; }
        .pv-btn {
            padding: 8px 14px; border-radius: 999px; cursor: pointer; font: inherit;
            font-size: 13px; font-weight: 600; border: 1px solid transparent;
            background: var(--indigo-600); color: #fff;
        }
        .pv-btn-ghost { background: transparent; color: var(--gray-500); border-color: var(--gray-200); }
        .pv-btn:hover { filter: brightness(1.06); }

        .pv-log {
            font-family: ui-monospace, Consolas, monospace; font-size: 12px; line-height: 1.5;
            background: var(--gray-100); border-radius: 10px; padding: 12px;
            max-height: 260px; overflow: auto; color: var(--ink);
            white-space: pre-wrap; word-break: break-word; margin: 0;
        }

        .pv-facts { display: grid; gap: 6px; font-size: 13px; }
        .pv-fact { display: flex; justify-content: space-between; gap: 10px; }
        .pv-fact span:last-child { font-weight: 600; color: var(--ink); }
        .pv-off { color: var(--danger); }
        .pv-on { color: var(--ink-good, #15803D); }

        .pv-warn {
            font-size: 13px; padding: 10px 12px; border-radius: 10px;
            background: var(--wash-warn, #FEF3C7); color: var(--ink-warn, #B45309);
        }
    </style>
</head>
<body>
<div class="app">
    @include('applicant.partials.sidebar')

    <main class="main">
        <div class="pv-wrap">
            <div>
                <h1 style="margin:0 0 4px; font-size:20px;">{{ __('Проверка голосового аватара') }}</h1>
                <p class="pv-hint">
                    {{ __('Страница для проверки компонента: как звучит вопрос, как двигается рот и что делает аватар, если голоса нет. На самом собеседовании аватар встанет в страницу диалога.') }}
                </p>
            </div>

            @unless($ttsAvailable)
                <div class="pv-warn">
                    {{ __('Синтез речи Gemini недоступен: нет ключа или он выключен в настройках. Аватар будет читать текст голосом браузера — это и есть запасной режим.') }}
                </div>
            @endunless

            <div class="pv-grid">
                <div>
                    @include('partials.ai-avatar', [
                        'avatarName' => 'Анна',
                        'avatarRole' => __('HR-специалист, ИИ'),
                    ])
                </div>

                <div style="display:flex; flex-direction:column; gap:18px; min-width:0;">
                    <div class="pv-panel">
                        <h2>{{ __('Текст вопроса') }}</h2>
                        <textarea class="pv-area" id="pvText">{{ $sample }}</textarea>

                        <div class="pv-row">
                            <button type="button" class="pv-btn" id="pvSpeakServer">
                                {{ __('Озвучить через Gemini') }}
                            </button>
                            <button type="button" class="pv-btn pv-btn-ghost" id="pvSpeakBrowser">
                                {{ __('Озвучить браузером') }}
                            </button>
                            <button type="button" class="pv-btn pv-btn-ghost" id="pvStop">
                                {{ __('Остановить') }}
                            </button>
                        </div>

                        <p class="pv-hint">
                            {{ __('Через Gemini работает настоящий липсинк по громкости. Голосом браузера амплитуду получить нельзя в принципе, поэтому рот двигается по приближению — разница видна на глаз.') }}
                        </p>
                    </div>

                    <div class="pv-panel">
                        <h2>{{ __('Состояния') }}</h2>
                        <div class="pv-row">
                            <button type="button" class="pv-btn pv-btn-ghost" data-state="idle">{{ __('Покой') }}</button>
                            <button type="button" class="pv-btn pv-btn-ghost" data-state="thinking">{{ __('Думает') }}</button>
                            <button type="button" class="pv-btn pv-btn-ghost" data-state="listening">{{ __('Слушает') }}</button>
                            <button type="button" class="pv-btn pv-btn-ghost" data-state="speaking">{{ __('Говорит') }}</button>
                        </div>
                    </div>

                    <div class="pv-panel">
                        <h2>{{ __('Что умеет этот браузер') }}</h2>
                        <div class="pv-facts" id="pvFacts"></div>
                        <div class="pv-row">
                            <button type="button" class="pv-btn pv-btn-ghost" id="pvRefresh">
                                {{ __('Обновить') }}
                            </button>
                        </div>
                    </div>

                    <div class="pv-panel">
                        <h2>{{ __('Журнал') }}</h2>
                        <pre class="pv-log" id="pvLog"></pre>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    (function () {
        const text = document.getElementById('pvText');
        const log = document.getElementById('pvLog');
        const facts = document.getElementById('pvFacts');
        const previewUrl = @json(route('applicant.ai.speech.preview'));
        const token = @json(csrf_token());

        function say(line) {
            const time = new Date().toLocaleTimeString();
            log.textContent = '[' + time + '] ' + line + '\n' + log.textContent;
        }

        function paintFacts() {
            const d = window.AiAvatar ? window.AiAvatar.diagnostics() : {};
            const rows = [
                [@json(__('Web Audio API')), d.audioContext],
                [@json(__('Анализатор громкости поднят')), d.analyser],
                [@json(__('Голос браузера')), d.speechSynthesis],
            ];

            facts.innerHTML = '';
            rows.forEach(function (row) {
                const div = document.createElement('div');
                div.className = 'pv-fact';
                div.innerHTML = '<span>' + row[0] + '</span>'
                    + '<span class="' + (row[1] ? 'pv-on' : 'pv-off') + '">'
                    + (row[1] ? @json(__('есть')) : @json(__('нет'))) + '</span>';
                facts.appendChild(div);
            });

            const ctxRow = document.createElement('div');
            ctxRow.className = 'pv-fact';
            ctxRow.innerHTML = '<span>' + @json(__('Состояние звука')) + '</span><span>'
                + (d.contextState || '—') + '</span>';
            facts.appendChild(ctxRow);
        }

        document.getElementById('pvSpeakServer').addEventListener('click', function () {
            const value = text.value.trim();
            if (!value) { say(@json(__('Текст пуст.'))); return; }

            // пока идёт синтез, аватар думает — интерфейс не блокируется
            window.AiAvatar.setState('thinking');
            say(@json(__('Запрос озвучки…')));

            fetch(previewUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({text: value}),
            })
                .then(function (response) {
                    if (!response.ok) {
                        return response.json().then(function (data) {
                            throw new Error(data.error || @json(__('Синтез недоступен.')));
                        });
                    }

                    const cached = response.headers.get('X-Speech-Cached') === '1';
                    const ms = response.headers.get('X-Speech-Ms');
                    say(cached
                        ? @json(__('Взято из кэша, длительность ')) + ms + @json(__(' мс — сеть не потревожена.'))
                        : @json(__('Синтезировано, длительность ')) + ms + @json(__(' мс.')));

                    return response.blob();
                })
                .then(function (blob) {
                    const url = URL.createObjectURL(blob);
                    window.AiAvatar.speak({audioUrl: url, text: value});
                    setTimeout(paintFacts, 400);
                })
                .catch(function (error) {
                    // отказ синтеза не тупик: читаем голосом браузера
                    say(error.message + @json(__(' Читаю голосом браузера.')));
                    window.AiAvatar.speak({text: value});
                    setTimeout(paintFacts, 400);
                });
        });

        document.getElementById('pvSpeakBrowser').addEventListener('click', function () {
            const value = text.value.trim();
            if (!value) { say(@json(__('Текст пуст.'))); return; }

            say(@json(__('Голос браузера: амплитуды нет, рот по приближению.')));
            window.AiAvatar.speak({text: value});
            setTimeout(paintFacts, 400);
        });

        document.getElementById('pvStop').addEventListener('click', function () {
            window.AiAvatar.stop();
            say(@json(__('Остановлено.')));
        });

        document.querySelectorAll('[data-state]').forEach(function (button) {
            button.addEventListener('click', function () {
                window.AiAvatar.setState(button.dataset.state);
                say(@json(__('Состояние: ')) + button.dataset.state);
            });
        });

        document.getElementById('pvRefresh').addEventListener('click', paintFacts);

        paintFacts();
        say(@json(__('Страница готова.')));
    })();
</script>

@include('applicant.partials.js')
@include('applicant.partials.script')
</body>
</html>
