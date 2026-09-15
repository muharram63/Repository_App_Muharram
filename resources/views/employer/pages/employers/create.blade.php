<!DOCTYPE html>
<html lang="ru" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Регистрация работодателя') }}</title>
    @include('public.partials.css')
</head>
<body>

<div class="page">
    <div class="brand">
        <a href="{{ route('public.home') }}" style="display:flex; align-items:center; gap:10px; text-decoration:none; color:inherit;">
            @include('partials.brand-mark', ['brandSize' => 38])
            <div class="brand-name">Work<span>io</span></div>
        </a>
    </div>

    <div class="card">
        <div class="card-top">
            <div class="badge-row"><span class="dot"></span>{{ __('Регистрация займёт меньше 2 минут') }}</div>
            <div class="role-pill">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                {{ __('Я работодатель') }}
            </div>
            <h1>{{ __('Заполняйте анкету') }}</h1>
            <p class="sub">{{ __('Заполните данные о компании, чтобы начать публиковать вакансии.') }}</p>
        </div>
        <form id="form-employer" action="{{route('public.employers.store')}}" method="post" enctype="multipart/form-data">
            @csrf
            <div class="grid">
                <div class="field full">
                    <label>{{ __('Фото') }} <span style="font-weight:400; opacity:.7;">{{ __('(необязательно)') }}</span></label>
                    <div style="display:flex; align-items:center; gap:16px;">
                        <div id="avatarPreviewWrap"
                             style="width:64px; height:64px; border-radius:50%; overflow:hidden; flex-shrink:0;
                                    background:#EEF1F6; color:#1656D6; display:flex; align-items:center;
                                    justify-content:center; font-weight:700; font-size:22px;">
                            <img id="avatarPreview" src="" alt="" style="width:100%; height:100%; object-fit:cover; display:none;">
                            <span id="avatarLetter">{{ mb_substr(auth()->user()->name ?? '?', 0, 1) }}</span>
                        </div>
                        <div>
                            <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/webp">
                            <div style="font-size:12px; opacity:.7; margin-top:6px;">{{ __('JPG, PNG или WEBP, до 5 МБ. Компания с логотипом заметнее в поиске соискателей') }}</div>
                        </div>
                    </div>
                </div>
                <div class="field full">
                    <label>{{ __('Название компании') }}</label>
                    <input type="text" placeholder="{{ __('ООО «Технологии Будущего»') }}" name="company_name">
                </div>
                <div class="field full">
                    <label>{{ __('Работа') }}</label>
                    <input type="text" placeholder="{{ __('Название работы') }}" name="job">
                </div>
                <div class="field">
                    <label>{{ __('Рабочий email') }}</label>
                    <input type="email" name="email_company" placeholder="email@company.tj">
                </div>
                <div class="field">
                    <label>{{ __('Рабочий Телефон') }}</label>
                    <input type="tel" name="phone" placeholder="+992 **-***-**-**">
                </div>
                <div class="field">
                    <label>{{ __('Сайт компании') }}</label>
                    <input type="text" name="website_url" placeholder="company.tj">
                </div>
                <div class="field">
                    <label>{{ __('Категория') }}</label>
                    <select name="category_id">
                        @foreach($categories as $category)
                            <option value="{{$category->id}}">{{ __($category->name) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>{{ __('Индустрия') }}</label>
                    <select name="industry_id">
                        @foreach($industries as $industry)
                            <option value="{{$industry->id}}">{{ __($industry->name) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>{{ __('Город') }}</label>
                    <select name="city_id">
                        @foreach($cities as $city)
                            <option value="{{$city->id}}">{{ __($city->country) }} , {{ __($city->region) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field full">
                    <label>{{ __('О компании') }} <span class="opt">{{ __('(обязательно)') }}</span></label>
                    <textarea name="description" placeholder="{{ __('Расскажите о компании, чтобы кандидаты могли лучше вас узнать') }}">{{old('description')}}</textarea>
                </div>


                <div class="field full">
                    <label class="checkbox-row">
                        <input type="checkbox" required>
                        <span>{{ __('Я согласен(на) с') }} <a href="#">{{ __('условиями использования') }}</a> {{ __('и подтверждаю, что представляю указанную компанию') }}</span>
                    </label>
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn-primary">{{ __('Сохранить') }}</button>
                <span class="link-muted">{{ __('Уже есть аккаунт?') }} <a href="#">{{ __('Войти') }}</a></span>
            </div>

        </form>
    </div>
</div>

</body>
</html>



{{---}}<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workio для компаний — Найдите лучшего кандидата</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root[data-theme="light"]{
            --bg:#F7F8FB;
            --surface:#FFFFFF;
            --surface-alt:#F1F3F8;
            --border:#E7E9F1;
            --text:#171B2C;
            --text-muted:#6B7184;
            --accent:#3D5AFE;
            --accent-ink:#2A3FCC;
            --accent-soft:#EDF0FF;
            --warn:#FF8A3D;
            --good:#1FAE6E;
            --shadow: 0 16px 40px -16px rgba(23,27,44,.16);
        }
        :root[data-theme="dark"]{
            --bg:#0E1120;
            --surface:#161A2C;
            --surface-alt:#1D2238;
            --border:#272C45;
            --text:#F2F3F9;
            --text-muted:#8A8FA8;
            --accent:#6E84FF;
            --accent-ink:#8C9CFF;
            --accent-soft:#202750;
            --warn:#FFA15C;
            --good:#36CC8E;
            --shadow: 0 16px 40px -16px rgba(0,0,0,.55);
        }
        *{box-sizing:border-box; margin:0; padding:0;}
        body{
            font-family:'Inter', sans-serif;
            background:var(--bg);
            color:var(--text);
            -webkit-font-smoothing:antialiased;
            transition:background .25s ease, color .25s ease;
            overflow-x:hidden;
        }
        h1,h2,h3,.logo-text,.btn-primary,.stat-num{font-family:'Manrope', sans-serif;}
        a{color:inherit; text-decoration:none;}
        .container{max-width:1180px; margin:0 auto; padding:0 32px;}

        /* ===== HEADER ===== */
        header.site{
            position:sticky; top:0; z-index:50;
            background:color-mix(in srgb, var(--bg) 88%, transparent);
            backdrop-filter:blur(10px);
            border-bottom:1px solid var(--border);
        }
        .site-row{display:flex; align-items:center; justify-content:space-between; height:74px;}
        .brand{display:flex; align-items:center; gap:10px; font-weight:800; font-size:18px;}
        .logo-mark{
            width:34px; height:34px; border-radius:9px;
            background:linear-gradient(135deg, var(--accent), var(--accent-ink));
            display:flex; align-items:center; justify-content:center;
            color:#fff; font-size:13px; font-weight:800; letter-spacing:.5px;
        }
        .brand span{color:var(--accent);}
        nav.main-nav{display:flex; gap:32px; font-size:14.5px; font-weight:500; color:var(--text-muted);}
        nav.main-nav a:hover{color:var(--text);}
        nav.main-nav a.on{color:var(--text);}
        .header-actions{display:flex; align-items:center; gap:14px;}
        .theme-toggle{
            display:flex; background:var(--surface-alt); border:1px solid var(--border);
            border-radius:10px; padding:3px;
        }
        .theme-btn{
            border:none; background:none; padding:6px 10px; font-size:12.5px; font-weight:600;
            color:var(--text-muted); border-radius:7px; cursor:pointer; transition:.2s;
        }
        .theme-btn.active{background:var(--surface); color:var(--text); box-shadow:var(--shadow);}
        .btn-ghost{
            padding:9px 16px; border-radius:9px; font-size:14px; font-weight:600;
            color:var(--text); border:1px solid var(--border); background:var(--surface);
        }
        .btn-primary{
            padding:10px 18px; border-radius:9px; font-size:14px; font-weight:700;
            color:#fff; background:var(--accent); border:none; cursor:pointer;
            box-shadow:0 10px 22px -10px var(--accent);
        }
        .btn-primary:hover{background:var(--accent-ink);}

        /* switcher: соискателям / работодателям */
        .mode-switch{
            display:inline-flex; background:var(--surface-alt); border:1px solid var(--border);
            border-radius:99px; padding:4px; margin-bottom:26px;
        }
        .mode-switch a{
            padding:9px 20px; font-size:13.5px; font-weight:700; border-radius:99px; color:var(--text-muted);
        }
        .mode-switch a.on{background:var(--surface); color:var(--text); box-shadow:var(--shadow);}

        /* ===== HERO ===== */
        .hero{padding:64px 0 60px; position:relative; overflow:hidden;}
        .hero::before{
            content:""; position:absolute; width:550px; height:550px;
            background:rgba(61,90,254,.16); border-radius:50%; filter:blur(120px);
            top:-200px; right:-160px; z-index:-1;
        }
        .hero-grid{display:grid; grid-template-columns:1.05fr .95fr; gap:56px; align-items:center;}
        .eyebrow{
            display:inline-flex; align-items:center; gap:8px;
            background:var(--accent-soft); color:var(--accent-ink);
            font-size:12.5px; font-weight:700; padding:7px 13px; border-radius:99px;
            margin-bottom:22px;
        }
        :root[data-theme="dark"] .eyebrow{color:var(--accent);}
        .eyebrow .dot{width:6px; height:6px; border-radius:50%; background:var(--good);}
        h1.hero-title{
            font-size:46px; line-height:1.1; font-weight:800; letter-spacing:-1.1px;
            margin-bottom:20px;
        }
        h1.hero-title em{font-style:normal; color:var(--accent);}
        .hero-sub{
            font-size:16.5px; color:var(--text-muted); line-height:1.65; max-width:480px; margin-bottom:30px;
        }

        /* vacancy form card */
        .form-card{
            background:var(--surface); border:1px solid var(--border); border-radius:16px;
            box-shadow:var(--shadow); padding:22px; max-width:520px;
        }
        .form-card-title{font-size:13.5px; font-weight:700; color:var(--text-muted); margin-bottom:14px; text-transform:uppercase; letter-spacing:.4px;}
        .field-row{display:flex; gap:10px; margin-bottom:10px;}
        .field{
            flex:1; display:flex; align-items:center; gap:9px; padding:11px 13px;
            background:var(--surface-alt); border:1px solid var(--border); border-radius:10px;
        }
        .field svg{width:17px; height:17px; color:var(--text-muted); flex-shrink:0;}
        .field input, .field select{
            border:none; outline:none; background:none; font-size:14px; width:100%;
            font-family:'Inter'; color:var(--text);
        }
        .form-card .btn-primary{width:100%; padding:13px; font-size:14.5px; margin-top:6px;}
        .form-hint{font-size:12px; color:var(--text-muted); text-align:center; margin-top:10px;}

        .hero-tags{display:flex; flex-wrap:wrap; gap:8px; margin-top:18px;}
        .tag-chip{
            font-size:12.5px; color:var(--text-muted); background:var(--surface-alt);
            border:1px solid var(--border); padding:6px 12px; border-radius:99px;
        }

        /* hero visual: candidate cards */
        .hero-visual{position:relative;}
        .float-card{
            background:var(--surface); border:1px solid var(--border); border-radius:16px;
            box-shadow:var(--shadow); padding:22px;
        }
        .fc-head{display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;}
        .fc-title{font-weight:700; font-size:14.5px;}
        .fc-badge{
            font-size:11px; font-weight:700; color:var(--good); background:color-mix(in srgb, var(--good) 14%, transparent);
            padding:4px 9px; border-radius:99px;
        }
        .cand-row{
            display:flex; align-items:center; gap:12px;
            padding:13px 0; border-bottom:1px solid var(--border);
        }
        .cand-row:last-child{border-bottom:none;}
        .cand-avatar{
            width:38px; height:38px; border-radius:50%; flex-shrink:0;
            background:linear-gradient(135deg, var(--accent-soft), var(--surface-alt));
            display:flex; align-items:center; justify-content:center;
            font-weight:800; font-size:13px; color:var(--accent-ink);
        }
        :root[data-theme="dark"] .cand-avatar{color:var(--accent);}
        .cand-name{font-weight:600; font-size:13.5px;}
        .cand-role{color:var(--text-muted); font-size:12px;}
        .cand-match{
            margin-left:auto; font-size:12px; font-weight:700; color:var(--good);
            background:color-mix(in srgb, var(--good) 12%, transparent); padding:4px 9px; border-radius:99px;
            flex-shrink:0;
        }
        .float-stat{
            position:absolute; bottom:-22px; left:-26px;
            background:var(--surface); border:1px solid var(--border); border-radius:14px;
            box-shadow:var(--shadow); padding:16px 20px; display:flex; gap:12px; align-items:center;
        }
        .float-stat .num{font-size:22px; font-weight:800;}
        .float-stat .lbl{font-size:11.5px; color:var(--text-muted); font-weight:600;}
        .float-icn{
            width:38px; height:38px; border-radius:10px; background:var(--accent-soft);
            display:flex; align-items:center; justify-content:center; color:var(--accent-ink); flex-shrink:0;
        }
        :root[data-theme="dark"] .float-icn{color:var(--accent);}

        /* ===== LOGO STRIP ===== */
        .logo-strip{padding:36px 0; border-top:1px solid var(--border); border-bottom:1px solid var(--border);}
        .logo-strip-row{display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:24px;}
        .logo-strip-label{font-size:12.5px; color:var(--text-muted); font-weight:600; white-space:nowrap;}
        .logo-strip-names{display:flex; gap:34px; flex-wrap:wrap;}
        .logo-strip-names span{font-weight:800; font-size:15px; color:var(--text-muted); letter-spacing:.3px;}

        /* ===== STATS ===== */
        .stats{padding:60px 0 10px;}
        .stats-grid{display:grid; grid-template-columns:repeat(4,1fr); gap:22px;}
        .stat-box{
            background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:24px;
        }
        .stat-icon{
            width:36px; height:36px; border-radius:9px; background:var(--accent-soft); color:var(--accent-ink);
            display:flex; align-items:center; justify-content:center; margin-bottom:14px; font-size:15px;
        }
        :root[data-theme="dark"] .stat-icon{color:var(--accent);}
        .stat-num{font-size:30px; font-weight:800; margin-bottom:4px;}
        .stat-lbl{font-size:13px; color:var(--text-muted); font-weight:500;}

        /* ===== SECTIONS ===== */
        .section{padding:64px 0;}
        .section-head{max-width:580px; margin-bottom:44px;}
        .section-head h2{font-size:32px; font-weight:800; letter-spacing:-.6px; margin-bottom:12px;}
        .section-head p{color:var(--text-muted); font-size:15.5px; line-height:1.6;}

        /* steps */
        .steps-grid{display:grid; grid-template-columns:repeat(4,1fr); gap:18px;}
        .step-card{
            background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:26px 22px;
            position:relative;
        }
        .step-card .step-num{
            width:30px; height:30px; border-radius:9px; background:var(--accent-soft); color:var(--accent-ink);
            display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; margin-bottom:16px;
        }
        :root[data-theme="dark"] .step-card .step-num{color:var(--accent);}
        .step-card h3{font-size:15.5px; font-weight:700; margin-bottom:8px;}
        .step-card p{font-size:13px; color:var(--text-muted); line-height:1.55;}

        /* pricing */
        .pricing-grid{display:grid; grid-template-columns:repeat(3,1fr); gap:20px;}
        .price-card{
            background:var(--surface); border:1px solid var(--border); border-radius:18px; padding:30px 26px;
        }
        .price-card.pop{border-color:var(--accent); box-shadow:0 0 0 1px var(--accent) inset;}
        .price-kicker{font-size:12px; font-weight:700; color:var(--accent-ink); text-transform:uppercase; letter-spacing:.6px; margin-bottom:8px;}
        :root[data-theme="dark"] .price-kicker{color:var(--accent);}
        .price-name{font-size:19px; font-weight:800; margin-bottom:6px;}
        .price-desc{font-size:13px; color:var(--text-muted); margin-bottom:18px; line-height:1.5;}
        .price-amount{font-size:32px; font-weight:800; margin-bottom:2px;}
        .price-amount span{font-size:13px; font-weight:600; color:var(--text-muted);}
        .price-list{list-style:none; margin:20px 0 24px; display:flex; flex-direction:column; gap:10px;}
        .price-list li{display:flex; gap:9px; align-items:flex-start; font-size:13.5px; color:var(--text);}
        .price-list li svg{width:16px; height:16px; color:var(--good); flex-shrink:0; margin-top:2px;}
        .price-card .btn-ghost, .price-card .btn-primary{width:100%; text-align:center; padding:11px;}

        /* candidate categories */
        .cat-grid{display:grid; grid-template-columns:repeat(5,1fr); gap:16px;}
        .cat-card{
            background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:20px 18px;
            transition:.2s;
        }
        .cat-card:hover{border-color:var(--accent); transform:translateY(-2px);}
        .cat-icn{
            width:36px; height:36px; border-radius:9px; background:var(--accent-soft); color:var(--accent-ink);
            display:flex; align-items:center; justify-content:center; margin-bottom:14px;
        }
        :root[data-theme="dark"] .cat-icn{color:var(--accent);}
        .cat-name{font-weight:700; font-size:14.5px; margin-bottom:3px;}
        .cat-count{font-size:12.5px; color:var(--text-muted);}

        /* ===== CTA ===== */
        .cta-band{
            background:linear-gradient(135deg, var(--accent), var(--accent-ink));
            border-radius:22px; padding:54px 56px; display:flex; align-items:center; justify-content:space-between;
            gap:24px; flex-wrap:wrap; color:#fff;
        }
        .cta-band h2{font-size:28px; font-weight:800; margin-bottom:8px; letter-spacing:-.5px;}
        .cta-band p{opacity:.9; font-size:14.5px; max-width:420px;}
        .cta-actions{display:flex; gap:12px;}
        .btn-white{
            background:#fff; color:var(--accent-ink); padding:12px 22px; border-radius:10px;
            font-weight:700; font-size:14.5px; border:none; cursor:pointer;
        }
        .btn-outline-white{
            background:transparent; color:#fff; padding:12px 22px; border-radius:10px;
            font-weight:700; font-size:14.5px; border:1.5px solid rgba(255,255,255,.5); cursor:pointer;
        }

        /* ===== FOOTER ===== */
        footer{border-top:1px solid var(--border); padding:44px 0 28px; margin-top:20px;}
        .footer-row{display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:18px;}
        .footer-links{display:flex; gap:26px; font-size:13.5px; color:var(--text-muted);}
        .footer-links a:hover{color:var(--text);}
        .footer-copy{font-size:12.5px; color:var(--text-muted); margin-top:22px;}

        @media (max-width:980px){
            nav.main-nav{display:none;}
            .hero-grid{grid-template-columns:1fr;}
            .hero-visual{display:none;}
            .stats-grid{grid-template-columns:repeat(2,1fr);}
            .steps-grid{grid-template-columns:1fr 1fr;}
            .pricing-grid{grid-template-columns:1fr;}
            .cat-grid{grid-template-columns:repeat(2,1fr);}
            h1.hero-title{font-size:32px;}
            .cta-band{flex-direction:column; align-items:flex-start;}
            .field-row{flex-direction:column;}
        }
    </style>
</head>
<body>

<header class="site">
    <div class="container site-row">
        <div class="brand">
            <a href="{{ route('public.home') }}" style="display:flex; align-items:center; gap:10px; text-decoration:none; color:inherit;">
            @include('partials.brand-mark', ['brandSize' => 34])
            <div class="logo-text">Work<span>io</span></div>
        </a>
        </div>
        <nav class="main-nav">
            <a href="#">Кандидаты</a>
            <a href="#" class="on">Разместить вакансию</a>
            <a href="#">Тарифы</a>
            <a href="#">Соискателям</a>
        </nav>
        <div class="header-actions">
            <div class="theme-toggle">
                <button class="theme-btn active" id="lightBtn" onclick="setTheme('light')">☀</button>
                <button class="theme-btn" id="darkBtn" onclick="setTheme('dark')">☾</button>
            </div>
            <a href="#" class="btn-ghost">Войти как компания</a>
            <a href="#" class="btn-primary">Разместить вакансию</a>
        </div>
    </div>
</header>

<section class="hero">
    <div class="container">
        <div class="mode-switch">
            <a href="#">Ищу работу</a>
            <a href="#" class="on">Ищу сотрудника</a>
        </div>
        <div class="hero-grid">
            <div>
                <div class="eyebrow"><span class="dot"></span> 5 620 кандидатов на связи прямо сейчас</div>
                <h1 class="hero-title">Найдите сотрудника<br>за <em>считанные дни,</em><br>а не недели</h1>
                <p class="hero-sub">Опишите вакансию — Workio подберёт релевантных кандидатов, соберёт отклики и покажет их в одной панели, без лишней переписки.</p>

                <div class="form-card">
                    <div class="form-card-title">Быстрый старт вакансии</div>
                    <div class="field-row">
                        <div class="field">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7h-9m9 5h-9m9 5h-9M5 7h.01M5 12h.01M5 17h.01"/></svg>
                            <input type="text" placeholder="Название должности">
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 6-9 12-9 12s-9-6-9-12a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <input type="text" placeholder="Город или «Удалённо»">
                        </div>
                        <div class="field">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            <input type="text" placeholder="Вилка зарплаты">
                        </div>
                    </div>
                    <button class="btn-primary">Опубликовать вакансию</button>
                    <div class="form-hint">Первая вакансия — бесплатно. Модерация занимает до 2 часов.</div>
                </div>

                <div class="hero-tags">
                    <span class="tag-chip">Подбор по навыкам</span>
                    <span class="tag-chip">Отклики за 24 часа</span>
                    <span class="tag-chip">Проверка резюме</span>
                </div>
            </div>

            <div class="hero-visual">
                <div class="float-card">
                    <div class="fc-head">
                        <div class="fc-title">Подходящие кандидаты</div>
                        <div class="fc-badge">Обновлено сейчас</div>
                    </div>
                    <div class="cand-row">
                        <div class="cand-avatar">АК</div>
                        <div>
                            <div class="cand-name">Анна Ковалёва</div>
                            <div class="cand-role">Senior React Developer · 6 лет</div>
                        </div>
                        <div class="cand-match">96%</div>
                    </div>
                    <div class="cand-row">
                        <div class="cand-avatar">ДС</div>
                        <div>
                            <div class="cand-name">Дмитрий Соколов</div>
                            <div class="cand-role">Product Manager · 4 года</div>
                        </div>
                        <div class="cand-match">91%</div>
                    </div>
                    <div class="cand-row">
                        <div class="cand-avatar">МП</div>
                        <div>
                            <div class="cand-name">Мария Петрова</div>
                            <div class="cand-role">DevOps Engineer · 5 лет</div>
                        </div>
                        <div class="cand-match">88%</div>
                    </div>
                </div>
                <div style="margin-top: 10vh;">
                    <div class="float-stat">
                        <div class="float-icn">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        </div>
                        <div>
                            <div class="num">347</div>
                            <div class="lbl">откликов сегодня</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="logo-strip">
    <div class="container logo-strip-row">
        <div class="logo-strip-label">Вакансии этих компаний уже собирают отклики</div>
        <div class="logo-strip-names">
            <span>Yandex</span>
            <span>Kaspersky</span>
            <span>Авито</span>
            <span>Сбер</span>
            <span>Wildberries</span>
            <span>MTC Digital</span>
        </div>
    </div>
</section>

<section class="stats">
    <div class="container stats-grid">
        <div class="stat-box">
            <div class="stat-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7h-9m9 5h-9m9 5h-9M5 7h.01M5 12h.01M5 17h.01"/></svg>
            </div>
            <div class="stat-num">1 284</div>
            <div class="stat-lbl">{{ __('активных вакансий на платформе') }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="stat-num">5 620</div>
            <div class="stat-lbl">новых кандидатов в этом месяце</div>
        </div>
        <div class="stat-box">
            <div class="stat-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>
            </div>
            <div class="stat-num">2,3 дня</div>
            <div class="stat-lbl">среднее время до первого отклика</div>
        </div>
        <div class="stat-box">
            <div class="stat-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <div class="stat-num">98%</div>
            <div class="stat-lbl">вакансий проходят модерацию без правок</div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <h2>Как это работает для компаний</h2>
            <p>От публикации вакансии до найма — четыре понятных шага, без сложных интеграций и лишних настроек.</p>
        </div>
        <div class="steps-grid">
            <div class="step-card">
                <div class="step-num">1</div>
                <h3>Опишите вакансию</h3>
                <p>Укажите должность, обязанности и вилку зарплаты — форма подскажет, чего не хватает для хорошего отклика.</p>
            </div>
            <div class="step-card">
                <div class="step-num">2</div>
                <h3>Пройдите модерацию</h3>
                <p>Вакансия проверяется автоматически и вручную — публикация занимает до 2 часов.</p>
            </div>
            <div class="step-card">
                <div class="step-num">3</div>
                <h3>Получайте отклики</h3>
                <p>Кандидаты приходят в единую панель, отсортированные по релевантности навыкам и опыту.</p>
            </div>
            <div class="step-card">
                <div class="step-num">4</div>
                <h3>Общайтесь и нанимайте</h3>
                <p>Пишите кандидатам напрямую в Workio, назначайте интервью и закрывайте вакансию.</p>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <h2>Кандидаты по направлениям</h2>
            <p>Больше всего откликов сейчас собирают эти категории — ориентир для вашей следующей вакансии.</p>
        </div>
        <div class="cat-grid">
            <div class="cat-card">
                <div class="cat-icn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg></div>
                <div class="cat-name">Разработка</div>
                <div class="cat-count">412 кандидатов</div>
            </div>
            <div class="cat-card">
                <div class="cat-icn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></div>
                <div class="cat-name">Продукт и аналитика</div>
                <div class="cat-count">198 кандидатов</div>
            </div>
            <div class="cat-card">
                <div class="cat-icn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.9 0 1.5-.7 1.5-1.5 0-.4-.2-.8-.4-1.1-.2-.3-.4-.7-.4-1.1 0-.8.7-1.5 1.5-1.5H16c3.3 0 6-2.7 6-6 0-4.4-4.5-8-10-8z"/></svg></div>
                <div class="cat-name">Дизайн</div>
                <div class="cat-count">156 кандидатов</div>
            </div>
            <div class="cat-card">
                <div class="cat-icn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
                <div class="cat-name">Маркетинг и продажи</div>
                <div class="cat-count">274 кандидата</div>
            </div>
            <div class="cat-card">
                <div class="cat-icn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                <div class="cat-name">HR и администрирование</div>
                <div class="cat-count">89 кандидатов</div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <h2>Тарифы для компаний</h2>
            <p>Платите за результат — от разовой вакансии до безлимитного подбора для отдела HR.</p>
        </div>
        <div class="pricing-grid">
            <div class="price-card">
                <div class="price-kicker">Разово</div>
                <div class="price-name">Одна вакансия</div>
                <div class="price-desc">Чтобы закрыть одну открытую позицию без подписки.</div>
                <div class="price-amount">2 900 сомони<span> / вакансия</span></div>
                <ul class="price-list">
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Публикация на 30 дней</li>
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Отклики в панели</li>
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Базовая модерация</li>
                </ul>
                <a href="#" class="btn-ghost">Выбрать тариф</a>
            </div>
            <div class="price-card pop">
                <div class="price-kicker">Популярный</div>
                <div class="price-name">Команда</div>
                <div class="price-desc">Для регулярного найма — до 10 вакансий одновременно.</div>
                <div class="price-amount">14 900 сомони<span> / месяц</span></div>
                <ul class="price-list">
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> До 10 вакансий</li>
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Приоритет в поиске</li>
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Подбор по навыкам</li>
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> До 5 мест команды</li>
                </ul>
                <a href="#" class="btn-primary">Выбрать тариф</a>
            </div>
            <div class="price-card">
                <div class="price-kicker">Бизнес</div>
                <div class="price-name">HR-отдел</div>
                <div class="price-desc">Безлимитный подбор для крупных команд и агентств.</div>
                <div class="price-amount">По запросу</div>
                <ul class="price-list">
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Безлимит вакансий</li>
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Персональный менеджер</li>
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> API и интеграции</li>
                </ul>
                <a href="#" class="btn-ghost">Связаться с нами</a>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="cta-band">
            <div>
                <h2>Готовы найти сотрудника?</h2>
                <p>Разместите первую вакансию бесплатно и получите первые отклики уже сегодня.</p>
            </div>
            <div class="cta-actions">
                <button class="btn-white">Разместить вакансию</button>
                <button class="btn-outline-white">Посмотреть тарифы</button>
            </div>
        </div>
    </div>
</section>

<footer>
    <div class="container">
        <div class="footer-row">
            <div class="brand">
                <a href="{{ route('public.home') }}" style="display:flex; align-items:center; gap:10px; text-decoration:none; color:inherit;">
            @include('partials.brand-mark', ['brandSize' => 34])
            <div class="logo-text">Work<span>io</span></div>
        </a>
            </div>
            <div class="footer-links">
                <a href="#">Кандидаты</a>
                <a href="#">Тарифы</a>
                <a href="#">О нас</a>
                <a href="#">Контакты</a>
                <a href="#">Поддержка</a>
            </div>
        </div>
        <div class="footer-copy">© {{ date('Y') }} Workio. {{ __('Все права защищены.') }}</div>
    </div>
</footer>

<script>
    function setTheme(t) {
        document.documentElement.setAttribute('data-theme', t);
        document.getElementById('lightBtn').classList.toggle('active', t === 'light');
        document.getElementById('darkBtn').classList.toggle('active', t === 'dark');
    }
</script>

<script>
    (function () {
        const input = document.getElementById('avatarInput');
        const preview = document.getElementById('avatarPreview');
        const letter = document.getElementById('avatarLetter');
        if (!input) { return; }

        input.addEventListener('change', function () {
            const file = input.files && input.files[0];
            if (!file || !preview) { return; }
            preview.src = URL.createObjectURL(file);
            preview.style.display = '';
            if (letter) { letter.style.display = 'none'; }
        });
    })();
</script>
</body>
</html>
----}}
