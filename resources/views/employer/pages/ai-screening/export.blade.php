<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Отчёт по кандидату') }} — {{ $candidate?->name ?? '—' }}</title>
    {{--
        Самодостаточный файл: ни одной внешней ссылки. Он должен открываться на
        машине без интернета и печататься в PDF средствами браузера — иначе
        «экспорт отчёта» превращается в страницу, которую нечем показать.

        Токенов кабинета здесь нет, поэтому в общем партиале у каждого цвета
        стоит запасное значение.
    --}}
    <style>
        body {
            margin: 0; padding: 28px 24px; background: #F8F9FC; color: #111827;
            font: 14px/1.55 -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
        }
        .ex-shell { max-width: 900px; margin: 0 auto; }
        .ex-head {
            display: flex; flex-wrap: wrap; gap: 10px; align-items: baseline;
            justify-content: space-between; margin-bottom: 18px;
        }
        .ex-brand { font-size: 18px; font-weight: 800; letter-spacing: -.2px; }
        .ex-brand span { color: #4F46E5; }
        .ex-when { font-size: 12px; color: #6B7280; }
        .ex-foot {
            margin-top: 22px; padding-top: 14px; border-top: 1px solid #E5E7EB;
            font-size: 12px; color: #6B7280; display: grid; gap: 5px;
        }

        /* при печати фон и рамки только мешают */
        @media print {
            body { background: #fff; padding: 0; }
            .rp-card { break-inside: avoid; border-color: #ddd; }
        }
    </style>
</head>
<body>
<div class="ex-shell">
    <div class="ex-head">
        <div class="ex-brand">Work<span>io</span> — {{ __('отчёт по кандидату') }}</div>
        <div class="ex-when">{{ __('выгружено') }} {{ now()->format('d.m.Y H:i') }}</div>
    </div>

    @include('employer.pages.ai-screening._report')

    <div class="ex-foot">
        <div>
            {{ __('Первичный отбор провёл ИИ-ассистент по критериям, которые задал работодатель. Итоговый балл посчитан по весам вакансии; сами оценки выставляла модель, а сумму и правила применял код.') }}
        </div>
        <div>
            {{ __('Подлинность документов система не проверяла — только сверяла данные с резюме.') }}
        </div>
        <div>
            {{ __('Пол, возраст, национальность, гражданство, религия, семейное положение и здоровье в оценке не участвовали и вырезались из текста до обращения к модели.') }}
        </div>
    </div>
</div>
</body>
</html>
