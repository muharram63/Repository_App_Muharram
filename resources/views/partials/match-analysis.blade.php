{{--
    Разбор совпадения кандидата и вакансии.

    Компонент самодостаточный: встаёт и на страницу вакансии, и на страницу
    резюме, поэтому не опирается на переменные и стили этих страниц.

    $options    — с чем сравнивать: [['url' => ..., 'label' => ...], ...]
    $emptyHint  — что сказать, если сравнивать не с чем (необязательно)
--}}
@php($mxOptions = collect($options ?? [])->filter(fn ($o) => filled($o['url'] ?? null))->values())

<div class="mx-card">
    <div class="mx-head">
        <div>
            <div class="mx-eyebrow">AI Match Analysis</div>
            <div class="mx-title">{{ __('Разбор совпадения') }}</div>
        </div>
        {{-- общий балл намеренно вторичен: главное — разбивка по навыкам ниже --}}
        <div class="mx-score" id="mxScore" hidden>
            <span class="mx-score-value">—</span>
            <span class="mx-score-label">{{ __('совпадение') }}</span>
        </div>
    </div>

    @if($mxOptions->isEmpty())
        <div class="mx-body">
            <p class="mx-muted">{{ $emptyHint ?? __('Сравнивать пока не с чем.') }}</p>
        </div>
    @else
        <div class="mx-body" id="mxBody" data-url="{{ $mxOptions->first()['url'] }}">
            @if($mxOptions->count() > 1)
                <select class="mx-pick" id="mxPick" aria-label="{{ __('Что сравнивать') }}">
                    @foreach($mxOptions as $option)
                        <option value="{{ $option['url'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
            @endif

            {{-- пока идёт разбор, показываем «скелет», а не пустоту --}}
            <div class="mx-load" id="mxLoad">
                <span class="mx-bar" style="width:70%"></span>
                <span class="mx-bar" style="width:52%"></span>
                <span class="mx-bar" style="width:61%"></span>
                <span class="mx-hint">{{ __('ИИ сравнивает навыки…') }}</span>
            </div>

            <div id="mxResult" hidden>
                <p class="mx-verdict" id="mxVerdict"></p>

                <div class="mx-group" id="mxMatchedBox" hidden>
                    <div class="mx-group-head mx-ok">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                             stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                        {{ __('Совпадает') }} <span class="mx-count" id="mxMatchedCount"></span>
                    </div>
                    <div class="mx-tags" id="mxMatched"></div>
                </div>

                <div class="mx-group" id="mxMissingBox" hidden>
                    <div class="mx-group-head mx-no">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                             stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                        {{ __('Не хватает') }} <span class="mx-count" id="mxMissingCount"></span>
                    </div>
                    <div class="mx-tags" id="mxMissing"></div>
                </div>

                <div class="mx-group" id="mxPartialBox" hidden>
                    <div class="mx-group-head mx-half">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                             stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/></svg>
                        {{ __('Частично') }} <span class="mx-count" id="mxPartialCount"></span>
                    </div>
                    <div class="mx-tags" id="mxPartial"></div>
                </div>

                {{-- подробности по запросу: интерфейс остаётся чистым --}}
                <button type="button" class="mx-more" id="mxMore" hidden>
                    <span id="mxMoreText">{{ __('Почему такой результат') }}</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                         stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                </button>
                <div class="mx-details" id="mxDetails">
                    <div class="mx-details-inner" id="mxDetailsInner"></div>
                </div>

                <p class="mx-stamp" id="mxStamp"></p>
            </div>

            <div class="mx-error" id="mxError" hidden>
                <span id="mxErrorText"></span>
                <button type="button" id="mxRetry">{{ __('Повторить') }}</button>
            </div>
        </div>
    @endif
</div>

<style>
    /* Скрытие через атрибут hidden должно побеждать раскладку. Правила вроде
       .mx-load{display:flex} перебивают браузерное [hidden]{display:none}:
       из-за этого «скелет» не исчезал после ответа, а кнопка подробностей
       показывалась даже тогда, когда пояснять нечего, и клик ничего не давал. */
    .mx-card [hidden], .mx-card[hidden] { display: none !important; }

    .mx-card {
        background: #fff; border: 1px solid #E3E7EF; border-radius: 14px;
        box-shadow: 0 18px 40px -32px rgba(16, 24, 48, .55);
        overflow: hidden; margin-bottom: 18px;
        font-family: 'Inter', 'Segoe UI', Arial, sans-serif; color: #1B2333;
    }
    .mx-head {
        display: flex; align-items: center; justify-content: space-between; gap: 12px;
        padding: 15px 18px; border-bottom: 1px solid #EEF1F6;
        background: linear-gradient(135deg, #F6F9FF, #FFFFFF 70%);
    }
    .mx-eyebrow {
        font-size: 10.5px; letter-spacing: .14em; text-transform: uppercase;
        font-weight: 800; color: #2C5FE0;
    }
    .mx-title { font-size: 15px; font-weight: 700; margin-top: 3px; }
    /* балл — мелкая приписка сбоку, а не главный герой карточки */
    .mx-score { text-align: right; flex: 0 0 auto; }
    .mx-score-value { display: block; font-size: 19px; font-weight: 800; color: #2C5FE0; line-height: 1; }
    .mx-score-label { font-size: 10.5px; color: #8195B8; }

    .mx-body { padding: 16px 18px 18px; }
    .mx-muted { margin: 0; font-size: 13px; color: #6B7A90; line-height: 1.6; }

    .mx-pick {
        width: 100%; margin-bottom: 13px; padding: 9px 11px; font: inherit; font-size: 13px;
        border: 1px solid #DDE3EE; border-radius: 10px; background: #FBFCFE; color: inherit;
    }

    /* ---------- ожидание ---------- */
    .mx-load { display: flex; flex-direction: column; gap: 9px; }
    .mx-bar {
        height: 11px; border-radius: 6px;
        background: linear-gradient(90deg, #EEF2F9 25%, #E2E9F6 50%, #EEF2F9 75%);
        background-size: 220% 100%; animation: mx-shine 1.3s linear infinite;
    }
    .mx-hint { font-size: 12px; color: #8195B8; margin-top: 2px; }
    @keyframes mx-shine { from { background-position: 120% 0; } to { background-position: -120% 0; } }

    /* ---------- результат ---------- */
    .mx-verdict { margin: 0 0 14px; font-size: 13.5px; line-height: 1.65; color: #2B3648; }
    .mx-group { margin-bottom: 13px; animation: mx-rise .4s ease both; }
    .mx-group-head {
        display: flex; align-items: center; gap: 6px;
        font-size: 11.5px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase;
        margin-bottom: 7px;
    }
    .mx-group-head svg { width: 14px; height: 14px; }
    .mx-ok { color: #15803D; }
    .mx-no { color: #B91C1C; }
    .mx-half { color: #B45309; }
    .mx-count { color: #8195B8; font-weight: 700; }

    .mx-tags { display: flex; flex-wrap: wrap; gap: 6px; }
    .mx-tag {
        font-size: 12.5px; font-weight: 600; padding: 5px 10px; border-radius: 8px;
        border: 1px solid transparent; line-height: 1.45;
    }
    .mx-tag.ok { background: #ECFDF3; color: #15803D; border-color: #BBF7D0; }
    /* навык, доказанный заданием: плотнее рамка и галочка */
    .mx-tag.ok.proven {
        display: inline-flex; align-items: center; gap: 5px;
        border-color: #15803D; background: #DCFCE7; font-weight: 700;
    }
    .mx-tag.ok.proven svg { width: 11px; height: 11px; flex: 0 0 11px; }
    .mx-tag.ok.proven .mx-level {
        font-style: normal; font-weight: 600; font-size: 10.5px;
        padding: 1px 6px; border-radius: 999px; background: #fff; color: #166534;
    }
    .mx-tag.ok.proven b { color: #0F5132; }
    .mx-tag.no { background: #FEF2F2; color: #B91C1C; border-color: #FECACA; }
    .mx-tag.half { background: #FFFBEB; color: #B45309; border-color: #FDE68A; }

    /* ---------- подробности ---------- */
    .mx-more {
        display: inline-flex; align-items: center; gap: 6px; cursor: pointer;
        border: 1px solid #DDE3EE; background: #fff; color: #2C5FE0;
        border-radius: 999px; padding: 7px 13px; font: 700 12.5px inherit;
        transition: background .18s ease;
    }
    .mx-more:hover { background: #F4F8FF; }
    .mx-more svg { width: 13px; height: 13px; transition: transform .25s ease; }
    .mx-more.open svg { transform: rotate(180deg); }

    /* плавное раскрытие без прыжка: сетка анимируется, в отличие от height:auto */
    .mx-details { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .3s ease; }
    .mx-details.open { grid-template-rows: 1fr; }
    .mx-details-inner { overflow: hidden; }
    .mx-detail { padding-top: 12px; font-size: 13px; line-height: 1.6; color: #45536B; }
    .mx-detail b { color: #1B2333; }

    .mx-stamp { margin: 12px 0 0; font-size: 11px; color: #9AA7BC; }

    .mx-error {
        background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B;
        border-radius: 10px; padding: 11px 13px; font-size: 12.5px; line-height: 1.55;
    }
    .mx-error button {
        display: block; margin-top: 8px; cursor: pointer;
        border: 1px solid #F87171; background: #fff; color: #B91C1C;
        border-radius: 999px; padding: 5px 13px; font: 600 12.5px inherit;
    }

    @keyframes mx-rise { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
    @media (prefers-reduced-motion: reduce) {
        .mx-group { animation: none; }
        .mx-bar { animation: none; }
        .mx-details { transition: none; }
    }
</style>

<script>
    (function () {
        const body = document.getElementById('mxBody');
        if (!body) return;

        const load = document.getElementById('mxLoad');
        const result = document.getElementById('mxResult');
        const error = document.getElementById('mxError');
        const errorText = document.getElementById('mxErrorText');
        const retry = document.getElementById('mxRetry');
        const pick = document.getElementById('mxPick');
        const score = document.getElementById('mxScore');
        const more = document.getElementById('mxMore');
        const details = document.getElementById('mxDetails');
        const calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        const esc = value => String(value ?? '').replace(/[&<>"]/g, c => (
            {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]
        ));

        // подтверждённые заданием навыки помечаем галочкой: это проверенный
        // факт, и он должен читаться иначе, чем строчка из резюме
        let proven = {};

        function tags(box, countBox, items, kind) {
            const wrap = box.parentElement;
            if (!items || !items.length) {
                wrap.hidden = true;
                return;
            }
            wrap.hidden = false;
            countBox.textContent = items.length;
            box.innerHTML = items.map(function (i) {
                const badge = kind === 'ok' ? proven[String(i).toLowerCase().trim()] : null;

                if (!badge) {
                    return '<span class="mx-tag ' + kind + '">' + esc(i) + '</span>';
                }

                // уровень и балл прямо на плашке: «проверено» без уровня
                // не отвечает на вопрос, насколько глубоко проверено
                return '<span class="mx-tag ok proven" title="'
                    + @json(__('Подтверждено заданием на платформе')) + '">'
                    + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"'
                    + ' stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>'
                    + esc(i)
                    + '<i class="mx-level">' + esc(badge.level) + '</i>'
                    + '<b>' + esc(badge.score) + '%</b></span>';
            }).join('');
        }

        function render(data) {
            const a = data.analysis || {};

            // ключ — название навыка в нижнем регистре, значение — уровень и балл
            proven = {};
            (a.proven_skills || []).forEach(function (p) {
                if (p && p.skill) proven[String(p.skill).toLowerCase().trim()] = p;
            });

            document.getElementById('mxVerdict').textContent = a.verdict || '';

            // данных мало — показываем только честное объяснение, без цифр и списков
            if (a.enough_data === false) {
                score.hidden = true;
                ['mxMatchedBox', 'mxMissingBox', 'mxPartialBox'].forEach(id => {
                    document.getElementById(id).hidden = true;
                });
                more.hidden = true;
            } else {
                if (a.score === null || a.score === undefined) {
                    score.hidden = true;
                } else {
                    score.hidden = false;
                    score.querySelector('.mx-score-value').textContent = a.score + '%';
                }

                tags(document.getElementById('mxMatched'), document.getElementById('mxMatchedCount'),
                    a.matched_skills, 'ok');
                tags(document.getElementById('mxMissing'), document.getElementById('mxMissingCount'),
                    a.missing_skills, 'no');
                tags(document.getElementById('mxPartial'), document.getElementById('mxPartialCount'),
                    (a.partial_matches || []).map(p => p.title), 'half');

                // Подробности объясняют, из чего сложился результат. Раньше здесь
                // были только пояснения к частичным совпадениям — а их часто нет,
                // и кнопка открывала пустоту.
                const parts = [];
                const matched = (a.matched_skills || []).length;
                const missing = (a.missing_skills || []).length;
                const partial = (a.partial_matches || []).length;

                if (matched + missing + partial > 0) {
                    parts.push('<div class="mx-detail"><b>' + @json(__('Из чего сложился результат')) + '</b> — '
                        + @json(__('совпало')) + ' ' + matched + ' ' + @json(__('из')) + ' '
                        + (matched + missing + partial) + ' ' + @json(__('требований вакансии')) + '.'
                        + (partial ? ' ' + partial + ' ' + @json(__('засчитано частично')) + '.' : '')
                        + (missing ? ' ' + missing + ' ' + @json(__('не подтверждено')) + '.' : '')
                        + '</div>');
                }

                if (Object.keys(proven).length) {
                    parts.push('<div class="mx-detail"><b>' + @json(__('Проверено заданием')) + '</b> — '
                        + @json(__('часть навыков подтверждена не резюме, а результатом теста на платформе.'))
                        + '</div>');
                }

                (a.partial_matches || []).filter(p => p.note).forEach(p => {
                    parts.push('<div class="mx-detail"><b>' + esc(p.title) + '</b> — ' + esc(p.note) + '</div>');
                });

                more.hidden = parts.length === 0;
                document.getElementById('mxDetailsInner').innerHTML = parts.join('');
            }

            document.getElementById('mxStamp').textContent =
                data.updated_at ? @json(__('Разбор от')) + ' ' + data.updated_at : '';

            load.hidden = true;
            error.hidden = true;
            result.hidden = false;
        }

        let timer = null;

        async function ask(url) {
            if (timer) { clearInterval(timer); timer = null; }
            load.hidden = false;
            result.hidden = true;
            error.hidden = true;

            try {
                const response = await fetch(url, {
                    headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                });

                let data = {};
                try { data = await response.json(); } catch (e) { /* пустое тело */ }

                if (!response.ok) {
                    throw Object.assign(
                        new Error(data.error || @json(__('Не удалось получить разбор.'))),
                        {retryAfter: data.retry_after || 0},
                    );
                }

                render(data);
            } catch (e) {
                load.hidden = true;
                result.hidden = true;
                error.hidden = false;
                errorText.textContent = e.message;

                // провайдер назвал паузу — отсчитываем и повторяем сами
                let left = Number(e.retryAfter) || 0;
                if (!left) {
                    retry.textContent = @json(__('Повторить'));
                    return;
                }
                const tick = () => {
                    retry.textContent = left > 0
                        ? @json(__('Повтор через')) + ' ' + left + ' ' + @json(__('с'))
                        : @json(__('Повторяю…'));
                    if (left-- <= 0) { clearInterval(timer); timer = null; ask(url); }
                };
                tick();
                timer = setInterval(tick, 1000);
            }
        }

        retry.addEventListener('click', () => ask(body.dataset.url));

        if (pick) {
            pick.addEventListener('change', () => {
                body.dataset.url = pick.value;
                ask(pick.value);
            });
        }

        more.addEventListener('click', () => {
            const open = details.classList.toggle('open');
            more.classList.toggle('open', open);
            document.getElementById('mxMoreText').textContent = open
                ? @json(__('Свернуть подробности'))
                : @json(__('Почему такой результат'));
        });

        ask(body.dataset.url);
    })();
</script>
