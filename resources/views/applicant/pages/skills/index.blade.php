<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Проверка навыков') }}</title>
    @include('applicant.partials.css')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap"
          rel="stylesheet">
    <style>
        :root {
            --navy-deep: #071433;
            --blue-primary: #2C5FE0;
            --blue-sky: #5B8DEF;
            --blue-ice: #EAF1FC;
            --slate: #44506B;
            --slate-soft: #8195B8;
            --radius: 18px;
        }

        body { font-family: 'Inter', sans-serif; color: var(--navy-deep); }
        h1, h2, h3, .sk-title { font-family: 'Manrope', 'Inter', sans-serif; }

        .sk-wrap { padding: 0 3vh 4vh 2vh; display: flex; flex-direction: column; gap: 18px; }

        /* названия навыков приходят из справочника и бывают длинными —
           переносим внутри карточек, а не выпускаем за край */
        .sk-card, .sk-card * { min-width: 0; }
        .sk-chip, .sk-badge, .sk-badge-name, .sk-sub, .sk-empty, .sk-table td, .sk-note, .sk-flash {
            overflow-wrap: anywhere; word-break: break-word;
        }
        .sk-chip, .sk-badge { max-width: 100%; }
        .sk-badge { flex-wrap: wrap; }

        .sk-card {
            background: rgba(255, 255, 255, .94); border: 1px solid rgba(255, 255, 255, .7);
            border-radius: var(--radius); overflow: hidden;
            box-shadow: 0 26px 54px -34px rgba(5, 15, 45, .45), 0 0 0 1px rgba(44, 95, 224, .06) inset;
        }
        .sk-head { padding: 20px 24px 16px; border-bottom: 1px solid rgba(44, 95, 224, .12); }
        .sk-eyebrow {
            font-size: 11px; letter-spacing: 2px; text-transform: uppercase;
            color: var(--blue-primary); font-weight: 700; margin: 0 0 6px;
        }
        .sk-title { font-size: 21px; margin: 0; }
        .sk-sub { margin: 7px 0 0; font-size: 13.5px; color: var(--slate-soft); line-height: 1.6; }
        .sk-body { padding: 20px 24px 24px; }

        /* ---------- подтверждённые навыки ---------- */
        .sk-badges { display: flex; flex-wrap: wrap; gap: 10px; }
        .sk-badge {
            display: flex; align-items: center; gap: 10px;
            border: 1px solid #BBF7D0; background: #ECFDF3; border-radius: 12px; padding: 10px 14px;
        }
        .sk-badge-score {
            font-family: 'Manrope', sans-serif; font-size: 17px; font-weight: 800; color: #15803D;
        }
        .sk-badge-name { font-size: 13.5px; font-weight: 700; }
        .sk-badge-meta { font-size: 11.5px; color: #4D7C5A; }
        .sk-empty { font-size: 13.5px; color: var(--slate-soft); line-height: 1.65; margin: 0; }

        /* ---------- выбор навыка ---------- */
        .sk-area { margin-bottom: 16px; }
        .sk-area-name {
            font-size: 11px; letter-spacing: 1.4px; text-transform: uppercase;
            color: var(--slate-soft); font-weight: 700; margin-bottom: 8px;
        }
        .sk-chips { display: flex; flex-wrap: wrap; gap: 7px; }
        .sk-chip {
            cursor: pointer; border: 1px solid rgba(44, 95, 224, .22); background: #fff;
            border-radius: 999px; padding: 7px 14px; font-size: 13px; font-weight: 600; color: var(--slate);
            transition: border-color .16s ease, color .16s ease, box-shadow .16s ease;
        }
        .sk-chip:hover { border-color: var(--blue-primary); }
        .sk-chip input { position: absolute; opacity: 0; width: 0; height: 0; }
        .sk-chip:has(input:checked) {
            border-color: var(--blue-primary); color: var(--navy-deep);
            box-shadow: 0 0 0 3px rgba(44, 95, 224, .12);
        }
        /* уже подтверждённый навык видно в списке */
        .sk-chip.done { border-color: #86EFAC; background: #F6FEF9; }

        .sk-levels { display: flex; flex-wrap: wrap; gap: 7px; margin: 4px 0 18px; }

        .sk-go {
            border: 0; cursor: pointer; color: #fff; font-weight: 700; font-size: 14px;
            padding: 13px 24px; border-radius: 14px;
            background: linear-gradient(135deg, var(--blue-primary), var(--blue-sky));
            box-shadow: 0 16px 30px -16px rgba(44, 95, 224, .95);
            transition: transform .15s ease, opacity .2s ease;
        }
        .sk-go:hover:not(:disabled) { transform: translateY(-1px); }
        .sk-go:disabled { opacity: .5; cursor: default; }

        /* ---------- история ---------- */
        .sk-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        .sk-table th {
            text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .05em;
            color: var(--slate-soft); font-weight: 700; padding: 0 10px 9px;
            border-bottom: 1px solid rgba(44, 95, 224, .12);
        }
        .sk-table td { padding: 11px 10px; border-bottom: 1px solid rgba(44, 95, 224, .07); color: var(--slate); }
        .sk-table tr:last-child td { border-bottom: 0; }
        .sk-res { font-weight: 800; }
        .sk-res.ok { color: #15803D; }
        .sk-res.no { color: #B45309; }

        .sk-note {
            margin: 0; padding: 14px 17px; border-radius: 12px; font-size: 13.5px; line-height: 1.6;
            background: #FFF7ED; border: 1px solid #FDBA74; color: #9A3412;
        }
        .sk-flash {
            margin: 0 0 4px; padding: 12px 16px; border-radius: 12px; font-size: 13.5px;
            background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B;
        }
    </style>
</head>
<body>
<div class="app">
    @include('applicant.partials.sidebar')

    <main class="main">
        <div class="topbar">
            <div>
                <h1 id="pageTitle">{{ __('Проверка навыков') }}</h1>
                <p id="pageSubtitle">{{ __('Докажите умение делом, а не строчкой в резюме') }}</p>
            </div>
            @include('partials.notification-bell')
            <div class="user-chip">
                <div class="avatar" style="padding: 14px 16px;">
                    @if($user->hasAvatar())
                        <img src="{{ asset($user->avatar) }}" alt="{{ $user->name }}"
                             style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                    @else
                        <div class="avatar text-white d-flex align-items-center justify-content-center"
                             style="font-size:13px; font-weight:700; background:#1656D6;">{{ $user->initials(1) }}</div>
                    @endif
                </div>
                <div>
                    <div class="name">{{ $user->name }}</div>
                    <div class="role">{{ $user->email }}</div>
                </div>
            </div>
        </div>

        <section class="section active" id="section-profile">
            <div class="sk-wrap">

                @if(session('error'))
                    <p class="sk-flash">{{ session('error') }}</p>
                @endif

                @unless($enabled)
                    <p class="sk-note">
                        {{ __('Проверка навыков пока выключена: в файле .env не задан GEMINI_API_KEY.') }}
                    </p>
                @endunless

                {{-- ===== подтверждённые навыки ===== --}}
                <div class="sk-card">
                    <div class="sk-head">
                        <p class="sk-eyebrow">{{ __('Ваши подтверждения') }}</p>
                        <h2 class="sk-title">{{ __('Навыки, доказанные на деле') }}</h2>
                        <p class="sk-sub">
                            {{ __('Работодатель видит эти результаты в вашем резюме. Диплом для них не нужен — только само задание.') }}
                        </p>
                    </div>
                    <div class="sk-body">
                        @if($badges->isEmpty())
                            <p class="sk-empty">
                                {{ __('Пока ничего не подтверждено. Выберите навык ниже — задание занимает 10–15 минут.') }}
                            </p>
                        @else
                            <div class="sk-badges">
                                @foreach($badges as $badge)
                                    <div class="sk-badge">
                                        <span class="sk-badge-score">{{ $badge['score'] }}%</span>
                                        <span>
                                            <span class="sk-badge-name">{{ $badge['skill'] }}</span><br>
                                            <span class="sk-badge-meta">
                                                {{ $badge['level'] }} · {{ $badge['at']?->format('d.m.Y') }}
                                            </span>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- ===== выбор задания ===== --}}
                <div class="sk-card">
                    <div class="sk-head">
                        <p class="sk-eyebrow">{{ __('Новая проверка') }}</p>
                        <h2 class="sk-title">{{ __('Выберите навык и уровень') }}</h2>
                        <p class="sk-sub">
                            {{ __('Практические вопросы: у каждого есть варианты ответа и поле «свой ответ» — если готовые формулировки вам не подходят, ответьте своими словами, это проверит ИИ.') }}
                            {{ __('Длину задания выбираете вы, времени даётся по') }}
                            {{ \App\Models\SkillAttempt::MINUTES_PER_QUESTION }} {{ __('минуты на вопрос.') }}
                            {{ __('Повторить проверку по тому же навыку можно через') }}
                            {{ \App\Models\SkillAttempt::COOLDOWN_MINUTES }} {{ __('минут.') }}
                        </p>
                    </div>
                    <div class="sk-body">
                        @php($doneSkills = $badges->pluck('skill')->all())
                        <form action="{{ route('applicant.skills.start') }}" method="post" id="skForm">
                            @csrf

                            @foreach($skills as $area => $group)
                                <div class="sk-area">
                                    <div class="sk-area-name">{{ $area ?: __('Прочее') }}</div>
                                    <div class="sk-chips">
                                        @foreach($group as $skill)
                                            <label class="sk-chip @if(in_array($skill->name, $doneSkills, true)) done @endif">
                                                <input type="radio" name="skill_id" value="{{ $skill->id }}" required>
                                                {{ $skill->name }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach

                            <div class="sk-area-name" style="margin-top:20px;">{{ __('Уровень') }}</div>
                            <div class="sk-levels">
                                @foreach(\App\Models\Skill::LEVELS as $key => $label)
                                    <label class="sk-chip">
                                        <input type="radio" name="level" value="{{ $key }}"
                                               @checked($key === 'confident')>
                                        {{ __($label) }}
                                    </label>
                                @endforeach
                            </div>

                            <div class="sk-area-name" style="margin-top:20px;">{{ __('Длина задания') }}</div>
                            <div class="sk-levels">
                                @foreach(\App\Models\SkillTest::LENGTHS as $count => $label)
                                    <label class="sk-chip">
                                        <input type="radio" name="questions" value="{{ $count }}"
                                               @checked($count === \App\Models\SkillTest::DEFAULT_LENGTH)>
                                        {{-- 5, 10 и 15 — у всех одна форма слова, склонять нечего --}}
                                        {{ __($label) }} — {{ $count }} {{ __('вопросов') }},
                                        {{ \App\Models\SkillAttempt::minutesFor($count) }} {{ __('мин') }}
                                    </label>
                                @endforeach
                            </div>

                            <button type="submit" class="sk-go" id="skGo" @unless($enabled) disabled @endunless>
                                {{ __('Начать проверку') }}
                            </button>
                        </form>
                    </div>
                </div>

                {{-- ===== история ===== --}}
                @if($attempts->isNotEmpty())
                    <div class="sk-card">
                        <div class="sk-head">
                            <p class="sk-eyebrow">{{ __('История') }}</p>
                            <h2 class="sk-title">{{ __('Пройденные задания') }}</h2>
                        </div>
                        <div class="sk-body" style="padding:16px 14px 18px;">
                            <table class="sk-table">
                                <thead>
                                <tr>
                                    <th>{{ __('Навык') }}</th>
                                    <th>{{ __('Уровень') }}</th>
                                    <th>{{ __('Результат') }}</th>
                                    <th>{{ __('Дата') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($attempts as $attempt)
                                    <tr>
                                        <td>
                                            <a href="{{ route('applicant.skills.attempt', $attempt) }}"
                                               style="font-weight:700; color:inherit;">
                                                {{ $attempt->test?->skill?->name ?? '—' }}
                                            </a>
                                        </td>
                                        <td>{{ $attempt->test?->levelLabel() }}</td>
                                        <td class="sk-res {{ $attempt->passed() ? 'ok' : 'no' }}">
                                            {{ $attempt->score }}%
                                            <span style="font-weight:600; color:var(--slate-soft);">
                                                {{ $attempt->passed() ? __('зачтено') : __('не зачтено') }}
                                            </span>
                                        </td>
                                        <td>{{ $attempt->finished_at?->format('d.m.Y H:i') }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

            </div>
        </section>
    </main>
</div>

<script>
    // составление задания занимает несколько секунд — показываем, что идёт работа,
    // и не даём отправить форму дважды
    document.getElementById('skForm').addEventListener('submit', function () {
        const button = document.getElementById('skGo');
        if (button.disabled) return;
        button.disabled = true;
        button.textContent = @json(__('Готовим задание…'));
    });
</script>

@include('applicant.partials.js')
@include('applicant.partials.script')
</body>
</html>
