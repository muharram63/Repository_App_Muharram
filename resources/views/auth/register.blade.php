<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Регистрация — Workio') }}</title>
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

        .auth-wrap{
            display:grid; grid-template-columns:1fr 1fr;
            min-height:calc(100vh - 80px);
        }
        .auth-form-side{
            display:flex; align-items:center; justify-content:center;
            padding:40px 32px;
        }
        .auth-card{width:100%; max-width:420px;}
        .auth-eyebrow{
            display:inline-flex; align-items:center; gap:8px;
            background:var(--accent-soft); color:var(--accent-ink);
            font-size:12px; font-weight:700; padding:6px 12px; border-radius:99px;
            margin-bottom:20px;
        }
        :root[data-theme="dark"] .auth-eyebrow{color:var(--accent);}
        .auth-card h1{font-size:28px; font-weight:800; letter-spacing:-.6px; margin-bottom:8px;}
        .auth-sub{font-size:14.5px; color:var(--text-muted); margin-bottom:26px;}
        .auth-sub a{color:var(--accent-ink); font-weight:600;}
        :root[data-theme="dark"] .auth-sub a{color:var(--accent);}

        /* role switch */
        .role-switch{
            display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:24px;
        }
        .role-option{display:none;}
        .role-card{
            display:flex; flex-direction:column; gap:8px; padding:14px;
            border:1.5px solid var(--border); border-radius:12px; cursor:pointer;
            background:var(--surface); transition:.15s;
        }
        .role-card svg{width:19px; height:19px; color:var(--text-muted);}
        .role-card .role-name{font-weight:700; font-size:13.5px;}
        .role-card .role-desc{font-size:12px; color:var(--text-muted); line-height:1.4;}
        .role-option:checked + .role-card{
            border-color:var(--accent); background:var(--accent-soft);
            box-shadow:0 0 0 1px var(--accent);
        }
        .role-option:checked + .role-card svg,
        .role-option:checked + .role-card .role-name{color:var(--accent-ink);}
        :root[data-theme="dark"] .role-option:checked + .role-card svg,
        :root[data-theme="dark"] .role-option:checked + .role-card .role-name{color:var(--accent);}

        .field-row-2{display:grid; grid-template-columns:1fr 1fr; gap:14px;}
        .field-group{margin-bottom:16px;}
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
            background:none; border:none; cursor:pointer; color:var(--text-muted); display:flex; padding:0;
        }
        .toggle-pass svg{position:static; width:17px; height:17px;}
        .toggle-pass:hover{color:var(--text);}
        /* место под глазок, иначе длинный пароль уезжает под иконку */
        .field-input.has-toggle{padding-right:42px;}

        .pass-strength{display:flex; gap:5px; margin-top:8px;}
        .pass-strength-bar{height:3px; flex:1; border-radius:99px; background:var(--border); transition:.2s;}
        .pass-hint{font-size:11.5px; color:var(--text-muted); margin-top:6px;}

        .terms-row{
            display:flex; gap:10px; align-items:flex-start; margin-bottom:22px; font-size:13px; color:var(--text-muted); line-height:1.5;
        }
        .terms-row input{accent-color:var(--accent); width:15px; height:15px; margin-top:2px; cursor:pointer; flex-shrink:0;}
        .terms-row a{color:var(--accent-ink); font-weight:600;}
        :root[data-theme="dark"] .terms-row a{color:var(--accent);}

        .btn-primary{
            width:100%; padding:13px; border-radius:10px; font-size:14.5px; font-weight:700;
            color:#fff; background:var(--accent); border:none; cursor:pointer;
            box-shadow:0 10px 22px -10px var(--accent); transition:.15s;
        }
        .btn-primary:hover{background:var(--accent-ink);}

        .divider{
            display:flex; align-items:center; gap:14px; margin:24px 0;
            font-size:12.5px; color:var(--text-muted);
        }
        .divider::before, .divider::after{content:''; flex:1; height:1px; background:var(--border);}

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
        .error-banner ul{padding-left:18px; margin-top:2px;}

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
        .visual-top{position:relative; z-index:1; color:#fff;}
        .visual-top h2{font-size:24px; font-weight:800; max-width:360px; line-height:1.35; margin-bottom:10px;}
        .visual-top p{font-size:14px; color:rgba(255,255,255,.8); max-width:360px; line-height:1.6;}

        .perk-list{display:flex; flex-direction:column; gap:16px; position:relative; z-index:1;}
        .perk-item{display:flex; gap:12px; align-items:flex-start;}
        .perk-icn{
            width:34px; height:34px; border-radius:10px; background:rgba(255,255,255,.16);
            display:flex; align-items:center; justify-content:center; flex-shrink:0; color:#fff;
        }
        .perk-icn svg{width:16px; height:16px;}
        .perk-text b{display:block; color:#fff; font-size:14px; font-weight:700; margin-bottom:2px;}
        .perk-text span{color:rgba(255,255,255,.78); font-size:12.5px; line-height:1.5;}

        .visual-card{
            background:rgba(255,255,255,.12); backdrop-filter:blur(8px);
            border:1px solid rgba(255,255,255,.18); border-radius:16px; padding:18px 20px;
            position:relative; z-index:1; display:flex; align-items:center; gap:14px;
        }
        .visual-avatar-stack{display:flex;}
        .visual-avatar-stack .av{
            width:32px; height:32px; border-radius:50%; border:2px solid var(--accent-ink);
            background:rgba(255,255,255,.85); margin-left:-10px; display:flex; align-items:center; justify-content:center;
            font-size:11px; font-weight:700; color:var(--accent-ink);
        }
        .visual-avatar-stack .av:first-child{margin-left:0;}
        .visual-card-text{color:#fff; font-size:13px; font-weight:600;}
        .visual-card-text span{display:block; color:rgba(255,255,255,.75); font-weight:500; font-size:11.5px; margin-top:2px;}

        @media (max-width:900px){
            .auth-wrap{grid-template-columns:1fr;}
            .auth-visual-side{display:none;}
            .field-row-2{grid-template-columns:1fr;}
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
            <div class="auth-eyebrow">{{ __('Бесплатно и без карты') }}</div>
            <h1>{{ __('Создайте аккаунт') }}</h1>
            <p class="auth-sub">{{ __('Уже есть аккаунт?') }} <a href="{{route('login')}}">{{ __('Войти') }}</a></p>

            @if ($errors->any())
                <div class="error-banner">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}">
                @csrf


                <div class="field-row-2">
                    <div class="field-group">
                        <label class="field-label">{{ __('ФИО') }}</label>
                        <div class="field-input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <input type="text" name="name" class="field-input" placeholder="{{ __('Введите свое ФИО') }}" style="width: 66vh;">
                        </div>
                    </div>
                   </div>

                <div class="field-group">
                    <label class="field-label">E-mail</label>
                    <div class="field-input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 6c0-1.1-.9-2-2-2H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6z"/><path d="M22 6l-10 7L2 6"/></svg>
                        <input type="email" name="email" class="field-input" placeholder="{{ __('Введите свой @email.com') }}" required>
                    </div>
                </div>

                <div class="field-group">
                    <label class="field-label">{{ __('Роль') }}</label>

                    <select name="role" class="field-input">
                        <option value="applicant">{{ __('Соискатель') }}</option>
                        <option value="employer">{{ __('Работадель') }}</option>
                    </select>
                </div>

                <div class="field-group">
                    <label class="field-label">{{ __('Пароль') }}</label>
                    <div class="field-input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" name="password" id="passwordField" class="field-input has-toggle" placeholder="{{ __('Минимум 8 символов') }}" required oninput="checkStrength(this.value)">
                        <button type="button" class="toggle-pass" onclick="togglePass(this)" aria-label="{{ __('Показать пароль') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-top: 2.4vh;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="pass-strength">
                        <div class="pass-strength-bar" id="bar1"></div>
                        <div class="pass-strength-bar" id="bar2"></div>
                        <div class="pass-strength-bar" id="bar3"></div>
                    </div>
                    <div class="pass-hint" id="passHint">{{ __('Используйте буквы, цифры и спецсимволы') }}</div>
                </div>

                <div class="field-group">
                    <label class="field-label">{{ __('Подтвердите пароль') }}</label>
                    <div class="field-input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" name="password_confirmation" class="field-input has-toggle" placeholder="••••••••" required>
                        <button type="button" class="toggle-pass" onclick="togglePass(this)" aria-label="{{ __('Показать пароль') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-top: 2.4vh;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <label class="terms-row">
                    <input type="checkbox" required>
                    {{ __('Я принимаю') }} <a href="#">{{ __('условия использования') }}</a> {{ __('и') }} <a href="#">{{ __('политику конфиденциальности') }}</a>
                </label>

                <button type="submit" class="btn-primary">{{ __('Создать аккаунт') }}</button>
            </form>

            <div class="divider">{{ __('или зарегистрируйтесь через') }}</div>

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
        <div class="visual-top">
            <h2>{{ __('Начните за пару минут') }}</h2>
            <p>{{ __('Один аккаунт для поиска работы или найма — переключайтесь между ролями в любой момент.') }}</p>
        </div>

        <div class="perk-list">
            <div class="perk-item">
                <div class="perk-icn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>
                <div class="perk-text"><b>{{ __('Бесплатная регистрация') }}</b><span>{{ __('Без скрытых платежей и привязки карты') }}</span></div>
            </div>
            <div class="perk-item">
                <div class="perk-icn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>
                <div class="perk-text"><b>{{ $activeVacancies }} {{ __('активных вакансий') }}</b><span>{{ __('Вакансия публикуется сразу после сохранения') }}</span></div>
            </div>
            <div class="perk-item">
                <div class="perk-icn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>
                <div class="perk-text"><b>{{ $resumesCount }} {{ __('резюме в базе') }}</b><span>{{ __('Отклики, чат и онлайн-собеседования в одном кабинете') }}</span></div>
            </div>
        </div>

        <div class="visual-card">
            <div class="visual-avatar-stack">
                @forelse($latestUsers as $latest)
                    <div class="av" title="{{ $latest->name }}">
                        @if($latest->hasAvatar())
                            <img src="{{ asset($latest->avatar) }}" alt=""
                                 style="width:100%; height:100%; border-radius:inherit; object-fit:cover;">
                        @else
                            {{ $latest->initials(2) }}
                        @endif
                    </div>
                @empty
                    <div class="av">W</div>
                @endforelse
            </div>

            <div class="visual-card-text">
                {{ $totalUser }} {{ __('человек уже с нами') }}
                <span>{{ $applicantsCount }} {{ __('соискателей') }} · {{ $employersCount }} {{ __('компаний') }}</span>
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
    // Глазок стоит у обоих полей пароля, поэтому переключатель работает не по
    // фиксированному id, а по кнопке: поле и иконка берутся из её же обёртки.
    const EYE_OPEN = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    const EYE_OFF = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';

    function togglePass(button) {
        const field = button.parentElement.querySelector('input');
        const icon = button.querySelector('svg');
        if (!field || !icon) return;

        const hidden = field.type === 'password';
        field.type = hidden ? 'text' : 'password';
        icon.innerHTML = hidden ? EYE_OFF : EYE_OPEN;
        button.setAttribute('aria-label', hidden ? @json(__('Скрыть пароль')) : @json(__('Показать пароль')));
    }
    function checkStrength(val) {
        const bars = [document.getElementById('bar1'), document.getElementById('bar2'), document.getElementById('bar3')];
        const hint = document.getElementById('passHint');
        let score = 0;
        if (val.length >= 8) score++;
        if (/[A-Z]/.test(val) && /[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val) && val.length >= 10) score++;

        const colors = ['#E5484D', '#FF8A3D', '#1FAE6E'];
        const labels = ['Слабый пароль', 'Средний пароль', 'Надёжный пароль'];

        bars.forEach((b, i) => {
            b.style.background = i < score ? colors[score - 1] : 'var(--border)';
        });
        hint.textContent = val.length === 0 ? 'Используйте буквы, цифры и спецсимволы' : labels[Math.max(score - 1, 0)];
    }
</script>
</body>
</html>
