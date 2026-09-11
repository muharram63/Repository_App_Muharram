<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Задание по навыку') }}</title>
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
        h1, h2, .qz-title { font-family: 'Manrope', 'Inter', sans-serif; }

        .qz-wrap { padding: 0 3vh 4vh 2vh; max-width: 860px; }

        /* текст вопросов и ответов пишет модель, а свой ответ — человек:
           длину не предсказать, поэтому переносим всё внутри карточки */
        .qz-card, .qz-card * { min-width: 0; }
        .qz-text, .qz-opt, .qz-given, .qz-comment, .qz-right, .qz-sub, .qz-score-note, .qz-flash {
            overflow-wrap: anywhere; word-break: break-word;
        }

        .qz-card {
            background: rgba(255, 255, 255, .95); border: 1px solid rgba(255, 255, 255, .7);
            border-radius: var(--radius); overflow: hidden; margin-bottom: 18px;
            box-shadow: 0 26px 54px -34px rgba(5, 15, 45, .45), 0 0 0 1px rgba(44, 95, 224, .06) inset;
        }
        .qz-head { padding: 20px 24px 17px; border-bottom: 1px solid rgba(44, 95, 224, .12); }
        .qz-head-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }

        /* часы: спокойные, пока времени достаточно, и тревожные под конец */
        .qz-clock {
            flex: 0 0 auto; text-align: center; padding: 8px 14px; border-radius: 12px;
            background: var(--blue-ice); border: 1px solid rgba(44, 95, 224, .2);
            transition: background .3s ease, border-color .3s ease;
        }
        .qz-clock-value {
            display: block; font-family: 'Manrope', sans-serif; font-size: 19px;
            font-weight: 800; color: var(--blue-primary); line-height: 1.1;
        }
        .qz-clock-label { font-size: 10.5px; color: var(--slate-soft); }
        .qz-clock.warn { background: #FFFBEB; border-color: #FDE68A; }
        .qz-clock.warn .qz-clock-value { color: #B45309; }
        .qz-clock.out { background: #FEF2F2; border-color: #FECACA; }
        .qz-clock.out .qz-clock-value { color: #B91C1C; }
        .qz-eyebrow {
            font-size: 11px; letter-spacing: 2px; text-transform: uppercase;
            color: var(--blue-primary); font-weight: 700; margin: 0 0 6px;
        }
        .qz-title { font-size: 21px; margin: 0; }
        .qz-sub { margin: 7px 0 0; font-size: 13.5px; color: var(--slate-soft); line-height: 1.6; }

        .qz-q { padding: 20px 24px; border-bottom: 1px solid rgba(44, 95, 224, .08); }
        .qz-q:last-of-type { border-bottom: 0; }
        .qz-num {
            font-size: 11px; letter-spacing: 1.4px; text-transform: uppercase;
            color: var(--slate-soft); font-weight: 700; margin-bottom: 7px;
        }
        .qz-text { font-size: 14.5px; font-weight: 600; line-height: 1.6; margin-bottom: 13px; }

        .qz-opt {
            display: flex; align-items: flex-start; gap: 10px; cursor: pointer;
            padding: 11px 14px; margin-bottom: 8px; border-radius: 12px;
            border: 1px solid rgba(44, 95, 224, .18); background: #fff;
            font-size: 13.5px; line-height: 1.55; color: var(--slate);
            transition: border-color .16s ease, background .16s ease;
        }
        .qz-opt:hover { border-color: var(--blue-primary); }
        .qz-opt input { margin-top: 3px; flex: 0 0 auto; accent-color: var(--blue-primary); }
        .qz-opt:has(input:checked) { border-color: var(--blue-primary); background: var(--blue-ice); }

        /* свой ответ: главная особенность проверки — можно не выбирать из готового */
        .qz-own { margin-top: 10px; }
        .qz-own-label { font-size: 12.5px; font-weight: 600; color: var(--slate); margin-bottom: 6px; }
        .qz-own textarea {
            width: 100%; min-height: 62px; resize: vertical; font: inherit; font-size: 13.5px;
            padding: 11px 13px; border-radius: 12px; border: 1px dashed rgba(44, 95, 224, .35);
            background: #FBFCFE; outline: none;
        }
        .qz-own textarea:focus { border-style: solid; border-color: var(--blue-primary); }
        .qz-own-hint { font-size: 11.5px; color: var(--slate-soft); margin-top: 5px; }

        .qz-foot {
            display: flex; align-items: center; justify-content: space-between; gap: 14px;
            padding: 18px 24px; border-top: 1px solid rgba(44, 95, 224, .12); flex-wrap: wrap;
        }
        .qz-send {
            border: 0; cursor: pointer; color: #fff; font-weight: 700; font-size: 14px;
            padding: 13px 24px; border-radius: 14px;
            background: linear-gradient(135deg, var(--blue-primary), var(--blue-sky));
            box-shadow: 0 16px 30px -16px rgba(44, 95, 224, .95);
        }
        .qz-send:disabled { opacity: .55; cursor: default; }
        .qz-back { font-size: 13px; color: var(--slate-soft); }

        /* ---------- результат ---------- */
        .qz-score {
            display: flex; align-items: center; gap: 18px; padding: 22px 24px;
            border-bottom: 1px solid rgba(44, 95, 224, .12);
        }
        .qz-score-value { font-family: 'Manrope', sans-serif; font-size: 42px; font-weight: 800; line-height: 1; }
        .qz-score.ok .qz-score-value { color: #15803D; }
        .qz-score.no .qz-score-value { color: #B45309; }
        .qz-score-note { font-size: 13.5px; line-height: 1.6; color: var(--slate); }

        .qz-verdict { display: flex; align-items: center; gap: 7px; font-size: 12px; font-weight: 800;
            letter-spacing: .06em; text-transform: uppercase; margin-bottom: 8px; }
        .qz-verdict.ok { color: #15803D; }
        .qz-verdict.no { color: #B91C1C; }
        .qz-verdict svg { width: 14px; height: 14px; }

        .qz-given {
            font-size: 13.5px; line-height: 1.6; padding: 10px 13px; border-radius: 10px;
            background: #F6F8FC; color: var(--slate); margin-bottom: 8px;
        }
        .qz-given b { color: var(--navy-deep); }
        .qz-comment { font-size: 13px; line-height: 1.6; color: var(--slate); }
        .qz-right { font-size: 13px; color: #15803D; margin-top: 6px; }

        .qz-flash {
            margin: 0 0 16px; padding: 12px 16px; border-radius: 12px; font-size: 13.5px;
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
                <h1 id="pageTitle">{{ $test?->skill?->name ?? __('Задание') }}</h1>
                <p id="pageSubtitle">{{ $test?->levelLabel() }} · {{ __('вариант') }} {{ $test?->variant }}</p>
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
            <div class="qz-wrap">

                @if(session('error'))
                    <p class="qz-flash">{{ session('error') }}</p>
                @endif

                @if($attempt->finished_at)
                    {{-- ===== разбор завершённой попытки ===== --}}
                    @php($passed = $attempt->passed())
                    <div class="qz-card">
                        <div class="qz-score {{ $passed ? 'ok' : 'no' }}">
                            <div class="qz-score-value">{{ $attempt->score }}%</div>
                            <div class="qz-score-note">
                                <strong>{{ $passed ? __('Навык подтверждён') : __('Пока не зачтено') }}</strong><br>
                                @if($passed)
                                    {{ __('Результат виден работодателям в вашем резюме.') }}
                                @else
                                    {{ __('Нужно от') }} {{ \App\Models\SkillAttempt::PASSING }}%.
                                    {{ __('Задание можно пройти снова — вариант будет другой.') }}
                                @endif
                            </div>
                        </div>

                        @foreach($questions as $i => $question)
                            @php($verdict = $attempt->review[$i] ?? null)
                            @php($given = $attempt->answers[$i] ?? null)
                            <div class="qz-q">
                                <div class="qz-num">{{ __('Вопрос') }} {{ $i + 1 }}</div>
                                <div class="qz-text">{{ $question['text'] }}</div>

                                @if($verdict)
                                    <div class="qz-verdict {{ $verdict['correct'] ? 'ok' : 'no' }}">
                                        @if($verdict['correct'])
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"
                                                 stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                                            {{ __('Зачтено') }}
                                        @else
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"
                                                 stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                            {{ __('Не зачтено') }}
                                        @endif
                                    </div>
                                @endif

                                <div class="qz-given">
                                    @if(filled($given['own'] ?? null))
                                        <b>{{ __('Ваш ответ своими словами:') }}</b> {{ $given['own'] }}
                                    @elseif(isset($given['option']) && isset($question['options'][$given['option']]))
                                        <b>{{ __('Вы выбрали:') }}</b> {{ $question['options'][$given['option']] }}
                                    @else
                                        <b>{{ __('Ответа нет') }}</b>
                                    @endif
                                </div>

                                @if(filled($verdict['comment'] ?? null))
                                    <div class="qz-comment">{{ $verdict['comment'] }}</div>
                                @endif

                                @if(! ($verdict['correct'] ?? false) && isset($question['options'][$question['answer']]))
                                    <div class="qz-right">
                                        {{ __('Верный ответ:') }} {{ $question['options'][$question['answer']] }}
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        <div class="qz-foot">
                            <span class="qz-back">{{ __('Пройдено') }} {{ $attempt->finished_at->format('d.m.Y H:i') }}</span>
                            <a href="{{ route('applicant.skills') }}" class="qz-send"
                               style="text-decoration:none; display:inline-block;">
                                {{ __('К списку навыков') }}
                            </a>
                        </div>
                    </div>
                @else
                    {{-- ===== прохождение ===== --}}
                    <form action="{{ route('applicant.skills.submit', $attempt) }}" method="post" id="qzForm">
                        @csrf
                        <div class="qz-card">
                            <div class="qz-head">
                                <div class="qz-head-row">
                                    <div>
                                        <p class="qz-eyebrow">{{ __('Проверка навыка') }}</p>
                                        <h2 class="qz-title">{{ $test?->skill?->name }}</h2>
                                    </div>
                                    {{-- отсчёт идёт от срока, выданного сервером: перезагрузка
                                         страницы времени не добавляет --}}
                                    <div class="qz-clock" id="qzClock" data-left="{{ $attempt->secondsLeft() }}">
                                        <span class="qz-clock-value" id="qzClockValue">--:--</span>
                                        <span class="qz-clock-label">{{ __('осталось') }}</span>
                                    </div>
                                </div>
                                <p class="qz-sub">
                                    {{ __('Выберите вариант — или напишите свой ответ в поле ниже вопроса. Свой ответ проверит ИИ по смыслу, а не по совпадению слов.') }}
                                    {{ __('На задание отводится') }} {{ \App\Models\SkillAttempt::MINUTES }} {{ __('минут; когда время выйдет, ответы отправятся сами.') }}
                                </p>
                            </div>

                            @foreach($questions as $i => $question)
                                <div class="qz-q">
                                    <div class="qz-num">{{ __('Вопрос') }} {{ $i + 1 }} {{ __('из') }} {{ count($questions) }}</div>
                                    <div class="qz-text">{{ $question['text'] }}</div>

                                    @foreach($question['options'] as $o => $option)
                                        <label class="qz-opt">
                                            <input type="radio" name="option[{{ $i }}]" value="{{ $o }}">
                                            <span>{{ $option }}</span>
                                        </label>
                                    @endforeach

                                    <div class="qz-own">
                                        <div class="qz-own-label">{{ __('Другое — ответьте своими словами') }}</div>
                                        <textarea name="own[{{ $i }}]" maxlength="600"
                                                  placeholder="{{ __('Если ни один вариант не подходит, объясните, как поступили бы вы') }}"></textarea>
                                        <div class="qz-own-hint">
                                            {{ __('Если поле заполнено, засчитывается именно этот ответ, а не выбранный вариант.') }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            <div class="qz-foot">
                                <span class="qz-back">
                                    {{ __('Проходной результат —') }} {{ \App\Models\SkillAttempt::PASSING }}%
                                </span>
                                <button type="submit" class="qz-send" id="qzSend">{{ __('Отправить на проверку') }}</button>
                            </div>
                        </div>
                    </form>
                @endif

            </div>
        </section>
    </main>
</div>

@if(! $attempt->finished_at)
    <script>
        (function () {
            const form = document.getElementById('qzForm');
            const button = document.getElementById('qzSend');
            const clock = document.getElementById('qzClock');
            const value = document.getElementById('qzClockValue');

            // проверка свободных ответов идёт через модель и занимает секунды:
            // показываем это и не даём отправить форму дважды
            form.addEventListener('submit', function () {
                if (button.disabled) return;
                button.disabled = true;
                button.textContent = @json(__('Проверяем…'));
            });

            let left = parseInt(clock.dataset.left, 10) || 0;
            let sent = false;

            function tick() {
                const minutes = Math.floor(Math.max(0, left) / 60);
                const seconds = Math.max(0, left) % 60;
                value.textContent = minutes + ':' + String(seconds).padStart(2, '0');

                clock.classList.toggle('warn', left <= 120 && left > 30);
                clock.classList.toggle('out', left <= 30);

                if (left <= 0 && !sent) {
                    // время вышло — отправляем то, что успел заполнить человек,
                    // а не теряем ответы вместе с попыткой
                    sent = true;
                    clearInterval(timer);
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                    return;
                }

                left--;
            }

            tick();
            const timer = setInterval(tick, 1000);
        })();
    </script>
@endif

@include('applicant.partials.js')
@include('applicant.partials.script')
</body>
</html>
