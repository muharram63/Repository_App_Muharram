<!DOCTYPE html>
<html lang="ru" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Личный кабинет') }}</title>
    @include('employer.partials.css')
</head>
<body>

<div class="app">

    <!-- ================= SIDEBAR ================= -->
    @include('employer.partials.sidebar')

    <!-- ================= MAIN ================= -->
    <main class="main">

        <div class="topbar">
            <div>
                <h1 id="pageTitle">{{ __('Профиль') }}</h1>
                <p id="pageSubtitle">{{ __('Заполните анкету — это повышает шанс, что работодатель откликнется первым') }}</p>
            </div>
            @include('partials.notification-bell')
            <div class="user-chip">
                <div class="avatar" style="padding: 14px 16px;">

                    <div class="avatar text-white d-flex align-items-center justify-content-center"
                         style="font-size:13px; font-weight:700; background:#1656D6;">
                        {{ mb_substr($employer->user->name, 0, 1) }}
                    </div>

                </div>
                <div>
                    <div class="name">{{ $employer->user->name }}</div>
                    <div class="role">{{ $employer->user->email }}</div>
                </div>
            </div>
        </div>

        <!-- ---------- SECTION: PROFILE ---------- -->
        <section class="section active" id="section-profile">

            <div class="stat-row">
                <div class="stat-card">
                    <div class="label">{{ __('Просмотры вакансий') }}</div>
                    <div class="value"><span data-live="profileViews">{{ $profileViews }}</span></div>
                    <div class="trend"><span data-live="vacanciesCount">{{ $vacanciesCount }}</span> {{ __('вакансий') }} · {{ __('За всё время') }}</div>
                </div>
                {{-- два потока разведены: что пришло компании и что отправила она.
                     Карточка — ссылка на соответствующий список откликов. --}}
                <a class="stat-card" href="{{ route('public.responses.index') }}#received">
                    <div class="label">{{ __('Получено откликов') }}</div>
                    <div class="value"><span data-live="responsesCount">{{ $responsesCount }}</span></div>
                    <div class="trend">
                        <span data-live="responsesPending">{{ $responsesPending }}</span> {{ __('новых') }} ·
                        {{ __('от кандидатов на ваши вакансии') }}
                    </div>
                    <span class="stat-go">{{ __('Кто откликнулся') }} →</span>
                </a>
                <a class="stat-card" href="{{ route('public.responses.index') }}#sent">
                    <div class="label">{{ __('Отправлено приглашений') }}</div>
                    <div class="value"><span data-live="invitesCount">{{ $invitesCount }}</span></div>
                    <div class="trend">
                        <span data-live="invitesPending">{{ $invitesPending }}</span> {{ __('ожидают ответа') }} ·
                        {{ __('ваши приглашения кандидатам') }}
                    </div>
                    <span class="stat-go">{{ __('Кого вы пригласили') }} →</span>
                </a>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>{{ __('Личные данные') }}</h2>
                    <span class="hint">{{ __('Эти данные видят работодатели') }}</span>
                </div>

                <form action="{{ route('employer.profile.update') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <!-- Photo -->
                    <div class="field" style="display:flex; align-items:center; gap:16px; margin-bottom:20px;">
                        <div class="avatar"
                             style="width:64px; height:64px; border-radius:50%; overflow:hidden; background:#EEF1F6; display:flex; align-items:center; justify-content:center; font-weight:700; color:#1656D6;">
                            <img id="photoPreview" src="{{ $employer->user->avatar ? asset($employer->user->avatar) : '' }}"
                                 alt="Фото профиля"
                                 style="width:100%; height:100%; object-fit:cover; @unless($employer->user?->hasAvatar()) display:none; @endunless">
                            <span id="photoPlaceholder" @if($employer->user?->hasAvatar()) style="display:none;" @endif>
                                {{ mb_substr($employer->user->name, 0, 1) }}
                            </span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-secondary" id="uploadPhotoBtn">{{ __('Загрузить фото') }}</button>
                            <input type="file" id="photoInput" name="avatar" accept="image/jpeg,image/png,image/webp"
                                   style="display:none;">
                            <div class="hint" style="margin-top:6px;">{{ __('JPG, PNG или WEBP, до 5 МБ. Компания с логотипом заметнее в поиске соискателей') }}
                            </div>
                            <div class="hint" id="photoName" style="margin-top:4px; color:#4F46E5; display:none;"></div>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="field">
                            <label for="fname">{{ __('ФИО') }}</label>
                            <input type="text" name="name" value="{{$employer->user->name}}">
                        </div>
                        <div class="field">
                            <label for="position">{{ __('Компания') }}</label>
                            <input id="position" type="text" name="company_name" value="{{$employer->company_name}}">
                        </div>
                        <div class="field">
                            <label for="position">{{ __('Должность') }}</label>
                            <input id="position" name="job" type="text" placeholder="{{ __('Например, Frontend Developer') }}"
                                   value="{{$employer->job}}">
                        </div>
                        <div class="field">
                            <label for="position">{{ __('Категория') }}</label>
                            <select name="category_id">
                                @foreach($categories as $category)
                                    <option value="{{$category->id}}">{{ __($category->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="position">{{ __('Индустрия') }}</label>
                            <select name="industry_id">
                                @foreach($industries as $industry)
                                    <option value="{{$industry->id}}">{{ __($industry->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="position">{{ __('URL-Компании') }}</label>
                            <input id="position" name="website_url" type="text" value="{{$employer->website_url}}">
                        </div>
                        <div class="field">
                            <label for="position">{{ __('Рабочий E-mail') }}</label>
                            <input id="position" name="email" type="text" placeholder="company@gmail.com"
                                   value="{{$employer->email_company}}">
                        </div>
                        <div class="field">
                            <label for="position">{{ __('Рабочий телефон') }}</label>
                            <input id="position" name="phone" type="text" placeholder="{{ __('92-ххх-хх-хх') }}"
                                   value="{{$employer->phone}}">
                        </div>

                        <div class="field">
                            <label for="city">{{ __('Город') }}</label>
                            <select name="city_id">
                                @foreach($cities as $city)
                                    <option value="{{$city->id}}">{{ __($city->country) }} , {{ __($city->region) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field full">
                            <label for="about">{{ __('О себе') }}</label>
                            <textarea id="about" maxlength="600" name="description" placeholder="{{ __('Коротко: о компании') }}"
                                      name="description">{{ $employer->description ?? '' }}</textarea>
                            <div class="hint" style="display:flex; justify-content:space-between;">
                                <span>{{ __('Достаточно 3–5 предложений') }}</span>
                                <span id="aboutCounter">0 / 600</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary">{{ __('Отмена') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Сохранить изменения') }}</button>
                    </div>
                </form>
            </div>
        </section>

        <!-- ---------- SECTION: EMPLOYERS ---------- -->
        <section class="section" id="section-employers">
            <div class="card">
                <div class="card-header">
                    <h2>{{ __('Соискатели, откликнувшиеся на вакансии') }}</h2>
                    <span class="hint">
                        {{ $candidateResponses->count() }} ·
                        <a href="{{ route('employer.responses.index') }}">{{ __('Все отклики') }}</a>
                    </span>
                </div>
                <table>
                    <thead>
                    <tr>
                        <th>{{ __('Соискатель') }}</th>
                        <th>{{ __('Вакансия') }}</th>
                        <th>{{ __('Статус') }}</th>
                        <th>{{ __('Дата') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @php
                        $statusLabels = [
                            'new' => __('Новый'),
                            'viewed' => __('Просмотрен'),
                            'accepted' => __('Принят'),
                            'rejected' => __('Отклонён'),
                        ];
                    @endphp
                    @forelse($candidateResponses as $response)
                        <tr>
                            <td>
                                <div class="company-cell">
                                    @if($response->applicant?->user?->hasAvatar())
                                        <img class="company-logo" src="{{ asset($response->applicant->user->avatar) }}"
                                             alt="" style="object-fit:cover;">
                                    @else
                                        <div class="company-logo">{{ $response->applicant?->user?->initials(2) ?? '?' }}</div>
                                    @endif
                                    {{ $response->applicant?->user?->name ?? __('Соискатель') }}
                                </div>
                            </td>
                            <td>{{ $response->vacancy?->title ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $response->status === 'accepted' ? 'badge-active' : 'badge-pending' }}">
                                    {{ __($statusLabels[$response->status] ?? $response->status) }}
                                </span>
                            </td>
                            <td>{{ $response->created_at->format('d.m.Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center; color:var(--gray-500); padding:24px 12px;">
                                {{ __('Пока никто не откликнулся') }}
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ---------- SECTION: RESPONSES ---------- -->
        <!-- ---------- SECTION: SETTINGS ---------- -->
        <section class="section" id="section-settings">

            <div class="card">
                <div class="card-header">
                    <h2>{{ __('Контактные данные') }}</h2>
                    <span class="hint">{{ __('Используются для уведомлений о статусе откликов') }}</span>
                </div>
                <div class="form-grid">
                    <div class="field">
                        <label for="email">Email</label>
                        <input id="email" type="email" value="{{ $employer->user->email }}" readonly
                               style="background:var(--gray-100); color:var(--gray-500);">
                    </div>
                    <div class="field">
                        <label for="phone">{{ __('Телефон') }}</label>
                        <input id="phone" type="text" value="{{ $employer->phone }}" placeholder="+992 ..." readonly
                               style="background:var(--gray-100); color:var(--gray-500);">
                        <div class="hint">{{ __('Показывается работодателю только после вашего отклика') }}</div>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn btn-primary">{{ __('Сохранить') }}</button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>{{ __('Пароль и безопасность') }}</h2>
                </div>
                <div class="form-grid">
                    <div class="field">
                        <label for="pass_current">{{ __('Текущий пароль') }}</label>
                        <input id="pass_current" type="password" placeholder="••••••••">
                    </div>
                    <div class="field">
                        <label for="pass">{{ __('Новый пароль') }}</label>
                        <input id="pass" type="password" placeholder="{{ __('Минимум 8 символов') }}">
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn btn-primary">{{ __('Изменить пароль') }}</button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>{{ __('Уведомления') }}</h2>
                </div>
                <div class="field"
                     style="display:flex; align-items:center; justify-content:space-between; max-width:420px;">
                    <label for="notify_email" style="margin:0;">{{ __('Email о новых откликах и приглашениях') }}</label>
                    <input id="notify_email" type="checkbox" checked style="width:auto;">
                </div>
                <div class="field"
                     style="display:flex; align-items:center; justify-content:space-between; max-width:420px;">
                    <label for="notify_jobs" style="margin:0;">{{ __('Подборка подходящих вакансий раз в неделю') }}</label>
                    <input id="notify_jobs" type="checkbox" style="width:auto;">
                </div>
            </div>

        </section>

    </main>
</div>

@include('employer.partials.js')

<script>
    // Счётчик символов в поле "О себе"
    (function () {
        const about = document.getElementById('about');
        const counter = document.getElementById('aboutCounter');
        if (about && counter) {
            const update = () => counter.textContent = about.value.length + ' / 600';
            about.addEventListener('input', update);
            update();
        }
    })();

    // Клик по кнопке "Загрузить фото" открывает выбор файла + превью
    (function () {
        const btn = document.getElementById('uploadPhotoBtn');
        const input = document.getElementById('photoInput');
        const preview = document.getElementById('photoPreview');
        const placeholder = document.getElementById('photoPlaceholder');
        const nameHint = document.getElementById('photoName');
        if (!btn || !input) {
            return;
        }

        btn.addEventListener('click', () => input.click());

        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            if (!file) {
                return;
            }

            if (nameHint) {
                nameHint.textContent = file.name + ' — не забудьте нажать «Сохранить изменения»';
                nameHint.style.display = '';
            }

            if (preview) {
                preview.src = URL.createObjectURL(file);
                preview.style.display = '';
                if (placeholder) {
                    placeholder.style.display = 'none';
                }
            }
        });
    })();
</script>

</body>
</html>
