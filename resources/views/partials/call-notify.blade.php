{{-- Всплывающее уведомление о входящем видеозвонке. Подключается на всех страницах для авторизованных. --}}
@auth
@if(auth()->user()->setting('call_notify'))
    <style>
        .call-toast{
            position:fixed; right:20px; bottom:20px; z-index:9999;
            padding-right:26px;
            display:none; align-items:center; gap:14px;
            width:min(360px, calc(100vw - 40px));
            padding:16px 18px; border-radius:16px;
            background:#0E1120; color:#fff;
            box-shadow:0 24px 60px -20px rgba(8,12,28,.65);
            font-family:'Inter', -apple-system, Segoe UI, sans-serif;
            animation:call-in .25s ease;
        }
        .call-toast.on{display:flex;}
        @keyframes call-in{from{opacity:0; transform:translateY(14px);} to{opacity:1; transform:none;}}

        .call-toast .ava{
            width:46px; height:46px; border-radius:14px; flex-shrink:0;
            background:#4F46E5; display:flex; align-items:center; justify-content:center;
            animation:call-pulse 1.4s ease-in-out infinite;
        }
        .call-toast .ava svg{width:22px; height:22px; stroke:#fff;}
        @keyframes call-pulse{0%,100%{transform:scale(1);} 50%{transform:scale(.9);}}
        @media (prefers-reduced-motion: reduce){
            .call-toast, .call-toast .ava{animation:none;}
        }

        .call-toast .txt{flex:1; min-width:0;}
        .call-toast .txt b{display:block; font-size:14.5px; font-weight:700;}
        .call-toast .txt span{
            display:block; font-size:12.5px; color:rgba(255,255,255,.7); margin-top:2px;
            overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
        }
        .call-toast .acts{display:flex; flex-direction:column; gap:6px; flex-shrink:0;}
        .call-toast .join{
            padding:8px 14px; border-radius:10px; background:#22C55E; color:#fff;
            font-size:13px; font-weight:700; white-space:nowrap; text-decoration:none; text-align:center;
        }
        .call-toast .skip, .call-toast .decline{
            padding:6px 14px; border-radius:10px; border:1px solid rgba(255,255,255,.25);
            background:none; color:rgba(255,255,255,.8);
            font-family:inherit; font-size:12.5px; font-weight:600; cursor:pointer;
        }
        .call-toast .skip:hover{color:#fff; border-color:rgba(255,255,255,.5);}
        .call-toast .decline{background:#EF4444; border-color:#EF4444; color:#fff;}
        .call-toast .decline:hover{background:#DC2626; border-color:#DC2626;}
        .call-toast.missed .ava{background:#EF4444; animation:none;}
        .call-toast .open{
            padding:8px 14px; border-radius:10px; background:#4F46E5; color:#fff;
            font-size:13px; font-weight:700; white-space:nowrap; text-decoration:none; text-align:center;
        }
        .call-toast .sound{
            position:absolute; top:8px; right:10px;
            border:none; background:none; cursor:pointer; padding:2px;
            font-size:13px; line-height:1; opacity:.65;
        }
        .call-toast .sound:hover{opacity:1;}
    </style>

    <div class="call-toast" id="callToast" role="alert" aria-live="assertive">
        <span class="ava">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"/><rect x="2" y="6" width="14" height="12" rx="2"/></svg>
        </span>
        <span class="txt">
            <b>{{ __('Входящий видеозвонок') }}</b>
            <span id="callFrom"></span>
        </span>
        <button type="button" class="sound" id="callSound" title="{{ __('Звук уведомления') }}">🔊</button>
        <span class="acts">
            <a class="open" id="callOpen" href="#" style="display:none;">{{ __('Перейти в чат') }}</a>
            <a class="join" id="callJoin" href="#">{{ __('Ответить') }}</a>
            <button type="button" class="decline" id="callDecline">{{ __('Отклонить') }}</button>
            <button type="button" class="skip" id="callSkip">{{ __('Позже') }}</button>
        </span>
    </div>

    <script>
        (function () {
            const toast = document.getElementById('callToast');
            const from = document.getElementById('callFrom');
            const join = document.getElementById('callJoin');
            const skip = document.getElementById('callSkip');
            const declineBtn = document.getElementById('callDecline');
            const openBtn = document.getElementById('callOpen');
            const heading = toast?.querySelector('.txt b');
            const url = @json(route('public.calls.incoming'));
            const declineUrl = @json(url('/calls'));
            const token = @json(csrf_token());

            if (!toast) { return; }

            const soundBtn = document.getElementById('callSound');

            const title = document.title;
            let blinkTimer = null;
            let shownId = null;
            let missedId = null;

            /* ===== звук входящего звонка ===== */
            let audioCtx = null;
            let ringTimer = null;
            let muted = false;

            // по умолчанию берём настройку профиля, локальный выбор её перекрывает
            muted = ! @json(auth()->user()->setting('call_sound'));

            try {
                const local = localStorage.getItem('call-sound');
                if (local) { muted = local === 'off'; }
            } catch (e) {}

            function paintSound() {
                if (soundBtn) { soundBtn.textContent = muted ? '🔇' : '🔊'; }
            }
            paintSound();

            soundBtn?.addEventListener('click', function () {
                muted = !muted;
                try { localStorage.setItem('call-sound', muted ? 'off' : 'on'); } catch (e) {}
                paintSound();

                if (muted) {
                    stopRinging();
                } else if (shownId) {
                    startRinging();
                }
            });

            // браузер разрешает звук только после действия пользователя
            function unlockAudio() {
                try {
                    const Ctx = window.AudioContext || window.webkitAudioContext;
                    if (!Ctx) { return; }
                    if (!audioCtx) { audioCtx = new Ctx(); }
                    if (audioCtx.state === 'suspended') { audioCtx.resume(); }
                } catch (e) {}
            }

            ['click', 'keydown', 'touchstart'].forEach(function (evt) {
                document.addEventListener(evt, unlockAudio, {once: false, passive: true});
            });

            // два коротких тона, как у обычного звонка
            function beep() {
                if (muted || !audioCtx || audioCtx.state !== 'running') { return; }

                [0, 0.28].forEach(function (offset) {
                    const osc = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();
                    const start = audioCtx.currentTime + offset;

                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(offset ? 660 : 880, start);

                    gain.gain.setValueAtTime(0.0001, start);
                    gain.gain.exponentialRampToValueAtTime(0.22, start + 0.03);
                    gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.24);

                    osc.connect(gain).connect(audioCtx.destination);
                    osc.start(start);
                    osc.stop(start + 0.26);
                });
            }

            function startRinging() {
                if (muted || ringTimer) { return; }
                unlockAudio();
                beep();
                ringTimer = setInterval(beep, 2400);
            }

            function stopRinging() {
                clearInterval(ringTimer);
                ringTimer = null;
            }

            function dismissed(id) {
                try {
                    return sessionStorage.getItem('call-skip-' + id) === '1';
                } catch (e) {
                    return false;
                }
            }

            function hide() {
                toast.classList.remove('on');
                toast.classList.remove('missed');
                stopRinging();
                shownId = null;
                clearInterval(blinkTimer);
                blinkTimer = null;
                document.title = title;
            }

            join.addEventListener('click', stopRinging);
            openBtn?.addEventListener('click', markMissedSeen);

            // отклонение: комната закрывается у обеих сторон
            declineBtn?.addEventListener('click', function () {
                if (!shownId) { return; }

                const id = shownId;
                hide();

                fetch(declineUrl + '/' + id + '/decline', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).catch(() => {});
            });

            skip.addEventListener('click', function () {
                if (missedId) {
                    markMissedSeen();
                } else if (shownId) {
                    try { sessionStorage.setItem('call-skip-' + shownId, '1'); } catch (e) {}
                }
                hide();
            });

            // пропущенный звонок: без звонка и без мигания вкладки
            function showMissed(missed) {
                if (shownId === 'missed-' + missed.id || missedSeen(missed.id)) { return; }

                shownId = 'missed-' + missed.id;
                missedId = missed.id;
                toast.classList.add('missed');
                heading.textContent = @json(__('Пропущенный звонок'));
                from.textContent = missed.caller + ' · ' + missed.time;
                openBtn.href = missed.chatUrl;
                openBtn.style.display = '';
                join.style.display = 'none';
                declineBtn.style.display = 'none';
                toast.classList.add('on');
            }

            function missedSeen(id) {
                try {
                    return Number(localStorage.getItem('call-missed-seen') || 0) >= id;
                } catch (e) {
                    return false;
                }
            }

            function markMissedSeen() {
                if (!missedId) { return; }
                try { localStorage.setItem('call-missed-seen', String(missedId)); } catch (e) {}
                missedId = null;
            }

            function show(call) {
                if (shownId === call.id) { return; }

                toast.classList.remove('missed');
                heading.textContent = @json(__('Входящий видеозвонок'));
                openBtn.style.display = 'none';
                join.style.display = '';
                declineBtn.style.display = '';
                shownId = call.id;
                from.textContent = call.caller;
                join.href = call.url;
                toast.classList.add('on');
                startRinging();

                // мигаем заголовком вкладки, если пользователь смотрит в другое окно
                clearInterval(blinkTimer);
                let on = false;
                blinkTimer = setInterval(function () {
                    on = !on;
                    document.title = on ? '📞 Входящий звонок' : title;
                }, 1200);
            }

            function check() {
                // на самой странице звонка уведомление не нужно
                if (location.pathname.startsWith('/interviews/')) { return; }

                fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                    .then(r => r.ok ? r.json() : null)
                    .then(function (data) {
                        const call = data && data.call;

                        if (call && !dismissed(call.id)) {
                            show(call);
                            return;
                        }

                        if (data && data.missed) {
                            showMissed(data.missed);
                            return;
                        }

                        if (shownId) { hide(); }
                    })
                    .catch(() => {});
            }

            check();
            setInterval(check, 10000);
        })();
    </script>
@endif
@endauth
