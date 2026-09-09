<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Вход — Workio') }}</title>
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
            --good:#1FAE6E;
            --danger:#E5484D;
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
            --good:#36CC8E;
            --danger:#FF6B6F;
            --shadow: 0 16px 40px -16px rgba(0,0,0,.55);
        }
        *{box-sizing:border-box; margin:0; padding:0;}
        body{
            font-family:'Inter', sans-serif;
            background:var(--bg);
            color:var(--text);
            -webkit-font-smoothing:antialiased;
            transition:background .25s ease, color .25s ease;
        }
        h1,h2,.logo-text,.btn-primary{font-family:'Manrope', sans-serif;}
        a{color:inherit; text-decoration:none;}

        /* ===== TOP BAR (minimal) ===== */
        .top-bar{
            display:flex; align-items:center; justify-content:space-between;
            padding:24px 32px;
        }
        .brand{display:flex; align-items:center; gap:10px; font-weight:800; font-size:17px; margin-right:auto; text-decoration:none; color:inherit;}
        .logo-mark{
            width:32px; height:32px; border-radius:9px;
            background:linear-gradient(135deg, var(--accent), var(--accent-ink));
            display:flex; align-items:center; justify-content:center;
            color:#fff; font-size:12px; font-weight:800;
        }
        .logo-text span{color:var(--accent);}
        .theme-toggle{
            display:flex; background:var(--surface-alt); border:1px solid var(--border);
            border-radius:10px; padding:3px;
        }
        .theme-btn{
            border:none; background:none; padding:6px 10px; font-size:12.5px; font-weight:600;
            color:var(--text-muted); border-radius:7px; cursor:pointer; transition:.2s;
        }
        .theme-btn.active{background:var(--surface); color:var(--text); box-shadow:var(--shadow);}

        /* ===== LAYOUT: split screen ===== */
        .auth-wrap{
            display:grid; grid-template-columns:1fr 1fr;
            min-height:calc(100vh - 80px);
        }
        .auth-form-side{
            display:flex; align-items:center; justify-content:center;
            padding:40px 32px;
        }
        .auth-card{width:100%; max-width:400px;}
        .auth-eyebrow{
            display:inline-flex; align-items:center; gap:8px;
            background:var(--accent-soft); color:var(--accent-ink);
            font-size:12px; font-weight:700; padding:6px 12px; border-radius:99px;
            margin-bottom:20px;
        }
        :root[data-theme="dark"] .auth-eyebrow{color:var(--accent);}
        .auth-card h1{font-size:28px; font-weight:800; letter-spacing:-.6px; margin-bottom:8px;}
        .auth-sub{font-size:14.5px; color:var(--text-muted); margin-bottom:30px;}
        .auth-sub a{color:var(--accent-ink); font-weight:600;}
        :root[data-theme="dark"] .auth-sub a{color:var(--accent);}

        .field-group{margin-bottom:18px;}
        .field-label{
            display:block; font-size:13px; font-weight:600; margin-bottom:7px; color:var(--text);
        }
        .field-input-wrap{position:relative;}
        .field-input-wrap svg{
            position:absolute; left:13px; top:50%; transform:translateY(-50%);
            width:17px; height:17px; color:var(--text-muted); pointer-events:none;
        }
        .field-input{
            width:100%; padding:12px 14px 12px 40px; border-radius:10px;
            border:1px solid var(--border); background:var(--surface-alt);
            font-size:14.5px; color:var(--text); outline:none;
            font-family:'Inter'; transition:.15s;
        }
        .field-input:focus{
            border-color:var(--accent); background:var(--surface);
            box-shadow:0 0 0 3px var(--accent-soft);
        }
        .toggle-pass{
            position:absolute; right:13px; top:50%; transform:translateY(-50%);
            background:none; border:none; cursor:pointer; color:var(--text-muted);
            display:flex; padding:0;
        }
        .toggle-pass svg{position:static; width:17px; height:17px;}

        .field-row{display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;}
        .remember-me{display:flex; align-items:center; gap:8px; font-size:13.5px; color:var(--text-muted);}
        .remember-me input{accent-color:var(--accent); width:15px; height:15px; cursor:pointer;}
        .forgot-link{font-size:13.5px; font-weight:600; color:var(--accent-ink);}
        :root[data-theme="dark"] .forgot-link{color:var(--accent);}

        .btn-primary{
            width:100%; padding:13px; border-radius:10px; font-size:14.5px; font-weight:700;
            color:#fff; background:var(--accent); border:none; cursor:pointer;
            box-shadow:0 10px 22px -10px var(--accent); transition:.15s;
        }
        .btn-primary:hover{background:var(--accent-ink);}

        .divider{
            display:flex; align-items:center; gap:14px; margin:26px 0;
            font-size:12.5px; color:var(--text-muted);
        }
        .divider::before, .divider::after{
            content:''; flex:1; height:1px; background:var(--border);
        }

        .social-row{display:flex; gap:12px;}
        .btn-social{
            flex:1; display:flex; align-items:center; justify-content:center; gap:8px;
            padding:11px; border-radius:10px; border:1px solid var(--border);
            background:var(--surface); font-size:13.5px; font-weight:600; color:var(--text);
            cursor:pointer; transition:.15s;
        }
        .btn-social:hover{border-color:var(--accent);}
        .btn-social svg{width:17px; height:17px;}

        .error-banner{
            display:flex; gap:10px; align-items:flex-start;
            background:color-mix(in srgb, var(--danger) 10%, transparent);
            border:1px solid color-mix(in srgb, var(--danger) 30%, transparent);
            color:var(--danger); padding:12px 14px; border-radius:10px;
            font-size:13px; margin-bottom:20px;
        }
        .error-banner svg{width:16px; height:16px; flex-shrink:0; margin-top:1px;}

        /* ===== VISUAL SIDE ===== */
        .auth-visual-side{
            position:relative; overflow:hidden;
            background:linear-gradient(160deg, var(--accent), var(--accent-ink));
            display:flex; flex-direction:column; justify-content:space-between;
            padding:44px;
        }
        .auth-visual-side::before{
            content:''; position:absolute; width:520px; height:520px; border-radius:50%;
            background:rgba(255,255,255,.08); top:-180px; right:-160px;
        }
        .auth-visual-side::after{
            content:''; position:absolute; width:360px; height:360px; border-radius:50%;
            background:rgba(255,255,255,.06); bottom:-140px; left:-100px;
        }
        .visual-quote{color:#fff; max-width:420px; position:relative; z-index:1;}
        .visual-quote .stars{display:flex; gap:3px; margin-bottom:18px;}
        .visual-quote .stars svg{width:16px; height:16px; fill:#FFC857; stroke:none;}
        .visual-quote p{font-size:19px; line-height:1.6; font-weight:600; margin-bottom:18px;}
        .visual-author{display:flex; align-items:center; gap:12px;}
        .visual-avatar{
            width:40px; height:40px; border-radius:50%; background:rgba(255,255,255,.2);
            display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; color:#fff;
        }
        .visual-author-name{font-weight:700; font-size:14px; color:#fff;}
        .visual-author-role{font-size:12.5px; color:rgba(255,255,255,.75);}

        .visual-card{
            background:rgba(255,255,255,.12); backdrop-filter:blur(8px);
            border:1px solid rgba(255,255,255,.18); border-radius:16px; padding:20px;
            position:relative; z-index:1;
        }
        .visual-card-row{display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;}
        .visual-card-row:last-child{margin-bottom:0;}
        .visual-stat-num{font-size:22px; font-weight:800; color:#fff;}
        .visual-stat-lbl{font-size:12px; color:rgba(255,255,255,.75);}

        @media (max-width:900px){
            .auth-wrap{grid-template-columns:1fr;}
            .auth-visual-side{display:none;}
        }
    </style>
</head>
<body>

<div class="top-bar">
    <a href="{{ route('public.home') }}" class="brand">
        @include('partials.brand-mark', ['brandSize' => 32])
        <div class="logo-text">Work<span>io</span></div>
    </a>
    <div class="theme-toggle">
        <button class="theme-btn active" id="lightBtn" onclick="setTheme('light')">☀</button>
        <button class="theme-btn" id="darkBtn" onclick="setTheme('dark')">☾</button>
    </div>
</div>

<div class="auth-wrap">
    <div class="auth-form-side">
        <div class="auth-card">
            <div class="auth-eyebrow">{{ __('С возвращением') }}</div>
            <h1>{{ __('Войдите в аккаунт') }}</h1>
            <p class="auth-sub">{{ __('Ещё нет аккаунта?') }} <a href="register.blade.php">{{ __('Зарегистрируйтесь бесплатно') }}</a></p>

            @if ($errors->any())
                <div class="error-banner">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span>{{ __('Неверный e-mail или пароль. Проверьте данные и попробуйте снова.') }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="field-group">
                    <label class="field-label">E-mail</label>
                    <div class="field-input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z" opacity="0"/><path d="M22 6c0-1.1-.9-2-2-2H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6z"/><path d="M22 6l-10 7L2 6"/></svg>
                        <input type="email" name="email" class="field-input" placeholder="you@company.com" required autofocus>
                    </div>
                </div>

                <div class="field-group">
                    <label class="field-label">{{ __('Пароль') }}</label>
                    <div class="field-input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" name="password" id="passwordField" class="field-input" placeholder="••••••••" required>
                        <button type="button" class="toggle-pass" style="margin-top: 1.5vh;" onclick="togglePass()">
                            <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="field-row">
                    <label class="remember-me">
                        <input type="checkbox" name="remember">
                        {{ __('Запомнить меня') }}
                    </label>
                    <a href="{{ route('password.request') }}" class="forgot-link">{{ __('Забыли пароль?') }}</a>
                </div>

                <button type="submit" class="btn-primary">{{ __('Войти') }}</button>
            </form>

            <div class="divider">{{ __('или войдите через') }}</div>

            <div class="social-row">
                <button class="btn-social">
                    <svg viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.99.66-2.25 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23z"/><path fill="#FBBC05" d="M5.84 14.1A6.6 6.6 0 0 1 5.5 12c0-.73.13-1.44.34-2.1V7.06H2.18A11 11 0 0 0 1 12c0 1.77.43 3.45 1.18 4.94z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1A11 11 0 0 0 2.18 7.06l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38z"/></svg>
                    Google
                </button>
                <button class="btn-social">
                    <svg viewBox="0 0 24 24" fill="#171B2C"><path d="M12 2C6.48 2 2 6.48 2 12c0 4.42 2.87 8.17 6.84 9.5.5.09.68-.22.68-.48v-1.7c-2.78.6-3.37-1.34-3.37-1.34-.45-1.16-1.11-1.46-1.11-1.46-.91-.62.07-.61.07-.61 1 .07 1.53 1.03 1.53 1.03.89 1.53 2.34 1.09 2.91.83.09-.65.35-1.09.63-1.34-2.22-.25-4.56-1.11-4.56-4.94 0-1.09.39-1.98 1.03-2.68-.1-.25-.45-1.27.1-2.65 0 0 .84-.27 2.75 1.02a9.4 9.4 0 0 1 5 0c1.91-1.29 2.75-1.02 2.75-1.02.55 1.38.2 2.4.1 2.65.64.7 1.03 1.59 1.03 2.68 0 3.84-2.34 4.69-4.57 4.93.36.31.68.92.68 1.85V21c0 .27.18.58.69.48A10 10 0 0 0 22 12c0-5.52-4.48-10-10-10z"/></svg>
                    GitHub
                </button>
            </div>
        </div>
    </div>

    <div class="auth-visual-side">
        <div></div>
        <div class="visual-quote">
            <div class="stars">
                <svg viewBox="0 0 24 24"><polygon points="12 2 15 9 22 9.5 17 14.5 18.5 22 12 18 5.5 22 7 14.5 2 9.5 9 9"/></svg>
                <svg viewBox="0 0 24 24"><polygon points="12 2 15 9 22 9.5 17 14.5 18.5 22 12 18 5.5 22 7 14.5 2 9.5 9 9"/></svg>
                <svg viewBox="0 0 24 24"><polygon points="12 2 15 9 22 9.5 17 14.5 18.5 22 12 18 5.5 22 7 14.5 2 9.5 9 9"/></svg>
                <svg viewBox="0 0 24 24"><polygon points="12 2 15 9 22 9.5 17 14.5 18.5 22 12 18 5.5 22 7 14.5 2 9.5 9 9"/></svg>
                <svg viewBox="0 0 24 24"><polygon points="12 2 15 9 22 9.5 17 14.5 18.5 22 12 18 5.5 22 7 14.5 2 9.5 9 9"/></svg>
            </div>
            <p>{{ __('Отклик, переписка и онлайн-собеседование — в одном кабинете. Соискатель заполняет анкету один раз, работодатель видит кандидатов сразу.') }}</p>
            <div class="visual-author">
                <div class="visual-avatar">W</div>
                <div>
                    <div class="visual-author-name">Workio</div>
                    <div class="visual-author-role">{{ $responsesCount }} {{ __('откликов на платформе') }}</div>
                </div>
            </div>
        </div>

        <div class="visual-card">
            <div class="visual-card-row">
                <div>
                    <div class="visual-stat-num">{{ $activeVacancies }}</div>
                    <div class="visual-stat-lbl">{{ __('активных вакансий') }}</div>
                </div>
                <div>
                    <div class="visual-stat-num">{{ $applicantsCount }}</div>
                    <div class="visual-stat-lbl">{{ __('соискателей') }}</div>
                </div>
                <div>
                    <div class="visual-stat-num">{{ $companiesCount }}</div>
                    <div class="visual-stat-lbl">{{ __('компаний') }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function setTheme(t) {
        document.documentElement.setAttribute('data-theme', t);
        document.getElementById('lightBtn').classList.toggle('active', t === 'light');
        document.getElementById('darkBtn').classList.toggle('active', t === 'dark');
    }
    function togglePass() {
        const f = document.getElementById('passwordField');
        const icon = document.getElementById('eyeIcon');
        if (f.type === 'password') {
            f.type = 'text';
            icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
        } else {
            f.type = 'password';
            icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
        }
    }
</script>
</body>
</html>
