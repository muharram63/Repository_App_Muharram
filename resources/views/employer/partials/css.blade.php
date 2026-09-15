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
            /* плашки статусов и опасные действия */
            --danger:#DC2626;
            --wash-danger:#FEF2F2;
            --wash-good:#DCFCE7;
            --ink-good:#15803D;
            --wash-warn:#FEF3C7;
            --ink-warn:#B45309;
            /* подсветка фона: на светлой теме она почти незаметна */
            --glow-a:rgba(79,70,229,0.10);
            --glow-b:rgba(99,102,241,0.07);
            --glow-c:transparent;
            --card-glow:none;
            --rail-glow:none;
        }

        *{ box-sizing:border-box; }
        html,body{ margin:0; padding:0; }
        body{
            font-family:"Inter","Segoe UI",Arial,sans-serif;
            background:
                radial-gradient(1100px 600px at 85% -10%, var(--glow-a), transparent 60%),
                radial-gradient(900px 500px at -10% 20%, var(--glow-b), transparent 55%),
            radial-gradient(700px 420px at 50% 110%, var(--glow-c), transparent 60%),
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
            /* на тёмной теме грань меню подсвечена, на светлой токен пустой */
            box-shadow:var(--rail-glow);
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

        /* ---------- переключатель меню ---------- */
        .side-toggle{
            margin-left:auto; flex-shrink:0; cursor:pointer;
            width:30px; height:30px; display:grid; place-items:center;
            border:1px solid var(--gray-200); border-radius:8px;
            background:var(--gray-100); color:var(--ink);
            transition:color .15s ease, border-color .15s ease;
        }
        .side-toggle:hover{ color:var(--indigo-600); border-color:var(--indigo-600); }
        .side-toggle svg{ width:16px; height:16px; transition:transform .25s ease; }

        /* свёрнутое меню: остаются иконки, кнопка возврата никуда не девается */
        .app.rail{ grid-template-columns:64px 1fr; }
        .app.rail .sidebar{ padding:0 8px; }
        .app.rail .brand{ flex-direction:column; gap:10px; padding:18px 0 16px; }
        .app.rail .brand-name,
        .app.rail .nav-group-label{ display:none; }
        .app.rail .side-toggle{ margin:0; }
        .app.rail .side-toggle svg{ transform:rotate(180deg); }
        .app.rail .nav-item{ justify-content:center; padding:10px 0; position:relative; }
        /* ссылка растягивается на всю строку: в свёрнутом виде кликают по иконке */
        .app.rail .nav-item a{ position:absolute; inset:0; font-size:0; }
        .app.rail .nav-item span[data-live]{ display:none; }
        .app.rail .logout-btn{ justify-content:center; padding:10px 0; font-size:0; }


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
            color:var(--danger);
            font-size:14px;
            font-weight:500;
            cursor:pointer;
            background:none;
            border:none;
            width:100%;
            text-align:left;
        }
        .logout-btn:hover{ background:var(--wash-danger); }

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
            /* колонки по числу карточек: этот же ряд используется с двумя,
               тремя и четырьмя плитками, жёсткие три оставляли пустое место */
            grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));
            gap:16px;
            margin-bottom:28px;
        }
        .stat-card{
            background:var(--white);
            border:1px solid var(--gray-200);
            border-radius:var(--radius);
            padding:18px 20px;
        }

        /* карточка-ссылка: ведёт к списку, который за ней стоит */
        a.stat-card{
            display:block; color:inherit; text-decoration:none;
            transition:border-color .18s ease, transform .18s ease, box-shadow .18s ease;
        }
        a.stat-card:hover{
            border-color:var(--indigo-600);
            transform:translateY(-2px);
            box-shadow:0 14px 28px -20px rgba(79,70,229,.8);
        }
        .stat-go{
            display:inline-block; margin-top:10px;
            font-size:12px; font-weight:700; color:var(--indigo-600);
        }
        a.stat-card:hover .stat-go{ text-decoration:underline; }

        .stat-card .label{ font-size:13px; color:var(--gray-500); margin-bottom:8px; }
        .stat-card .value{ font-size:26px; font-weight:700; }
        .stat-card .trend{ font-size:12px; color:var(--green); margin-top:4px; }

        .card{
            background:var(--white);
            border:1px solid var(--gray-200);
            border-radius:var(--radius);
            padding:24px;
            margin-bottom:20px;
            box-shadow:var(--card-glow);
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
        .badge-active{ background:var(--wash-good); color:var(--ink-good); }
        .badge-pending{ background:var(--wash-warn); color:var(--ink-warn); }
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
    
        /* ================= ТЁМНАЯ ТЕМА =================

           Светлая палитра лежит в :root выше и остаётся значением по умолчанию.
           Тёмная описана дважды намеренно, и это не копипаста по недосмотру:

           - media-запрос срабатывает, когда атрибут темы не выставлен вовсе.
             Так выглядит «системная» тема: решает настройка устройства. Оговорка
             :not([data-theme="light"]) нужна, чтобы явно выбранная светлая тема
             побеждала тёмную систему;
           - :root[data-theme="dark"] перебивает систему в другую сторону, когда
             тёмную выбрали руками.

           CSS не умеет переиспользовать блок объявлений, поэтому список токенов
           повторяется. Правила компонентов при этом не дублируются ни разу: всё,
           что меняется между темами, вынесено в токены выше.

           Имена оставлены прежними: --white здесь означает не белый, а «поверхность
           карточки». Благодаря этому вся вёрстка кабинета переключается разом,
           без правки сотен мест.
        */
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                /* поверхности и текст */
                --ink:#E9ECF7;
                --gray-500:#99A1BA;
                --gray-300:#3A4260;
                --gray-200:#272E45;
                --gray-100:#1B2133;
                --bg:#0C0F1A;
                --white:#151A2B;
                /* акцент на тёмном нужен светлее: исходный индиго на нём глухой */
                --indigo-600:#7C8CFF;
                --indigo-700:#98A5FF;
                --indigo-50:#1B2145;
                --indigo-100:#232B57;
                --green:#34D399;
                /* плашки статусов: светлые заливки на тёмном слепят */
                --danger:#F87171;
                --wash-danger:#2A1620;
                --wash-good:#0F2E20;
                --ink-good:#6EE7B7;
                --wash-warn:#2E2415;
                --ink-warn:#FBBF24;
                /* то самое освещение: подсветка фона и мягкое свечение поверхностей */
                --glow-a:rgba(124,140,255,0.20);
                --glow-b:rgba(99,102,241,0.13);
                --glow-c:rgba(56,189,248,0.07);
                --card-glow:0 1px 0 rgba(255,255,255,.04) inset, 0 18px 40px -30px rgba(0,0,0,.9);
                --rail-glow:1px 0 0 rgba(255,255,255,.03) inset, 24px 0 60px -50px rgba(124,140,255,.55);
            }
        }

        :root[data-theme="dark"] {
            /* поверхности и текст */
            --ink:#E9ECF7;
            --gray-500:#99A1BA;
            --gray-300:#3A4260;
            --gray-200:#272E45;
            --gray-100:#1B2133;
            --bg:#0C0F1A;
            --white:#151A2B;
            /* акцент на тёмном нужен светлее: исходный индиго на нём глухой */
            --indigo-600:#7C8CFF;
            --indigo-700:#98A5FF;
            --indigo-50:#1B2145;
            --indigo-100:#232B57;
            --green:#34D399;
            /* плашки статусов: светлые заливки на тёмном слепят */
            --danger:#F87171;
            --wash-danger:#2A1620;
            --wash-good:#0F2E20;
            --ink-good:#6EE7B7;
            --wash-warn:#2E2415;
            --ink-warn:#FBBF24;
            /* то самое освещение: подсветка фона и мягкое свечение поверхностей */
            --glow-a:rgba(124,140,255,0.20);
            --glow-b:rgba(99,102,241,0.13);
            --glow-c:rgba(56,189,248,0.07);
            --card-glow:0 1px 0 rgba(255,255,255,.04) inset, 0 18px 40px -30px rgba(0,0,0,.9);
            --rail-glow:1px 0 0 rgba(255,255,255,.03) inset, 24px 0 60px -50px rgba(124,140,255,.55);
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
