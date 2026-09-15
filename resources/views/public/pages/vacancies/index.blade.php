@extends('public.layouts.app')
@section('content')

    <style>
        :root{
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
            --accent-text:#2A3FCC;
            /* освещение и скругление: на светлой теме подсветка почти незаметна */
            --glow-a:rgba(61,90,254,.10);
            --glow-b:rgba(0,200,255,.07);
            --art-radius:26px;
            --art-dim:0;
            --art-glow:0 30px 70px -40px rgba(23,27,44,.35);
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
            --shadow: 0 1px 0 rgba(255,255,255,.05) inset, 0 18px 44px -16px rgba(0,0,0,.7);
            --accent-text:#8C9CFF;
            /* на тёмной теме подсветка работает в полную силу */
            --glow-a:rgba(110,132,255,.22);
            --glow-b:rgba(0,200,255,.13);
            --art-radius:26px;
            /* затемнение картинки: без него она светится пятном на тёмном фоне */
            --art-dim:.42;
            --art-glow:0 40px 90px -50px rgba(110,132,255,.5);
        }
        /* «системная» тема: атрибута нет, решает настройка устройства.
           :not([data-theme="light"]) нужен, чтобы явно выбранная светлая
           тема побеждала тёмную систему. */
        @media (prefers-color-scheme: dark){
            :root:not([data-theme="light"]){
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
            --shadow: 0 1px 0 rgba(255,255,255,.05) inset, 0 18px 44px -16px rgba(0,0,0,.7);
            --accent-text:#8C9CFF;
            /* на тёмной теме подсветка работает в полную силу */
            --glow-a:rgba(110,132,255,.22);
            --glow-b:rgba(0,200,255,.13);
            --art-radius:26px;
            /* затемнение картинки: без него она светится пятном на тёмном фоне */
            --art-dim:.42;
            --art-glow:0 40px 90px -50px rgba(110,132,255,.5);
            }
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
            background:var(--accent-soft); color:var(--accent-text);
            font-size:12.5px; font-weight:700; padding:7px 13px; border-radius:99px;
            margin-bottom:22px;
        }
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
            font-weight:800; font-size:13px; color:var(--accent-text);
        }
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
            display:flex; align-items:center; justify-content:center; color:var(--accent-text); flex-shrink:0;
        }

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
            width:36px; height:36px; border-radius:9px; background:var(--accent-soft); color:var(--accent-text);
            display:flex; align-items:center; justify-content:center; margin-bottom:14px; font-size:15px;
        }
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
            width:100px; height:30px; border-radius:9px; background:var(--accent-soft); color:var(--accent-text);
            display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px;
        }
        .step-card h3{font-size:15.5px; font-weight:700; margin-bottom:8px;}
        .step-card p{font-size:13px; color:var(--text-muted); line-height:1.55;}

        /* pricing */
        .pricing-grid{display:grid; grid-template-columns:repeat(3,1fr); gap:20px;}
        .price-card{
            background:var(--surface); border:1px solid var(--border); border-radius:18px; padding:30px 26px;
        }
        .price-card.pop{border-color:var(--accent); box-shadow:0 0 0 1px var(--accent) inset;}
        .price-kicker{font-size:12px; font-weight:700; color:var(--accent-text); text-transform:uppercase; letter-spacing:.6px; margin-bottom:8px;}
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
            width:36px; height:36px; border-radius:9px; background:var(--accent-soft); color:var(--accent-text);
            display:flex; align-items:center; justify-content:center; margin-bottom:14px;
        }
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
            background:#fff; color:var(--accent-text); padding:12px 22px; border-radius:10px;
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
        /* ===== карточки вакансий ===== */
        .vac-toolbar{
            display:flex; flex-wrap:wrap; gap:12px; align-items:center; margin-bottom:28px;
        }
        .vac-toolbar input{
            padding:12px 16px; background:var(--surface); color:var(--text);
            border:1px solid var(--border); border-radius:12px;
            font-family:inherit; font-size:14.5px; outline:none;
            transition:border-color .2s ease, box-shadow .2s ease;
        }
        .vac-toolbar input:focus{
            border-color:var(--accent);
            box-shadow:0 0 0 3px color-mix(in srgb, var(--accent) 18%, transparent);
        }
        .vac-toolbar .q{flex:1 1 280px; max-width:420px;}
        .vac-toolbar .c{flex:0 1 220px;}
        .vac-toolbar button{
            border:none; cursor:pointer; font-family:inherit;
            padding:12px 26px; border-radius:12px;
        }
        .vac-reset{font-size:13.5px; color:var(--text-muted); font-weight:600;}
        .vac-reset:hover{color:var(--accent);}

        .vac-grid{display:grid; grid-template-columns:repeat(3,1fr); gap:20px;}
        .vac-card{
            background:var(--surface); border:1px solid var(--border); border-radius:16px;
            padding:24px; display:flex; flex-direction:column; gap:12px;
            transition:border-color .2s ease, transform .2s ease, box-shadow .2s ease;
        }
        .vac-card:hover{border-color:var(--accent); transform:translateY(-2px); box-shadow:var(--shadow);}
        .vac-top{display:flex; align-items:flex-start; gap:12px;}
        .vac-logo{
            width:44px; height:44px; border-radius:12px; flex-shrink:0;
            background:var(--accent-soft); color:var(--accent-text);
            display:flex; align-items:center; justify-content:center; font-weight:800; font-size:17px;
        }
        .vac-title{font-size:16.5px; font-weight:800; letter-spacing:-.2px; line-height:1.3;}
        .vac-co{font-size:13px; color:var(--text-muted); margin-top:2px;}
        .vac-meta{display:flex; flex-wrap:wrap; gap:8px;}
        .vac-foot{
            margin-top:auto; padding-top:14px; border-top:1px solid var(--border);
            display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;
        }
        .vac-salary{font-weight:800; font-size:15.5px; color:var(--accent-text); white-space:nowrap;}
        .vac-btn{
            font-size:13.5px; font-weight:700; padding:9px 18px; border-radius:11px;
            background:var(--accent); color:#fff;
        }
        .vac-btn:hover{background:var(--accent-ink);}
        .vac-empty{
            grid-column:1 / -1; padding:52px 20px; text-align:center;
            background:var(--surface); border:1px dashed var(--border); border-radius:16px;
            color:var(--text-muted); font-size:14.5px;
        }
        @media (max-width:1024px){ .vac-grid{grid-template-columns:repeat(2,1fr);} }
        @media (max-width:720px){ .vac-grid{grid-template-columns:1fr;} }
    </style>
    <section class="section">
        <div class="container">
            <div class="section-head">
                <h2>{{ __('Как разместить вакансию') }}</h2>
                <p>{{ __('От публикации вакансии до найма — четыре понятных шага, без сложных интеграций и лишних настроек.') }}</p>
            </div>
            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-num" style="margin-bottom: 1vh;">{{ __('Шаг 1') }}</div>
                    <h3>{{ __('Опишите вакансию') }}</h3>
                    <p>{{ __('Укажите должность, обязанности и вилку зарплаты — форма подскажет, чего не хватает для хорошего отклика.') }}</p>
                </div>
                <div class="step-card">
                    <div class="step-num" style="margin-bottom: 1vh;">{{ __('Шаг 2') }}</div>
                    <h3>{{ __('Пройдите модерацию') }}</h3>
                    <p>{{ __('Вакансия проверяется автоматически и вручную — публикация занимает до 2 часов.') }}</p>
                </div>
                <div class="step-card">
                    <div class="step-num" style="margin-bottom: 1vh;">{{ __('Шаг 3') }}</div>
                    <h3>{{ __('Получайте отклики') }}</h3>
                    <p>{{ __('Кандидаты приходят в единую панель, отсортированные по релевантности навыкам и опыту.') }}</p>
                </div>
                <div class="step-card">
                    <div class="step-num" style="margin-bottom: 1vh;">{{ __('Шаг 4') }}</div>
                    <h3>{{ __('Общайтесь и нанимайте') }}</h3>
                    <p>{{ __('Пишите кандидатам напрямую в Workio, назначайте интервью и закрывайте вакансию.') }}</p>
                </div>
            </div>
        </div>
    </section>


    <section class="section">
        <div class="container">
            @php
                $employmentTypeLabels = [
                    'full-time' => 'Полная занятость',
                    'part-time' => 'Частичная занятость',
                    'project_work' => 'Проектная работа',
                    'internship' => 'Стажировка',
                ];
                $workScheduleLabels = [
                    'full_day' => 'Полный день',
                    'flexible_schedule' => 'Гибкий график',
                    'remote_work' => 'Удалённо',
                ];
                $experienceLabels = [
                    'not' => 'Без опыта',
                    'year' => 'От 1 года',
                    '3_years' => 'От 3 лет',
                    '3-6years' => '3–6 лет',
                    'more_6years' => 'Более 6 лет',
                ];
            @endphp

            <div class="section-head">
                <h2>{{ __('Список вакансий') }}</h2>
                <p>
                    @if(($query ?? '') !== '' || ($city ?? '') !== '')
                        Найдено {{ $vacancies->count() }}
                        @if(($query ?? '') !== '') по запросу «{{ $query }}» @endif
                        @if(($city ?? '') !== '') в городе «{{ $city }}» @endif
                        · <a class="vac-reset" href="{{ route('public.vacancies.index') }}">{{ __('сбросить фильтр') }}</a>
                    @else
                        {{ $vacancies->count() }} открытых позиций на платформе
                    @endif
                </p>
            </div>

            <form class="vac-toolbar" action="{{ route('public.vacancies.index') }}" method="get">
                <input class="q" type="text" name="q" value="{{ $query ?? '' }}"
                       placeholder="{{ __('Должность, навык или ключевое слово') }}">
                <input class="c" type="text" name="city" value="{{ $city ?? '' }}" placeholder="{{ __('Город') }}">
                <button type="submit" class="btn-primary">{{ __('Найти') }}</button>
            </form>

            <div class="vac-grid">
               @forelse($vacancies as $vacancy)
                    <article class="vac-card">
                        <div class="vac-top">
                            @if($vacancy->employer?->user?->hasAvatar())
                                <img class="vac-logo" src="{{ asset($vacancy->employer->user->avatar) }}"
                                     alt="{{ $vacancy->employer->company_name }}" style="object-fit:cover;">
                            @else
                                <div class="vac-logo">{{ $vacancy->employer?->initials(2) ?? '?' }}</div>
                            @endif
                            <div style="min-width:0;">
                                <div class="vac-title">{{ $vacancy->title }}</div>
                                <div class="vac-co">{{ $vacancy->employer->company_name ?? 'Компания' }}</div>
                            </div>
                        </div>

                        <div class="vac-meta">
                            @if($vacancy->city)
                                <span class="tag-chip">📍 {{ __($vacancy->city->country) }}, {{ __($vacancy->city->region) }}</span>
                            @endif
                            <span class="tag-chip">🕒 {{ $employmentTypeLabels[$vacancy->employment_type] ?? $vacancy->employment_type }}</span>
                            <span class="tag-chip">💻 {{ $workScheduleLabels[$vacancy->work_schedule] ?? $vacancy->work_schedule }}</span>
                            <span class="tag-chip">📈 {{ $experienceLabels[$vacancy->experience_required] ?? $vacancy->experience_required }}</span>
                            <span class="tag-chip">👥 {{ $vacancy->responses_count }} {{ __('откликов') }}</span>
                            <span class="tag-chip">👁 {{ $vacancy->views }} {{ __('просмотров') }}</span>
                        </div>

                        <div class="vac-foot">
                            <span class="vac-salary">
                                {{ number_format($vacancy->salary_from, 0, ',', ' ') }}–{{ number_format($vacancy->salary_to, 0, ',', ' ') }}
                                {{ __($vacancy->currencyLabel()) }}
                            </span>
                            <a class="vac-btn" href="{{ route('public.vacancies.show', $vacancy) }}">{{ __('Посмотреть') }}</a>
                        </div>
                    </article>
               @empty
                    <div class="vac-empty">
                        @if(($query ?? '') !== '' || ($city ?? '') !== '')
                            По вашему запросу ничего не найдено
                        @else
                            Пока нет опубликованных вакансий
                        @endif
                    </div>
               @endforelse
            </div>

            @if(method_exists($vacancies, 'links'))
                {{ $vacancies->links() }}
            @endif
        </div>
    </section>

@endsection
