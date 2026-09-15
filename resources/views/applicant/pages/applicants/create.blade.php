<!DOCTYPE html>
<html lang="ru" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Регистрация соискателя') }}</title>
    <style>
        :root{
            --blue: #4F5BFF;
            --blue-dark: #3B46E0;
            --blue-light: #EEF0FF;
            --green: #22C55E;
            --green-bg: #ECFDF3;
            --ink: #10131A;
            --gray-700: #4B5163;
            --gray-500: #8A90A3;
            --gray-300: #E3E5EE;
            --gray-100: #F6F7FB;
            --white: #FFFFFF;
            --radius: 14px;
        }

        *{box-sizing:border-box;}
        html,body{margin:0;padding:0;}
        body{
            font-family:'Inter', -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
            background: var(--gray-100);
            color: var(--ink);
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding: 40px 20px;
        }

        .page{
            width:100%;
            max-width: 1040px;
        }

        .brand{
            display:flex;
            align-items:center;
            gap:10px;
            margin-bottom: 28px;
        }
        .brand-mark{
            width:38px;height:38px;border-radius:10px;
            background: var(--blue);
            color:#fff;
            display:flex;align-items:center;justify-content:center;
            font-weight:800; font-size:16px;
            box-shadow: 0 6px 16px rgba(79,91,255,.28);
        }
        .brand-name{font-weight:800; font-size:20px; letter-spacing:-.01em;}
        .brand-name span{color:var(--blue);}

        .card{
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 1px 2px rgba(16,19,26,.04), 0 20px 50px -20px rgba(16,19,26,.15);
            overflow:hidden;
            border: 1px solid var(--gray-300);
        }

        .card-top{
            padding: 32px 40px 0 40px;
        }

        h1{
            font-size: 28px;
            line-height:1.15;
            margin:0 0 6px 0;
            letter-spacing:-0.02em;
        }
        .sub{
            color: var(--gray-500);
            font-size:15px;
            margin:0 0 24px 0;
        }

        .role-pill{
            display:flex; align-items:center; gap:8px;
            background: var(--blue-light);
            border-radius: 12px;
            padding: 12px 16px;
            width:fit-content;
            font-weight:600;
            font-size:14.5px;
            color: var(--blue-dark);
            margin-bottom: 4px;
        }
        .role-pill svg{width:16px;height:16px; flex:none;}

        form{
            padding: 28px 40px 40px 40px;
        }

        .grid{
            display:grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px 16px;
        }
        .full{grid-column: 1 / -1;}

        .field{display:flex; flex-direction:column; gap:7px;}
        .field label{
            font-size:13.5px;
            font-weight:600;
            color: var(--gray-700);
        }
        .field label .opt{
            font-weight:400;
            color: var(--gray-500);
            margin-left:4px;
        }
        input, select, textarea{
            font-family: inherit;
            font-size: 14.5px;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1.5px solid var(--gray-300);
            background: var(--white);
            color: var(--ink);
            outline:none;
            transition: border-color .15s ease, box-shadow .15s ease;
            width:100%;
        }
        textarea{resize:vertical; min-height:88px; font-family:inherit;}
        input::placeholder, textarea::placeholder{color:#AEB2C2;}
        input:focus, select:focus, textarea:focus{
            border-color: var(--blue);
            box-shadow: 0 0 0 4px var(--blue-light);
        }

        .upload{
            border: 1.5px dashed var(--gray-300);
            border-radius: 12px;
            padding: 18px;
            display:flex;
            align-items:center;
            gap:12px;
            background: var(--gray-100);
            cursor:pointer;
            transition: border-color .15s ease, background .15s ease;
        }
        .upload:hover{ border-color: var(--blue); background: var(--blue-light); }
        .upload-icon{
            width:36px;height:36px;border-radius:9px;
            background: var(--white);
            border:1px solid var(--gray-300);
            display:flex;align-items:center;justify-content:center;
            flex:none;
        }
        .upload-text b{display:block; font-size:14px; color:var(--ink);}
        .upload-text span{font-size:12.5px; color:var(--gray-500);}

        .badge-row{
            display:flex; align-items:center; gap:8px;
            background: var(--green-bg);
            border-radius: 999px;
            padding: 8px 14px;
            width:fit-content;
            font-size: 12.5px;
            font-weight:600;
            color:#15803D;
            margin-bottom:18px;
        }
        .dot{width:7px;height:7px;border-radius:50%; background:var(--green);}

        .checkbox-row{
            display:flex; align-items:flex-start; gap:10px;
            font-size:13px; color: var(--gray-500);
            margin-top: 4px;
        }
        .checkbox-row input{width:16px; height:16px; margin-top:2px; flex:none; accent-color: var(--blue);}
        .checkbox-row a{color:var(--blue-dark); text-decoration:none; font-weight:600;}

        .actions{
            display:flex;
            align-items:center;
            gap:14px;
            margin-top:26px;
            flex-wrap:wrap;
        }
        .btn-primary{
            background: var(--blue);
            color:#fff;
            border:none;
            padding: 13px 26px;
            border-radius: 10px;
            font-weight:700;
            font-size:14.5px;
            cursor:pointer;
            transition: background .15s ease, transform .1s ease;
            box-shadow: 0 8px 18px -6px rgba(79,91,255,.5);
        }
        .btn-primary:hover{ background: var(--blue-dark); }
        .btn-primary:active{ transform: translateY(1px); }

        .link-muted{
            font-size:13.5px;
            color: var(--gray-500);
        }
        .link-muted a{color:var(--blue-dark); font-weight:600; text-decoration:none;}

        .switch-role{
            font-size:13.5px;
            color: var(--gray-500);
            margin-top:20px;
            text-align:center;
        }
        .switch-role a{color:var(--blue-dark); font-weight:600; text-decoration:none;}

        @media (max-width: 700px){
            .grid{grid-template-columns: 1fr;}
            .card-top, form{padding-left:22px; padding-right:22px;}
        }
    </style>
</head>
<body>

<div class="page">

    <div class="brand">
        <a href="{{ route('public.home') }}" style="display:flex; align-items:center; gap:10px; text-decoration:none; color:inherit;">
            @include('partials.brand-mark', ['brandSize' => 38])
            <div class="brand-name">Work<span>io</span></div>
        </a>
    </div>

    <div class="card">
        <div class="card-top">
            <div class="badge-row"><span class="dot"></span>{{ __('Регистрация займёт меньше 2 минут') }}</div>

            <div class="role-pill">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
                {{ __('Я соискатель') }}
            </div>

            <h1>{{ __('Заполните анкету для своего профиля') }}</h1>
            <p class="sub">{{ __('Заполните данные о себе, чтобы работодатели могли вас найти.') }}</p>
        </div>

        <form id="form-applicant" action="{{route('public.applicants.store')}}" method="post" enctype="multipart/form-data">
            @csrf
            @if ($errors->any())
                <div class="alert-danger">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div class="grid">
                <div class="field full">
                    <label>{{ __('Фото') }} <span style="font-weight:400; opacity:.7;">{{ __('(необязательно)') }}</span></label>
                    <div style="display:flex; align-items:center; gap:16px;">
                        <div id="avatarPreviewWrap"
                             style="width:64px; height:64px; border-radius:50%; overflow:hidden; flex-shrink:0;
                                    background:#EEF1F6; color:#1656D6; display:flex; align-items:center;
                                    justify-content:center; font-weight:700; font-size:22px;">
                            <img id="avatarPreview" src="" alt="" style="width:100%; height:100%; object-fit:cover; display:none;">
                            <span id="avatarLetter">{{ mb_substr(auth()->user()->name ?? '?', 0, 1) }}</span>
                        </div>
                        <div>
                            <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/webp">
                            <div style="font-size:12px; opacity:.7; margin-top:6px;">{{ __('JPG, PNG или WEBP, до 5 МБ. Профиль с фото заметнее в поиске работодателей') }}</div>
                        </div>
                    </div>
                </div>
                <div class="field">
                    <label>{{ __('Год рождения') }}</label>
                    <input type="date" placeholder="d-m-Y" name="birth" value="{{old('birth')}}">
                </div>
                <div class="field">
                    <label>{{ __('Ваш пол') }}</label>
                    <select name="gender">
                        <option value="male">{{ __('Женский') }}</option>
                        <option value="female">{{ __('Мужской') }}</option>
                    </select>
                </div>

                <div class="field">
                    <label>{{ __('Телефон') }}</label>
                    <input type="tel" placeholder="+7 900 000-00-00" name="phone" value="{{old('phone')}}">
                </div>

                <div class="field">
                    <label>{{ __('Город') }}</label>
                    <input type="text" placeholder="{{ __('Страна,Город или регион') }}" name="city" value="{{old('city')}}">
                </div>
                <div class="field">
                    <label>{{ __('Адрес') }}</label>
                    <input type="text" placeholder="{{ __('Ваш адрес') }}" name="address" value="{{old('address')}}">
                </div>

                <div class="field">
                    <label>{{ __('Образование') }}</label>
                    <input type="text" name="education" placeholder="{{ __('Какой университет вы закончели') }}" value="{{old('education')}}">
                </div>
                <div class="field full">
                    <label>{{ __('О себе') }} <span class="opt">{{ __('(обязательно)') }}</span></label>
                    <textarea name="about_me" placeholder="{{ __('Коротко расскажите о своём опыте и ключевых навыках') }}">{{old('about_me')}}</textarea>
                </div>


                <div class="field full">
                    <label class="checkbox-row">
                        <input type="checkbox">
                        <span>{{ __('Я согласен(на) с') }} <a href="#">{{ __('условиями использования') }}</a> {{ __('и') }} <a href="#">{{ __('политикой конфиденциальности') }}</a></span>
                    </label>
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn-primary">{{ __('Сохранить профиль') }}</button>
                <span class="link-muted">{{ __('Уже есть аккаунт?') }} <a href="#">{{ __('Войти') }}</a></span>
            </div>

            </form>
    </div>
</div>

<script>
    (function () {
        const input = document.getElementById('avatarInput');
        const preview = document.getElementById('avatarPreview');
        const letter = document.getElementById('avatarLetter');
        if (!input) { return; }

        input.addEventListener('change', function () {
            const file = input.files && input.files[0];
            if (!file || !preview) { return; }
            preview.src = URL.createObjectURL(file);
            preview.style.display = '';
            if (letter) { letter.style.display = 'none'; }
        });
    })();
</script>
</body>
</html>
