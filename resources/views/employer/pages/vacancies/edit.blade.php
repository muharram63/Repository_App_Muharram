<!DOCTYPE html>
<html lang="ru" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Личный кабинет') }}</title>
    @include('employer.partials.css')
    <style>
        :root {
            --navy-deep: #071433;
            --navy-mid: #0E2456;
            --blue-primary: #2C5FE0;
            --blue-sky: #5B8DEF;
            --blue-ice: #EAF1FC;
            --slate: #44506B;
            --slate-soft: #8195B8;
            --white: #FFFFFF;
            --glass: rgba(255, 255, 255, 0.9);
            --radius: 20px;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--navy-deep);
            min-height: 100vh;
            overflow-x: hidden;
            background: var(--navy-deep);
        }

        #wave-bg {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            display: block;
        }

        .stage {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
        }

        .form-card {
            width: 100%;
            max-width: 760px;
            background: var(--glass);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: var(--radius);
            box-shadow: 0 40px 80px -30px rgba(5, 15, 45, 0.55),
            0 0 0 1px rgba(255, 255, 255, 0.4) inset;
            overflow: hidden;
        }

        .card-head {
            padding: 34px 40px 24px;
            border-bottom: 1px solid rgba(44, 95, 224, 0.12);
            position: relative;
        }

        .card-head::before {
            content: "";
            position: absolute;
            left: 40px;
            top: 0;
            width: 46px;
            height: 4px;
            border-radius: 0 0 4px 4px;
            background: linear-gradient(90deg, var(--blue-primary), var(--blue-sky));
        }

        .eyebrow {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 12px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--blue-primary);
            font-weight: 600;
            margin: 14px 0 6px;
        }

        .card-head h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 27px;
            margin: 0;
            line-height: 1.2;
        }

        .card-head p {
            margin: 8px 0 0;
            font-size: 14px;
            color: var(--slate-soft);
        }

        .card-body {
            padding: 32px 40px 8px;
        }

        .field-group {
            margin-bottom: 22px;
        }

        .field-group.split {
            display: grid;
            grid-template-columns:1fr 1fr;
            gap: 18px;
            margin-bottom: 22px;
        }

        .field-group.triple {
            display: grid;
            grid-template-columns:1fr 1fr 0.75fr;
            gap: 18px;
            margin-bottom: 22px;
        }

        label {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--slate);
            margin-bottom: 8px;
            letter-spacing: .2px;
        }

        label .opt {
            font-weight: 400;
            color: var(--slate-soft);
            text-transform: none;
            letter-spacing: 0;
        }

        input, select, textarea {
            width: 100%;
            font-family: 'Inter', sans-serif;
            font-size: 14.5px;
            color: var(--navy-deep);
            background: #FBFCFF;
            border: 1.5px solid #DCE7FA;
            border-radius: 12px;
            padding: 12px 14px;
            transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
            line-height: 1.55;
        }

        input:hover, select:hover, textarea:hover {
            border-color: var(--blue-sky);
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--blue-primary);
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(44, 95, 224, 0.14);
        }

        select {
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%232C5FE0' stroke-width='3'><path d='M6 9l6 6 6-6'/></svg>");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 36px;
        }

        .pill-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .pill {
            position: relative;
        }

        .pill input {
            position: absolute;
            opacity: 0;
            inset: 0;
            width: 100%;
            height: 100%;
            margin: 0;
            cursor: pointer;
        }

        .pill span {
            display: block;
            padding: 10px 18px;
            border-radius: 999px;
            border: 1.5px solid #DCE7FA;
            font-size: 13.5px;
            font-weight: 600;
            color: var(--slate);
            background: #FBFCFF;
            transition: all .16s ease;
        }

        .pill input:checked + span {
            background: linear-gradient(135deg, var(--blue-primary), var(--blue-sky));
            border-color: transparent;
            color: var(--white);
            box-shadow: 0 10px 20px -8px rgba(44, 95, 224, 0.65);
        }

        .pill input:focus-visible + span {
            outline: 2px solid var(--navy-mid);
            outline-offset: 2px;
        }

        .divider-line {
            height: 1px;
            background: linear-gradient(90deg, transparent, #DCE7FA, transparent);
            margin: 28px 0;
        }

        .card-foot {
            padding: 26px 40px 36px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .card-foot .note {
            font-size: 12px;
            color: var(--slate-soft);
        }

        .submit-btn {
            position: relative;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 600;
            font-size: 15px;
            color: var(--white);
            background: linear-gradient(135deg, var(--blue-primary) 0%, var(--navy-mid) 130%);
            border: none;
            padding: 14px 30px;
            border-radius: 12px;
            cursor: pointer;
            box-shadow: 0 16px 30px -14px rgba(14, 36, 86, 0.7);
            transition: transform .15s ease, box-shadow .15s ease;
            overflow: hidden;
        }

        .submit-btn::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, transparent 30%, rgba(255, 255, 255, 0.35) 50%, transparent 70%);
            transform: translateX(-120%);
            transition: transform .5s ease;
        }

        .submit-btn:hover::after {
            transform: translateX(120%);
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 36px -14px rgba(14, 36, 86, 0.8);
        }

        .submit-btn:focus-visible {
            outline: 2px solid var(--white);
            outline-offset: 3px;
        }

        @media (max-width: 640px) {
            .card-head, .card-body, .card-foot {
                padding-left: 24px;
                padding-right: 24px;
            }

            .field-group.split, .field-group.triple {
                grid-template-columns:1fr;
            }

            .card-head h1 {
                font-size: 23px;
            }
        }
    </style>
</head>
<body>


@if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
<div class="app">

    <!-- ================= SIDEBAR ================= -->
    @include('employer.partials.sidebar')

    <!-- ================= MAIN ================= -->
    <main class="main">

        <div class="topbar">
            <div>
                <h1 id="pageTitle">{{ __('Вакансия') }}</h1>
                <p id="pageSubtitle">{{ __('Заполните анкету — это поможет вам найти сотрудника быстрее!') }}</p>
            </div>
            @include('partials.notification-bell')
            <div class="user-chip">
                <div class="avatar" style="padding: 14px 16px;">
                    @if($employer->user?->hasAvatar())
                        <img src="{{ asset($user->avatar) }}" alt="Avatar"
                             style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                    @else
                        <div class="avatar text-white d-flex align-items-center justify-content-center"
                             style="font-size:13px; font-weight:700; background:#1656D6;">
                            {{ $user->initials(1) }}
                        </div>
                    @endif
                </div>
                <div>
                    <div class="name">{{ $employer->user->name }}</div>
                    <div class="role">{{ $employer->email_company }}</div>
                </div>
            </div>
        </div>

        <!-- ---------- SECTION: PROFILE ---------- -->
        <section class="section active" id="section-profile">

            <div class="">

                <canvas id="wave-bg"></canvas>

                <div>
                    <div class="form-card" style="margin-left: 17vh;">
                        <form action="{{route('employer.vacancies.update',$vacancy)}}" method="post">
                            @csrf
                            @method('PUT')

                            <div class="card-head">

                                <p class="eyebrow">{{ __('Новая публикация') }}</p>
                                <h1>{{ __('Изменение вакансии') }}</h1>
                                <p>{{ __('Заполните карточку — кандидаты увидят её в списке открытых позиций.') }}</p>
                            </div>

                            <div class="card-body">

                                <div class="field-group">
                                    <label>{{ __('Название должности') }}</label>
                                    <input type="text" placeholder="{{ __('Например: Backend-разработчик (Laravel)') }}"
                                           name="title" value="{{old('title',$vacancy->title)}}">
                                </div>

                                <div class="field-group">
                                    <label>{{ __('Описание вакансии') }}</label>
                                    <textarea placeholder="{{ __('Обязанности, команда, проект, стек...') }}"
                                              name="description"></textarea>
                                </div>

                                <div class="field-group split">
                                    <div>
                                        <label>{{ __('Компания') }}</label>
                                        <select name="employer_id" style="width: 37vh;">
                                            @foreach($employers as $employer)
                                                <option value="{{$employer->id}}">{{$employer->company_name}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label>{{ __('Страна,Регион') }}</label>
                                        <select name="city_id" style="width: 70vh;">
                                            @foreach($cities as $city)
                                                <option value="{{$city->id}}">{{ __($city->country) }}
                                                    ,{{ __($city->region) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="divider-line"></div>

                                <div class="field-group triple">
                                    <div>
                                        <label>{{ __('Зарплата от') }}</label>
                                        <input type="number" placeholder="1500" name="salary_from" style="width: 30vh;"
                                               value="{{old('salary_from',$vacancy->salary_from)}}">
                                    </div>
                                    <div>
                                        <label>{{ __('Зарплата до') }}</label>
                                        <input type="number" placeholder="2500" name="salary_to" style="width: 30vh;"
                                               value="{{old('salary_to',$vacancy->salary_to)}}">
                                    </div>
                                    <div>
                                        <label>{{ __('Валюта') }}</label>
                                        <input type="text" value="{{ __('сомони') }}" style="width: 37vh;" readonly>
                                        <input type="hidden" name="currency" value="TJS">
                                    </div>
                                </div>

                                <div class="field-group triple">
                                    <div>
                                        <label>{{ __('Тип занятости') }}</label>
                                        <select name="employment_type">
                                            <option value="full-time">{{ __('Полная занятость') }}</option>
                                            <option value="part-time">{{ __('Частичная занятость') }}</option>
                                            <option value="project_work">{{ __('Проектная работа') }}</option>
                                            <option value="internship">{{ __('Стажировка') }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label>{{ __('График работы') }}</label>
                                        <select name="work_schedule">
                                            <option value="full_day">{{ __('Полный день') }}</option>
                                            <option value="flexible_schedule">{{ __('Гибкий график') }}</option>
                                            <option value="remote_work">{{ __('Удалённая работа') }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label>{{ __('Опыт работы') }}</label>
                                        <select name="experience_required">
                                            <option value="not">{{ __('Без опыта') }}</option>
                                            <option value="year">{{ __('От 1 года') }}</option>
                                            <option value="3_years">{{ __('От 3 лет') }}</option>
                                            <option value="3-6years">{{ __('3–6 лет') }}</option>
                                            <option value="more_6years">{{ __('Более 6 лет') }}</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="field-group">
                                    <label>{{ __('Навыки') }} <span class="opt">{{ __('(через запятую)') }}</span></label>
                                    <input type="text" placeholder="PHP, Laravel, MySQL, Docker" name="skill"
                                           value="{{old('skill',$vacancy->skill)}}">
                                </div>

                                <div class="field-group">
                                    <label>{{ __('Языки') }} <span class="opt">{{ __('(через запятую / необязательно)') }}</span></label>
                                    <input type="text" placeholder="{{ __('Таджикский, русский, английский B2') }}" name="languages"
                                           value="{{old('languages',$vacancy->languages)}}">
                                </div>

                                <div class="divider-line"></div>

                                <div class="field-group">
                                    <label>{{ __('Статус публикации') }}</label>
                                    <div class="pill-group">
                                        <label class="pill">
                                            <input type="radio" name="status" value="active"
                                                    {{ old('status', $vacancy->status ?? 'active') == 'active' ? 'checked' : '' }}>
                                            <span>{{ __('Активна') }}</span>
                                        </label>
                                        <label class="pill">
                                            <input type="radio" name="status" value="inactive"
                                                    {{ old('status', $vacancy->status ?? '') == 'inactive' ? 'checked' : '' }}>
                                            <span>{{ __('Неактивна') }}</span>
                                        </label>
                                        <label class="pill">
                                            <input type="radio" name="status" value="closed"
                                                    {{ old('status', $vacancy->status ?? '') == 'closed' ? 'checked' : '' }}>
                                            <span>{{ __('Закрыта') }}</span>
                                        </label>
                                        <label class="pill">
                                            <input type="radio" name="status" value="in_archived"
                                                    {{ old('status', $vacancy->status ?? '') == 'in_archived' ? 'checked' : '' }}>
                                            <span>{{ __('В архиве') }}</span>
                                        </label>
                                    </div>
                                </div>

                            </div>

                            <div class="card-foot">
                                <span class="note">{{ __('Все поля можно связать с вашей логикой отдельно') }}</span>
                                <button type="submit" class="submit-btn">{{ __('Изменить вакансию') }}</button>
                            </div>
                        </form>
                    </div>
                </div>


            </div>


        </section>

    </main>
</div>

@include('employer.partials.js')


<script>
    // Только фон: белый/лавандовый фон + мягкое живое сияние в тон сайта (голубой/лавандовый/сиреневый)
    const canvas = document.getElementById('wave-bg');
    const ctx = canvas.getContext('2d');
    let w, h;

    function resize() {
        w = canvas.width = window.innerWidth;
        h = canvas.height = window.innerHeight;
    }

    window.addEventListener('resize', resize);
    resize();

    // Оттенки в стиле сайта: светло-голубой, лавандовый, сиреневый, мятный
    const blobs = [
        {color: '167, 199, 255', baseX: 0.05, baseY: 0.15, rx: 0.06, ry: 0.10, speed: 0.10, phase: 0, size: 0.60},
        {color: '196, 181, 253', baseX: 0.95, baseY: 0.12, rx: 0.05, ry: 0.12, speed: 0.08, phase: 1.4, size: 0.65},
        {color: '180, 210, 255', baseX: 0.08, baseY: 0.85, rx: 0.06, ry: 0.10, speed: 0.09, phase: 2.7, size: 0.62},
        {color: '210, 190, 255', baseX: 0.92, baseY: 0.88, rx: 0.05, ry: 0.09, speed: 0.11, phase: 4.1, size: 0.55},
        {color: '170, 220, 240', baseX: 0.02, baseY: 0.50, rx: 0.04, ry: 0.12, speed: 0.07, phase: 5.3, size: 0.55},
        {color: '190, 195, 255', baseX: 0.98, baseY: 0.55, rx: 0.04, ry: 0.11, speed: 0.10, phase: 3.2, size: 0.55}
    ];

    let t = 0;

    function drawBlob(b, shimmer) {
        const cx = (b.baseX + Math.sin(t * b.speed + b.phase) * b.rx) * w;
        const cy = (b.baseY + Math.cos(t * b.speed * 0.8 + b.phase) * b.ry) * h;
        const radius = Math.min(w, h) * b.size;

        // лёгкая пульсация яркости — "дыхание" сияния
        const alpha = 0.35 + shimmer * 0.15;

        const grad = ctx.createRadialGradient(cx, cy, 0, cx, cy, radius);
        grad.addColorStop(0, `rgba(${b.color}, ${alpha})`);
        grad.addColorStop(0.4, `rgba(${b.color}, ${alpha * 0.6})`);
        grad.addColorStop(0.75, `rgba(${b.color}, ${alpha * 0.2})`);
        grad.addColorStop(1, `rgba(${b.color}, 0)`);

        ctx.fillStyle = grad;
        ctx.beginPath();
        ctx.arc(cx, cy, radius, 0, Math.PI * 2);
        ctx.fill();
    }

    function render() {
        // базовый фон — светлый, почти белый с лёгким лавандовым оттенком (как на сайте)
        const bgGrad = ctx.createLinearGradient(0, 0, w, h);
        bgGrad.addColorStop(0, '#f7f8fc');
        bgGrad.addColorStop(1, '#eef1fb');
        ctx.fillStyle = bgGrad;
        ctx.fillRect(0, 0, w, h);

        const shimmer = (Math.sin(t * 0.015) + 1) / 2; // плавная пульсация 0..1

        ctx.globalCompositeOperation = 'multiply';
        blobs.forEach(b => drawBlob(b, shimmer));
        ctx.globalCompositeOperation = 'source-over';

        t += 0.004;
        requestAnimationFrame(render);
    }

    render();
</script>

</body>
</html>



