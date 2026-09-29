{{--
    Тело отчёта по кандидату.

    Общее для страницы в кабинете и для выгружаемого файла: данные собирает один
    метод контроллера, разметка тоже одна — иначе экран и файл однажды разойдутся,
    и доверия не будет ни тому, ни другому.

    Цвета заданы через токены кабинета с запасными значениями: в кабинете
    работает тёмная тема, в выгруженном файле токенов нет, и подставляются
    запасные.
--}}
<style>
    .rp { display: flex; flex-direction: column; gap: 16px; }
    .rp-card {
        background: var(--white, #fff); border: 1px solid var(--gray-200, #E5E7EB);
        border-radius: var(--radius, 12px); padding: 18px;
        display: flex; flex-direction: column; gap: 12px; min-width: 0;
    }
    .rp-card h2 { margin: 0; font-size: 15px; color: var(--ink, #111827); }
    .rp-hint { margin: 0; font-size: 12px; color: var(--gray-500, #6B7280); }

    .rp-head { display: flex; flex-wrap: wrap; gap: 14px; align-items: baseline; justify-content: space-between; }
    .rp-name { font-size: 20px; font-weight: 700; color: var(--ink, #111827); }

    .rp-mark { padding: 4px 12px; border-radius: 999px; font-size: 13px; font-weight: 700; }
    .rp-passed { background: var(--wash-good, #DCFCE7); color: var(--ink-good, #15803D); }
    .rp-rejected { background: var(--wash-danger, #FEF2F2); color: var(--danger, #DC2626); }
    .rp-manual { background: var(--wash-warn, #FEF3C7); color: var(--ink-warn, #B45309); }
    .rp-running { background: var(--gray-100, #F3F4F6); color: var(--gray-500, #6B7280); }

    .rp-scores { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; }
    .rp-score {
        border: 1px solid var(--gray-200, #E5E7EB); border-radius: 10px; padding: 10px 12px;
    }
    .rp-score b { display: block; font-size: 22px; color: var(--ink, #111827); }
    .rp-score span { font-size: 12px; color: var(--gray-500, #6B7280); }
    .rp-score-total { border-color: var(--indigo-600, #4F46E5); }

    .rp-line { display: flex; flex-wrap: wrap; gap: 8px; align-items: baseline; }
    .rp-chip {
        padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 600;
        background: var(--gray-100, #F3F4F6); color: var(--gray-500, #6B7280);
    }
    .rp-ok { background: var(--wash-good, #DCFCE7); color: var(--ink-good, #15803D); }
    .rp-no { background: var(--wash-danger, #FEF2F2); color: var(--danger, #DC2626); }
    .rp-half { background: var(--wash-warn, #FEF3C7); color: var(--ink-warn, #B45309); }

    .rp-row {
        border-top: 1px solid var(--gray-200, #E5E7EB); padding-top: 10px;
        display: flex; flex-direction: column; gap: 4px;
    }
    .rp-row:first-of-type { border-top: 0; padding-top: 0; }
    .rp-quote {
        font-size: 13px; color: var(--gray-500, #6B7280);
        border-left: 3px solid var(--gray-200, #E5E7EB); padding-left: 10px;
        overflow-wrap: anywhere;
    }

    .rp-turn { display: flex; gap: 10px; align-items: flex-start; }
    .rp-who {
        flex: none; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px;
        background: var(--indigo-50, #EEF0FF); color: var(--indigo-700, #4338CA);
    }
    .rp-who-me { background: var(--gray-100, #F3F4F6); color: var(--gray-500, #6B7280); }
    .rp-text { font-size: 14px; line-height: 1.55; color: var(--ink, #111827); overflow-wrap: anywhere; white-space: pre-wrap; }
    .rp-why { font-size: 12px; color: var(--gray-500, #6B7280); margin-top: 3px; }

    .rp-pre {
        font-family: ui-monospace, Consolas, monospace; font-size: 12px; line-height: 1.5;
        background: var(--gray-100, #F3F4F6); border-radius: 10px; padding: 12px;
        overflow-x: auto; white-space: pre-wrap; overflow-wrap: anywhere; margin: 0;
        color: var(--ink, #111827);
    }
    .rp-warn {
        font-size: 13px; padding: 10px 12px; border-radius: 10px;
        background: var(--wash-warn, #FEF3C7); color: var(--ink-warn, #B45309);
    }
    .rp-muted { font-size: 13px; color: var(--gray-500, #6B7280); }
</style>

@php
    $outcome = $decision?->effectiveOutcome();
    $markClass = match ($outcome) {
        'passed' => 'rp-passed',
        'rejected' => 'rp-rejected',
        'manual_review' => 'rp-manual',
        default => 'rp-running',
    };
@endphp

<div class="rp">
    {{-- ==================== шапка и баллы ==================== --}}
    <div class="rp-card">
        <div class="rp-head">
            <div>
                <div class="rp-name">{{ $candidate?->name ?? __('Кандидат удалён') }}</div>
                <div class="rp-hint">
                    {{ $resume?->profession }}
                    @if($resume?->experience_years)
                        · {{ __('опыт') }} {{ $resume->experience_years }} {{ __('лет') }}
                    @endif
                    · {{ __('вакансия') }} «{{ $vacancy->title }}»
                </div>
            </div>
            <span class="rp-mark {{ $markClass }}">
                {{ $decision?->outcomeLabel() ?? $interview->stageLabel() }}
            </span>
        </div>

        <div class="rp-scores">
            <div class="rp-score rp-score-total">
                <b>{{ $interview->total_score ?? '—' }}</b>
                <span>{{ __('итог') }}</span>
            </div>
            <div class="rp-score">
                <b>{{ $interview->documents_score ?? '—' }}</b>
                <span>{{ __('документы') }} · {{ $interview->config?->weight_documents }}%</span>
            </div>
            <div class="rp-score">
                <b>{{ $interview->interview_score ?? '—' }}</b>
                <span>{{ __('собеседование') }} · {{ $interview->config?->weight_interview }}%</span>
            </div>
            <div class="rp-score">
                <b>{{ $interview->test_score ?? '—' }}</b>
                <span>{{ __('задание') }} · {{ $interview->config?->weight_test }}%</span>
            </div>
        </div>

        @if($decision)
            <div class="rp-line">
                <span class="rp-chip">{{ __('Основание:') }} {{ $decision->gateLabel() ?? '—' }}</span>
                <span class="rp-chip">{{ __('Пороги:') }}
                    {{ $interview->config?->threshold_reject }}–{{ $interview->config?->threshold_accept }}</span>
                @if($interview->follow_up_round > 0)
                    <span class="rp-chip">{{ __('Были уточняющие вопросы') }}</span>
                @endif
                @if($decision->isOverridden())
                    <span class="rp-chip rp-half">
                        {{ __('Решение изменено человеком:') }} {{ $decision->overriddenBy?->name }}
                    </span>
                @endif
            </div>

            @if($decision->reasons)
                <div class="rp-hint">
                    {{ __('Чего не хватило:') }} {{ implode(', ', $decision->reasons) }}
                </div>
            @endif

            @if($decision->override_note)
                <div class="rp-quote">{{ $decision->override_note }}</div>
            @endif
        @endif
    </div>

    {{-- ==================== требования ==================== --}}
    <div class="rp-card">
        <h2>{{ __('Требования вакансии') }}</h2>
        <p class="rp-hint">
            {{ __('Каждый вывод подкреплён цитатой из резюме, документа или ответа: без неё требование не считается закрытым.') }}
        </p>

        @forelse($checks as $check)
            <div class="rp-row">
                <div class="rp-line">
                    <span class="rp-chip {{ match($check->status) {
                        'found', 'confirmed_in_interview' => 'rp-ok',
                        'partial' => 'rp-half',
                        default => 'rp-no',
                    } }}">{{ $check->statusLabel() }}</span>
                    <b style="font-size:14px">{{ $check->requirement }}</b>
                    <span class="rp-chip">{{ $check->isMust() ? __('Обязательное') : __('Желательное') }}</span>
                    <span class="rp-chip">{{ __('источник:') }} {{ __(\App\Models\AiRequirementCheck::ORIGINS[$check->origin] ?? $check->origin) }}</span>
                </div>
                @if($check->evidence_quote)
                    <div class="rp-quote">«{{ $check->evidence_quote }}»</div>
                @endif
            </div>
        @empty
            <p class="rp-muted">{{ __('Сверка требований ещё не проводилась.') }}</p>
        @endforelse
    </div>

    {{-- ==================== документы ==================== --}}
    <div class="rp-card">
        <h2>{{ __('Документы кандидата') }}</h2>

        @forelse($documents as $document)
            <div class="rp-row">
                <div class="rp-line">
                    <b style="font-size:14px">{{ $document->original_name }}</b>
                    <span class="rp-chip">{{ $document->kindLabel() }}</span>
                    <span class="rp-chip {{ $document->isAnalysed() ? 'rp-ok' : 'rp-no' }}">
                        {{ $document->statusLabel() }}
                    </span>
                </div>

                @if($document->analysis)
                    @php($a = $document->analysis)
                    <div class="rp-hint">
                        {{ $a['institution'] ?? '—' }}
                        @if($a['program'] ?? null) · {{ $a['program'] }} @endif
                        @if($a['year'] ?? null) · {{ $a['year'] }} @endif
                    </div>
                    @if($a['note'] ?? null)
                        <div class="rp-quote">{{ $a['note'] }}</div>
                    @endif
                    @if($a['mismatches'] ?? null)
                        <div class="rp-warn">
                            {{ __('Расхождения с резюме:') }} {{ implode('; ', $a['mismatches']) }}
                        </div>
                    @endif
                @endif

                {{-- Оговорка обязательна и стоит у каждого документа: система не
                     умеет отличать настоящий диплом от поддельного. --}}
                <div class="rp-hint">{{ \App\Models\AiCandidateDocument::AUTHENTICITY_NOTE }}</div>

                @if($document->failure_reason)
                    <div class="rp-hint" style="color:var(--danger,#DC2626)">{{ $document->failure_reason }}</div>
                @endif
            </div>
        @empty
            <p class="rp-muted">{{ __('Кандидат не загружал документов. Это не минус: умение проверяется вопросами и заданием.') }}</p>
        @endforelse
    </div>

    {{-- ==================== несостыковки ==================== --}}
    @if($analysis['inconsistencies'] ?? null)
        <div class="rp-card">
            <h2>{{ __('Что показалось несогласованным') }}</h2>
            <p class="rp-hint">{{ __('Не обвинение, а поводы уточнить. По ним ИИ и задавал вопросы.') }}</p>
            @foreach($analysis['inconsistencies'] as $item)
                <div class="rp-quote">{{ $item }}</div>
            @endforeach
        </div>
    @endif

    {{-- ==================== стенограмма ==================== --}}
    <div class="rp-card">
        <h2>{{ __('Стенограмма собеседования') }}</h2>
        <p class="rp-hint">{{ __('Оценки ответов кандидату не показывались.') }}</p>

        @forelse($turns as $turn)
            <div class="rp-row">
                <div class="rp-turn">
                    <span class="rp-who {{ $turn->isFromCandidate() ? 'rp-who-me' : '' }}">
                        {{ $turn->isFromCandidate() ? __('кандидат') : __('ИИ') }}
                    </span>
                    <div style="min-width:0">
                        <div class="rp-text">{{ $turn->text }}</div>

                        @if($turn->questionKindLabel())
                            <div class="rp-why">{{ $turn->questionKindLabel() }}</div>
                        @endif

                        @if($turn->isScored())
                            <div class="rp-why">
                                <b>{{ $turn->score }}/{{ $turn->max_score }}</b>
                                @if($turn->criterion_key) · {{ $turn->criterion_key }} @endif
                                @if($turn->rationale) — {{ $turn->rationale }} @endif
                            </div>
                        @elseif($turn->isFromCandidate())
                            <div class="rp-why" style="color:var(--danger,#DC2626)">{{ __('Ответ не оценён') }}</div>
                        @endif

                        @if($turn->injection_flagged)
                            <div class="rp-why" style="color:var(--ink-warn,#B45309)">
                                {{ __('В ответе была попытка повлиять на оценку — она проигнорирована') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="rp-muted">{{ __('Разговор ещё не начинался.') }}</p>
        @endforelse
    </div>

    {{-- ==================== тестовое задание ==================== --}}
    <div class="rp-card">
        <h2>{{ __('Тестовое задание') }}</h2>

        @if(! $task)
            <p class="rp-muted">{{ __('Задание не выдавалось.') }}</p>
        @else
            <div class="rp-line">
                <span class="rp-chip">{{ $task->formatLabel() }}</span>
                @if($task->language)<span class="rp-chip">{{ $task->language }}</span>@endif
                <span class="rp-chip">{{ $task->time_limit_minutes }} {{ __('мин') }}</span>
                @if($submission?->score !== null)
                    <span class="rp-chip {{ $submission->score >= 60 ? 'rp-ok' : 'rp-no' }}">
                        {{ $submission->score }}/100
                    </span>
                @endif
            </div>

            <div class="rp-text">{{ $task->statement }}</div>

            @if(! $submission || ! $submission->submitted_at)
                <div class="rp-warn">{{ __('Решение не было отправлено в срок.') }}</div>
            @else
                <h2 style="margin-top:6px">{{ __('Решение кандидата') }}</h2>
                <pre class="rp-pre">{{ $submission->content }}</pre>

                @if($submission->wasExecuted())
                    <h2 style="margin-top:6px">{{ __('Что вывела запущенная программа') }}</h2>
                    <p class="rp-hint">
                        {{ __('Это факт, а не мнение модели: код исполнялся по-настоящему. При расхождении с оценкой верить надо выводу.') }}
                    </p>
                    @foreach($submission->execution as $run)
                        <pre class="rp-pre">{{ trim($run['result']) }}</pre>
                        @if($run['is_error'] ?? false)
                            <div class="rp-warn">{{ __('Запуск завершился ошибкой.') }}</div>
                        @endif
                    @endforeach
                @elseif($task->isCode())
                    <div class="rp-warn">
                        {{ __('Код не запускался: оценка сделана по чтению. Это слабее, чем проверка запуском.') }}
                    </div>
                @endif

                @if($submission->review)
                    <h2 style="margin-top:6px">{{ __('Разбор по критериям') }}</h2>
                    @foreach($submission->review as $point)
                        <div class="rp-line">
                            <span class="rp-chip {{ ($point['passed'] ?? false) ? 'rp-ok' : 'rp-no' }}">
                                {{ ($point['passed'] ?? false) ? __('да') : __('нет') }}
                            </span>
                            <span style="font-size:13px">{{ $point['point'] ?? '' }}</span>
                            @if($point['note'] ?? null)
                                <span class="rp-hint">— {{ $point['note'] }}</span>
                            @endif
                        </div>
                    @endforeach
                @endif
            @endif
        @endif
    </div>

    {{-- ==================== что сказали кандидату ==================== --}}
    @if($decision?->message_to_candidate)
        <div class="rp-card">
            <h2>{{ __('Что получил кандидат') }}</h2>
            <p class="rp-hint">{{ __('Сохранено дословно: формулировки со временем меняются, а сказанное человеку должно быть воспроизводимо.') }}</p>
            <div class="rp-text">{{ $decision->message_to_candidate }}</div>
        </div>
    @endif

    {{-- ==================== служебное ==================== --}}
    <div class="rp-card">
        <h2>{{ __('Как это считалось') }}</h2>
        <div class="rp-line">
            <span class="rp-chip">{{ __('обращений к модели:') }} {{ $usage?->calls ?? 0 }}</span>
            <span class="rp-chip">{{ __('токенов на вход:') }} {{ $usage?->tokens_in ?? 0 }}</span>
            <span class="rp-chip">{{ __('на выход:') }} {{ $usage?->tokens_out ?? 0 }}</span>
            <span class="rp-chip">{{ __('суммарно ждали:') }} {{ round(($usage?->latency ?? 0) / 1000) }} {{ __('с') }}</span>
        </div>

        @if($injections->isNotEmpty())
            <div class="rp-warn">
                {{ __('Попыток повлиять на оценку:') }} {{ $injections->count() }}.
                {{ __('Все проигнорированы, балл за них не снижался.') }}
            </div>
        @endif

        @if($decisions->count() > 1)
            <p class="rp-hint">
                {{ __('Решение пересчитывалось') }} {{ $decisions->count() }} {{ __('раза — видна вся история.') }}
            </p>
        @endif

        <p class="rp-hint">
            {{ __('Согласие на обработку данных получено') }}
            {{ $interview->consent_at?->format('d.m.Y H:i') ?? '—' }}.
        </p>
    </div>
</div>
