@extends('public.layouts.app')
@section('content')

    <style>
        .resume-toolbar{
            display:flex; flex-wrap:wrap; gap:12px; align-items:center;
            margin-bottom:32px;
        }
        .resume-search{
            position:relative; flex:1 1 280px; max-width:380px;
        }
        .resume-search input{
            width:100%; padding:12px 16px 12px 42px;
            background:var(--surface); color:var(--text);
            border:1px solid var(--border); border-radius:12px;
            font-family:inherit; font-size:14.5px; outline:none;
            transition:border-color .2s ease, box-shadow .2s ease;
        }
        .resume-search input:focus{
            border-color:var(--accent);
            box-shadow:0 0 0 3px color-mix(in srgb, var(--accent) 18%, transparent);
        }
        .resume-search svg{
            position:absolute; left:14px; top:50%; transform:translateY(-50%);
            width:17px; height:17px; stroke:var(--text-muted); pointer-events:none;
        }
        .exp-filters{display:flex; flex-wrap:wrap; gap:8px;}
        .exp-filters input{position:absolute; opacity:0; width:0; height:0;}
        .exp-filters label{
            display:inline-block; padding:10px 18px; cursor:pointer;
            background:var(--surface); color:var(--text-muted);
            border:1px solid var(--border); border-radius:999px;
            font-size:13.5px; font-weight:600;
            transition:.18s ease;
        }
        .exp-filters label:hover{border-color:var(--accent); color:var(--text);}
        .exp-filters input:checked + label{
            background:var(--accent); border-color:var(--accent); color:#fff;
        }

        .resume-grid{display:grid; grid-template-columns:repeat(3,1fr); gap:20px;}
        .resume-card{
            background:var(--surface); border:1px solid var(--border); border-radius:18px;
            padding:24px; display:flex; flex-direction:column; gap:14px;
            transition:.2s ease;
        }
        .resume-card:hover{border-color:var(--accent); transform:translateY(-2px); box-shadow:var(--shadow);}
        .rc-top{display:flex; align-items:center; gap:13px;}
        .rc-avatar{
            width:46px; height:46px; border-radius:50%; flex-shrink:0; object-fit:cover;
            background:var(--accent-soft); color:var(--accent-ink);
            display:flex; align-items:center; justify-content:center;
            font-weight:800; font-size:17px;
        }
        :root[data-theme="dark"] .rc-avatar{color:var(--accent);}
        .rc-name{font-weight:700; font-size:15.5px;}
        .rc-city{font-size:12.5px; color:var(--text-muted); margin-top:2px;}
        .rc-profession{font-size:18px; font-weight:800; letter-spacing:-.3px;}
        .rc-position{font-size:13.5px; color:var(--text-muted); margin-top:3px;}
        .rc-meta{display:flex; flex-wrap:wrap; gap:8px;}
        .rc-chip{
            font-size:12px; font-weight:600; padding:5px 11px; border-radius:999px;
            background:var(--surface-alt); border:1px solid var(--border); color:var(--text-muted);
        }
        .rc-chip.skill{background:var(--accent-soft); border-color:transparent; color:var(--accent-ink);}

        /* карточка узкая, а названия навыков бывают длинными — переносим,
           а не выпускаем за край */
        .resume-card, .resume-card *{min-width:0;}
        .rc-chip, .rc-name, .rc-profession, .rc-position, .rc-city{
            overflow-wrap:anywhere; word-break:break-word;
        }
        .rc-chip{max-width:100%;}

        /* подтверждённый навык выделяется намеренно: это проверенный факт */
        .rc-chip.proof{
            display:inline-flex; align-items:center; gap:5px;
            background:#ECFDF3; border-color:#BBF7D0; color:#15803D; font-weight:700;
        }
        .rc-chip.proof{flex-wrap:wrap;}
        .rc-chip.proof svg{width:11px; height:11px; flex:0 0 11px;}
        .rc-chip.proof b{color:#0F5132;}
        .rc-level{
            font-style:normal; font-weight:600; font-size:10.5px;
            padding:1px 6px; border-radius:999px; background:#fff; color:#166534;
        }

        /* ---------- фильтр по подтверждённым навыкам ---------- */
        .proof-filter{
            display:flex; flex-wrap:wrap; align-items:center; gap:8px;
            margin:0 0 18px; padding:12px 14px; border-radius:14px;
            background:#F4FBF7; border:1px solid #CFEDDD;
        }
        .proof-filter-label{
            display:inline-flex; align-items:center; gap:6px;
            font-size:12px; font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:#15803D;
        }
        .proof-filter-label svg{width:13px; height:13px;}
        .proof-chip{
            padding:6px 13px; border-radius:999px; text-decoration:none; font-size:13px; font-weight:600;
            border:1px solid #BBF7D0; background:#fff; color:#15803D;
            transition:border-color .16s ease, background .16s ease;
        }
        .proof-chip:hover{border-color:#15803D;}
        .proof-chip.on{background:#15803D; color:#fff; border-color:#15803D;}
        .proof-clear{margin-left:auto; font-size:12.5px; color:#6B7A90; text-decoration:underline;}
        :root[data-theme="dark"] .rc-chip.skill{color:var(--accent);}
        .rc-foot{
            margin-top:auto; padding-top:16px; border-top:1px solid var(--border);
            display:flex; align-items:center; justify-content:space-between; gap:12px;
        }
        .rc-salary{font-size:17px; font-weight:800; color:var(--accent-ink); white-space:nowrap;}
        :root[data-theme="dark"] .rc-salary{color:var(--accent);}
        .rc-foot .btn-primary{padding:10px 18px; border-radius:11px; font-size:13.5px;}

        .empty-state{
            grid-column:1 / -1; text-align:center; padding:56px 20px;
            background:var(--surface); border:1px dashed var(--border); border-radius:18px;
        }
        .empty-state h3{font-size:18px; font-weight:700; margin-bottom:8px;}
        .empty-state p{color:var(--text-muted); font-size:14.5px;}

        @media (max-width:1024px){ .resume-grid{grid-template-columns:repeat(2,1fr);} }
        @media (max-width:720px){ .resume-grid{grid-template-columns:1fr;} }
    </style>

    <section class="section">
        <div class="container">

            <div class="section-head">
                <div class="eyebrow">{{ __('Соискатели') }}</div>
                <h2>{{ __('Резюме кандидатов, готовых к работе') }}</h2>
                <p>{{ method_exists($resumes, 'total') ? $resumes->total() : $resumes->count() }} резюме на платформе. Найдите специалиста по профессии, навыкам или городу.</p>
            </div>

            <form class="resume-toolbar" action="{{ route('public.resumes.index') }}" method="get">
                <div class="exp-filters">
                    @foreach(['all' => __('Все'), 'noexp' => __('Без опыта'), 'junior' => __('1–2 года'), 'middle' => __('3–5 лет'), 'senior' => __('6+ лет')] as $key => $label)
                        <a href="{{ route('public.resumes.index', array_filter(['exp' => $key === 'all' ? null : $key, 'q' => $query ?: null])) }}"
                           style="display:inline-block; padding:8px 14px; border-radius:999px; text-decoration:none; font-size:13px; font-weight:600;
                                  border:1px solid {{ $exp === $key ? '#4F46E5' : 'rgba(120,130,160,.3)' }};
                                  background:{{ $exp === $key ? '#4F46E5' : 'transparent' }};
                                  color:{{ $exp === $key ? '#fff' : 'inherit' }};">{{ $label }}</a>
                    @endforeach
                </div>

                <div class="resume-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="q" value="{{ $query }}" placeholder="{{ __('Профессия, должность, навык или город...') }}">
                </div>
                @if($exp !== 'all')
                    <input type="hidden" name="exp" value="{{ $exp }}">
                @endif
                @if($proven)
                    <input type="hidden" name="proven" value="{{ $proven }}">
                @endif
                <button type="submit" class="btn-primary" style="padding:10px 20px; border:none; border-radius:10px; background:#4F46E5; color:#fff; font-weight:700; cursor:pointer;">{{ __('Найти') }}</button>
            </form>

            {{-- Фильтр по навыкам, доказанным заданием. Показываем только те,
                 по которым кто-то уже прошёл проверку: иначе список был бы
                 длинным и ничего не находил. --}}
            @if($provenSkills->isNotEmpty())
                <div class="proof-filter">
                    <span class="proof-filter-label">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"
                             stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                        {{ __('Навык подтверждён заданием') }}
                    </span>
                    @foreach($provenSkills as $skill)
                        <a class="proof-chip @if($proven === $skill->id) on @endif"
                           href="{{ route('public.resumes.index', array_filter([
                               'proven' => $proven === $skill->id ? null : $skill->id,
                               'exp' => $exp === 'all' ? null : $exp,
                               'q' => $query ?: null,
                           ])) }}">{{ $skill->name }}</a>
                    @endforeach
                    @if($proven)
                        <a class="proof-clear"
                           href="{{ route('public.resumes.index', array_filter([
                               'exp' => $exp === 'all' ? null : $exp,
                               'q' => $query ?: null,
                           ])) }}">{{ __('сбросить') }}</a>
                    @endif
                </div>
            @endif

            <div class="resume-grid" id="resume-grid">
                @forelse($resumes as $resume)
                    @php
                        $applicant = $resume->applicant;
                        $user = $applicant->user;
                        $years = (int) $resume->experience_years;

                        if ($years <= 0) {
                            $expKey = 'noexp';
                        } elseif ($years <= 2) {
                            $expKey = 'junior';
                        } elseif ($years <= 5) {
                            $expKey = 'middle';
                        } else {
                            $expKey = 'senior';
                        }

                        $skills = array_slice(array_filter(array_map('trim', explode(',', (string) $resume->skills))), 0, 5);

                        // попытки уже загружены контроллером — лишних запросов нет
                        $proofs = App\Models\SkillAttempt::badgesFor($applicant)->take(3);

                        $searchBlob = mb_strtolower(
                            $user->name.' '.
                            $resume->profession.' '.
                            $resume->desired_position.' '.
                            $resume->skills.' '.
                            $resume->languages.' '.
                            $applicant->city.' '.
                            $resume->place_work
                        );
                    @endphp
                    <article class="resume-card" data-experience="{{ $expKey }}" data-search="{{ $searchBlob }}">
                        <div class="rc-top">
                            @if($user->hasAvatar())
                                <img class="rc-avatar" src="{{ asset($user->avatar) }}" alt="{{ $user->name }}">
                            @else
                                <div class="rc-avatar">{{ $user->initials(1) }}</div>
                            @endif
                            <div>
                                <div class="rc-name">{{ $user->name }}</div>
                                <div class="rc-city">📍 {{ $applicant->city ?: '—' }}</div>
                            </div>
                        </div>

                        <div>
                            <div class="rc-profession">{{ $resume->profession }}</div>
                            <div class="rc-position">Желаемая должность: {{ $resume->desired_position }}</div>
                        </div>

                        <div class="rc-meta">
                            <span class="rc-chip">
                                @if($years > 0) Опыт {{ $years }} г. @else Без опыта @endif
                            </span>
                            <span class="rc-chip">👥 {{ $resume->responses_count }} {{ __('откликов') }}</span>
                            <span class="rc-chip">👁 {{ $resume->views }} {{ __('просмотров') }}</span>
                            @if($resume->place_work)
                                <span class="rc-chip">{{ $resume->place_work }}</span>
                            @endif
                            {{-- подтверждённые навыки идут первыми: это проверенный
                                 результат, а не перечисление из резюме --}}
                            @foreach($proofs as $proof)
                                <span class="rc-chip proof"
                                      title="{{ __('Подтверждено заданием') }}: {{ $proof['level'] }}, {{ $proof['score'] }}%">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                                         stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                                    {{ $proof['skill'] }}
                                    {{-- уровень рядом с баллом: «уверенный 70%» и «начальный 100%»
                                         это разные вещи, и работодатель должен их различать --}}
                                    <i class="rc-level">{{ $proof['level'] }}</i>
                                    <b>{{ $proof['score'] }}%</b>
                                </span>
                            @endforeach
                            @foreach($skills as $skill)
                                <span class="rc-chip skill">{{ $skill }}</span>
                            @endforeach
                        </div>

                        <div class="rc-foot">
                            <span class="rc-salary">{{ number_format($resume->desired_salary, 0, ',', ' ') }} {{ __('сомони') }}</span>
                            <a href="{{ route('public.resumes.show', $resume) }}" class="btn-primary">{{ __('Посмотреть резюме') }}</a>
                        </div>
                    </article>
                @empty
                    <div class="empty-state">
                        <h3>{{ __('Пока нет опубликованных резюме') }}</h3>
                        <p>{{ __('Как только соискатели опубликуют свои резюме, они появятся здесь.') }}</p>
                    </div>
                @endforelse

                <div class="empty-state" id="no-results" style="display:none;">
                    <h3>{{ __('Ничего не найдено') }}</h3>
                    <p>{{ __('Попробуйте изменить запрос или сбросить фильтр по опыту.') }}</p>
                </div>

            @if(method_exists($resumes, 'links'))
                {{ $resumes->links() }}
            @endif
            </div>

        </div>
    </section>


@endsection
