<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Собеседование с ИИ') }}</title>
    @include('applicant.partials.css')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@600;700;800&family=Inter:wght@400;500;600&display=swap"
          rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2 { font-family: 'Manrope', 'Inter', sans-serif; }

        .ch-wrap { padding: 0 3vh 3vh 2vh; display: grid; gap: 16px;
                   grid-template-columns: minmax(0, 300px) minmax(0, 1fr); align-items: start; }
        @media (max-width: 900px) { .ch-wrap { grid-template-columns: 1fr; } }

        .ch-side { display: flex; flex-direction: column; gap: 14px; position: sticky; top: 12px; }
        @media (max-width: 900px) { .ch-side { position: static; } }

        .ch-panel {
            background: var(--white); border: 1px solid var(--gray-200);
            border-radius: var(--radius); padding: 16px;
            display: flex; flex-direction: column; gap: 10px; min-width: 0;
        }
        .ch-panel h2 { margin: 0; font-size: 14px; color: var(--ink); }
        .ch-hint { margin: 0; font-size: 12px; color: var(--gray-500); }

        .ch-bar { height: 6px; border-radius: 999px; background: var(--gray-100); overflow: hidden; }
        .ch-bar i { display: block; height: 100%; background: var(--indigo-600); transition: width .4s ease; }
        .ch-count { font-size: 13px; color: var(--ink); font-weight: 600; }

        /* ---------- лента ---------- */
        .ch-feed {
            background: var(--white); border: 1px solid var(--gray-200);
            border-radius: var(--radius); padding: 18px;
            display: flex; flex-direction: column; gap: 14px; min-width: 0;
        }
        .ch-msg { display: flex; gap: 10px; max-width: 92%; }
        .ch-msg[data-who="me"] { margin-left: auto; flex-direction: row-reverse; }

        .ch-who {
            flex: none; width: 34px; height: 34px; border-radius: 50%;
            display: grid; place-items: center; font-size: 12px; font-weight: 700;
            background: var(--indigo-50); color: var(--indigo-700);
        }
        .ch-msg[data-who="me"] .ch-who { background: var(--gray-100); color: var(--gray-500); }

        .ch-body {
            padding: 11px 14px; border-radius: 14px; font-size: 14px; line-height: 1.55;
            background: var(--gray-100); color: var(--ink);
            overflow-wrap: anywhere; white-space: pre-wrap; min-width: 0;
        }
        .ch-msg[data-who="ai"] .ch-body { background: var(--indigo-50); color: var(--ink); }
        .ch-kind { display: block; margin-top: 5px; font-size: 11px; color: var(--gray-500); }

        .ch-typing { display: flex; gap: 4px; padding: 13px 14px; }
        .ch-typing i {
            width: 7px; height: 7px; border-radius: 50%; background: var(--gray-300);
            animation: ch-dot 1.2s infinite ease-in-out;
        }
        .ch-typing i:nth-child(2) { animation-delay: .15s; }
        .ch-typing i:nth-child(3) { animation-delay: .3s; }
        @keyframes ch-dot { 0%,100% { opacity: .3; transform: translateY(0); }
                            50% { opacity: 1; transform: translateY(-3px); } }

        /* ---------- ввод ---------- */
        .ch-form { display: flex; flex-direction: column; gap: 10px; }
        .ch-area {
            width: 100%; min-height: 96px; resize: vertical; font: inherit; font-size: 14px;
            padding: 11px 13px; border-radius: 12px; color: var(--ink);
            border: 1px solid var(--gray-300); background: var(--white);
        }
        .ch-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; }
        .ch-btn {
            display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px;
            border-radius: 999px; cursor: pointer; font: inherit; font-size: 14px; font-weight: 600;
            background: var(--indigo-600); color: #fff; border: 1px solid transparent;
        }
        .ch-btn:hover { filter: brightness(1.06); }
        .ch-btn:disabled { opacity: .55; cursor: default; filter: none; }
        .ch-left { font-size: 12px; color: var(--gray-500); }

        .ch-note { font-size: 13px; padding: 10px 12px; border-radius: 10px; }
        .ch-bad { background: var(--wash-danger); color: var(--danger); }
        .ch-good { background: var(--wash-good); color: var(--ink-good); }
        .ch-warn { background: var(--wash-warn); color: var(--ink-warn); }

        .ch-done { display: flex; flex-direction: column; gap: 10px; }
    </style>
</head>
<body>
<div class="app">
    @include('applicant.partials.sidebar')

    <main class="main">
        <div class="ch-wrap">
            {{-- ==================== слева: аватар и прогресс ==================== --}}
            <div class="ch-side">
                @include('partials.ai-avatar', [
                    'avatarName' => $assistantName,
                    'avatarRole' => __($assistantRole).', ' . __('ИИ'),
                ])

                <div class="ch-panel">
                    <h2>{{ __('Как идёт разговор') }}</h2>
                    <div class="ch-count">
                        <span id="chAsked">{{ $asked }}</span> {{ __('из') }} {{ $target }}
                        {{ __('вопросов') }}
                    </div>
                    <div class="ch-bar">
                        <i id="chBar" style="width: {{ $target > 0 ? min(100, round($asked / $target * 100)) : 0 }}%"></i>
                    </div>
                    <p class="ch-hint">
                        {{ __('Отвечайте своими словами и по возможности с примерами. Пропустить вопрос можно — напишите об этом прямо.') }}
                    </p>
                </div>

                <div class="ch-panel">
                    <h2>{{ __('Что важно знать') }}</h2>
                    <p class="ch-hint">
                        {{ __('Разговор ведёт ИИ-ассистент, а не человек. Прогресс сохраняется: можно закрыть страницу и вернуться.') }}
                    </p>
                    <p class="ch-hint">
                        {{ __('Оценки ответов вам не показываются — их увидит работодатель в отчёте.') }}
                    </p>
                    <p class="ch-hint">
                        {{ __('Вакансия:') }} {{ $vacancy->title }}
                    </p>
                </div>
            </div>

            {{-- ==================== справа: лента и ввод ==================== --}}
            <div style="display:flex; flex-direction:column; gap:14px; min-width:0;">
                <div class="ch-feed" id="chFeed">
                    @forelse($turns as $turn)
                        <div class="ch-msg" data-who="{{ $turn->isFromCandidate() ? 'me' : 'ai' }}">
                            <div class="ch-who">
                                {{ $turn->isFromCandidate()
                                    ? mb_substr($user->name, 0, 1)
                                    : mb_substr($assistantName, 0, 1) }}
                            </div>
                            <div class="ch-body">{{ $turn->text }}@if($turn->questionKindLabel())<span class="ch-kind">{{ $turn->questionKindLabel() }}</span>@endif</div>
                        </div>
                    @empty
                        {{-- реплик нет: первый вопрос запросит скрипт --}}
                    @endforelse

                    <div class="ch-msg" data-who="ai" id="chTyping" hidden>
                        <div class="ch-who">{{ mb_substr($assistantName, 0, 1) }}</div>
                        <div class="ch-body ch-typing"><i></i><i></i><i></i></div>
                    </div>
                </div>

                <div class="ch-note ch-bad" id="chError" hidden></div>

                <div class="ch-panel ch-done" id="chDone" hidden>
                    <h2>{{ __('Собеседование пройдено') }}</h2>
                    <p class="ch-hint">
                        {{ __('Спасибо за разговор. Дальше — тестовое задание, эта часть ещё готовится. Итог вы увидите в разделе «Мои ИИ-собеседования».') }}
                    </p>
                    <div>
                        <a class="ch-btn" href="{{ route('applicant.ai.index') }}">
                            {{ __('К моим собеседованиям') }}
                        </a>
                    </div>
                </div>

                <form class="ch-panel ch-form" id="chForm">
                    <label for="chText" style="font-size:12px; font-weight:600; color:var(--gray-500)">
                        {{ __('Ваш ответ') }}
                    </label>
                    <textarea class="ch-area" id="chText" maxlength="{{ $maxAnswer }}"
                              placeholder="{{ __('Отвечайте так, как рассказали бы коллеге') }}"></textarea>
                    <div class="ch-row">
                        <span class="ch-left" id="chLeft"></span>
                        <button type="submit" class="ch-btn" id="chSend">{{ __('Отправить') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
    (function () {
        const feed = document.getElementById('chFeed');
        const typing = document.getElementById('chTyping');
        const form = document.getElementById('chForm');
        const text = document.getElementById('chText');
        const send = document.getElementById('chSend');
        const left = document.getElementById('chLeft');
        const error = document.getElementById('chError');
        const done = document.getElementById('chDone');
        const askedOut = document.getElementById('chAsked');
        const bar = document.getElementById('chBar');

        const token = @json(csrf_token());
        const urls = {
            begin: @json(route('applicant.ai.chat.begin', $interview)),
            answer: @json(route('applicant.ai.chat.answer', $interview)),
        };
        const initials = @json(mb_substr($user->name, 0, 1));
        const aiInitial = @json(mb_substr($assistantName, 0, 1));
        const target = @json($target);
        const maxAnswer = @json($maxAnswer);
        const voiceEnabled = @json($voiceEnabled);

        let busy = false;
        // номер вопроса, на который отвечаем: по нему сервер отличает
        // двойное нажатие от нового ответа
        let questionId = @json(optional($turns->where("role", "ai")->last())->id);

        function paintLeft() {
            const used = text.value.length;
            left.textContent = used > maxAnswer - 400
                ? used + ' / ' + maxAnswer
                : '';
        }

        function scroll() {
            window.scrollTo({top: document.body.scrollHeight, behavior: 'smooth'});
        }

        function bubble(who, body, kind) {
            const wrap = document.createElement('div');
            wrap.className = 'ch-msg';
            wrap.dataset.who = who;

            const avatar = document.createElement('div');
            avatar.className = 'ch-who';
            avatar.textContent = who === 'me' ? initials : aiInitial;

            const text = document.createElement('div');
            text.className = 'ch-body';
            text.textContent = body;

            if (kind) {
                const label = document.createElement('span');
                label.className = 'ch-kind';
                label.textContent = kind;
                text.appendChild(label);
            }

            wrap.append(avatar, text);
            feed.insertBefore(wrap, typing);

            return wrap;
        }

        function showError(message) {
            error.textContent = message;
            error.hidden = false;
        }

        function hideError() { error.hidden = true; }

        function waiting(on) {
            busy = on;
            typing.hidden = !on;
            send.disabled = on;
            text.disabled = on;

            // Пока реплика готовится, аватар думает. Это же состояние он
            // держит, пока догоняет озвучка.
            if (window.AiAvatar) {
                window.AiAvatar.setState(on ? 'thinking' : 'idle');
            }

            if (on) { scroll(); }
        }

        /** Озвучить вопрос: файл с сервера, иначе голосом браузера. */
        function speak(question) {
            if (!window.AiAvatar) { return; }

            if (!voiceEnabled || question.speech_status === 'none') {
                window.AiAvatar.speak({text: question.text});

                return;
            }

            // Синтез идёт в очереди: аватар подождёт, спрашивая состояние, а
            // не дёрнет пустой файл.
            window.AiAvatar.speak({
                text: question.text,
                statusUrl: question.speech_state_url,
            });
        }

        function paintProgress(asked) {
            askedOut.textContent = asked;
            bar.style.width = target > 0 ? Math.min(100, Math.round(asked / target * 100)) + '%' : '0%';
        }

        function finish() {
            form.hidden = true;
            done.hidden = false;
            if (window.AiAvatar) { window.AiAvatar.setState('idle'); }
            scroll();
        }

        function ask(url, payload) {
            hideError();
            waiting(true);

            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload || {}),
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return {ok: response.ok, data: data};
                    });
                })
                .then(function (result) {
                    waiting(false);

                    if (!result.ok) {
                        showError(result.data.error || @json(__('Не удалось продолжить. Попробуйте ещё раз.')));

                        // Разговор передан человеку — продолжать нечего, но
                        // ответы сохранены, и об этом сказано прямо.
                        if (result.data.handover) { form.hidden = true; }

                        return;
                    }

                    if (result.data.already) { return; }

                    if (result.data.question) {
                        questionId = result.data.question.id;
                        bubble('ai', result.data.question.text, result.data.question.kind);
                        paintProgress(result.data.asked);
                        speak(result.data.question);
                        scroll();
                    }

                    if (result.data.done) { finish(); }
                })
                .catch(function () {
                    waiting(false);
                    showError(@json(__('Связь прервалась. Ответы сохранены — попробуйте ещё раз.')));
                });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            const value = text.value.trim();

            if (!value || busy) { return; }

            bubble('me', value, null);
            text.value = '';
            paintLeft();
            scroll();

            // кандидат ответил — аватар слушает, пока идёт обработка
            if (window.AiAvatar) { window.AiAvatar.setState('listening'); }

            ask(urls.answer, {text: value, question_id: questionId});
        });

        // Ctrl+Enter отправляет: привычно тем, кто печатает много
        text.addEventListener('keydown', function (event) {
            if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                form.requestSubmit();
            }
        });

        text.addEventListener('input', paintLeft);
        paintLeft();

        // Разговор ещё не начался — просим первую реплику. Страница при этом
        // уже открыта: ждать её отрисовки из-за модели неправильно.
        if (!feed.querySelector('.ch-msg[data-who]:not(#chTyping)')) {
            ask(urls.begin, {});
        } else {
            scroll();
        }
    })();
</script>

@include('applicant.partials.js')
@include('applicant.partials.script')
</body>
</html>
