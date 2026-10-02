<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Документы для собеседования') }}</title>
    @include('applicant.partials.css')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@600;700;800&family=Inter:wght@400;500;600&display=swap"
          rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2 { font-family: 'Manrope', 'Inter', sans-serif; }

        .dc-wrap { padding: 0 3vh 4vh 2vh; display: flex; flex-direction: column; gap: 16px; max-width: 900px; }
        .dc-panel {
            background: var(--white); border: 1px solid var(--gray-200);
            border-radius: var(--radius); padding: 18px;
            display: flex; flex-direction: column; gap: 14px; min-width: 0;
        }
        .dc-panel h1 { margin: 0; font-size: 20px; color: var(--ink); }
        .dc-panel h2 { margin: 0; font-size: 15px; color: var(--ink); }
        .dc-hint { margin: 0; font-size: 13px; color: var(--gray-500); }

        .dc-steps { display: flex; flex-wrap: wrap; gap: 6px; font-size: 12px; }
        .dc-step { padding: 4px 10px; border-radius: 999px; background: var(--gray-100); color: var(--gray-500); }
        .dc-step.now { background: var(--indigo-600); color: #fff; }
        .dc-step.done { background: var(--wash-good); color: var(--ink-good); }

        .dc-resume {
            padding: 12px 14px; border-radius: 12px; background: var(--wash-good); color: var(--ink-good);
            font-size: 13px;
        }
        .dc-resume b { display: block; font-size: 14px; }

        .dc-doc {
            display: grid; grid-template-columns: minmax(0,1fr) auto; gap: 12px; align-items: center;
            border: 1px solid var(--gray-200); border-radius: 12px; padding: 11px 13px;
        }
        .dc-doc[data-bad="1"] { border-color: var(--danger); }
        .dc-name { font-weight: 600; color: var(--ink); overflow-wrap: anywhere; }
        .dc-meta { font-size: 12px; color: var(--gray-500); margin-top: 2px; }
        .dc-meta a { color: var(--indigo-600); }

        .dc-field { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
        .dc-field label { font-size: 12px; font-weight: 600; color: var(--gray-500); }
        .dc-field input, .dc-field select {
            font: inherit; font-size: 14px; padding: 8px 10px; border-radius: 10px;
            border: 1px solid var(--gray-300); background: var(--white); color: var(--ink); min-width: 0;
        }
        .dc-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }

        .dc-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .dc-btn {
            display: inline-flex; align-items: center; padding: 9px 16px; border-radius: 999px;
            cursor: pointer; font: inherit; font-size: 13px; font-weight: 600; text-decoration: none;
            background: var(--indigo-600); color: #fff; border: 1px solid transparent;
        }
        .dc-btn:hover { filter: brightness(1.06); }
        .dc-btn-ghost { background: transparent; color: var(--gray-500); border-color: var(--gray-200); }
        .dc-btn-danger { background: transparent; color: var(--danger); border-color: var(--gray-200); }

        .dc-note { font-size: 13px; padding: 10px 12px; border-radius: 10px; }
        .dc-good { background: var(--wash-good); color: var(--ink-good); }
        .dc-bad  { background: var(--wash-danger); color: var(--danger); }
        .dc-warn { background: var(--wash-warn); color: var(--ink-warn); }

        .dc-empty {
            border: 1px dashed var(--gray-300); border-radius: 12px; padding: 18px;
            text-align: center; color: var(--gray-500); font-size: 13px;
        }
    </style>
</head>
<body>
<div class="app">
    @include('applicant.partials.sidebar')

    <main class="main">
        <div class="dc-wrap">
            @if(session('status'))
                <div class="dc-note dc-good">{{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="dc-note dc-bad">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="dc-note dc-bad">{{ $errors->first() }}</div>
            @endif

            <div class="dc-panel">
                <h1>{{ $vacancy->title }}</h1>

                <div class="dc-steps">
                    @foreach(\App\Models\AiInterview::STAGES as $key => $label)
                        @php
                            $order = array_keys(\App\Models\AiInterview::STAGES);
                            $now = array_search($interview->stage, $order, true);
                            $mine = array_search($key, $order, true);
                        @endphp
                        <span class="dc-step {{ $mine === $now ? 'now' : ($mine < $now ? 'done' : '') }}">
                            {{ __($label) }}
                        </span>
                    @endforeach
                </div>

                <p class="dc-hint">
                    {{ __('Дипломы и сертификаты помогают ИИ сверить ваш опыт с требованиями вакансии. Их может не быть вовсе — это не помешает пройти собеседование: умение проверяется вопросами и заданием, а не документом.') }}
                </p>

                @if($resume)
                    <div class="dc-resume">
                        <b>{{ __('Резюме уже на площадке') }}</b>
                        {{ $resume->profession }}{{ $resume->desired_position ? ' · '.$resume->desired_position : '' }}
                        — {{ __('загружать его заново не нужно.') }}
                    </div>
                @endif
            </div>

            <div class="dc-panel">
                <h2>{{ __('Загруженные документы') }}</h2>

                @if($documents->isEmpty())
                    <div class="dc-empty">{{ __('Пока ничего не загружено.') }}</div>
                @else
                    @foreach($documents as $document)
                        <div class="dc-doc" data-bad="{{ $document->status === 'unreadable' ? '1' : '0' }}">
                            <div>
                                <div class="dc-name">{{ $document->original_name }}</div>
                                <div class="dc-meta">
                                    {{ $document->kindLabel() }}
                                    · {{ $document->statusLabel() }}
                                    @if($document->size)
                                        · {{ $document->size > 1048576
                                            ? round($document->size / 1048576, 1).' '.__('МБ')
                                            : round($document->size / 1024).' '.__('КБ') }}
                                    @endif
                                    · <a href="{{ route('applicant.ai.documents.file', [$interview, $document]) }}">{{ __('скачать') }}</a>
                                    @if($document->failure_reason)
                                        <div style="color:var(--danger); margin-top:3px">{{ $document->failure_reason }}</div>
                                    @endif
                                </div>
                            </div>
                            <form method="POST"
                                  action="{{ route('applicant.ai.documents.destroy', [$interview, $document]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="dc-btn dc-btn-danger">{{ __('Удалить') }}</button>
                            </form>
                        </div>
                    @endforeach
                @endif

                <p class="dc-hint">
                    {{ __('Подлинность документов система не проверяет — она сверяет данные с вашим резюме.') }}
                </p>
            </div>

            @php
                /*
                 * Пока разбор идёт, добавлять нечего — и нельзя.
                 *
                 * Нельзя: страница в это время перезагружается каждые пять
                 * секунд, и диалог выбора файла закрывается под руками —
                 * выбрать ничего не удаётся.
                 *
                 * Нечего: цепочка разбора собирается из тех документов, что
                 * были на момент нажатия «Продолжить». Добавленный позже в неё
                 * уже не попадёт и останется неразобранным — форма обещала бы
                 * то, чего не делает.
                 *
                 * Зависший разбор — другое дело: его можно запустить заново, и
                 * тогда новый документ попадёт в цепочку. Там форма нужна.
                 */
                $analysisRunning = $interview->analysis_status === 'pending'
                    && ! $interview->analysisStalled();
            @endphp

            @if($analysisRunning)
                <div class="dc-note">
                    {{ __('Документы отправлены на разбор. Добавить ещё можно будет, если разбор не удастся.') }}
                </div>
            @elseif($documents->count() < $maxDocuments)
                <form class="dc-panel" method="POST"
                      action="{{ route('applicant.ai.documents.upload', $interview) }}"
                      enctype="multipart/form-data">
                    @csrf
                    <h2>{{ __('Добавить документ') }}</h2>

                    <div class="dc-grid">
                        <div class="dc-field">
                            <label for="kind">{{ __('Что это') }}</label>
                            <select id="kind" name="kind">
                                @foreach($kinds as $value => $label)
                                    <option value="{{ $value }}">{{ __($label) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="dc-field">
                            <label for="file">{{ __('Файл') }}</label>
                            <input id="file" type="file" name="file" required
                                   accept=".pdf,.jpg,.jpeg,.png,.docx,.txt">
                        </div>
                    </div>

                    <p class="dc-hint">
                        {{ __('PDF, JPG, PNG, DOCX или TXT, до') }} {{ round($maxKb / 1024) }} {{ __('МБ.') }}
                        {{ __('Скан читается моделью, поэтому фотография диплома тоже подойдёт.') }}
                    </p>

                    <div><button type="submit" class="dc-btn dc-btn-ghost">{{ __('Загрузить') }}</button></div>
                </form>
            @else
                <div class="dc-note dc-warn">
                    {{ __('Достигнут предел документов. Удалите лишние, если хотите заменить.') }}
                </div>
            @endif

            @if($interview->analysisStalled())
                {{-- Разбор помечен идущим, но не двигается. Молчать нельзя:
                     страница иначе перезагружалась бы вечно и повторяла
                     «занимает до минуты». --}}
                <div class="dc-panel">
                    <h2>{{ __('Разбор затянулся') }}</h2>
                    <p class="dc-hint">
                        {{ __('Документы приняты, но разбор не продвинулся. Ваши данные сохранены — можно запустить разбор заново или вернуться позже.') }}
                    </p>

                    {{-- Кнопка называется тем, что делает. «Попробовать снова»
                         читалось как «повторить то, что не вышло», и человек не
                         понимал, что именно этим и идёт дальше: разбор — это
                         шаг к разговору, других путей со страницы нет. --}}
                    <div class="dc-row">
                        <form method="POST" action="{{ route('applicant.ai.proceed', $interview) }}">
                            @csrf
                            <button type="submit" class="dc-btn">{{ __('Запустить разбор и продолжить') }}</button>
                        </form>
                        <a class="dc-btn dc-btn-ghost" href="{{ route('applicant.ai.index') }}">
                            {{ __('Продолжу потом') }}
                        </a>
                    </div>

                    @if(app()->environment('local') && \Illuminate\Support\Facades\Schema::hasTable('jobs'))
                        @php($queued = \Illuminate\Support\Facades\DB::table('jobs')->count())
                        @if($queued > 0)
                            {{-- Видно только на машине разработчика: кандидату такой
                                 совет ни к чему, а владельцу площадки он объясняет
                                 тупик за секунду вместо часа догадок. --}}
                            <div class="dc-note dc-warn">
                                В очереди ждёт задач: {{ $queued }}, и их никто не берёт.
                                Запустите обработчик во втором окне:
                                <code>php artisan queue:work</code>
                            </div>
                        @endif
                    @endif
                </div>
            @elseif($interview->analysis_status === 'pending')
                {{-- Разбор в очереди. Страница обновляется сама: три модельных
                     вызова занимают до минуты, и следить за ними вручную
                     кандидату незачем. --}}
                <div class="dc-panel" id="dcWaiting">
                    <h2>{{ __('ИИ разбирает документы') }}</h2>
                    <p class="dc-hint">
                        {{ __('Он сверяет ваш опыт с требованиями вакансии и готовит вопросы. Это занимает до минуты — страница обновится сама. Можно закрыть её и вернуться позже.') }}
                    </p>
                    <div class="dc-row">
                        <a class="dc-btn dc-btn-ghost" href="{{ route('applicant.ai.index') }}">
                            {{ __('Продолжу потом') }}
                        </a>
                    </div>
                </div>
            @elseif($interview->analysis_status === 'failed')
                <div class="dc-panel">
                    <h2>{{ __('Разбор не удался') }}</h2>
                    <p class="dc-hint">
                        {{ __('Ваши документы не удалось разобрать автоматически. Это не отказ: работодателю отправлено уведомление, и вашу кандидатуру рассмотрит человек. Ответ придёт в обещанный срок.') }}
                    </p>
                </div>
            @elseif($interview->analysis_status === 'ready')
                <div class="dc-panel">
                    <h2>{{ __('Документы разобраны') }}</h2>
                    <p class="dc-hint">
                        {{ __('ИИ сверил ваш опыт с требованиями вакансии. Можно переходить к собеседованию.') }}
                    </p>
                    <div class="dc-row">
                        <a class="dc-btn" href="{{ route('applicant.ai.interview', $interview) }}">
                            {{ __('К собеседованию') }}
                        </a>
                    </div>
                </div>
            @else
                <form class="dc-panel" method="POST" action="{{ route('applicant.ai.proceed', $interview) }}">
                    @csrf
                    <h2>{{ __('Готовы продолжить?') }}</h2>
                    <p class="dc-hint">
                        {{ __('Дальше ИИ разберёт документы и начнёт собеседование. Вернуться и добавить документ можно будет и позже.') }}
                    </p>
                    <div class="dc-row">
                        <button type="submit" class="dc-btn">{{ __('Перейти к собеседованию') }}</button>
                        <a class="dc-btn dc-btn-ghost" href="{{ route('applicant.ai.index') }}">
                            {{ __('Продолжу потом') }}
                        </a>
                    </div>
                </form>
            @endif
        </div>
    </main>
</div>

@if($interview->analysis_status === 'pending' && ! $interview->analysisStalled())
    <script>
        // Пока разбор идёт, страница переспрашивает сервер. Раз в пять секунд:
        // разбор занимает около минуты, и чаще дёргать незачем. Зависший разбор
        // не переспрашиваем — там ждать нечего, и об этом сказано на экране.
        setTimeout(function () { window.location.reload(); }, 5000);
    </script>
@endif

@include('applicant.partials.js')
@include('applicant.partials.script')
</body>
</html>
