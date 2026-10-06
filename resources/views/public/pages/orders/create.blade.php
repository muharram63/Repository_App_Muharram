@extends('public.layouts.app')
@section('content')

    <style>
        .uo-form .f { margin-bottom: 18px; }
        .uo-form label.t { display: block; font-size: 14px; font-weight: 700; margin-bottom: 8px; color: var(--text); }
        .uo-form .opt { font-weight: 500; color: var(--text-muted); }
        .uo-form input[type=text], .uo-form input[type=number], .uo-form input[type=tel], .uo-form select, .uo-form textarea {
            width: 100%; padding: 12px 14px; border: 1px solid var(--border); border-radius: 12px;
            background: var(--surface); color: var(--text); font: inherit; font-size: 15px;
        }
        .uo-form textarea { min-height: 110px; resize: vertical; }
        .uo-form input:focus, .uo-form select:focus, .uo-form textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
        .uo-form .hint { font-size: 12.5px; color: var(--text-muted); margin-top: 6px; line-height: 1.45; }
        .uo-form .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }

        /* вид работ и срок — крупные кнопки-плитки, удобные пальцем */
        .uo-choices { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; }
        .uo-choice { position: relative; }
        .uo-choice input { position: absolute; opacity: 0; inset: 0; margin: 0; cursor: pointer; }
        .uo-choice span {
            display: flex; align-items: center; gap: 8px; min-height: 48px; padding: 10px 12px; border-radius: 12px;
            border: 1.5px solid var(--border); background: var(--surface); font-size: 14px; font-weight: 600; color: var(--text);
        }
        .uo-choice input:checked + span { border-color: var(--accent); background: var(--accent-soft); color: var(--accent-text); }
        .uo-choice input:focus-visible + span { box-shadow: 0 0 0 3px var(--accent-soft); }
        .uo-deadlines { grid-template-columns: repeat(3, 1fr); }
        .uo-deadlines .uo-choice span { flex-direction: column; justify-content: center; text-align: center; gap: 2px; min-height: 64px; }
        .uo-deadlines small { font-weight: 500; font-size: 12px; color: var(--text-muted); }

        .uo-photos { display: grid; grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: 8px; margin-top: 10px; }
        .uo-photo { position: relative; aspect-ratio: 1; border-radius: 10px; overflow: hidden; background: var(--surface-alt, #F1F3F8); }
        .uo-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .uo-pick {
            display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 16px;
            border: 2px dashed var(--border); border-radius: 12px; cursor: pointer; font-weight: 700; color: var(--text);
        }
        .uo-pick:hover { border-color: var(--accent); }
        .uo-lock { display: inline-flex; gap: 6px; align-items: center; font-size: 12.5px; color: var(--text-muted); margin-top: 6px; }
        .uo-submit { width: 100%; padding: 15px; font-size: 16px; }

        @media (max-width: 640px) {
            .uo-form .row2 { grid-template-columns: 1fr; }
            .uo-choices { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .uo-deadlines { grid-template-columns: 1fr; }
            .uo-deadlines .uo-choice span { flex-direction: row; justify-content: space-between; min-height: 52px; padding: 10px 14px; }
            /* 16px — чтобы iOS не увеличивал страницу при вводе */
            .uo-form input, .uo-form select, .uo-form textarea { font-size: 16px; }
        }
    </style>

    <section class="uo-page">
        <div class="container">
            <a class="uo-back" href="{{ route('public.orders.mine') }}">← {{ __('Мои заявки') }}</a>
            <h1 class="uo-h1">🛠 {{ __('Нужен мастер') }}</h1>
            @if($prefill)
                <div class="uo-flash ok" style="margin-top:4px;">↻ {{ __('Данные прошлой заявки подставлены — проверьте их и выберите новый срок. Фото добавьте заново.') }}</div>
            @endif
            <p class="uo-lead">{{ __('Опишите, что случилось, — мастера нужного профиля увидят заявку и откликнутся. Вы посмотрите их работы и выберете исполнителя.') }}</p>

            @include('public.pages.orders._styles')

            @if($errors->any())
                <div class="uo-flash bad" role="alert">
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form class="uo-form" id="uoForm" method="POST" action="{{ route('public.orders.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="uo-card">
                    <div class="f">
                        <label class="t" for="uoTitle">{{ __('Что случилось или что нужно сделать?') }}</label>
                        <input id="uoTitle" type="text" name="title" maxlength="120" required value="{{ old('title', $prefill['title'] ?? null) }}"
                               placeholder="{{ __('Например: Не сливает воду стиральная машина') }}">
                    </div>

                    <div class="f">
                        <span class="t" style="display:block; font-size:14px; font-weight:700; margin-bottom:8px;">{{ __('Какой мастер нужен') }}</span>
                        <div class="uo-choices" role="radiogroup">
                            @foreach($categories as $category)
                                <label class="uo-choice">
                                    <input type="radio" name="work_category_id" value="{{ $category->id }}" required
                                           @checked((int) old('work_category_id', $prefill['work_category_id'] ?? null) === $category->id)>
                                    <span>{{ $category->title() }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="f" style="margin-bottom:0;">
                        <label class="t" for="uoDescription">{{ __('Подробнее') }}</label>
                        <textarea id="uoDescription" name="description" maxlength="2000" required
                                  placeholder="{{ __('Марка и модель, что именно не работает, с чего началось. Чем подробнее — тем точнее мастер назовёт цену.') }}">{{ old('description', $prefill['description'] ?? null) }}</textarea>
                    </div>
                </div>

                <div class="uo-card">
                    <div class="f" style="margin-bottom:0;">
                        <span class="t" style="display:block; font-size:14px; font-weight:700; margin-bottom:8px;">{{ __('Когда нужен мастер') }}</span>
                        <div class="uo-choices uo-deadlines" role="radiogroup">
                            @foreach($deadlines as $value => $label)
                                <label class="uo-choice">
                                    <input type="radio" name="deadline_option" value="{{ $value }}" required
                                           @checked(old('deadline_option', 'today') === $value)>
                                    <span>
                                        {{ __($label) }}
                                        @if(in_array($value, \App\Models\UrgentOrder::URGENT_DEADLINES, true))
                                            <small>🔥 {{ __('Срочно') }}</small>
                                        @else
                                            <small>{{ __('Не горит') }}</small>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <div class="hint">{{ __('Если к сроку мастер не найдётся, заявка закроется сама — её можно будет повторить.') }}</div>
                    </div>
                </div>

                <div class="uo-card">
                    <div class="row2">
                        <div class="f">
                            <label class="t" for="uoCity">{{ __('Город') }}</label>
                            <select id="uoCity" name="city_id" required>
                                <option value="">{{ __('Выберите…') }}</option>
                                @foreach($cities as $city)
                                    <option value="{{ $city->id }}" @selected((int) old('city_id', $prefill['city_id'] ?? null) === $city->id)>{{ __($city->region) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="f">
                            <label class="t" for="uoDistrict">{{ __('Район') }} <span class="opt">({{ __('необязательно') }})</span></label>
                            <input id="uoDistrict" type="text" name="district" maxlength="120" value="{{ old('district', $prefill['district'] ?? null) }}"
                                   placeholder="{{ __('Например: 102 мкр') }}">
                        </div>
                    </div>
                    <div class="f" style="margin-bottom:0;">
                        <label class="t" for="uoAddress">{{ __('Точный адрес') }} <span class="opt">({{ __('необязательно') }})</span></label>
                        <input id="uoAddress" type="text" name="address" maxlength="255" value="{{ old('address', $prefill['address'] ?? null) }}"
                               placeholder="{{ __('Улица, дом, квартира') }}">
                        <div class="uo-lock">🔒 {{ __('Адрес увидит только мастер, которого вы выберете. Остальные видят город и район.') }}</div>
                    </div>
                    <div class="f" style="margin:18px 0 0;">
                        <label class="t" for="uoPhone">{{ __('Телефон для связи') }}</label>
                        <input id="uoPhone" type="tel" name="contact_phone" maxlength="30" required inputmode="tel" autocomplete="tel"
                               value="{{ old('contact_phone', ($prefill['contact_phone'] ?? null) ?: $defaultPhone) }}" placeholder="+992 90 000 00 00">
                        <div class="uo-lock">🔒 {{ __('Номер тоже увидит только выбранный мастер — чтобы договориться о времени.') }}</div>
                    </div>
                </div>

                <div class="uo-card">
                    <div class="f">
                        <label class="t" for="uoBudget">{{ __('Примерный бюджет') }} <span class="opt">({{ __('необязательно') }})</span></label>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <input id="uoBudget" type="number" name="budget" min="1" max="10000000" step="1" inputmode="numeric"
                                   value="{{ old('budget', $prefill['budget'] ?? null) }}" placeholder="300" style="max-width:220px;">
                            <span style="font-weight:600;">{{ __('сомони') }}</span>
                        </div>
                    </div>

                    <div class="f" style="margin-bottom:0;">
                        <span class="t" style="display:block; font-size:14px; font-weight:700; margin-bottom:8px;">
                            {{ __('Фото проблемы') }} <span class="opt">({{ __('необязательно') }})</span>
                        </span>
                        <label class="uo-pick" for="uoPhotos">📷 {{ __('Добавить фото') }}</label>
                        <input id="uoPhotos" type="file" name="photos[]" multiple accept="{{ implode(',', $photoMimes) }}" style="position:absolute; left:-9999px;">
                        <div class="hint">{{ __('До :count фото, каждое до :mb МБ. С фото мастер быстрее поймёт, что взять с собой.', ['count' => $maxPhotos, 'mb' => $photoMb]) }}</div>
                        <div class="uo-photos" id="uoPreview"></div>
                    </div>
                </div>

                <button type="submit" class="uo-btn primary uo-submit" id="uoSubmit">{{ __('Опубликовать заявку') }}</button>
            </form>
        </div>
    </section>

    <script>
        (function () {
            const input = document.getElementById('uoPhotos');
            const preview = document.getElementById('uoPreview');
            const form = document.getElementById('uoForm');
            const submit = document.getElementById('uoSubmit');
            const MAX = {{ (int) $maxPhotos }};
            const MAX_BYTES = {{ (int) $photoMb }} * 1024 * 1024;
            const T = {
                many: {{ \Illuminate\Support\Js::from(__('Можно не больше :count фото — лишние не добавлены.', ['count' => $maxPhotos])) }},
                big: {{ \Illuminate\Support\Js::from(__('Фото больше 10 МБ.')) }},
                sending: {{ \Illuminate\Support\Js::from(__('Отправляем…')) }},
            };

            // показываем выбранные фото и сразу отсекаем лишнее, чтобы не ждать ответа сервера
            input.addEventListener('change', function () {
                preview.innerHTML = '';
                const files = Array.from(input.files);
                let warn = '';

                if (files.length > MAX) { warn = T.many; }
                if (files.some(f => f.size > MAX_BYTES)) { warn = T.big; }

                if (warn) {
                    // сбросить выбор целиком: частично убрать файлы из поля браузер не даёт
                    if (files.length > MAX || files.some(f => f.size > MAX_BYTES)) { input.value = ''; }
                    alert(warn);
                    return;
                }

                files.forEach(function (file) {
                    const box = document.createElement('div');
                    box.className = 'uo-photo';
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    img.alt = '';
                    box.appendChild(img);
                    preview.appendChild(box);
                });
            });

            form.addEventListener('submit', function () {
                // повторное нажатие на медленной связи создало бы вторую заявку
                submit.disabled = true;
                submit.textContent = T.sending;
            });
        })();
    </script>

@endsection
