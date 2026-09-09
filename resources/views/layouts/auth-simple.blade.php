<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', __('Workio'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root{
            --bg:#F7F8FB; --surface:#FFFFFF; --border:#E7E9F1;
            --text:#171B2C; --text-muted:#6B7184;
            --accent:#3D5AFE; --accent-ink:#2A3FCC; --accent-soft:#EDF0FF;
            --danger:#E5484D; --good:#1FAE6E;
            --shadow:0 16px 40px -16px rgba(23,27,44,.16);
        }
        *{box-sizing:border-box; margin:0; padding:0;}
        body{
            font-family:'Inter', -apple-system, Segoe UI, sans-serif;
            background:var(--bg); color:var(--text);
            min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px;
        }
        .auth-box{
            width:100%; max-width:430px; background:var(--surface);
            border:1px solid var(--border); border-radius:20px;
            box-shadow:var(--shadow); padding:34px;
        }
        .logo{
            display:inline-flex; align-items:center; gap:10px; margin-bottom:22px;
            text-decoration:none; color:inherit;
        }
        .logo-mark{
            width:34px; height:34px; border-radius:10px; background:var(--accent); color:#fff;
            display:flex; align-items:center; justify-content:center;
            font-family:'Manrope', sans-serif; font-weight:800; font-size:16px;
        }
        .logo-text{font-family:'Manrope', sans-serif; font-weight:800; font-size:19px;}
        .logo-text span{color:var(--accent);}

        h1{font-family:'Manrope', sans-serif; font-size:22px; margin-bottom:8px;}
        .lead{font-size:14px; color:var(--text-muted); line-height:1.6; margin-bottom:22px;}

        .field{margin-bottom:16px;}
        .field label{display:block; font-size:13px; font-weight:600; margin-bottom:6px;}
        .field input{
            width:100%; padding:11px 13px; border:1px solid var(--border); border-radius:11px;
            font-family:inherit; font-size:14px; color:var(--text); background:var(--surface);
        }
        .field input:focus{outline:none; border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-soft);}
        .err{font-size:12.5px; color:var(--danger); margin-top:6px;}

        .btn-primary{
            width:100%; padding:12px; border:none; border-radius:11px; cursor:pointer;
            background:var(--accent); color:#fff;
            font-family:'Manrope', sans-serif; font-size:14.5px; font-weight:700;
        }
        .btn-primary:hover{background:var(--accent-ink);}
        .btn-link{
            display:inline-block; margin-top:16px; font-size:13.5px; font-weight:600;
            color:var(--accent); text-decoration:none; background:none; border:none;
            cursor:pointer; font-family:inherit; padding:0;
        }
        .btn-link:hover{text-decoration:underline;}

        .note{
            border-radius:12px; padding:12px 14px; font-size:13.5px; font-weight:600;
            background:#ECFDF5; color:#047857; margin-bottom:18px;
        }
        .row{display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;}
    </style>
</head>
<body>

<div class="auth-box">
    <a href="{{ route('public.home') }}" class="logo">
        @include('partials.brand-mark', ['brandSize' => 34])
        <span class="logo-text">Work<span>io</span></span>
    </a>

    @if(session('status'))
        <div class="note">{{ session('status') }}</div>
    @endif

    <h1>@yield('heading')</h1>
    <p class="lead">@yield('lead')</p>

    @yield('content')
</div>

</body>
</html>
