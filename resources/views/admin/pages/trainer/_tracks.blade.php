{{--
    Направления тренировок. Навыки — по одному в строке: «Навык; обязательный; 30».
    Первый навык — сама профессия направления. Выключенное направление
    кандидаты не видят, а начатые по нему тренировки идут дальше.
--}}
<div class="tn-card" id="tracks">
    <h2>Направления тренировок</h2>
    <div class="tn-body" style="display:grid; gap:14px">
        @foreach($tracks as $track)
            <details style="border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px">
                <summary style="cursor:pointer; font-weight:600">
                    {{ $track->title }}
                    <span class="tn-muted">· {{ strtoupper($track->language) }} · навыков: {{ count($track->skills ?? []) }}</span>
                    @unless($track->active)<span class="tn-tag rejected">выключено</span>@endunless
                </summary>
                @include('admin.pages.trainer._track-form', ['track' => $track, 'action' => route('superadmin.trainer.tracks.update', $track)])
            </details>
        @endforeach

        <details @if($tracks->isEmpty()) open @endif style="border:1px dashed #cbd5e1; border-radius:10px; padding:10px 12px">
            <summary style="cursor:pointer; font-weight:600; color:#4F46E5">+ Новое направление</summary>
            @include('admin.pages.trainer._track-form', ['track' => null, 'action' => route('superadmin.trainer.tracks.store')])
        </details>
    </div>
</div>
