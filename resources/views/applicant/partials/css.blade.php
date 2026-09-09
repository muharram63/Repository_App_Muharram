{{--Личный кабинет--}}


<style>
    :root{
        --indigo-600:#4F46E5;
        --indigo-700:#4338CA;
        --indigo-50:#EEF0FF;
        --indigo-100:#E0E4FF;
        --ink:#111827;
        --gray-500:#6B7280;
        --gray-300:#D1D5DB;
        --gray-200:#E5E7EB;
        --gray-100:#F3F4F6;
        --bg:#F8F9FC;
        --white:#FFFFFF;
        --green:#16A34A;
        --radius:12px;
    }

    *{ box-sizing:border-box; }
    html,body{ margin:0; padding:0; }
    body{
        font-family:"Inter","Segoe UI",Arial,sans-serif;
        background:
            radial-gradient(1100px 600px at 85% -10%, rgba(79,70,229,0.10), transparent 60%),
            radial-gradient(900px 500px at -10% 20%, rgba(99,102,241,0.07), transparent 55%),
            var(--bg);
        color:var(--ink);
        -webkit-font-smoothing:antialiased;
    }
    a{ text-decoration:none; color:inherit; }
    button{ font-family:inherit; }

    /* ---------- layout ---------- */
    .app{
        display:grid;
        grid-template-columns:260px 1fr;
        min-height:100vh;
    }

    /* ---------- sidebar ---------- */
    .sidebar{
        background:var(--white);
        border-right:1px solid var(--gray-200);
        padding:0 16px;
        display:flex;
        flex-direction:column;
        gap:8px;
        position:sticky;
        top:0;
        height:100vh;
        /* пунктов меню стало больше, чем помещается на экран — даём прокрутку */
        overflow-y:auto;
        overscroll-behavior:contain;
        scrollbar-width:thin;
        scrollbar-color:var(--gray-300) transparent;
    }
    .sidebar::-webkit-scrollbar{ width:8px; }
    .sidebar::-webkit-scrollbar-track{ background:transparent; }
    .sidebar::-webkit-scrollbar-thumb{ background:var(--gray-200); border-radius:999px; }
    .sidebar::-webkit-scrollbar-thumb:hover{ background:var(--gray-300); }
    .brand{
        display:flex;
        align-items:center;
        gap:10px;
        padding:24px 8px 20px 8px;
        position:sticky;
        top:0;
        z-index:2;
        background:var(--white);
        margin-bottom:8px;
        border-bottom:1px solid var(--gray-200);
    }
    .brand-logo{
        width:34px; height:34px;
        background:var(--indigo-600);
        border-radius:9px;
        color:#fff;
        display:flex;
        align-items:center;
        justify-content:center;
        font-weight:700;
        font-size:14px;
        flex-shrink:0;
    }
    .brand-name{ font-weight:700; font-size:16px; }
    .brand-name span{ color:var(--indigo-600); }

    .nav-group-label{
        font-size:11px;
        font-weight:600;
        letter-spacing:.06em;
        text-transform:uppercase;
        color:var(--gray-500);
        padding:16px 12px 4px;
    }

    .nav-item{
        display:flex;
        align-items:center;
        gap:12px;
        padding:10px 12px;
        border-radius:10px;
        color:var(--gray-500);
        font-size:14px;
        font-weight:500;
        cursor:pointer;
        transition:background .15s ease, color .15s ease;
    }
    .nav-item:hover{ background:var(--gray-100); color:var(--ink); }
    .nav-item.active{ background:var(--indigo-50); color:var(--indigo-700); font-weight:600; }
    .nav-item .icon{
        width:18px; height:18px;
        flex-shrink:0;
        display:flex; align-items:center; justify-content:center;
    }
    .nav-item svg{ width:18px; height:18px; }

    .sidebar-footer{
        margin-top:auto;
        padding:16px 0 24px;
        position:sticky;
        bottom:0;
        background:var(--white);
        /* закрашиваем зазор flex-gap над закреплённой кнопкой выхода */
        box-shadow:0 -8px 0 var(--white);
        border-top:1px solid var(--gray-200);
    }
    .logout-btn{
        display:flex;
        align-items:center;
        gap:10px;
        padding:10px 12px;
        border-radius:10px;
        color:#DC2626;
        font-size:14px;
        font-weight:500;
        cursor:pointer;
        background:none;
        border:none;
        width:100%;
        text-align:left;
    }
    .logout-btn:hover{ background:#FEF2F2; }

    /* ---------- main content ---------- */
    .main{
        padding:28px 36px 60px;
        max-width:1100px;
        width:100%;
    }

    .topbar{
        display:flex;
        align-items:center;
        justify-content:flex-start;
        gap:14px;
        margin-bottom:28px;
    }
    /* заголовок слева, колокольчик и карточка пользователя — справа */
    .topbar > :first-child{margin-right:auto;}
    .topbar h1{
        font-size:22px;
        margin:0 0 4px;
    }
    .topbar p{
        margin:0;
        color:var(--gray-500);
        font-size:14px;
    }
    .user-chip{
        display:flex;
        align-items:center;
        gap:10px;
        background:var(--white);
        border:1px solid var(--gray-200);
        padding:6px 14px 6px 6px;
        border-radius:999px;
    }
    .user-chip .avatar{
        width:32px; height:32px;
        border-radius:50%;
        background:var(--indigo-600);
        color:#fff;
        display:flex; align-items:center; justify-content:center;
        font-size:13px; font-weight:600;
    }
    .user-chip .name{ font-size:14px; font-weight:600; }
    .user-chip .role{ font-size:12px; color:var(--gray-500); }

    /* ---------- cards / stats ---------- */
    .stat-row{
        display:grid;
        grid-template-columns:repeat(3,1fr);
        gap:16px;
        margin-bottom:28px;
    }
    .stat-card{
        background:var(--white);
        border:1px solid var(--gray-200);
        border-radius:var(--radius);
        padding:18px 20px;
    }
    .stat-card .label{ font-size:13px; color:var(--gray-500); margin-bottom:8px; }
    .stat-card .value{ font-size:26px; font-weight:700; }
    .stat-card .trend{ font-size:12px; color:var(--green); margin-top:4px; }

    .card{
        background:var(--white);
        border:1px solid var(--gray-200);
        border-radius:var(--radius);
        padding:24px;
        margin-bottom:20px;
    }
    .card-header{
        display:flex;
        align-items:center;
        justify-content:space-between;
        margin-bottom:18px;
    }
    .card-header h2{ font-size:16px; margin:0; }
    .card-header .hint{ font-size:13px; color:var(--gray-500); }

    /* ---------- buttons ---------- */
    .btn{
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:9px 18px;
        border-radius:9px;
        font-size:14px;
        font-weight:600;
        cursor:pointer;
        border:1px solid transparent;
    }
    .btn-primary{ background:var(--indigo-600); color:#fff; }
    .btn-primary:hover{ background:var(--indigo-700); }
    .btn-secondary{ background:var(--white); color:var(--ink); border-color:var(--gray-300); }
    .btn-secondary:hover{ background:var(--gray-100); }

    /* ---------- form ---------- */
    .form-grid{
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:16px 20px;
    }
    .form-grid .full{ grid-column:1 / -1; }
    .field label{
        display:block;
        font-size:13px;
        font-weight:600;
        margin-bottom:6px;
        color:var(--ink);
    }
    .field input,
    .field select,
    .field textarea{
        width:100%;
        padding:10px 12px;
        border:1px solid var(--gray-300);
        border-radius:9px;
        font-size:14px;
        color:var(--ink);
        background:var(--white);
    }
    .field input:focus,
    .field select:focus,
    .field textarea:focus{
        outline:none;
        border-color:var(--indigo-600);
        box-shadow:0 0 0 3px var(--indigo-100);
    }
    .field textarea{ resize:vertical; min-height:90px; }
    .form-actions{
        display:flex;
        justify-content:flex-end;
        gap:10px;
        margin-top:20px;
    }

    /* ---------- employers table ---------- */
    table{ width:100%; border-collapse:collapse; }
    th{
        text-align:left;
        font-size:12px;
        text-transform:uppercase;
        letter-spacing:.04em;
        color:var(--gray-500);
        padding:10px 12px;
        border-bottom:1px solid var(--gray-200);
    }
    td{
        padding:14px 12px;
        font-size:14px;
        border-bottom:1px solid var(--gray-100);
    }
    tr:last-child td{ border-bottom:none; }
    .badge{
        display:inline-block;
        padding:3px 10px;
        border-radius:999px;
        font-size:12px;
        font-weight:600;
    }
    .badge-active{ background:#DCFCE7; color:#15803D; }
    .badge-pending{ background:#FEF3C7; color:#B45309; }
    .company-cell{ display:flex; align-items:center; gap:10px; }
    .company-logo{
        width:32px; height:32px;
        border-radius:8px;
        background:var(--indigo-50);
        color:var(--indigo-700);
        display:flex; align-items:center; justify-content:center;
        font-weight:700; font-size:13px;
    }

    /* ---------- sections show/hide ---------- */
    .section{ display:none; }
    .section.active{ display:block; }

    @media (max-width:900px){
        .app{ grid-template-columns:1fr; }
        .sidebar{ display:none; }
        .stat-row{ grid-template-columns:1fr; }
        .form-grid{ grid-template-columns:1fr; }
    }
</style>
