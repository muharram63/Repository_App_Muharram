{{-- Постраничная навигация проекта: своя разметка, потому что дефолтная у Laravel рассчитана на Tailwind. --}}
@if ($paginator->hasPages())
    <style>
        .pager{display:flex; flex-wrap:wrap; align-items:center; gap:6px; margin:22px 0 4px; font-size:14px;}
        .pager a, .pager span{
            display:inline-flex; align-items:center; justify-content:center;
            min-width:36px; height:36px; padding:0 10px; border-radius:10px;
            border:1px solid rgba(120,130,160,.28); text-decoration:none; color:inherit;
        }
        .pager a:hover{border-color:rgba(120,130,160,.6);}
        .pager .is-current{background:#4F46E5; border-color:#4F46E5; color:#fff; font-weight:700;}
        .pager .is-disabled{opacity:.4;}
        .pager .dots{border:none; min-width:auto; padding:0 2px;}
        .pager .total{margin-left:auto; opacity:.65; font-size:13px;}
    </style>

    <nav class="pager" role="navigation" aria-label="{{ __('Навигация по страницам') }}">
        @if ($paginator->onFirstPage())
            <span class="is-disabled" aria-disabled="true">‹</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('Назад') }}">‹</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="dots">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="is-current" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('Вперёд') }}">›</a>
        @else
            <span class="is-disabled" aria-disabled="true">›</span>
        @endif

        <span class="total">{{ __('Всего') }}: {{ $paginator->total() }}</span>
    </nav>
@endif
