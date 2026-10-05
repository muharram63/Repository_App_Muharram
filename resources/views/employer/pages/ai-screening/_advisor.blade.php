{{--
    Обсуждение кандидата с Анной — голосом или текстом.

    Анна докладывает о кандидате, отвечает на вопросы работодателя по отчёту и
    помогает решить. Решение голосом — «принимаем» или «отказываем» — она только
    переспрашивает; ответ кандидату уходит обычными кнопками отчёта и только после
    явного «да» работодателя: голосом или кнопкой.

    Голос: Анна говорит через синтез речи сервера (запасной — голос браузера).
    Работодатель говорит в микрофон: распознаёт браузер (Chrome, Edge), а где он не
    умеет — сервер. Микрофон слушает, только когда Анна молчит.
--}}
<style>
    .ad-panel { display: grid; grid-template-columns: 270px minmax(0, 1fr); gap: 18px; align-items: start; }
    @media (max-width: 900px) { .ad-panel { grid-template-columns: 1fr; } }
    .ad-main { display: flex; flex-direction: column; gap: 12px; min-width: 0; }

    /* лента разговора — своя рамка и фон, реплики с подписью, кто говорит */
    .ad-feed {
        display: flex; flex-direction: column; gap: 12px; min-height: 120px; max-height: 420px; overflow-y: auto;
        padding: 14px; border: 1px solid var(--gray-200, #E5E7EB); border-radius: 12px;
        background: var(--bg, #F8F9FC);
    }
    .ad-empty { margin: auto; text-align: center; font-size: 13px; color: var(--gray-500, #6B7280); max-width: 340px; line-height: 1.5; }
    .ad-msg { display: flex; flex-direction: column; gap: 4px; max-width: 88%; }
    .ad-msg-ai { align-self: flex-start; }
    .ad-msg-me { align-self: flex-end; align-items: flex-end; }
    .ad-who { font-size: 11px; font-weight: 700; color: var(--gray-500, #6B7280); padding: 0 4px; }
    .ad-text {
        padding: 10px 14px; border-radius: 14px; font-size: 14px; line-height: 1.55;
        white-space: pre-wrap; overflow-wrap: anywhere;
    }
    .ad-msg-ai .ad-text {
        background: var(--white, #fff); color: var(--ink, #111827);
        border: 1px solid var(--indigo-100, #E0E4FF); border-top-left-radius: 4px;
    }
    .ad-msg-me .ad-text { background: var(--indigo-600, #4F46E5); color: #fff; border-top-right-radius: 4px; }

    .ad-chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .ad-chip {
        padding: 6px 12px; border-radius: 999px; border: 1px solid var(--gray-300, #D1D5DB);
        background: var(--white, #fff); font: inherit; font-size: 12px; cursor: pointer; color: var(--ink, #111827);
    }
    .ad-chip:hover { border-color: var(--indigo-600, #4F46E5); color: var(--indigo-600, #4F46E5); }

    .ad-form {
        display: flex; gap: 8px; align-items: center; padding: 6px;
        border: 1px solid var(--gray-300, #D1D5DB); border-radius: 14px; background: var(--white, #fff);
    }
    .ad-form:focus-within { border-color: var(--indigo-600, #4F46E5); }
    .ad-input {
        flex: 1; min-width: 0; padding: 8px 10px; border: 0; outline: 0; background: transparent;
        color: var(--ink, #111827); font: inherit; font-size: 14px;
    }
    .ad-icon {
        flex: none; width: 40px; height: 40px; border-radius: 50%; border: 1px solid var(--gray-300, #D1D5DB);
        background: var(--white, #fff); cursor: pointer; font-size: 17px; display: grid; place-items: center;
    }
    .ad-icon.is-on { background: #16A34A; border-color: #16A34A; color: #fff; animation: adPulse 1.4s ease-in-out infinite; }
    .ad-icon:disabled { opacity: .45; cursor: default; animation: none; }
    @keyframes adPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(22,163,74,.45); } 50% { box-shadow: 0 0 0 7px rgba(22,163,74,0); } }
    @media (prefers-reduced-motion: reduce) { .ad-icon.is-on { animation: none; } }

    .ad-state { font-size: 12px; color: var(--gray-500, #6B7280); min-height: 16px; }
    .ad-confirm {
        padding: 12px 14px; border-radius: 12px; border: 1px solid #FCD34D;
        background: var(--wash-warn, #FEF3C7); color: var(--ink-warn, #92400E);
    }
    .ad-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
</style>

<div class="cr-actions" id="adBox">
    <h2>🎙 {{ __('Обсудить кандидата с ИИ') }}</h2>
    <p class="cr-hint">
        {{ __('Анна провела собеседование и расскажет о кандидате голосом. Спрашивайте голосом или текстом, а решение — принять или отказать — тоже можно сказать вслух: Анна переспросит и отправит ответ кандидату только после вашего «да».') }}
    </p>

    <div class="ad-panel">
        <div>
            @include('partials.ai-avatar', [
                'avatarName' => \App\Services\Ai\GeminiInterviewer::NAME,
                'avatarRole' => __(\App\Services\Ai\GeminiInterviewer::ROLE).', '.__('ИИ'),
            ])
        </div>

        <div class="ad-main">
            <div class="ad-feed" id="adFeed">
                <div class="ad-empty" id="adEmpty">
                    {{ __('Нажмите «Начать обсуждение» — Анна кратко расскажет о кандидате, а дальше спрашивайте что угодно о нём.') }}
                </div>
            </div>

            <div class="ad-row" id="adStartRow">
                <button type="button" class="cr-btn" id="adStart">▶ {{ __('Начать обсуждение') }}</button>
                <span class="ad-state">{{ __('Анна кратко доложит о кандидате.') }}</span>
            </div>

            <div class="ad-confirm" id="adConfirm" hidden>
                <div id="adConfirmText" style="font-weight:700; margin-bottom:8px"></div>
                <div class="ad-row">
                    <button type="button" class="cr-btn" id="adYes">✓ {{ __('Да, отправить') }}</button>
                    <button type="button" class="cr-btn cr-btn-ghost" id="adNo">{{ __('Нет') }}</button>
                </div>
            </div>

            <div id="adTalk" hidden>
                <div style="display:flex; flex-direction:column; gap:10px">
                    <div class="ad-chips">
                        <button type="button" class="ad-chip" data-ask="{{ __('Почему такая рекомендация?') }}">{{ __('Почему такая рекомендация?') }}</button>
                        <button type="button" class="ad-chip" data-ask="{{ __('Какие сильные стороны и риски?') }}">{{ __('Сильные стороны и риски') }}</button>
                        <button type="button" class="ad-chip" data-ask="{{ __('Как он справился с тестом и заданиями?') }}">{{ __('Тест и задания') }}</button>
                        <button type="button" class="ad-chip" data-ask="{{ __('Что спросить у него на личной встрече?') }}">{{ __('Что спросить на встрече?') }}</button>
                    </div>
                    <form class="ad-form" id="adForm">
                        <button type="button" class="ad-icon" id="adMic" title="{{ __('Говорить голосом') }}"
                                aria-label="{{ __('Говорить голосом') }}">🎙</button>
                        <input class="ad-input" id="adText" maxlength="1500" autocomplete="off"
                               placeholder="{{ __('Спросите Анну или скажите решение: «принимаем», «отказываем»') }}">
                        <button type="submit" class="cr-btn" id="adSend">{{ __('Отправить') }}</button>
                    </form>
                    <span class="ad-state" id="adState"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const urls = {
            advise: @json(route('employer.ai.candidate.advise', [$vacancy, $interview])),
            speak: @json(route('employer.ai.candidate.speak', [$vacancy, $interview])),
            listen: @json(route('employer.ai.candidate.listen', [$vacancy, $interview])),
        };
        const token = @json(csrf_token());
        const locale = @json(app()->getLocale());

        const feed = document.getElementById('adFeed');
        const startRow = document.getElementById('adStartRow');
        const talk = document.getElementById('adTalk');
        const form = document.getElementById('adForm');
        const input = document.getElementById('adText');
        const send = document.getElementById('adSend');
        const mic = document.getElementById('adMic');
        const state = document.getElementById('adState');
        const confirmBox = document.getElementById('adConfirm');
        const confirmText = document.getElementById('adConfirmText');

        const history = [];
        let busy = false;
        let voiceOn = false;
        let pending = null; // предложенное решение: {action, canApprove, recommended}

        // ==================== лента и голос Анны ====================

        function say(message) { state.textContent = message || ''; }

        function bubble(who, text) {
            const empty = document.getElementById('adEmpty');
            if (empty) { empty.remove(); }

            const node = document.createElement('div');
            node.className = 'ad-msg ' + (who === 'ai' ? 'ad-msg-ai' : 'ad-msg-me');

            const name = document.createElement('div');
            name.className = 'ad-who';
            name.textContent = who === 'ai' ? @json(\App\Services\Ai\GeminiInterviewer::NAME) : @json(__('Вы'));

            const body = document.createElement('div');
            body.className = 'ad-text';
            body.textContent = text;

            node.append(name, body);
            feed.appendChild(node);
            feed.scrollTop = feed.scrollHeight;
        }

        /** Анна говорит: звук с сервера, иначе голос браузера. Договорила — onEnd. */
        function speak(text, onEnd) {
            if (!window.AiAvatar) { if (onEnd) { onEnd(); } return; }

            window.AiAvatar.setState('thinking');

            fetch(urls.speak, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify({text: text}),
            })
                .then(function (r) { return r.ok ? r.blob() : null; })
                .then(function (blob) {
                    const options = {text: text, lang: locale, onEnd: onEnd};
                    if (blob) { options.audioUrl = URL.createObjectURL(blob); }
                    window.AiAvatar.speak(options);
                })
                .catch(function () {
                    window.AiAvatar.speak({text: text, lang: locale, onEnd: onEnd});
                });
        }

        // ==================== разговор ====================

        function ask(message) {
            if (busy) { return; }
            busy = true;
            send.disabled = true;
            say(@json(__('Анна думает…')));

            if (message) {
                bubble('me', message);
            }

            fetch(urls.advise, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                body: JSON.stringify({message: message || '', history: history}),
            })
                .then(function (r) { return r.json().then(function (d) { return {ok: r.ok, data: d}; }); })
                .then(function (res) {
                    busy = false;
                    send.disabled = false;

                    if (!res.ok) {
                        say(res.data.error || @json(__('Не получилось ответить — спросите иначе.')));
                        afterSpeech();

                        return;
                    }

                    say('');
                    if (message) { history.push({role: 'employer', text: message}); }
                    history.push({role: 'ai', text: res.data.reply});
                    bubble('ai', res.data.reply);

                    if (res.data.action === 'accept' || res.data.action === 'reject') {
                        propose(res.data);
                    }

                    speak(res.data.reply, afterSpeech);
                })
                .catch(function () {
                    busy = false;
                    send.disabled = false;
                    say(@json(__('Связь прервалась — попробуйте ещё раз.')));
                });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const value = input.value.trim();
            if (!value) { return; }
            input.value = '';
            handle(value);
        });

        talk.querySelectorAll('[data-ask]').forEach(function (chip) {
            chip.addEventListener('click', function () { handle(chip.dataset.ask); });
        });

        document.getElementById('adStart').addEventListener('click', function () {
            startRow.hidden = true;
            talk.hidden = false;

            /*
             * Голос включается сам: это нажатие — то действие, после которого
             * браузер разрешает и звук, и микрофон. Анна договорила — помощник
             * слушает работодателя. Выключить — кнопкой 🎙.
             */
            // сначала доклад (Анна «занята»), потом голос — иначе микрофон
            // услышал бы её саму; слушать начнёт, когда она договорит
            ask('');

            if (engine) { switchVoice(true); }
        });

        // ==================== решение ====================

        const OUTCOME = {
            accept: @json(__('Прошёл отбор')),
            reject: @json(__('Отказ')),
        };

        function propose(data) {
            // кандидат ещё не прошёл все шаги — решать пока нечего
            if (!data.awaits) {
                say(@json(__('Решение можно будет принять, когда кандидат пройдёт все шаги, — мы пришлём уведомление.')));

                return;
            }

            if (data.released) {
                say(@json(__('Ответ кандидату уже отправлен — изменить его можно формой «Ваше решение» ниже.')));
            }

            pending = {
                action: data.action,
                canApprove: data.can_approve,
                recommended: data.recommended,
            };
            confirmText.textContent = @json(__('Отправить кандидату ответ:')) + ' ' + OUTCOME[data.action] + '?';
            confirmBox.hidden = false;
        }

        function cancel() {
            pending = null;
            confirmBox.hidden = true;
        }

        /** Отправить ответ кандидату — теми же кнопками, что и на странице. */
        function execute() {
            if (!pending) { return; }

            const wanted = pending.action === 'accept' ? 'passed' : 'rejected';
            const approve = document.getElementById('crApprove');
            const override = document.getElementById('crOverride');

            stopListening();
            say(@json(__('Отправляю ответ кандидату…')));

            // ИИ рекомендовал то же самое — подтверждаем его решение
            if (pending.canApprove && pending.recommended === wanted && approve) {
                approve.submit();

                return;
            }

            if (override) {
                override.querySelector('[name=outcome]').value = wanted;
                const note = override.querySelector('[name=note]');
                if (note && !note.value) { note.value = @json(__('Решение принято в разговоре с ИИ-помощником.')); }
                override.submit();
            }
        }

        document.getElementById('adYes').addEventListener('click', execute);
        document.getElementById('adNo').addEventListener('click', function () {
            cancel();
            handle(@json(__('Нет, не отправляй пока.')));
        });

        /** Реплика работодателя: «да»/«нет» в ответ на предложение — иначе вопрос Анне. */
        function handle(text) {
            if (pending) {
                const t = text.toLowerCase();
                if (/^(да|ҳа|ha|yes|подтверждаю|отправ|давай|ок|okay|ok)/.test(t)) { execute(); return; }
                if (/^(нет|не |не$|на|no|отмена|подожди)/.test(t)) { cancel(); }
            }

            ask(text);
        }

        // ==================== голос работодателя ====================

        const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        const browserLang = {ru: 'ru-RU', en: 'en-US'}[locale];
        const canMic = !!(window.isSecureContext && navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
        const engine = Recognition && browserLang && window.isSecureContext ? 'browser' : (canMic ? 'server' : null);
        const SILENCE_MS = 2000;

        let rec = null, listening = false, heard = '', heardAt = 0, spoke = false, watch = null;
        let ctx = null, stream = null, source = null, proc = null, chunks = [];

        if (!engine) {
            mic.disabled = true;
            mic.title = @json(__('Микрофон работает в Chrome или Edge и только на защищённой странице (https)'));
        }

        function afterSpeech() {
            if (voiceOn && !busy) { setTimeout(startListening, 300); }
        }

        /** Включить или выключить голос. Включённый слушает, когда Анна молчит. */
        function switchVoice(on) {
            voiceOn = on;
            mic.classList.toggle('is-on', voiceOn);
            mic.title = voiceOn ? @json(__('Голос включён — нажмите, чтобы выключить')) : @json(__('Говорить голосом'));

            if (voiceOn) {
                if (engine === 'server') {
                    const Ctx = window.AudioContext || window.webkitAudioContext;
                    ctx = ctx || new Ctx();
                    if (ctx.state === 'suspended') { ctx.resume(); }
                }
                startListening();
            } else {
                stopListening();
                say('');
            }
        }

        mic.addEventListener('click', function () { switchVoice(!voiceOn); });

        function startListening() {
            if (!voiceOn || listening || busy) { return; }

            listening = true;
            heard = '';
            spoke = false;
            say(@json(__('Слушаю — говорите. Отправлю сам, когда вы замолчите.')));
            if (window.AiAvatar) { window.AiAvatar.setState('listening'); }

            if (engine === 'browser') {
                rec = new Recognition();
                rec.lang = browserLang;
                rec.continuous = true;
                rec.interimResults = true;
                let finals = '';

                rec.onresult = function (event) {
                    let interim = '';
                    for (let i = event.resultIndex; i < event.results.length; i++) {
                        if (event.results[i].isFinal) { finals += event.results[i][0].transcript + ' '; }
                        else { interim += event.results[i][0].transcript; }
                    }
                    heard = (finals + interim).trim();
                    input.value = heard;
                    spoke = true;
                    heardAt = Date.now();
                };
                rec.onend = function () { if (listening && rec) { try { rec.start(); } catch (e) {} } };
                rec.onerror = function (event) {
                    if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
                        voiceOn = false;
                        mic.classList.remove('is-on');
                        stopListening();
                        say(@json(__('Браузер не дал доступ к микрофону. Разрешите его в настройках сайта или пишите текстом.')));
                    }
                };
                try { rec.start(); } catch (e) {}
            } else {
                const begin = stream ? Promise.resolve(stream) : navigator.mediaDevices.getUserMedia({audio: {echoCancellation: true, noiseSuppression: true}});

                begin.then(function (s) {
                    stream = s;
                    if (!listening) { return; }
                    chunks = [];
                    source = ctx.createMediaStreamSource(s);
                    proc = ctx.createScriptProcessor(4096, 1, 1);
                    proc.onaudioprocess = function (event) {
                        if (!listening) { return; }
                        const data = event.inputBuffer.getChannelData(0);
                        chunks.push(new Float32Array(data));
                        let sum = 0;
                        for (let i = 0; i < data.length; i++) { sum += data[i] * data[i]; }
                        if (Math.sqrt(sum / data.length) > 0.02) { spoke = true; heardAt = Date.now(); }
                    };
                    source.connect(proc);
                    proc.connect(ctx.destination);
                }).catch(function () {
                    voiceOn = false;
                    mic.classList.remove('is-on');
                    stopListening();
                    say(@json(__('Браузер не дал доступ к микрофону. Разрешите его в настройках сайта или пишите текстом.')));
                });
            }

            clearInterval(watch);
            watch = setInterval(function () {
                if (listening && spoke && Date.now() - heardAt > SILENCE_MS) { finishListening(); }
            }, 300);
        }

        function stopListening() {
            listening = false;
            clearInterval(watch);
            if (rec) { const r = rec; rec = null; try { r.stop(); } catch (e) {} }
            try { source && source.disconnect(); } catch (e) {}
            try { proc && proc.disconnect(); } catch (e) {}
            source = null;
            proc = null;
        }

        function finishListening() {
            const wasServer = engine === 'server';
            const recorded = chunks;
            stopListening();
            input.value = '';

            if (!wasServer) {
                if (heard) { handle(heard); } else { startListening(); }

                return;
            }

            say(@json(__('Распознаю…')));
            const body = new FormData();
            body.append('audio', toWav(recorded, ctx.sampleRate), 'speech.wav');

            fetch(urls.listen, {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                body: body,
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.text) { handle(data.text); } else { say(data.error || ''); startListening(); }
                })
                .catch(function () { startListening(); });
        }

        /** Запись — в WAV 16 кГц моно: его понимает распознавание на сервере. */
        function toWav(parts, rate) {
            let length = 0;
            parts.forEach(function (p) { length += p.length; });
            const merged = new Float32Array(length);
            let offset = 0;
            parts.forEach(function (p) { merged.set(p, offset); offset += p.length; });

            const target = 16000;
            const ratio = rate / target;
            const samples = Math.floor(merged.length / ratio);
            const pcm = new Int16Array(samples);
            for (let i = 0; i < samples; i++) {
                const v = Math.max(-1, Math.min(1, merged[Math.floor(i * ratio)]));
                pcm[i] = v < 0 ? v * 0x8000 : v * 0x7FFF;
            }

            const buffer = new ArrayBuffer(44 + pcm.length * 2);
            const view = new DataView(buffer);
            const write = function (pos, str) { for (let i = 0; i < str.length; i++) { view.setUint8(pos + i, str.charCodeAt(i)); } };
            write(0, 'RIFF'); view.setUint32(4, 36 + pcm.length * 2, true); write(8, 'WAVE'); write(12, 'fmt ');
            view.setUint32(16, 16, true); view.setUint16(20, 1, true); view.setUint16(22, 1, true);
            view.setUint32(24, target, true); view.setUint32(28, target * 2, true);
            view.setUint16(32, 2, true); view.setUint16(34, 16, true);
            write(36, 'data'); view.setUint32(40, pcm.length * 2, true);
            new Int16Array(buffer, 44).set(pcm);

            return new Blob([buffer], {type: 'audio/wav'});
        }
    })();
</script>
