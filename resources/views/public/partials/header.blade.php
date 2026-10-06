{{--
    Общая шапка публичной части: подключается и в layouts/app, и на главной.

    Навигация: основные пункты в строке, остальные — в «Ещё ▾».
    Раньше все пункты стояли в одной строке, навигации разрешалось сжиматься,
    а ссылкам — обрезаться многоточием: у вошедшего пользователя («Отклики»,
    «Собеседования», «Чат») выходило «Вакан…», «Комп…», «Ч…». Теперь ссылки
    не обрезаются никогда:
      — основные пункты (Вакансии, Компании, Соискатели, Срочные заказы)
        стоят в строке, остальное — в выпадающем «Ещё»;
      — если основные всё же не помещаются (другой язык, узкое окно),
        скрипт переносит последние из них в «Ещё», пока строка не поместится;
      — не помещается и «Ещё» — навигация целиком уходит в бургер ☰.
    Так решение не зависит от длины слов конкретного языка.

    Ширины: от 1280px — всё в строке; 1100–1279 — тема переезжает в «Ещё»;
    до 1100 — навигация в бургере, кнопки справа остаются; до 768 — в строке
    только логотип и бургер.
--}}
@php
    $user = auth()->user();

    // пункты навигации: key, маршрут, шаблон активности, подпись, основной ли
    $navItems = collect([
        ['vacancies', 'public.vacancies.index', ['public.vacancies.*'], __('Вакансии'), true],
        ['companies', 'public.companies.index', ['public.companies.*'], __('Компании'), true],
        ['resumes', 'public.resumes.index', ['public.resumes.*'], __('Соискатели'), true],
        ['orders', 'public.orders.index', ['public.orders.index', 'public.orders.show'], '🔥 '.__('Срочные заказы'), true],
        ['masters', 'public.masters.index', ['public.masters.*'], __('Мастера'), false],
        ['stats', 'public.stats', ['public.stats', 'public.stats.*'], __('Статистика'), false],
        ['order-create', 'public.orders.create', ['public.orders.create'], '🛠 '.__('Нужен мастер'), false],
    ])->when($user, fn ($items) => $items->concat([
        ['my-orders', 'public.orders.mine', ['public.orders.mine'], __('Мои заявки на мастера'), false],
        ['responses', 'public.responses.index', ['public.responses.*'], __('Отклики'), false],
        ['interviews', 'public.interviews.index', ['public.interviews.*'], __('Собеседования'), false],
        ['chats', 'public.chats.index', ['public.chats.*'], __('Чат'), false],
    ]))->map(fn ($item) => [
        'key' => $item[0],
        'url' => route($item[1]),
        'active' => request()->routeIs(...$item[2]),
        'label' => $item[3],
        'primary' => $item[4],
    ]);

    $primary = $navItems->where('primary', true);
    $secondary = $navItems->where('primary', false);
    $moreActive = $secondary->contains('active', true);
@endphp

<style>
    /* ===============================================================
       Свой широкий контейнер: общий .container ограничен 1180px.
       =============================================================== */
    header.site > .container.site-row{
        max-width:1600px !important;
        width:100% !important;
        margin:0 auto !important;
        padding:0 28px !important;
        display:flex !important;
        align-items:center !important;
        justify-content:space-between !important;
        flex-wrap:nowrap !important;
        gap:20px !important;
        height:68px; min-height:68px;
        overflow:visible;
    }

    /* логотип — не сжимается */
    header.site .brand{flex:0 0 auto !important; margin:0 !important; min-width:0;}
    header.site .brand .hdr-tagline{white-space:normal; overflow-wrap:break-word; max-width:22ch;}

    /* ===== навигация ===== */
    /* nav сжимается, список внутри — нет: что не влезло, скрипт уносит в «Ещё» */
    .hdr-nav{display:flex; align-items:center; flex:1 1 auto; min-width:0;}
    .hdr-primary{
        display:flex; align-items:center; flex:0 0 auto; flex-wrap:nowrap;
        gap:clamp(2px, .4vw, 8px); list-style:none; margin:0; padding:0;
    }
    .hdr-primary > li{flex:0 0 auto; position:relative;}
    .hdr-primary > li[hidden]{display:none;}
    .hdr-link{
        display:inline-flex; align-items:center; gap:6px; height:38px; padding:0 11px;
        border:0; border-radius:10px; background:none; cursor:pointer;
        font-family:inherit; font-size:14.5px; font-weight:500; line-height:1;
        color:var(--text-muted); text-decoration:none; white-space:nowrap;
        transition:color .15s ease, background .15s ease;
    }
    .hdr-link:hover{color:var(--text); background:var(--surface-alt);}
    .hdr-link.on{color:var(--accent-text); background:var(--accent-soft); font-weight:600;}
    .hdr-link svg{width:12px; height:12px; opacity:.7; transition:transform .15s ease;}
    .hdr-more-btn[aria-expanded="true"]{color:var(--text); background:var(--surface-alt);}
    .hdr-more-btn[aria-expanded="true"] svg{transform:rotate(180deg);}

    /* выпадающее «Ещё» */
    .hdr-more-menu{
        position:absolute; left:0; top:calc(100% + 8px); z-index:70;
        min-width:230px; max-width:min(320px, 90vw); padding:6px;
        display:flex; flex-direction:column; gap:2px;
        background:var(--surface); border:1px solid var(--border);
        border-radius:14px; box-shadow:var(--shadow);
    }
    .hdr-more-menu[hidden]{display:none;}
    .hdr-more-item{
        display:flex; align-items:center; padding:10px 12px; border-radius:9px;
        font-size:14px; font-weight:600; color:var(--text-muted); text-decoration:none;
        white-space:normal; overflow-wrap:anywhere;
    }
    .hdr-more-item[hidden]{display:none;}
    .hdr-more-item:hover, .hdr-more-item:focus-visible{background:var(--surface-alt); color:var(--text);}
    .hdr-more-item.on{background:var(--accent-soft); color:var(--accent-text);}
    .hdr-more-sep{height:1px; margin:4px 6px; background:var(--border);}
    /* переключатель темы внутри «Ещё» — только когда в строке для него нет места */
    .hdr-more-theme{display:none; align-items:center; justify-content:space-between; gap:10px; padding:6px 6px 4px 12px; font-size:13px; color:var(--text-muted);}

    /* видимый фокус с клавиатуры — для всех элементов шапки */
    header.site a:focus-visible,
    header.site button:focus-visible,
    header.site summary:focus-visible{outline:2px solid var(--accent); outline-offset:2px;}

    /* ===== правая группа ===== */
    header.site .header-actions{
        display:flex !important; align-items:center;
        flex:0 0 auto !important; flex-wrap:nowrap;
        gap:12px !important;
    }

    .hdr-divider{
        width:1px; height:26px; flex:0 0 auto; margin:0 6px;
        background:color-mix(in srgb, var(--text) 16%, transparent);
    }

    /* переключатель темы */
    header.site .theme-toggle{
        display:flex; align-items:center; gap:2px;
        height:36px; padding:3px; flex:0 0 auto;
        background:var(--surface-alt); border:1px solid var(--border); border-radius:10px;
    }
    header.site .theme-btn{
        display:inline-flex; align-items:center; justify-content:center;
        height:28px; min-width:32px; padding:0 9px;
        border:none; background:none; border-radius:7px;
        font-family:inherit; font-size:12.5px; font-weight:600; line-height:1;
        color:var(--text-muted); cursor:pointer; transition:.15s ease;
    }
    header.site .theme-btn:hover{color:var(--text);}
    header.site .theme-btn.active{background:var(--surface); color:var(--text); box-shadow:var(--shadow);}

    /* компактный выбор языка */
    .lang-pick{position:relative; flex:0 0 auto;}
    .lang-pick > summary{
        list-style:none; cursor:pointer;
        display:inline-flex; align-items:center; gap:6px;
        height:36px; padding:0 12px; border-radius:10px;
        background:var(--surface-alt); border:1px solid var(--border);
        font-size:12.5px; font-weight:700; color:var(--text);
    }
    .lang-pick > summary::-webkit-details-marker{display:none;}
    .lang-pick > summary svg{width:12px; height:12px; opacity:.6;}
    .lang-pick[open] > summary{border-color:var(--accent); color:var(--accent);}
    .lang-menu{
        position:absolute; right:0; top:calc(100% + 8px); z-index:60;
        min-width:132px; padding:6px;
        background:var(--surface); border:1px solid var(--border);
        border-radius:12px; box-shadow:var(--shadow);
        display:flex; flex-direction:column; gap:2px;
    }
    .lang-menu a{
        padding:8px 10px; border-radius:8px; font-size:13.5px; font-weight:600;
        color:var(--text-muted); white-space:nowrap;
    }
    .lang-menu a:hover{background:var(--surface-alt); color:var(--text);}
    .lang-menu a.active{background:var(--accent-soft); color:var(--accent-text);}

    header.site .btn-ghost,
    header.site .btn-primary{
        display:inline-flex; align-items:center; justify-content:center;
        height:36px; padding:0 15px; border-radius:10px; flex:0 0 auto;
        font-size:14px; font-weight:600; white-space:nowrap;
    }

    /* ===== бургер и панель ===== */
    .hdr-burger{
        display:none; align-items:center; justify-content:center;
        width:40px; height:40px; flex:0 0 auto;
        background:var(--surface-alt); border:1px solid var(--border); border-radius:10px;
        color:var(--text); cursor:pointer;
    }
    .hdr-burger svg{width:18px; height:18px;}

    .hdr-mobile{display:none; border-top:1px solid var(--border); background:var(--bg); padding:14px 0 20px;}
    .hdr-mobile.open{display:block;}
    .hdr-mobile nav{display:flex; flex-direction:column;}
    .hdr-mobile nav a{
        padding:12px 4px; font-size:15px; font-weight:600; color:var(--text-muted);
        border-bottom:1px solid var(--border); overflow-wrap:anywhere;
    }
    .hdr-mobile nav a:last-of-type{border-bottom:none;}
    .hdr-mobile nav a:hover{color:var(--accent);}
    .hdr-mobile nav a.on{color:var(--accent-text);}
    .hdr-mobile .toggles{display:flex; gap:12px; margin-top:16px; flex-wrap:wrap;}
    .hdr-m-actions{display:none;}

    /* ===== адаптивность ===== */
    /* подпись под логотипом занимает ~150px — убираем, когда место дорого */
    @media (max-width:1500px){
        header.site .brand .hdr-tagline{display:none !important;}
    }
    @media (max-width:1400px){
        .hdr-link{font-size:14px; padding:0 9px;}
        header.site .btn-ghost, header.site .btn-primary{padding:0 13px; font-size:13.5px;}
    }
    /* 1100–1279: тема — в «Ещё», в строке остаются навигация, язык и кнопки */
    @media (max-width:1279px){
        header.site .header-actions > .theme-toggle,
        header.site .hdr-divider{display:none !important;}
        .hdr-more-theme{display:flex;}
    }
    /* до 1100 — навигация в бургере */
    @media (max-width:1099px){
        .hdr-nav{display:none !important;}
        .hdr-burger{display:inline-flex !important;}
        header.site .header-actions{margin-left:auto;}
    }
    /* скрипт сам переводит шапку в этот режим, если навигация не помещается вовсе */
    header.site.hdr-compact .hdr-nav{display:none !important;}
    header.site.hdr-compact .hdr-burger{display:inline-flex !important;}
    header.site.hdr-compact .header-actions{margin-left:auto;}

    /* до 768 — в строке только логотип и бургер, остальное в панели */
    @media (max-width:767px){
        header.site > .container.site-row{padding:0 16px !important; gap:12px !important;}
        header.site .header-actions > .btn-ghost,
        header.site .header-actions > .btn-primary,
        header.site .header-actions > .lang-pick{display:none !important;}
        .hdr-m-actions{display:flex; gap:10px; margin-top:14px; flex-wrap:wrap;}
        .hdr-m-actions a{
            flex:1 1 140px; display:inline-flex; align-items:center; justify-content:center;
            min-height:44px; padding:8px 14px; border-radius:12px; font-size:14.5px; font-weight:700; text-align:center;
            border:1px solid var(--border); color:var(--text); background:var(--surface);
        }
        .hdr-m-actions a.primary{background:var(--accent); border-color:var(--accent); color:#fff;}
    }
    @media (prefers-reduced-motion: reduce){
        .hdr-link, .hdr-link svg{transition:none;}
    }
</style>

<header class="site" id="siteHeader">
    <div class="container site-row" id="hdrRow">
        <div class="brand">
            <a href="{{ route('public.home') }}" class="workio-logo"
               style="display:flex; align-items:center; gap:10px; text-decoration:none;">
               <img src="{{asset('assets/img/work1.jfif')}}" height="50" width="50" style="border-radius: 12px;" alt="Workio">
                <div style="line-height:1.15;">
                    <div style="font-family: 'Manrope', 'Segoe UI', system-ui, sans-serif; font-size: 21px; font-weight: 700; color:var(--text); white-space:nowrap;">
                        Work<span style="color:var(--accent-text);">io</span>
                    </div>
                    <div class="hdr-tagline" style="font-family: 'Inter', 'Segoe UI', system-ui, sans-serif; font-size: 11.5px; color:var(--text-muted);">
                        {{ __('работа без лишних шагов') }}
                    </div>
                </div>
            </a>
        </div>

        {{-- навигация: основные пункты + «Ещё» --}}
        <nav class="hdr-nav" id="hdrNav" aria-label="{{ __('Основное меню') }}">
            <ul class="hdr-primary" id="hdrPrimary">
                @foreach($primary as $item)
                    <li data-key="{{ $item['key'] }}">
                        <a href="{{ $item['url'] }}" @class(['hdr-link', 'on' => $item['active']])
                           @if($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
                    </li>
                @endforeach

                <li class="hdr-more-wrap" id="hdrMoreWrap">
                    <button type="button" @class(['hdr-link', 'hdr-more-btn', 'on' => $moreActive]) id="hdrMoreBtn"
                            aria-haspopup="true" aria-expanded="false" aria-controls="hdrMoreMenu"
                            aria-label="{{ __('Ещё разделы') }}">
                        {{ __('Ещё') }}
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div class="hdr-more-menu" id="hdrMoreMenu" hidden>
                        {{-- копии основных пунктов: показываются, только если им не хватило места в строке --}}
                        @foreach($primary as $item)
                            <a href="{{ $item['url'] }}" data-dup="{{ $item['key'] }}" hidden
                               @class(['hdr-more-item', 'on' => $item['active']])
                               @if($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
                        @endforeach
                        @foreach($secondary as $item)
                            <a href="{{ $item['url'] }}" @class(['hdr-more-item', 'on' => $item['active']])
                               @if($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
                        @endforeach
                        <div class="hdr-more-theme">
                            <span>{{ __('Тема') }}</span>
                            <div class="theme-toggle">
                                <button type="button" class="theme-btn" onclick="setTheme('light')" title="{{ __('Светлая тема') }}" aria-label="{{ __('Светлая тема') }}">☀</button>
                                <button type="button" class="theme-btn" onclick="setTheme('dark')" title="{{ __('Тёмная тема') }}" aria-label="{{ __('Тёмная тема') }}">☾</button>
                            </div>
                        </div>
                    </div>
                </li>
            </ul>
        </nav>

        {{-- переключатели и действия --}}
        <div class="header-actions">
            <span class="hdr-divider" aria-hidden="true"></span>

            <details class="lang-pick">
                <summary aria-label="{{ __('Язык') }}">
                    {{ ['ru' => 'RU', 'en' => 'EN', 'tg' => 'TJ'][app()->getLocale()] ?? 'RU' }}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </summary>
                <div class="lang-menu">
                    @foreach(['ru' => 'Русский', 'en' => 'English', 'tg' => 'Тоҷикӣ'] as $code => $label)
                        <a class="{{ app()->getLocale() === $code ? 'active' : '' }}"
                           href="{{ route('locale.switch', $code) }}">{{ $label }}</a>
                    @endforeach
                </div>
            </details>

            <div class="theme-toggle">
                <button type="button" class="theme-btn active" id="lightBtn" onclick="setTheme('light')" title="{{ __('Светлая тема') }}" aria-label="{{ __('Светлая тема') }}">☀</button>
                <button type="button" class="theme-btn" id="darkBtn" onclick="setTheme('dark')" title="{{ __('Тёмная тема') }}" aria-label="{{ __('Тёмная тема') }}">☾</button>
            </div>

            <span class="hdr-divider" aria-hidden="true"></span>

            @guest
                <a href="{{ route('login') }}" class="btn-ghost">{{ __('Войти') }}</a>
                <a href="{{ route('register') }}" class="btn-primary">{{ __('Зарегистрироваться') }}</a>
            @endguest

            @auth
                @if($user->role === 'employer')
                    <a href="{{ route('employer.dashboard') }}" class="btn-ghost">{{ __('Кабинет') }}</a>
                    <a href="{{ route('employer.vacancies.create') }}" class="btn-primary">{{ __('Разместить вакансию') }}</a>
                @elseif($user->role === 'applicant')
                    <a href="{{ route('applicant.dashboard') }}" class="btn-ghost">{{ __('Кабинет') }}</a>
                    <a href="{{ route('applicant.resume.create') }}" class="btn-primary">{{ __('Создать резюме') }}</a>
                @else
                    <a href="{{ route('superadmin.dashboard') }}" class="btn-primary">{{ __('Админ-панель') }}</a>
                @endif
            @endauth

            <button type="button" class="hdr-burger" id="hdrBurger"
                    aria-label="{{ __('Открыть меню') }}" aria-expanded="false" aria-controls="hdrMobile">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- панель бургера: все пункты полностью --}}
    <div class="hdr-mobile" id="hdrMobile">
        <div class="container">
            <nav aria-label="{{ __('Меню') }}">
                @foreach($navItems as $item)
                    <a href="{{ $item['url'] }}" @class(['on' => $item['active']])
                       @if($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
                @endforeach
            </nav>

            {{-- кнопки шапки — на телефоне здесь, иначе шапка шире экрана --}}
            <div class="hdr-m-actions">
                @guest
                    <a href="{{ route('login') }}">{{ __('Войти') }}</a>
                    <a href="{{ route('register') }}" class="primary">{{ __('Зарегистрироваться') }}</a>
                @endguest
                @auth
                    @if($user->role === 'employer')
                        <a href="{{ route('employer.dashboard') }}">{{ __('Кабинет') }}</a>
                        <a href="{{ route('employer.vacancies.create') }}" class="primary">{{ __('Разместить вакансию') }}</a>
                    @elseif($user->role === 'applicant')
                        <a href="{{ route('applicant.dashboard') }}">{{ __('Кабинет') }}</a>
                        <a href="{{ route('applicant.resume.create') }}" class="primary">{{ __('Создать резюме') }}</a>
                    @else
                        <a href="{{ route('superadmin.dashboard') }}" class="primary">{{ __('Админ-панель') }}</a>
                    @endif
                @endauth
            </div>

            <div class="toggles">
                <div class="theme-toggle">
                    @foreach(['ru' => 'RU', 'en' => 'EN', 'tg' => 'TJ'] as $code => $label)
                        <a class="theme-btn {{ app()->getLocale() === $code ? 'active' : '' }}"
                           href="{{ route('locale.switch', $code) }}">{{ $label }}</a>
                    @endforeach
                </div>
                <div class="theme-toggle">
                    <button type="button" class="theme-btn active" onclick="setTheme('light')" aria-label="{{ __('Светлая тема') }}">☀</button>
                    <button type="button" class="theme-btn" onclick="setTheme('dark')" aria-label="{{ __('Тёмная тема') }}">☾</button>
                </div>
            </div>
        </div>
    </div>
</header>

@include('partials.call-notify')
{{-- новое уведомление — со звуком и всплывающей карточкой --}}
@include('partials.notification-alert')

<script>
    (function () {
        const header = document.getElementById('siteHeader');
        const nav = document.getElementById('hdrNav');
        const list = document.getElementById('hdrPrimary');
        const moreWrap = document.getElementById('hdrMoreWrap');
        const moreBtn = document.getElementById('hdrMoreBtn');
        const menu = document.getElementById('hdrMoreMenu');
        const burger = document.getElementById('hdrBurger');
        const panel = document.getElementById('hdrMobile');
        const T = {open: @json(__('Открыть меню')), close: @json(__('Закрыть меню'))};

        // ---------- «Ещё»: открыть, закрыть, клавиатура ----------
        const items = () => Array.from(menu.querySelectorAll('.hdr-more-item:not([hidden])'));

        function setMore(open, focusFirst) {
            menu.hidden = !open;
            moreBtn.setAttribute('aria-expanded', String(open));
            if (open && focusFirst) { (items()[0] || moreBtn).focus(); }
        }

        moreBtn.addEventListener('click', () => setMore(menu.hidden, false));
        moreBtn.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || ((e.key === 'Enter' || e.key === ' ') && menu.hidden)) {
                e.preventDefault();
                setMore(true, true);
            }
        });
        menu.addEventListener('keydown', function (e) {
            const list = items();
            const i = list.indexOf(document.activeElement);
            if (e.key === 'ArrowDown') { e.preventDefault(); list[(i + 1) % list.length]?.focus(); }
            if (e.key === 'ArrowUp') { e.preventDefault(); list[(i - 1 + list.length) % list.length]?.focus(); }
            if (e.key === 'Home') { e.preventDefault(); list[0]?.focus(); }
            if (e.key === 'End') { e.preventDefault(); list[list.length - 1]?.focus(); }
        });
        // фокус ушёл из меню (Tab) — закрываем
        moreWrap.addEventListener('focusout', function () {
            setTimeout(function () { if (!moreWrap.contains(document.activeElement)) { setMore(false); } }, 0);
        });

        // ---------- бургер ----------
        function setPanel(open) {
            panel.classList.toggle('open', open);
            burger.setAttribute('aria-expanded', String(open));
            burger.setAttribute('aria-label', open ? T.close : T.open);
        }
        burger.addEventListener('click', () => setPanel(!panel.classList.contains('open')));

        // клик мимо и Esc закрывают меню, список языков и панель
        document.addEventListener('click', function (e) {
            if (!moreWrap.contains(e.target)) { setMore(false); }
            document.querySelectorAll('details.lang-pick[open]').forEach(function (el) {
                if (!el.contains(e.target)) { el.removeAttribute('open'); }
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') { return; }
            if (!menu.hidden) { setMore(false); moreBtn.focus(); }
            if (panel.classList.contains('open')) { setPanel(false); burger.focus(); }
            document.querySelectorAll('details.lang-pick[open]').forEach(el => el.removeAttribute('open'));
        });

        // ---------- уместить строку без обрезаний ----------
        /*
         * Основные пункты переносятся в «Ещё» с конца, пока список не
         * поместится. Не помещается и «Ещё» — шапка переходит в режим бургера.
         * Ширина слов не важна: меряем то, что реально нарисовано.
         */
        const primary = Array.from(list.querySelectorAll(':scope > li[data-key]'));

        function overflowing() {
            return list.getBoundingClientRect().width > nav.getBoundingClientRect().width + 0.5;
        }

        function fit() {
            header.classList.remove('hdr-compact');
            primary.forEach(function (li) {
                li.hidden = false;
                menu.querySelector('[data-dup="' + li.dataset.key + '"]').hidden = true;
            });

            if (getComputedStyle(nav).display === 'none') { return syncMore(); }

            for (let i = primary.length - 1; i >= 0 && overflowing(); i--) {
                primary[i].hidden = true;
                menu.querySelector('[data-dup="' + primary[i].dataset.key + '"]').hidden = false;
            }

            if (overflowing()) { header.classList.add('hdr-compact'); }

            syncMore();
        }

        // «Ещё» подсвечен, если внутри — текущая страница
        function syncMore() {
            moreBtn.classList.toggle('on', items().some(a => a.classList.contains('on')));
        }

        fit();
        if ('ResizeObserver' in window) {
            new ResizeObserver(() => fit()).observe(document.getElementById('hdrRow'));
        } else {
            window.addEventListener('resize', fit);
        }
        // шрифты догружаются позже — ширины меняются
        if (document.fonts && document.fonts.ready) { document.fonts.ready.then(fit); }
    })();
</script>
