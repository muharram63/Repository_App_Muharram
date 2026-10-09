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

        /* голосовой режим */
        /* второстепенная кнопка: «Отвечать текстом», «Готово, отправить» */
        .ch-btn-ghost { background: var(--white); color: var(--indigo-600); border-color: var(--gray-300); }
        .ch-btn-ghost:hover { border-color: var(--indigo-600); filter: none; }

        /* голосовая панель — в своей рамке, как остальные блоки */
        .ch-voice {
            display: flex; align-items: center; flex-wrap: wrap; gap: 10px;
            padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: var(--radius);
            background: var(--white);
        }
        .ch-voice.is-listening { border-color: var(--green, #16A34A); }
        .ch-start h2 { font-size: 15px; }
        .ch-voice-toggle.is-on { background: var(--green, #16A34A); }
        .ch-voice-state { font-size: 13px; color: var(--gray-500); }
        /* статус бота — тот же текст, что у аватара (data-ai-state) */
        .ch-voice-status { font-size: 13px; font-weight: 700; color: var(--indigo-600); }
        .ch-voice-status[data-ai-state="listening"] { color: var(--green, #16A34A); }
        .ch-voice-status[data-ai-state="listening"]::before {
            content: ''; display: inline-block; width: 8px; height: 8px; margin-right: 6px;
            border-radius: 50%; background: var(--green, #16A34A); vertical-align: middle;
            animation: chPulse 1.2s ease-in-out infinite;
        }
        @keyframes chPulse { 0%, 100% { opacity: 1; } 50% { opacity: .25; } }
        .ch-voice-level { width: 90px; height: 6px; border-radius: 99px; background: var(--gray-200); overflow: hidden; }
        .ch-voice-level i { display: block; height: 100%; width: 0; background: var(--green, #16A34A); transition: width .1s; }
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
                    // один голос на всё собеседование
                    'voiceKey' => 'interview-'.$interview->id,
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
                @if(session('status'))
                    <div class="ch-note">{{ session('status') }}</div>
                @endif

                {{-- Разговор идёт, не дожидаясь разбора документов: пока он в
                     работе, ИИ спрашивает по резюме и специальности. --}}
                @if($analysing ?? false)
                    <div class="ch-note" id="chAnalysing">
                        {{ __('ИИ ещё разбирает ваши документы — пока вопросы идут по резюме и вашей специальности, а разбор подключится по ходу разговора.') }}
                    </div>
                @endif

                <div class="ch-feed" id="chFeed">
                    @forelse($turns as $turn)
                        <div class="ch-msg" data-who="{{ $turn->isFromCandidate() ? 'me' : 'ai' }}"
                             @if($turn->isClosing()) data-closing="1" @endif>
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

                {{-- первый вопрос ждёт разбор резюме и документов — не дольше двух минут --}}
                <div class="ch-note ch-warn" id="chPreparing" hidden>
                    {{ __('Готовим собеседование: ИИ изучает ваше резюме и документы, чтобы вопросы были по делу. Это займёт до 2 минут.') }}
                    <span style="white-space:nowrap">{{ __('Осталось не больше') }} <b id="chPreparingLeft">2</b> {{ __('мин.') }}</span>
                </div>

                <div class="ch-note ch-bad" id="chError" hidden></div>

                {{-- Ответ сохранён, а следующий вопрос не пришёл (модель была
                     недоступна). Повторять ответ не нужно — только продолжить. --}}
                <div id="chResume" hidden>
                    <button type="button" class="ch-btn" id="chResumeBtn">{{ __('Продолжить разговор') }}</button>
                </div>

                <div class="ch-panel ch-done" id="chDone" hidden>
                    <h2>{{ __('Собеседование пройдено') }}</h2>
                    <p class="ch-hint">
                        {{ __('Анна договорит — и страница сама перейдёт к следующему шагу. Можно перейти и сейчас.') }}
                    </p>
                    <div>
                        <a class="ch-btn" id="chNext" href="{{ route('applicant.ai.interview', $interview) }}">
                            {{ __('Дальше') }}
                        </a>
                    </div>
                </div>

                {{-- До первого вопроса — выбор, как отвечать. Нажатие заодно
                     разрешает браузеру звук и микрофон: без него первый вопрос
                     не прозвучал бы, а микрофон не включился. --}}
                @if($turns->isEmpty())
                    <div class="ch-panel ch-start" id="chStart">
                        <h2>{{ __('Собеседование проходит голосом') }}</h2>
                        <p class="ch-hint">
                            {{ __('Анна задаёт вопросы голосом, а вы отвечаете так же, как в обычном разговоре: когда она договорит, микрофон включится сам, а ответ уйдёт, когда вы замолчите. Отвечать текстом можно в любой момент.') }}
                        </p>
                        <div class="ch-row" style="justify-content:flex-start; gap:10px; flex-wrap:wrap">
                            <button type="button" class="ch-btn" id="chStartVoice">🎙 {{ __('Начать собеседование') }}</button>
                            <button type="button" class="ch-btn ch-btn-ghost" id="chStartText">⌨ {{ __('Лучше отвечу текстом') }}</button>
                        </div>
                    </div>
                @endif

                @include('applicant.pages.ai-interview._voice')

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
        const resume = document.getElementById('chResume');
        const resumeBtn = document.getElementById('chResumeBtn');
        const next = document.getElementById('chNext');

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
        // язык собеседования — для голоса браузера, если звук с сервера не пришёл
        const speechLang = @json($config?->language ?? 'ru');

        let busy = false;

        /*
         * Задание составляется заранее, пока идёт разговор.
         *
         * Составление — самый долгий вызов модуля, и раньше кандидат после
         * беседы ждал его минутами. Теперь после первого ответа страница в
         * фоне просит сервер составить задание, и к концу разговора оно уже
         * готово. Кандидат этого запроса не ждёт и не видит.
         */
        const prepareUrl = @json(route('applicant.ai.task.prepare', $interview));
        let taskRequested = false;

        /*
         * Один шаг собеседования в фоне — оценить прошлый ответ, доразобрать
         * документы. Нужен там, где не запущен обработчик очереди: так к концу
         * разговора всё уже посчитано, и решение не ждёт. Кандидат этого не
         * видит и не ждёт.
         */
        const pumpUrl = @json(route('applicant.ai.pump', $interview));

        /*
         * На однопоточном сервере (php artisan serve на Windows) фоновую
         * работу не просим: пока она шла, сервер не отвечал ни на что — ни на
         * озвучку вопроса, ни на отправку ответа. Оценки и задание догонят
         * позже, на странице задания.
         */
        const backgroundWork = @json($backgroundWork ?? true);

        function pumpStep() {
            if (!backgroundWork) { return; }

            fetch(pumpUrl, {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
            }).catch(function () {});
        }

        function prepareTask() {
            if (taskRequested || !backgroundWork) { return; }
            taskRequested = true;

            fetch(prepareUrl, {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
            })
                .then(function (r) { return r.ok ? r.json() : {status: 'busy'}; })
                .then(function (data) {
                    // модель была занята — попросим снова после следующего ответа
                    if (data.status === 'busy') { taskRequested = false; }
                })
                .catch(function () { taskRequested = false; });
        }
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
                window.AiAvatar.speak({text: question.text, lang: speechLang, onEnd: questionSpoken});

                return;
            }

            // Звук готовится на сервере: аватар спрашивает состояние (первый
            // же запрос сам запускает озвучку), а не дёргает пустой файл.
            window.AiAvatar.speak({
                text: question.text,
                statusUrl: question.speech_state_url,
                lang: speechLang,
                onEnd: questionSpoken,
            });
        }

        // вопрос договорён — в голосовом режиме кандидат отвечает голосом
        function questionSpoken() {
            if (typeof voice !== 'undefined') { voice.afterQuestion(); }
        }

        function isBusy() { return busy; }

        /*
         * Ждём разбор резюме и документов перед первым вопросом.
         *
         * Кандидат видит, что идёт подготовка и сколько примерно ждать, —
         * иначе пустой экран выглядел бы зависанием. Сама страница двигает
         * разбор (как и страница документов), а не только ждёт очередь: на
         * однопоточном сервере без обработчика очереди его иначе не сделал бы
         * никто. Сервер сам решает, когда ждать хватит.
         */
        const preparingNote = document.getElementById('chPreparing');
        const preparingLeft = document.getElementById('chPreparingLeft');

        function prepare(left) {
            preparingNote.hidden = false;
            // «вопросы идут по резюме» — пока неправда: мы как раз ждём разбор
            const analysingNote = document.getElementById('chAnalysing');
            if (analysingNote) { analysingNote.hidden = true; }
            preparingLeft.textContent = Math.max(1, Math.ceil(left / 60));
            busy = true;
            send.disabled = true;
            text.disabled = true;
            if (window.AiAvatar) { window.AiAvatar.setState('thinking'); }

            const again = function () {
                busy = false;
                ask(urls.begin, {});
            };

            fetch(pumpUrl, {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
            })
                .then(function () { setTimeout(again, 1500); })
                .catch(function () { setTimeout(again, 4000); });
        }

        function speakQuestion(question) { speak(question); }

        function paintProgress(asked) {
            askedOut.textContent = asked;
            bar.style.width = target > 0 ? Math.min(100, Math.round(asked / target * 100)) + '%' : '0%';
        }

        /*
         * Анна подводит черту: говорит, что дальше и когда ждать ответа, — и
         * после её слов страница сама ведёт к заданию. Если звук не сработал,
         * переходим через минуту: кнопка «Дальше» видна и раньше.
         */
        function closeConversation(closing, nextUrl) {
            if (!closing || !closing.text) { return; }

            const node = bubble('ai', closing.text, null);
            node.dataset.closing = '1';
            scroll();

            let gone = false;
            const go = function () {
                if (gone || !nextUrl) { return; }
                gone = true;
                setTimeout(function () { window.location.href = nextUrl; }, 1200);
            };

            setTimeout(go, 60000);

            if (!window.AiAvatar) { go(); return; }

            const say = {text: closing.text, lang: speechLang, onEnd: go};

            if (voiceEnabled && closing.speech_status !== 'none') {
                say.statusUrl = closing.speech_state_url;
            }

            window.AiAvatar.speak(say);
        }

        function finish(nextUrl) {
            if (typeof voice !== 'undefined') { voice.stop(); }
            form.hidden = true;
            resume.hidden = true;
            done.hidden = false;
            if (nextUrl && next) { next.href = nextUrl; }
            if (window.AiAvatar) { window.AiAvatar.setState('idle'); }
            scroll();
        }

        /**
         * Запрос к серверу.
         *
         * onFail возвращает страницу в то состояние, в котором она была до
         * отправки. Без этого ответ кандидата оставался в переписке, будто
         * ушёл, а из поля исчезал: человек видел свои четыре тысячи знаков на
         * экране, надпись «попробуйте ещё раз» и пустое поле — и набирал всё
         * заново.
         */
        function ask(url, payload, onFail) {
            hideError();
            resume.hidden = true;
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
                        if (result.data.handover) {
                            if (typeof voice !== 'undefined') { voice.stop(); }
                            form.hidden = true;

                            return;
                        }
                        /*
                         * Ответ уже записан, а вопрос не пришёл. Текст в поле
                         * не возвращаем: повторная отправка того же ответа
                         * ничего бы не дала. Вместо этого — «Продолжить».
                         */
                        if (result.data.saved) {
                            form.hidden = true;
                            resume.hidden = false;
                            return;
                        }
                        // этот ответ уже лежит на сервере — продолжаем с него
                        if (result.data.resume) {
                            if (onFail) { onFail(); }
                            text.value = '';
                            paintLeft();
                            ask(urls.begin, {});
                            return;
                        }

                        /*
                         * Стадия сменилась на сервере: разговор уже закончен
                         * или ещё не начат. Страница об этом не знает, и
                         * держать её на месте бессмысленно — перезагрузка
                         * отправит кандидата туда, где он нужен.
                         */
                        if (result.data.reload) {
                            window.location.reload();

                            return;
                        }

                        if (onFail) { onFail(); }

                        return;
                    }

                    if (result.data.reload) {
                        window.location.reload();
                        return;
                    }
                    if (result.data.already) { return; }

                    // разбор резюме и документов ещё идёт — ждём его, не дольше двух минут
                    if (result.data.preparing) {
                        prepare(result.data.left);

                        return;
                    }

                    preparingNote.hidden = true;
                    form.hidden = false;

                    if (result.data.question) {
                        questionId = result.data.question.id;
                        bubble('ai', result.data.question.text, result.data.question.kind);
                        paintProgress(result.data.asked);
                        speak(result.data.question);
                        scroll();

                        // первый ответ уже дан — пора готовить задание
                        if (result.data.asked >= 2) { prepareTask(); }

                        pumpStep();
                    }

                    if (result.data.done) {
                        if (result.data.asked !== undefined) { paintProgress(result.data.asked); }
                        finish(result.data.next_url);
                        closeConversation(result.data.closing, result.data.next_url);
                    }
                })
                .catch(function () {
                    waiting(false);
                    showError(@json(__('Связь прервалась. Ответы сохранены — попробуйте ещё раз.')));

                    if (onFail) { onFail(); }
                });
        }

@include('applicant.pages.ai-interview._voice-script')

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            const value = text.value.trim();

            if (!value || busy) { return; }

            // ответ уходит — микрофон выключаем, пока Анна думает и говорит
            if (typeof voice !== 'undefined') { voice.stop(); }

            const mine = bubble('me', value, null);
            text.value = '';
            paintLeft();
            scroll();

            // ответ принят — бот думает над следующей репликой (не «слушает»:
            // микрофон уже выключен, и подписи не должны говорить обратное)
            if (window.AiAvatar) { window.AiAvatar.setState('thinking'); }

            ask(urls.answer, {text: value, question_id: questionId}, function () {
                // ответ не ушёл — возвращаем его в поле и убираем из переписки
                mine.remove();
                text.value = value;
                paintLeft();
            });
        });

        // Ctrl+Enter отправляет: привычно тем, кто печатает много
        text.addEventListener('keydown', function (event) {
            if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                form.requestSubmit();
            }
        });

        text.addEventListener('input', function (event) {
            paintLeft();

            // печатает сам человек (распознавание пишет в поле без события) —
            // голосовая автоотправка его не перебивает
            if (event.isTrusted && typeof voice !== 'undefined') { voice.typed(); }
        });
        paintLeft();

        // Разговор ещё не начался — просим первую реплику. Страница при этом
        // уже открыта: ждать её отрисовки из-за модели неправильно.
        resumeBtn.addEventListener('click', function () {
            ask(urls.begin, {});
        });

        /*
         * Пустая лента — первый вопрос. Последняя реплика наша — следующий
         * вопрос в прошлый раз не пришёл: просим его сейчас, иначе кандидат
         * смотрел бы на свой ответ без продолжения.
         */
        const messages = feed.querySelectorAll('.ch-msg[data-who]:not(#chTyping)');
        const lastMessage = messages.length ? messages[messages.length - 1] : null;

        const start = document.getElementById('chStart');

        // вернулся к начатому разговору — задание, возможно, ещё не готово
        if (feed.querySelector('.ch-msg[data-who="me"]')) { prepareTask(); }

        if (start) {
            // первый вопрос — после выбора, как отвечать
            form.hidden = true;

            const begin = function () {
                start.remove();
                form.hidden = false;
                ask(urls.begin, {});
            };

            document.getElementById('chStartText').addEventListener('click', begin);

            const voiceStart = document.getElementById('chStartVoice');

            if (!voice.supported) {
                voiceStart.disabled = true;
                voiceStart.title = @json(__('В этом браузере голосовые ответы недоступны'));
            }

            voiceStart.addEventListener('click', function () {
                voice.enable().then(begin, begin);
            });
        } else if (!lastMessage || lastMessage.dataset.who === 'me' || lastMessage.dataset.closing === '1') {
            // наш ответ без продолжения или завершение прошлого круга (после
            // пограничного дораунда разговор продолжается) — просим вопрос
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
