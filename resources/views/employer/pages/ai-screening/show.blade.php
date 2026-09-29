<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Настройка ИИ-собеседования') }}</title>
    @include('employer.partials.css')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@600;700;800&family=Inter:wght@400;500;600&display=swap"
          rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2 { font-family: 'Manrope', 'Inter', sans-serif; }

        .sc-wrap { padding: 0 3vh 4vh 2vh; display: flex; flex-direction: column; gap: 16px; }
        .sc-back { font-size: 13px; color: var(--gray-500); text-decoration: none; }
        .sc-back:hover { color: var(--indigo-600); }

        .sc-panel {
            background: var(--white); border: 1px solid var(--gray-200);
            border-radius: var(--radius); padding: 18px;
            display: flex; flex-direction: column; gap: 14px; min-width: 0;
        }
        .sc-panel h2 { margin: 0; font-size: 15px; color: var(--ink); }
        .sc-hint { margin: 0; font-size: 13px; color: var(--gray-500); }

        .sc-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; }
        .sc-field { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
        .sc-field label { font-size: 12px; font-weight: 600; color: var(--gray-500); }
        .sc-field input, .sc-field select, .sc-field textarea {
            font: inherit; font-size: 14px; padding: 8px 10px; border-radius: 10px;
            border: 1px solid var(--gray-300); background: var(--white); color: var(--ink);
            min-width: 0;
        }

        .sc-note { font-size: 13px; padding: 10px 12px; border-radius: 10px; }
        .sc-good { background: var(--wash-good); color: var(--ink-good); }
        .sc-bad  { background: var(--wash-danger); color: var(--danger); }
        .sc-warn { background: var(--wash-warn); color: var(--ink-warn); }

        .sc-btn {
            display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px;
            border-radius: 999px; cursor: pointer; font: inherit; font-size: 13px; font-weight: 600;
            background: var(--indigo-600); color: #fff; border: 1px solid transparent;
            text-decoration: none;
        }
        .sc-btn:hover { filter: brightness(1.06); }
        .sc-btn-ghost { background: transparent; color: var(--gray-500); border-color: var(--gray-200); }
        .sc-btn-danger { background: transparent; color: var(--danger); border-color: var(--gray-200); }

        .sc-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }

        /* критерии */
        .sc-crit {
            border: 1px solid var(--gray-200); border-radius: 12px; padding: 12px;
            display: flex; flex-direction: column; gap: 10px; min-width: 0;
        }
        .sc-crit[data-pending="1"] { border-style: dashed; background: var(--gray-100); }
        .sc-crit-head { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .sc-crit-head input[type="text"] { flex: 1 1 240px; min-width: 0; }
        .sc-chip {
            padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 600;
            background: var(--gray-100); color: var(--gray-500);
        }
        .sc-chip-ai { background: var(--indigo-50); color: var(--indigo-700); }
        .sc-share { font-size: 12px; color: var(--gray-500); white-space: nowrap; }
        .sc-check { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--ink); }

        .sc-empty {
            border: 1px dashed var(--gray-300); border-radius: 12px; padding: 18px;
            text-align: center; color: var(--gray-500); font-size: 13px;
        }
        .sc-sum { font-size: 13px; color: var(--gray-500); }
        .sc-sum b { color: var(--ink); }
    </style>
</head>
<body>
<div class="app">
    @include('employer.partials.sidebar')

    <main class="main">
        <div class="sc-wrap">
            <div class="sc-row" style="justify-content:space-between">
                <a class="sc-back" href="{{ route('employer.ai.index') }}">← {{ __('Ко всем вакансиям') }}</a>
                <a class="sc-btn" href="{{ route('employer.ai.candidates', $vacancy) }}">
                    {{ __('Кандидаты и отчёты') }}
                </a>
            </div>

            <div>
                <h1 style="margin:0 0 4px; font-size:20px;">{{ $vacancy->title }}</h1>
                <p class="sc-hint">{{ __('Настройка ИИ-собеседования для этой вакансии.') }}</p>
            </div>

            @if(session('status'))
                <div class="sc-note sc-good">{{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="sc-note sc-bad">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="sc-note sc-bad">{{ $errors->first() }}</div>
            @endif

            @if($problems)
                <div class="sc-note sc-warn">
                    <b>{{ __('Пока собеседование включить нельзя:') }}</b>
                    <ul style="margin:6px 0 0; padding-left:18px">
                        @foreach($problems as $problem)
                            <li>{{ $problem }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ==================== критерии ==================== --}}
            <div class="sc-panel">
                <h2>{{ __('Критерии оценки') }}</h2>
                <p class="sc-hint">
                    {{ __('По ним ИИ оценивает кандидата. Предложенное моделью в оценке не участвует, пока вы это не подтвердили: правила, по которым отказывают людям, утверждает человек.') }}
                </p>

                <div class="sc-row">
                    <form method="POST" action="{{ route('employer.ai.criteria.suggest', $vacancy) }}">
                        @csrf
                        <button type="submit" class="sc-btn" @disabled(! $aiAvailable)>
                            {{ __('Предложить критерии через ИИ') }}
                        </button>
                    </form>
                    @unless($aiAvailable)
                        <span class="sc-hint">{{ __('ИИ недоступен — добавьте критерии вручную.') }}</span>
                    @endunless
                </div>

                @if($criteria->isEmpty())
                    <div class="sc-empty">
                        {{ __('Критериев пока нет. Попросите ИИ предложить их по описанию вакансии или добавьте свой ниже.') }}
                    </div>
                @else
                    @php($confirmedWeight = $criteria->whereNotNull('confirmed_at')->sum('weight'))

                    <form method="POST" action="{{ route('employer.ai.criteria.save', $vacancy) }}"
                          style="display:flex; flex-direction:column; gap:10px;">
                        @csrf
                        @method('PATCH')

                        @foreach($criteria as $criterion)
                            <div class="sc-crit" data-pending="{{ $criterion->isConfirmed() ? '0' : '1' }}">
                                <div class="sc-crit-head">
                                    <input type="text" name="criteria[{{ $criterion->id }}][label]"
                                           value="{{ old('criteria.'.$criterion->id.'.label', $criterion->label) }}"
                                           maxlength="120" required>

                                    <select name="criteria[{{ $criterion->id }}][kind]">
                                        @foreach(\App\Models\AiInterviewCriterion::KINDS as $value => $label)
                                            <option value="{{ $value }}" @selected($criterion->kind === $value)>
                                                {{ __($label) }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <input type="number" name="criteria[{{ $criterion->id }}][weight]"
                                           value="{{ old('criteria.'.$criterion->id.'.weight', $criterion->weight) }}"
                                           min="1" max="100" style="width:88px"
                                           aria-label="{{ __('Вес') }}">

                                    {{-- Доля, а не проценты «должно быть 100»: система считает
                                         соотношение весов, поэтому подгонять сумму не нужно. --}}
                                    <span class="sc-share">
                                        @if($criterion->isConfirmed() && $confirmedWeight > 0)
                                            {{ __('доля') }} {{ round($criterion->weight / $confirmedWeight * 100) }}%
                                        @else
                                            {{ __('в оценке не учтён') }}
                                        @endif
                                    </span>

                                    <span class="sc-chip {{ $criterion->source === 'ai' ? 'sc-chip-ai' : '' }}">
                                        {{ __(\App\Models\AiInterviewCriterion::SOURCES[$criterion->source] ?? $criterion->source) }}
                                    </span>
                                </div>

                                <textarea name="criteria[{{ $criterion->id }}][description]" rows="2"
                                          maxlength="400"
                                          placeholder="{{ __('Что именно проверяем и что считаем хорошим ответом') }}"
                                >{{ old('criteria.'.$criterion->id.'.description', $criterion->description) }}</textarea>

                                <div class="sc-row" style="justify-content:space-between">
                                    <label class="sc-check">
                                        <input type="hidden" name="criteria[{{ $criterion->id }}][confirmed]" value="0">
                                        <input type="checkbox" name="criteria[{{ $criterion->id }}][confirmed]"
                                               value="1" @checked($criterion->isConfirmed())>
                                        {{ __('Подтверждаю — использовать в оценке') }}
                                    </label>

                                    <button type="submit" form="drop-{{ $criterion->id }}"
                                            class="sc-btn sc-btn-danger">{{ __('Удалить') }}</button>
                                </div>
                            </div>
                        @endforeach

                        <div class="sc-row" style="justify-content:space-between">
                            <span class="sc-sum">
                                {{ __('Подтверждено критериев:') }}
                                <b>{{ $criteria->whereNotNull('confirmed_at')->count() }}</b>,
                                {{ __('из них обязательных:') }}
                                <b>{{ $criteria->whereNotNull('confirmed_at')->where('kind', 'must')->count() }}</b>
                            </span>
                            <button type="submit" class="sc-btn">{{ __('Сохранить критерии') }}</button>
                        </div>
                    </form>

                    {{-- Формы удаления вынесены наружу: вложенная форма внутри формы
                         недопустима в HTML, а кнопка связывается с ней атрибутом form. --}}
                    @foreach($criteria as $criterion)
                        <form id="drop-{{ $criterion->id }}" method="POST"
                              action="{{ route('employer.ai.criteria.destroy', [$vacancy, $criterion]) }}" hidden>
                            @csrf
                            @method('DELETE')
                        </form>
                    @endforeach
                @endif

                {{-- добавить свой --}}
                <details>
                    <summary style="cursor:pointer; font-size:13px; color:var(--indigo-600)">
                        {{ __('Добавить критерий вручную') }}
                    </summary>
                    <form method="POST" action="{{ route('employer.ai.criteria.add', $vacancy) }}"
                          style="margin-top:10px; display:flex; flex-direction:column; gap:10px;">
                        @csrf
                        <div class="sc-grid">
                            <div class="sc-field">
                                <label for="new-label">{{ __('Название') }}</label>
                                <input id="new-label" type="text" name="label" maxlength="120" required>
                            </div>
                            <div class="sc-field">
                                <label for="new-kind">{{ __('Вид') }}</label>
                                <select id="new-kind" name="kind">
                                    @foreach(\App\Models\AiInterviewCriterion::KINDS as $value => $label)
                                        <option value="{{ $value }}">{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sc-field">
                                <label for="new-weight">{{ __('Вес') }}</label>
                                <input id="new-weight" type="number" name="weight" value="20" min="1" max="100">
                            </div>
                        </div>
                        <div class="sc-field">
                            <label for="new-desc">{{ __('Что проверяем') }}</label>
                            <textarea id="new-desc" name="description" rows="2" maxlength="400"></textarea>
                        </div>
                        <div><button type="submit" class="sc-btn sc-btn-ghost">{{ __('Добавить') }}</button></div>
                    </form>
                </details>
            </div>

            {{-- ==================== настройки ==================== --}}
            <form method="POST" action="{{ route('employer.ai.config', $vacancy) }}" class="sc-panel">
                @csrf
                @method('PATCH')

                <h2>{{ __('Как проводить собеседование') }}</h2>

                <div class="sc-grid">
                    <div class="sc-field">
                        <label for="level">{{ __('Уровень позиции') }}</label>
                        <select id="level" name="level">
                            @foreach($levels as $value => $label)
                                <option value="{{ $value }}" @selected(old('level', $config->level) === $value)>
                                    {{ __($label) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sc-field">
                        <label for="language">{{ __('Язык разговора') }}</label>
                        <select id="language" name="language">
                            @foreach($locales as $locale)
                                <option value="{{ $locale }}" @selected(old('language', $config->language) === $locale)>
                                    {{ strtoupper($locale) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sc-field">
                        <label for="questions_count">{{ __('Сколько вопросов') }}</label>
                        <input id="questions_count" type="number" name="questions_count" min="3" max="20"
                               value="{{ old('questions_count', $config->questions_count) }}">
                    </div>

                    <div class="sc-field">
                        <label for="test_time_limit_minutes">{{ __('Минут на тестовое') }}</label>
                        <input id="test_time_limit_minutes" type="number" name="test_time_limit_minutes"
                               min="10" max="480" value="{{ old('test_time_limit_minutes', $config->test_time_limit_minutes) }}">
                    </div>
                </div>

                <h2 style="margin-top:6px">{{ __('Из чего складывается итог') }}</h2>
                <p class="sc-hint">{{ __('Три веса в процентах, вместе они должны давать 100.') }}</p>

                <div class="sc-grid">
                    <div class="sc-field">
                        <label for="weight_documents">{{ __('Документы, %') }}</label>
                        <input id="weight_documents" type="number" name="weight_documents" min="0" max="100"
                               value="{{ old('weight_documents', $config->weight_documents) }}"
                               data-weight>
                    </div>
                    <div class="sc-field">
                        <label for="weight_interview">{{ __('Собеседование, %') }}</label>
                        <input id="weight_interview" type="number" name="weight_interview" min="0" max="100"
                               value="{{ old('weight_interview', $config->weight_interview) }}"
                               data-weight>
                    </div>
                    <div class="sc-field">
                        <label for="weight_test">{{ __('Тестовое задание, %') }}</label>
                        <input id="weight_test" type="number" name="weight_test" min="0" max="100"
                               value="{{ old('weight_test', $config->weight_test) }}"
                               data-weight>
                    </div>
                    <div class="sc-field">
                        <label>{{ __('Сумма') }}</label>
                        <div id="weightSum" class="sc-sum" style="padding:8px 0"></div>
                    </div>
                </div>

                <h2 style="margin-top:6px">{{ __('Пороги решения') }}</h2>
                <p class="sc-hint">
                    {{ __('Ниже порога отказа — вежливый отказ. Выше порога приёма — кандидат прошёл. Между ними ИИ задаст дополнительные вопросы, а если и после них неясно, передаст вам на ручную проверку.') }}
                </p>

                <div class="sc-grid">
                    <div class="sc-field">
                        <label for="threshold_reject">{{ __('Порог отказа') }}</label>
                        <input id="threshold_reject" type="number" name="threshold_reject" min="0" max="100"
                               value="{{ old('threshold_reject', $config->threshold_reject) }}">
                    </div>
                    <div class="sc-field">
                        <label for="threshold_accept">{{ __('Порог приёма') }}</label>
                        <input id="threshold_accept" type="number" name="threshold_accept" min="0" max="100"
                               value="{{ old('threshold_accept', $config->threshold_accept) }}">
                    </div>
                    <div class="sc-field">
                        <label for="response_sla">{{ __('Срок ответа кандидату') }}</label>
                        <input id="response_sla" type="text" name="response_sla" maxlength="120"
                               value="{{ old('response_sla', $config->response_sla) }}">
                    </div>
                    <div class="sc-field">
                        <label for="decision_mode">{{ __('Кто принимает решение') }}</label>
                        <select id="decision_mode" name="decision_mode">
                            @foreach($modes as $value => $label)
                                <option value="{{ $value }}" @selected(old('decision_mode', $config->decision_mode) === $value)>
                                    {{ __($label) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <label class="sc-check" style="margin-top:6px">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $config->enabled))>
                    <b>{{ __('Включить ИИ-собеседование для этой вакансии') }}</b>
                </label>
                <p class="sc-hint">
                    {{ __('После включения каждый откликнувшийся кандидат увидит предупреждение, что собеседование проводит ИИ, и должен будет дать согласие.') }}
                </p>

                <div><button type="submit" class="sc-btn">{{ __('Сохранить настройки') }}</button></div>
            </form>
        </div>
    </main>
</div>

<script>
    (function () {
        // Сумма весов считается на глазах: иначе про требование «вместе 100»
        // узнаёшь только после отправки формы.
        const inputs = document.querySelectorAll('[data-weight]');
        const out = document.getElementById('weightSum');

        if (!out || !inputs.length) { return; }

        function paint() {
            let sum = 0;
            inputs.forEach(function (input) { sum += parseInt(input.value, 10) || 0; });

            out.innerHTML = '<b>' + sum + '%</b>';
            out.style.color = sum === 100 ? 'var(--ink-good)' : 'var(--danger)';
        }

        inputs.forEach(function (input) { input.addEventListener('input', paint); });
        paint();
    })();
</script>

@include('employer.partials.js')
</body>
</html>
