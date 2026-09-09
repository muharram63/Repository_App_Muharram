<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Workio — Резюме-бот') }}</title>
    @include('applicant.partials.css')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap"
          rel="stylesheet">
    <style>
        /* Палитра страницы создания резюме: помощник живёт рядом с ней
           и должен читаться как её часть, а не как чужая вставка. */
        :root {
            --navy-deep: #071433;
            --navy-mid: #0E2456;
            --blue-primary: #2C5FE0;
            --blue-sky: #5B8DEF;
            --blue-ice: #EAF1FC;
            --slate: #44506B;
            --slate-soft: #8195B8;
            --glass: rgba(255, 255, 255, 0.94);
            --radius: 20px;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--navy-deep);
            background:
                radial-gradient(900px 520px at 88% -8%, rgba(91, 141, 239, .22), transparent 60%),
                radial-gradient(760px 460px at -6% 18%, rgba(44, 95, 224, .16), transparent 55%),
                #F4F7FD;
        }

        h1, h2, h3, .ai-title, .ai-eyebrow { font-family: 'Manrope', 'Inter', sans-serif; }

        /* ---------- каркас ---------- */
        .ai-wrap { padding: 0 3vh 4vh 2vh; }

        .ai-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(0, .95fr);
            gap: 22px;
            align-items: start;
        }

        .ai-card {
            background: var(--glass);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, .7);
            border-radius: var(--radius);
            box-shadow: 0 30px 60px -34px rgba(5, 15, 45, .45), 0 0 0 1px rgba(44, 95, 224, .06) inset;
            overflow: hidden;
        }

        .ai-head {
            padding: 22px 26px 18px;
            border-bottom: 1px solid rgba(44, 95, 224, .12);
            position: relative;
            /* заголовок слева, действие справа */
            display: flex; align-items: center; justify-content: space-between;
            gap: 14px; flex-wrap: wrap;
        }

        .ai-head::before {
            content: "";
            position: absolute;
            left: 26px; top: 0;
            width: 46px; height: 4px;
            border-radius: 0 0 4px 4px;
            background: linear-gradient(90deg, var(--blue-primary), var(--blue-sky));
        }

        .ai-eyebrow {
            font-size: 11.5px; letter-spacing: 2px; text-transform: uppercase;
            color: var(--blue-primary); font-weight: 700; margin: 10px 0 6px;
        }

        .ai-title { font-size: 22px; margin: 0; line-height: 1.25; }

        /* «Собрать новое резюме» — заметное действие в шапке разговора */
        .ai-new {
            flex: 0 0 auto; display: inline-flex; align-items: center; gap: 7px; cursor: pointer;
            border: 1px solid rgba(44, 95, 224, .3); background: #fff; color: var(--blue-primary);
            border-radius: 999px; padding: 9px 15px; font: 700 12.5px 'Inter', sans-serif;
            transition: background .18s ease, transform .18s ease, box-shadow .18s ease;
        }
        .ai-new:hover {
            background: var(--blue-ice); transform: translateY(-1px);
            box-shadow: 0 10px 20px -14px rgba(44, 95, 224, .9);
        }
        .ai-sub { margin: 7px 0 0; font-size: 13.5px; color: var(--slate-soft); }

        /* ---------- список разговоров ---------- */
        .ai-threads {
            display: flex; gap: 7px; overflow-x: auto; padding: 11px 26px;
            border-bottom: 1px solid rgba(44, 95, 224, .12); background: rgba(234, 241, 252, .4);
        }
        .ai-thread {
            display: inline-flex; align-items: center; flex: 0 0 auto; max-width: 250px;
            padding: 0 4px 0 12px; border-radius: 999px; border: 1px solid rgba(44, 95, 224, .2);
            background: #fff; font-size: 12.5px; color: var(--slate);
            transition: border-color .18s ease, color .18s ease;
        }
        .ai-thread:hover { border-color: var(--blue-primary); }
        .ai-thread.on { border-color: var(--blue-primary); color: var(--navy-deep); font-weight: 600; }
        .ai-thread-link { display: inline-flex; align-items: center; gap: 7px; min-width: 0; padding: 6px 0; }
        /* крестик проявляется на наведении: в спокойном состоянии он не отвлекает */
        .ai-thread-x {
            border: 0; background: none; cursor: pointer; line-height: 1;
            color: var(--slate-soft); font-size: 17px; padding: 4px 7px; border-radius: 50%;
            opacity: .35; transition: opacity .18s ease, color .18s ease, background .18s ease;
        }
        .ai-thread:hover .ai-thread-x { opacity: 1; }
        .ai-thread-x:hover { color: #B91C1C; background: #FEF2F2; }
        .ai-thread-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ai-thread-when { color: var(--slate-soft); font-size: 11.5px; flex: 0 0 auto; }
        .ai-thread-dot {
            width: 7px; height: 7px; border-radius: 50%; flex: 0 0 7px; background: var(--blue-sky);
        }
        /* завершённый разговор виден сразу: из него уже выросло резюме */
        .ai-thread-dot.done { background: #16A34A; }

        /* ---------- прогресс ---------- */
        .ai-progress { height: 5px; background: rgba(44, 95, 224, .1); }
        .ai-progress span {
            display: block; height: 100%; width: 0;
            background: linear-gradient(90deg, var(--blue-primary), var(--blue-sky));
            transition: width .7s cubic-bezier(.22, .61, .36, 1);
        }

        /* ---------- лента диалога ---------- */
        .ai-feed {
            height: 52vh; min-height: 340px; overflow-y: auto;
            padding: 22px 26px; display: flex; flex-direction: column; gap: 14px;
            scroll-behavior: smooth;
        }

        .ai-msg { display: flex; gap: 10px; max-width: 88%; animation: ai-rise .45s ease both; }
        .ai-msg.me { align-self: flex-end; flex-direction: row-reverse; }

        .ai-ava {
            flex: 0 0 32px; width: 32px; height: 32px; border-radius: 50%;
            display: grid; place-items: center; font-size: 12.5px; font-weight: 700; color: #fff;
            background: linear-gradient(135deg, var(--blue-primary), var(--blue-sky));
        }
        .ai-msg.me .ai-ava { background: #17203A; }

        .ai-bubble {
            padding: 12px 15px; border-radius: 16px; font-size: 14px; line-height: 1.55;
            background: #fff; border: 1px solid rgba(44, 95, 224, .14); color: var(--navy-deep);
            box-shadow: 0 8px 20px -14px rgba(7, 20, 51, .5);
            white-space: pre-wrap; word-break: break-word;
        }
        .ai-msg.me .ai-bubble {
            background: linear-gradient(135deg, var(--blue-primary), #3E6FE8);
            color: #fff; border-color: transparent;
        }
        .ai-bubble.err {
            background: #FEF2F2; border-color: #FCA5A5; color: #991B1B;
        }
        .ai-bubble.err button {
            margin-top: 8px; border: 1px solid #F87171; background: #fff; color: #B91C1C;
            border-radius: 999px; padding: 5px 13px; font-size: 12.5px; font-weight: 600; cursor: pointer;
        }

        /* курсор печати — ощущение живого ответа, а не мгновенной вставки */
        .ai-bubble.typing::after {
            content: "▍"; margin-left: 1px; color: var(--blue-sky);
            animation: ai-blink 1s steps(2) infinite;
        }

        .ai-dots { display: inline-flex; gap: 4px; padding: 3px 0; }
        .ai-dots i {
            width: 6px; height: 6px; border-radius: 50%; background: var(--slate-soft);
            animation: ai-bounce 1.1s infinite ease-in-out;
        }
        .ai-dots i:nth-child(2) { animation-delay: .15s; }
        .ai-dots i:nth-child(3) { animation-delay: .3s; }

        /* ---------- поле ввода ---------- */
        .ai-compose { border-top: 1px solid rgba(44, 95, 224, .12); padding: 14px 26px 18px; background: #fff; }

        .ai-chips { display: flex; flex-wrap: wrap; gap: 7px; margin-bottom: 11px; }
        .ai-chip {
            border: 1px solid rgba(44, 95, 224, .22); background: var(--blue-ice); color: var(--blue-primary);
            border-radius: 999px; padding: 6px 13px; font-size: 12.5px; font-weight: 600; cursor: pointer;
            transition: transform .15s ease, background .15s ease;
        }
        .ai-chip:hover { background: #DCE8FB; transform: translateY(-1px); }

        .ai-row { display: flex; gap: 10px; align-items: flex-end; }

        .ai-input {
            flex: 1; resize: none; min-height: 46px; max-height: 130px;
            border: 1px solid rgba(44, 95, 224, .25); border-radius: 14px;
            padding: 13px 15px; font-family: inherit; font-size: 14px; line-height: 1.45;
            outline: none; transition: border-color .2s ease, box-shadow .2s ease;
        }
        .ai-input:focus { border-color: var(--blue-primary); box-shadow: 0 0 0 4px rgba(44, 95, 224, .12); }

        .ai-send {
            flex: 0 0 auto; border: 0; cursor: pointer; color: #fff; font-weight: 700; font-size: 14px;
            padding: 13px 22px; border-radius: 14px;
            background: linear-gradient(135deg, var(--blue-primary), var(--blue-sky));
            box-shadow: 0 14px 26px -14px rgba(44, 95, 224, .9);
            transition: transform .15s ease, opacity .2s ease;
        }
        .ai-send:hover:not(:disabled) { transform: translateY(-1px); }
        .ai-send:disabled { opacity: .5; cursor: default; }

        /* ---------- превью резюме ---------- */
        .ai-paper { position: sticky; top: 18px; }
        /* лист внутри карточки: рамку задаёт выбранное оформление */
        .ai-sheet {
            padding: 26px 28px 30px; min-height: 320px; margin: 18px 22px 22px;
            background: #fff; overflow: hidden;
            border: 1px solid rgba(44, 95, 224, .14); border-radius: 8px;
            box-shadow: 0 16px 36px -26px rgba(7, 20, 51, .5);
            transition: border-radius .25s ease, box-shadow .25s ease, border-color .25s ease;
        }

        .ai-empty {
            display: grid; place-items: center; text-align: center; gap: 10px;
            padding: 54px 20px; color: var(--slate-soft); font-size: 13.5px;
        }
        .ai-empty svg { opacity: .35; }

        .ai-sec { margin-bottom: 20px; animation: ai-rise .5s ease both; }
        .ai-sec:last-child { margin-bottom: 0; }

        .ai-sec-title {
            font-size: 11px; letter-spacing: 1.6px; text-transform: uppercase;
            color: var(--blue-primary); font-weight: 700; margin-bottom: 9px;
            padding-bottom: 6px; border-bottom: 1px solid rgba(44, 95, 224, .14);
        }

        .ai-name { font-family: 'Manrope', sans-serif; font-size: 25px; font-weight: 800; margin: 0; }
        .ai-role { font-size: 14.5px; color: var(--blue-primary); font-weight: 600; margin: 4px 0 0; }
        .ai-meta { font-size: 13px; color: var(--slate-soft); margin: 7px 0 0; }
        .ai-text { font-size: 13.5px; line-height: 1.65; color: var(--slate); margin: 0; }

        .ai-job { margin-bottom: 14px; }
        .ai-job:last-child { margin-bottom: 0; }
        .ai-job-head { display: flex; justify-content: space-between; gap: 10px; align-items: baseline; }
        .ai-job-role { font-weight: 700; font-size: 14px; }
        .ai-job-when { font-size: 12px; color: var(--slate-soft); white-space: nowrap; }
        .ai-job-co { font-size: 13px; color: var(--blue-primary); margin: 2px 0 6px; }
        .ai-job ul { margin: 0; padding-left: 17px; }
        .ai-job li, .ai-list li { font-size: 13.5px; line-height: 1.6; color: var(--slate); margin-bottom: 4px; }
        .ai-list { margin: 0; padding-left: 17px; }

        .ai-tags { display: flex; flex-wrap: wrap; gap: 6px; }
        .ai-tag {
            background: var(--blue-ice); color: var(--blue-primary); border: 1px solid rgba(44, 95, 224, .18);
            border-radius: 8px; padding: 5px 10px; font-size: 12.5px; font-weight: 600;
        }

        /* подсветка только что изменившегося блока */
        .ai-fresh { animation: ai-glow 1.5s ease; }

        /* ---------- выбор оформления ---------- */
        .ai-skins {
            display: flex; flex-wrap: wrap; gap: 7px; padding: 13px 26px;
            border-bottom: 1px solid rgba(44, 95, 224, .12); background: rgba(234, 241, 252, .45);
        }
        .ai-skin {
            display: inline-flex; align-items: center; gap: 7px; cursor: pointer;
            border: 1px solid rgba(44, 95, 224, .22); background: #fff; color: var(--slate);
            border-radius: 999px; padding: 6px 13px 6px 7px; font: 600 12.5px 'Inter', sans-serif;
            transition: border-color .18s ease, color .18s ease, transform .18s ease;
        }
        .ai-skin:hover { transform: translateY(-1px); border-color: var(--blue-primary); }
        .ai-skin[aria-pressed="true"] {
            border-color: var(--blue-primary); color: var(--navy-deep);
            box-shadow: 0 0 0 3px rgba(44, 95, 224, .12);
        }
        /* кружок-образец: видно, чем оформления отличаются, до нажатия */
        .ai-skin-mark { width: 16px; height: 16px; border-radius: 5px; flex: 0 0 16px; }
        .ai-skin-classic { background: linear-gradient(135deg, #2C5FE0, #5B8DEF); }
        .ai-skin-modern { background: linear-gradient(135deg, #0E2456 55%, #5B8DEF 55%); }
        .ai-skin-compact { background: repeating-linear-gradient(180deg, #2C5FE0 0 3px, #EAF1FC 3px 6px); }
        .ai-skin-strict { background: linear-gradient(135deg, #1F2937, #6B7280); }
        .ai-skin-elegant { background: linear-gradient(135deg, #8A6D3B, #D9C7A3); }
        .ai-skin-bold { background: linear-gradient(180deg, #0E2456 50%, #fff 50%); }
        .ai-skin-soft { background: linear-gradient(135deg, #EAF6F2, #9DD5C4); }

        /* ---------- оформление 2: современное ---------- */
        /* акцентная полоса слева, крупное имя, разделы без линеек */
        .ai-sheet[data-skin="modern"] {
            border-left: 5px solid var(--blue-primary); border-radius: 16px;
            border-color: #DCE6FA; box-shadow: 0 22px 44px -28px rgba(44, 95, 224, .75);
        }
        .ai-sheet[data-skin="modern"] .ai-name { font-size: 30px; letter-spacing: -.6px; }
        .ai-sheet[data-skin="modern"] .ai-role { font-size: 15px; color: var(--navy-mid); }
        .ai-sheet[data-skin="modern"] .ai-sec-title {
            border-bottom: 0; color: var(--navy-deep); letter-spacing: .8px; font-size: 11.5px;
            background: var(--blue-ice); display: inline-block; padding: 4px 10px; border-radius: 6px;
        }
        .ai-sheet[data-skin="modern"] .ai-tag { border-radius: 999px; background: #fff; border-color: rgba(44,95,224,.35); }
        .ai-sheet[data-skin="modern"] .ai-job { border-left: 2px solid var(--blue-ice); padding-left: 13px; }

        /* ---------- оформление 3: компактное ---------- */
        /* две колонки и мелкий шрифт: длинное резюме помещается целиком */
        .ai-sheet[data-skin="compact"] {
            column-count: 2; column-gap: 22px; font-size: 12.5px;
            padding: 20px 22px 22px; border-radius: 3px;
            box-shadow: 0 10px 24px -20px rgba(7, 20, 51, .55);
        }
        .ai-sheet[data-skin="compact"] .ai-sec { break-inside: avoid; margin-bottom: 14px; }
        .ai-sheet[data-skin="compact"] .ai-name { font-size: 20px; }
        .ai-sheet[data-skin="compact"] .ai-role { font-size: 13px; }
        .ai-sheet[data-skin="compact"] .ai-sec-title { font-size: 10px; margin-bottom: 6px; padding-bottom: 4px; }
        .ai-sheet[data-skin="compact"] .ai-text,
        .ai-sheet[data-skin="compact"] .ai-job li,
        .ai-sheet[data-skin="compact"] .ai-list li { font-size: 12.5px; line-height: 1.5; }
        .ai-sheet[data-skin="compact"] .ai-tag { font-size: 11.5px; padding: 3px 8px; }

        /* ---------- оформление 4: строгое ---------- */
        /* типографика без цвета — для консервативных отраслей */
        .ai-sheet[data-skin="strict"] {
            font-family: Georgia, 'Times New Roman', serif;
            /* прямые углы и жёсткая рамка — документ, а не карточка */
            border-radius: 0; border: 2px solid #111827; box-shadow: none;
        }
        .ai-sheet[data-skin="strict"] .ai-name { font-family: Georgia, serif; font-weight: 700; letter-spacing: -.2px; }
        .ai-sheet[data-skin="strict"] .ai-role,
        .ai-sheet[data-skin="strict"] .ai-job-co { color: #1F2937; font-style: italic; }
        .ai-sheet[data-skin="strict"] .ai-sec-title {
            color: #111827; border-bottom: 1px solid #111827; letter-spacing: 2px; font-family: 'Inter', sans-serif;
        }
        .ai-sheet[data-skin="strict"] .ai-tag {
            background: #fff; color: #1F2937; border: 0; border-radius: 0; padding: 2px 0;
            font-family: Georgia, serif;
        }
        /* без плашек список навыков читается строкой через запятую */
        .ai-sheet[data-skin="strict"] .ai-tag:not(:last-child)::after { content: ","; }
        .ai-sheet[data-skin="strict"] .ai-tags { gap: 5px; }

        /* ---------- оформление 5: элегантное ---------- */
        /* тёплый акцент и разрядка букв: спокойный тон без строгости */
        .ai-sheet[data-skin="elegant"] {
            --accent: #8A6D3B;
            /* тонкая рамка с отступом внутрь — как паспарту у гравюры */
            border-radius: 2px; border-color: rgba(138, 109, 59, .45);
            box-shadow: 0 0 0 1px rgba(138, 109, 59, .18) inset, 0 18px 40px -30px rgba(138, 109, 59, .8);
            padding: 30px 32px 34px;
        }
        .ai-sheet[data-skin="elegant"] .ai-name {
            font-weight: 600; letter-spacing: 3px; text-transform: uppercase; font-size: 21px;
        }
        .ai-sheet[data-skin="elegant"] .ai-role { color: var(--accent); font-style: italic; font-weight: 500; }
        .ai-sheet[data-skin="elegant"] .ai-sec-title {
            color: var(--accent); border-bottom-color: rgba(138, 109, 59, .35);
            letter-spacing: 2.4px; font-weight: 600;
        }
        .ai-sheet[data-skin="elegant"] .ai-tag {
            background: #FBF7EF; color: #6B5427; border-color: rgba(138, 109, 59, .3);
        }
        .ai-sheet[data-skin="elegant"] .ai-job-role { font-weight: 600; }

        /* ---------- оформление 6: контрастное ---------- */
        /* тёмная шапка: имя и должность читаются первыми */
        .ai-sheet[data-skin="bold"] {
            padding-top: 0; border: 0; border-radius: 12px;
            box-shadow: 0 24px 50px -28px rgba(14, 36, 86, .95);
        }
        .ai-sheet[data-skin="bold"] .ai-sec:first-child {
            background: var(--navy-mid); color: #fff; margin: -26px -28px 22px;
            padding: 24px 28px; border-radius: 0;
        }
        .ai-sheet[data-skin="bold"] .ai-sec:first-child .ai-name { color: #fff; }
        .ai-sheet[data-skin="bold"] .ai-sec:first-child .ai-role { color: #A8C4F5; }
        .ai-sheet[data-skin="bold"] .ai-sec:first-child .ai-meta { color: #C9D8F3; }
        .ai-sheet[data-skin="bold"] .ai-sec-title {
            color: var(--navy-mid); border-bottom-width: 2px; border-bottom-color: var(--navy-mid);
        }
        .ai-sheet[data-skin="bold"] .ai-tag {
            background: var(--navy-mid); color: #fff; border-color: transparent;
        }

        /* ---------- оформление 7: мягкое ---------- */
        /* пастельные блоки без линеек: дружелюбный вид для творческих профессий */
        .ai-sheet[data-skin="soft"] {
            --accent: #2F8F76;
            /* без рамки, крупные скругления и мягкая тень в тон */
            border: 0; border-radius: 22px;
            box-shadow: 0 22px 48px -30px rgba(47, 143, 118, .85);
        }
        .ai-sheet[data-skin="soft"] .ai-name { font-weight: 700; }
        .ai-sheet[data-skin="soft"] .ai-role { color: var(--accent); }
        .ai-sheet[data-skin="soft"] .ai-sec {
            background: #F4FAF8; border-radius: 14px; padding: 14px 16px;
        }
        .ai-sheet[data-skin="soft"] .ai-sec-title {
            border-bottom: 0; color: var(--accent); margin-bottom: 7px; padding-bottom: 0;
        }
        .ai-sheet[data-skin="soft"] .ai-tag {
            background: #fff; color: var(--accent); border-color: rgba(47, 143, 118, .3); border-radius: 999px;
        }
        .ai-sheet[data-skin="soft"] .ai-job { background: transparent; }

        /* ---------- финал ---------- */
        .ai-final { padding: 0 26px 24px; }
        .ai-final label { display: block; font-size: 12.5px; font-weight: 600; color: var(--slate); margin: 14px 0 6px; }
        .ai-final input, .ai-final textarea {
            width: 100%; border: 1px solid rgba(44, 95, 224, .25); border-radius: 12px;
            padding: 11px 13px; font-family: inherit; font-size: 13.5px; outline: none;
        }
        .ai-final textarea { min-height: 210px; line-height: 1.6; resize: vertical; }
        /* поле файла: системная кнопка выбивалась из вёрстки, поэтому input скрыт,
           а роль кнопки играет подпись к нему */
        .ai-file {
            display: inline-flex; align-items: center; gap: 9px; cursor: pointer;
            border: 1px dashed rgba(44, 95, 224, .4); border-radius: 12px;
            padding: 12px 16px; color: var(--blue-primary); font-weight: 600; font-size: 13.5px;
            background: var(--blue-ice); transition: border-color .2s ease, background .2s ease;
        }
        .ai-file:hover { border-color: var(--blue-primary); background: #DCE8FB; }
        .ai-file.picked { border-style: solid; color: var(--navy-deep); background: #fff; }

        .ai-attach {
            display: flex; align-items: center; justify-content: space-between; gap: 14px;
            flex-wrap: wrap; padding: 16px 26px;
            border-top: 1px solid rgba(44, 95, 224, .12);
        }
        .ai-attach-title { font-size: 13px; font-weight: 700; color: var(--navy-deep); }
        .ai-final input:focus, .ai-final textarea:focus { border-color: var(--blue-primary); }
        .ai-two { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        .ai-foot {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 16px 26px 20px; border-top: 1px solid rgba(44, 95, 224, .12); flex-wrap: wrap;
        }
        .ai-note { font-size: 12.5px; color: var(--slate-soft); }

        .ai-btn {
            border: 0; cursor: pointer; border-radius: 12px; padding: 12px 20px;
            font-size: 13.5px; font-weight: 700; transition: transform .15s ease, opacity .2s ease;
        }
        .ai-btn:hover:not(:disabled) { transform: translateY(-1px); }
        .ai-btn:disabled { opacity: .55; cursor: default; }
        .ai-btn.primary { color: #fff; background: linear-gradient(135deg, var(--blue-primary), var(--blue-sky)); }
        .ai-btn.ghost { background: #fff; color: var(--slate); border: 1px solid rgba(44, 95, 224, .22); }

        .ai-banner {
            margin: 0 0 18px; padding: 15px 18px; border-radius: 14px; font-size: 13.5px; line-height: 1.55;
            background: #FFF7ED; border: 1px solid #FDBA74; color: #9A3412;
        }
        .ai-banner a { color: #9A3412; text-decoration: underline; font-weight: 600; }

        @keyframes ai-rise { from { opacity: 0; transform: translateY(9px); } to { opacity: 1; transform: none; } }
        @keyframes ai-blink { 50% { opacity: 0; } }
        @keyframes ai-bounce { 0%, 80%, 100% { transform: translateY(0); opacity: .5; } 40% { transform: translateY(-4px); opacity: 1; } }
        @keyframes ai-glow {
            0% { background: rgba(44, 95, 224, .13); box-shadow: 0 0 0 6px rgba(44, 95, 224, .09); border-radius: 10px; }
            100% { background: transparent; box-shadow: none; border-radius: 10px; }
        }

        /* уважаем системную настройку: без анимаций всё должно оставаться читаемым */
        @media (prefers-reduced-motion: reduce) {
            .ai-msg, .ai-sec { animation: none; }
            .ai-fresh { animation: none; }
            .ai-progress span { transition: none; }
        }

        @media (max-width: 1200px) {
            .ai-grid { grid-template-columns: 1fr; }
            .ai-paper { position: static; }
        }
    </style>
</head>
<body>
<div class="app">

    @include('applicant.partials.sidebar')

    <main class="main">

        <div class="topbar">
            <div>
                <h1 id="pageTitle">{{ __('Резюме-бот') }}</h1>
                <p id="pageSubtitle">{{ __('Расскажите о себе своими словами — резюме соберётся само') }}</p>
            </div>
            @include('partials.notification-bell')
            <div class="user-chip">
                <div class="avatar" style="padding: 14px 16px;">
                    @if($user->hasAvatar())
                        <img src="{{ asset($user->avatar) }}" alt="{{ $user->name }}"
                             style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                    @else
                        <div class="avatar text-white d-flex align-items-center justify-content-center"
                             style="font-size:13px; font-weight:700; background:#1656D6;">{{ $user->initials(1) }}</div>
                    @endif
                </div>
                <div>
                    <div class="name">{{ $user->name }}</div>
                    <div class="role">{{ $user->email }}</div>
                </div>
            </div>
        </div>

        <section class="section active" id="section-profile">
            <div class="ai-wrap">

                @unless($enabled)
                    <div class="ai-banner">
                        {{ __('Помощник пока выключен: в файле .env не задан GEMINI_API_KEY. Пока можно') }}
                        <a href="{{ route('applicant.resume.create') }}">{{ __('заполнить резюме обычной формой') }}</a>.
                    </div>
                @endunless

                <div class="ai-grid">

                    {{-- ================= ДИАЛОГ ================= --}}
                    <div class="ai-card">
                        <div class="ai-head">
                            <div>
                                <p class="ai-eyebrow">{{ __('Разговор') }}</p>
                                <h2 class="ai-title">{{ __('Соберём резюме вместе') }}</h2>
                                <p class="ai-sub">{{ __('Отвечайте так, как рассказали бы знакомому. Уточняющие вопросы задам я.') }}</p>
                            </div>
                            {{-- «Собрать новое» стоит в шапке, а не среди быстрых реплик:
                                 внизу его не находили. Текущий разговор при этом
                                 никуда не девается — остаётся в списке ниже. --}}
                            <form action="{{ route('applicant.resume.assistant.start') }}" method="post" style="margin:0;">
                                @csrf
                                <button type="submit" class="ai-new" title="{{ __('Начать отдельный разговор; текущий сохранится') }}">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 5v14M5 12h14"/>
                                    </svg>
                                    {{ __('Собрать новое резюме') }}
                                </button>
                            </form>
                        </div>

                        {{-- История разговоров, как список переписок в чате:
                             ничего не теряется, к любому можно вернуться --}}
                        @if($conversations->count() > 1)
                            <div class="ai-threads">
                                @foreach($conversations as $thread)
                                    <span class="{{ $thread->id === $draft->id ? 'ai-thread on' : 'ai-thread' }}">
                                        <a class="ai-thread-link"
                                           href="{{ route('applicant.resume.assistant.open', $thread) }}">
                                            <span class="{{ $thread->finished() ? 'ai-thread-dot done' : 'ai-thread-dot' }}"></span>
                                            <span class="ai-thread-name">{{ $thread->title() }}</span>
                                            <span class="ai-thread-when">
                                                @if($thread->finished())
                                                    {{ __('опубликовано') }}
                                                @else
                                                    {{ $thread->updated_at?->format('d.m') }}
                                                @endif
                                            </span>
                                        </a>
                                        {{-- удаление разговора; опубликованное резюме при этом остаётся --}}
                                        <form action="{{ route('applicant.resume.assistant.destroy', $thread) }}"
                                              method="post" style="margin:0; display:flex;"
                                              onsubmit="return confirm('{{ __('Удалить этот разговор? Отменить не получится.') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ai-thread-x"
                                                    title="{{ __('Удалить разговор') }}"
                                                    aria-label="{{ __('Удалить разговор') }}">&times;</button>
                                        </form>
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        <div class="ai-progress"><span id="aiProgress" style="width: {{ $progress }}%"></span></div>

                        <div class="ai-feed" id="aiFeed">
                            @foreach($messages as $message)
                                <div class="ai-msg {{ $message['role'] === 'user' ? 'me' : '' }}">
                                    <div class="ai-ava">{{ $message['role'] === 'user' ? $user->initials(1) : 'AI' }}</div>
                                    <div class="ai-bubble">{{ $message['text'] }}</div>
                                </div>
                            @endforeach
                        </div>

                        <div class="ai-compose">
                            <div class="ai-chips">
                                {{-- быстрые реплики: «пропустить» и «исправить» помощник понимает
                                     как обычный текст, отдельной механики для них не нужно --}}
                                <button type="button" class="ai-chip" data-say="{{ __('Давайте пропустим этот раздел.') }}">{{ __('Пропустить') }}</button>
                                <button type="button" class="ai-chip" data-say="{{ __('Хочу исправить предыдущий ответ: ') }}" data-keep>{{ __('Исправить ответ') }}</button>
                                <button type="button" class="ai-chip" data-say="{{ __('Думаю, данных достаточно — соберите резюме.') }}">{{ __('Данных достаточно') }}</button>
                            </div>

                            <div class="ai-row">
                                <textarea class="ai-input" id="aiInput" rows="1"
                                          placeholder="{{ __('Напишите ответ и нажмите Enter…') }}"
                                          @unless($enabled) disabled @endunless></textarea>
                                <button class="ai-send" id="aiSend" @unless($enabled) disabled @endunless>{{ __('Отправить') }}</button>
                            </div>
                        </div>
                    </div>

                    {{-- ================= ЖИВОЕ ПРЕВЬЮ ================= --}}
                    <div class="ai-paper">
                        <div class="ai-card">
                            <div class="ai-head">
                                <div>
                                    <p class="ai-eyebrow">{{ __('Черновик') }}</p>
                                    <h2 class="ai-title">{{ __('Ваше резюме') }}</h2>
                                    <p class="ai-sub">{{ __('Обновляется прямо во время разговора') }}</p>
                                </div>
                            </div>

                            {{-- Оформление меняет только вид листа: разметка одна,
                                 отличаются стили. Выбор уходит в резюме вместе с текстом. --}}
                            <div class="ai-skins" role="group" aria-label="{{ __('Оформление резюме') }}">
                                @foreach(\App\Models\Resume::STYLES as $key => $title)
                                    <button type="button" class="ai-skin" data-skin="{{ $key }}"
                                            @if($loop->first) aria-pressed="true" @else aria-pressed="false" @endif>
                                        <span class="ai-skin-mark ai-skin-{{ $key }}"></span>
                                        {{ __($title) }}
                                    </button>
                                @endforeach
                                <input type="hidden" name="style" id="fStyle" form="aiSaveForm" value="classic">
                            </div>

                            <div class="ai-sheet" id="aiSheet" data-skin="classic"></div>

                            <div class="ai-final" id="aiFinal" hidden>
                                <div class="ai-sec-title" style="margin-bottom:2px;">{{ __('Финальная версия') }}</div>
                                <p class="ai-note">{{ __('Проверьте и поправьте текст — сохранится именно он.') }}</p>

                                <form action="{{ route('applicant.resume.assistant.save', $draft) }}" method="post"
                                      id="aiSaveForm" enctype="multipart/form-data">
                                    @csrf
                                    <div class="ai-two">
                                        <div>
                                            <label>{{ __('Профессия') }}</label>
                                            <input type="text" name="profession" id="fProfession" maxlength="255" required>
                                        </div>
                                        <div>
                                            <label>{{ __('Желаемая должность') }}</label>
                                            <input type="text" name="desired_position" id="fPosition" maxlength="255" required>
                                        </div>
                                    </div>
                                    <div class="ai-two">
                                        <div>
                                            <label>{{ __('Опыт, лет') }}</label>
                                            <input type="number" name="experience_years" id="fYears" min="0" max="70" required>
                                        </div>
                                        <div>
                                            <label>{{ __('Желаемая зарплата') }}</label>
                                            <input type="number" name="desired_salary" id="fSalary" min="0" required>
                                        </div>
                                    </div>
                                    <label>{{ __('Навыки') }}</label>
                                    <input type="text" name="skills" id="fSkills" maxlength="2000">
                                    <div class="ai-two">
                                        <div>
                                            <label>{{ __('Языки') }}</label>
                                            <input type="text" name="languages" id="fLanguages" maxlength="255" required>
                                        </div>
                                        <div>
                                            <label>{{ __('Место работы') }}</label>
                                            <input type="text" name="place_work" id="fPlace" maxlength="255">
                                        </div>
                                    </div>
                                    <label>{{ __('Текст резюме') }}</label>
                                    <textarea name="description" id="fDescription"></textarea>
                                </form>
                            </div>

                            {{-- Документ виден с самого начала, а не только на последнем шаге:
                                 спрятанное за кнопкой поле никто не находил. Поле живёт вне
                                 формы, а привязано к ней атрибутом form — так его можно
                                 показать где угодно на странице. --}}
                            <div class="ai-attach">
                                <div>
                                    <div class="ai-attach-title">{{ __('Документ') }}
                                        <span class="ai-note">{{ __('необязательно') }}</span></div>
                                    <p class="ai-note" style="margin:3px 0 0;">{{ __('Диплом, сертификат или готовое резюме: PDF, DOC, DOCX до 5 МБ') }}</p>
                                </div>
                                <label class="ai-file" for="fDocuments">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                                    </svg>
                                    <span id="fDocumentsName">{{ __('Прикрепить файл') }}</span>
                                    <input type="file" name="documents" id="fDocuments" form="aiSaveForm"
                                           accept=".pdf,.doc,.docx" hidden>
                                </label>
                            </div>

                            <div class="ai-foot">
                                <span class="ai-note" id="aiHint">{{ __('Соберём финальную версию, когда расскажете об опыте и навыках.') }}</span>
                                <div style="display:flex; gap:9px;">
                                    <button type="button" class="ai-btn primary" id="aiFinalize" @unless($enabled) disabled @endunless>
                                        {{ __('Сгенерировать финальную версию') }}
                                    </button>
                                    <button type="submit" form="aiSaveForm" class="ai-btn ghost" id="aiSave" hidden>
                                        {{ __('Сохранить резюме') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>
    </main>
</div>

<script>
    (function () {
        const feed = document.getElementById('aiFeed');
        const input = document.getElementById('aiInput');
        const send = document.getElementById('aiSend');
        const sheet = document.getElementById('aiSheet');
        const bar = document.getElementById('aiProgress');
        const hint = document.getElementById('aiHint');
        const finalizeBtn = document.getElementById('aiFinalize');
        const saveBtn = document.getElementById('aiSave');
        const finalBox = document.getElementById('aiFinal');

        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const urls = {
            message: @json(route('applicant.resume.assistant.message', $draft)),
            finalize: @json(route('applicant.resume.assistant.finalize', $draft)),
        };
        const initials = @json($user->initials(1));
        const calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        let resume = @json($resume ?: (object) []);
        let busy = false;
        // отложенный автоповтор: держим ссылку, чтобы отменить его, если
        // человек тем временем написал что-то сам
        let pendingRetry = null;

        /* ---------- лента ---------- */

        function scrollDown() {
            feed.scrollTop = feed.scrollHeight;
        }

        function bubble(role, text, className) {
            const row = document.createElement('div');
            row.className = 'ai-msg' + (role === 'user' ? ' me' : '');
            row.innerHTML = '<div class="ai-ava"></div><div class="ai-bubble' + (className ? ' ' + className : '') + '"></div>';
            row.querySelector('.ai-ava').textContent = role === 'user' ? initials : 'AI';
            row.querySelector('.ai-bubble').textContent = text || '';
            feed.appendChild(row);
            scrollDown();
            return row.querySelector('.ai-bubble');
        }

        // «печатает…» — пока ждём ответ модели, экран не должен выглядеть замершим
        function thinking() {
            const row = document.createElement('div');
            row.className = 'ai-msg';
            row.innerHTML = '<div class="ai-ava">AI</div>'
                + '<div class="ai-bubble"><span class="ai-dots"><i></i><i></i><i></i></span></div>';
            feed.appendChild(row);
            scrollDown();
            return row;
        }

        // ответ появляется словами, а не мгновенным блоком: так разговор
        // читается как живой, а не как выгрузка из базы
        function type(node, text) {
            if (calm) {
                node.textContent = text;
                scrollDown();
                return Promise.resolve();
            }

            return new Promise(resolve => {
                const words = text.split(/(\s+)/);
                let i = 0;
                node.classList.add('typing');

                (function step() {
                    if (i >= words.length) {
                        node.classList.remove('typing');
                        return resolve();
                    }
                    node.textContent += words[i++];
                    scrollDown();
                    setTimeout(step, 28);
                })();
            });
        }

        /* ---------- превью ---------- */

        const esc = value => String(value ?? '').replace(/[&<>"]/g, c => (
            {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]
        ));

        const filled = value => Array.isArray(value) ? value.length > 0 : Boolean(String(value ?? '').trim());

        function section(title, body) {
            return '<div class="ai-sec"><div class="ai-sec-title">' + esc(title) + '</div>' + body + '</div>';
        }

        function list(items) {
            return '<ul class="ai-list">' + items.map(i => '<li>' + esc(i) + '</li>').join('') + '</ul>';
        }

        function renderResume(data) {
            const parts = [];
            const head = [];

            if (filled(data.name)) head.push('<h3 class="ai-name">' + esc(data.name) + '</h3>');
            if (filled(data.headline)) head.push('<p class="ai-role">' + esc(data.headline) + '</p>');

            const meta = [data.desired_position, data.city].filter(filled).map(esc);
            if (filled(data.experience_years)) meta.push(esc(data.experience_years) + ' ' + @json(__('лет опыта')));
            if (meta.length) head.push('<p class="ai-meta">' + meta.join(' · ') + '</p>');
            if (head.length) parts.push('<div class="ai-sec">' + head.join('') + '</div>');

            if (filled(data.summary)) {
                parts.push(section(@json(__('О себе')), '<p class="ai-text">' + esc(data.summary) + '</p>'));
            }

            if (filled(data.positions)) {
                const jobs = data.positions.map(job => {
                    const bullets = filled(job.bullets)
                        ? '<ul>' + job.bullets.map(b => '<li>' + esc(b) + '</li>').join('') + '</ul>' : '';
                    return '<div class="ai-job">'
                        + '<div class="ai-job-head"><span class="ai-job-role">' + esc(job.role || '') + '</span>'
                        + (filled(job.period) ? '<span class="ai-job-when">' + esc(job.period) + '</span>' : '')
                        + '</div>'
                        + (filled(job.company) ? '<div class="ai-job-co">' + esc(job.company) + '</div>' : '')
                        + bullets + '</div>';
                }).join('');
                parts.push(section(@json(__('Опыт работы')), jobs));
            }

            if (filled(data.achievements)) {
                parts.push(section(@json(__('Достижения')), list(data.achievements)));
            }

            if (filled(data.skills)) {
                parts.push(section(@json(__('Навыки')),
                    '<div class="ai-tags">' + data.skills.map(s => '<span class="ai-tag">' + esc(s) + '</span>').join('') + '</div>'));
            }

            if (filled(data.languages)) {
                parts.push(section(@json(__('Языки')),
                    '<div class="ai-tags">' + data.languages.map(s => '<span class="ai-tag">' + esc(s) + '</span>').join('') + '</div>'));
            }

            if (filled(data.education)) {
                const rows = data.education.map(e => '<div class="ai-job">'
                    + '<div class="ai-job-head"><span class="ai-job-role">' + esc(e.program || e.place || '') + '</span>'
                    + (filled(e.year) ? '<span class="ai-job-when">' + esc(e.year) + '</span>' : '') + '</div>'
                    + (filled(e.place) && filled(e.program) ? '<div class="ai-job-co">' + esc(e.place) + '</div>' : '')
                    + '</div>').join('');
                parts.push(section(@json(__('Образование')), rows));
            }

            if (filled(data.extras)) {
                data.extras.forEach(block => {
                    if (filled(block.items)) parts.push(section(block.title || @json(__('Дополнительно')), list(block.items)));
                });
            }

            if (!parts.length) {
                sheet.innerHTML = '<div class="ai-empty">'
                    + '<svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">'
                    + '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>'
                    + '<path d="M8 13h8M8 17h5"/></svg>'
                    + '<span>' + @json(__('Здесь появится резюме — по мере того, как вы отвечаете на вопросы.')) + '</span></div>';
                return;
            }

            // сравниваем с прошлым рендером: подсвечиваем только то,
            // что действительно изменилось, а не всю страницу целиком
            const before = Array.from(sheet.querySelectorAll('.ai-sec')).map(n => n.innerHTML);
            sheet.innerHTML = parts.join('');

            if (!calm) {
                sheet.querySelectorAll('.ai-sec').forEach((node, i) => {
                    if (before[i] !== node.innerHTML) node.classList.add('ai-fresh');
                    node.style.animationDelay = (i * 60) + 'ms';
                });
            }
        }

        /* ---------- обмен с сервером ---------- */

        async function ask(url, body) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(body || {}),
            });

            let data = {};
            try { data = await response.json(); } catch (e) { /* пустое тело — обработаем ниже */ }

            if (!response.ok) {
                const error = new Error(data.error || @json(__('Не удалось связаться с помощником. Попробуйте ещё раз.')));
                // сервер сказал, через сколько повторять — пробрасываем дальше
                error.retryAfter = data.retry_after || 0;
                throw error;
            }

            return data;
        }

        function lock(state) {
            busy = state;
            send.disabled = state;
            finalizeBtn.disabled = state;
            input.disabled = state;
        }

        // ошибка не должна съедать написанное: текст возвращается в поле,
        // а в ленте остаётся кнопка «Повторить». Если провайдер назвал паузу
        // (бесплатный ключ упирается в лимит запросов), показываем обратный
        // отсчёт и повторяем сами — человеку не за что себя винить и нечего
        // нажимать, разговор просто продолжается через несколько секунд.
        function failure(message, retry, seconds) {
            const node = bubble('model', message, 'err');
            const again = document.createElement('button');
            again.type = 'button';
            node.appendChild(again);

            let left = Math.max(0, Number(seconds) || 0);
            let timer = null;

            const fire = () => {
                if (timer) clearInterval(timer);
                pendingRetry = null;
                node.closest('.ai-msg').remove();
                retry();
            };

            pendingRetry = () => {
                if (timer) clearInterval(timer);
                again.textContent = @json(__('Повторить'));
            };

            again.addEventListener('click', fire);

            if (!left) {
                again.textContent = @json(__('Повторить'));
                scrollDown();
                return;
            }

            const tick = () => {
                again.textContent = left > 0
                    ? @json(__('Повтор через')) + ' ' + left + ' ' + @json(__('с'))
                    : @json(__('Повторяю…'));
                if (left-- <= 0) fire();
            };

            tick();
            timer = setInterval(tick, 1000);
            scrollDown();
        }

        async function sendMessage(text) {
            if (busy || !text.trim()) return;

            // человек написал сам — отложенный автоповтор больше не нужен,
            // иначе он выстрелит поверх нового сообщения
            if (pendingRetry) { pendingRetry(); pendingRetry = null; }

            const mine = bubble('user', text).closest('.ai-msg');
            input.value = '';
            input.style.height = 'auto';
            lock(true);

            const waiting = thinking();

            try {
                const data = await ask(urls.message, {message: text});
                waiting.remove();

                const node = bubble('model', '');
                await type(node, data.reply);

                resume = data.resume || {};
                renderResume(resume);
                bar.style.width = (data.progress || 0) + '%';

                if (data.done) {
                    hint.textContent = @json(__('Готово — можно собрать финальную версию.'));
                    finalizeBtn.classList.add('ai-fresh');
                }
            } catch (e) {
                waiting.remove();
                // свою реплику убираем: при повторе она добавится заново,
                // иначе в ленте копились бы её копии
                mine.remove();
                input.value = text;
                failure(e.message, () => sendMessage(text), e.retryAfter);
            } finally {
                lock(false);
                input.focus();
            }
        }

        async function finalize() {
            if (busy) return;
            lock(true);
            finalizeBtn.textContent = @json(__('Собираю…'));

            try {
                const data = await ask(urls.finalize, {});
                const f = data.final || {};

                document.getElementById('fProfession').value = f.profession || '';
                document.getElementById('fPosition').value = f.desired_position || '';
                document.getElementById('fYears').value = f.experience_years ?? 0;
                document.getElementById('fSalary').value = f.desired_salary ?? 0;
                document.getElementById('fSkills').value = f.skills || '';
                document.getElementById('fLanguages').value = f.languages || '';
                document.getElementById('fPlace').value = f.place_work || '';
                document.getElementById('fDescription').value = f.description || '';

                finalBox.hidden = false;
                saveBtn.hidden = false;
                hint.textContent = @json(__('Проверьте текст и сохраните — резюме появится в списке.'));
                finalBox.scrollIntoView({behavior: calm ? 'auto' : 'smooth', block: 'nearest'});
            } catch (e) {
                failure(e.message, finalize, e.retryAfter);
            } finally {
                lock(false);
                finalizeBtn.textContent = @json(__('Сгенерировать финальную версию'));
            }
        }

        /* ---------- события ---------- */

        send.addEventListener('click', () => sendMessage(input.value));
        finalizeBtn.addEventListener('click', finalize);

        // Оформление резюме. Меняется только класс листа — разметка и данные
        // те же, поэтому переключение мгновенное и ничего не пересобирает.
        const sheetStyle = document.getElementById('fStyle');
        const skins = document.querySelectorAll('.ai-skin');

        function applySkin(skin, remember) {
            if (!skin) return;

            sheet.dataset.skin = skin;
            sheetStyle.value = skin;
            skins.forEach(b => b.setAttribute('aria-pressed', String(b.dataset.skin === skin)));

            // выбор переживает перезагрузку страницы; окончательно он
            // сохраняется вместе с резюме, в скрытом поле формы
            if (remember) {
                try { localStorage.setItem('workio.resume.skin', skin); } catch (e) { /* приватный режим */ }
            }
        }

        skins.forEach(button => {
            button.addEventListener('click', () => applySkin(button.dataset.skin, true));
        });

        try {
            const saved = localStorage.getItem('workio.resume.skin');
            if (saved && document.querySelector('.ai-skin[data-skin="' + saved + '"]')) {
                applySkin(saved, false);
            }
        } catch (e) { /* хранилище недоступно — остаётся оформление по умолчанию */ }

        // имя выбранного файла вместо «Прикрепить файл»: иначе непонятно,
        // выбрал человек что-нибудь или промахнулся мимо кнопки
        const docs = document.getElementById('fDocuments');
        docs.addEventListener('change', () => {
            const picked = docs.files && docs.files[0];
            document.getElementById('fDocumentsName').textContent = picked
                ? picked.name
                : @json(__('Прикрепить файл'));
            docs.closest('.ai-file').classList.toggle('picked', Boolean(picked));
        });

        input.addEventListener('keydown', event => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendMessage(input.value);
            }
        });

        // поле растёт под текст, но не бесконечно
        input.addEventListener('input', () => {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 130) + 'px';
        });

        document.querySelectorAll('.ai-chip[data-say]').forEach(chip => {
            chip.addEventListener('click', () => {
                const text = chip.dataset.say;
                // «Исправить ответ» подставляет начало фразы: дальше человек
                // дописывает сам, отправлять пустую заготовку бессмысленно
                if (chip.hasAttribute('data-keep')) {
                    input.value = text;
                    input.focus();
                    return;
                }
                sendMessage(text);
            });
        });

        renderResume(resume || {});
        scrollDown();
        if (!input.disabled) input.focus();
    })();
</script>

@include('applicant.partials.js')
@include('applicant.partials.script')
</body>
</html>
