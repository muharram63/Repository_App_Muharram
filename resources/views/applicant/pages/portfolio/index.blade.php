<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Портфолио работ') }}</title>
    @include('applicant.partials.css')
    <style>
        .pf-intro { font-size: 14px; color: var(--gray-500); line-height: 1.55; margin: 0 0 18px; max-width: 720px; }
        .pf-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 16px; }
        .pf-card {
            border: 1px solid var(--gray-200); border-radius: 14px; overflow: hidden;
            background: var(--white); display: flex; flex-direction: column;
        }
        .pf-cover {
            position: relative; aspect-ratio: 4 / 3; background: var(--gray-100);
            display: flex; align-items: center; justify-content: center; font-size: 38px; color: var(--gray-500);
        }
        .pf-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .pf-tag {
            position: absolute; left: 10px; top: 10px; padding: 4px 9px; border-radius: 999px;
            font-size: 11.5px; font-weight: 700; background: rgba(17, 24, 39, .72); color: #fff;
        }
        .pf-body { padding: 14px; display: flex; flex-direction: column; gap: 6px; flex: 1; }
        .pf-title { font-weight: 700; font-size: 15px; overflow-wrap: anywhere; }
        .pf-cat { font-size: 12.5px; color: var(--indigo-700); font-weight: 600; }
        .pf-meta { font-size: 12.5px; color: var(--gray-500); }
        .pf-actions { display: flex; gap: 8px; margin-top: auto; padding-top: 8px; }
        .pf-actions form { margin: 0; }
        .pf-actions .btn { padding: 8px 12px; font-size: 13px; }
        .pf-empty { text-align: center; padding: 40px 16px; }
        .pf-empty .big { font-size: 44px; margin-bottom: 8px; }
        .pf-empty p { color: var(--gray-500); font-size: 14px; max-width: 460px; margin: 0 auto 18px; line-height: 1.55; }

        @media (max-width: 900px) {
            .pf-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; }
            .card { padding: 16px; }
            .card-header { flex-wrap: wrap; gap: 10px; }
        }
        @media (max-width: 420px) {
            .pf-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="app">
    @include('applicant.partials.sidebar')

    <main class="main">
        @include('applicant.pages.portfolio._head', [
            'title' => __('Портфолио работ'),
            'subtitle' => __('Покажите, что вы умеете, — фото и видео выполненных работ'),
        ])

        <div class="card">
            <div class="card-header">
                <h2>{{ __('Мои работы') }} · {{ $works->count() }}</h2>
                @if($works->count() < $maxWorks)
                    <a href="{{ route('applicant.portfolio.create') }}" class="btn btn-primary">+ {{ __('Добавить работу') }}</a>
                @endif
            </div>

            @if($works->isEmpty())
                <div class="pf-empty">
                    <div class="big">📸</div>
                    <div style="font-size:16px; font-weight:700; margin-bottom:6px;">{{ __('Портфолио пока пустое') }}</div>
                    <p>{{ __('Сантехнику, маляру, электрику или мастеру по ремонту не нужен диплом, чтобы доказать умение: покажите сделанную работу. Снимите «до» и «после» — заказчики верят фотографиям больше, чем словам.') }}</p>
                    <a href="{{ route('applicant.portfolio.create') }}" class="btn btn-primary">+ {{ __('Добавить первую работу') }}</a>
                </div>
            @else
                <p class="pf-intro">{{ __('Работы видят заказчики и работодатели в вашем профиле. Новые — сверху.') }}</p>

                <div class="pf-grid">
                    @foreach($works as $work)
                        @php $cover = $work->cover(); @endphp
                        <article class="pf-card">
                            <a class="pf-cover" href="{{ route('applicant.portfolio.edit', $work) }}">
                                @if($cover && $cover->previewUrl())
                                    <img src="{{ $cover->previewUrl() }}" alt="{{ $work->title }}" loading="lazy">
                                @else
                                    ▶
                                @endif
                                @if($work->hasBeforeAfter())
                                    <span class="pf-tag">{{ __('До / после') }}</span>
                                @endif
                            </a>
                            <div class="pf-body">
                                <div class="pf-title">{{ $work->title }}</div>
                                @if($work->category)
                                    <div class="pf-cat">{{ $work->category->title() }}</div>
                                @endif
                                <div class="pf-meta">
                                    📷 {{ $work->photosCount() }}
                                    @if($work->videosCount()) · 🎬 {{ $work->videosCount() }} @endif
                                    @if($work->done_at) · {{ $work->done_at->format('m.Y') }} @endif
                                </div>
                                <div class="pf-actions">
                                    <a href="{{ route('applicant.portfolio.edit', $work) }}" class="btn btn-secondary">{{ __('Изменить') }}</a>
                                    <form method="POST" action="{{ route('applicant.portfolio.destroy', $work) }}"
                                          data-confirm="{{ __('Удалить работу «:title» вместе со всеми фото и видео?', ['title' => $work->title]) }}"
                                          onsubmit="return confirm(this.dataset.confirm)">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-secondary" style="color:#E14A4A;">{{ __('Удалить') }}</button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </main>
</div>

@include('applicant.partials.js')
</body>
</html>
