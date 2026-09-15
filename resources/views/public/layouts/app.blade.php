<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Найдите работу мечты или лучшего кандидата') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>



        .trusted{

            position:relative;

            overflow:hidden;

            isolation:isolate;

            background:rgba(255,255,255,.72);

            backdrop-filter:blur(22px);

            border:1px solid rgba(255,255,255,.75);

            border-radius:70px;

            box-shadow:
                0 20px 60px rgba(79,107,255,.06);

        }
        .star{

            color:#5b7cff;

            font-size:20px;

            animation:pulse 2s infinite;

        }

        @keyframes pulse{

            0%,100%{

                transform:scale(1);

            }

            50%{

                transform:scale(1.4);

            }

        }
        .trusted:hover .trusted-track{

            animation-play-state:paused;

        }
        @keyframes marquee{

            from{

                transform:translateX(0);

            }

            to{

                transform:translateX(-50%);

            }

        }
        .trusted-track span:hover{

            color:#4f6bff;

            transform:

                scale(1.15)

                translateY(-5px);

            text-shadow:

                0 0 12px rgba(79,107,255,.35);

        }
        .trusted-track span{

            font-size:32px;

            font-weight:700;

            color:#70798d;

            transition:.35s;

            cursor:pointer;

        }
        .trusted-track{

            display:flex;

            width:max-content;

            gap:80px;

            animation:marquee 20s linear infinite;

        }
        .trusted-mask{

            overflow:hidden;

            margin-left:170px;

        }
        .trusted-title{

            position:absolute;

            left:35px;

            top:50%;

            transform:translateY(-50%);

            display:flex;

            align-items:center;

            gap:10px;

            font-weight:700;

            color:#394150;

            z-index:5;

        }
        @keyframes lightMove{

            from{

                transform:translateX(-100%);

            }

            to{

                transform:translateX(100%);

            }

        }
        .trusted::before{

            content:"";

            position:absolute;

            inset:0;

            background:
                linear-gradient(
                    110deg,
                    transparent 0%,
                    rgba(91,124,255,.08) 35%,
                    rgba(0,210,255,.10) 55%,
                    transparent 80%
                );

            animation:lightMove 8s linear infinite;

        }
        .trusted{

            position:relative;

            padding:45px;

            border-radius:70px;

            overflow:hidden;

            background:rgba(255,255,255,.65);

            backdrop-filter:blur(20px);

            border:1px solid rgba(255,255,255,.8);

            box-shadow:
                0 20px 45px rgba(70,90,255,.08);

        }
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
            background:var(--accent-soft); color:var(--accent-text);
            font-size:12.5px; font-weight:700; padding:7px 13px; border-radius:99px;
            margin-bottom:22px;
        }
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
        .fc-salary{font-weight:700; color:var(--accent-text); font-size:13px;}
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
        .stats{padding:64px 0;}
        .stats-grid{display:grid; grid-template-columns:repeat(4,1fr); gap:22px;}
        .stat-box{
            background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:24px;
        }
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
        .audience-kicker{font-size:12px; font-weight:700; color:var(--accent-text); text-transform:uppercase; letter-spacing:.6px; margin-bottom:10px;}
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
            /* ---------- перенос длинных подписей ----------
        Таджикские строки заметно длиннее русских: «Зачтено» превращается
        в «Ба ҳисоб гирифта шуд», «Email» — в «Почтаи электронӣ». Без этого
        подпись выходит за рамку кнопки или карточки. break-word срабатывает
        только когда слово физически не помещается, поэтому вёрстку
        на русском не трогает. */
        h1, h2, h3, h4, p, li, td, th, label, button, a,
        .btn, .chip, .tag, .badge, .pill {
        overflow-wrap: break-word;
        }
</style>
</head>
<body>

@include('public.partials.header')


@yield('content')


@include('public.partials.footer')

<script>
    function setTheme(t) {
        document.documentElement.setAttribute('data-theme', t);
        document.getElementById('lightBtn')?.classList.toggle('active', t === 'light');
        document.getElementById('darkBtn')?.classList.toggle('active', t === 'dark');
    }

    // тема из настроек: «как в системе» смотрит на оформление ОС
    (function () {
        const saved = @json(auth()->check() ? (auth()->user()->theme ?: 'light') : session('theme', 'light'));
        const dark = saved === 'dark'
            || (saved === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

        setTheme(dark ? 'dark' : 'light');
    })();


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
