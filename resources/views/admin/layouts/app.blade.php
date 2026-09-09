<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Workio — Админ-панель</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap CSS (стили, без JS) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root{
            --blue:#1656D6; --blue-soft:#2F6FEF; --blue-50:#EEF3FE;
            --ink-900:#131B2E; --ink-700:#2C374B; --ink-600:#48546B; --ink-400:#8592A6; --ink-200:#C6CEDA;
            --line:#E4E9F2; --bg:#F5F7FB; --surface:#FFFFFF;
            --green:#12946F; --green-50:#E7F7F1;
            --amber:#C98A12; --amber-50:#FBF3E3;
            --red:#D6431E; --red-50:#FBEAE5;
            --sidebar-w:248px; --radius:14px;
            --shadow-sm:0 1px 2px rgba(19,27,46,0.05);
        }
        *{box-sizing:border-box;}
        html,body{height:100%;}
        body{margin:0; background:var(--bg); color:var(--ink-700); font-family:"Inter",-apple-system,BlinkMacSystemFont,sans-serif; font-size:14px; -webkit-font-smoothing:antialiased;}
        .disp{font-family:"Manrope","Inter",sans-serif; letter-spacing:-0.01em;}

        /* hide all the radios that drive tabs/filters — CSS-only interactivity, no JS */
        .tab-radio, .filter-radio{position:absolute; opacity:0; width:0; height:0; pointer-events:none;}

        .app{display:flex; min-height:100vh;}

        .sidebar{width:var(--sidebar-w); flex-shrink:0; background:var(--ink-900); color:#fff; display:flex; flex-direction:column; padding:22px 16px; position:sticky; top:0; height:100vh;}
        .brand{display:flex; align-items:center; gap:10px; padding:4px 8px 26px 8px;}
        .brand-icon{width:34px; height:34px; border-radius:10px; background:linear-gradient(145deg, var(--blue-soft), var(--blue)); display:flex; align-items:center; justify-content:center; font-size:15px; color:#fff; flex-shrink:0; box-shadow:0 4px 10px rgba(22,86,214,0.35);}
        .brand-name{font-weight:800; font-size:17px; color:#fff;}

        .nav{display:flex; flex-direction:column; gap:2px; flex:1; overflow-y:auto;}
        .nav-item{display:flex; align-items:center; gap:11px; text-decoration:none; cursor:pointer; color:#A7B0C4; font-size:13.5px; font-weight:500; padding:10px 12px; border-radius:9px; transition:background .15s ease, color .15s ease;}
        .nav-item i{width:17px; text-align:center; font-size:14px; color:inherit; opacity:.7;}
        .nav-item .label{flex:1;}
        .nav-item:hover{background:rgba(255,255,255,0.06); color:#fff;}
        .nav-badge{background:var(--red); color:#fff; font-size:10.5px; font-weight:700; min-width:18px; height:18px; border-radius:999px; display:flex; align-items:center; justify-content:center; padding:0 5px;}

        .status-box{margin-top:14px; font-size:12px; color:#8FE3C1; display:flex; align-items:center; gap:7px; padding:10px 12px; border-radius:9px; background:rgba(18,148,111,0.14);}
        .status-box i{font-size:8px; color:#25C08A;}

        .main{flex:1; min-width:0; display:flex; flex-direction:column;}
        header{height:64px; flex-shrink:0; background:var(--surface); border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; padding:0 24px; position:sticky; top:0; z-index:10;}
        .breadcrumb{font-size:13px; color:var(--ink-400);}
        .breadcrumb b{color:var(--ink-700); font-weight:600;}
        .crumb{display:none;}
        .header-right{display:flex; align-items:center; gap:14px;}
        .header-right .btn.btn-light{background:var(--bg); border:1px solid var(--line); width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:var(--ink-600);}
        .profile{padding:4px 6px; border-radius:10px;}
        .avatar{width:34px; height:34px; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; background:var(--blue) !important;}
        .profile-name{font-weight:600; font-size:13.5px; color:var(--ink-700);}

        main{padding:22px 24px 40px; flex:1;}
        .page{display:none;}

        .section-header{display:flex; align-items:flex-end; justify-content:space-between; flex-wrap:wrap; gap:10px;}
        .section-header h1{font-size:23px; font-weight:800; margin:0; color:var(--ink-900);}
        .section-header .sub{color:var(--ink-400); font-size:13px; margin-top:2px;}

        .card{background:var(--surface); border:1px solid var(--line); border-radius:var(--radius); box-shadow:var(--shadow-sm);}
        .card-header{font-weight:700; font-size:13.5px; color:var(--ink-700); padding:16px 18px; border-bottom:1px solid var(--line);}
        .card-body{padding:18px;}

        .row{display:flex; gap:18px; margin:0 -9px;}
        .row > [class*="col-"]{padding:0 9px; flex:1; min-width:0;}
        .row.mt-3{margin-top:18px;}

        .kpi-row{display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:18px;}
        .kpi{padding:18px;}
        .kpi-top{display:flex; align-items:flex-start; justify-content:space-between;}
        .kpi-label{font-size:12.5px; color:var(--ink-400); font-weight:600; margin-bottom:6px;}
        .kpi-value{font-size:26px; font-weight:800; color:var(--ink-900);}
        .kpi-icon{width:38px; height:38px; border-radius:10px; background:var(--blue-50); color:var(--blue); display:flex; align-items:center; justify-content:center; font-size:15px;}
        .kpi-delta{margin-top:14px; font-size:12.5px; font-weight:600; display:flex; align-items:center; gap:6px;}
        .kpi-delta span{color:var(--ink-400); font-weight:500;}
        .kpi-delta.up{color:var(--green);}
        .kpi-delta.down{color:var(--red);}

        .table{width:100%; border-collapse:collapse; margin:0; font-size:13.5px;}
        .table thead th{text-align:left; font-size:11.5px; text-transform:uppercase; letter-spacing:.04em; color:var(--ink-400); font-weight:700; padding:0 12px 10px; border-bottom:1px solid var(--line);}
        .table tbody td{padding:13px 12px; border-bottom:1px solid var(--line); vertical-align:middle; color:var(--ink-600);}
        .table tbody tr:last-child td{border-bottom:none;}
        .table tbody tr:hover{background:var(--bg);}
        .bold{font-weight:700; color:var(--ink-900);}
        .sub{color:var(--ink-600);}
        .muted{color:var(--ink-400); font-size:12.5px;}

        .badge{font-size:11.5px; font-weight:700; padding:5px 10px; border-radius:999px; display:inline-flex; align-items:center; gap:5px; line-height:1;}
        .badge.pending{background:var(--amber-50); color:var(--amber);}
        .badge.approved, .badge.active, .badge.resolved, .badge.public{background:var(--green-50); color:var(--green);}
        .badge.rejected, .badge.blocked{background:var(--red-50); color:var(--red);}
        .badge.open{background:var(--red-50); color:var(--red);}
        .badge.hidden{background:var(--bg); color:var(--ink-400); border:1px solid var(--line);}

        .filters{display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px;}
        .pill{border:1px solid var(--line); background:var(--surface); color:var(--ink-600); font-size:12.5px; font-weight:600; padding:7px 14px; border-radius:999px; cursor:pointer; user-select:none; transition:all .15s ease; display:inline-block;}
        .pill:hover{border-color:var(--blue-soft);}

        .actions-cell{display:flex; gap:6px;}
        .icon-btn{width:30px; height:30px; border-radius:8px; border:1px solid var(--line); background:var(--surface); color:var(--ink-600); display:inline-flex; align-items:center; justify-content:center; font-size:12.5px;}

        .company-grid{display:grid; grid-template-columns:repeat(auto-fill, minmax(240px,1fr)); gap:16px;}
        .company-card{padding:18px;}
        .company-card .top{display:flex; align-items:flex-start; justify-content:space-between; gap:10px; margin-bottom:16px;}
        .company-name{font-weight:700; font-size:14.5px; color:var(--ink-900);}
        .company-industry{font-size:12px; color:var(--ink-400); margin-top:2px;}
        .tag{font-size:10.5px; font-weight:700; padding:4px 9px; border-radius:999px; white-space:nowrap;}
        .tag.verified{background:var(--green-50); color:var(--green);}
        .tag.unverified{background:var(--bg); color:var(--ink-400); border:1px solid var(--line);}
        .company-stats{display:flex; gap:24px; border-top:1px solid var(--line); padding-top:14px;}
        .stat-label{font-size:11px; color:var(--ink-400); font-weight:600; margin-bottom:4px;}
        .stat-value{font-size:19px; font-weight:800; color:var(--ink-900);}
        .stat-value.danger{color:var(--red);}

        .analytics-grid{display:grid; grid-template-columns:1fr 1fr; gap:18px;}
        .analytics-grid .card.full{grid-column:1 / -1;}

        /* ---- static charts, pure HTML/CSS/SVG, no JS ---- */
        .chart-legend{display:flex; gap:16px; flex-wrap:wrap; margin-top:12px; font-size:12px; color:var(--ink-600);}
        .chart-legend .dot{width:9px; height:9px; border-radius:2px; display:inline-block; margin-right:6px;}
        .chart-x-labels{display:flex; justify-content:space-between; font-size:11px; color:var(--ink-400); padding:0 4px; margin-top:2px;}

        .donut-wrap{display:flex; align-items:center; gap:26px; flex-wrap:wrap;}
        .donut{
            width:150px; height:150px; border-radius:50%; flex-shrink:0; position:relative;
            background:conic-gradient(
                var(--blue) 0% 32%,
                var(--blue-soft) 32% 54%,
                #6FA0F5 54% 70%,
                #9CBEF7 70% 82%,
                var(--amber) 82% 92%,
                var(--ink-400) 92% 100%
            );
        }
        .donut::after{content:""; position:absolute; inset:24%; background:var(--surface); border-radius:50%;}
        .donut-legend{display:flex; flex-direction:column; gap:9px; font-size:12.5px; color:var(--ink-600);}
        .donut-legend .row-l{display:flex; align-items:center; gap:8px;}
        .donut-legend .pct{margin-left:auto; font-weight:700; color:var(--ink-900);}

        .bars{display:flex; align-items:flex-end; gap:16px; height:190px; padding-top:10px;}
        .bars .col{flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; height:100%;}
        .bars .col .bar{width:100%; max-width:38px; background:linear-gradient(180deg, var(--blue-soft), var(--blue)); border-radius:6px 6px 0 0;}
        .bars .col .val{font-size:11px; color:var(--ink-400); margin-bottom:6px;}
        .bars .col .lbl{font-size:11.5px; color:var(--ink-400); margin-top:8px;}

        .hbars{display:flex; flex-direction:column; gap:12px;}
        .hbars .row-h{display:grid; grid-template-columns:110px 1fr 40px; align-items:center; gap:10px;}
        .hbars .row-h .name{font-size:12.5px; color:var(--ink-600); font-weight:600;}
        .hbars .track{background:var(--bg); border-radius:999px; height:12px; overflow:hidden;}
        .hbars .fill{height:100%; border-radius:999px; background:var(--blue);}
        .hbars .num{font-size:12.5px; color:var(--ink-900); font-weight:700; text-align:right;}

        /* ---------------- CSS-only tab switching (radio hack) ---------------- */
        #tab-dashboard:checked ~ .main #page-dashboard,
        #tab-vacancies:checked ~ .main #page-vacancies,
        #tab-users:checked ~ .main #page-users,
        #tab-companies:checked ~ .main #page-companies,
        #tab-resumes:checked ~ .main #page-resumes,
        #tab-analytics:checked ~ .main #page-analytics,
        #tab-complaints:checked ~ .main #page-complaints{ display:block; }

        #tab-dashboard:checked ~ .main .crumb-dashboard,
        #tab-vacancies:checked ~ .main .crumb-vacancies,
        #tab-users:checked ~ .main .crumb-users,
        #tab-companies:checked ~ .main .crumb-companies,
        #tab-resumes:checked ~ .main .crumb-resumes,
        #tab-analytics:checked ~ .main .crumb-analytics,
        #tab-complaints:checked ~ .main .crumb-complaints{ display:inline; }

        #tab-dashboard:checked ~ .sidebar label[for="tab-dashboard"],
        #tab-vacancies:checked ~ .sidebar label[for="tab-vacancies"],
        #tab-users:checked ~ .sidebar label[for="tab-users"],
        #tab-companies:checked ~ .sidebar label[for="tab-companies"],
        #tab-resumes:checked ~ .sidebar label[for="tab-resumes"],
        #tab-analytics:checked ~ .sidebar label[for="tab-analytics"],
        #tab-complaints:checked ~ .sidebar label[for="tab-complaints"]{
            background:var(--blue); color:#fff;
        }
        #tab-dashboard:checked ~ .sidebar label[for="tab-dashboard"] i,
        #tab-vacancies:checked ~ .sidebar label[for="tab-vacancies"] i,
        #tab-users:checked ~ .sidebar label[for="tab-users"] i,
        #tab-companies:checked ~ .sidebar label[for="tab-companies"] i,
        #tab-resumes:checked ~ .sidebar label[for="tab-resumes"] i,
        #tab-analytics:checked ~ .sidebar label[for="tab-analytics"] i,
        #tab-complaints:checked ~ .sidebar label[for="tab-complaints"] i{ opacity:1; }

        /* ---------------- CSS-only filters (radio hack) ---------------- */
        #vacfilter-pending:checked ~ .card tr.row-approved,
        #vacfilter-pending:checked ~ .card tr.row-rejected{ display:none; }
        #vacfilter-approved:checked ~ .card tr.row-pending,
        #vacfilter-approved:checked ~ .card tr.row-rejected{ display:none; }
        #vacfilter-rejected:checked ~ .card tr.row-pending,
        #vacfilter-rejected:checked ~ .card tr.row-approved{ display:none; }

        #vacfilter-all:checked ~ .filters label[for="vacfilter-all"],
        #vacfilter-pending:checked ~ .filters label[for="vacfilter-pending"],
        #vacfilter-approved:checked ~ .filters label[for="vacfilter-approved"],
        #vacfilter-rejected:checked ~ .filters label[for="vacfilter-rejected"]{ background:var(--blue); border-color:var(--blue); color:#fff; }

        #userfilter-applicant:checked ~ .card tr.row-employer{ display:none; }
        #userfilter-employer:checked ~ .card tr.row-applicant{ display:none; }
        #userfilter-all:checked ~ .filters label[for="userfilter-all"],
        #userfilter-applicant:checked ~ .filters label[for="userfilter-applicant"],
        #userfilter-employer:checked ~ .filters label[for="userfilter-employer"]{ background:var(--blue); border-color:var(--blue); color:#fff; }

        #compfilter-open:checked ~ .card tr.row-resolved{ display:none; }
        #compfilter-resolved:checked ~ .card tr.row-open{ display:none; }
        #compfilter-all:checked ~ .filters label[for="compfilter-all"],
        #compfilter-open:checked ~ .filters label[for="compfilter-open"],
        #compfilter-resolved:checked ~ .filters label[for="compfilter-resolved"]{ background:var(--blue); border-color:var(--blue); color:#fff; }

        @media (max-width:1100px){
            .kpi-row{grid-template-columns:repeat(2,1fr);}
            .row{flex-direction:column;}
            .analytics-grid{grid-template-columns:1fr;}
        }
        @media (max-width:800px){
            .sidebar{position:fixed; left:-260px; z-index:50;}
            .kpi-row{grid-template-columns:1fr;}
        }
    </style>
</head>
<body>

<div class="app">
    <!-- CSS-only tab state (no JS) -->
    <input type="radio" name="tabs" id="tab-dashboard" class="tab-radio" checked>
    <input type="radio" name="tabs" id="tab-vacancies" class="tab-radio">
    <input type="radio" name="tabs" id="tab-users" class="tab-radio">
    <input type="radio" name="tabs" id="tab-companies" class="tab-radio">
    <input type="radio" name="tabs" id="tab-resumes" class="tab-radio">
    <input type="radio" name="tabs" id="tab-analytics" class="tab-radio">
    <input type="radio" name="tabs" id="tab-complaints" class="tab-radio">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="brand">
            <a href="{{route('public.home')}}" class="workio-logo" style="display:flex; align-items:center; gap:12px; text-decoration:none;">
                @include('partials.brand-mark', ['brandSize' => 52])
                <div style="line-height:1.1;">
                    <div style="font-family: Arial, sans-serif; font-size: 24px; font-weight: 700; color:#111827;">
                        <a href="{{route('public.home')}}"> Work<span style="color:#4F46E5;">io</span></a>
                    </div>
                    <div style="font-family: Arial, sans-serif; font-size: 12px; color:white;">
                        работа без лишних шагов
                    </div>
                </div>
            </a>
        </div>

        <nav class="nav">
            <a class="nav-item" href="{{ route('superadmin.dashboard') }}"><i class="fa-solid fa-gauge-high"></i><span class="label">Обзор</span></a>
            <a class="nav-item" href="{{route('superadmin.vacancy')}}"><i class="fa-solid fa-briefcase"></i><span class="label">Вакансии</span></a>
            <a class="nav-item" href="{{route('superadmin.users')}}"><i class="fa-solid fa-users"></i><span class="label">Пользователи</span></a>
            <a class="nav-item" href="{{ route('superadmin.applicants') }}"><i class="fa-solid fa-user-tie"></i><span class="label">Соискатели</span></a>
            <a class="nav-item" href="{{ route('superadmin.companies') }}"><i class="fa-solid fa-building"></i><span class="label">Компании</span></a>
            <a class="nav-item" href="{{ route('superadmin.resumes') }}"><i class="fa-solid fa-file-lines"></i><span class="label">Резюме</span></a>
            <a class="nav-item" href="{{ route('superadmin.responses') }}"><i class="fa-solid fa-inbox"></i><span class="label">Отклики</span></a>
            <a class="nav-item" href="{{ route('superadmin.analytics') }}"><i class="fa-solid fa-chart-simple"></i><span class="label">Аналитика</span></a>
            @php($newComplaints = \App\Models\Complaint::where('status', 'new')->count())
            <a class="nav-item" href="{{ route('superadmin.complaints') }}"><i class="fa-solid fa-flag"></i><span class="label">Жалобы</span><span data-live="nav-complaints" data-live-hide-zero style="margin-left:auto; background:#dc2626; color:#fff; font-size:11px; font-weight:700; border-radius:999px; padding:2px 7px;" @if(! $newComplaints) hidden @endif>{{ $newComplaints }}</span></a>
            @php($newAdminComments = \App\Models\AdminComment::whereIn('status', ['new', 'in_review'])->count())
            <a class="nav-item" href="{{ route('superadmin.comments') }}"><i class="fa-solid fa-comment-dots"></i><span class="label">Комментарии</span><span data-live="nav-comments" data-live-hide-zero style="margin-left:auto; background:#1656D6; color:#fff; font-size:11px; font-weight:700; border-radius:999px; padding:2px 7px;" @if(! $newAdminComments) hidden @endif>{{ $newAdminComments }}</span></a>
        </nav>

<form action="{{route('logout')}}" method="post">
    @csrf
     <button type="submit" class="btn btn-danger"> Выйти из аккаунта</button>
</form>
    </aside>

    <div>
        <header style="width: 177vh;">

            <div class="breadcrumb">
                Админ-панель <i class="fa-solid fa-chevron-right" style="font-size:10px; margin:0 10px;margin-top: 0.7vh;"></i>Пользователи
                <b class="crumb crumb-dashboard">Обзор</b>
                <b class="crumb crumb-vacancies">Вакансии</b>
                <b class="crumb crumb-users">Пользователи</b>
                <b class="crumb crumb-applicants">Соискатели</b>
                <b class="crumb crumb-companies">Компании</b>
                <b class="crumb crumb-resumes">Резюме</b>
                <b class="crumb crumb-responses">Отклики</b>
                <b class="crumb crumb-analytics">Аналитика</b>
                <b class="crumb crumb-complaints">Жалобы</b>
                <b class="crumb crumb-comments">Комментарии</b>
            </div>

            <div class="header-right">

                <div class="profile d-flex align-items-center">
                    <div class="avatar bg-primary text-white rounded-circle p-2">А</div>

                    <div class="profile-name ms-2">Админ</div>

                </div>

            </div>

            <!-- Main -->



        </header>
        @yield('main')

    </div>
</div>

@include('partials.live-counters', ['liveUrl' => route('superadmin.live.counters')])

</body>
</html>
