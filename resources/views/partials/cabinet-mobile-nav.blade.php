{{--
    Меню кабинета на телефоне.

    Раньше на экранах уже 900px боковое меню просто пряталось (display:none),
    и с телефона из кабинета нельзя было перейти никуда, кроме ссылок внутри
    страницы. Теперь там сверху полоса «☰ Workio · раздел», а по нажатию слева
    выезжает то же самое боковое меню — со всеми пунктами и счётчиками.

    Подключается из бокового меню кабинетов (applicant и employer), поэтому
    работает на каждой странице, где оно есть. Стоит внутри .app рядом с
    .sidebar: на узком экране сетка .app становится одной колонкой, и полоса
    оказывается первой строкой — над содержимым страницы.
--}}
<div class="cab-mbar" id="cabMbar">
    <button type="button" class="cab-burger" id="cabBurger"
            aria-controls="cabinetSidebar" aria-expanded="false" aria-label="{{ __('Открыть меню') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
            <line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/>
        </svg>
        <span class="cab-burger-dot" id="cabBurgerDot" hidden></span>
    </button>
    <a class="cab-mbrand" href="{{ route('public.home') }}">Work<span>io</span></a>
    <span class="cab-mtitle" id="cabMtitle"></span>
</div>
<div class="cab-backdrop" id="cabBackdrop" hidden></div>

<style>
    .cab-mbar, .cab-backdrop { display: none; }

    @media (max-width: 900px) {
        .cab-mbar {
            display: flex; align-items: center; gap: 10px;
            position: sticky; top: 0; z-index: 1400;
            padding: 10px 14px; min-height: 56px;
            background: var(--white); color: var(--ink);
            border-bottom: 1px solid var(--gray-200);
        }
        .cab-burger {
            position: relative; flex: none; width: 42px; height: 42px; border-radius: 12px;
            display: grid; place-items: center; cursor: pointer;
            border: 1px solid var(--gray-200); background: var(--gray-100); color: var(--ink);
        }
        .cab-burger svg { width: 22px; height: 22px; }
        .cab-burger-dot {
            position: absolute; top: 7px; right: 7px; width: 9px; height: 9px; border-radius: 50%;
            background: #EF4444; box-shadow: 0 0 0 2px var(--gray-100);
        }
        .cab-mbrand { font-weight: 800; font-size: 17px; color: var(--ink); text-decoration: none; }
        .cab-mbrand span { color: var(--indigo-600); }
        .cab-mtitle {
            flex: 1; min-width: 0; font-size: 14px; font-weight: 600; color: var(--gray-500);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .cab-mtitle:not(:empty)::before { content: '·'; margin-right: 8px; }

        /* боковое меню — выезжающая панель вместо display:none */
        .app .sidebar {
            display: flex !important;
            position: fixed; top: 0; left: 0; bottom: 0; z-index: 1600;
            width: min(300px, 86vw); height: 100%;
            transform: translateX(-105%);
            transition: transform .25s ease;
            box-shadow: 0 20px 50px rgba(15, 23, 42, .35);
        }
        .app.cab-open .sidebar { transform: none; }
        /* сворачивание до иконок на телефоне не нужно — там своя панель */
        .app .side-toggle { display: none; }

        .cab-backdrop {
            display: block; position: fixed; inset: 0; z-index: 1500;
            background: rgba(15, 23, 42, .45);
        }
        .cab-backdrop[hidden] { display: none; }
        body.cab-lock { overflow: hidden; }

        /* пункты меню крупнее — под палец */
        .app .sidebar .nav-item { min-height: 44px; }

        /* содержимое — с отступами телефона, а не настольного экрана */
        .main { padding: 18px 16px 56px; }
        .topbar { flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
    }
    @media (max-width: 560px) {
        .topbar .user-chip { display: none; }
    }
    @media (max-width: 900px) and (prefers-reduced-motion: reduce) {
        .app .sidebar { transition: none; }
    }
</style>

<script>
    (function () {
        // обёртка кабинета — та, внутри которой стоит само меню
        const app = document.getElementById('cabinetSidebar')?.closest('.app');
        const sidebar = document.getElementById('cabinetSidebar');
        const burger = document.getElementById('cabBurger');
        const backdrop = document.getElementById('cabBackdrop');
        const title = document.getElementById('cabMtitle');
        const dot = document.getElementById('cabBurgerDot');
        const narrow = window.matchMedia('(max-width: 900px)');

        if (!app || !sidebar || !burger) { return; }

        // название раздела — из заголовка страницы
        const heading = document.getElementById('pageTitle') || document.querySelector('.topbar h1') || document.querySelector('main h1');
        if (heading) { title.textContent = heading.textContent.trim(); }

        function setOpen(open) {
            app.classList.toggle('cab-open', open);
            backdrop.hidden = !open;
            document.body.classList.toggle('cab-lock', open);
            burger.setAttribute('aria-expanded', String(open));
            burger.setAttribute('aria-label', open ? @json(__('Закрыть меню')) : @json(__('Открыть меню')));

            if (open) {
                // пункт текущей страницы — в поле зрения
                const current = sidebar.querySelector('.nav-item.active');
                if (current) { current.scrollIntoView({block: 'nearest'}); }
            }
        }

        burger.addEventListener('click', () => setOpen(!app.classList.contains('cab-open')));
        backdrop.addEventListener('click', () => setOpen(false));
        document.addEventListener('keydown', e => { if (e.key === 'Escape') { setOpen(false); } });
        sidebar.addEventListener('click', e => { if (e.target.closest('a')) { setOpen(false); } });

        // свайп влево по панели закрывает её
        let startX = null;
        sidebar.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, {passive: true});
        sidebar.addEventListener('touchend', function (e) {
            if (startX !== null && startX - e.changedTouches[0].clientX > 60) { setOpen(false); }
            startX = null;
        });

        /*
         * Свёрнутое на компьютере меню (иконки без подписей) на телефоне
         * не годится: подписи нужны. Снимаем свёртку на узком экране и
         * возвращаем на широком — выбор человека в localStorage не трогаем.
         */
        function syncRail() {
            if (narrow.matches) {
                app.dataset.wasRail = app.classList.contains('rail') ? '1' : (app.dataset.wasRail || '');
                app.classList.remove('rail');
            } else {
                setOpen(false);
                if (app.dataset.wasRail === '1') { app.classList.add('rail'); }
            }
        }
        syncRail();
        (narrow.addEventListener ? narrow.addEventListener('change', syncRail) : narrow.addListener(syncRail));

        // точка на кнопке меню, если в разделах есть непрочитанное
        function paintDot() {
            const unread = Array.from(sidebar.querySelectorAll('[data-live]'))
                .some(el => !el.hidden && parseInt(el.textContent, 10) > 0);
            dot.hidden = !unread;
        }
        paintDot();
        new MutationObserver(paintDot).observe(sidebar, {subtree: true, childList: true, characterData: true, attributes: true, attributeFilter: ['hidden']});
    })();
</script>
