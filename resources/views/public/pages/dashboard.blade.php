<!DOCTYPE html>
@php
    $appTheme = auth()->check() ? (auth()->user()->theme ?: 'light') : session('theme', 'light');
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $appTheme === 'dark' ? 'dark' : 'light' }}">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Найдите работу мечты или лучшего кандидата') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>

        .hero{
            padding:88px 0 64px;
            position:relative;
            overflow:hidden;
        }

        .hero::before{
            content:"";
            position:absolute;
            width:550px;
            height:550px;
            background:rgba(61,90,254,.18);
            border-radius:50%;
            filter:blur(120px);
            top:-180px;
            left:-180px;
            z-index:-1;
        }

        .hero::after{
            content:"";
            position:absolute;
            width:420px;
            height:420px;
            background:rgba(0,200,255,.12);
            border-radius:50%;
            filter:blur(110px);
            bottom:-150px;
            right:-120px;
            z-index:-1;
        }


        .logos a{

            color:#6b7280;

            font-size:25px;

            font-weight:700;

            text-decoration:none;

            transition:.35s;
        }

        .logos a:hover{

            color:#4f6bff;

            transform:translateY(-3px);
        }

        .trust-title span{

            color:#334155;
        }


        .stat-box{

            position:relative;

            overflow:visible;
        }

        .stat-box::after{

            content:"";

            position:absolute;

            left:15%;
            right:15%;
            bottom:-18px;

            height:20px;

            background:#5b7cff;

            filter:blur(18px);

            opacity:0;

            transition:.35s;
        }

        .stat-box:hover::after{

            opacity:.8;

        }
        .stat-box{

            transition:.2s;

            transform-style:preserve-3d;

        }
        .stat-box{

            animation:breath 5s ease-in-out infinite;
        }

        @keyframes breath{

            0%,100%{

                transform:scale(1);

            }

            50%{

                transform:scale(1.02);

            }

        }
        .stat-box::after{

            content:"";

            position:absolute;

            width:220px;
            height:220px;

            border-radius:50%;

            background:#5d7cff;

            filter:blur(90px);

            opacity:.18;

            animation:blob 8s ease-in-out infinite;
        }

        @keyframes blob{

            0%{
                left:-80px;
                top:-50px;
            }

            50%{
                left:120px;
                top:80px;
            }

            100%{
                left:-80px;
                top:-50px;
            }

        }
        .stat-box{

            border:2px solid transparent;

            background:
                linear-gradient(#fff,#fff) padding-box,

                linear-gradient(
                    135deg,
                    #5b7cff,
                    #9d7cff,
                    #00d4ff,
                    #5b7cff
                ) border-box;

            animation:borderMove 6s linear infinite;
        }

        @keyframes borderMove{

            100%{
                filter:hue-rotate(360deg);
            }

        }
        .stat-box{
            position:relative;
            overflow:hidden;
        }

        .stat-box::before{
            content:'';
            position:absolute;
            top:-100%;
            left:-150%;

            width:70%;
            height:300%;

            background:linear-gradient(
                90deg,
                transparent,
                rgba(255,255,255,.7),
                transparent
            );

            transform:rotate(25deg);

            animation:shine 4s infinite;
        }

        @keyframes shine{

            0%{
                left:-180%;
            }

            100%{
                left:180%;
            }

        }
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
        .site-row{display:flex; align-items:center; height:68px;}
        .brand{display:flex; align-items:center; gap:10px; font-weight:800; font-size:18px;}
        .logo-mark{
            width:34px; height:34px; border-radius:9px;
            background:linear-gradient(135deg, var(--accent), var(--accent-ink));
            display:flex; align-items:center; justify-content:center;
            color:#fff; font-size:13px; font-weight:800; letter-spacing:.5px;
        }
        .brand span{color:var(--accent);}
        nav.main-nav{display:flex; align-items:center; gap:30px; font-size:14.5px; font-weight:500; color:var(--text-muted);}
        nav.main-nav a:hover{color:var(--text);}
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

        /* ===== HERO ===== */
        .hero{padding:88px 0 64px; position:relative; overflow:hidden;}
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
            font-size:50px; line-height:1.08; font-weight:800; letter-spacing:-1.2px;
            margin-bottom:22px;
        }
        h1.hero-title em{font-style:normal; color:var(--accent);}
        .hero-sub{
            font-size:17px; color:var(--text-muted); line-height:1.65; max-width:480px; margin-bottom:34px;
        }

        /* search card */
        .search-card{
            display:flex; gap:10px; background:var(--surface); border:1px solid var(--border);
            padding:8px; border-radius:14px; box-shadow:var(--shadow); max-width:560px;
        }
        .search-field{
            flex:1; display:flex; align-items:center; gap:10px; padding:8px 12px;
        }
        .search-field svg{width:18px; height:18px; color:var(--text-muted); flex-shrink:0;}
        .search-field input{
            border:none; outline:none; background:none; font-size:14.5px; width:100%;
            font-family:'Inter'; color:var(--text);
        }
        .search-divider{width:1px; background:var(--border); margin:4px 0;}
        .search-card .btn-primary{padding:0 22px; white-space:nowrap;}

        .hero-tags{display:flex; flex-wrap:wrap; gap:8px; margin-top:18px;}
        .tag-chip{
            font-size:12.5px; color:var(--text-muted); background:var(--surface-alt);
            border:1px solid var(--border); padding:6px 12px; border-radius:99px;
        }

        /* hero visual: mock dashboard card */
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
        .fc-row{
            display:flex; justify-content:space-between; align-items:center;
            padding:12px 0; border-bottom:1px solid var(--border); font-size:13.5px;
        }
        .fc-row:last-child{border-bottom:none;}
        .fc-job{font-weight:600;}
        .fc-co{color:var(--text-muted); font-size:12px;}
        .fc-salary{font-weight:700; color:var(--accent-ink); font-size:13px;}
        :root[data-theme="dark"] .fc-salary{color:var(--accent);}
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
        .stats{padding:64px 0;}
        .stats-grid{display:grid; grid-template-columns:repeat(4,1fr); gap:22px;}
        .stat-box{
            background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:24px;
        }
        .stat-icon{
            width:52px; height:52px; border-radius:14px; margin-bottom:14px;
            background:color-mix(in srgb, var(--accent) 12%, transparent);
            color:var(--accent-ink);
            display:flex; align-items:center; justify-content:center;
        }
        .stat-icon svg{width:26px; height:26px;}
        :root[data-theme="dark"] .stat-icon{color:var(--accent);}
        .stat-num{font-size:30px; font-weight:800; margin-bottom:4px;}
        .stat-lbl{font-size:13px; color:var(--text-muted); font-weight:500;}

        /* ===== HOW IT WORKS (two columns: candidates / companies) ===== */
        .section{padding:64px 10px;}
        .section-head{max-width:580px; margin-bottom:44px;}
        .section-head h2{font-size:32px; font-weight:800; letter-spacing:-.6px; margin-bottom:12px;}
        .section-head p{color:var(--text-muted); font-size:15.5px; line-height:1.6;}

        .audience-grid{display:grid; grid-template-columns:1fr 1fr; gap:22px;}
        .audience-card{
            background:var(--surface); border:1px solid var(--border); border-radius:18px; padding:32px;
            position:relative; overflow:hidden;
        }
        .audience-card.company{border-color:var(--accent); box-shadow:0 0 0 1px var(--accent) inset;}
        .audience-kicker{font-size:12px; font-weight:700; color:var(--accent-ink); text-transform:uppercase; letter-spacing:.6px; margin-bottom:10px;}
        :root[data-theme="dark"] .audience-kicker{color:var(--accent);}
        .audience-card h3{font-size:22px; font-weight:800; margin-bottom:10px;}
        .audience-card p{color:var(--text-muted); font-size:14.5px; line-height:1.6; margin-bottom:22px;}
        .step-list{display:flex; flex-direction:column; gap:14px; margin-bottom:24px;}
        .step{display:flex; gap:12px; align-items:flex-start;}
        .step-num{
            width:24px; height:24px; border-radius:7px; background:var(--surface-alt); border:1px solid var(--border);
            display:flex; align-items:center; justify-content:center; font-size:11.5px; font-weight:700; flex-shrink:0; margin-top:1px;
        }
        .step-text{font-size:13.5px; color:var(--text);}
        .step-text b{display:block; font-weight:600; margin-bottom:1px;}
        .step-text span{color:var(--text-muted); font-size:12.5px;}

        /* ===== CATEGORIES ===== */
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
        footer{border-top:1px solid var(--border); padding:44px 0 28px; margin-top:40px;}
        .footer-row{display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:18px;}
        .footer-links{display:flex; gap:26px; font-size:13.5px; color:var(--text-muted);}
        .footer-links a:hover{color:var(--text);}
        .footer-copy{font-size:12.5px; color:var(--text-muted); margin-top:22px;}

        @media (max-width:980px){
            nav.main-nav{display:none;}
            .hero-grid{grid-template-columns:1fr;}
            .hero-visual{display:none;}
            .stats-grid{grid-template-columns:repeat(2,1fr);}
            .audience-grid{grid-template-columns:1fr;}
            .cat-grid{grid-template-columns:repeat(2,1fr);}
            h1.hero-title{font-size:36px;}
            .cta-band{flex-direction:column; align-items:flex-start;}
        }
    </style>
</head>
<body>

@include('public.partials.header')

<style>
    .job-grid{display:grid; grid-template-columns:repeat(3,1fr); gap:18px;}
    .job-card{
        background:var(--surface); border:1px solid var(--border); border-radius:16px;
        padding:22px; display:flex; flex-direction:column; gap:10px; transition:.2s ease;
    }
    .job-card:hover{border-color:var(--accent); transform:translateY(-2px); box-shadow:var(--shadow);}
    .job-title{font-size:16.5px; font-weight:800; letter-spacing:-.2px;}
    .job-co{font-size:13px; color:var(--text-muted);}
    .job-meta{display:flex; flex-wrap:wrap; gap:8px; margin-top:2px;}
    .job-foot{
        margin-top:auto; padding-top:14px; border-top:1px solid var(--border);
        display:flex; align-items:center; justify-content:space-between; gap:10px;
    }
    .job-salary{font-weight:800; color:var(--accent-ink); font-size:15px; white-space:nowrap;}
    :root[data-theme="dark"] .job-salary{color:var(--accent);}
    .job-link{font-size:13.5px; font-weight:700; color:var(--accent);}
    .section-actions{margin-top:28px; display:flex; gap:12px; flex-wrap:wrap;}
    .empty-block{
        background:var(--surface); border:1px dashed var(--border); border-radius:16px;
        padding:44px 20px; text-align:center; color:var(--text-muted); font-size:14.5px;
    }
    .cat-card{display:block;}
    /* ===== О сервисе ===== */
    .about-lead{
        display:grid; grid-template-columns:minmax(0,1.15fr) minmax(0,1fr); gap:26px; align-items:start;
        margin-bottom:26px;
    }
    .about-text{font-size:15.5px; line-height:1.75; color:var(--text-muted);}
    .about-text b{color:var(--text);}
    .about-numbers{
        display:grid; grid-template-columns:1fr 1fr; gap:14px;
    }
    .about-num{
        background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:18px 20px;
    }
    .about-num .n{font-size:24px; font-weight:800; letter-spacing:-.4px;}
    .about-num .l{font-size:12.5px; color:var(--text-muted); margin-top:3px;}

    .about-grid{display:grid; grid-template-columns:repeat(4,1fr); gap:18px; margin-top:8px;}
    .about-card{
        background:var(--surface); border:1px solid var(--border); border-radius:16px;
        padding:22px; min-width:0; transition:.2s ease;
    }
    .about-card:hover{border-color:var(--accent); transform:translateY(-2px); box-shadow:var(--shadow);}
    .about-icn{
        width:52px; height:52px; border-radius:14px; margin-bottom:16px;
        background:color-mix(in srgb, var(--accent) 12%, transparent);
        color:var(--accent-ink);
        display:flex; align-items:center; justify-content:center;
    }
    .about-icn svg{width:26px; height:26px;}
    :root[data-theme="dark"] .about-icn{color:var(--accent);}
    .about-card h3{font-size:15px; font-weight:700; margin-bottom:7px;}
    .about-card p{font-size:13.5px; color:var(--text-muted); line-height:1.6;}

    .faq{margin-top:34px; display:flex; flex-direction:column; gap:10px;}
    .faq details{
        background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:16px 20px;
    }
    .faq details[open]{border-color:var(--accent);}
    .faq summary{
        cursor:pointer; font-weight:700; font-size:14.5px; list-style:none;
        display:flex; align-items:center; justify-content:space-between; gap:14px;
    }
    .faq summary::-webkit-details-marker{display:none;}
    .faq summary::after{content:"+"; font-size:19px; font-weight:700; color:var(--accent);}
    .faq details[open] summary::after{content:"–";}
    .faq p{margin-top:11px; font-size:14px; line-height:1.65; color:var(--text-muted);}

    @media (max-width:1024px){
        .about-lead{grid-template-columns:1fr;}
        .about-grid{grid-template-columns:repeat(2,1fr);}
    }
    @media (max-width:720px){
        .about-grid{grid-template-columns:1fr;}
    }

    .co-grid{display:grid; grid-template-columns:repeat(4,1fr); gap:18px;}
    .co-card{
        display:flex; flex-direction:column; gap:14px;
        background:var(--surface); border:1px solid var(--border); border-radius:16px;
        padding:20px; transition:.2s ease; min-width:0;
    }
    .co-card:hover{border-color:var(--accent); transform:translateY(-2px); box-shadow:var(--shadow);}
    .co-top{display:flex; align-items:center; gap:12px; min-width:0;}
    .co-logo, .co-logo-img{
        width:46px; height:46px; border-radius:12px; flex-shrink:0; object-fit:cover;
    }
    .co-logo{
        background:var(--accent-soft); color:var(--accent-ink);
        display:flex; align-items:center; justify-content:center; font-weight:800; font-size:18px;
    }
    :root[data-theme="dark"] .co-logo{color:var(--accent);}
    .co-name{
        font-weight:700; font-size:15.5px; overflow:hidden;
        text-overflow:ellipsis; white-space:nowrap;
    }
    .co-sub{font-size:12.5px; color:var(--text-muted); margin-top:2px;}
    .co-foot{
        margin-top:auto; padding-top:12px; border-top:1px solid var(--border);
        display:flex; align-items:center; justify-content:space-between; gap:10px;
        font-size:12.5px; color:var(--text-muted); flex-wrap:wrap;
    }
    .co-count{font-weight:700; color:var(--accent-ink); white-space:nowrap;}
    :root[data-theme="dark"] .co-count{color:var(--accent);}
    @media (max-width:1024px){ .co-grid{grid-template-columns:repeat(2,1fr);} }
    @media (max-width:720px){ .co-grid{grid-template-columns:1fr;} }
    @media (max-width:1024px){ .job-grid{grid-template-columns:repeat(2,1fr);} }
    @media (max-width:720px){ .job-grid{grid-template-columns:1fr;} }
</style>

<div class="liquid-bg"></div>

<section class="hero">
    <div class="container hero-grid">
        <div>
            <div class="eyebrow"><span class="dot"></span>{{ $activeVacancies }} активных вакансий</div>
            <h1 class="hero-title">{{ __('Работа мечты') }}<br>{{ __('и сильная команда') }} <em>{{ __('в одном месте') }}</em></h1>
            <p class="hero-sub">{{ __('Workio соединяет компании и соискателей: умный поиск, быстрые отклики и прозрачная модерация — всё в одной панели.') }}</p>

            <form class="search-card" action="{{ route('public.vacancies.index') }}" method="get">
                <div class="search-field">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="q" placeholder="{{ __('Должность, навык или компания') }}">
                </div>
                <div class="search-divider"></div>
                <div class="search-field" style="flex:.6">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 6-9 12-9 12s-9-6-9-12a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <input type="text" name="city" placeholder="{{ __('Город') }}">
                </div>
                <button type="submit" class="btn-primary">{{ __('Найти') }}</button>
            </form>

            <div class="hero-tags">
                @foreach(['React Developer', 'Product Manager', 'UX/UI Designer', 'Data Analyst', 'Удалённо'] as $tag)
                    <a class="tag-chip" href="{{ route('public.vacancies.index', ['q' => $tag]) }}">{{ $tag }}</a>
                @endforeach
            </div>
        </div>

        <div class="hero-visual">
            <div style="margin-bottom: 10vh;">
                @auth
                    @if(auth()->user()->role === 'employer')
                        <a href="{{ route('employer.vacancies.create') }}" class="btn-ghost" style="margin: 1vh;">{{ __('Разместить вакансию') }}</a>
                    @else
                        <a href="{{ route('public.resumes.index') }}" class="btn-ghost" style="margin: 1vh;">{{ __('Смотреть кандидатов') }}</a>
                    @endif
                @endauth
                @guest
                    <a href="{{ route('register') }}" class="btn-ghost" style="margin: 1vh;">{{ __('Ищу сотрудника') }}</a>
                @endguest

                <a href="{{ route('public.vacancies.index') }}" class="btn-ghost">{{ __('Ищу работу') }}</a>
            </div>

            <img src="{{ asset('assets/img/hh_ru.png') }}" alt="Workio" width="477" height="477">
        </div>
    </div>
</section>

<section class="stats">
    <div class="container stats-grid">
        <div class="stat-box">
            <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="14" x="2" y="7" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><path d="M2 13h20"/></svg></div>
            <div class="stat-num">{{ $activeVacancies }}</div>
            <div class="stat-lbl">{{ __('активных вакансий') }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
            <div class="stat-num">{{ $newApplicantsMonth }}</div>
            <div class="stat-lbl">{{ __('новых соискателей за месяц') }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="m7 14 3-3 3 3 5-6"/></svg></div>
            <div class="stat-num">{{ $responsesToday }}</div>
            <div class="stat-lbl">{{ __('откликов сегодня') }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 2H8a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7z"/><path d="M14 2v5h5"/><path d="M9 13h6"/><path d="M9 17h4"/></svg></div>
            <div class="stat-num">{{ $resumesCount }}</div>
            <div class="stat-lbl">{{ __('резюме в базе') }}</div>
        </div>
    </div>
</section>

<section class="section" id="vacancies">
    <div class="container">
        <div class="section-head">
            <h2>{{ __('Свежие вакансии') }}</h2>
            <p>{{ __('Последние открытые позиции от компаний платформы.') }}</p>
        </div>

        @if($latestVacancies->isEmpty())
            <div class="empty-block">{{ __('Пока нет активных вакансий') }}</div>
        @else
            <div class="job-grid">
                @foreach($latestVacancies as $vacancy)
                    <article class="job-card">
                        <div>
                            <div class="job-title">{{ $vacancy->title }}</div>
                            <div class="job-co">{{ $vacancy->employer->company_name ?? 'Компания' }}</div>
                        </div>

                        <div class="job-meta">
                            @if($vacancy->city)
                                <span class="tag-chip">📍 {{ $vacancy->city->country }}, {{ $vacancy->city->region }}</span>
                            @endif
                            <span class="tag-chip">👥 {{ $vacancy->responses_count }} {{ __('откликов') }}</span>
                            <span class="tag-chip">👁 {{ $vacancy->views }} {{ __('просмотров') }}</span>
                        </div>

                        <div class="job-foot">
                            <span class="job-salary">
                                {{ number_format($vacancy->salary_from, 0, ',', ' ') }}–{{ number_format($vacancy->salary_to, 0, ',', ' ') }} {{ __($vacancy->currencyLabel()) }}
                            </span>
                            <a class="job-link" href="{{ route('public.vacancies.show', $vacancy) }}">{{ __('Смотреть →') }}</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        <div class="section-actions">
            <a href="{{ route('public.vacancies.index') }}" class="btn-primary">{{ __('Все вакансии') }}</a>
        </div>
    </div>
</section>

<section class="section" id="resumes">
    <div class="container">
        <div class="section-head">
            <h2>{{ __('Кандидаты') }}</h2>
            <p>{{ __('Соискатели, которые недавно опубликовали резюме.') }}</p>
        </div>

        @if($latestResumes->isEmpty())
            <div class="empty-block">{{ __('Пока нет опубликованных резюме') }}</div>
        @else
            <div class="job-grid">
                @foreach($latestResumes as $resume)
                    @php $resumeUser = $resume->applicant->user; @endphp
                    <article class="job-card">
                        <div style="display:flex; align-items:center; gap:12px;">
                            @if($resumeUser->hasAvatar())
                                <img src="{{ asset($resumeUser->avatar) }}" alt=""
                                     style="width:44px; height:44px; border-radius:50%; object-fit:cover;">
                            @else
                                <div style="width:44px; height:44px; border-radius:50%; background:var(--accent-soft);
                                            color:var(--accent-ink); display:flex; align-items:center; justify-content:center;
                                            font-weight:800;">{{ $resumeUser->initials(1) }}</div>
                            @endif
                            <div>
                                <div class="job-title" style="font-size:15.5px;">{{ $resumeUser->name }}</div>
                                <div class="job-co">{{ $resume->profession }}</div>
                            </div>
                        </div>

                        <div class="job-meta">
                            <span class="tag-chip">
                                @if((int) $resume->experience_years > 0)
                                    Опыт {{ (int) $resume->experience_years }} г.
                                @else
                                    Без опыта
                                @endif
                            </span>
                            <span class="tag-chip">📍 {{ $resume->applicant->city ?: '—' }}</span>
                        </div>

                        <div class="job-foot">
                            <span class="job-salary">{{ number_format($resume->desired_salary, 0, ',', ' ') }} {{ __('сомони') }}</span>
                            <a class="job-link" href="{{ route('public.resumes.show', $resume) }}">{{ __('Смотреть →') }}</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        <div class="section-actions">
            <a href="{{ route('public.resumes.index') }}" class="btn-primary">{{ __('Все резюме') }}</a>
        </div>
    </div>
</section>

<section class="section" id="companies">
    <div class="container">
        <div class="section-head">
            <h2>{{ __('Компании, которые нанимают') }}</h2>
            <p>{{ __('Работодатели с открытыми вакансиями прямо сейчас.') }}</p>
        </div>

        @if($topCompanies->isEmpty())
            <div class="empty-block">{{ __('Пока нет компаний с вакансиями') }}</div>
        @else
            <div class="co-grid">
                @foreach($topCompanies as $company)
                    <a class="co-card" href="{{ route('public.companies.show', $company) }}">
                        <div class="co-top">
                            @if($company->user?->avatar)
                                <img class="co-logo-img" src="{{ asset($company->user->avatar) }}" alt="{{ $company->company_name }}">
                            @else
                                <div class="co-logo">{{ $company->initials(2) }}</div>
                            @endif
                            <div style="min-width:0;">
                                <div class="co-name">{{ $company->company_name }}</div>
                                <div class="co-sub">{{ $company->industry->name ?? 'Компания' }}</div>
                            </div>
                        </div>

                        <div class="co-foot">
                            <span class="co-city">
                                📍 {{ $company->city ? $company->city->country.', '.$company->city->region : 'Город не указан' }}
                            </span>
                            <span class="co-count">{{ $company->vacancies_count }} вакансий</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="section-actions">
            <a href="{{ route('public.companies.index') }}" class="btn-primary">{{ __('Все компании') }}</a>
        </div>
    </div>
</section>

<section class="section" id="about">
    <div class="container">
        <div class="section-head">
            <div class="eyebrow">{{ __('О сервисе') }}</div>
            <h2>{{ __('Workio — работа без лишних шагов') }}</h2>
            <p>{{ __('Площадка, где компании и соискатели находят друг друга напрямую, без посредников и долгой переписки.') }}</p>
        </div>

        <div class="about-lead">
            <div class="about-text">
                <p><b>Workio</b> — это job-платформа с двумя кабинетами. Соискатель один раз заполняет анкету
                и публикует резюме, работодатель — карточку компании и вакансии. Дальше всё происходит в один клик:
                отклик на вакансию, приглашение на резюме, статус «принят» или «отклонён».</p>
                <p style="margin-top:14px;">Никаких скрытых тарифов и платных откликов: публикация вакансий и резюме,
                поиск и переписка по откликам доступны сразу после регистрации. Модерация следит за тем,
                чтобы в каталоге не было дублей и фиктивных вакансий.</p>
            </div>

            <div class="about-numbers">
                <div class="about-num">
                    <div class="n">{{ $activeVacancies }}</div>
                    <div class="l">{{ __('активных вакансий') }}</div>
                </div>
                <div class="about-num">
                    <div class="n">{{ $resumesCount }}</div>
                    <div class="l">{{ __('резюме в базе') }}</div>
                </div>
                <div class="about-num">
                    <div class="n">{{ $companiesCount }}</div>
                    <div class="l">{{ __('компаний') }}</div>
                </div>
                <div class="about-num">
                    <div class="n">{{ $responsesToday }}</div>
                    <div class="l">{{ __('откликов сегодня') }}</div>
                </div>
            </div>
        </div>

        <div class="about-grid">
            <div class="about-card">
                <div class="about-icn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 9l5 12 1.8-5.2L21 14z"/><path d="M7.2 2.2 8 5.1"/><path d="m5.1 8-2.9-.8"/><path d="M14 4.1 12 6"/><path d="m6 12-1.9 2"/></svg></div>
                <h3>{{ __('Отклик в один клик') }}</h3>
                <p>{{ __('Резюме уже заполнено — на вакансию достаточно нажать «Откликнуться» и при желании добавить сопроводительное письмо.') }}</p>
            </div>
            <div class="about-card">
                <div class="about-icn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3 4 7l4 4"/><path d="M4 7h16"/><path d="m16 21 4-4-4-4"/><path d="M20 17H4"/></svg></div>
                <h3>{{ __('Работает в обе стороны') }}</h3>
                <p>{{ __('Не только соискатель ищет вакансию: работодатель может сам пригласить кандидата, найденного в каталоге резюме.') }}</p>
            </div>
            <div class="about-card">
                <div class="about-icn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 17 2 2 4-4"/><path d="m3 7 2 2 4-4"/><path d="M13 6h8"/><path d="M13 12h8"/><path d="M13 18h8"/></svg></div>
                <h3>{{ __('Понятные статусы') }}</h3>
                <p>{{ __('Каждый отклик имеет статус: новый, просмотрен, принят или отклонён. Обе стороны видят, на какой стадии переговоры.') }}</p>
            </div>
            <div class="about-card">
                <div class="about-icn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg></div>
                <h3>{{ __('Контакты под защитой') }}</h3>
                <p>{{ __('Телефон и почту соискателя видят только зарегистрированные работодатели — случайные посетители не получат персональные данные.') }}</p>
            </div>
        </div>

        <div class="faq">
            <details>
                <summary>{{ __('Сколько стоит размещение?') }}</summary>
                <p>{{ __('Регистрация, публикация вакансий и резюме, а также отклики — бесплатны для обеих сторон.') }}</p>
            </details>
            <details>
                <summary>{{ __('Кто может публиковать вакансии?') }}</summary>
                <p>Пользователь с ролью «работодатель». После регистрации нужно заполнить карточку компании — название, отрасль, город и контакты, — после чего станет доступно создание вакансий.</p>
            </details>
            <details>
                <summary>{{ __('Как соискателю начать?') }}</summary>
                <p>Зарегистрируйтесь, заполните анкету соискателя и создайте резюме: профессия, желаемая должность, опыт, навыки и зарплатные ожидания. После этого можно откликаться на вакансии, а работодатели смогут находить вас в каталоге.</p>
            </details>
            <details>
                <summary>{{ __('Можно ли отозвать отклик?') }}</summary>
                <p>{{ __('Да. На странице вакансии или в разделе «Отклики» есть кнопка «Отозвать отклик» — то же самое доступно работодателю для отправленных приглашений.') }}</p>
            </details>
            <details>
                <summary>{{ __('Как удалить резюме или вакансию?') }}</summary>
                <p>{{ __('В личном кабинете у каждой карточки есть кнопка «Удалить». Вместе с записью удаляются и связанные с ней отклики.') }}</p>
            </details>
        </div>
    </div>
</section>

<section class="section" id="how">
    <div class="container">
        <div class="section-head">
            <h2>{{ __('Как это работает') }}</h2>
            <p>{{ __('Четыре шага — и вы либо нашли работу, либо нашли сотрудника.') }}</p>
        </div>

        <div class="audience-grid">
            <div class="audience-card">
                <div class="audience-kicker">{{ __('Соискателю') }}</div>
                <h3>{{ __('Найти работу') }}</h3>
                <p>{{ __('Заполните анкету один раз — и откликайтесь в один клик.') }}</p>

                <div class="step-list">
                    <div class="step">
                        <div class="step-num">1</div>
                        <div class="step-text"><b>{{ __('Регистрация') }}</b><span>{{ __('Аккаунт соискателя за минуту') }}</span></div>
                    </div>
                    <div class="step">
                        <div class="step-num">2</div>
                        <div class="step-text"><b>{{ __('Анкета') }}</b><span>{{ __('Контакты, город, образование') }}</span></div>
                    </div>
                    <div class="step">
                        <div class="step-num">3</div>
                        <div class="step-text"><b>{{ __('Резюме') }}</b><span>{{ __('Опыт, навыки, желаемая зарплата') }}</span></div>
                    </div>
                    <div class="step">
                        <div class="step-num">4</div>
                        <div class="step-text"><b>{{ __('Отклик') }}</b><span>{{ __('Кнопка «Откликнуться» на вакансии') }}</span></div>
                    </div>
                </div>

                @auth
                    @if(auth()->user()->role === 'applicant')
                        <a href="{{ route('applicant.dashboard') }}" class="btn-primary">{{ __('В мой кабинет') }}</a>
                    @else
                        <a href="{{ route('public.vacancies.index') }}" class="btn-primary">{{ __('Смотреть вакансии') }}</a>
                    @endif
                @endauth
                @guest
                    <a href="{{ route('register') }}" class="btn-primary">{{ __('Начать поиск работы') }}</a>
                @endguest
            </div>

            <div class="audience-card company">
                <div class="audience-kicker">{{ __('Работодателю') }}</div>
                <h3>{{ __('Найти сотрудника') }}</h3>
                <p>{{ __('Разместите вакансию и приглашайте кандидатов из базы резюме.') }}</p>

                <div class="step-list">
                    <div class="step">
                        <div class="step-num">1</div>
                        <div class="step-text"><b>{{ __('Регистрация') }}</b><span>{{ __('Аккаунт работодателя') }}</span></div>
                    </div>
                    <div class="step">
                        <div class="step-num">2</div>
                        <div class="step-text"><b>{{ __('Анкета компании') }}</b><span>{{ __('Название, отрасль, контакты') }}</span></div>
                    </div>
                    <div class="step">
                        <div class="step-num">3</div>
                        <div class="step-text"><b>{{ __('Вакансия') }}</b><span>{{ __('Условия, вилка, требования') }}</span></div>
                    </div>
                    <div class="step">
                        <div class="step-num">4</div>
                        <div class="step-text"><b>{{ __('Отклики') }}</b><span>{{ __('Принимайте или отклоняйте кандидатов') }}</span></div>
                    </div>
                </div>

                @auth
                    @if(auth()->user()->role === 'employer')
                        <a href="{{ route('employer.dashboard') }}" class="btn-primary">{{ __('В мой кабинет') }}</a>
                    @else
                        <a href="{{ route('public.resumes.index') }}" class="btn-primary">{{ __('Смотреть резюме') }}</a>
                    @endif
                @endauth
                @guest
                    <a href="{{ route('register') }}" class="btn-primary">{{ __('Разместить вакансию') }}</a>
                @endguest
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="cta-band">
            <div>
                <h2>{{ __('Начните искать сегодня') }}</h2>
                <p>{{ $activeVacancies }} активных вакансий и {{ $resumesCount }} резюме уже ждут на платформе.</p>
            </div>
            <div class="cta-actions">
                <a href="{{ route('public.vacancies.index') }}" class="btn-white">{{ __('Найти работу') }}</a>
                @auth
                    @if(auth()->user()->role === 'employer')
                        <a href="{{ route('employer.vacancies.create') }}" class="btn-outline-white">{{ __('Разместить вакансию') }}</a>
                    @else
                        <a href="{{ route('public.resumes.index') }}" class="btn-outline-white">{{ __('Смотреть резюме') }}</a>
                    @endif
                @endauth
                @guest
                    <a href="{{ route('register') }}" class="btn-outline-white">{{ __('Разместить вакансию') }}</a>
                @endguest
            </div>
        </div>
    </div>
</section>

@include('public.partials.footer')


<script>
    function setTheme(t) {
        document.documentElement.setAttribute('data-theme', t);
        document.getElementById('lightBtn').classList.toggle('active', t === 'light');
        document.getElementById('darkBtn').classList.toggle('active', t === 'dark');
    }


    document.querySelectorAll('.stat-box').forEach(card=>{

        card.addEventListener('mousemove',e=>{

            const rect=card.getBoundingClientRect();

            const x=e.clientX-rect.left;
            const y=e.clientY-rect.top;

            const rotateY=((x/rect.width)-0.5)*20;
            const rotateX=((y/rect.height)-0.5)*-20;

            card.style.transform=
                `perspective(900px)
 rotateX(${rotateX}deg)
 rotateY(${rotateY}deg)
 scale(1.04)`;

        });

        card.addEventListener('mouseleave',()=>{

            card.style.transform=
                'perspective(900px) rotateX(0) rotateY(0)';

        });

    });
</script>

</body>
</html>
