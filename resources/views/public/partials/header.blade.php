{{-- Общая шапка публичной части: подключается и в layouts/app, и на главной --}}
<style>
    /* ===============================================================
       Шапка получает собственный широкий контейнер.
       Общий .container ограничен 1180px, а содержимое шапки занимает
       ~1570px — оно не помещалось и обрезалось из-за body{overflow-x:hidden},
       поэтому кнопка «Разместить вакансию» уезжала за экран.
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

    /* навигация занимает свободное место и сжимается первой */
    header.site nav.main-nav{
        display:flex !important;
        align-items:center;
        flex:1 1 auto !important;
        min-width:0 !important;
        gap:clamp(14px, 1.5vw, 30px) !important;
        margin:0 !important;
        font-size:14.5px; font-weight:500; color:var(--text-muted);
        overflow:hidden;
    }
    header.site nav.main-nav a{
        white-space:nowrap; transition:color .15s ease;
        overflow:hidden; text-overflow:ellipsis;
    }
    header.site nav.main-nav a:hover{color:var(--text);}

    /* правая группа никогда не сжимается и не обрезается */
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

    /* компактный выбор языка: одна кнопка вместо трёх (экономит ~60px) */
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

    /* ===== бургер ===== */
    .hdr-burger{
        display:none; align-items:center; justify-content:center;
        width:36px; height:36px; flex:0 0 auto;
        background:var(--surface-alt); border:1px solid var(--border); border-radius:10px;
        color:var(--text); cursor:pointer;
    }
    .hdr-burger svg{width:18px; height:18px;}

    .hdr-mobile{display:none; border-top:1px solid var(--border); background:var(--bg); padding:14px 0 20px;}
    .hdr-mobile.open{display:block;}
    .hdr-mobile nav{display:flex; flex-direction:column;}
    .hdr-mobile nav a{
        padding:12px 4px; font-size:15px; font-weight:600; color:var(--text-muted);
        border-bottom:1px solid var(--border);
    }
    .hdr-mobile nav a:last-of-type{border-bottom:none;}
    .hdr-mobile nav a:hover{color:var(--accent);}
    .hdr-mobile .toggles{display:flex; gap:12px; margin-top:16px; flex-wrap:wrap;}

    /* подпись под логотипом занимает ~150px — убираем, когда место дорого */
    @media (max-width:1500px){
        header.site .brand .hdr-tagline{display:none !important;}
    }
    @media (max-width:1400px){
        header.site nav.main-nav{font-size:13.5px;}
        header.site .btn-ghost, header.site .btn-primary{padding:0 13px; font-size:13.5px;}
    }
    /* ниже 1280px навигация уходит в бургер — кнопки остаются на месте */
    @media (max-width:1280px){
        header.site nav.main-nav,
        header.site .header-actions > .theme-toggle,
        header.site .hdr-divider{display:none !important;}
        .hdr-burger{display:inline-flex !important;}
    }
    @media (max-width:640px){
        header.site .btn-ghost{display:none !important;}
        header.site > .container.site-row{padding:0 16px !important; gap:12px !important;}
    }
</style>

<header class="site">
    <div class="container site-row">
        <div class="brand">
            <a href="{{ route('public.home') }}" class="workio-logo"
               style="display:flex; align-items:center; gap:10px; text-decoration:none;">
               <img src="{{asset('assets/img/work1.jfif')}}" height="50" width="50" style="border-radius: 12px;">
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

        {{-- группа 1: навигация --}}
        <nav class="main-nav">
            <a href="{{ route('public.vacancies.index') }}">{{ __('Вакансии') }}</a>
            <a href="{{ route('public.companies.index') }}">{{ __('Компании') }}</a>
            <a href="{{ route('public.resumes.index') }}">{{ __('Соискатели') }}</a>
            <a href="{{ route('public.stats') }}">{{ __('Статистика') }}</a>
            @auth
                <a href="{{ route('public.responses.index') }}">{{ __('Отклики') }}</a>
                <a href="{{ route('public.interviews.index') }}">{{ __('Собеседования') }}</a>
                <a href="{{ route('public.chats.index') }}">{{ __('Чат') }}</a>
            @endauth
        </nav>

        {{-- группы 2 и 3: переключатели и действия --}}
        <div class="header-actions">
            <span class="hdr-divider" aria-hidden="true"></span>

            <details class="lang-pick">
                <summary>
                    {{ ['ru' => 'RU', 'en' => 'EN', 'tg' => 'TJ'][app()->getLocale()] ?? 'RU' }}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                </summary>
                <div class="lang-menu">
                    @foreach(['ru' => 'Русский', 'en' => 'English', 'tg' => 'Тоҷикӣ'] as $code => $label)
                        <a class="{{ app()->getLocale() === $code ? 'active' : '' }}"
                           href="{{ route('locale.switch', $code) }}">{{ $label }}</a>
                    @endforeach
                </div>
            </details>

            <div class="theme-toggle">
                <button class="theme-btn active" id="lightBtn" onclick="setTheme('light')" title="{{ __('Светлая тема') }}">☀</button>
                <button class="theme-btn" id="darkBtn" onclick="setTheme('dark')" title="{{ __('Тёмная тема') }}">☾</button>
            </div>

            <span class="hdr-divider" aria-hidden="true"></span>

            @guest
                <a href="{{ route('login') }}" class="btn-ghost">{{ __('Войти') }}</a>
                <a href="{{ route('register') }}" class="btn-primary">{{ __('Зарегистрироваться') }}</a>
            @endguest

            @auth
                @if(auth()->user()->role === 'employer')
                    <a href="{{ route('employer.dashboard') }}" class="btn-ghost">{{ __('Кабинет') }}</a>
                    <a href="{{ route('employer.vacancies.create') }}" class="btn-primary">{{ __('Разместить вакансию') }}</a>
                @elseif(auth()->user()->role === 'applicant')
                    <a href="{{ route('applicant.dashboard') }}" class="btn-ghost">{{ __('Кабинет') }}</a>
                    <a href="{{ route('applicant.resume.create') }}" class="btn-primary">{{ __('Создать резюме') }}</a>
                @else
                    <a href="{{ route('superadmin.dashboard') }}" class="btn-primary">{{ __('Админ-панель') }}</a>
                @endif
            @endauth

            <button type="button" class="hdr-burger" id="hdrBurger" aria-label="{{ __('Меню') }}" aria-expanded="false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- меню для узких экранов --}}
    <div class="hdr-mobile" id="hdrMobile">
        <div class="container">
            <nav>
                <a href="{{ route('public.vacancies.index') }}">{{ __('Вакансии') }}</a>
                <a href="{{ route('public.companies.index') }}">{{ __('Компании') }}</a>
                <a href="{{ route('public.resumes.index') }}">{{ __('Соискатели') }}</a>
                <a href="{{ route('public.stats') }}">{{ __('Статистика') }}</a>
                @auth
                    <a href="{{ route('public.responses.index') }}">{{ __('Отклики') }}</a>
                    <a href="{{ route('public.interviews.index') }}">{{ __('Собеседования') }}</a>
                    <a href="{{ route('public.chats.index') }}">{{ __('Чат') }}</a>
                @endauth
            </nav>

            <div class="toggles">
                <div class="theme-toggle">
                    @foreach(['ru' => 'RU', 'en' => 'EN', 'tg' => 'TJ'] as $code => $label)
                        <a class="theme-btn {{ app()->getLocale() === $code ? 'active' : '' }}"
                           href="{{ route('locale.switch', $code) }}">{{ $label }}</a>
                    @endforeach
                </div>
                <div class="theme-toggle">
                    <button class="theme-btn active" onclick="setTheme('light')">☀</button>
                    <button class="theme-btn" onclick="setTheme('dark')">☾</button>
                </div>
            </div>
        </div>
    </div>
</header>

@include('partials.call-notify')

<script>
    (function () {
        const burger = document.getElementById('hdrBurger');
        const panel = document.getElementById('hdrMobile');

        if (burger && panel) {
            burger.addEventListener('click', function () {
                const open = panel.classList.toggle('open');
                burger.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }

        // список языков закрывается кликом мимо
        document.addEventListener('click', function (e) {
            document.querySelectorAll('details.lang-pick[open]').forEach(function (el) {
                if (!el.contains(e.target)) { el.removeAttribute('open'); }
            });
        });
    })();
</script>
