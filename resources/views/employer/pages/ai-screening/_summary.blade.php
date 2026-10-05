{{--
    Сводка для работодателя — первое, что он видит в отчёте.

    ИИ не решает за работодателя, а советуется: здесь его рекомендация, на чём
    она основана, насколько кандидат подходит под требования и кто он по
    резюме. Подробности — ниже, в полном отчёте.

    Используется и на странице, и в выгружаемом файле — цвета с запасными
    значениями, как в остальном отчёте.
--}}
@php
    $recommendation = $decision?->outcome;
    $released = $decision?->isReleased();

    // насколько закрыты требования: найдено — полностью, частично — наполовину,
    // с весом каждого критерия
    $byCriterion = $checks->keyBy('criterion_id');
    $weighted = 0;
    $weights = 0;
    foreach ($criteria as $criterion) {
        $check = $byCriterion->get($criterion->id);
        $value = match ($check?->status) {
            'found', 'confirmed_in_interview' => 1,
            'partial' => 0.5,
            default => 0,
        };
        $weighted += $value * $criterion->weight;
        $weights += $criterion->weight;
    }
    $match = $weights > 0 ? (int) round($weighted / $weights * 100) : null;

    $strengths = $checks->filter(fn ($c) => $c->isSatisfied());
    $gaps = $checks->reject(fn ($c) => $c->isSatisfied());

    $skills = collect(preg_split('/[,;\n]+/u', (string) ($resume?->skills ?? '')))
        ->map(fn ($s) => trim($s))->filter()->unique()->take(24);

    $details = $submission?->details ?? [];

@endphp

<style>
    .sm-verdict { display: flex; flex-wrap: wrap; gap: 16px; align-items: center; }
    .sm-verdict-icon {
        width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-size: 26px; flex: none;
    }
    .sm-verdict b { font-size: 18px; color: var(--ink, #111827); display: block; }
    .sm-good { background: var(--wash-good, #DCFCE7); }
    .sm-bad { background: var(--wash-danger, #FEF2F2); }
    .sm-warn { background: var(--wash-warn, #FEF3C7); }
    .sm-wait { background: var(--gray-100, #F3F4F6); }
    .sm-meter { height: 10px; border-radius: 99px; background: var(--gray-100, #F3F4F6); overflow: hidden; }
    .sm-meter i { display: block; height: 100%; border-radius: 99px; }
    .sm-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; }
    .sm-list { margin: 0; padding-left: 18px; font-size: 13px; line-height: 1.6; color: var(--ink, #111827); }
    .sm-list li span { color: var(--gray-500, #6B7280); }
    .sm-skills { display: flex; flex-wrap: wrap; gap: 6px; }
    .sm-skill {
        padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600;
        background: var(--indigo-50, #EEF0FF); color: var(--indigo-700, #4338CA);
    }
    .sm-facts { display: grid; grid-template-columns: max-content 1fr; gap: 6px 14px; font-size: 13px; }
    .sm-facts dt { color: var(--gray-500, #6B7280); }
    .sm-facts dd { margin: 0; color: var(--ink, #111827); overflow-wrap: anywhere; }
</style>

{{-- ==================== рекомендация ИИ ==================== --}}
<div class="rp-card">
    <h2>{{ __('Рекомендация ИИ') }}</h2>

    <div class="sm-verdict">
        <div class="sm-verdict-icon {{ match($recommendation) {
            'passed' => 'sm-good', 'rejected' => 'sm-bad', 'manual_review' => 'sm-warn', default => 'sm-wait',
        } }}">{{ match($recommendation) {
            'passed' => '👍', 'rejected' => '✋', 'manual_review' => '🤔', default => '⏳',
        } }}</div>
        <div style="flex:1; min-width:220px">
            <b>{{ match($recommendation) {
                'passed' => __('Рекомендую принять кандидата'),
                'rejected' => __('Рекомендую отказать — вежливо, с причиной'),
                'manual_review' => __('Не берусь решать — нужно ваше решение'),
                default => match (true) {
                    ! $interview->consented() => __('Кандидат ещё не дал согласие на собеседование'),
                    $interview->stage === 'documents' => __('Кандидат загружает документы'),
                    $interview->stage === 'interview' => __('Кандидат отвечает на вопросы ИИ'),
                    $interview->stage === 'test' && ! $submission?->submitted_at => __('Кандидат выполняет тестовое задание'),
                    default => __('Кандидат всё прошёл — ИИ проверяет решения и готовит рекомендацию'),
                },
            } }}</b>
            <span class="rp-hint">
                @if(! $decision)
                    @php
                        $answered = $interview->turns->where('role', 'candidate')->count();
                    @endphp
                    {{ __('Пройдено шагов кандидата: :percent%.', ['percent' => $interview->progress()]) }}
                    @if($interview->stage === 'interview')
                        {{ __('Ответов: :done из :total.', ['done' => $answered, 'total' => $interview->questionTarget()]) }}
                    @endif
                    {{ __('Рекомендация появится здесь, как только всё будет проверено, — мы пришлём уведомление.') }}
                @elseif($released)
                    {{ __('Ответ отправлен кандидату') }} {{ $decision->released_at->format('d.m.Y H:i') }}
                    · {{ $decision->release_source === 'employer' ? __('по вашему решению') : __('отправлен автоматически (по прежним правилам)') }}
                @else
                    {{ __('Ждёт вашего разрешения: ИИ ничего не отправит кандидату, пока вы не подтвердите или не измените решение.') }}
                @endif
            </span>
        </div>
    </div>

    @if($match !== null)
        <div>
            <div class="rp-line" style="justify-content:space-between">
                <span style="font-size:13px; font-weight:600">{{ __('Соответствие требованиям вакансии') }}</span>
                <b style="font-size:15px">{{ $match }}%</b>
            </div>
            <div class="sm-meter"><i style="width: {{ $match }}%; background: {{ $match >= 70 ? '#22C55E' : ($match >= 40 ? '#F59E0B' : '#EF4444') }}"></i></div>
        </div>
    @endif

    <div class="sm-grid">
        <div>
            <div style="font-size:13px; font-weight:700; margin-bottom:6px">✅ {{ __('Подтверждено') }}</div>
            @if($strengths->isNotEmpty())
                <ul class="sm-list">
                    @foreach($strengths as $check)
                        <li>{{ $check->requirement }}
                            <span>— {{ $check->statusLabel() }}@if($check->evidence_quote): «{{ \Illuminate\Support\Str::limit($check->evidence_quote, 90) }}»@endif</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="rp-muted">{{ __('Пока ничего не подтверждено.') }}</p>
            @endif
        </div>
        <div>
            <div style="font-size:13px; font-weight:700; margin-bottom:6px">⚠️ {{ __('Не хватает или частично') }}</div>
            @if($gaps->isNotEmpty())
                <ul class="sm-list">
                    @foreach($gaps as $check)
                        <li>{{ $check->requirement }}
                            <span>— {{ $check->statusLabel() }}{{ $check->isMust() ? ' · '.__('обязательное') : '' }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="rp-muted">{{ __('Все требования закрыты.') }}</p>
            @endif
        </div>
    </div>

    {{-- тестовая часть одной строкой --}}
    @if($submission?->isGraded())
        <div class="rp-line">
            @isset($details['quiz'])
                <span class="rp-chip {{ $details['quiz']['score'] >= 60 ? 'rp-ok' : 'rp-no' }}">
                    {{ __('Тест:') }} {{ $details['quiz']['correct'] }}/{{ $details['quiz']['total'] }}
                </span>
            @endisset
            @foreach($details['parts'] ?? [] as $i => $part)
                <span class="rp-chip {{ $part['score'] >= 60 ? 'rp-ok' : 'rp-no' }}">
                    {{ __('Задание') }} {{ $i + 1 }}: {{ $part['score'] }}/100
                </span>
            @endforeach
            <span class="rp-chip">{{ __('Тестовая часть итого:') }} {{ $submission->score }}/100</span>
        </div>
    @endif
</div>

{{-- ==================== резюме ==================== --}}
<div class="rp-card">
    <h2>{{ __('Резюме кандидата') }}</h2>

    @if($resume)
        <dl class="sm-facts">
            @if($resume->profession)<dt>{{ __('Профессия') }}</dt><dd>{{ $resume->profession }}</dd>@endif
            @if($resume->desired_position)<dt>{{ __('Желаемая должность') }}</dt><dd>{{ $resume->desired_position }}</dd>@endif
            @if($resume->experience_years !== null)<dt>{{ __('Опыт работы') }}</dt><dd>{{ $resume->experience_years }} {{ __('лет') }}</dd>@endif
            @if($resume->place_work)<dt>{{ __('Место работы') }}</dt><dd>{{ $resume->place_work }}</dd>@endif
            @if($resume->desired_salary)<dt>{{ __('Желаемая зарплата') }}</dt><dd>{{ number_format((float) $resume->desired_salary, 0, ',', ' ') }}</dd>@endif
            @if($resume->languages)<dt>{{ __('Языки') }}</dt><dd>{{ $resume->languages }}</dd>@endif
            @if($resume->url_website)<dt>{{ __('Ссылка') }}</dt><dd>{{ $resume->url_website }}</dd>@endif
            @if($candidate?->email)<dt>{{ __('Почта') }}</dt><dd>{{ $candidate->email }}</dd>@endif
        </dl>

        @if($skills->isNotEmpty())
            <div>
                <div style="font-size:13px; font-weight:700; margin-bottom:6px">{{ __('Навыки') }}</div>
                <div class="sm-skills">
                    @foreach($skills as $skill)
                        <span class="sm-skill">{{ $skill }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        @if($resume->description)
            <div>
                <div style="font-size:13px; font-weight:700; margin-bottom:6px">{{ __('О себе') }}</div>
                <div class="rp-text">{{ $resume->description }}</div>
            </div>
        @endif

        <div class="rp-line">
            @if($resume->documents && ($withFileLinks ?? false))
                <a class="rp-chip" href="{{ route('public.files.resume', $resume) }}" target="_blank" rel="noopener">📄 {{ __('Файл резюме') }}</a>
            @endif
            <span class="rp-chip">📎 {{ __('Документов к собеседованию:') }} {{ $documents->count() }}</span>
        </div>
    @else
        <p class="rp-muted">{{ __('Резюме удалено.') }}</p>
    @endif
</div>
