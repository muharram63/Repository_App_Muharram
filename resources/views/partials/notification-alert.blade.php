{{--
    Новое уведомление — со звуком и всплывающей карточкой.

    Раньше уведомления приходили молча: число на значке менялось, и только. А
    ответ кандидату, отчёт ИИ для работодателя, приглашение на встречу — то, что
    человек ждёт. Теперь при новом уведомлении звучит короткий сигнал, в углу
    появляется карточка с заголовком и ссылкой, а в заголовке вкладки мигает
    «(1) Новое уведомление», если человек смотрит на другую вкладку.

    Подключается через боковое меню кабинетов и шапку сайта — то есть на каждой
    странице, включая ИИ-собеседование и отчёты, где колокольчика нет.

    Звук браузер даёт только после первого нажатия на странице: до него карточка
    появится без звука. Звук можно выключить кнопкой на карточке.
--}}
@auth
<div class="na-toast" id="naToast" role="status" aria-live="polite" hidden>
    <span class="na-ico" id="naIcon">🔔</span>
    <a class="na-body" id="naLink" href="#">
        <b id="naTitle"></b>
        <span id="naText"></span>
    </a>
    <div class="na-side">
        <button type="button" class="na-x" id="naClose" aria-label="{{ __('Закрыть') }}">×</button>
        <button type="button" class="na-mute" id="naMute"></button>
    </div>
</div>

<style>
    .na-toast {
        position: fixed; right: 20px; bottom: 20px; z-index: 9999; width: min(360px, calc(100vw - 32px));
        display: flex; gap: 12px; align-items: flex-start; padding: 14px 14px 14px 16px;
        background: var(--white, #fff); color: var(--ink, #111827);
        border: 1px solid var(--gray-200, #E5E7EB); border-left: 4px solid var(--indigo-600, #4F46E5);
        border-radius: 14px; box-shadow: 0 12px 32px rgba(17, 24, 39, .18);
        animation: naIn .35s ease-out;
    }
    .na-toast[hidden] { display: none !important; }
    .na-ico { font-size: 22px; line-height: 1; animation: naRing .9s ease-in-out 2; transform-origin: 50% 0; }
    .na-body { flex: 1; min-width: 0; text-decoration: none; color: inherit; overflow-wrap: anywhere; }
    .na-body b { display: block; font-size: 14px; margin-bottom: 3px; }
    .na-body span { display: block; font-size: 13px; color: var(--gray-500, #6B7280); line-height: 1.4; }
    .na-side { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; }
    .na-x { border: 0; background: transparent; font-size: 20px; line-height: 1; cursor: pointer; color: var(--gray-500, #6B7280); }
    .na-mute { border: 0; background: transparent; font-size: 11px; cursor: pointer; color: var(--gray-500, #6B7280); white-space: nowrap; }
    @keyframes naIn { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
    @keyframes naRing {
        0%, 100% { transform: rotate(0); } 20% { transform: rotate(16deg); }
        40% { transform: rotate(-14deg); } 60% { transform: rotate(9deg); } 80% { transform: rotate(-5deg); }
    }
    /* телефон: карточка по ширине экрана с равными полями, а не прижатой справа */
    @media (max-width: 560px) {
        .na-toast { left: 12px; right: 12px; bottom: 12px; width: auto; }
    }
    @media (prefers-reduced-motion: reduce) { .na-toast, .na-ico { animation: none; } }
</style>

<script>
    (function () {
        const url = @json(route('public.notifications.count'));
        // время последнего показанного уведомления (секунды сервера)
        const LAST = 'na-last-ts';
        const SOUND = 'na-sound';
        // уведомление в браузере: работодатель включил этот канал в настройках
        const browserOn = @json((bool) auth()->user()->setting('channel_browser'));

        const toast = document.getElementById('naToast');
        const icon = document.getElementById('naIcon');
        const link = document.getElementById('naLink');
        const title = document.getElementById('naTitle');
        const text = document.getElementById('naText');
        const mute = document.getElementById('naMute');

        const pageTitle = document.title;
        let blink = null;
        let hideTimer = null;
        let ctx = null;

        function stored(key, fallback) {
            try { return localStorage.getItem(key) ?? fallback; } catch (e) { return fallback; }
        }

        function store(key, value) {
            try { localStorage.setItem(key, value); } catch (e) {}
        }

        function soundOn() { return stored(SOUND, 'on') !== 'off'; }

        function paintMute() {
            mute.textContent = soundOn() ? @json('🔊 '.__('Звук включён')) : @json('🔇 '.__('Без звука'));
        }

        // звук браузер разрешает только после первого действия на странице
        function unlock() {
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                ctx = ctx || (Ctx ? new Ctx() : null);
                if (ctx && ctx.state === 'suspended') { ctx.resume(); }
            } catch (e) {}
        }

        ['click', 'keydown', 'touchstart'].forEach(function (evt) {
            document.addEventListener(evt, unlock, {passive: true});
        });

        /** Короткий мягкий сигнал из двух нот — без звуковых файлов. */
        function chime() {
            if (!soundOn() || !ctx || ctx.state !== 'running') { return; }

            [[880, 0], [1318.5, 0.16]].forEach(function (note) {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                const start = ctx.currentTime + note[1];

                osc.type = 'sine';
                osc.frequency.value = note[0];
                gain.gain.setValueAtTime(0.0001, start);
                gain.gain.exponentialRampToValueAtTime(0.18, start + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.45);
                osc.connect(gain).connect(ctx.destination);
                osc.start(start);
                osc.stop(start + 0.5);
            });
        }

        function flashTitle() {
            if (!document.hidden) { return; }

            clearInterval(blink);
            let on = false;
            blink = setInterval(function () {
                on = !on;
                document.title = on ? '(1) ' + @json(__('Новое уведомление')) : pageTitle;
            }, 1000);
        }

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                clearInterval(blink);
                document.title = pageTitle;
            }
        });

        function show(note) {
            icon.textContent = note.icon || '🔔';
            title.textContent = note.title || '';
            text.textContent = note.body || '';
            link.href = note.url || '#';

            // перезапуск анимации колокольчика
            icon.style.animation = 'none';
            void icon.offsetWidth;
            icon.style.animation = '';

            toast.hidden = false;
            chime();
            flashTitle();
            notifyBrowser(note);

            clearTimeout(hideTimer);
            hideTimer = setTimeout(function () { toast.hidden = true; }, 12000);
        }

        /*
         * Системное уведомление браузера — когда вкладка свёрнута или на
         * другой. Карточка в углу видна, только если человек смотрит на
         * страницу; работодатель с десятком вкладок пропустил бы кандидата.
         * Браузер показывает его лишь с разрешения (кнопка в настройках).
         */
        function notifyBrowser(note) {
            if (!browserOn || !document.hidden || !('Notification' in window) || Notification.permission !== 'granted') { return; }

            try {
                const shown = new Notification(note.title || '', {body: note.body || '', tag: 'workio-' + note.id});
                shown.onclick = function () {
                    window.focus();
                    if (note.url) { window.location.href = note.url; }
                    shown.close();
                };
            } catch (e) {}
        }

        document.getElementById('naClose').addEventListener('click', function () { toast.hidden = true; });
        mute.addEventListener('click', function () {
            store(SOUND, soundOn() ? 'off' : 'on');
            paintMute();
            if (soundOn()) { unlock(); chime(); }
        });
        paintMute();

        function check() {
            fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}})
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!data) { return; }

                    const latest = data.latest;

                    /*
                     * Первый заход на этом устройстве: старые уведомления не
                     * озвучиваем — запоминаем «сейчас». Именно время сервера,
                     * а не последнее уведомление: если непрочитанных нет,
                     * запоминать было бы нечего, и первое настоящее
                     * уведомление пропало бы молча.
                     */
                    if (stored(LAST, null) === null) {
                        store(LAST, String(latest ? Math.max(latest.ts || 0, data.now || 0) : (data.now || 0)));

                        return;
                    }

                    if (!latest) { return; }

                    // сравниваем по времени, а не по номеру: склеенное уведомление
                    // («откликов: 3») обновляется на месте и номер не меняет
                    const last = parseInt(stored(LAST, '0'), 10) || 0;

                    if ((latest.ts || 0) > last) {
                        store(LAST, String(latest.ts));
                        show(latest);
                    }
                })
                .catch(function () {});
        }

        check();
        setInterval(check, 15000);
    })();
</script>
@endauth
