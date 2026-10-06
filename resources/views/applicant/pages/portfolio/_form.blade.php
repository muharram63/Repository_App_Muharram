{{--
    Форма работы портфолио — общая для создания и правки.

    Файлы выбираются кнопкой (на телефоне — галерея или камера), до отправки
    видны превью, у каждого можно отметить «до» или «после» и убрать лишнее.
    Для видео браузер снимает кадр-обложку. Отправка идёт скриптом с полосой
    загрузки: видео с телефона грузится не мгновенно, и без полосы человек
    решит, что всё зависло.

    Без скрипта форма отправляется обычным способом — просто без обложек и
    отметок у новых файлов.
--}}
@php
    $existing = $work->exists ? $work->media : collect();
    $acceptTypes = implode(',', array_merge($limits['photoMimes'], $limits['videoMimes']));
@endphp

<style>
    .pf-form .field { margin-bottom: 16px; }
    .pf-form .hint { font-size: 12.5px; color: var(--gray-500); margin-top: 6px; line-height: 1.45; }
    .pf-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0 20px; }
    .pf-count { float: right; font-weight: 500; color: var(--gray-500); font-size: 12px; }

    .pf-drop {
        display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px;
        width: 100%; padding: 26px 16px; border: 2px dashed var(--gray-300); border-radius: 14px;
        background: var(--gray-50, #F9FAFB); color: var(--ink); cursor: pointer; text-align: center;
        font: inherit;
    }
    .pf-drop:hover, .pf-drop.drag { border-color: var(--indigo-600); background: var(--indigo-50); }
    .pf-drop b { font-size: 15px; }
    .pf-drop span { font-size: 12.5px; color: var(--gray-500); }
    .pf-drop .ico { font-size: 30px; }

    .pf-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; margin-top: 14px; }
    .pf-tile { border: 1px solid var(--gray-200); border-radius: 12px; overflow: hidden; background: var(--white); position: relative; }
    .pf-thumb {
        aspect-ratio: 4 / 3; background: var(--gray-100); display: flex; align-items: center; justify-content: center;
        font-size: 30px; color: var(--gray-500); position: relative;
    }
    .pf-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .pf-thumb .play {
        position: absolute; inset: auto auto 8px 8px; padding: 3px 8px; border-radius: 999px;
        background: rgba(17, 24, 39, .72); color: #fff; font-size: 11px; font-weight: 700;
    }
    .pf-tile select { width: 100%; border: 0; border-top: 1px solid var(--gray-200); padding: 9px 10px; font-size: 13px; background: var(--white); color: var(--ink); }
    .pf-tile .x {
        position: absolute; top: 6px; right: 6px; width: 30px; height: 30px; border-radius: 50%;
        border: 0; background: rgba(17, 24, 39, .72); color: #fff; font-size: 17px; line-height: 1; cursor: pointer;
    }
    .pf-tile .name { font-size: 11.5px; color: var(--gray-500); padding: 6px 10px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .pf-progress { display: none; margin-top: 16px; }
    .pf-progress.on { display: block; }
    .pf-bar { height: 10px; border-radius: 99px; background: var(--gray-100); overflow: hidden; }
    .pf-bar i { display: block; height: 100%; width: 0; background: var(--indigo-600); transition: width .2s; }
    .pf-progress small { display: block; margin-top: 6px; font-size: 12.5px; color: var(--gray-500); }

    .pf-errors { margin-bottom: 16px; }
    .pf-errors[hidden] { display: none; }

    @media (max-width: 900px) {
        .pf-row { grid-template-columns: 1fr; }
        .pf-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .pf-form .form-actions { flex-direction: column-reverse; }
        .pf-form .form-actions .btn { width: 100%; justify-content: center; padding: 13px 18px; font-size: 15px; }
        /* на телефоне 16px — иначе iOS увеличивает страницу при вводе */
        .pf-form input, .pf-form select, .pf-form textarea { font-size: 16px !important; }
    }
</style>

<div class="pf-flash bad pf-errors" id="pfErrors" role="alert" @if(! $errors->any()) hidden @endif>
    <ul id="pfErrorList">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>

<form class="pf-form" id="pfForm" method="POST" enctype="multipart/form-data"
      action="{{ $work->exists ? route('applicant.portfolio.update', $work) : route('applicant.portfolio.store') }}"
      data-photos="{{ $existing->where('type', 'photo')->count() }}"
      data-videos="{{ $existing->where('type', 'video')->count() }}">
    @csrf
    @if($work->exists)
        @method('PUT')
    @endif

    <div class="field">
        <label for="pfTitle">{{ __('Название работы') }}</label>
        <input id="pfTitle" type="text" name="title" maxlength="120" required
               value="{{ old('title', $work->title) }}"
               placeholder="{{ __('Например: Замена труб в ванной') }}">
    </div>

    <div class="pf-row">
        <div class="field">
            <label for="pfCategory">{{ __('Вид работ') }}</label>
            <select id="pfCategory" name="work_category_id" required>
                <option value="">{{ __('Выберите…') }}</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) old('work_category_id', $work->work_category_id) === $category->id)>
                        {{ $category->title() }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="pfDone">{{ __('Когда выполнена') }} <span style="font-weight:400; color:var(--gray-500)">({{ __('необязательно') }})</span></label>
            <input id="pfDone" type="date" name="done_at" max="{{ now()->toDateString() }}"
                   value="{{ old('done_at', $work->done_at?->toDateString()) }}">
        </div>
    </div>

    <div class="field">
        <label for="pfDescription">
            {{ __('Что сделано') }}
            <span class="pf-count" id="pfDescCount"></span>
        </label>
        <textarea id="pfDescription" name="description" maxlength="1000" rows="4"
                  placeholder="{{ __('Коротко: что было, что сделали, сколько заняло. Например: протекал стояк, заменил трубы на полипропилен, работа заняла 1 день.') }}">{{ old('description', $work->description) }}</textarea>
    </div>

    @if($existing->isNotEmpty())
        <div class="field">
            <label>{{ __('Загруженные файлы') }}</label>
            <div class="pf-tiles">
                @foreach($existing as $media)
                    <div class="pf-tile">
                        <div class="pf-thumb">
                            @if($media->previewUrl())
                                <img src="{{ $media->previewUrl() }}" alt="" loading="lazy">
                            @else
                                ▶
                            @endif
                            @if($media->isVideo())
                                <span class="play">▶ {{ __('Видео') }}</span>
                            @endif
                        </div>
                        <button type="submit" class="x" form="pfDel{{ $media->id }}" title="{{ __('Удалить файл') }}"
                                aria-label="{{ __('Удалить файл') }}">×</button>
                        <select name="existing_stage[{{ $media->id }}]" aria-label="{{ __('Отметка') }}">
                            <option value="">{{ __('Без отметки') }}</option>
                            @foreach(\App\Models\PortfolioMedia::STAGES as $value => $label)
                                <option value="{{ $value }}" @selected($media->stage === $value)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="field">
        <label>{{ $existing->isNotEmpty() ? __('Добавить фото или видео') : __('Фото и видео работы') }}</label>
        <label class="pf-drop" id="pfDrop" for="pfFiles">
            <span class="ico">📷</span>
            <b>{{ __('Выбрать фото или видео') }}</b>
            <span>{{ __('Фото JPG, PNG, WEBP до :photo МБ, не больше :photos. Видео MP4, WEBM, MOV до :video МБ, не больше :videos.', [
                'photo' => $limits['photoMb'], 'photos' => $limits['photos'], 'video' => $limits['videoMb'], 'videos' => $limits['videos'],
            ]) }}</span>
        </label>
        <input id="pfFiles" type="file" name="media[]" multiple accept="{{ $acceptTypes }}" style="position:absolute; left:-9999px;">
        <div class="hint">{{ __('Совет: снимите одно фото «до» и одно «после» с той же точки — так результат видно сразу.') }}</div>
        <div class="pf-tiles" id="pfTiles"></div>
    </div>

    <div class="pf-progress" id="pfProgress" aria-live="polite">
        <div class="pf-bar"><i id="pfBar"></i></div>
        <small id="pfProgressText"></small>
    </div>

    <div class="form-actions">
        <a href="{{ route('applicant.portfolio.index') }}" class="btn btn-secondary">{{ __('Отмена') }}</a>
        <button type="submit" class="btn btn-primary" id="pfSubmit">
            {{ $work->exists ? __('Сохранить') : __('Добавить в портфолио') }}
        </button>
    </div>
</form>

{{-- удаление загруженных файлов: вложенные формы HTML не допускает, поэтому они снаружи --}}
@foreach($existing as $media)
    <form id="pfDel{{ $media->id }}" method="POST" hidden
          action="{{ route('applicant.portfolio.media.destroy', [$work, $media]) }}"
          data-confirm="{{ __('Удалить этот файл?') }}"
          onsubmit="return confirm(this.dataset.confirm)">
        @csrf
        @method('DELETE')
    </form>
@endforeach

<script>
    (function () {
        const form = document.getElementById('pfForm');
        const input = document.getElementById('pfFiles');
        const drop = document.getElementById('pfDrop');
        const tiles = document.getElementById('pfTiles');
        const errorsBox = document.getElementById('pfErrors');
        const errorList = document.getElementById('pfErrorList');
        const progress = document.getElementById('pfProgress');
        const bar = document.getElementById('pfBar');
        const progressText = document.getElementById('pfProgressText');
        const submit = document.getElementById('pfSubmit');
        const description = document.getElementById('pfDescription');
        const descCount = document.getElementById('pfDescCount');

        const LIMITS = {{ \Illuminate\Support\Js::from($limits) }};
        const STAGES = {{ \Illuminate\Support\Js::from(collect(\App\Models\PortfolioMedia::STAGES)->map(fn ($label) => __($label))) }};
        const T = {
            none: {{ \Illuminate\Support\Js::from(__('Без отметки')) }},
            video: {{ \Illuminate\Support\Js::from(__('Видео')) }},
            remove: {{ \Illuminate\Support\Js::from(__('Убрать')) }},
            badType: {{ \Illuminate\Support\Js::from(__('«:name» — не фото и не видео. Подходят JPG, PNG, WEBP, MP4, WEBM, MOV.')) }},
            bigPhoto: {{ \Illuminate\Support\Js::from(__('Фото «:name» больше 10 МБ.')) }},
            bigVideo: {{ \Illuminate\Support\Js::from(__('Видео «:name» больше 100 МБ.')) }},
            manyPhotos: {{ \Illuminate\Support\Js::from(__('В одной работе не больше :count фото.', ['count' => $limits['photos']])) }},
            manyVideos: {{ \Illuminate\Support\Js::from(__('В одной работе не больше :count видео.', ['count' => $limits['videos']])) }},
            tooMuch: {{ \Illuminate\Support\Js::from(__('За один раз можно отправить до 240 МБ. Сохраните работу, а остальные файлы добавьте потом через «Изменить».')) }},
            uploading: {{ \Illuminate\Support\Js::from(__('Загружаем… :percent%')) }},
            processing: {{ \Illuminate\Support\Js::from(__('Файлы загружены, обрабатываем фото…')) }},
            serverTooBig: {{ \Illuminate\Support\Js::from(__('Сервер не принял файлы: их общий размер слишком большой. Отправьте меньше файлов за раз.')) }},
            failed: {{ \Illuminate\Support\Js::from(__('Не удалось сохранить. Проверьте связь и попробуйте ещё раз.')) }},
            needFile: {{ \Illuminate\Support\Js::from(__('Добавьте хотя бы одно фото или видео работы.')) }},
        };
        const MAX_TOTAL = 240 * 1024 * 1024;
        const isNew = {{ $work->exists ? 'false' : 'true' }};

        // выбранные файлы живут здесь: можно добавлять по нескольку раз и убирать
        const picked = [];

        // форму отправляет скрипт — у поля файлов имя больше не нужно
        input.removeAttribute('name');

        function fmt(text, values) {
            return Object.keys(values).reduce((s, k) => s.split(':' + k).join(values[k]), text);
        }

        function showErrors(list) {
            errorList.innerHTML = '';
            list.forEach(function (message) {
                const li = document.createElement('li');
                li.textContent = message;
                errorList.appendChild(li);
            });
            errorsBox.hidden = list.length === 0;
            if (list.length) { errorsBox.scrollIntoView({behavior: 'smooth', block: 'center'}); }
        }

        function kindOf(file) {
            if (LIMITS.photoMimes.includes(file.type)) { return 'photo'; }
            if (LIMITS.videoMimes.includes(file.type)) { return 'video'; }
            // iPhone иногда не сообщает тип видео — смотрим на расширение
            if (/\.(mov|mp4|webm)$/i.test(file.name)) { return 'video'; }

            return null;
        }

        function counts() {
            return {
                photo: parseInt(form.dataset.photos, 10) + picked.filter(p => p.kind === 'photo').length,
                video: parseInt(form.dataset.videos, 10) + picked.filter(p => p.kind === 'video').length,
            };
        }

        /** Кадр из видео для обложки: через секунду от начала или с трети ролика. */
        function capturePoster(item) {
            return new Promise(function (resolve) {
                const video = document.createElement('video');
                let done = false;
                const finish = function (blob) {
                    if (done) { return; }
                    done = true;
                    video.removeAttribute('src');
                    video.load();
                    resolve(blob);
                };

                video.muted = true;
                video.playsInline = true;
                video.preload = 'auto';
                video.src = item.url;

                video.addEventListener('loadeddata', function () {
                    const at = isFinite(video.duration) && video.duration > 0 ? Math.min(1, video.duration / 3) : 0;
                    video.currentTime = at;
                });
                video.addEventListener('seeked', function () {
                    try {
                        const scale = Math.min(1, 960 / Math.max(video.videoWidth, video.videoHeight));
                        const canvas = document.createElement('canvas');
                        canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
                        canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
                        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                        canvas.toBlob(finish, 'image/jpeg', 0.8);
                    } catch (e) {
                        finish(null);
                    }
                });
                video.addEventListener('error', function () { finish(null); });
                // формат, который браузер не показывает (часто MOV), — без обложки
                setTimeout(function () { finish(null); }, 8000);
            });
        }

        function tile(item) {
            const box = document.createElement('div');
            box.className = 'pf-tile';

            const thumb = document.createElement('div');
            thumb.className = 'pf-thumb';
            box.appendChild(thumb);

            function paintThumb() {
                thumb.innerHTML = '';
                const src = item.kind === 'photo' ? item.url : (item.posterUrl || null);
                if (src) {
                    const img = document.createElement('img');
                    img.src = src;
                    img.alt = '';
                    thumb.appendChild(img);
                } else {
                    thumb.textContent = item.kind === 'video' ? '▶' : '📷';
                }
                if (item.kind === 'video') {
                    const tag = document.createElement('span');
                    tag.className = 'play';
                    tag.textContent = '▶ ' + T.video;
                    thumb.appendChild(tag);
                }
            }
            paintThumb();
            item.repaint = paintThumb;

            const name = document.createElement('div');
            name.className = 'name';
            name.textContent = item.file.name;
            box.appendChild(name);

            const x = document.createElement('button');
            x.type = 'button';
            x.className = 'x';
            x.textContent = '×';
            x.title = T.remove;
            x.setAttribute('aria-label', T.remove);
            x.addEventListener('click', function () {
                picked.splice(picked.indexOf(item), 1);
                URL.revokeObjectURL(item.url);
                box.remove();
            });
            box.appendChild(x);

            const select = document.createElement('select');
            select.setAttribute('aria-label', T.none);
            [['', T.none]].concat(Object.entries(STAGES)).forEach(function (pair) {
                const option = document.createElement('option');
                option.value = pair[0];
                option.textContent = pair[1];
                select.appendChild(option);
            });
            select.addEventListener('change', function () { item.stage = select.value; });
            box.appendChild(select);

            return box;
        }

        function add(files) {
            const problems = [];

            Array.from(files).forEach(function (file) {
                const kind = kindOf(file);
                const now = counts();

                if (!kind) { problems.push(fmt(T.badType, {name: file.name})); return; }
                if (kind === 'photo' && file.size > LIMITS.photoMb * 1024 * 1024) { problems.push(fmt(T.bigPhoto, {name: file.name})); return; }
                if (kind === 'video' && file.size > LIMITS.videoMb * 1024 * 1024) { problems.push(fmt(T.bigVideo, {name: file.name})); return; }
                if (kind === 'photo' && now.photo >= LIMITS.photos) { problems.push(T.manyPhotos); return; }
                if (kind === 'video' && now.video >= LIMITS.videos) { problems.push(T.manyVideos); return; }

                const item = {file: file, kind: kind, stage: '', poster: null, posterUrl: null, url: URL.createObjectURL(file)};
                picked.push(item);
                tiles.appendChild(tile(item));

                if (kind === 'video') {
                    item.posterReady = capturePoster(item).then(function (blob) {
                        if (blob) {
                            item.poster = blob;
                            item.posterUrl = URL.createObjectURL(blob);
                            item.repaint();
                        }
                    });
                }
            });

            showErrors(Array.from(new Set(problems)));
        }

        input.addEventListener('change', function () {
            add(input.files);
            input.value = '';
        });

        ['dragenter', 'dragover'].forEach(function (evt) {
            drop.addEventListener(evt, function (e) { e.preventDefault(); drop.classList.add('drag'); });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            drop.addEventListener(evt, function (e) { e.preventDefault(); drop.classList.remove('drag'); });
        });
        drop.addEventListener('drop', function (e) { add(e.dataTransfer.files); });

        function paintCount() {
            descCount.textContent = description.value.length + ' / 1000';
        }
        description.addEventListener('input', paintCount);
        paintCount();

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (isNew && picked.length === 0) { showErrors([T.needFile]); return; }

            const total = picked.reduce((sum, p) => sum + p.file.size, 0);
            if (total > MAX_TOTAL) { showErrors([T.tooMuch]); return; }

            submit.disabled = true;

            // обложки снимаются в фоне — дожидаемся, но не дольше их собственного таймаута
            Promise.all(picked.map(p => p.posterReady || Promise.resolve())).then(function () {
                const data = new FormData(form);
                picked.forEach(function (item, i) {
                    data.append('media[' + i + ']', item.file, item.file.name);
                    data.append('stages[' + i + ']', item.stage || '');
                    if (item.poster) { data.append('posters[' + i + ']', item.poster, 'poster.jpg'); }
                });

                const xhr = new XMLHttpRequest();
                xhr.open('POST', form.action);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                progress.classList.add('on');
                bar.style.width = '0';
                progressText.textContent = fmt(T.uploading, {percent: 0});

                xhr.upload.addEventListener('progress', function (e) {
                    if (!e.lengthComputable) { return; }
                    const percent = Math.round(e.loaded / e.total * 100);
                    bar.style.width = percent + '%';
                    progressText.textContent = percent >= 100 ? T.processing : fmt(T.uploading, {percent: percent});
                });

                xhr.addEventListener('load', function () {
                    let body = null;
                    try { body = JSON.parse(xhr.responseText); } catch (e) {}

                    if (xhr.status >= 200 && xhr.status < 300 && body && body.redirect) {
                        window.location.href = body.redirect;
                        return;
                    }

                    submit.disabled = false;
                    progress.classList.remove('on');

                    if (xhr.status === 422 && body && body.errors) {
                        showErrors([].concat.apply([], Object.values(body.errors)));
                    } else if (xhr.status === 413) {
                        showErrors([T.serverTooBig]);
                    } else {
                        showErrors([T.failed]);
                    }
                });

                xhr.addEventListener('error', function () {
                    submit.disabled = false;
                    progress.classList.remove('on');
                    showErrors([T.failed]);
                });

                xhr.send(data);
            });
        });
    })();
</script>
