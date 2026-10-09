{{--
    Голосовой аватар ИИ-собеседования.

    Компонент самодостаточный: свои стили и свой скрипт, никаких переменных
    страницы, кроме необязательных параметров. Встаёт на страницу собеседования
    одним @include — так же, как partials/match-analysis.

    $avatarName  — как аватар подписан (по умолчанию «Ассистент»)
    $avatarRole  — подпись под именем
    $muteKey     — ключ в localStorage для памяти о выключенном звуке

    Цвета взяты из токенов кабинета (--indigo-600, --ink, --white, --gray-*),
    поэтому тёмная тема работает сама: токены переопределены в
    applicant/partials/css, а здесь не дублируется ни одно правило.

    Управление снаружи:
        AiAvatar.speak({ text, audioUrl, statusUrl })
        AiAvatar.setState('idle' | 'thinking' | 'listening' | 'speaking')
        AiAvatar.pause() / .resume() / .repeat() / .stop()
        AiAvatar.mute(true|false) / .muted()
        AiAvatar.diagnostics()   — чем именно сейчас говорит и анализирует
--}}
@php
    $avatarName = $avatarName ?? 'Ассистент';
    $avatarRole = $avatarRole ?? 'ИИ-собеседование';
    $muteKey = $muteKey ?? 'ai-avatar-voice';
@endphp

<div class="av-card" id="avCard" data-mute-key="{{ $muteKey }}" data-voice-key="{{ $voiceKey ?? '' }}">
    <div class="av-stage">
        {{--
            Фигура. Всё, что движется, двигается через transform: это единственное
            свойство, которое браузер анимирует без перерисовки слоя, и на слабой
            машине рот не начинает отставать от звука.
        --}}
        <svg class="av-figure" id="avFigure" viewBox="0 0 220 220" role="img"
             aria-label="{{ __('Аватар ИИ-собеседования') }}" data-state="idle">
            {{-- контур пульсирует, когда аватар слушает --}}
            <circle class="av-ring" cx="110" cy="110" r="86"/>
            <circle class="av-ring av-ring-2" cx="110" cy="110" r="86"/>

            <g class="av-body">
                {{-- плечи --}}
                <path class="av-shoulders" d="M40 214c0-34 31-52 70-52s70 18 70 52z"/>
                <rect class="av-neck" x="98" y="128" width="24" height="26" rx="10"/>

                <g class="av-head">
                    {{-- голова --}}
                    <rect class="av-face" x="58" y="40" width="104" height="112" rx="44"/>
                    {{-- волосы: простая шапка, без попытки изобразить причёску --}}
                    <path class="av-hair" d="M58 88c0-30 23-50 52-50s52 20 52 50c-8-14-26-20-52-20s-44 6-52 20z"/>

                    {{-- брови: приподнимаются в состоянии «думает» --}}
                    <g class="av-brows">
                        <rect class="av-brow" x="76" y="86" width="22" height="5" rx="2.5"/>
                        <rect class="av-brow" x="122" y="86" width="22" height="5" rx="2.5"/>
                    </g>

                    {{-- глаза; веко закрывается через scaleY, поэтому моргание
                         не требует ни второй картинки, ни перерисовки --}}
                    <g class="av-eye av-eye-l">
                        <ellipse class="av-eye-white" cx="88" cy="103" rx="10" ry="11"/>
                        <circle class="av-pupil" cx="88" cy="103" r="4.6"/>
                        <rect class="av-lid" x="77" y="91" width="22" height="24" rx="10"/>
                    </g>
                    <g class="av-eye av-eye-r">
                        <ellipse class="av-eye-white" cx="132" cy="103" rx="10" ry="11"/>
                        <circle class="av-pupil" cx="132" cy="103" r="4.6"/>
                        <rect class="av-lid" x="121" y="91" width="22" height="24" rx="10"/>
                    </g>

                    {{--
                        Рот. Одна фигура, растянутая по вертикали переменной
                        --mouth: 0 — закрыт, 1 — широко открыт. Три требуемых
                        состояния задаются порогами в скрипте, но переход между
                        ними непрерывный, поэтому рот не щёлкает между тремя
                        картинками, а двигается.
                    --}}
                    <g class="av-mouth-wrap">
                        <ellipse class="av-mouth" cx="110" cy="130" rx="17" ry="12"/>
                        <ellipse class="av-tongue" cx="110" cy="136" rx="9" ry="5"/>
                    </g>
                </g>
            </g>

            {{-- точки раздумья --}}
            <g class="av-think">
                <circle cx="176" cy="52" r="5"/>
                <circle cx="192" cy="44" r="4"/>
                <circle cx="205" cy="38" r="3"/>
            </g>
        </svg>

        <div class="av-meta">
            <div class="av-name">{{ $avatarName }}</div>
            <div class="av-role">{{ $avatarRole }}</div>
            {{-- голос браузера вместо голоса Анны: лимит озвучки Gemini исчерпан --}}
            <div class="av-voice-note" hidden
                 title="{{ __('Озвучка Gemini временно недоступна (исчерпан лимит), поэтому вопросы читает голос браузера. Голос Анны вернётся, когда лимит обновится.') }}">
                {{ __('упрощённый голос') }}
            </div>
            <div class="av-state" id="avState" data-ai-state="idle">{{ __('Ожидание') }}</div>
        </div>
    </div>

    <div class="av-controls">
        <button type="button" class="av-btn" id="avPlay" hidden>
            <span id="avPlayLabel">{{ __('Озвучить') }}</span>
        </button>
        <button type="button" class="av-btn" id="avRepeat">{{ __('Повторить') }}</button>
        <button type="button" class="av-btn av-btn-ghost" id="avMute"
                aria-pressed="false">{{ __('Звук включён') }}</button>
    </div>

    {{-- Скрытый проигрыватель. Именно он, а не Audio(), — из элемента можно
         сделать источник для анализатора громкости. --}}
    <audio id="avAudio" preload="auto" crossorigin="anonymous"></audio>
</div>

<style>
    /* Скрытие атрибутом hidden должно побеждать раскладку: .av-btn{display:flex}
       иначе перебивает браузерное [hidden]{display:none}. Та же оговорка, что
       в partials/match-analysis. */
    .av-card [hidden] { display: none !important; }

    .av-card {
        display: flex; flex-direction: column; gap: 14px; min-width: 0; max-width: 100%; box-sizing: border-box;
        padding: 18px; border-radius: var(--radius, 12px);
        background: var(--white, #fff); border: 1px solid var(--gray-200, #E5E7EB);
    }

    /* Подпись рядом с фигурой, а если места мало — под ней: в узкой колонке
       «HR-специалист, ИИ» не помещалась справа и вылезала за рамку. */
    .av-stage { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 12px 16px; min-width: 0; }

    .av-figure { width: 132px; height: 132px; flex: none; overflow: visible; }

    .av-meta { min-width: 0; flex: 1 1 120px; overflow-wrap: break-word; hyphens: auto; }
    .av-name { font-weight: 700; color: var(--ink, #111827); line-height: 1.3; }
    .av-role { font-size: 13px; color: var(--gray-500, #6B7280); line-height: 1.35; margin-top: 2px; }
    .av-voice-note { display: inline-block; margin-top: 4px; padding: 2px 8px; border-radius: 999px; font-size: 11.5px; font-weight: 600; background: var(--wash-warn, #FEF3C7); color: var(--ink-warn, #92400E); cursor: help; }
    .av-voice-note[hidden] { display: none; }
    .av-state {
        margin-top: 6px; font-size: 12px; font-weight: 600;
        color: var(--indigo-600, #4F46E5);
    }

    /* ---------- фигура ---------- */

    .av-shoulders { fill: var(--indigo-600, #4F46E5); opacity: .92; }
    .av-neck      { fill: #E9B58F; }
    .av-face      { fill: #F3C39C; }
    .av-hair      { fill: #3B3A4A; }
    .av-brow      { fill: #3B3A4A; }
    .av-eye-white { fill: #FFFFFF; }
    .av-pupil     { fill: #2A2A38; }
    .av-lid       { fill: #F3C39C; }
    .av-mouth     { fill: #7E3B42; }
    .av-tongue    { fill: #C96A72; opacity: .85; }

    /* Дыхание в покое: едва заметное, 1.5 пикселя. Больше — и аватар
       начинает казаться качающимся, а не живым. */
    .av-body { animation: av-breathe 4.2s ease-in-out infinite; }
    @keyframes av-breathe {
        0%, 100% { transform: translateY(0); }
        50%      { transform: translateY(1.5px); }
    }

    /* Голова слегка ведёт за дыханием, но с другим периодом: синхронное
       движение выглядит механическим. */
    .av-head { animation: av-sway 6.5s ease-in-out infinite; transform-origin: 110px 150px; }
    @keyframes av-sway {
        0%, 100% { transform: rotate(-0.6deg); }
        50%      { transform: rotate(0.6deg); }
    }

    /* ---------- моргание ---------- */

    /* Веко закрыто при --lid = 1. Пружинит из центра глаза, поэтому
       transform-origin задан по каждому глазу отдельно. */
    .av-lid { transform: scaleY(var(--lid, 0)); transition: transform 90ms ease-out; }
    .av-eye-l .av-lid { transform-origin: 88px 91px; }
    .av-eye-r .av-lid { transform-origin: 132px 91px; }

    /* ---------- рот ---------- */

    /* Открытие рта: по вертикали сильно, по горизонтали чуть-чуть. Человек,
       говоря, растягивает рот заметно меньше, чем открывает, — при равном
       масштабировании получается рыба. */
    .av-mouth-wrap {
        transform: scale(calc(0.94 + var(--mouth, 0) * 0.16), calc(0.14 + var(--mouth, 0) * 1.5));
        transform-origin: 110px 128px;
        /* Переход короткий: длинный превратил бы липсинк в кашу, потому что
           значение обновляется каждый кадр. Он нужен только чтобы сгладить
           дрожание между кадрами. */
        transition: transform 60ms linear;
    }
    .av-tongue { opacity: calc(var(--mouth, 0) * 0.9); }

    /* ---------- контур: слушает ---------- */

    .av-ring {
        fill: none; stroke: var(--indigo-600, #4F46E5); stroke-width: 2;
        opacity: 0; transform-origin: 110px 110px;
    }
    .av-figure[data-state="listening"] .av-ring { animation: av-pulse 1.9s ease-out infinite; }
    .av-figure[data-state="listening"] .av-ring-2 { animation-delay: .95s; }
    @keyframes av-pulse {
        0%   { opacity: .55; transform: scale(.94); }
        70%  { opacity: 0;   transform: scale(1.12); }
        100% { opacity: 0;   transform: scale(1.12); }
    }

    /* ---------- думает ---------- */

    .av-think circle { fill: var(--indigo-600, #4F46E5); opacity: 0; }
    .av-figure[data-state="thinking"] .av-think circle { animation: av-dots 1.4s ease-in-out infinite; }
    .av-figure[data-state="thinking"] .av-think circle:nth-child(2) { animation-delay: .18s; }
    .av-figure[data-state="thinking"] .av-think circle:nth-child(3) { animation-delay: .36s; }
    @keyframes av-dots {
        0%, 100% { opacity: .15; transform: translateY(1px); }
        50%      { opacity: .95; transform: translateY(-2px); }
    }

    /* Пока думает — брови вверх и взгляд в сторону: два мелких сдвига, от
       которых лицо перестаёт выглядеть пустым. */
    .av-brows, .av-pupil { transition: transform 280ms ease; }
    .av-figure[data-state="thinking"] .av-brows { transform: translateY(-3px); }
    .av-figure[data-state="thinking"] .av-pupil { transform: translate(3px, -2px); }
    .av-figure[data-state="listening"] .av-pupil { transform: translateY(1px); }

    /* ---------- органы управления ---------- */

    .av-controls { display: flex; flex-wrap: wrap; gap: 8px; }
    .av-btn {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 8px 14px; border-radius: 999px; cursor: pointer;
        font: inherit; font-size: 13px; font-weight: 600;
        background: var(--indigo-600, #4F46E5); color: #fff; border: 1px solid transparent;
    }
    .av-btn:hover { filter: brightness(1.06); }
    .av-btn-ghost {
        background: transparent; color: var(--gray-500, #6B7280);
        border-color: var(--gray-200, #E5E7EB);
    }
    .av-btn[aria-pressed="true"] { color: var(--danger, #DC2626); border-color: currentColor; }

    /*
        Уважаем настройку «меньше движения»: дыхание, покачивание и пульсация
        отключаются. Рот остаётся — он несёт смысл, а не украшает: по нему
        видно, что аватар сейчас говорит.
    */
    @media (prefers-reduced-motion: reduce) {
        .av-body, .av-head, .av-ring, .av-think circle { animation: none !important; }
        .av-brows, .av-pupil, .av-lid { transition: none; }
    }

    @media (max-width: 520px) {
        .av-figure { width: 96px; height: 96px; }
    }
</style>

<script>
    (function () {
        const card = document.getElementById('avCard');
        if (!card) { return; }

        const figure = document.getElementById('avFigure');
        const audio = document.getElementById('avAudio');
        const stateLabel = document.getElementById('avState');
        const playBtn = document.getElementById('avPlay');
        const playLabel = document.getElementById('avPlayLabel');
        const repeatBtn = document.getElementById('avRepeat');
        const muteBtn = document.getElementById('avMute');
        const muteKey = card.dataset.muteKey || 'ai-avatar-voice';

        /*
         * Четыре состояния бота — единственный источник статуса на странице.
         *
         * Раньше подпись у аватара и подпись под микрофоном писали разные
         * скрипты, каждый своё: под микрофоном «Анна думает…», а у аватара в
         * ту же секунду «Слушает вас». Теперь статус меняет только setState(),
         * и он сам пишет его во все элементы с атрибутом data-ai-state.
         */
        const STATE_LABELS = {
            idle: @json(__('Ожидание')),
            thinking: @json(__('Думаю')),
            listening: @json(__('Слушаю вас')),
            speaking: @json(__('Говорю')),
        };

        /*
         * Голос — один на всё собеседование.
         *
         * Раньше голос выбирался для каждой реплики заново: одна звучала
         * голосом Gemini, следующая, когда лимит озвучки кончался, — голосом
         * браузера, а голос браузера каждый раз подбирался из списка, который
         * браузер к первой реплике ещё не загрузил, — и первая звучала голосом
         * по умолчанию, часто мужским. Теперь: пока Gemini озвучивает, звучит
         * его голос; как только он хоть раз не смог, до конца собеседования
         * звучит один и тот же голос браузера, выбранный один раз, с теми же
         * скоростью и высотой. Выбор помнится до закрытия вкладки — перезагрузка
         * страницы голос не меняет.
         */
        const voiceKey = card.dataset.voiceKey ? 'ai-voice:' + card.dataset.voiceKey : null;
        const VOICE_RATE = 0.98;
        const VOICE_PITCH = 1.0;
        let voiceMode = null;          // 'browser' — Gemini больше не зовём
        let browserVoice;              // undefined — ещё не выбран
        let browserTicket = 0;         // новая реплика отменяет ожидание голосов

        function remembered(suffix) {
            try { return voiceKey ? sessionStorage.getItem(voiceKey + suffix) : null; } catch (e) { return null; }
        }

        function remember(suffix, value) {
            try { if (voiceKey) { sessionStorage.setItem(voiceKey + suffix, value); } } catch (e) {}
        }

        if (remembered(':mode') === 'browser') { voiceMode = 'browser'; }

        /** Gemini не смог — дальше только голос браузера, без возврата. */
        function lockBrowser() {
            voiceMode = 'browser';
            remember(':mode', 'browser');
        }

        /*
            Пороги громкости для трёх состояний рта. Гистерезис нужен, иначе на
            границе рот дребезжит: значение колеблется вокруг порога и
            переключает состояние каждый кадр.
        */
        const MOUTH = {closed: 0.06, half: 0.18, hysteresis: 0.02};

        let ctx = null;         // AudioContext
        let analyser = null;
        let source = null;      // источник из элемента <audio>, создаётся один раз
        let buffer = null;
        let frame = null;
        let state = 'idle';
        let muted = false;
        let last = null;        // последняя реплика: нужна кнопке «Повторить»
        let envelope = 0;       // сглаженная громкость
        let step = 0;           // ступень рта: 0 закрыт, 1 приоткрыт, 2 широко
        let synthetic = null;   // таймер псевдоогибающей для голоса браузера
        let blinkTimer = null;
        let statusPoll = null;
        // текущее ожидание звука: новая реплика отменяет прежнее
        let waiting = null;

        try { muted = localStorage.getItem(muteKey) === 'off'; } catch (e) {}

        // ==================== состояние ====================

        function setState(next) {
            if (!STATE_LABELS[next]) { return; }
            state = next;
            figure.setAttribute('data-state', next);

            // подпись аватара и подписи под микрофоном — один и тот же текст
            document.querySelectorAll('[data-ai-state]').forEach(function (node) {
                node.textContent = STATE_LABELS[next];
                node.setAttribute('data-ai-state', next);
            });
        }

        function setMouth(value) {
            const level = Math.max(0, Math.min(1, value));
            figure.style.setProperty('--mouth', level.toFixed(3));
        }

        function closeMouth() {
            envelope = 0;
            step = 0;
            setMouth(0);
        }

        // ==================== моргание ====================

        function blink() {
            const lids = figure.querySelectorAll('.av-lid');
            lids.forEach(function (lid) { lid.style.setProperty('--lid', '1'); });

            setTimeout(function () {
                lids.forEach(function (lid) { lid.style.setProperty('--lid', '0'); });
            }, 110);
        }

        function scheduleBlink() {
            clearTimeout(blinkTimer);

            // говорящий человек моргает реже, слушающий — чаще
            const base = state === 'speaking' ? 4200 : (state === 'listening' ? 2600 : 3200);

            blinkTimer = setTimeout(function () {
                blink();
                scheduleBlink();
            }, base + Math.random() * 1800);
        }

        // ==================== громкость ====================

        /*
            Анализатор поднимаем один раз и на один и тот же элемент <audio>:
            createMediaElementSource можно вызвать для элемента только однажды,
            повторный вызов бросает исключение и звук пропадает совсем.

            Обязательно соединяем и с ctx.destination — без этого звук уходит
            в анализатор и наружу не попадает, получается тишина при рабочем
            липсинке. Ошибка незаметная: видно, что рот движется, и кажется,
            что дело в колонках.
        */
        function ensureAnalyser() {
            if (analyser) { return true; }

            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx || !audio) { return false; }

            try {
                ctx = ctx || new Ctx();
                source = ctx.createMediaElementSource(audio);
                analyser = ctx.createAnalyser();
                analyser.fftSize = 1024;
                analyser.smoothingTimeConstant = 0.6;
                buffer = new Uint8Array(analyser.fftSize);
                source.connect(analyser);
                analyser.connect(ctx.destination);

                return true;
            } catch (e) {
                // старый браузер или запрет — рот поедет по псевдоогибающей
                analyser = null;

                return false;
            }
        }

        function unlock() {
            try {
                if (ctx && ctx.state === 'suspended') { ctx.resume(); }
            } catch (e) {}
        }

        ['click', 'keydown', 'touchstart'].forEach(function (evt) {
            document.addEventListener(evt, unlock, {passive: true});
        });

        /*
            Среднеквадратичная громкость по временной области.

            Берём именно её, а не спектр: для рта важна только сила звука, а
            частоты ничего не добавляют — зато считать их дороже каждый кадр.
        */
        function amplitude() {
            analyser.getByteTimeDomainData(buffer);

            let sum = 0;
            for (let i = 0; i < buffer.length; i++) {
                const v = (buffer[i] - 128) / 128;
                sum += v * v;
            }

            return Math.sqrt(sum / buffer.length);
        }

        /*
            Огибающая: открываем рот быстро, закрываем медленнее. При равных
            скоростях рот дёргается на каждом слоге, потому что между словами
            громкость падает в ноль на считанные кадры.
        */
        function follow(raw) {
            const rate = raw > envelope ? 0.35 : 0.12;
            envelope += (raw - envelope) * rate;

            // три состояния с гистерезисом: порог на вход и на выход разный
            const up = MOUTH.hysteresis;
            if (step === 0 && envelope > MOUTH.closed + up) { step = 1; }
            else if (step === 1 && envelope < MOUTH.closed - up) { step = 0; }
            else if (step === 1 && envelope > MOUTH.half + up) { step = 2; }
            else if (step === 2 && envelope < MOUTH.half - up) { step = 1; }

            // внутри ступени рот всё равно двигается плавно по самой громкости,
            // поэтому переходы не выглядят тремя щелчками
            const target = step === 0 ? 0 : (step === 1 ? 0.45 : 1);
            setMouth(target * Math.min(1, envelope / MOUTH.half + 0.35));
        }

        function loop() {
            if (!analyser || audio.paused || audio.ended) {
                frame = null;

                return;
            }

            follow(amplitude());
            frame = requestAnimationFrame(loop);
        }

        /*
            Псевдоогибающая для голоса браузера.

            SpeechSynthesis невозможно подать в AnalyserNode: он играет прямо в
            устройство вывода и не отдаёт ни MediaStream, ни AudioNode — ни в
            одном браузере. Настоящей громкости здесь нет и быть не может,
            поэтому рот двигаем по случайной огибающей, а на границах слов
            (onboundary) подталкиваем её вверх. Выглядит живо, но это
            приближение, а не липсинк.
        */
        function startSynthetic() {
            stopSynthetic();

            synthetic = setInterval(function () {
                follow(0.1 + Math.random() * 0.22);
            }, 110);
        }

        function stopSynthetic() {
            clearInterval(synthetic);
            synthetic = null;
        }

        // ==================== речь ====================

        function stop() {
            try { audio.pause(); audio.currentTime = 0; } catch (e) {}
            try { window.speechSynthesis && window.speechSynthesis.cancel(); } catch (e) {}
            cancelAnimationFrame(frame);
            frame = null;
            stopSynthetic();
            clearTimeout(statusPoll);
            waiting = null;
            browserTicket++;
            closeMouth();
        }

        /**
         * Озвучить реплику.
         *
         * audioUrl  — готовый файл с сервера: тогда работает настоящий липсинк;
         * statusUrl — адрес, где можно спросить, дождался ли звук синтеза;
         * text      — то, что прочитает браузер, если файла нет или он не дошёл.
         */
        /*
         * Реплика договорена — сообщаем тому, кто просил её сказать.
         *
         * Нужен голосовому режиму: как в живом разговоре, кандидат начинает
         * отвечать, когда собеседник замолчал, — и микрофон включается сам.
         * Зовётся один раз на реплику, как бы она ни закончилась: доиграл файл,
         * дочитал браузер, говорить было нечем или звук выключен.
         */
        let current = null;

        function finished() {
            const done = current && current.onEnd;
            current = null;

            if (typeof done === 'function') { done(); }
        }

        function speak(options) {
            const opts = options || {};
            last = opts;

            stop();
            current = opts;

            if (muted) { setState('idle'); finished(); return; }

            // страница сама не дозвалась до Gemini — дальше голос браузера
            if (opts.serverFailed) { lockBrowser(); }

            // голос на это собеседование уже закреплён за браузером: к Gemini
            // не обращаемся, иначе следующая реплика зазвучала бы другим голосом
            if (voiceMode === 'browser') {
                speakByBrowser(opts.text, opts.lang);
            } else if (opts.audioUrl) {
                playFile(opts.audioUrl, opts.text, opts.lang);
            } else if (opts.statusUrl) {
                // звук ещё готовится: аватар думает, текст уже на экране
                setState('thinking');
                waitForSpeech(opts);
            } else {
                speakByBrowser(opts.text, opts.lang);
            }
        }

        function playFile(url, fallbackText, lang) {
            const analysing = ensureAnalyser();
            unlock();

            // звучит голос Анны (Gemini) — пометка «упрощённый голос» не нужна
            markVoice(false);
            audio.src = url;

            const started = audio.play();

            if (started && typeof started.catch === 'function') {
                started.catch(function () {
                    /*
                        Автоплей запрещён до первого действия пользователя.
                        Это не поломка: показываем кнопку и ждём клика, а не
                        роняем собеседование.
                    */
                    showPlayButton(function () { playFile(url, fallbackText, lang); });
                });
            }

            audio.onplaying = function () {
                hidePlayButton();
                setState('speaking');
                scheduleBlink();

                if (analysing) {
                    unlock();
                    frame = frame || requestAnimationFrame(loop);
                } else {
                    // анализатора нет — рот по приближению
                    startSynthetic();
                }
            };

            audio.onended = function () {
                stopSynthetic();
                closeMouth();
                setState('idle');
                scheduleBlink();
                finished();
            };

            audio.onerror = function () {
                // файл не дошёл или битый — читаем текстом, чтобы вопрос
                // всё равно был произнесён, и дальше тем же голосом
                lockBrowser();
                speakByBrowser(fallbackText, lang);
            };
        }

        /**
         * Голос браузера. Работает без сети и без ключа, но амплитуды не даёт.
         */
        /*
         * Голос браузера под язык собеседования, а не интерфейса.
         *
         * Раньше язык брался из страницы, и при таджикском интерфейсе браузер
         * искал таджикский голос, которого почти нигде нет, — и молчал. Если
         * голоса нужного языка нет, читаем русским: таджикский пишется
         * кириллицей, и так вопрос хотя бы прозвучит.
         */
        const LOCALES = {ru: 'ru-RU', en: 'en-US', tg: 'tg-TJ'};

        // Chrome отдаёт список голосов не сразу: просим его заранее, чтобы к
        // первому вопросу нужный голос уже был известен
        try { window.speechSynthesis && window.speechSynthesis.getVoices(); } catch (e) {}

        /*
         * Голос браузера — запасной, когда озвучка Gemini недоступна (чаще
         * всего исчерпан бесплатный лимит). Раньше брался первый русский
         * голос в списке — на Windows это нередко мужской «Pavel», и Анна
         * вдруг начинала говорить мужским голосом. Теперь выбираем женский
         * и по возможности естественный (нейросетевые голоса Edge, Google).
         */
        const FEMALE = /svetlana|dariya|daria|irina|milena|ekaterina|anna|alena|alyona|elena|google русский|female|женск/i;
        const MALE = /pavel|dmitr|yuri|maxim|nikolai|male|мужск/i;

        function pickVoice(lang) {
            const voices = window.speechSynthesis ? window.speechSynthesis.getVoices() : [];
            const wanted = (lang || '').toLowerCase().slice(0, 2);

            let best = null;
            let bestScore = -1;

            voices.forEach(function (v) {
                const l = (v.lang || '').toLowerCase();
                let score = l.indexOf(wanted) === 0 ? 100 : (l.indexOf('ru') === 0 ? 40 : -1);
                if (score < 0) { return; }
                if (FEMALE.test(v.name)) { score += 30; }
                if (MALE.test(v.name) && !/female/i.test(v.name)) { score -= 30; }
                if (/natural|neural|online/i.test(v.name)) { score += 15; }
                // сетевые голоса («Google русский») звучат живее встроенных
                if (v.localService === false) { score += 10; }
                if (score > bestScore) { best = v; bestScore = score; }
            });

            return best;
        }

        /** Пометка «упрощённый голос» под именем — видно, почему голос другой. */
        function markVoice(browser) {
            const note = card.querySelector('.av-voice-note');
            if (note) { note.hidden = !browser; }
        }

        /**
         * Голос браузера на всё собеседование — выбирается один раз.
         *
         * Пока браузер не загрузил список голосов, выбор не фиксируем: иначе
         * закрепился бы голос по умолчанию. Выбранный запоминается по имени,
         * и после перезагрузки страницы берётся он же.
         */
        function sessionVoice(lang) {
            if (browserVoice !== undefined) { return browserVoice; }

            const voices = window.speechSynthesis.getVoices();
            if (!voices.length) { return null; }

            const saved = remembered(':name');
            browserVoice = (saved && voices.find(function (v) { return v.name === saved; })) || pickVoice(lang);

            if (browserVoice) { remember(':name', browserVoice.name); }

            return browserVoice;
        }

        /** Дождаться списка голосов: Chrome отдаёт его не сразу. */
        function whenVoicesReady(callback) {
            if (window.speechSynthesis.getVoices().length) { callback(); return; }

            let called = false;
            const go = function () { if (!called) { called = true; callback(); } };

            try { window.speechSynthesis.addEventListener('voiceschanged', go, {once: true}); } catch (e) {}
            setTimeout(go, 1500);
        }

        function speakByBrowser(text, lang) {
            if (!text || !window.speechSynthesis || typeof SpeechSynthesisUtterance === 'undefined') {
                // говорить нечем — аватар стоит молча, вопрос читается глазами
                setState('idle');
                closeMouth();
                finished();

                return;
            }

            markVoice(true);
            const ticket = ++browserTicket;

            whenVoicesReady(function () {
                // пока ждали голоса, пришла новая реплика или «стоп»
                if (ticket !== browserTicket) { return; }

                sayByBrowser(text, lang);
            });
        }

        function sayByBrowser(text, lang) {
            const utterance = new SpeechSynthesisUtterance(text);
            const code = lang || document.documentElement.lang || 'ru';
            const voice = sessionVoice(code);

            utterance.lang = voice ? voice.lang : (LOCALES[code] || 'ru-RU');
            if (voice) { utterance.voice = voice; }
            // скорость и высота — одни и те же на всё собеседование
            utterance.rate = VOICE_RATE;
            utterance.pitch = VOICE_PITCH;

            utterance.onstart = function () {
                setState('speaking');
                scheduleBlink();
                startSynthetic();
            };

            // границы слов — единственный настоящий сигнал, который отдаёт
            // браузерный синтез: на них подталкиваем рот шире
            utterance.onboundary = function () {
                follow(0.3 + Math.random() * 0.25);
            };

            utterance.onend = function () {
                stopSynthetic();
                closeMouth();
                setState('idle');
                scheduleBlink();
                finished();
            };

            utterance.onerror = function (event) {
                stopSynthetic();
                closeMouth();
                setState('idle');

                // браузер не даёт говорить до первого клика по странице —
                // предлагаем кнопку, а не молчим
                if (event && event.error === 'not-allowed') {
                    showPlayButton(function () { speakByBrowser(text, lang); });

                    return;
                }

                // прервали новой репликой — не наш конец; иначе договорили как смогли
                if (!event || event.error !== 'interrupted' && event.error !== 'canceled') {
                    finished();
                }
            };

            try {
                window.speechSynthesis.cancel();
                window.speechSynthesis.speak(utterance);
            } catch (e) {
                setState('idle');
                finished();
            }
        }

        /**
         * Ждём, пока очередь озвучит вопрос. Если не дождались — читает браузер.
         */
        function waitForSpeech(opts) {
            /*
             * Спрашиваем по очереди, а не по таймеру.
             *
             * Первый же запрос сам озвучивает реплику на сервере (около пяти
             * секунд, длинный вопрос — до двадцати), поэтому ждём его ответа,
             * а не шлём следом новые. Сорок пять секунд — с запасом; дальше читаем
             * голосом браузера, чтобы вопрос всё равно прозвучал.
             */
            const deadline = Date.now() + 45000;
            const ticket = {};
            waiting = ticket;

            /*
             * Срок — не только между вопросами, но и посреди запроса.
             *
             * Раньше он проверялся, лишь когда сервер ответил, а синтез может
             * висеть до полутора минут (таймаут Gemini). Всё это время Анна
             * молчала, а микрофон ждал, когда она договорит.
             */
            const giveUp = setTimeout(function () {
                if (waiting !== ticket) { return; }
                waiting = null;
                clearTimeout(statusPoll);
                lockBrowser();
                speakByBrowser(opts.text, opts.lang);
            }, 45000);

            function ask() {
                if (waiting !== ticket) { return; }

                if (Date.now() > deadline) {
                    clearTimeout(giveUp);
                    lockBrowser();
                    speakByBrowser(opts.text, opts.lang);

                    return;
                }

                fetch(opts.statusUrl, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (data) {
                        if (waiting !== ticket) { return; }

                        if (data && data.status === 'ready' && data.url) {
                            clearTimeout(giveUp);
                            playFile(data.url, opts.text, opts.lang);
                        } else if (data && (data.status === 'failed' || data.status === 'none')) {
                            clearTimeout(giveUp);
                            lockBrowser();
                            speakByBrowser(opts.text, opts.lang);
                        } else {
                            statusPoll = setTimeout(ask, 1500);
                        }
                    })
                    .catch(function () {
                        // сеть мигнула — спросим ещё раз
                        statusPoll = setTimeout(ask, 1500);
                    });
            }

            ask();
        }

        // ==================== кнопки ====================

        function showPlayButton(play) {
            playBtn.hidden = false;
            playLabel.textContent = @json(__('Озвучить'));
            setState('idle');

            playBtn.onclick = function () {
                unlock();
                playBtn.hidden = true;
                play();
            };
        }

        function hidePlayButton() { playBtn.hidden = true; }

        repeatBtn.addEventListener('click', function () {
            unlock();
            if (last) { speak(last); }
        });

        function paintMute() {
            muteBtn.textContent = muted ? @json(__('Звук выключен')) : @json(__('Звук включён'));
            muteBtn.setAttribute('aria-pressed', muted ? 'true' : 'false');
        }

        muteBtn.addEventListener('click', function () {
            muted = !muted;
            try { localStorage.setItem(muteKey, muted ? 'off' : 'on'); } catch (e) {}
            paintMute();

            if (muted) {
                stop();
                setState('idle');
                // замолчала по просьбе — для разговора это тоже «договорила»
                finished();
            } else if (last) {
                speak(last);
            }
        });

        paintMute();
        scheduleBlink();
        closeMouth();
        // подписи статуса под микрофоном появляются в разметке раньше скрипта —
        // сразу показываем в них то же, что у аватара
        document.addEventListener('DOMContentLoaded', function () { setState(state); });
        setState(state);

        // ==================== наружу ====================

        window.AiAvatar = {
            speak: speak,
            setState: setState,
            stop: stop,
            pause: function () {
                try { audio.pause(); } catch (e) {}
                try { window.speechSynthesis && window.speechSynthesis.pause(); } catch (e) {}
                stopSynthetic();
                closeMouth();
                setState('idle');
            },
            resume: function () {
                unlock();
                try { audio.play(); } catch (e) {}
                try { window.speechSynthesis && window.speechSynthesis.resume(); } catch (e) {}
            },
            repeat: function () { if (last) { speak(last); } },
            // запомнить реплику для «Повторить», не произнося её сейчас
            remember: function (options) { last = options || null; },
            // текущее состояние: страница сверяется с ним, а не держит своё
            state: function () { return state; },
            // голос закреплён за браузером — Gemini на этом собеседовании не зовём
            usesBrowserVoice: function () { return voiceMode === 'browser'; },
            mute: function (value) {
                muted = !!value;
                try { localStorage.setItem(muteKey, muted ? 'off' : 'on'); } catch (e) {}
                paintMute();
                if (muted) { stop(); setState('idle'); }
            },
            muted: function () { return muted; },

            /**
             * Чем аватар располагает в этом браузере. Нужно странице проверки
             * и поддержке: «почему нет голоса» выясняется одним вызовом.
             */
            diagnostics: function () {
                return {
                    audioContext: !!(window.AudioContext || window.webkitAudioContext),
                    analyser: !!analyser,
                    contextState: ctx ? ctx.state : null,
                    speechSynthesis: !!window.speechSynthesis,
                    state: state,
                    muted: muted,
                };
            },
        };
    })();
</script>
