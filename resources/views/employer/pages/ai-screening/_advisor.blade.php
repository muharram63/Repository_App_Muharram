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
    .ad-status { font-size: 12px; font-weight: 700; color: var(--indigo-600, #4F46E5); }
    .ad-status[data-ai-state="listening"] { color: #16A34A; }
    .ad-status[data-ai-state="ended"] { color: var(--ink-good, #15803D); }
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
                'voiceKey' => 'advisor-'.$interview->id,
            ])
        </div>

        <div class="ad-main">
            <div class="ad-feed" id="adFeed">
                <div class="ad-empty" id="adEmpty">
                    {{ __('Нажмите «Начать обсуждение» — Анна кратко расскажет о кандидате, а дальше спрашивайте что угодно о нём.') }}
                </div>
            </div>

            @php($alreadySent = (bool) $interview->latestDecision?->isReleased())

            {{-- отправка сорвалась: причина и повтор; разговор не завершается --}}
            <div class="cr-failed" id="adFailed" role="alert" hidden>
                <span>✕ <span id="adFailedText"></span></span>
                <button type="button" class="cr-btn" id="adRetry">↻ {{ __('Повторить отправку') }}</button>
            </div>

            {{--
                Ответ кандидату отправлен — разговор завершён. При повторном
                открытии страницы Анна не начинает заново: работодатель сразу
                видит итог. Обсудить ещё раз можно, но новая отправка без явного
                нового подтверждения не случится.
            --}}
            <div class="ad-row" id="adEnded" @unless($alreadySent) hidden @endunless>
                <span class="ad-state" style="font-weight:700; color:var(--ink,#111827)">{{ __('Разговор завершён: ответ кандидату отправлен.') }}</span>
                <button type="button" class="cr-btn cr-btn-ghost" id="adReopen">{{ __('Открыть обсуждение заново') }}</button>
            </div>

            <div class="ad-row" id="adStartRow" @if($alreadySent) hidden @endif>
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
                    <div class="ad-row">
                        {{-- статус — тот же, что у аватара: его пишет только аватар --}}
                        <span class="ad-status" data-ai-state="idle"></span>
                        <span class="ad-state" id="adState"></span>
                    </div>
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

            return node;
        }

        /*
         * Одна реплика — одна озвучка.
         *
         * Звук готовится на сервере секунды, а то и десятки секунд. Раньше,
         * если работодатель за это время успевал спросить снова, звук прошлой
         * реплики приходил уже после его вопроса — и Анна заново произносила
         * старый ответ. Каждая озвучка теперь получает номер; пришедшая не к
         * своей реплике молча отбрасывается, а новое сообщение обрывает речь.
         */
        let speechTicket = 0;
        let speechFetch = null;

        function silence() {
            speechTicket++;
            talking = false;
            if (speechFetch) { try { speechFetch.abort(); } catch (e) {} speechFetch = null; }
            if (window.AiAvatar) { window.AiAvatar.stop(); }
        }

        /** Анна говорит: звук с сервера, иначе голос браузера. Договорила — onEnd. */
        function speak(text, onEnd) {
            silence();
            const mine = speechTicket;
            talking = true;
            const done = function () {
                if (mine !== speechTicket) { return; }
                talking = false;
                if (onEnd) { onEnd(); }
            };

            if (!window.AiAvatar) { done(); return; }

            // голос на этот разговор уже закреплён за браузером — к Gemini не
            // обращаемся: иначе следующая реплика зазвучала бы другим голосом
            if (window.AiAvatar.usesBrowserVoice()) {
                window.AiAvatar.speak({text: text, lang: locale, onEnd: done});

                return;
            }

            window.AiAvatar.setState('thinking');

            speechFetch = window.AbortController ? new AbortController() : null;

            fetch(urls.speak, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify({text: text}),
                signal: speechFetch ? speechFetch.signal : undefined,
            })
                .then(function (r) { return r.ok ? r.blob() : null; })
                .then(function (blob) {
                    if (mine !== speechTicket) { return; }
                    speechFetch = null;
                    const options = {text: text, lang: locale, onEnd: done};
                    // нет файла — Gemini не смог: дальше весь разговор голосом браузера
                    if (blob) { options.audioUrl = URL.createObjectURL(blob); } else { options.serverFailed = true; }
                    window.AiAvatar.speak(options);
                })
                .catch(function () {
                    if (mine !== speechTicket) { return; }
                    speechFetch = null;
                    window.AiAvatar.speak({text: text, lang: locale, onEnd: done, serverFailed: true});
                });
        }

        // ==================== разговор ====================

        function ask(message) {
            if (busy) { return; }
            busy = true;
            send.disabled = true;

            /*
             * Реплика ушла — микрофон и голос Анны замолкают. Раньше микрофон
             * продолжал слушать после отправки текстом или подсказкой, слышал
             * ответ Анны из колонок и отправлял его как реплику работодателя:
             * Анна отвечала сама себе, второй раз подряд.
             */
            stopListening();
            silence();
            window.AiAdvisorBusy = true;
            // статус «Думаю» — у аватара и под микрофоном одновременно
            say('');
            if (window.AiAvatar) { window.AiAvatar.setState('thinking'); }

            // вопрос без ответа из ленты убираем: он вернётся в поле, и
            // повторная отправка не задвоит его в переписке
            const mine = message ? bubble('me', message) : null;
            const unsent = function () {
                if (mine) { mine.remove(); }
                if (message && !input.value) { input.value = message; }
            };

            fetch(urls.advise, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                // ждём «да» на отправку — сервер скажет модели не переспрашивать
                body: JSON.stringify({message: message || '', history: history, awaiting: pending ? pending.action : null}),
            })
                .then(function (r) { return r.json().then(function (d) { return {ok: r.ok, data: d}; }); })
                .then(function (res) {
                    busy = false;
                    send.disabled = false;
                    window.AiAdvisorBusy = false;

                    if (!res.ok) {
                        failed(res.data.error || @json(__('Не получилось ответить — спросите иначе.')));

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
                    window.AiAdvisorBusy = false;
                    failed(@json(__('Связь прервалась — попробуйте ещё раз.')));
                });

            /*
             * Анна не ответила — так и говорим, и микрофон не включаем.
             *
             * Раньше ошибка («Помощник не успел ответить») тут же затиралась
             * надписью «Слушаю — говорите», а аватар снова показывал «Слушает
             * вас». Со стороны выглядело так, будто ответ проглочен и ИИ просит
             * говорить заново. Теперь ошибка остаётся на экране, вопрос — в
             * поле, и спросить снова можно одной кнопкой.
             */
            function failed(error) {
                unsent();
                if (window.AiAvatar) { window.AiAvatar.setState('idle'); }
                say(error + ' ' + @json(__('Ваш вопрос остался в поле — нажмите «Отправить», чтобы спросить ещё раз.')));
            }
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
            // разговор начат — страница больше не перезагружается сама
            window.AiAdvisorActive = true;

            /*
             * Голос включается сам: это нажатие — то действие, после которого
             * браузер разрешает и звук, и микрофон. Анна договорила — помощник
             * слушает работодателя. Выключить — кнопкой 🎙.
             */
            // сначала доклад (Анна «занята»), потом голос — иначе микрофон
            // услышал бы её саму; слушать начнёт, когда она договорит.
            // Доклад — один раз за разговор: повторный «Начать» повторял его
            // слово в слово, и Анна дважды подряд рассказывала одно и то же
            if (!history.some(function (turn) { return turn.role === 'ai'; })) { ask(''); }

            if (engine) { switchVoice(true); }
        });

        // ==================== решение ====================

        const OUTCOME = {
            accept: @json(__('Прошёл отбор')),
            reject: @json(__('Отказ')),
        };

        /*
         * Ответ кандидату по этому собеседованию уже ушёл — второй раз ни
         * предлагать, ни отправлять нельзя. Знает и страница, и сервер.
         */
        let sent = @json((bool) $interview->latestDecision?->isReleased());

        function propose(data) {
            // кандидат ещё не прошёл все шаги — решать пока нечего
            if (!data.awaits) {
                say(@json(__('Решение можно будет принять, когда кандидат пройдёт все шаги, — мы пришлём уведомление.')));

                return;
            }

            // ответ уже отправлен — подтверждения больше не предлагаем
            if (data.released || sent) {
                sent = true;
                say(@json(__('Ответ кандидату уже отправлен — изменить его можно формой «Ваше решение» ниже.')));

                return;
            }

            // то же предложение уже ждёт ответа — второй вопрос не задаём
            if (pending && pending.action === data.action) { return; }

            pending = {
                action: data.action,
                canApprove: data.can_approve,
                recommended: data.recommended,
            };
            confirmText.textContent = @json(__('Отправить кандидату ответ:')) + ' ' + OUTCOME[data.action] + '?';
            confirmBox.hidden = false;
            yesBtn.disabled = false;
        }

        function cancel() {
            pending = null;
            confirmBox.hidden = true;
        }

        /*
         * Отправить ответ кандидату — один раз, без перезагрузки страницы.
         *
         * Раньше «да» отправляло форму, страница перезагружалась, и разговор
         * с Анной пропадал; а нажатие и голосовое «да» подряд могли отправить
         * её дважды. Теперь запрос уходит в фоне, кнопка блокируется сразу,
         * а Анна говорит, чем кончилось.
         */
        let executing = false;

        function execute() {
            if (!pending || executing || sent) { return; }
            executing = true;
            yesBtn.disabled = true;

            const wanted = pending.action === 'accept' ? 'passed' : 'rejected';
            const approve = document.getElementById('crApprove');
            const override = document.getElementById('crOverride');

            stopListening();
            silence();
            say(@json(__('Отправляю ответ кандидату…')));
            if (window.AiAvatar) { window.AiAvatar.setState('thinking'); }

            // ИИ рекомендовал то же самое — подтверждаем его решение, иначе — своё
            const viaApprove = pending.canApprove && pending.recommended === wanted && approve;
            const target = viaApprove ? approve : override;

            if (!target) {
                executing = false;
                showFailure(@json(__('Не удалось отправить уведомление: :reason', ['reason' => __('воспользуйтесь кнопками решения ниже')])));

                return;
            }

            hideFailure();

            const body = new FormData(target);
            if (!viaApprove) {
                body.set('outcome', wanted);
                if (!body.get('note')) { body.set('note', @json(__('Решение принято в разговоре с ИИ-помощником.'))); }
            }
            // письмо — черновик под этот итог, с правками работодателя
            if (window.CrDraft) { body.set('message', window.CrDraft.get(wanted)); }

            fetch(target.action, {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                body: body,
            })
                .then(function (r) { return r.json().then(function (d) { return {ok: r.ok, data: d}; }); })
                .then(function (res) {
                    executing = false;

                    if (!res.ok) {
                        yesBtn.disabled = false;
                        showFailure(res.data.error || res.data.message
                            || @json(__('Не удалось отправить уведомление: :reason', ['reason' => __('сбой на сервере, попробуйте ещё раз')])));

                        return;
                    }

                    sent = true;
                    cancel();
                    finishAfterSend(res.data);
                })
                .catch(function () {
                    executing = false;
                    yesBtn.disabled = false;
                    showFailure(@json(__('Не удалось отправить уведомление: :reason', ['reason' => __('связь прервалась')])));
                });
        }

        // ==================== отправлено: разговор завершён ====================

        const failedBox = document.getElementById('adFailed');
        const failedText = document.getElementById('adFailedText');
        const endedRow = document.getElementById('adEnded');

        /*
         * Отправка сорвалась — красная плашка с причиной и «Повторить».
         * Разговор не завершается: пока ответ не ушёл, решение не принято.
         */
        function showFailure(message) {
            failedText.textContent = message;
            failedBox.hidden = false;
            say('');
            if (window.AiAvatar) { window.AiAvatar.setState('idle'); }
        }

        function hideFailure() { failedBox.hidden = true; }

        document.getElementById('adRetry').addEventListener('click', execute);

        /*
         * Уведомление ушло — Анна один раз говорит об этом и замолкает совсем.
         *
         * Раньше после отправки разговор продолжался: микрофон снова слушал,
         * Анна отвечала, и было непонятно, ушёл ли ответ. Теперь — зелёная
         * плашка с решением, одна фраза «Уведомление отправлено. Разговор
         * завершён», после неё микрофон, распознавание и озвучка выключены, а
         * поле и кнопки неактивны — продолжить случайно нельзя.
         */
        function finishAfterSend(data) {
            hideFailure();

            const banner = document.getElementById('crSentBanner');
            if (banner && data.banner) {
                document.getElementById('crSentText').textContent = data.banner.replace(/^✓\s*/, '');
                banner.hidden = false;
            }

            const farewell = @json(__('Уведомление отправлено. Разговор завершён.'));
            bubble('ai', farewell);
            history.push({role: 'ai', text: farewell});

            // голос больше не нужен: после прощания микрофон не включится
            voiceOn = false;
            stopListening();
            lockTalk(true);

            speak(farewell, endConversation);
        }

        /** Разговор завершён: всё выключено, статус — «Разговор завершён». */
        function endConversation() {
            voiceOn = false;
            stopListening();
            silence();
            lockTalk(true);
            say('');
            // поле и микрофон остаются видны, но неактивны: под микрофоном
            // тот же статус «Разговор завершён», что и у аватара
            endedRow.hidden = false;
            if (window.AiAvatar) { window.AiAvatar.setState('ended'); }
        }

        /** Поле, кнопки, подсказки и микрофон — неактивны, пока разговор завершён. */
        function lockTalk(locked) {
            [input, send, mic].concat(Array.from(talk.querySelectorAll('[data-ask]'))).forEach(function (node) {
                node.disabled = locked;
            });
            mic.classList.remove('is-on');
        }

        /*
         * «Открыть обсуждение заново» — поговорить ещё раз. Новой отправки без
         * нового явного подтверждения не будет: ответ уже ушёл (sent), и
         * подтверждение больше не предлагается — изменить решение можно только
         * формой «Ваше решение».
         */
        document.getElementById('adReopen').addEventListener('click', function () {
            endedRow.hidden = true;
            lockTalk(false);
            if (window.AiAvatar) { window.AiAvatar.setState('idle'); }

            if (window.AiAdvisorActive) {
                talk.hidden = false;
            } else {
                startRow.hidden = false;
            }
        });

        // ответ отправлен раньше — сразу итог, Анна разговор не начинает
        if (sent && window.AiAvatar) { window.AiAvatar.setState('ended'); }

        const yesBtn = document.getElementById('adYes');
        yesBtn.addEventListener('click', execute);
        document.getElementById('adNo').addEventListener('click', function () {
            cancel();
            handle(@json(__('Нет, не отправляй пока.')));
        });

        /*
         * Согласие на отправку.
         *
         * Раньше согласием считалась только короткая фраза — до четырёх слов. «Да,
         * отправляй уведомление кандидату» длиннее, уходила модели как вопрос,
         * модель снова спрашивала «Отправить?», и так по кругу. Теперь:
         * — «отправляй», «подтверждаю» и подобные — согласие при любой длине;
         * — голое «да», «ок», «конечно» — согласие, если фраза короткая (до трёх
         *   слов): «да, расскажи подробнее» — это уже просьба, а не согласие;
         * — «нет», «не», «но», «подожди» где угодно — не согласие;
         * — фраза о другом исходе («отправляй отказ», когда предложен приём) —
         *   не согласие, а новый вопрос Анне.
         */
        const YES = ['да', 'ҳа', 'ha', 'yes', 'ок', 'ok', 'okay', 'давай', 'конечно', 'ага', 'согласен', 'согласна'];
        const SEND = /^(отправ|подтвержд|send|confirm|фирист|тасдиқ)/;
        const NO = ['нет', 'не', 'но', 'no', 'not', "don't", 'отмена', 'отмени', 'подожди', 'погоди', 'стоп'];
        // таджикское «на» — «нет»; по-русски это предлог («отправляй на почту»)
        if (locale === 'tg') { NO.push('на'); }
        const OTHER = {
            accept: /^(отказ|откаж|reject|рад)/,
            reject: /^(прин|приня|пригла|accept|hire|қабул)/,
        };

        function answerKind(text, action) {
            if (/\?/.test(text)) { return null; }

            const words = text.toLowerCase().replace(/[.,!…;:«»"]/g, ' ').trim().split(/\s+/).filter(Boolean);

            if (!words.length) { return null; }
            if (words.some(function (w) { return NO.indexOf(w) !== -1; })) { return 'no'; }
            if (action && OTHER[action] && words.some(function (w) { return OTHER[action].test(w); })) { return null; }
            if (words.some(function (w) { return SEND.test(w); })) { return 'yes'; }
            if (YES.indexOf(words[0]) !== -1 && words.length <= 3) { return 'yes'; }

            return null;
        }

        /** Реплика работодателя: «да»/«нет» в ответ на предложение — иначе вопрос Анне. */
        function handle(text) {
            if (pending) {
                const reply = answerKind(text, pending.action);
                if (reply === 'yes') { execute(); return; }
                if (reply === 'no') { cancel(); }
            }

            ask(text);
        }

        // ==================== голос работодателя ====================

        const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        const browserLang = {ru: 'ru-RU', en: 'en-US'}[locale];
        const canMic = !!(window.isSecureContext && navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
        // распознавание на сервере — только если сервер его умеет (есть ключ)
        const serverCapable = canMic && @json(app(\App\Services\Ai\SpeechRecognizer::class)->available())
            && !!(window.AudioContext || window.webkitAudioContext);
        // микрофон браузер уже дал — «not-allowed» тогда говорит о службе
        // распознавания, а не о микрофоне
        let micGranted = false;
        // Анна говорит — микрофон не слушает
        let talking = false;
        let engine = Recognition && browserLang && window.isSecureContext ? 'browser' : (serverCapable ? 'server' : null);
        const SILENCE_MS = 2000;
        // браузер сам сказал «фраза закончилась» — ждём лишь короткую паузу
        const SETTLE_MS = 1000;
        // дольше трёх минут одну реплику не записываем: раньше у записи на
        // сервер предела не было, и в шумной комнате она не кончалась никогда
        const MAX_RECORD_MS = 180000;

        let rec = null, listening = false, heard = '', heardAt = 0, spoke = false, watch = null;
        let startedAt = 0, lastFinal = false, speechEnded = false;
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
                if (engine === 'server') { ensureContext(); }
                startListening();
            } else {
                stopListening();
                say('');
            }
        }

        mic.addEventListener('click', function () { switchVoice(!voiceOn); });

        function ensureContext() {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            ctx = ctx || new Ctx();
            if (ctx.state === 'suspended') { ctx.resume(); }
        }

        /*
         * Распознавание браузера не работает — дальше слушает сервер.
         *
         * Раньше ошибка network (нет доступа к серверам Google, Яндекс
         * Браузер, Opera) не обрабатывалась вовсе: распознавание
         * перезапускалось по кругу, кнопка горела зелёным, а голос не брался.
         */
        function fallbackToServer() {
            if (engine !== 'browser') { return; }

            stopListening();

            if (!serverCapable) {
                voiceOn = false;
                mic.classList.remove('is-on');
                say(@json(__('Распознавание речи в этом браузере не работает — пишите текстом. Лучше всего работает Chrome или Edge.')));

                return;
            }

            engine = 'server';
            ensureContext();
            startListening();
            say(@json(__('Распознавание браузера не отвечает — слушаю через сервер. Говорите, отправлю, когда замолчите.')));
        }

        function startListening() {
            // пока Анна говорит, микрофон молчит: иначе он услышит её саму
            if (!voiceOn || listening || busy || talking) { return; }

            listening = true;
            heard = '';
            spoke = false;
            lastFinal = false;
            speechEnded = false;
            startedAt = Date.now();
            say(@json(__('Говорите — отправлю сам, когда вы замолчите.')));
            // «Слушаю вас» — когда микрофон действительно включится (micStarted)

            if (engine === 'browser') {
                rec = new Recognition();
                rec.lang = browserLang;
                rec.continuous = true;
                rec.interimResults = true;
                let finals = '';
                let heardAudio = false;
                let quickEnds = 0;
                let runStarted = Date.now();
                const mine = rec;

                rec.onaudiostart = function () {
                    heardAudio = true;
                    if (rec === mine) { micStarted(); }
                };

                // браузер услышал конец речи — ответ уйдёт после короткой паузы
                rec.onspeechend = function () {
                    if (rec === mine) { speechEnded = true; }
                };

                // служба так и не взяла звук — она недоступна, хоть и молчит об этом
                setTimeout(function () {
                    if (rec === mine && listening && !heardAudio) { fallbackToServer(); }
                }, 6000);

                rec.onresult = function (event) {
                    // после остановки служба ещё может прислать хвост — он не наш
                    if (rec !== mine || !listening) { return; }

                    let interim = '';
                    for (let i = event.resultIndex; i < event.results.length; i++) {
                        if (event.results[i].isFinal) { finals += event.results[i][0].transcript + ' '; }
                        else { interim += event.results[i][0].transcript; }
                    }
                    heard = (finals + interim).trim();
                    input.value = heard;
                    spoke = true;
                    speechEnded = false;
                    lastFinal = interim === '';
                    heardAt = Date.now();
                };
                rec.onend = function () {
                    if (!listening || rec !== mine) { return; }

                    // сеанс обрывается сразу после запуска — служба не работает
                    quickEnds = Date.now() - runStarted < 1500 ? quickEnds + 1 : 0;
                    if (quickEnds >= 3) { fallbackToServer(); return; }

                    runStarted = Date.now();
                    try { rec.start(); } catch (e) {}
                };
                rec.onerror = function (event) {
                    if (rec !== mine) { return; }

                    if (event.error === 'not-allowed' && !micGranted) {
                        voiceOn = false;
                        mic.classList.remove('is-on');
                        stopListening();
                        say(@json(__('Браузер не дал доступ к микрофону. Разрешите его в настройках сайта или пишите текстом.')));
                    } else if (['network', 'service-not-allowed', 'not-allowed', 'language-not-supported'].indexOf(event.error) !== -1) {
                        fallbackToServer();
                    }
                };

                // заранее спрашиваем микрофон: если он есть, отказ службы
                // распознавания — повод перейти на сервер, а не выключить голос
                if (canMic && !micGranted) {
                    navigator.mediaDevices.getUserMedia({audio: true}).then(function (s) {
                        micGranted = true;
                        s.getTracks().forEach(function (t) { t.stop(); });
                    }).catch(function () {});
                }

                try { rec.start(); } catch (e) {}
            } else {
                const begin = stream ? Promise.resolve(stream) : navigator.mediaDevices.getUserMedia({audio: {echoCancellation: true, noiseSuppression: true}});

                begin.then(function (s) {
                    stream = s;
                    micGranted = true;
                    if (!listening) { return; }
                    chunks = [];
                    source = ctx.createMediaStreamSource(s);
                    proc = ctx.createScriptProcessor(4096, 1, 1);

                    /*
                     * Голос — то, что заметно громче фона этой комнаты.
                     * Раньше порог был постоянным (0,02), и в шумной комнате
                     * фон считался речью: тишина не наступала, «Слушаю вас»
                     * висело, а реплика не уходила никогда.
                     */
                    const begun = Date.now();
                    let floorSum = 0, floorCount = 0;

                    proc.onaudioprocess = function (event) {
                        if (!listening) { return; }
                        const data = event.inputBuffer.getChannelData(0);
                        chunks.push(new Float32Array(data));
                        let sum = 0;
                        for (let i = 0; i < data.length; i++) { sum += data[i] * data[i]; }
                        const rms = Math.sqrt(sum / data.length);

                        // первые полсекунды — мерим фон
                        if (Date.now() - begun < 500) { floorSum += rms; floorCount++; return; }

                        const floor = floorCount ? floorSum / floorCount : 0;
                        if (rms > Math.max(0.02, floor * 2.5)) { spoke = true; heardAt = Date.now(); }
                    };
                    source.connect(proc);
                    proc.connect(ctx.destination);
                    micStarted();
                }).catch(function () {
                    voiceOn = false;
                    mic.classList.remove('is-on');
                    stopListening();
                    say(@json(__('Браузер не дал доступ к микрофону. Разрешите его в настройках сайта или пишите текстом.')));
                });
            }

            clearInterval(watch);
            watch = setInterval(function () {
                if (!listening) { return; }

                const quiet = Date.now() - heardAt;

                // договорил: браузер сам сказал «конец фразы» — или долгая тишина
                if (spoke && (speechEnded || lastFinal) && quiet > SETTLE_MS) { finishListening(); }
                else if (spoke && quiet > SILENCE_MS) { finishListening(); }
                else if (Date.now() - startedAt > MAX_RECORD_MS) { finishListening(); }
            }, 200);
        }

        /** Микрофон включился и слышит — только теперь «Слушаю вас». */
        function micStarted() {
            if (listening && window.AiAvatar) { window.AiAvatar.setState('listening'); }
        }

        function stopListening() {
            listening = false;
            clearInterval(watch);
            if (rec) { const r = rec; rec = null; try { r.stop(); } catch (e) {} }
            try { source && source.disconnect(); } catch (e) {}
            try { proc && proc.disconnect(); } catch (e) {}
            source = null;
            proc = null;

            // микрофон выключен — «Слушаю вас» больше не правда
            if (window.AiAvatar && window.AiAvatar.state() === 'listening') { window.AiAvatar.setState('idle'); }
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
            if (window.AiAvatar) { window.AiAvatar.setState('thinking'); }
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
