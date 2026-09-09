<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Настройки') }}</title>
    @include('applicant.partials.css')
</head>
<body>

<div class="app">

    @include('applicant.partials.sidebar')

    <main class="main">

        <div class="topbar">
            <div>
                <h1 id="pageTitle">{{ __('Настройки') }}</h1>
                <p id="pageSubtitle">{{ __('Аккаунт, безопасность, уведомления и приватность') }}</p>
            </div>
            @include('partials.notification-bell')
            <div class="user-chip">
                <div class="avatar" style="padding: 14px 16px;">
                    @if($user->hasAvatar())
                        <img src="{{ asset($user->avatar) }}" alt="{{ __('Фото профиля') }}"
                             style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                    @else
                        <div class="avatar text-white d-flex align-items-center justify-content-center"
                             style="font-size:13px; font-weight:700; background:#1656D6;">
                            {{ $user->initials(1) }}
                        </div>
                    @endif
                </div>
                <div>
                    <div class="name">{{ $user->name }}</div>
                    <div class="role">{{ $user->email }}</div>
                </div>
            </div>
        </div>

        <section class="section active" id="section-settings">
            @include('partials.settings-form')
        </section>

    </main>
</div>

</body>
</html>
