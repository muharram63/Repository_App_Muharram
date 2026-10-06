{{--
    Галерея портфолио мастера — для публичных страниц (резюме, профиль мастера).

    Параметры:
      $works  — работы с загруженными media и category;
      $limit  — сколько карточек показать сразу (остальные — по кнопке), null — все.

    Нажатие на карточку открывает просмотр на весь экран: листание кнопками,
    стрелками клавиатуры и свайпом на телефоне. Если в работе есть пара
    «до / после», первым идёт ползунок сравнения. Видео грузится только когда
    до него долистали — страница с десятком роликов не должна тянуть их все.

    Подключается несколько раз на странице безопасно: окно просмотра и скрипт
    выводятся один раз.
--}}
@php
    $limit = $limit ?? null;
    $galleryId = 'pg'.substr(md5(uniqid('', true)), 0, 8);
    $categoriesInWorks = $works->pluck('category')->filter()->unique('id')->values();
@endphp

@once
<style>
    .pg-filter { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; }
    .pg-filter button {
        padding: 7px 13px; border-radius: 999px; border: 1px solid var(--border); background: var(--surface);
        color: var(--text); font: inherit; font-size: 13px; font-weight: 600; cursor: pointer;
    }
    .pg-filter button.on { background: var(--accent); border-color: var(--accent); color: #fff; }
    .pg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; }
    .pg-card {
        display: flex; flex-direction: column; text-align: left; padding: 0; overflow: hidden; cursor: pointer;
        border: 1px solid var(--border); border-radius: 14px; background: var(--surface); color: var(--text); font: inherit;
        transition: transform .15s, box-shadow .15s;
    }
    .pg-card:hover, .pg-card:focus-visible { transform: translateY(-2px); box-shadow: 0 14px 30px -18px rgba(16, 24, 48, .45); }
    .pg-card[hidden] { display: none; }
    .pg-cover {
        position: relative; width: 100%; aspect-ratio: 4 / 3; background: var(--surface-alt, #F1F3F8);
        display: flex; align-items: center; justify-content: center; font-size: 34px; color: var(--text-muted);
    }
    .pg-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .pg-badge {
        position: absolute; left: 8px; top: 8px; padding: 3px 9px; border-radius: 999px;
        background: rgba(17, 24, 39, .74); color: #fff; font-size: 11.5px; font-weight: 700;
    }
    .pg-count {
        position: absolute; right: 8px; bottom: 8px; padding: 3px 8px; border-radius: 999px;
        background: rgba(17, 24, 39, .74); color: #fff; font-size: 11.5px; font-weight: 600;
    }
    .pg-info { padding: 12px 14px 14px; display: flex; flex-direction: column; gap: 4px; }
    .pg-title { font-weight: 700; font-size: 14.5px; line-height: 1.35; overflow-wrap: anywhere; }
    .pg-cat { font-size: 12.5px; color: var(--accent-text, var(--accent)); font-weight: 600; }
    .pg-more { margin-top: 14px; }
    .pg-more button {
        width: 100%; padding: 11px; border-radius: 12px; border: 1px dashed var(--border); background: transparent;
        color: var(--text); font: inherit; font-weight: 600; cursor: pointer;
    }

    /* ---------- просмотр на весь экран ---------- */
    .pg-view { position: fixed; inset: 0; z-index: 2000; background: rgba(8, 10, 18, .94); color: #fff; display: none; flex-direction: column; }
    .pg-view.on { display: flex; }
    .pg-top { display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px; }
    .pg-top h3 { margin: 0; font-size: 16px; font-weight: 700; color: #fff; }
    .pg-top small { display: block; margin-top: 3px; color: #B9BED0; font-size: 12.5px; }
    .pg-close {
        margin-left: auto; flex: none; width: 40px; height: 40px; border-radius: 50%; border: 0;
        background: rgba(255, 255, 255, .12); color: #fff; font-size: 22px; cursor: pointer;
    }
    .pg-stage { position: relative; flex: 1; min-height: 0; display: flex; align-items: center; justify-content: center; padding: 0 56px; touch-action: pan-y; }
    .pg-stage img, .pg-stage video { max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 6px; display: block; }
    .pg-nav {
        position: absolute; top: 50%; transform: translateY(-50%); width: 44px; height: 44px; border-radius: 50%;
        border: 0; background: rgba(255, 255, 255, .14); color: #fff; font-size: 22px; cursor: pointer;
    }
    .pg-nav.prev { left: 8px; }
    .pg-nav.next { right: 8px; }
    .pg-nav[hidden] { display: none; }
    .pg-label {
        position: absolute; left: 50%; top: 10px; transform: translateX(-50%); padding: 4px 12px; border-radius: 999px;
        background: rgba(17, 24, 39, .8); font-size: 12.5px; font-weight: 700;
    }
    .pg-label[hidden] { display: none; }
    .pg-bottom { padding: 10px 16px 16px; }
    .pg-desc { margin: 0 0 10px; font-size: 13.5px; line-height: 1.55; color: #D6D9E4; max-height: 5.5em; overflow: auto; white-space: pre-line; }
    .pg-desc:empty { display: none; }
    .pg-strip { display: flex; gap: 8px; overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 2px; }
    .pg-strip button {
        flex: none; width: 64px; height: 48px; padding: 0; border-radius: 8px; overflow: hidden; cursor: pointer;
        border: 2px solid transparent; background: #1E2233; color: #fff; font-size: 16px;
    }
    .pg-strip button.on { border-color: #fff; }
    .pg-strip img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .pg-counter { font-size: 12px; color: #B9BED0; margin-bottom: 8px; }

    /* ---------- ползунок «до / после» ---------- */
    .pg-compare { position: relative; max-width: 100%; max-height: 100%; aspect-ratio: var(--ratio, 4 / 3); width: min(100%, calc((100vh - 220px) * var(--ratio-num, 1.333))); user-select: none; }
    .pg-compare img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; max-width: none; max-height: none; border-radius: 6px; }
    .pg-compare .before { clip-path: inset(0 calc(100% - var(--cut, 50%)) 0 0); }
    .pg-compare .line { position: absolute; top: 0; bottom: 0; left: var(--cut, 50%); width: 3px; margin-left: -1.5px; background: #fff; pointer-events: none; }
    .pg-compare .line::after {
        content: '⇆'; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
        width: 38px; height: 38px; border-radius: 50%; background: #fff; color: #111827;
        display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 700;
    }
    .pg-compare input { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: ew-resize; margin: 0; }
    .pg-compare .tag { position: absolute; bottom: 10px; padding: 3px 10px; border-radius: 999px; background: rgba(17, 24, 39, .8); font-size: 12px; font-weight: 700; pointer-events: none; }
    .pg-compare .tag.l { left: 10px; }
    .pg-compare .tag.r { right: 10px; }

    @media (max-width: 640px) {
        .pg-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .pg-info { padding: 10px; }
        .pg-title { font-size: 13.5px; }
        .pg-stage { padding: 0 8px; }
        .pg-nav { width: 38px; height: 38px; background: rgba(0, 0, 0, .45); }
    }
    @media (prefers-reduced-motion: reduce) { .pg-card { transition: none; } }
</style>

<div class="pg-view" id="pgView" role="dialog" aria-modal="true" aria-labelledby="pgTitle">
    <div class="pg-top">
        <div>
            <h3 id="pgTitle"></h3>
            <small id="pgSub"></small>
        </div>
        <button type="button" class="pg-close" id="pgClose" aria-label="{{ __('Закрыть') }}">×</button>
    </div>
    <div class="pg-stage" id="pgStage">
        <span class="pg-label" id="pgLabel" hidden></span>
        <button type="button" class="pg-nav prev" id="pgPrev" aria-label="{{ __('Назад') }}">‹</button>
        <div id="pgSlot" style="display:contents"></div>
        <button type="button" class="pg-nav next" id="pgNext" aria-label="{{ __('Дальше') }}">›</button>
    </div>
    <div class="pg-bottom">
        <div class="pg-counter" id="pgCounter"></div>
        <p class="pg-desc" id="pgDesc"></p>
        <div class="pg-strip" id="pgStrip"></div>
    </div>
</div>

<script>
    window.PortfolioGallery = (function () {
        const view = document.getElementById('pgView');
        const slot = document.getElementById('pgSlot');
        const label = document.getElementById('pgLabel');
        const strip = document.getElementById('pgStrip');
        const prev = document.getElementById('pgPrev');
        const next = document.getElementById('pgNext');
        const T = {
            compare: {{ \Illuminate\Support\Js::from(__('До / после')) }},
            before: {{ \Illuminate\Support\Js::from(__('До')) }},
            after: {{ \Illuminate\Support\Js::from(__('После')) }},
            of: {{ \Illuminate\Support\Js::from(__(':n из :total')) }},
        };

        let slides = [];
        let index = 0;
        let lastFocus = null;

        function el(tag, attrs) {
            const node = document.createElement(tag);
            Object.keys(attrs || {}).forEach(k => { node[k] = attrs[k]; });
            return node;
        }

        function compare(pair) {
            const box = el('div', {className: 'pg-compare'});
            const after = el('img', {src: pair.after, alt: T.after, className: 'after'});
            const before = el('img', {src: pair.before, alt: T.before, className: 'before'});
            const range = el('input', {type: 'range', min: 0, max: 100, value: 50});
            range.setAttribute('aria-label', T.compare);

            // пропорции берём у снимка «после», чтобы не было полос
            after.addEventListener('load', function () {
                if (after.naturalWidth && after.naturalHeight) {
                    const ratio = after.naturalWidth / after.naturalHeight;
                    box.style.setProperty('--ratio', after.naturalWidth + ' / ' + after.naturalHeight);
                    box.style.setProperty('--ratio-num', ratio.toFixed(3));
                }
            });
            range.addEventListener('input', function () { box.style.setProperty('--cut', range.value + '%'); });
            // свайп по ползунку не должен листать слайды
            range.addEventListener('touchstart', e => e.stopPropagation(), {passive: true});

            box.append(after, before, el('div', {className: 'line'}),
                el('span', {className: 'tag l', textContent: T.before}),
                el('span', {className: 'tag r', textContent: T.after}), range);

            return box;
        }

        function show(i) {
            index = (i + slides.length) % slides.length;
            const slide = slides[index];

            // уходящее видео — на паузу
            slot.querySelectorAll('video').forEach(v => v.pause());
            slot.innerHTML = '';

            if (slide.kind === 'pair') {
                slot.appendChild(compare(slide.pair));
            } else if (slide.type === 'video') {
                const video = el('video', {controls: true, playsInline: true, preload: 'metadata', src: slide.url});
                if (slide.preview) { video.poster = slide.preview; }
                slot.appendChild(video);
            } else {
                slot.appendChild(el('img', {src: slide.url, alt: slide.stage || ''}));
            }

            const tag = slide.kind === 'pair' ? T.compare : slide.stage;
            label.textContent = tag || '';
            label.hidden = !tag;

            document.getElementById('pgCounter').textContent = T.of.replace(':n', index + 1).replace(':total', slides.length);
            prev.hidden = next.hidden = slides.length < 2;

            strip.querySelectorAll('button').forEach((b, n) => b.classList.toggle('on', n === index));
            const active = strip.children[index];
            if (active) { active.scrollIntoView({block: 'nearest', inline: 'center'}); }
        }

        function open(work, start) {
            slides = (work.pair ? [{kind: 'pair', pair: work.pair, preview: work.pair.after}] : [])
                .concat(work.items.map(item => Object.assign({kind: 'item'}, item)));

            if (!slides.length) { return; }

            document.getElementById('pgTitle').textContent = work.title;
            document.getElementById('pgSub').textContent = [work.category, work.date].filter(Boolean).join(' · ');
            document.getElementById('pgDesc').textContent = work.description || '';

            strip.innerHTML = '';
            slides.forEach(function (slide, n) {
                const b = el('button', {type: 'button'});
                b.setAttribute('aria-label', String(n + 1));
                if (slide.preview) {
                    b.appendChild(el('img', {src: slide.preview, alt: '', loading: 'lazy'}));
                } else {
                    b.textContent = '▶';
                }
                b.addEventListener('click', () => show(n));
                strip.appendChild(b);
            });

            lastFocus = document.activeElement;
            view.classList.add('on');
            document.body.style.overflow = 'hidden';
            show(start || 0);
            document.getElementById('pgClose').focus();
        }

        function close() {
            slot.querySelectorAll('video').forEach(v => v.pause());
            slot.innerHTML = '';
            view.classList.remove('on');
            document.body.style.overflow = '';
            if (lastFocus) { lastFocus.focus(); }
        }

        document.getElementById('pgClose').addEventListener('click', close);
        prev.addEventListener('click', () => show(index - 1));
        next.addEventListener('click', () => show(index + 1));

        document.addEventListener('keydown', function (e) {
            if (!view.classList.contains('on')) { return; }
            if (e.key === 'Escape') { close(); }
            if (e.key === 'ArrowLeft') { show(index - 1); }
            if (e.key === 'ArrowRight') { show(index + 1); }
        });

        // свайп влево-вправо на телефоне
        let startX = null;
        const stage = document.getElementById('pgStage');
        stage.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, {passive: true});
        stage.addEventListener('touchend', function (e) {
            if (startX === null) { return; }
            const dx = e.changedTouches[0].clientX - startX;
            startX = null;
            if (Math.abs(dx) > 50 && slides.length > 1) { show(index + (dx < 0 ? 1 : -1)); }
        });

        /** Подключить сетку карточек: клики, фильтр по виду работ, «показать ещё». */
        function bind(root, works) {
            const byId = {};
            works.forEach(w => { byId[w.id] = w; });

            root.querySelectorAll('.pg-card').forEach(function (card) {
                card.addEventListener('click', () => open(byId[card.dataset.work], 0));
            });

            const more = root.querySelector('.pg-more button');
            let expanded = !more;

            function applyFilter(category) {
                let shown = 0;
                const limit = parseInt(root.dataset.limit || '0', 10);
                root.querySelectorAll('.pg-card').forEach(function (card) {
                    const fits = !category || card.dataset.category === category;
                    const within = expanded || !limit || shown < limit;
                    card.hidden = !(fits && within);
                    if (fits && within) { shown++; }
                });
            }

            let current = '';
            root.querySelectorAll('.pg-filter button').forEach(function (button) {
                button.addEventListener('click', function () {
                    current = button.dataset.category;
                    root.querySelectorAll('.pg-filter button').forEach(b => b.classList.toggle('on', b === button));
                    applyFilter(current);
                });
            });

            if (more) {
                more.addEventListener('click', function () {
                    expanded = true;
                    more.parentElement.remove();
                    applyFilter(current);
                });
            }
        }

        return {bind: bind, open: open};
    })();
</script>
@endonce

<div class="pg" id="{{ $galleryId }}" data-limit="{{ $limit ?? 0 }}">
    @if($categoriesInWorks->count() > 1)
        <div class="pg-filter" role="group" aria-label="{{ __('Вид работ') }}">
            <button type="button" class="on" data-category="">{{ __('Все') }} · {{ $works->count() }}</button>
            @foreach($categoriesInWorks as $category)
                <button type="button" data-category="{{ $category->id }}">
                    {{ $category->title() }} · {{ $works->where('work_category_id', $category->id)->count() }}
                </button>
            @endforeach
        </div>
    @endif

    <div class="pg-grid">
        @foreach($works as $work)
            @php
                $cover = $work->cover();
                $total = $work->media->count();
            @endphp
            <button type="button" class="pg-card" data-work="{{ $work->id }}" data-category="{{ $work->work_category_id }}"
                    @if($limit && $loop->index >= $limit) hidden @endif>
                <span class="pg-cover">
                    @if($cover && $cover->previewUrl())
                        <img src="{{ $cover->previewUrl() }}" alt="{{ $work->title }}" loading="lazy">
                    @else
                        ▶
                    @endif
                    @if($work->hasBeforeAfter())
                        <span class="pg-badge">{{ __('До / после') }}</span>
                    @endif
                    <span class="pg-count">
                        📷 {{ $work->photosCount() }}@if($work->videosCount()) · 🎬 {{ $work->videosCount() }}@endif
                    </span>
                </span>
                <span class="pg-info">
                    <span class="pg-title">{{ $work->title }}</span>
                    @if($work->category)
                        <span class="pg-cat">{{ $work->category->title() }}</span>
                    @endif
                </span>
            </button>
        @endforeach
    </div>

    @if($limit && $works->count() > $limit)
        <div class="pg-more">
            <button type="button">{{ __('Показать все работы · :count', ['count' => $works->count()]) }}</button>
        </div>
    @endif
</div>

<script>
    window.PortfolioGallery.bind(
        document.getElementById({{ \Illuminate\Support\Js::from($galleryId) }}),
        {{ \Illuminate\Support\Js::from($works->map->toGallery()->values()) }}
    );
</script>
