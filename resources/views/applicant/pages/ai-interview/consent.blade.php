<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Согласие на ИИ-собеседование') }}</title>
    @include('applicant.partials.css')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@600;700;800&family=Inter:wght@400;500;600&display=swap"
          rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2 { font-family: 'Manrope', 'Inter', sans-serif; }

        .cn-wrap { padding: 0 3vh 4vh 2vh; display: flex; flex-direction: column; gap: 16px; max-width: 900px; }
        .cn-panel {
            background: var(--white); border: 1px solid var(--gray-200);
            border-radius: var(--radius); padding: 20px;
            display: flex; flex-direction: column; gap: 14px;
        }
        .cn-panel h1 { margin: 0; font-size: 20px; color: var(--ink); }
        .cn-panel h2 { margin: 0; font-size: 15px; color: var(--ink); }
        .cn-lead { margin: 0; color: var(--gray-500); font-size: 14px; }

        .cn-vacancy {
            padding: 12px 14px; border-radius: 12px; background: var(--indigo-50);
            color: var(--indigo-700); font-weight: 600;
        }
        .cn-vacancy span { display: block; font-weight: 500; font-size: 13px; opacity: .85; }

        .cn-list { margin: 0; padding-left: 20px; display: grid; gap: 7px; font-size: 14px; color: var(--ink); }
        .cn-list li::marker { color: var(--indigo-600); }

        .cn-rights {
            border-left: 3px solid var(--gray-300); padding-left: 14px;
            display: grid; gap: 7px; font-size: 13px; color: var(--gray-500);
        }

        .cn-check {
            display: flex; gap: 10px; align-items: flex-start; font-size: 14px; color: var(--ink);
            padding: 12px 14px; border-radius: 12px; background: var(--gray-100);
        }
        .cn-check input { margin-top: 3px; flex: none; }

        .cn-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .cn-btn {
            display: inline-flex; align-items: center; padding: 10px 18px; border-radius: 999px;
            cursor: pointer; font: inherit; font-size: 14px; font-weight: 600;
            background: var(--indigo-600); color: #fff; border: 1px solid transparent;
        }
        .cn-btn:hover { filter: brightness(1.06); }
        .cn-btn-ghost { background: transparent; color: var(--gray-500); border-color: var(--gray-200); }

        .cn-note { font-size: 13px; padding: 10px 12px; border-radius: 10px; }
        .cn-bad { background: var(--wash-danger); color: var(--danger); }
        .cn-good { background: var(--wash-good); color: var(--ink-good); }
    </style>
</head>
<body>
<div class="app">
    @include('applicant.partials.sidebar')

    <main class="main">
        <div class="cn-wrap">
            @if(session('status'))
                <div class="cn-note cn-good">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="cn-note cn-bad">{{ $errors->first() }}</div>
            @endif

            <div class="cn-panel">
                <h1>{{ __('Собеседование проводит ИИ-ассистент') }}</h1>

                <div class="cn-vacancy">
                    {{ $vacancy->title }}
                    <span>{{ $vacancy->employer?->company_name }}</span>
                </div>

                <p class="cn-lead">
                    {{ __('По этой вакансии работодатель поручил первичный отбор ИИ-ассистенту. Он задаст вопросы, проверит ваши документы, даст небольшое задание и сообщит результат. Это не человек, и он честно ответит на такой вопрос, если вы его зададите.') }}
                </p>

                <h2>{{ __('Что будет происходить') }}</h2>
                <ol class="cn-list">
                    <li>{{ __('Вы загрузите дипломы и сертификаты, если они есть. Резюме загружать не нужно — оно уже на площадке.') }}</li>
                    <li>{{ __('ИИ сверит ваш опыт с требованиями вакансии и подготовит вопросы, в том числе по спорным местам.') }}</li>
                    <li>{{ __('Вы ответите на вопросы своими словами. Каждый ответ оценивается по критериям, которые задал работодатель.') }}</li>
                    <li>{{ __('Затем — короткое тестовое задание с ограничением по времени.') }}</li>
                    <li>{{ __('ИИ посчитает итог и сообщит: вы прошли отбор или нет. В спорном случае решение примет человек.') }}</li>
                </ol>

                <h2>{{ __('Как обрабатываются ваши данные') }}</h2>
                <div class="cn-rights">
                    <div>{{ __('Оцениваются только навыки, опыт, образование и результаты работы.') }}</div>
                    <div>{{ __('Пол, возраст, дата рождения, национальность, гражданство, религия, семейное положение, внешность и здоровье не запрашиваются и не учитываются. Такие данные вырезаются из текста до того, как его увидит модель.') }}</div>
                    <div>{{ __('Ваши ответы, документы и оценки хранятся в зашифрованном виде и доступны только работодателю этой вакансии.') }}</div>
                    <div>{{ __('Все обращения к модели записываются, чтобы любое решение можно было объяснить и проверить.') }}</div>
                    <div>{{ __('Подлинность дипломов система не проверяет: она только сверяет данные с резюме.') }}</div>
                    <div>{{ __('Вы можете попросить удалить ваши данные по этому собеседованию в любой момент.') }}</div>
                </div>

                <form method="POST" action="{{ route('applicant.ai.consent', $interview) }}"
                      style="display:flex; flex-direction:column; gap:12px;">
                    @csrf

                    <label class="cn-check">
                        <input type="checkbox" name="consent" value="1" required>
                        <span>
                            {{ __('Я понимаю, что собеседование проводит ИИ-ассистент, и согласен на обработку моих данных для оценки моей кандидатуры по этой вакансии.') }}
                        </span>
                    </label>

                    <div class="cn-row">
                        <button type="submit" class="cn-btn">{{ __('Согласен, начать') }}</button>
                        <button type="submit" form="decline" class="cn-btn cn-btn-ghost">
                            {{ __('Не согласен') }}
                        </button>
                    </div>
                    <p class="cn-lead" style="font-size:13px">
                        {{ __('Если вы откажетесь, отклик останется: его рассмотрит работодатель обычным порядком.') }}
                    </p>
                </form>

                {{-- Форма отказа вынесена наружу: вложенная форма внутри формы
                     недопустима, кнопка связывается с ней атрибутом form. --}}
                <form id="decline" method="POST" action="{{ route('applicant.ai.decline', $interview) }}" hidden>
                    @csrf
                    @method('DELETE')
                </form>
            </div>
        </div>
    </main>
</div>

@include('applicant.partials.js')
@include('applicant.partials.script')
</body>
</html>
