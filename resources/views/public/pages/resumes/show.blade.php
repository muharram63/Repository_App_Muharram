@extends('public.layouts.app')
@section('content')

    <style>
        /* ===== раскладка страницы ===== */
        .cv-layout{display:grid; grid-template-columns:minmax(0,1fr) 320px; gap:24px; align-items:start;}
        .cv-aside{position:sticky; top:96px; display:flex; flex-direction:column; gap:18px;}

        .back-link{
            display:inline-flex; align-items:center; gap:8px;
            font-size:14px; font-weight:600; color:var(--text-muted); margin-bottom:20px;
        }
        .back-link:hover{color:var(--accent);}

        /* ===== лист резюме: всегда «бумага», в любой теме ===== */
        .cv-sheet{
            position:relative;
            --ink:#1B2333;
            --ink-soft:#5A6478;
            --line:#E3E7EF;
            --mark:#3D5AFE;
            background:#FFFFFF;
            color:var(--ink);
            border:1px solid var(--line);
            border-radius:6px;
            box-shadow:0 24px 60px -28px rgba(16,24,48,.45);
            overflow:hidden;
            font-size:14.5px;
            line-height:1.65;
        }
        /* фирменная полоса сверху листа */
        .cv-sheet::before{
            content:""; display:block; height:6px;
            background:linear-gradient(90deg, var(--mark), #7C8CFF 60%, #9FB0FF);
        }

        /* ничто не должно вылезать за рамку листа */
        .cv-sheet, .cv-sheet *{min-width:0;}
        .cv-name, .cv-role, .cv-target, .cv-text, .cv-muted, .cv-entry-title,
        .cv-entry-meta, .cv-fact-value, .cv-tag, .cv-doc, .cv-locked,
        .cv-proof, .cv-proof-level{
            overflow-wrap:anywhere; word-break:break-word;
        }
        .cv-fact-value a, .cv-doc{word-break:break-all;}
        .cv-tags{max-width:100%;}
        .cv-tag{max-width:100%;}

        .cv-head{
            display:flex; gap:26px; align-items:flex-start;
            padding:40px 52px 28px;
            border-bottom:3px solid var(--ink);
            background:linear-gradient(180deg, #FBFCFE, #FFFFFF 70%);
        }
        .cv-photo{
            width:104px; height:104px; border-radius:4px; object-fit:cover; flex-shrink:0;
            border:1px solid var(--line);
        }
        .cv-photo-fallback{
            width:104px; height:104px; border-radius:4px; flex-shrink:0;
            background:#F2F5FB; color:var(--mark);
            display:flex; align-items:center; justify-content:center;
            font-size:38px; font-weight:800;
        }
        .cv-name{
            font-size:clamp(23px, 3.1vw, 32px);
            font-weight:800; letter-spacing:-.4px; line-height:1.15;
            text-transform:uppercase; margin:0;
        }
        .cv-role{
            font-size:15.5px; font-weight:700; color:var(--mark); margin-top:7px;
            letter-spacing:.06em; text-transform:uppercase;
        }
        .cv-target{
            font-size:13.5px; color:var(--ink-soft); margin-top:10px;
            padding-top:10px; border-top:1px solid var(--line);
        }
        .cv-target b{color:var(--ink);}

        .cv-body{
            display:grid; grid-template-columns:minmax(0,1.55fr) minmax(0,1fr);
        }
        .cv-main{padding:34px 40px 44px 52px; min-width:0;}
        .cv-side{padding:34px 52px 44px 34px; border-left:1px solid var(--line); background:#FBFCFE; min-width:0;}

        .cv-section + .cv-section{margin-top:30px;}
        .cv-section-title{
            font-size:11.5px; font-weight:800; letter-spacing:.16em; text-transform:uppercase;
            color:var(--ink); padding-bottom:8px; margin-bottom:16px;
            border-bottom:1px solid var(--line); display:flex; align-items:center; gap:9px;
        }
        .cv-section-title::before{
            content:""; width:14px; height:2px; background:var(--mark); flex-shrink:0;
        }

        .cv-text{white-space:pre-line; color:var(--ink);}
        .cv-muted{color:var(--ink-soft);}

        .cv-entry{position:relative; padding-left:20px;}
        .cv-entry + .cv-entry{margin-top:20px;}
        .cv-entry::before{
            content:""; position:absolute; left:0; top:7px; z-index:1;
            width:8px; height:8px; border-radius:50%; border:2px solid var(--mark);
            background:#fff;
        }
        .cv-entry:not(:last-child)::after{
            content:""; position:absolute; left:3px; top:17px; bottom:-20px;
            width:2px; background:var(--line);
        }
        .cv-entry-title{font-weight:700; font-size:15px;}
        .cv-entry-meta{font-size:13px; color:var(--ink-soft); margin-top:2px;}

        .cv-facts{display:flex; flex-direction:column; gap:12px;}
        .cv-fact-label{
            font-size:10.5px; font-weight:700; letter-spacing:.12em; text-transform:uppercase;
            color:var(--ink-soft); margin-bottom:2px;
        }
        .cv-fact-value{font-size:14px; font-weight:600; word-break:break-word;}
        .cv-fact-value a{color:var(--mark);}

        .cv-tags{display:flex; flex-wrap:wrap; gap:7px;}
        .cv-tag{
            font-size:12.5px; font-weight:600; padding:5px 11px; border-radius:4px;
            background:#EEF2FF; color:#2A3FCC; line-height:1.5;
        }
        .cv-tag.line{background:transparent; border:1px solid var(--line); color:var(--ink-soft);}

        /* подтверждённый навык: отличается от обычного намеренно — это проверенный факт */
        .cv-proof{
            display:inline-flex; align-items:center; gap:7px;
            font-size:12.5px; font-weight:700; padding:6px 12px; border-radius:999px;
            background:#ECFDF3; color:#15803D; border:1px solid #BBF7D0; line-height:1.5;
        }
        /* плашка не шире колонки, а иконка не сжимается в ноль при переносе */
        .cv-proof{max-width:100%; flex-wrap:wrap;}
        .cv-proof svg{width:13px; height:13px; flex:0 0 13px;}
        .cv-proof b{color:#0F5132;}
        .cv-proof-level{
            font-style:normal; font-weight:600; font-size:11.5px;
            padding:2px 7px; border-radius:999px; background:#fff; color:#166534;
        }

        .cv-doc{
            display:inline-flex; align-items:center; gap:9px; max-width:100%;
            padding:10px 14px; border:1px solid var(--line); border-radius:5px;
            font-size:13.5px; font-weight:600; color:var(--ink); background:#fff;
            transition:border-color .15s ease, color .15s ease;
        }
        .cv-doc:hover{border-color:var(--mark); color:var(--mark);}

        .cv-locked{
            border:1px dashed var(--line); border-radius:6px; padding:16px;
            font-size:13px; color:var(--ink-soft);
        }
        .cv-locked a{color:var(--mark); font-weight:600;}

        .cv-foot{
            padding:16px 52px; border-top:1px solid var(--line);
            font-size:11.5px; color:var(--ink-soft);
            display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;
            background:#FBFCFE;
        }

        /* ===== боковые панели (вне документа) ===== */
        .panel{
            background:var(--surface); border:1px solid var(--border);
            border-radius:16px; padding:24px;
        }
        .panel h3{font-size:15.5px; font-weight:800; margin-bottom:14px;}
        .panel, .panel *{min-width:0;}
        .panel a, .panel .muted{overflow-wrap:anywhere;}
        .panel .muted{color:var(--text-muted); font-size:14px;}
        .salary-box{
            background:linear-gradient(135deg, var(--accent), var(--accent-ink));
            color:#fff; border-radius:16px; padding:24px;
        }
        .salary-box .lbl{font-size:12.5px; opacity:.85; font-weight:600; margin-bottom:6px;}
        .salary-box .val{font-size:27px; font-weight:800; letter-spacing:-.5px;}
        .salary-box .pos{font-size:13.5px; opacity:.9; margin-top:6px;}
        .print-btn{
            width:100%; cursor:pointer; font-family:inherit; font-size:14px; font-weight:700;
            padding:11px 18px; border-radius:12px;
            background:var(--surface); color:var(--text); border:1px solid var(--border);
        }
        .print-btn:hover{border-color:var(--accent); color:var(--accent);}
        .resp-item{display:flex; gap:14px; padding:16px 0; border-bottom:1px solid var(--border);}
        .resp-item:last-child{border-bottom:none;}

        @media (max-width:980px){
            .cv-layout{grid-template-columns:1fr;}
            .cv-aside{position:static;}
            .cv-body{grid-template-columns:1fr;}
            .cv-side{border-left:none; border-top:1px solid var(--line); padding:30px 40px 40px;}
            .cv-main{padding:30px 40px;}
            .cv-head{padding:34px 40px 26px; flex-wrap:wrap;}
            .cv-foot{padding:16px 40px;}
        }

        /* ===== печать: остаётся только лист ===== */
        @media print{
            header.site, footer, .cv-aside, .back-link, .no-print{display:none !important;}
            body{background:#fff;}
            .cv-layout{display:block;}
            .cv-sheet{border:none; box-shadow:none;}
        }

        /* ================= ОФОРМЛЕНИЯ =================
           Соискатель выбирает вид резюме в помощнике, здесь он применяется.
           Разметка листа одна на все оформления — отличаются только стили,
           а classic намеренно не переопределяется: это прежний вид страницы,
           и резюме, созданные до появления выбора, выглядят как раньше. */

        /* ---------- современное: акцентная полоса и мягкие формы ---------- */
        .cv-sheet[data-skin="modern"]::before{height:0;}
        .cv-sheet[data-skin="modern"]{
            border-left:6px solid var(--mark); border-radius:16px; border-color:#DCE6FA;
            box-shadow:0 28px 70px -30px rgba(44,95,224,.55);
        }
        .cv-sheet[data-skin="modern"] .cv-head{
            border-bottom:1px solid var(--line);
            background:linear-gradient(135deg, #F4F7FF, #FFFFFF 65%);
        }
        .cv-sheet[data-skin="modern"] .cv-name{text-transform:none; letter-spacing:-.8px;}
        .cv-sheet[data-skin="modern"] .cv-role{text-transform:none; letter-spacing:0; font-size:16.5px;}
        .cv-sheet[data-skin="modern"] .cv-photo,
        .cv-sheet[data-skin="modern"] .cv-photo-fallback{border-radius:50%;}
        .cv-sheet[data-skin="modern"] .cv-section-title{
            border-bottom:0; background:#EEF2FF; color:#2A3FCC;
            display:inline-flex; padding:5px 11px; border-radius:6px; letter-spacing:.1em;
        }
        .cv-sheet[data-skin="modern"] .cv-tag{border-radius:999px;}
        .cv-sheet[data-skin="modern"] .cv-side{background:#FAFBFF;}

        /* ---------- компактное: плотнее и мельче, помещается на один экран ---------- */
        .cv-sheet[data-skin="compact"]{
            border-radius:3px; box-shadow:0 14px 34px -24px rgba(16,24,48,.5);
        }
        .cv-sheet[data-skin="compact"] .cv-head{padding:26px 40px 20px; gap:20px;}
        .cv-sheet[data-skin="compact"] .cv-photo,
        .cv-sheet[data-skin="compact"] .cv-photo-fallback{width:78px; height:78px;}
        .cv-sheet[data-skin="compact"] .cv-name{font-size:clamp(20px, 2.4vw, 25px);}
        .cv-sheet[data-skin="compact"] .cv-role{font-size:13.5px;}
        .cv-sheet[data-skin="compact"] .cv-main{padding:24px 30px 30px 40px;}
        .cv-sheet[data-skin="compact"] .cv-side{padding:24px 40px 30px 26px;}
        .cv-sheet[data-skin="compact"] .cv-section + .cv-section{margin-top:20px;}
        .cv-sheet[data-skin="compact"] .cv-section-title{font-size:10.5px; padding-bottom:5px; margin-bottom:10px;}
        .cv-sheet[data-skin="compact"] .cv-text,
        .cv-sheet[data-skin="compact"] .cv-entry-title{font-size:13.5px;}
        .cv-sheet[data-skin="compact"] .cv-entry + .cv-entry{margin-top:13px;}
        .cv-sheet[data-skin="compact"] .cv-tag{font-size:11.5px; padding:3px 8px;}
        .cv-sheet[data-skin="compact"] .cv-facts{gap:9px;}

        /* ---------- строгое: типографика без цвета ---------- */
        .cv-sheet[data-skin="strict"]{
            font-family:Georgia, 'Times New Roman', serif; --mark:#1F2937;
            /* прямые углы и жёсткая рамка — документ, а не карточка */
            border-radius:0; border:2px solid #111827; box-shadow:none;
        }
        .cv-sheet[data-skin="strict"]::before{background:#1F2937;}
        .cv-sheet[data-skin="strict"] .cv-head{background:#fff;}
        .cv-sheet[data-skin="strict"] .cv-name{letter-spacing:.02em;}
        .cv-sheet[data-skin="strict"] .cv-role{color:#1F2937; font-style:italic; text-transform:none;}
        .cv-sheet[data-skin="strict"] .cv-section-title{
            font-family:'Inter', sans-serif; color:#111827; border-bottom-color:#111827;
        }
        .cv-sheet[data-skin="strict"] .cv-entry::before{border-color:#1F2937;}
        .cv-sheet[data-skin="strict"] .cv-tag{
            background:#fff; color:#1F2937; border:1px solid var(--line); border-radius:0;
        }
        .cv-sheet[data-skin="strict"] .cv-side{background:#FCFCFC;}

        /* ---------- элегантное: тёплый акцент и разрядка ---------- */
        .cv-sheet[data-skin="elegant"]{
            --mark:#8A6D3B;
            /* тонкая рамка с внутренней линией — как паспарту у гравюры */
            border-radius:2px; border-color:rgba(138,109,59,.45);
            box-shadow:0 0 0 1px rgba(138,109,59,.18) inset, 0 22px 54px -32px rgba(138,109,59,.8);
        }
        .cv-sheet[data-skin="elegant"]::before{background:linear-gradient(90deg,#8A6D3B,#D9C7A3);}
        .cv-sheet[data-skin="elegant"] .cv-head{
            background:#FDFBF7; border-bottom-width:1px; border-bottom-color:rgba(138,109,59,.35);
        }
        .cv-sheet[data-skin="elegant"] .cv-name{font-weight:600; letter-spacing:.14em;}
        .cv-sheet[data-skin="elegant"] .cv-role{font-style:italic; text-transform:none; letter-spacing:.02em;}
        .cv-sheet[data-skin="elegant"] .cv-section-title{color:#6B5427; border-bottom-color:rgba(138,109,59,.3);}
        .cv-sheet[data-skin="elegant"] .cv-tag{background:#FBF7EF; color:#6B5427;}
        .cv-sheet[data-skin="elegant"] .cv-side{background:#FDFBF7;}

        /* ---------- контрастное: тёмная шапка ---------- */
        .cv-sheet[data-skin="bold"]::before{height:0;}
        .cv-sheet[data-skin="bold"]{
            border:0; border-radius:12px; box-shadow:0 30px 70px -30px rgba(14,36,86,.85);
        }
        .cv-sheet[data-skin="bold"] .cv-head{
            background:#0E2456; border-bottom:0; color:#fff;
        }
        .cv-sheet[data-skin="bold"] .cv-name{color:#fff;}
        .cv-sheet[data-skin="bold"] .cv-role{color:#A8C4F5;}
        .cv-sheet[data-skin="bold"] .cv-target{color:#C9D8F3; border-top-color:rgba(255,255,255,.25);}
        .cv-sheet[data-skin="bold"] .cv-photo,
        .cv-sheet[data-skin="bold"] .cv-photo-fallback{border-color:rgba(255,255,255,.35);}
        .cv-sheet[data-skin="bold"] .cv-section-title{border-bottom-width:2px; border-bottom-color:#0E2456;}
        .cv-sheet[data-skin="bold"] .cv-tag{background:#0E2456; color:#fff;}

        /* ---------- мягкое: пастельные блоки без линеек ---------- */
        .cv-sheet[data-skin="soft"]{
            --mark:#2F8F76;
            /* без рамки, крупные скругления и мягкая тень в тон */
            border:0; border-radius:24px; box-shadow:0 26px 60px -30px rgba(47,143,118,.7);
        }
        .cv-sheet[data-skin="soft"]::before{background:linear-gradient(90deg,#2F8F76,#9DD5C4);}
        .cv-sheet[data-skin="soft"] .cv-head{background:#F4FAF8; border-bottom:1px solid #D7ECE5;}
        .cv-sheet[data-skin="soft"] .cv-photo,
        .cv-sheet[data-skin="soft"] .cv-photo-fallback{border-radius:16px;}
        .cv-sheet[data-skin="soft"] .cv-section-title{border-bottom:0; color:#2F8F76; margin-bottom:10px;}
        .cv-sheet[data-skin="soft"] .cv-section{
            background:#F8FCFB; border-radius:14px; padding:16px 18px;
        }
        .cv-sheet[data-skin="soft"] .cv-tag{
            background:#fff; color:#2F8F76; border:1px solid rgba(47,143,118,.3); border-radius:999px;
        }
        .cv-sheet[data-skin="soft"] .cv-side{background:#FBFDFC;}

        /* ---------- журнальное: широкие поля, заголовок с засечками ---------- */
        .cv-sheet[data-skin="editorial"]{
            --mark:#111827;
            border:0; border-radius:0; border-top:8px solid #111827; box-shadow:none;
        }
        .cv-sheet[data-skin="editorial"]::before{height:0;}
        .cv-sheet[data-skin="editorial"] .cv-head{
            background:#fff; border-bottom:1px solid #111827; padding:44px 56px 30px;
        }
        .cv-sheet[data-skin="editorial"] .cv-name{
            font-family:Georgia, 'Times New Roman', serif; text-transform:none; letter-spacing:-.5px;
        }
        .cv-sheet[data-skin="editorial"] .cv-role{color:#111827; text-transform:none; font-weight:600;}
        .cv-sheet[data-skin="editorial"] .cv-section-title{
            border-bottom:0; border-top:1px solid #111827; padding:8px 0 0; letter-spacing:.22em;
        }
        .cv-sheet[data-skin="editorial"] .cv-tag{background:transparent; border:1px solid var(--line); border-radius:0;}
        .cv-sheet[data-skin="editorial"] .cv-side{background:#fff;}

        /* ---------- техническое: моноширинные заголовки ---------- */
        .cv-sheet[data-skin="tech"]{
            --mark:#0F766E;
            border-color:#99F6E4; border-radius:4px;
            box-shadow:0 18px 44px -30px rgba(15,118,110,.8);
        }
        .cv-sheet[data-skin="tech"]::before{background:linear-gradient(90deg,#0F766E,#5EEAD4);}
        .cv-sheet[data-skin="tech"] .cv-head{background:#F0FDFA; border-bottom-width:1px; border-bottom-color:#99F6E4;}
        .cv-sheet[data-skin="tech"] .cv-section-title,
        .cv-sheet[data-skin="tech"] .cv-entry-meta{
            font-family:ui-monospace, 'Cascadia Mono', Consolas, monospace;
        }
        .cv-sheet[data-skin="tech"] .cv-section-title{color:#115E59; border-bottom-color:#99F6E4;}
        .cv-sheet[data-skin="tech"] .cv-tag{
            font-family:ui-monospace, Consolas, monospace; font-size:11.5px;
            background:#F0FDFA; color:#115E59; border-radius:3px;
        }
        .cv-sheet[data-skin="tech"] .cv-side{background:#FAFFFE;}

        /* ---------- тёплое: бумажный фон и терракота ---------- */
        .cv-sheet[data-skin="warm"]{
            --mark:#C2410C; --line:#F3D9C4;
            background:#FFFBF7; border-color:#F3D9C4; border-radius:14px;
            box-shadow:0 24px 54px -32px rgba(194,65,12,.65);
        }
        .cv-sheet[data-skin="warm"]::before{background:linear-gradient(90deg,#C2410C,#FDBA74);}
        .cv-sheet[data-skin="warm"] .cv-head{background:#FFF6EE; border-bottom-color:#E9C6A8;}
        .cv-sheet[data-skin="warm"] .cv-role{color:#C2410C; text-transform:none;}
        .cv-sheet[data-skin="warm"] .cv-tag{background:#FFF1E6; color:#9A3412; border-radius:999px;}
        .cv-sheet[data-skin="warm"] .cv-side{background:#FFF8F2;}

        /* ---------- тёмное: светлый текст на тёмном листе ----------
           лист собран на переменных, поэтому хватает переопределить их */
        .cv-sheet[data-skin="night"]{
            --ink:#E8ECF5; --ink-soft:#9AA7BC; --line:#2A3346; --mark:#93B4FF;
            background:#111827; border-color:#2A3346; border-radius:14px;
            box-shadow:0 30px 64px -32px rgba(0,0,0,.9);
        }
        .cv-sheet[data-skin="night"]::before{background:linear-gradient(90deg,#93B4FF,#C084FC);}
        .cv-sheet[data-skin="night"] .cv-head{
            background:#161F33; border-bottom-color:#2A3346; border-bottom-width:1px;
        }
        .cv-sheet[data-skin="night"] .cv-name{color:#FFFFFF;}
        .cv-sheet[data-skin="night"] .cv-photo-fallback{background:#1C2436; color:#93B4FF;}
        .cv-sheet[data-skin="night"] .cv-tag{background:#1C2436; color:#C9D8F3; border:1px solid #2A3346;}
        .cv-sheet[data-skin="night"] .cv-tag.line{background:transparent; color:#9AA7BC;}
        .cv-sheet[data-skin="night"] .cv-side{background:#141C2E;}
        .cv-sheet[data-skin="night"] .cv-entry::before{background:#111827;}
        .cv-sheet[data-skin="night"] .cv-doc{background:#1C2436; color:#E8ECF5;}
        /* подтверждённый навык остаётся зелёным, но читаемым на тёмном */
        .cv-sheet[data-skin="night"] .cv-proof{background:#12301F; border-color:#1F6F43; color:#86EFAC;}
        .cv-sheet[data-skin="night"] .cv-proof b{color:#BBF7D0;}
        .cv-sheet[data-skin="night"] .cv-proof-level{background:#0B2416; color:#86EFAC;}
    </style>

    @php
        $applicant = $resume->applicant;
        $user = $applicant->user;
        $years = (int) $resume->experience_years;
        $skills = array_filter(array_map('trim', explode(',', (string) $resume->skills)));
        $languages = array_filter(array_map('trim', explode(',', (string) $resume->languages)));

        $authUser = auth()->user();
        $myEmployer = $authUser?->employer;
        $myResponse = $myEmployer
            ? $resume->responses->firstWhere('employer_id', $myEmployer->id)
            : null;
        $isResumeOwner = $authUser && $applicant->user_id === $authUser->id;

        // навыки, доказанные заданием на платформе
        $badges = App\Models\SkillAttempt::badgesFor($applicant);

        // работодатель сравнивает кандидата со своими вакансиями
        $matchOptions = $myEmployer
            ? $myEmployer->vacancies()->latest('id')->get()->map(fn ($v) => [
                'url' => route('public.match', [$v, $resume]),
                'label' => $v->title,
            ])->all()
            : [];

        $statusLabels = [
            'new' => 'Новый',
            'viewed' => 'Просмотрен',
            'accepted' => 'Принят',
            'rejected' => 'Отклонён',
        ];

        $experienceLine = $years > 0 ? $years.' г. опыта' : 'Без опыта';
    @endphp

    <section class="section">
        <div class="container">

            <a href="{{ route('public.resumes.index') }}" class="back-link">{{ __('← Назад к списку резюме') }}</a>

            @if(session('status') || session('error'))
                <div class="no-print" style="margin-bottom:20px; padding:14px 18px; border-radius:12px; font-weight:600;
                            background:{{ session('error') ? '#FEF2F2' : '#ECFDF5' }};
                            color:{{ session('error') ? '#B91C1C' : '#15803D' }};">
                    {{ session('error') ?: session('status') }}
                </div>
            @endif

            <div class="cv-layout">

                {{-- ================= ЛИСТ РЕЗЮМЕ ================= --}}
                <div>
                    {{-- оформление выбрано соискателем при создании резюме --}}
                    <article class="cv-sheet" data-skin="{{ $resume->style ?: 'classic' }}">

                        <header class="cv-head">
                            @if($user->hasAvatar())
                                <img class="cv-photo" src="{{ asset($user->avatar) }}" alt="{{ $user->name }}">
                            @else
                                <div class="cv-photo-fallback">{{ $user->initials(1) }}</div>
                            @endif

                            <div style="min-width:0;">
                                <h1 class="cv-name">{{ $user->name }}</h1>
                                <div class="cv-role">{{ $resume->profession }}</div>
                                <div class="cv-target">
                                    {{ __('Желаемая должность:') }} <b>{{ $resume->desired_position }}</b> ·
                                    {{ number_format($resume->desired_salary, 0, ',', ' ') }} {{ __('сомони') }} ·
                                    {{ $experienceLine }}
                                </div>
                            </div>
                        </header>

                        <div class="cv-body">

                            {{-- ---------- основная колонка ---------- --}}
                            <div class="cv-main">

                                <section class="cv-section">
                                    <div class="cv-section-title">{{ __('О себе') }}</div>
                                    @if($resume->description)
                                        <div class="cv-text">{{ $resume->description }}</div>
                                    @elseif($applicant->about_me)
                                        <div class="cv-text">{{ $applicant->about_me }}</div>
                                    @else
                                        <div class="cv-muted">{{ __('Раздел не заполнен') }}</div>
                                    @endif
                                </section>

                                <section class="cv-section">
                                    <div class="cv-section-title">{{ __('Опыт работы') }}</div>
                                    <div class="cv-entry">
                                        <div class="cv-entry-title">{{ $resume->profession }}</div>
                                        <div class="cv-entry-meta">
                                            {{ $resume->place_work ?: 'Место работы не указано' }} · {{ $experienceLine }}
                                        </div>
                                    </div>
                                    <div class="cv-entry">
                                        <div class="cv-entry-title">Ищет позицию: {{ $resume->desired_position }}</div>
                                        <div class="cv-entry-meta">
                                            {{ __('Ожидания по доходу') }} — {{ number_format($resume->desired_salary, 0, ',', ' ') }} {{ __('сомони') }}
                                        </div>
                                    </div>
                                </section>

                                <section class="cv-section">
                                    <div class="cv-section-title">{{ __('Образование') }}</div>
                                    @if($applicant->education)
                                        <div class="cv-entry">
                                            <div class="cv-entry-title">{{ $applicant->education }}</div>
                                            <div class="cv-entry-meta">{{ $applicant->city ?: 'Город не указан' }}</div>
                                        </div>
                                    @else
                                        <div class="cv-muted">{{ __('Не указано') }}</div>
                                    @endif
                                </section>

                                @if($resume->description && $applicant->about_me)
                                    <section class="cv-section">
                                        <div class="cv-section-title">{{ __('Дополнительно') }}</div>
                                        <div class="cv-text">{{ $applicant->about_me }}</div>
                                    </section>
                                @endif
                            </div>

                            {{-- ---------- боковая колонка листа ---------- --}}
                            <aside class="cv-side">

                                <section class="cv-section" style="margin-top:0;">
                                    <div class="cv-section-title">{{ __('Контакты') }}</div>
                                    @auth
                                        <div class="cv-facts">
                                            <div>
                                                <div class="cv-fact-label">{{ __('Телефон') }}</div>
                                                <div class="cv-fact-value">
                                                    {{ ($hideContacts ?? false) ? __('Скрыт настройками') : ($applicant->phone ?: '—') }}
                                                </div>
                                            </div>
                                            <div>
                                                <div class="cv-fact-label">E-mail</div>
                                                <div class="cv-fact-value">
                                                    {{ ($hideContacts ?? false) ? __('Скрыт настройками') : $user->email }}
                                                </div>
                                            </div>
                                            <div>
                                                <div class="cv-fact-label">{{ __('Город') }}</div>
                                                <div class="cv-fact-value">{{ $applicant->city ?: '—' }}</div>
                                            </div>
                                            @if($applicant->address)
                                                <div>
                                                    <div class="cv-fact-label">{{ __('Адрес') }}</div>
                                                    <div class="cv-fact-value">{{ $applicant->address }}</div>
                                                </div>
                                            @endif
                                        </div>
                                        @if($hideContacts ?? false)
                                            <div class="cv-locked" style="margin-top:10px;">
                                                {{ __('Соискатель скрыл контакты — напишите в чате.') }}
                                            </div>
                                        @endif
                                    @endauth
                                    @guest
                                        <div class="cv-locked">
                                            {{ __('Контакты видны зарегистрированным работодателям.') }}<br>
                                            <a href="{{ route('login') }}">{{ __('Войти') }}</a>
                                        </div>
                                    @endguest
                                </section>

                                {{-- Подтверждённые навыки идут ПЕРЕД обычными: это
                                     проверенный результат, а не слова о себе. Ради
                                     этого и затевалась проверка навыков. --}}
                                @if($badges->isNotEmpty())
                                    <section class="cv-section">
                                        <div class="cv-section-title">{{ __('Подтверждено заданием') }}</div>
                                        <div class="cv-tags">
                                            @foreach($badges as $badge)
                                                <span class="cv-proof">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                         stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                                                    {{ $badge['skill'] }}
                                                    {{-- уровень задания: по нему видно, насколько
                                                         глубоко проверен навык, а не только «сдал» --}}
                                                    <i class="cv-proof-level">{{ $badge['level'] }}</i>
                                                    <b>{{ $badge['score'] }}%</b>
                                                </span>
                                            @endforeach
                                        </div>
                                        <div class="cv-muted" style="margin-top:8px; font-size:12.5px;">
                                            {{ __('Результат задания, пройденного на платформе') }}
                                        </div>
                                    </section>
                                @endif

                                <section class="cv-section">
                                    <div class="cv-section-title">{{ __('Навыки') }}</div>
                                    @if(count($skills))
                                        <div class="cv-tags">
                                            @foreach($skills as $skill)
                                                <span class="cv-tag">{{ $skill }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="cv-muted">{{ __('Не указаны') }}</div>
                                    @endif
                                </section>

                                <section class="cv-section">
                                    <div class="cv-section-title">{{ __('Языки') }}</div>
                                    @if(count($languages))
                                        <div class="cv-tags">
                                            @foreach($languages as $language)
                                                <span class="cv-tag line">{{ $language }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="cv-muted">{{ __('Не указаны') }}</div>
                                    @endif
                                </section>

                                @if($resume->url_website || $resume->documents)
                                    <section class="cv-section">
                                        <div class="cv-section-title">{{ __('Ссылки и файлы') }}</div>
                                        <div class="cv-facts">
                                            @if($resume->url_website)
                                                <div>
                                                    <div class="cv-fact-label">{{ __('Портфолио') }}</div>
                                                    <div class="cv-fact-value">
                                                        <a href="{{ $resume->url_website }}" target="_blank" rel="noopener">{{ $resume->url_website }}</a>
                                                    </div>
                                                </div>
                                            @endif
                                            @auth
                                                @if($resume->documents)
                                                    <a class="cv-doc" href="{{ route('public.files.resume', $resume) }}" target="_blank" rel="noopener">
                                                        📄 {{ basename($resume->documents) }}
                                                    </a>
                                                @endif
                                            @endauth
                                        </div>
                                    </section>
                                @endif
                            </aside>
                        </div>

                        <footer class="cv-foot">
                            <span>Резюме №{{ $resume->id }} · Workio</span>
                            <span>Обновлено {{ $resume->updated_at->format('d.m.Y') }}</span>
                        </footer>
                    </article>

                    {{-- отклики владельцу — вне документа --}}
                    @if($isResumeOwner)
                        <div class="panel no-print" style="margin-top:22px;">
                            <h3>Кто заинтересовался ({{ $resume->responses_count }})</h3>
                            @forelse($resume->responses->sortByDesc('created_at') as $response)
                                @php $respEmployer = $response->employer; @endphp
                                <div class="resp-item">
                                    <div style="width:44px; height:44px; border-radius:12px; flex-shrink:0;
                                                background:var(--accent-soft); color:var(--accent-ink);
                                                display:flex; align-items:center; justify-content:center; font-weight:800;">
                                        @if($respEmployer?->user?->hasAvatar())
                                            <img src="{{ asset($respEmployer->user->avatar) }}" alt=""
                                                 style="width:100%; height:100%; border-radius:inherit; object-fit:cover;">
                                        @else
                                            {{ $respEmployer?->initials(2) ?? '?' }}
                                        @endif
                                    </div>
                                    <div style="flex:1; min-width:0;">
                                        <div style="font-weight:700;">
                                            @if($respEmployer)
                                                <a href="{{ route('public.companies.show', $respEmployer) }}">{{ $respEmployer->company_name }}</a>
                                            @else
                                                Компания удалена
                                            @endif
                                        </div>
                                        <div class="muted" style="margin-top:2px;">
                                            {{ $respEmployer?->email_company ?: '—' }} · {{ $respEmployer?->phone ?: '—' }}
                                        </div>
                                        <div class="muted" style="margin-top:2px;">
                                            {{ $response->created_at->format('d.m.Y H:i') }} ·
                                            {{ $statusLabels[$response->status] ?? $response->status }}
                                        </div>
                                        @if($response->message)
                                            <div style="font-size:14px; margin-top:8px; line-height:1.6; white-space:pre-line;">{{ $response->message }}</div>
                                        @endif

                                        @include('partials.response-actions', [
                                            'response' => $response,
                                            'route' => route('public.resume_responses.status', $response),
                                        ])
                                    </div>
                                </div>
                            @empty
                                <p class="muted" style="margin:0;">{{ __('Пока никто не откликнулся на это резюме.') }}</p>
                            @endforelse
                        </div>
                    @endif
                </div>

                {{-- ================= ПАНЕЛЬ ДЕЙСТВИЙ ================= --}}
                <aside class="cv-aside no-print">
                    {{-- разбор совпадения: работодателю — до кнопки приглашения --}}
                    @auth
                        @if($myEmployer)
                            @include('partials.match-analysis', [
                                'options' => $matchOptions,
                                'emptyHint' => __('Опубликуйте вакансию, и ИИ разберёт, насколько кандидат ей подходит.'),
                            ])
                        @endif
                    @endauth

                    <div class="salary-box">
                        <div class="lbl">{{ __('Желаемая зарплата') }}</div>
                        <div class="val">{{ number_format($resume->desired_salary, 0, ',', ' ') }} {{ __('сомони') }}</div>
                        <div class="pos">{{ $resume->desired_position }} · {{ $experienceLine }}</div>
                    </div>

                    <div class="panel">
                        <h3>{{ __('Отклик') }}</h3>
                        @guest
                            <p class="muted" style="margin-bottom:14px;">{{ __('Войдите как работодатель, чтобы пригласить соискателя.') }}</p>
                            <a href="{{ route('login') }}" class="btn-primary" style="display:inline-block;">{{ __('Войти') }}</a>
                        @endguest

                        @auth
                            @if($isResumeOwner)
                                <p class="muted" style="margin:0;">{{ __('Это ваше резюме. Отклики работодателей — в блоке под документом.') }}</p>
                            @elseif($authUser->role !== 'employer')
                                <p class="muted" style="margin:0;">{{ __('Приглашать соискателей могут только работодатели.') }}</p>
                            @elseif($myResponse)
                                <form action="{{ route('public.chats.start') }}" method="post" style="margin-bottom:14px;">
                                    @csrf
                                    <input type="hidden" name="applicant_id" value="{{ $applicant->id }}">
                                    <button type="submit" class="btn-primary"
                                            style="width:100%; border:none; cursor:pointer;">{{ __('💬 Написать соискателю') }}</button>
                                </form>
                                <p style="font-weight:700; margin-bottom:4px;">
                                    Вы пригласили {{ $myResponse->created_at->format('d.m.Y H:i') }}
                                </p>
                                <p class="muted" style="margin-bottom:14px;">
                                    Статус: {{ $statusLabels[$myResponse->status] ?? $myResponse->status }}
                                </p>
                                <form action="{{ route('public.resumes.respond.destroy', $resume) }}" method="post">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-ghost" style="width:100%; cursor:pointer;">
                                        {{ __('Отозвать приглашение') }}
                                    </button>
                                </form>

                                @include('partials.interview-schedule', [
                                    'applicantId' => $applicant->id,
                                    'resumeResponseId' => $myResponse->id,
                                ])
                            @else
                                <form action="{{ route('public.resumes.respond', $resume) }}" method="post">
                                    @csrf
                                    <textarea name="message" maxlength="1000" rows="4"
                                              placeholder="{{ __('Расскажите о вакансии (необязательно)') }}"
                                              style="width:100%; margin-bottom:12px; padding:12px 14px; font-family:inherit;
                                                     font-size:14px; color:var(--text); background:var(--surface-alt);
                                                     border:1px solid var(--border); border-radius:12px; resize:vertical;"></textarea>
                                    <button type="submit" class="btn-primary"
                                            style="width:100%; border:none; cursor:pointer;">
                                        {{ __('Пригласить') }}
                                    </button>
                                </form>

                                <form action="{{ route('public.chats.start') }}" method="post" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="applicant_id" value="{{ $applicant->id }}">
                                    <button type="submit" class="btn-ghost"
                                            style="width:100%; cursor:pointer;">{{ __('💬 Написать соискателю') }}</button>
                                </form>
                            @endif

                            @include('partials.complaint-form', [
                                'targetType' => 'resume',
                                'targetId' => $resume->id,
                            ])
                        @endauth
                    </div>

                    <div class="panel">
                        <h3>{{ __('Резюме') }}</h3>
                        <p class="muted" style="margin-bottom:14px;">
                            👥 {{ $resume->responses_count }} {{ __('откликов работодателей') }}<br>
                            👁 {{ $resume->views }} {{ __('просмотров') }}
                        </p>
                        <button type="button" class="print-btn" onclick="window.print()">{{ __('🖨 Распечатать / PDF') }}</button>
                    </div>
                </aside>

            </div>
        </div>
    </section>

@endsection
