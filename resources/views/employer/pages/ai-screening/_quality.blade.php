{{--
    Качество кода в задании: балл по рубрике и три оси качества.

    Итог задания — 80 % рубрика и 20 % качество (структура, обработка ошибок,
    качество кода). Складывает код, а не модель; здесь видно, из чего сложилось.

    $part — разбор одного задания из details.parts
--}}
{{-- задание оценила запасная модель — так же, как у ответов в стенограмме --}}
@if(filled($part['model'] ?? null) && $part['model'] !== ($scoringModel ?? config('services.gemini.model_scoring')))
    <div class="rp-why" style="color:var(--ink-warn,#B45309)">
        {{ __('Оценено запасной моделью :model', ['model' => $part['model']]) }}
    </div>
@endif

@if(is_array($part['quality'] ?? null))
    @php($q = $part['quality'])
    <div class="rp-line">
        <span class="rp-chip">{{ __('рубрика') }}: {{ $part['rubric_score'] ?? '—' }}/100 · 80%</span>
        <span class="rp-chip {{ $q['score'] >= 60 ? 'rp-ok' : 'rp-no' }}">{{ __('качество кода') }}: {{ $q['score'] }}/100 · 20%</span>
        <span class="rp-hint">
            {{ __('структура') }} {{ $q['structure'] }} ·
            {{ __('обработка ошибок') }} {{ $q['error_handling'] }} ·
            {{ __('качество') }} {{ $q['code_quality'] }}
        </span>
    </div>
    @if(filled($q['notes'] ?? null))
        <div class="rp-quote">{{ $q['notes'] }}</div>
    @endif
@endif
