<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Отчёт по кандидату') }}</title>
    @include('employer.partials.css')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@600;700;800&family=Inter:wght@400;500;600&display=swap"
          rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2 { font-family: 'Manrope', 'Inter', sans-serif; }

        .cr-wrap { padding: 0 3vh 4vh 2vh; display: flex; flex-direction: column; gap: 16px; max-width: 980px; }
        .cr-back { font-size: 13px; color: var(--gray-500); text-decoration: none; }
        .cr-back:hover { color: var(--indigo-600); }

        .cr-actions {
            background: var(--white); border: 1px solid var(--gray-200);
            border-radius: var(--radius); padding: 18px;
            display: flex; flex-direction: column; gap: 12px;
        }
        .cr-actions h2 { margin: 0; font-size: 15px; color: var(--ink); }
        .cr-hint { margin: 0; font-size: 12px; color: var(--gray-500); }

        .cr-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .cr-field { display: flex; flex-direction: column; gap: 5px; flex: 1 1 260px; min-width: 0; }
        .cr-field label { font-size: 12px; font-weight: 600; color: var(--gray-500); }
        .cr-field select, .cr-field input {
            font: inherit; font-size: 14px; padding: 8px 10px; border-radius: 10px;
            border: 1px solid var(--gray-300); background: var(--white); color: var(--ink);
        }

        .cr-btn {
            display: inline-flex; align-items: center; padding: 9px 16px; border-radius: 999px;
            cursor: pointer; font: inherit; font-size: 13px; font-weight: 600; text-decoration: none;
            background: var(--indigo-600); color: #fff; border: 1px solid transparent;
        }
        .cr-btn:hover { filter: brightness(1.06); }
        .cr-btn-ghost { background: transparent; color: var(--gray-500); border-color: var(--gray-200); }
        .cr-btn-danger { background: transparent; color: var(--danger); border-color: var(--gray-200); }

        .cr-note { font-size: 13px; padding: 10px 12px; border-radius: 10px; }
        .cr-good { background: var(--wash-good); color: var(--ink-good); }
        .cr-bad { background: var(--wash-danger); color: var(--danger); }
    </style>
</head>
<body>
<div class="app">
    @include('employer.partials.sidebar')

    <main class="main">
        <div class="cr-wrap">
            <a class="cr-back" href="{{ route('employer.ai.candidates', $vacancy) }}">
                ← {{ __('Ко всем кандидатам') }}
            </a>

            @if(session('status'))
                <div class="cr-note cr-good">{{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="cr-note cr-bad">{{ session('error') }}</div>
            @endif

            @include('employer.pages.ai-screening._report', ['withFileLinks' => true])

            @php
                $mainTask = $interview->testTasks->firstWhere('kind', 'main');
                $sent = $mainTask?->submissions->sortByDesc('id')->first();
                $checking = $interview->candidateFinished() && ! $interview->latestDecision;
            @endphp

            @if($checking)
                {{-- кандидат всё прошёл, а проверка ещё идёт: доводим её отсюда --}}
                <div class="cr-note" id="crChecking" style="background:var(--wash-warn,#FEF3C7); color:var(--ink-warn,#92400E)">
                    ⏳ {{ $sent && ! $sent->isGraded()
                        ? __('ИИ проверяет тест и задания кандидата — это займёт около минуты. Страница обновится сама.')
                        : __('ИИ готовит рекомендацию по кандидату — страница обновится сама.') }}
                </div>
                <script>
                    (function () {
                        const url = @json(route('employer.ai.candidate.pump', [$vacancy, $interview]));
                        const token = @json(csrf_token());
                        let tries = 60;

                        /*
                         * Обсуждение с Анной не обрываем.
                         *
                         * Раньше готовая рекомендация перезагружала страницу
                         * посреди разговора — пропадала вся переписка. А шаг
                         * проверки, отправленный, пока Анна отвечает, на
                         * однопоточном сервере задерживал её ответ на минуту.
                         * Теперь шаг ждёт, пока Анна не занята, а вместо
                         * перезагрузки во время разговора — кнопка.
                         */
                        function ready() {
                            if (!window.AiAdvisorActive) { window.location.reload(); return; }

                            const note = document.getElementById('crChecking');
                            note.textContent = @json(__('Рекомендация готова. Обновите страницу, когда закончите разговор с Анной, — она уже знает результаты.')) + ' ';
                            const link = document.createElement('a');
                            link.href = window.location.href;
                            link.textContent = @json(__('Обновить'));
                            note.appendChild(link);
                        }

                        function step() {
                            if (window.AiAdvisorBusy) { setTimeout(step, 2000); return; }
                            if (--tries < 0) { return; }

                            fetch(url, {
                                method: 'POST',
                                headers: {'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                            })
                                .then(function (r) { return r.ok ? r.json() : null; })
                                .then(function (data) {
                                    if (data && data.ready) { ready(); return; }
                                    setTimeout(step, data && data.did && data.did !== 'busy' ? 800 : 5000);
                                })
                                .catch(function () { setTimeout(step, 6000); });
                        }

                        step();
                    })();
                </script>
            @endif

            {{-- обсуждение кандидата с Анной — голосом или текстом --}}
            @include('employer.pages.ai-screening._advisor')

            {{-- ==================== действия работодателя ==================== --}}
            <div class="cr-actions">
                <h2>{{ __('Ваше решение') }}</h2>
                <p class="cr-hint">
                    {{ __('Последнее слово за вами. Прежнее решение никуда не денется: в отчёте останется видно и что посчитал ИИ, и что решили вы.') }}
                </p>

                {{-- ИИ советуется: согласны — один щелчок, и кандидат получает ответ --}}
                @php $pending = $interview->latestDecision && ! $interview->latestDecision->isReleased(); @endphp

                {{--
                    Черновик письма кандидату — можно исправить перед отправкой.
                    Под каждый итог свой текст: под рекомендацию ИИ — его письмо,
                    под другой итог — шаблон. Выбрали другой итог — текст сменится,
                    если вы его ещё не правили. Уходит то, что в поле.
                --}}
                @if($interview->awaitsEmployer() && ! ($interview->latestDecision?->isReleased()))
                    @php
                        $draftDecision = $interview->latestDecision;
                        $draftReasons = $draftDecision?->outcome === 'rejected' ? ($draftDecision->reasons ?? []) : [];
                        $drafts = [
                            'passed' => $draftDecision?->effectiveOutcome() === 'passed' && $draftDecision->candidateMessage()
                                ? $draftDecision->candidateMessage()
                                : \App\Services\Scoring\DecisionMessage::template('passed', $interview, []),
                            'rejected' => $draftDecision?->effectiveOutcome() === 'rejected' && $draftDecision->candidateMessage()
                                ? $draftDecision->candidateMessage()
                                : \App\Services\Scoring\DecisionMessage::template('rejected', $interview, $draftReasons),
                        ];
                        $draftOutcome = in_array($draftDecision?->effectiveOutcome(), ['passed', 'rejected'], true)
                            ? $draftDecision->effectiveOutcome() : 'passed';
                    @endphp
                    <div class="cr-field" style="margin-bottom:12px">
                        <label for="crDraft">{{ __('Черновик письма кандидату') }}</label>
                        <textarea id="crDraft" rows="6" maxlength="5000" style="width:100%; font:inherit; font-size:14px; padding:10px 12px; border-radius:10px; border:1px solid var(--gray-300,#D1D5DB); background:var(--white,#fff); color:var(--ink,#111827)"
                                  data-draft-passed="{{ $drafts['passed'] }}" data-draft-rejected="{{ $drafts['rejected'] }}"
                                  data-outcome="{{ $draftOutcome }}">{{ $drafts[$draftOutcome] }}</textarea>
                        <span class="cr-hint">{{ __('Можно исправить. Кандидат получит ровно этот текст — и только после вашего подтверждения.') }}</span>
                    </div>
                @endif
                @if($pending && in_array($interview->latestDecision->outcome, ['passed', 'rejected'], true))
                    <form method="POST" id="crApprove" action="{{ route('employer.ai.candidate.approve', [$vacancy, $interview]) }}"
                          style="margin-bottom:12px">
                        @csrf
                        <button type="submit" class="cr-btn">
                            ✓ {{ __('Согласен с ИИ:') }} {{ $interview->latestDecision->outcomeLabel() }} — {{ __('отправить ответ кандидату') }}
                        </button>
                    </form>
                    <p class="cr-hint">{{ __('Не согласны — выберите другой итог ниже: кандидат получит ваш ответ.') }}</p>
                @endif

                @unless($interview->awaitsEmployer())
                    <p class="cr-hint">
                        {{ __('Собеседование ещё идёт. Решить можно будет, когда оно закончится или когда система передаст кандидата вам на проверку.') }}
                    </p>
                @else
                <form method="POST" id="crOverride" action="{{ route('employer.ai.candidate.override', [$vacancy, $interview]) }}"
                      style="display:flex; flex-direction:column; gap:10px;">
                    @csrf
                    <div class="cr-row">
                        <div class="cr-field" style="flex:0 0 200px">
                            <label for="outcome">{{ __('Итог') }}</label>
                            <select id="outcome" name="outcome">
                                <option value="passed">{{ __('Прошёл отбор') }}</option>
                                <option value="rejected">{{ __('Отказ') }}</option>
                            </select>
                        </div>
                        <div class="cr-field">
                            <label for="note">{{ __('Почему (увидите только вы)') }}</label>
                            <input id="note" type="text" name="note" maxlength="1000"
                                   placeholder="{{ __('Например: беру, опыт важнее формального балла') }}">
                        </div>
                    </div>
                    <div>
                        <button type="submit" class="cr-btn">
                            {{ $interview->latestDecision ? __('Изменить решение') : __('Принять решение') }}
                        </button>
                    </div>
                </form>
                @endunless

                {{--
                    Черновик письма: под каждый итог — свой текст, правки помнятся
                    для каждого итога отдельно. «Согласен с ИИ» отправляет черновик
                    итога ИИ, «Изменить решение» — черновик выбранного итога. Тот же
                    текст берёт и Анна, когда решение подтверждают голосом.
                --}}
                <script>
                    (function () {
                        const draft = document.getElementById('crDraft');
                        if (!draft) { return; }

                        const texts = {passed: draft.dataset.draftPassed, rejected: draft.dataset.draftRejected};
                        let shown = draft.dataset.outcome;
                        const select = document.getElementById('outcome');

                        function show(outcome) {
                            texts[shown] = draft.value;
                            shown = outcome;
                            draft.value = texts[outcome] || '';
                        }

                        if (select) {
                            select.value = shown;
                            select.addEventListener('change', function () { show(select.value); });
                        }

                        draft.addEventListener('input', function () { texts[shown] = draft.value; });

                        window.CrDraft = {
                            get: function (outcome) {
                                texts[shown] = draft.value;

                                return texts[outcome] || '';
                            },
                        };

                        // в обычную отправку формы — текст письма под её итог
                        function attach(form, outcome) {
                            if (!form) { return; }
                            form.addEventListener('submit', function () {
                                let field = form.querySelector('input[name=message]');
                                if (!field) {
                                    field = document.createElement('input');
                                    field.type = 'hidden';
                                    field.name = 'message';
                                    form.appendChild(field);
                                }
                                field.value = window.CrDraft.get(outcome());
                            });
                        }

                        attach(document.getElementById('crApprove'), function () { return draft.dataset.outcome; });
                        attach(document.getElementById('crOverride'), function () { return select ? select.value : shown; });
                    })();
                </script>
            </div>

            <div class="cr-actions">
                <h2>{{ __('Отчёт и данные') }}</h2>
                <div class="cr-row">
                    <a class="cr-btn cr-btn-ghost"
                       href="{{ route('employer.ai.candidate.export', [$vacancy, $interview]) }}">
                        {{ __('Скачать отчёт') }}
                    </a>
                    <button type="submit" form="purge" class="cr-btn cr-btn-danger"
                            onclick="return confirm('{{ __('Удалить все данные кандидата по этому собеседованию? Отменить будет нельзя.') }}')">
                        {{ __('Удалить данные кандидата') }}
                    </button>
                </div>
                <p class="cr-hint">
                    {{ __('Отчёт скачивается отдельным файлом: открывается без сети и печатается в PDF. Удаление стирает документы, стенограмму, оценки и аудит обращений к модели — в них персональные данные человека. Отклик останется.') }}
                </p>
            </div>

            <form id="purge" method="POST"
                  action="{{ route('employer.ai.candidate.purge', [$vacancy, $interview]) }}" hidden>
                @csrf
                @method('DELETE')
            </form>
        </div>
    </main>
</div>

@include('employer.partials.js')
</body>
</html>
