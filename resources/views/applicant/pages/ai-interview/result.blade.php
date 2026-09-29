<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Результат собеседования') }}</title>
    @include('applicant.partials.css')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@600;700;800&family=Inter:wght@400;500;600&display=swap"
          rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1 { font-family: 'Manrope', 'Inter', sans-serif; }

        .rs-wrap { padding: 0 3vh 4vh 2vh; display: flex; flex-direction: column; gap: 16px; max-width: 760px; }
        .rs-panel {
            background: var(--white); border: 1px solid var(--gray-200);
            border-radius: var(--radius); padding: 22px;
            display: flex; flex-direction: column; gap: 14px;
        }
        .rs-panel h1 { margin: 0; font-size: 22px; color: var(--ink); }
        .rs-vacancy { font-size: 14px; color: var(--gray-500); }

        .rs-mark {
            align-self: flex-start; padding: 5px 14px; border-radius: 999px;
            font-size: 13px; font-weight: 700;
        }
        .rs-passed { background: var(--wash-good); color: var(--ink-good); }
        .rs-rejected { background: var(--gray-100); color: var(--gray-500); }
        .rs-manual { background: var(--wash-warn); color: var(--ink-warn); }

        .rs-message {
            font-size: 15px; line-height: 1.7; color: var(--ink);
            white-space: pre-wrap; overflow-wrap: anywhere;
        }

        .rs-note {
            font-size: 13px; color: var(--gray-500); border-left: 3px solid var(--gray-200);
            padding-left: 14px; display: grid; gap: 6px;
        }

        .rs-row { display: flex; flex-wrap: wrap; gap: 10px; }
        .rs-btn {
            display: inline-flex; align-items: center; padding: 10px 18px; border-radius: 999px;
            font-size: 14px; font-weight: 600; text-decoration: none;
            background: var(--indigo-600); color: #fff;
        }
        .rs-btn-ghost { background: transparent; color: var(--gray-500); border: 1px solid var(--gray-200); }
    </style>
</head>
<body>
<div class="app">
    @include('applicant.partials.sidebar')

    <main class="main">
        <div class="rs-wrap">
            @php($outcome = $decision->effectiveOutcome())

            <div class="rs-panel">
                <span class="rs-mark {{ match($outcome) {
                    'passed' => 'rs-passed',
                    'rejected' => 'rs-rejected',
                    default => 'rs-manual',
                } }}">{{ $decision->outcomeLabel() }}</span>

                <h1>{{ $vacancy?->title ?? __('Вакансия удалена') }}</h1>
                <div class="rs-vacancy">{{ $vacancy?->employer?->company_name }}</div>

                {{-- Только текст решения. Ни баллов, ни разбора по критериям:
                     внутренние цифры адресованы работодателю. --}}
                <div class="rs-message">{{ $decision->message_to_candidate }}</div>
            </div>

            <div class="rs-panel">
                <div class="rs-note">
                    <div>{{ __('Собеседование провёл ИИ-ассистент по критериям, которые задал работодатель.') }}</div>
                    <div>{{ __('Все обращения к модели сохранены: решение можно объяснить и проверить.') }}</div>
                    @if($decision->isOverridden())
                        <div>{{ __('Итоговое решение принял работодатель.') }}</div>
                    @elseif($outcome === 'manual_review')
                        <div>{{ __('Система не стала решать сама — вашу кандидатуру рассматривает человек.') }}</div>
                    @endif
                </div>

                <div class="rs-row">
                    <a class="rs-btn" href="{{ route('public.vacancies.index') }}">
                        {{ __('Смотреть другие вакансии') }}
                    </a>
                    <a class="rs-btn rs-btn-ghost" href="{{ route('applicant.ai.index') }}">
                        {{ __('К моим собеседованиям') }}
                    </a>
                    {{-- Право на удаление принадлежит кандидату: просить об этом
                         через того, кто отказал, — странная процедура. --}}
                    <button type="submit" form="purge" class="rs-btn rs-btn-ghost"
                            style="cursor:pointer; font:inherit; font-size:14px; font-weight:600;"
                            onclick="return confirm('{{ __('Удалить ваши ответы, документы и оценки по этому собеседованию? Отменить будет нельзя.') }}')">
                        {{ __('Удалить мои данные') }}
                    </button>
                </div>

                <form id="purge" method="POST" action="{{ route('applicant.ai.purge', $interview) }}" hidden>
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
