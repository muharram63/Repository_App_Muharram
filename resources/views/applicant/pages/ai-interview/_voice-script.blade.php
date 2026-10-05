{{--
    Голосовой режим — скрипт. Вставляется внутрь скрипта чата и пользуется
    его переменными: text, form, paintLeft, isBusy(), showError, feed,
    questionId, speechLang, token, speakQuestion().
--}}
        const voice = (function () {
            const box = document.getElementById('chVoice');
            const toggle = document.getElementById('chVoiceToggle');
            const toggleLabel = document.getElementById('chVoiceToggleLabel');
            const stateOut = document.getElementById('chVoiceState');
            const levelWrap = document.getElementById('chVoiceLevelWrap');
            const level = document.getElementById('chVoiceLevel');
            const sendNow = document.getElementById('chVoiceSend');

            const transcribeUrl = @json(route('applicant.ai.chat.transcribe', $interview));
            const serverRecognition = @json($serverRecognition ?? false);

            // сколько тишины после речи считаем «договорил»
            const SILENCE_MS = 2500;
            // дольше трёх минут один ответ не записываем
            const MAX_RECORD_MS = 180000;
            const PREF = 'ai-voice-mode';

            const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            // браузерное распознавание надёжно по-русски и по-английски;
            // таджикского в нём нет — его распознаёт сервер
            const browserLang = {ru: 'ru-RU', en: 'en-US'}[speechLang];
            const canMic = !!(window.isSecureContext && navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
            const engine = Recognition && browserLang && window.isSecureContext
                ? 'browser'
                : (canMic && serverRecognition && (window.AudioContext || window.webkitAudioContext) ? 'server' : null);

            let on = false;
            let listening = false;
            let spoke = false;
            let heardAt = 0;
            let startedAt = 0;
            let watch = null;
            let rec = null;
            let base = '';

            // запись для серверного распознавания
            let ctx = null;
            let stream = null;
            let source = null;
            let proc = null;
            let chunks = [];

            function say(message) { stateOut.textContent = message || ''; }

            function paint() {
                toggle.setAttribute('aria-pressed', on ? 'true' : 'false');
                toggle.classList.toggle('is-on', on);
                toggleLabel.textContent = on
                    ? @json(__('Голосовой режим включён'))
                    : @json(__('Включить голосовой режим'));
                box.classList.toggle('is-listening', listening);
                sendNow.hidden = !listening;
                levelWrap.hidden = !(listening && engine === 'server');
            }

            if (!engine) {
                toggle.disabled = true;
                say(window.isSecureContext
                    ? @json(__('Голосовые ответы в этом браузере недоступны — отвечайте текстом. Лучше всего работает Chrome или Edge.'))
                    : @json(__('Микрофон работает только на защищённой странице (https) — пока отвечайте текстом.')));

                return {
                    supported: false,
                    enable: function () { return Promise.reject(); },
                    afterQuestion: function () {},
                    stop: function () {},
                    typed: function () {},
                };
            }

            // ==================== прослушивание ====================

            function armWatch() {
                clearInterval(watch);
                watch = setInterval(function () {
                    if (!listening) { return; }

                    if (spoke && Date.now() - heardAt > SILENCE_MS) {
                        submit();
                    } else if (Date.now() - startedAt > MAX_RECORD_MS) {
                        submit();
                    }
                }, 300);
            }

            function listen() {
                if (!on || listening || isBusy() || form.hidden) { return; }

                listening = true;
                spoke = false;
                startedAt = Date.now();
                base = text.value.trim();
                paint();
                say(@json(__('Слушаю — отвечайте. Отправлю сам, когда вы замолчите.')));

                if (window.AiAvatar) { window.AiAvatar.setState('listening'); }

                if (engine === 'browser') {
                    startBrowser();
                } else {
                    startRecording();
                }

                armWatch();
            }

            function startBrowser() {
                rec = new Recognition();
                rec.lang = browserLang;
                rec.continuous = true;
                rec.interimResults = true;

                let finals = '';

                rec.onresult = function (event) {
                    let interim = '';

                    for (let i = event.resultIndex; i < event.results.length; i++) {
                        const piece = event.results[i][0].transcript;

                        if (event.results[i].isFinal) {
                            finals += piece + ' ';
                        } else {
                            interim += piece;
                        }
                    }

                    text.value = (base ? base + ' ' : '') + (finals + interim).trim();
                    paintLeft();
                    spoke = true;
                    heardAt = Date.now();
                };

                // Chrome сам останавливает распознавание после паузы — пока
                // мы слушаем, запускаем снова
                rec.onend = function () {
                    if (listening && rec) {
                        try { rec.start(); } catch (e) {}
                    }
                };

                rec.onerror = function (event) {
                    if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
                        off(@json(__('Браузер не дал доступ к микрофону. Разрешите его в настройках сайта или отвечайте текстом.')));
                    } else if (event.error === 'network') {
                        off(@json(__('Распознавание речи недоступно без сети — отвечайте текстом.')));
                    }
                    // no-speech и прочее — просто слушаем дальше
                };

                try { rec.start(); } catch (e) {}
            }

            function stopBrowser() {
                if (!rec) { return; }

                const r = rec;
                rec = null;
                try { r.stop(); } catch (e) {}
            }

            // ==================== запись для сервера ====================

            function ensureMic() {
                if (stream) { return Promise.resolve(stream); }

                return navigator.mediaDevices.getUserMedia({audio: {echoCancellation: true, noiseSuppression: true}})
                    .then(function (s) { stream = s; return s; });
            }

            function ensureContext() {
                if (!ctx) {
                    const Ctx = window.AudioContext || window.webkitAudioContext;
                    ctx = new Ctx();
                }

                if (ctx.state === 'suspended') { ctx.resume(); }

                return ctx;
            }

            function startRecording() {
                ensureMic().then(function (s) {
                    if (!listening) { return; }

                    const c = ensureContext();
                    chunks = [];
                    source = c.createMediaStreamSource(s);
                    proc = c.createScriptProcessor(4096, 1, 1);

                    proc.onaudioprocess = function (event) {
                        if (!listening) { return; }

                        const data = event.inputBuffer.getChannelData(0);
                        chunks.push(new Float32Array(data));

                        let sum = 0;
                        for (let i = 0; i < data.length; i++) { sum += data[i] * data[i]; }
                        const rms = Math.sqrt(sum / data.length);

                        level.style.width = Math.min(100, Math.round(rms * 600)) + '%';

                        // порог голоса: тише — фон и дыхание
                        if (rms > 0.02) {
                            spoke = true;
                            heardAt = Date.now();
                        }
                    };

                    source.connect(proc);
                    proc.connect(c.destination);
                }).catch(function () {
                    off(@json(__('Браузер не дал доступ к микрофону. Разрешите его в настройках сайта или отвечайте текстом.')));
                });
            }

            function stopRecording() {
                try { source && source.disconnect(); } catch (e) {}
                try { proc && proc.disconnect(); } catch (e) {}
                source = null;
                proc = null;
            }

            /** Записанное — в WAV 16 кГц моно: его понимают и браузер, и модель. */
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
                const write = function (pos, str) {
                    for (let i = 0; i < str.length; i++) { view.setUint8(pos + i, str.charCodeAt(i)); }
                };

                write(0, 'RIFF');
                view.setUint32(4, 36 + pcm.length * 2, true);
                write(8, 'WAVE');
                write(12, 'fmt ');
                view.setUint32(16, 16, true);
                view.setUint16(20, 1, true);
                view.setUint16(22, 1, true);
                view.setUint32(24, target, true);
                view.setUint32(28, target * 2, true);
                view.setUint16(32, 2, true);
                view.setUint16(34, 16, true);
                write(36, 'data');
                view.setUint32(40, pcm.length * 2, true);
                new Int16Array(buffer, 44).set(pcm);

                return new Blob([buffer], {type: 'audio/wav'});
            }

            function transcribe() {
                const recorded = chunks;
                chunks = [];

                if (!recorded.length || !spoke) {
                    return Promise.resolve('');
                }

                say(@json(__('Распознаю ответ…')));

                const body = new FormData();
                body.append('audio', toWav(recorded, ctx.sampleRate), 'answer.wav');

                return fetch(transcribeUrl, {
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                    body: body,
                })
                    .then(function (r) { return r.json().then(function (d) { return {ok: r.ok, data: d}; }); })
                    .then(function (res) {
                        if (!res.ok) {
                            showError(res.data.error || @json(__('Не удалось разобрать запись — повторите или ответьте текстом.')));

                            return '';
                        }

                        return (res.data.text || '').trim();
                    })
                    .catch(function () {
                        showError(@json(__('Связь прервалась — повторите ответ или напишите его.')));

                        return '';
                    });
            }

            // ==================== отправка ====================

            function halt() {
                listening = false;
                clearInterval(watch);
                stopBrowser();
                stopRecording();
                paint();
            }

            function submit() {
                if (!listening) { return; }

                const wasServer = engine === 'server';
                halt();

                const finalize = function (heard) {
                    if (heard) {
                        text.value = (base ? base + ' ' : '') + heard;
                        paintLeft();
                    }

                    if (text.value.trim()) {
                        say('');
                        form.requestSubmit();
                    } else {
                        // ничего не услышали — слушаем снова
                        listen();
                    }
                };

                if (wasServer) {
                    transcribe().then(finalize);
                } else {
                    finalize('');
                }
            }

            sendNow.addEventListener('click', submit);

            // ==================== включение ====================

            function off(message) {
                halt();
                on = false;
                try { localStorage.setItem(PREF, 'off'); } catch (e) {}
                paint();
                say(message || '');
            }

            /** Последний заданный вопрос — чтобы озвучить его при включении. */
            function lastQuestionOnPage() {
                const messages = feed.querySelectorAll('.ch-msg[data-who]:not(#chTyping)');
                const lastMessage = messages.length ? messages[messages.length - 1] : null;

                if (!lastMessage || lastMessage.dataset.who !== 'ai' || !questionId) { return null; }

                const body = lastMessage.querySelector('.ch-body');
                const kind = body ? body.querySelector('.ch-kind') : null;
                const textOnly = body ? body.textContent.replace(kind ? kind.textContent : '', '').trim() : '';

                return {
                    id: questionId,
                    text: textOnly,
                    speech_state_url: @json(route('applicant.ai.speech.status', ['turn' => '__TURN__'])).replace('__TURN__', questionId),
                };
            }

            /**
             * Включить режим. Вызывается только из нажатия: это то действие
             * пользователя, после которого браузер разрешает звук и микрофон.
             */
            function enable() {
                on = true;
                try { localStorage.setItem(PREF, 'on'); } catch (e) {}
                paint();

                if (engine === 'server') {
                    ensureContext();

                    return ensureMic();
                }

                // браузерное распознавание спросит разрешение само — заранее
                // проверяем микрофон, если это возможно, чтобы отказ всплыл сразу
                return canMic
                    ? navigator.mediaDevices.getUserMedia({audio: true}).then(function (s) {
                        s.getTracks().forEach(function (t) { t.stop(); });
                    })
                    : Promise.resolve();
            }

            toggle.addEventListener('click', function () {
                if (on) {
                    off(@json(__('Голосовой режим выключен — отвечайте текстом.')));

                    return;
                }

                enable().then(function () {
                    const question = lastQuestionOnPage();

                    if (question && question.text) {
                        say(@json(__('Анна повторит вопрос, потом говорите вы.')));
                        speakQuestion(question);
                    } else {
                        listen();
                    }
                }).catch(function () {
                    off(@json(__('Браузер не дал доступ к микрофону. Разрешите его в настройках сайта или отвечайте текстом.')));
                });
            });

            /*
             * Кандидат отвечал голосом — голос включается сам, без повторного
             * выбора: Анна договорила, микрофон слушает. Разрешение на микрофон
             * браузер уже запомнил. Не вышло (разрешение отозвали) — просим
             * нажать кнопку.
             */
            try {
                if (localStorage.getItem(PREF) === 'on' && !document.getElementById('chStart')) {
                    enable().then(function () {
                        // вопрос уже на экране и ждёт ответа — слушаем сразу
                        const messages = feed.querySelectorAll('.ch-msg[data-who]:not(#chTyping)');
                        const lastMessage = messages.length ? messages[messages.length - 1] : null;

                        if (lastMessage && lastMessage.dataset.who === 'ai' && lastMessage.dataset.closing !== '1') {
                            listen();
                        }
                    }).catch(function () {
                        on = false;
                        paint();
                        say(@json(__('Нажмите «Включить голосовой режим», чтобы продолжить голосом.')));
                    });
                }
            } catch (e) {}

            paint();

            return {
                supported: true,
                enable: function () {
                    return enable().catch(function (e) {
                        off(@json(__('Браузер не дал доступ к микрофону. Разрешите его в настройках сайта или отвечайте текстом.')));
                        throw e;
                    });
                },
                // вопрос договорён — слушаем ответ
                afterQuestion: function () {
                    if (on) { setTimeout(listen, 350); }
                },
                // ответ ушёл, разговор окончен или передан человеку
                stop: function () {
                    halt();
                    say('');
                },
                // кандидат начал печатать — не перебиваем его автоотправкой
                typed: function () {
                    if (listening) {
                        halt();
                        say(@json(__('Вы печатаете — отправьте ответ кнопкой.')));
                    }
                },
            };
        })();
