<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Личный кабинет') }}</title>
    @include('applicant.partials.css')
</head>
<body>

<div class="app">

    <!-- ================= SIDEBAR ================= -->
    @include('applicant.partials.sidebar')

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
                        {{ $user->initials(1) }}
                    </div>

                </div>
                <div>
                    <div class="name">{{ $applicant->user->name }}</div>
                    <div class="role">{{ $applicant->user->email }}</div>
                </div>
            </div>
        </div>

        <!-- ---------- SECTION: PROFILE ---------- -->
        <section class="section active" id="section-profile">

            <div class="stat-row">
                <div class="stat-card">
                    <div class="label">{{ __('Просмотры резюме') }}</div>
                    <div class="value"><span data-live="profileViews">{{ $profileViews }}</span></div>
                    <div class="trend"><span data-live="resumesCount">{{ $resumesCount }}</span> {{ __('резюме') }} · {{ __('За всё время') }}</div>
                </div>
                <div class="stat-card">
                    <div class="label">{{ __('Отклики') }}</div>
                    <div class="value"><span data-live="responsesCount">{{ $responsesCount }}</span></div>
                    <div class="trend"><span data-live="responsesPending">{{ $responsesPending }}</span> {{ __('ожидают ответа') }} · <span data-live="invitesCount">{{ $invitesCount }}</span> {{ __('приглашений') }}</div>
                </div>
                <div class="stat-card">
                    <div class="label">{{ __('Заполненность анкеты') }}</div>
                    <div class="value">{{ $completeness['percent'] }}%</div>
                    <div class="trend">
                        @if(empty($completeness['missing']))
                            {{ __('Анкета заполнена') }}
                        @else
                            {{ __('Осталось') }}:
                            {{ collect($completeness['missing'])->map(fn($f) => __($f))->implode(', ') }}
                        @endif
                    </div>
                    <div style="margin-top:8px; height:6px; border-radius:4px; background:#EEF1F6; overflow:hidden;">
                        <div style="height:100%; width:{{ $completeness['percent'] }}%; border-radius:4px; background:#1656D6;"></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>{{ __('Личные данные') }}</h2>
                    <span class="hint">{{ __('Эти данные видят работодатели') }}</span>
                </div>

                @if(session('status'))
                    <div class="field" style="margin-bottom:18px; padding:12px 16px; border-radius:10px; background:#DCFCE7; color:#15803D; font-weight:600;">
                        {{ session('status') }}
                    </div>
                @endif

                @if(isset($errors) && $errors->any())
                    <div class="field" style="margin-bottom:18px; padding:12px 16px; border-radius:10px; background:#FEF2F2; color:#B91C1C; font-weight:600;">
                        <ul style="margin:0; padding-left:18px;">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{route('applicant.profile.update')}}" method="post" enctype="multipart/form-data">
                      @csrf
                    <!-- Photo -->
                    <div class="field" style="display:flex; align-items:center; gap:16px; margin-bottom:20px;">
                        <div class="avatar"
                             style="width:64px; height:64px; border-radius:50%; overflow:hidden; background:#EEF1F6; display:flex; align-items:center; justify-content:center; font-weight:700; color:#1656D6;">
                            <img id="photoPreview" src="{{ $user->avatar ? asset($user->avatar) : '' }}"
                                 alt="Фото профиля"
                                 style="width:100%; height:100%; object-fit:cover; @unless($user->hasAvatar()) display:none; @endunless">
                            <span id="photoPlaceholder" @if($user->hasAvatar()) style="display:none;" @endif>
                                {{ $user->initials(1) }}
                            </span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-secondary" id="uploadPhotoBtn">{{ __('Загрузить фото') }}</button>
                            <input type="file" id="photoInput" name="avatar" accept="image/jpeg,image/png,image/webp"
                                   style="display:none;">
                            <div class="hint" style="margin-top:6px;">{{ __('JPG, PNG или WEBP, до 5 МБ. Профиль с фото заметнее в поиске работодателей') }}
                            </div>
                            <div class="hint" id="photoName" style="margin-top:4px; color:#4F46E5; display:none;"></div>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="field">
                            <label for="fname">{{ __('ФИО') }}</label>
                            <input id="fname" type="text" name="name" value="{{ old('name', $user->name) }}">
                        </div>
                        <div class="field">
                            <label for="birth">{{ __('Дата рождения') }}</label>
                            <input id="birth" type="date" name="birth"
                                   value="{{ old('birth', $applicant->birth ? $applicant->birth->format('Y-m-d') : '') }}">
                        </div>
                        <div class="field">
                            <label for="gender">{{ __('Пол') }}</label>
                            <select id="gender" name="gender">
                                <option value="">{{ __('Не указан') }}</option>
                                <option value="male" @selected(old('gender', $applicant->gender) === 'male')>{{ __('Мужской') }}</option>
                                <option value="female" @selected(old('gender', $applicant->gender) === 'female')>{{ __('Женский') }}</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="city">{{ __('Город') }}</label>
                            <input id="city" type="text" name="city" placeholder="{{ __('Например, Душанбе') }}"
                                   value="{{ old('city', $applicant->city) }}">
                        </div>
                        <div class="field">
                            <label for="address">{{ __('Адрес') }}</label>
                            <input id="address" type="text" name="address" placeholder="{{ __('Улица, дом') }}"
                                   value="{{ old('address', $applicant->address) }}">
                        </div>

                        <div class="field">
                            <label for="education">{{ __('Образование') }}</label>
                            <input id="education" type="text" name="education" placeholder="{{ __('Например, ТУТ, программная инженерия') }}"
                                   value="{{ old('education', $applicant->education) }}">
                        </div>

                        <div class="field">
                            <label for="email">E-mail</label>
                            <input id="email" type="text" value="{{ $user->email }}" readonly
                                   style="background:var(--gray-100); color:var(--gray-500);">
                            <div class="hint" style="margin-top:6px;">{{ __('E-mail меняется в настройках аккаунта') }}</div>
                        </div>
                        <div class="field">
                            <label for="phone">{{ __('Номер телефона') }}</label>
                            <input id="phone" type="text" name="phone" placeholder="{{ __('92-ххх-хх-хх') }}"
                                   value="{{ old('phone', $applicant->phone) }}">
                        </div>


                        <div class="field full">
                            <label for="about">{{ __('О себе') }}</label>
                            <textarea id="about" maxlength="600" name="about_me"
                                      placeholder="{{ __('Коротко: о себе') }}">{{ old('about_me', $applicant->about_me) }}</textarea>
                            <div class="hint" style="display:flex; justify-content:space-between;">
                                <span id="aboutCounter">0 / 600</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">{{ __('Сохранить изменения') }}</button>
                    </div>
                </form>
            </div>
        </section>

        <!-- ---------- SECTION: EMPLOYERS ---------- -->
        <section class="section" id="section-employers">
            <div class="card">
                <div class="card-header">
                    <h2>{{ __('Компании, откликнувшиеся на резюме') }}</h2>
                    <span class="hint">
                        {{ $companyResponses->count() }} ·
                        <a href="{{ route('applicant.responses.index') }}">{{ __('Все отклики') }}</a>
                    </span>
                </div>
                <table>
                    <thead>
                    <tr>
                        <th>{{ __('Компания') }}</th>
                        <th>{{ __('Вакансия') }}</th>
                        <th>{{ __('Статус') }}</th>
                        <th>{{ __('Дата') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @php
                        $statusLabels = [
                            'new' => 'Новый',
                            'viewed' => 'Просмотрен',
                            'accepted' => 'Принят',
                            'rejected' => 'Отклонён',
                        ];
                    @endphp
                    @forelse($companyResponses as $response)
                        <tr>
                            <td>
                                <div class="company-cell">
                                    @if($response->employer?->user?->hasAvatar())
                                        <img class="company-logo" src="{{ asset($response->employer->user->avatar) }}"
                                             alt="" style="object-fit:cover;">
                                    @else
                                        <div class="company-logo">{{ $response->employer?->initials(2) ?? '?' }}</div>
                                    @endif
                                    {{ $response->employer?->company_name ?? __('Компания') }}
                                </div>
                            </td>
                            <td>{{ $response->resume?->profession ?? '—' }}</td>
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
                                {{ __('Пока никто не откликнулся на это резюме.') }}
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
                        <input id="email" type="email" value="{{ $user->email }}" readonly
                               style="background:var(--gray-100); color:var(--gray-500);">
                    </div>
                    <div class="field">
                        <label for="phone">{{ __('Телефон') }}</label>
                        <input id="phone" type="text" value="{{ $applicant->phone }}" placeholder="+992 ..." readonly
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

@include('applicant.partials.js')

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
