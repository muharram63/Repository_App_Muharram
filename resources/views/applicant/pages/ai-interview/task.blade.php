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
            .tk-quiz { border: 1px solid var(--gray-200); border-radius: 10px; padding: 12px 14px; margin: 0 0 10px; }
        .tk-quiz legend { font-size: 14px; padding: 0 4px; line-height: 1.5; }
        .tk-option { display: flex; gap: 8px; align-items: flex-start; padding: 6px 2px; font-size: 14px; cursor: pointer; }
        .tk-option input { margin-top: 3px; }
        /* праздничная картинка после собеседования */
        .tk-finish { position: relative; height: 150px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .tk-badge-ring { stroke: #22C55E; stroke-dasharray: 330; stroke-dashoffset: 330; animation: tkRing .9s ease-out forwards; }
        .tk-badge-check { stroke: #22C55E; stroke-dasharray: 80; stroke-dashoffset: 80; animation: tkCheck .5s .8s ease-out forwards; }
        .tk-badge { animation: tkPop .5s 1.2s ease-out both; }
        .tk-confetti i {
            position: absolute; top: -12px; left: var(--x); width: 8px; height: 14px; border-radius: 2px;
            background: var(--c); opacity: 0; animation: tkFall 2.4s var(--d) ease-in forwards;
        }
        @keyframes tkRing { to { stroke-dashoffset: 0; } }
        @keyframes tkCheck { to { stroke-dashoffset: 0; } }
        @keyframes tkPop { 0% { transform: scale(1); } 50% { transform: scale(1.12); } 100% { transform: scale(1); } }
        @keyframes tkFall {
            0% { opacity: 1; transform: translateY(0) rotate(0); }
            100% { opacity: 0; transform: translateY(170px) rotate(540deg); }
        }
        @media (prefers-reduced-motion: reduce) {
            .tk-badge-ring, .tk-badge-check { animation: none; stroke-dashoffset: 0; }
            .tk-badge, .tk-confetti i { animation: none; }
            .tk-confetti { display: none; }
        }
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

            @if(! $task && ($testSkipped ?? false))
                {{-- Работодатель не включил задание в оценку: его и не дают. --}}
                <div class="tk-panel">
                    <h1>{{ __('Тестового задания по этой вакансии нет') }}</h1>
                    <p class="tk-hint">
                        {{ __('Решение принимается по документам и собеседованию. Итог появится в разделе «Мои ИИ-собеседования».') }}
                    </p>
                    <div class="tk-row">
                        <a class="tk-btn" href="{{ route('applicant.ai.index') }}">
                            {{ __('К моим собеседованиям') }}
                        </a>
                    </div>
                </div>
            @elseif(! $task && ($interview->requires_review || $interview->isDecided()))
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
                            {{ __('ИИ составляет задание по вашей специальности — обычно это несколько секунд. Страница обновится сама.') }}
                        </p>
                    </div>
                    <p class="tk-hint">
                        {{ __('Отсчёт времени начнётся, только когда задание появится на экране, — можно спокойно подождать.') }}
                    </p>
                </div>
            @elseif($submission && $submission->submitted_at)
                {{-- решения отправлены: Анна прощается, проверка идёт в фоне --}}
                <div class="tk-panel">
                    {{-- Праздничная картинка: беседа и задания позади. Анимация
                         одна, короткая, и не играет у тех, кто отключил её в системе. --}}
                    <div class="tk-finish" aria-hidden="true">
                        <div class="tk-confetti">
                            @for($i = 0; $i < 18; $i++)
                                <i style="--x: {{ ($i * 37) % 100 }}%; --d: {{ ($i % 6) * 0.12 }}s; --c: {{ ['#4F46E5', '#22C55E', '#F59E0B', '#EC4899', '#06B6D4', '#8B5CF6'][$i % 6] }}"></i>
                            @endfor
                        </div>
                        <svg class="tk-badge" viewBox="0 0 120 120" width="120" height="120">
                            <circle class="tk-badge-ring" cx="60" cy="60" r="52" fill="none" stroke-width="8"/>
                            <path class="tk-badge-check" d="M38 62 L54 78 L84 44" fill="none" stroke-width="9"
                                  stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>

                    <h1 style="text-align:center">{{ __('Собеседование закончено') }}</h1>

                    @if($farewell)
                        @include('partials.ai-avatar', [
                            'avatarName' => $assistantName,
                            'avatarRole' => __($assistantRole).', '.__('ИИ'),
                            'voiceKey' => 'interview-'.$interview->id,
                        ])
                        <div class="tk-statement" id="tkFarewell">{{ $farewell->text }}</div>
                    @endif

                    <p class="tk-hint" id="tkGradeState">
                        {{ __('Ваши ответы переданы на рассмотрение. Итог — принят ли вы на работу — придёт в уведомления в течение 24 часов. Эту страницу можно закрыть.') }}
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

                @php $answers = $submission->answers ?? []; @endphp
                @foreach($practical as $i => $item)
                    @php $given = $i === 0 ? $submission->content : ($answers['parts'][$i - 1] ?? ''); @endphp
                    @if(filled($given))
                        <div class="tk-panel">
                            <h2>{{ __('Ваше решение') }} · {{ __('задание') }} {{ $i + 1 }}</h2>
                            <div class="tk-statement" style="font-family: ui-monospace, Consolas, monospace; font-size:13px;">{{ $given }}</div>
                        </div>
                    @endif
                @endforeach
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
                {{-- тестовая часть открыта, идёт отсчёт --}}
                <div class="tk-panel">
                    <div class="tk-head">
                        <h1>{{ __('Тестовая часть') }}</h1>
                        <div class="tk-timer" id="tkTimer" data-left="{{ $task->secondsLeft() }}">—</div>
                    </div>
                    <div class="tk-row" style="justify-content:flex-start">
                        @if($quiz)
                            <span class="tk-chip">{{ __('Тест') }}: {{ count($quiz) }} {{ __('вопросов') }}</span>
                        @endif
                        <span class="tk-chip">{{ __('Практических заданий') }}: {{ count($practical) }}</span>
                        <span class="tk-chip">{{ $task->time_limit_minutes }} {{ __('мин') }} {{ __('на всё') }}</span>
                    </div>
                    <p class="tk-hint">
                        {{ __('Все вопросы и задания — по вашей специальности. Отвечайте в любом порядке, отправка — одна, в конце страницы.') }}
                    </p>
                </div>

                <form method="POST" action="{{ route('applicant.ai.task.submit', $interview) }}"
                      id="tkForm" style="display:flex; flex-direction:column; gap:16px;">
                    @csrf

                    @if($quiz)
                        <div class="tk-panel">
                            <h2>{{ __('Тест') }}</h2>
                            <p class="tk-hint">{{ __('В каждом вопросе один верный вариант.') }}</p>

                            @foreach($quiz as $i => $q)
                                <fieldset class="tk-quiz">
                                    <legend><b>{{ $i + 1 }}.</b> {{ $q['question'] }}</legend>
                                    @foreach($q['options'] as $o => $option)
                                        <label class="tk-option">
                                            <input type="radio" name="quiz[{{ $i }}]" value="{{ $o }}"
                                                   @checked((string) old('quiz.'.$i) === (string) $o)>
                                            <span>{{ $option }}</span>
                                        </label>
                                    @endforeach
                                </fieldset>
                            @endforeach
                        </div>
                    @endif

                    @foreach($practical as $i => $item)
                        <div class="tk-panel">
                            <h2>{{ __('Задание') }} {{ $i + 1 }}</h2>
                            <div class="tk-row" style="justify-content:flex-start">
                                <span class="tk-chip">{{ __(\App\Models\AiTestTask::FORMATS[$item['format']] ?? $item['format']) }}</span>
                                @if($item['language'])
                                    <span class="tk-chip">{{ $item['language'] }}</span>
                                @endif
                            </div>
                            <div class="tk-statement">{{ $item['statement'] }}</div>

                            @php $field = $i === 0 ? 'solution' : 'parts['.($i - 1).']'; @endphp
                            @php $oldKey = $i === 0 ? 'solution' : 'parts.'.($i - 1); @endphp
                            <textarea class="tk-area" name="{{ $field }}" data-draft="part-{{ $i }}"
                                      maxlength="{{ $maxSolution }}"
                                      placeholder="{{ $item['format'] === 'code'
                                          ? __('Вставьте код решения') : __('Напишите решение здесь') }}">{{ old($oldKey) }}</textarea>

                            @if($item['format'] === 'code')
                                <p class="tk-hint">
                                    {{ __('Код будет запущен и проверен по-настоящему, а не прочитан: важно, чтобы он работал.') }}
                                </p>
                            @endif
                        </div>
                    @endforeach

                    <div class="tk-panel">
                        <div class="tk-row">
                            <span class="tk-hint" id="tkSaved"></span>
                            <button type="submit" class="tk-btn" id="tkSend">{{ __('Отправить ответы') }}</button>
                        </div>
                        <p class="tk-hint">
                            {{ __('Отправить можно один раз — проверьте, что ответили на всё. Черновик сохраняется в браузере, пока вы отвечаете.') }}
                        </p>
                    </div>
                </form>
            @endif
        </div>
    </main>
</div>

@if($task && ! $task->expired() && ! ($submission && $submission->submitted_at))
<script>
    (function () {
        const timer = document.getElementById('tkTimer');

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
        // все поля сразу: тексты заданий и выбранные варианты теста
        function collect() {
            const data = {};
            form.querySelectorAll('textarea[data-draft]').forEach(function (a) { data[a.dataset.draft] = a.value; });
            form.querySelectorAll('input[type=radio]:checked').forEach(function (r) { data[r.name] = r.value; });

            return data;
        }

        try {
            const draft = JSON.parse(localStorage.getItem(key) || 'null');

            if (draft && typeof draft === 'object') {
                form.querySelectorAll('textarea[data-draft]').forEach(function (a) {
                    if (!a.value && draft[a.dataset.draft]) { a.value = draft[a.dataset.draft]; }
                });
                form.querySelectorAll('input[type=radio]').forEach(function (r) {
                    if (draft[r.name] === r.value && !form.querySelector('input[name="' + r.name + '"]:checked')) {
                        r.checked = true;
                    }
                });
            }
        } catch (e) {}

        let saveTimer = null;

        form.addEventListener('input', function () {
            clearTimeout(saveTimer);
            saveTimer = setTimeout(function () {
                try {
                    localStorage.setItem(key, JSON.stringify(collect()));
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
        && ! ($testSkipped ?? false)
        && (! $task|| ($submission && $submission->submitted_at && ! $submission->isGraded()));
@endphp

@if($task && $submission && $submission->submitted_at)
<script>
    // Решение принято — черновик в браузере больше не нужен. Убираем его
    // здесь, а не по нажатию кнопки: до этого места доходит только то, что
    // сервер действительно принял.
    try { localStorage.removeItem('ai-task-draft-{{ $task->id }}'); } catch (e) {}
</script>
@endif

@if($awaiting && ! $task)
<script>
    /*
     * Задания ещё нет — составляем его прямо сейчас, а не ждём очереди.
     *
     * Обычно оно готово заранее: страница разговора просила его в фоне. Если
     * кандидат пришёл раньше, этот запрос составит задание сам (до полуминуты)
     * — и страница покажет его. Модель занята — спросим ещё раз.
     */
    (function () {
        const url = @json(route('applicant.ai.task.prepare', $interview));
        const token = @json(csrf_token());

        function ask() {
            fetch(url, {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
            })
                .then(function (r) { return r.ok ? r.json() : {status: 'busy'}; })
                .then(function (data) {
                    if (data.status === 'busy') {
                        setTimeout(ask, 4000);
                    } else {
                        window.location.reload();
                    }
                })
                .catch(function () { setTimeout(ask, 4000); });
        }

        ask();
    })();
</script>
@endif

@if($submission && $submission->submitted_at)
<script>
    (function () {
        @if($farewell)
            /*
             * Анна прощается: собеседование закончено, ответ придёт в срок.
             * Звук готовится на сервере; если браузер не даст играть без
             * нажатия, аватар покажет кнопку «Озвучить».
             *
             * Только сразу после отправки. Раньше прощание звучало при каждом
             * открытии страницы — вернулся по уведомлению, обновил страницу, и
             * Анна прощается снова. Текст остаётся на экране, а услышать
             * его ещё раз можно кнопкой «Повторить».
             */
            if (window.AiAvatar) {
                const farewell = {
                    text: @json($farewell->text),
                    @if($farewell->speech_status !== 'none')
                        statusUrl: @json(route('applicant.ai.speech.status', $farewell)),
                    @endif
                    lang: @json($interview->config?->language ?? 'ru'),
                };

                window.AiAvatar[@json((bool) session('speak_farewell')) ? 'speak' : 'remember'](farewell);
            }
        @endif

        /*
         * Проверка и решение — без очереди.
         *
         * Раньше страница обещала «ИИ проверяет — до минуты» и просто
         * перезагружалась: проверку делала очередь, а без её обработчика не
         * делал никто. Теперь страница сама просит сервер сделать следующий
         * шаг — проверить тест, задания, принять решение, — пока ответ не
         * будет готов. Закрыть страницу можно: ответ придёт в уведомления.
         */
        const url = @json(route('applicant.ai.pump', $interview));
        const token = @json(csrf_token());
        // итог кандидату здесь не показываем: его сначала смотрит работодатель,
        // и ответ придёт в уведомления. Страница только доводит проверку до конца.

        function step() {
            fetch(url, {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!data) { setTimeout(step, 6000); return; }

                    if (data.decided) { return; }

                    // пограничный случай: Анна хочет кое-что уточнить
                    if (data.stage === 'interview') {
                        window.location.href = data.next_url;

                        return;
                    }

                    // проверка и подготовка отчёта закончены — дальше решает работодатель
                    if (data.graded && data.did === null && data.stage === 'done') { return; }

                    // шаг сделан — сразу следующий; ждём очередь или модель — реже
                    setTimeout(step, data.did && data.did !== 'busy' && data.did !== 'error' ? 800 : 5000);
                })
                .catch(function () { setTimeout(step, 6000); });
        }

        step();
    })();
</script>
@endif

@include('applicant.partials.js')
@include('applicant.partials.script')
</body>
</html>
