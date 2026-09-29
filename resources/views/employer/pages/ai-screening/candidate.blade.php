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

            @include('employer.pages.ai-screening._report')

            {{-- ==================== действия работодателя ==================== --}}
            <div class="cr-actions">
                <h2>{{ __('Ваше решение') }}</h2>
                <p class="cr-hint">
                    {{ __('Последнее слово за вами. Прежнее решение никуда не денется: в отчёте останется видно и что посчитал ИИ, и что решили вы.') }}
                </p>

                <form method="POST" action="{{ route('employer.ai.candidate.override', [$vacancy, $interview]) }}"
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
                        <button type="submit" class="cr-btn">{{ __('Изменить решение') }}</button>
                    </div>
                </form>
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
