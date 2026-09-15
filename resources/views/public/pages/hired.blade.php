@extends('public.layouts.app')

@section('content')

    <style>
        .hr-page { padding: 44px 0 80px; }

        .hr-back {
            display: inline-flex; align-items: center; gap: 7px; font-size: 13.5px;
            color: var(--text-muted); margin-bottom: 18px;
        }
        .hr-back:hover { color: var(--accent-text); }

        .hr-head { max-width: 62ch; margin-bottom: 28px; }
        .hr-head h1 {
            font-family: 'Manrope', sans-serif; font-size: clamp(26px, 3.6vw, 38px);
            font-weight: 800; letter-spacing: -.02em; margin-bottom: 10px;
        }
        .hr-head .count { color: var(--accent-text); }
        .hr-head p { font-size: 15px; color: var(--text-muted); line-height: 1.6; }

        /* ---------- список ---------- */
        .hr-list {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 18px; box-shadow: var(--shadow); overflow: hidden;
        }
        /* одна сетка на шапку и строки, иначе колонки разъезжаются */
        .hr-row, .hr-head-row {
            display: grid;
            /* только доли: auto и minmax считаются по содержимому, а каждая
               строка — отдельная сетка, поэтому колонки получались разной
               ширины и вертикальные линии не сходились в одну */
            grid-template-columns: 1.5fr 1.1fr 0.9fr 1.1fr;
            gap: 0;
            /* stretch, а не center: при центрировании ячейка равна своему
               содержимому, и вертикальные линии выходили разной длины —
               ровными они становятся, только когда ячейка занимает всю строку */
            align-items: stretch;
            padding: 0 22px; border-top: 1px solid var(--border);
        }
        /* вертикальные линии между колонками: видно, что к чему относится */
        .hr-row > *, .hr-head-row > * {
            padding: 15px 16px; min-width: 0;
            border-left: 1px solid var(--border);
            display: flex; flex-direction: column; justify-content: center;
        }
        .hr-row > *:first-child, .hr-head-row > *:first-child {
            border-left: none; padding-left: 0;
        }
        .hr-row > *:last-child, .hr-head-row > *:last-child { padding-right: 0; }

        /* шапка таблицы */
        .hr-head-row {
            border-top: none; background: var(--surface-alt);
            font-size: 11.5px; font-weight: 700; letter-spacing: .08em;
            text-transform: uppercase; color: var(--text-muted);
        }
        .hr-head-row > * { padding-top: 11px; padding-bottom: 11px; }
        .hr-row:hover { background: var(--accent-soft); }
        /* имя — главное в строке, должность под ним помельче */
        .hr-person { font-weight: 700; font-size: 14.5px; }
        .hr-role { font-size: 13px; color: var(--text-muted); margin-top: 2px; }
        .hr-company { font-size: 14px; }
        .hr-city { font-size: 13px; color: var(--text-muted); overflow-wrap: break-word; }
        .hr-when {
            font-size: 12.5px; color: var(--text-muted);
            font-variant-numeric: tabular-nums; gap: 5px;
        }
        /* кто кому написал первым: отклик кандидата или приглашение компании */
        .hr-tag {
            font-size: 11px; font-weight: 700; letter-spacing: .04em;
            padding: 3px 9px; border-radius: 999px; white-space: nowrap;
            background: var(--accent-soft); color: var(--accent-text);
        }
        .hr-tag.invite { background: color-mix(in srgb, var(--good) 14%, transparent); color: var(--good); }

        @media (max-width: 760px) {
            /* на узком экране колонки складываются, и вертикальные линии
               только мешают — оставляем горизонтальные */
            .hr-head-row { display: none; }
            .hr-row { grid-template-columns: 1fr; row-gap: 2px; padding: 14px 22px; }
            .hr-row > * {
                border-left: none; padding: 0; height: auto;
                flex-direction: row; align-items: baseline; gap: 8px;
            }
        }

        .hr-empty { padding: 40px 22px; text-align: center; color: var(--text-muted); font-size: 14px; }
        .hr-pages { margin-top: 20px; }
        .hr-note { font-size: 12.5px; color: var(--text-muted); margin-top: 14px; max-width: 70ch; }
    </style>

    <section class="hr-page">
        <div class="container">

            <a href="{{ route('public.stats') }}" class="hr-back">← {{ __('К статистике') }}</a>

            <div class="hr-head">
                <h1><span class="count">{{ $total }}</span> {{ __('человек вышли на работу') }}</h1>
                <p>{{ __('Кто и на какую должность вышел. Каждая строка — принятый отклик: либо кандидат откликнулся на вакансию и его взяли, либо компания написала по резюме и кандидат согласился.') }}</p>
            </div>

            <div class="hr-list">
                @if($hires->isNotEmpty())
                    <div class="hr-head-row">
                        <div>{{ __('Кандидат и должность') }}</div>
                        <div>{{ __('Компания') }}</div>
                        <div>{{ __('Город') }}</div>
                        <div>{{ __('Когда и откуда') }}</div>
                    </div>
                @endif

                @forelse($hires as $hire)
                    <div class="hr-row">
                        <div>
                            <div class="hr-person">{{ $hire->person }}</div>
                            <div class="hr-role">{{ $hire->role }}</div>
                        </div>
                        <div class="hr-company">{{ $hire->company }}</div>
                        <div class="hr-city">
                            {{ $hire->city ? __(\App\Support\PublicNumbers::shortCity($hire->city)) : '—' }}
                        </div>
                        <div class="hr-when">
                            <span class="hr-tag {{ $hire->source === 'resume' ? 'invite' : '' }}">
                                {{ $hire->source === 'resume' ? __('по приглашению') : __('по отклику') }}
                            </span>
                            {{ \Illuminate\Support\Carbon::parse($hire->hired_at)->translatedFormat('d F Y') }}
                        </div>
                    </div>
                @empty
                    <div class="hr-empty">{{ __('Пока никого не приняли — здесь появятся первые выходы на работу.') }}</div>
                @endforelse
            </div>

            <div class="hr-pages">{{ $hires->links() }}</div>

            <p class="hr-note">
                {{ __('Страница открыта всем. Показаны имя, должность, компания, город и дата — контактов, почты и зарплаты здесь нет.') }}
            </p>
        </div>
    </section>

@endsection
