{{-- Настройки кабинета: общий блок для соискателя и работодателя --}}
<style>
    .set-card{margin-bottom:18px;}
    .set-hint{font-size:13px; color:var(--gray-500); margin:-2px 0 16px;}
    .set-error{font-size:12.5px; color:#B91C1C; margin-top:6px;}
    .set-alert{
        border:1px solid #BBF7D0; background:#F0FDF4; color:#15803D;
        font-weight:600; border-radius:12px; padding:14px 16px; margin-bottom:18px;
    }

    .set-avatar{display:flex; align-items:center; gap:16px; margin-bottom:18px;}
    .set-avatar .pic{
        width:72px; height:72px; border-radius:18px; overflow:hidden; flex-shrink:0;
        background:var(--indigo-50); color:var(--indigo-700);
        display:flex; align-items:center; justify-content:center; font-weight:700; font-size:22px;
    }
    .set-avatar .pic img{width:100%; height:100%; object-fit:cover;}

    .set-switch{
        display:flex; align-items:flex-start; gap:12px;
        padding:14px 0; border-bottom:1px solid #EEF1F6;
    }
    .set-switch:last-of-type{border-bottom:none;}
    .set-switch input{width:18px; height:18px; margin-top:2px; flex-shrink:0; accent-color:var(--indigo-600);}
    .set-switch .t{flex:1; min-width:0;}
    .set-switch .t b{display:block; font-size:14px; font-weight:600;}
    .set-switch .t span{display:block; font-size:12.5px; color:var(--gray-500); margin-top:2px;}

    .set-danger{border-color:#FECACA;}
    .set-danger h2{color:#B91C1C;}
    .btn-danger{background:#DC2626; color:#fff;}
    .btn-danger:hover{background:#B91C1C;}
</style>

@if(session('status'))
    <div class="set-alert">{{ session('status') }}</div>
@endif

{{-- ============ Аккаунт ============ --}}
<div class="card set-card">
    <div class="card-header">
        <h2>{{ __('Аккаунт') }}</h2>
        <span class="hint">{{ __('Имя, почта и фотография профиля') }}</span>
    </div>

    <form action="{{ route($user->employer ? 'employer.settings.account' : 'applicant.settings.account') }}"
          method="post" enctype="multipart/form-data">
        @csrf
        @method('PATCH')

        <div class="set-avatar">
            <div class="pic">
                @if($user->hasAvatar())
                    <img src="{{ asset($user->avatar) }}" alt="{{ __('Фото профиля') }}">
                @else
                    {{ $user->initials(1) }}
                @endif
            </div>
            <div class="field" style="flex:1;">
                <label for="avatar">{{ __('Фотография профиля') }}</label>
                <input type="file" name="avatar" id="avatar" accept="image/*">
                @if($user->hasAvatar())
                    <label style="font-weight:500; margin-top:8px; display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" name="remove_avatar" value="1" style="width:16px; height:16px;">
                        {{ __('Удалить текущее фото') }}
                    </label>
                @endif
                @error('avatar', 'account')<div class="set-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="form-grid">
            <div class="field">
                <label for="set-name">{{ __('Имя') }}</label>
                <input type="text" name="name" id="set-name" value="{{ old('name', $user->name) }}" required>
                @error('name', 'account')<div class="set-error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="set-email">{{ __('Email') }}</label>
                <input type="email" name="email" id="set-email" value="{{ old('email', $user->email) }}" required>
                @error('email', 'account')<div class="set-error">{{ $message }}</div>@enderror
                @if(! $user->email_verified_at)
                    <div class="set-hint" style="margin:6px 0 0;">{{ __('Почта не подтверждена') }}</div>
                @endif
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">{{ __('Сохранить') }}</button>
        </div>
    </form>
</div>

{{-- ============ Безопасность ============ --}}
<div class="card set-card">
    <div class="card-header">
        <h2>{{ __('Безопасность') }}</h2>
        <span class="hint">{{ __('Пароль и активные сессии') }}</span>
    </div>

    <form action="{{ route($user->employer ? 'employer.settings.password' : 'applicant.settings.password') }}"
          method="post">
        @csrf
        @method('PATCH')

        <div class="form-grid">
            <div class="field full">
                <label for="current_password">{{ __('Текущий пароль') }}</label>
                <input type="password" name="current_password" id="current_password" autocomplete="current-password" required>
                @error('current_password', 'password')<div class="set-error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="new_password">{{ __('Новый пароль') }}</label>
                <input type="password" name="password" id="new_password" autocomplete="new-password" required>
                @error('password', 'password')<div class="set-error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="password_confirmation">{{ __('Повторите пароль') }}</label>
                <input type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password" required>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">{{ __('Изменить пароль') }}</button>
        </div>
    </form>

    <div style="border-top:1px solid #EEF1F6; margin-top:18px; padding-top:18px;">
        <form action="{{ route($user->employer ? 'employer.settings.devices' : 'applicant.settings.devices') }}"
              method="post">
            @csrf
            @method('PATCH')

            <div class="form-grid">
                <div class="field">
                    <label for="devices_password">{{ __('Выйти на всех устройствах') }}</label>
                    <input type="password" name="password" id="devices_password" placeholder="{{ __('Текущий пароль') }}"
                           autocomplete="current-password" required>
                    @error('password', 'devices')<div class="set-error">{{ $message }}</div>@enderror
                </div>
                <div class="field" style="display:flex; align-items:flex-end;">
                    <button type="submit" class="btn btn-secondary">{{ __('Завершить другие сессии') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ============ Интерфейс ============ --}}
<div class="card set-card">
    <div class="card-header">
        <h2>{{ __('Интерфейс') }}</h2>
        <span class="hint">{{ __('Язык и оформление') }}</span>
    </div>

    <form action="{{ route($user->employer ? 'employer.settings.interface' : 'applicant.settings.interface') }}"
          method="post">
        @csrf
        @method('PATCH')

        @php
            $localeNames = ['ru' => 'Русский', 'en' => 'English', 'tg' => 'Тоҷикӣ'];
            $currentLocale = old('locale', $user->locale ?: app()->getLocale());
            $currentTheme = old('theme', $user->theme ?: 'light');
        @endphp

        <div class="form-grid">
            <div class="field">
                <label for="locale">{{ __('Язык интерфейса') }}</label>
                <select name="locale" id="locale">
                    @foreach($locales as $locale)
                        <option value="{{ $locale }}" @selected($currentLocale === $locale)>
                            {{ $localeNames[$locale] ?? strtoupper($locale) }}
                        </option>
                    @endforeach
                </select>
                @error('locale', 'interface')<div class="set-error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="theme">{{ __('Тема') }}</label>
                <select name="theme" id="theme">
                    <option value="light" @selected($currentTheme === 'light')>{{ __('Светлая') }}</option>
                    <option value="dark" @selected($currentTheme === 'dark')>{{ __('Тёмная') }}</option>
                    <option value="system" @selected($currentTheme === 'system')>{{ __('Как в системе') }}</option>
                </select>
                @error('theme', 'interface')<div class="set-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">{{ __('Сохранить') }}</button>
        </div>
    </form>
</div>

{{-- ============ Уведомления ============ --}}
<div class="card set-card">
    <div class="card-header">
        <h2>{{ __('Уведомления') }}</h2>
        <span class="hint">{{ __('Звонки и ящик уведомлений') }}</span>
    </div>

    <form action="{{ route($user->employer ? 'employer.settings.notifications' : 'applicant.settings.notifications') }}"
          method="post">
        @csrf
        @method('PATCH')

        <label class="set-switch">
            <input type="checkbox" name="call_notify" value="1" @checked($settings->call_notify)>
            <span class="t">
                <b>{{ __('Уведомление о входящем звонке') }}</b>
                <span>{{ __('Всплывающая карточка на всех страницах платформы') }}</span>
            </span>
        </label>

        <label class="set-switch">
            <input type="checkbox" name="call_sound" value="1" @checked($settings->call_sound)>
            <span class="t">
                <b>{{ __('Звук входящего звонка') }}</b>
                <span>{{ __('Звуковой сигнал, пока звонок не принят') }}</span>
            </span>
        </label>

        <label class="set-switch">
            <input type="checkbox" name="mail_responses" value="1" @checked($settings->mail_responses)>
            <span class="t">
                <b>{{ __('Уведомления об откликах') }}</b>
                <span>{{ __('Новые отклики, приглашения и смена их статуса — в ящик уведомлений') }}</span>
            </span>
        </label>

        <label class="set-switch">
            <input type="checkbox" name="mail_messages" value="1" @checked($settings->mail_messages)>
            <span class="t">
                <b>{{ __('Уведомления о сообщениях') }}</b>
                <span>{{ __('Новые сообщения в чате — в ящик уведомлений') }}</span>
            </span>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">{{ __('Сохранить') }}</button>
        </div>
    </form>
</div>

{{-- ============ Приватность ============ --}}
<div class="card set-card">
    <div class="card-header">
        <h2>{{ __('Приватность') }}</h2>
        <span class="hint">{{ __('Что видно в публичной части') }}</span>
    </div>

    <form action="{{ route($user->employer ? 'employer.settings.privacy' : 'applicant.settings.privacy') }}"
          method="post">
        @csrf
        @method('PATCH')

        @if($user->employer)
            <label class="set-switch">
                <input type="checkbox" name="hide_company" value="1" @checked($settings->hide_company)>
                <span class="t">
                    <b>{{ __('Скрыть компанию из каталога') }}</b>
                    <span>{{ __('Страница компании будет доступна только вам и администратору') }}</span>
                </span>
            </label>
        @else
            <label class="set-switch">
                <input type="checkbox" name="hide_profile" value="1" @checked($settings->hide_profile)>
                <span class="t">
                    <b>{{ __('Скрыть анкету и резюме из каталога') }}</b>
                    <span>{{ __('Резюме перестанут показываться в публичном поиске') }}</span>
                </span>
            </label>

            <label class="set-switch">
                <input type="checkbox" name="hide_contacts" value="1" @checked($settings->hide_contacts)>
                <span class="t">
                    <b>{{ __('Не показывать телефон и email в резюме') }}</b>
                    <span>{{ __('Работодатели смогут связаться с вами через чат') }}</span>
                </span>
            </label>
        @endif

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">{{ __('Сохранить') }}</button>
        </div>
    </form>
</div>

{{-- ============ Опасная зона ============ --}}
<div class="card set-card set-danger">
    <div class="card-header">
        <h2>{{ __('Удаление аккаунта') }}</h2>
    </div>

    <p class="set-hint">
        {{ __('Вместе с аккаунтом безвозвратно удалятся анкета, резюме, вакансии, отклики, собеседования и переписка.') }}
    </p>

    <form action="{{ route($user->employer ? 'employer.settings.destroy' : 'applicant.settings.destroy') }}"
          method="post" onsubmit="return confirm('{{ __('Удалить аккаунт без возможности восстановления?') }}');">
        @csrf
        @method('DELETE')

        <div class="form-grid">
            <div class="field">
                <label for="destroy_password">{{ __('Подтвердите паролем') }}</label>
                <input type="password" name="password" id="destroy_password" autocomplete="current-password" required>
                @error('password', 'destroy')<div class="set-error">{{ $message }}</div>@enderror
            </div>
            <div class="field" style="display:flex; align-items:flex-end;">
                <button type="submit" class="btn btn-danger">{{ __('Удалить аккаунт') }}</button>
            </div>
        </div>
    </form>
</div>
