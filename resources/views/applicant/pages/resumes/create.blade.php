<!DOCTYPE html>
<html lang="ru" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Личный кабинет') }}</title>
    @include('applicant.partials.css')
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

        /* ---------- выбор оформления резюме ---------- */
        .skin-pick {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
        }

        .skin-option {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 9px 15px 9px 11px;
            border: 1px solid rgba(44, 95, 224, .25);
            border-radius: 999px;
            font-size: 13.5px;
            font-weight: 600;
            color: var(--slate);
            background: #fff;
            transition: border-color .18s ease, box-shadow .18s ease, color .18s ease;
        }

        .skin-option:hover {
            border-color: var(--blue-primary);
        }

        /* сама радиокнопка не нужна: роль индикатора играет рамка */
        .skin-option input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .skin-option:has(input:checked) {
            border-color: var(--blue-primary);
            color: var(--navy-deep);
            box-shadow: 0 0 0 3px rgba(44, 95, 224, .12);
        }

        /* видно, чем отличаются оформления, ещё до выбора */
        .skin-mark {
            width: 16px;
            height: 16px;
            border-radius: 5px;
            flex: 0 0 16px;
        }

        .skin-classic { background: linear-gradient(135deg, #2C5FE0, #5B8DEF); }
        .skin-modern { background: linear-gradient(135deg, #0E2456 55%, #5B8DEF 55%); }
        .skin-compact { background: repeating-linear-gradient(180deg, #2C5FE0 0 3px, #EAF1FC 3px 6px); }
        .skin-strict { background: linear-gradient(135deg, #1F2937, #6B7280); }
        .skin-elegant { background: linear-gradient(135deg, #8A6D3B, #D9C7A3); }
        .skin-bold { background: linear-gradient(180deg, #0E2456 50%, #fff 50%); }
        .skin-soft { background: linear-gradient(135deg, #EAF6F2, #9DD5C4); }
        .skin-editorial { background: linear-gradient(135deg, #FFFFFF 50%, #111827 50%); }
        .skin-tech { background: repeating-linear-gradient(90deg, #0F766E 0 4px, #CCFBF1 4px 8px); }
        .skin-warm { background: linear-gradient(135deg, #C2410C, #FED7AA); }
        .skin-night { background: linear-gradient(135deg, #111827, #7C9CFF); }
        /* сетка чертежа и жёлтая плашка брутального — образцы читаются в списке */
        .skin-blueprint { background: #0B2340 repeating-linear-gradient(90deg, rgba(127,227,240,.55) 0 1px, transparent 1px 6px); }
        .skin-brutal { background: linear-gradient(135deg, #FFE600 50%, #0A0A0A 50%); }

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
    @include('applicant.partials.sidebar')

    <!-- ================= MAIN ================= -->
    <main class="main">

        <div class="topbar">
            <div>
                <h1 id="pageTitle">{{ __('Резюме') }}</h1>
                <p id="pageSubtitle">{{ __('Заполните анкету — это поможет вам найти работу быстрее!') }}</p>
            </div>
            @include('partials.notification-bell')
            <div class="user-chip">
                <div class="avatar" style="padding: 14px 16px;">
                    @if($applicant->user?->hasAvatar())
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
                    <div class="name">{{ $applicant->user->name }}</div>
                    <div class="role">{{ $applicant->user->email }}</div>
                </div>
            </div>
        </div>

        <!-- ---------- SECTION: PROFILE ---------- -->
        <section class="section active" id="section-profile">

            <div class="">

                <canvas id="wave-bg"></canvas>

                <div>
                    <div class="form-card" style="margin-left: 17vh;">
                        <form action="{{route('applicant.resume.store')}}" method="post" enctype="multipart/form-data">
                            @csrf

                            <div class="card-head">

                                <p class="eyebrow">{{ __('Новая публикация') }}</p>
                                <h1>{{ __('Создание резюме') }}</h1>
                                <p>{{ __('Заполните карточку — кандидаты увидят её в списке открытых позиций.') }}</p>
                            </div>

                            <div class="card-body">

                                <div class="field-group">
                                    <label>{{ __('Моя профессия') }}</label>
                                    <input type="text" placeholder="{{ __('Например: Backend-разработчик (Laravel)') }}"
                                           name="profession" value="{{old('profession')}}">
                                </div>

                                <div class="field-group">
                                    <label>{{ __('Опыт вашей работы?') }}</label>
                                    <input type="number" name="experience_years" value="{{old('experience_years')}}">
                                </div>

                                <div class="field-group split">
                                    <div>
                                        <label>{{ __('Желаемая должность') }}</label>
                                        <input type="text" name="desired_position" value="{{old('desired_position')}}">
                                    </div>
                                    <div>
                                        <label>{{ __('Желаемая зарплата') }}</label>
                                        <input type="number" name="desired_salary" value="{{old('desired_salary')}}">
                                    </div>

                                    <div>
                                        <label>{{ __('Ссылка вашего Github или Linked') }}</label>
                                        <input type="text" name="url_website" value="{{old('url_website')}}">
                                    </div>


                                    <div>
                                        <label>{{ __('Какые языки вы знаете?') }}</label>
                                        <input type="text" name="languages" value="{{old('languages')}}">
                                    </div>

                                    <div>

                                    </div>
                                </div>

                                <div class="divider-line"></div>


                                <div class="field-group">
                                    <label>{{ __('Ваши Навыки') }} <span class="opt">{{ __('(через запятую / необязательно)') }}</span></label>
                                    <input type="text" placeholder="PHP, Laravel, MySQL, Docker" name="skills"
                                           value="{{old('skills')}}">
                                </div>
                                <div class="field-group">
                                    <label>{{ __('Место работы') }}<span class="opt"> {{ __('(необязательно)') }}</span></label>
                                    <input type="text" placeholder="{{ __('Место где вы работали.') }}" name="place_work"
                                           value="{{old('place_work')}}">
                                </div>
                                <div class="field-group">
                                    <label>{{ __('О себе') }} <span class="opt">{{ __('(обязательно)') }}</span></label>
                                    <textarea name="description"
                                              placeholder="{{ __('Коротко расскажите о своём опыте, проектах и целях.') }}">{{old('description')}}</textarea>
                                </div>
                                <div class="field-group">
                                    <label>{{ __('Документы') }} <span class="opt">{{ __('(PDF, DOC, DOCX до 5 МБ / необязательно)') }}</span></label>
                                    <input type="file" name="documents" accept=".pdf,.doc,.docx">
                                </div>
                                <div class="field-group">
                                    <label>{{ __('Оформление') }} <span class="opt">{{ __('(как резюме увидят работодатели)') }}</span></label>
                                    {{-- радиокнопки, а не переключатель на скриптах:
                                         выбор должен работать и при отключённом JS --}}
                                    <div class="skin-pick">
                                        @foreach(\App\Models\Resume::STYLES as $key => $title)
                                            <label class="skin-option">
                                                <input type="radio" name="style" value="{{ $key }}"
                                                       @checked(old('style', 'classic') === $key)>
                                                <span class="skin-mark skin-{{ $key }}"></span>
                                                {{ __($title) }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="divider-line"></div>


                            </div>

                            <div class="card-foot">
                                <span class="note">{{ __('Все поля можно связать с вашей логикой отдельно') }}</span>
                                <button type="submit" class="submit-btn">{{ __('Опубликовать резюме') }}</button>
                            </div>
                        </form>
                    </div>
                </div>


            </div>


        </section>

    </main>
</div>

@include('applicant.partials.js')

@include('applicant.partials.script')
</body>
</html>



