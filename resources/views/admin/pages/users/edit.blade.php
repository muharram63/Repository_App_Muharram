@extends('admin.layouts.app')

@section('main')
    <style>
        .u-card{
            background:#fff; border:1px solid #e2e8f0; border-radius:14px;
            padding:24px; max-width:720px; margin-bottom:18px;
        }
        .u-card h2{margin:0 0 4px; font-size:18px;}
        .u-card .sub{margin:0 0 22px; font-size:13.5px; color:#64748b;}
        .u-grid{display:grid; grid-template-columns:1fr 1fr; gap:16px 20px;}
        .u-grid .full{grid-column:1 / -1;}
        .u-field label{display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#0f172a;}
        .u-value{
            padding:10px 12px; border:1px solid #e2e8f0; border-radius:9px;
            font-size:14px; color:#0f172a; background:#f8fafc; min-height:40px;
            display:flex; align-items:center; word-break:break-all;
        }
        .u-field select{
            width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:9px;
            font-size:14px; color:#0f172a; background:#fff; font-family:inherit;
        }
        .u-field select:focus{outline:none; border-color:#1656D6;}
        .u-err{font-size:12.5px; color:#b91c1c; margin-top:6px;}
        .u-actions{display:flex; gap:10px; justify-content:flex-end; margin-top:22px;}
        .u-btn{
            padding:10px 18px; border-radius:9px; border:1px solid transparent;
            font-size:14px; font-weight:600; cursor:pointer; text-decoration:none;
        }
        .u-primary{background:#1656D6; color:#fff;}
        .u-secondary{background:#fff; color:#0f172a; border-color:#cbd5e1;}
        .u-danger{background:#DC2626; color:#fff;}
        .u-alert{
            border:1px solid #fecaca; background:#fef2f2; color:#b91c1c;
            border-radius:10px; padding:12px 14px; margin-bottom:18px; font-weight:600; font-size:14px;
        }
        .u-note{
            border:1px solid #e2e8f0; background:#f8fafc; color:#475569;
            border-radius:10px; padding:12px 14px; font-size:13.5px; line-height:1.6;
        }
    </style>

    @php
        $roles = ['applicant' => 'Соискатель', 'employer' => 'Работодатель', 'admin' => 'Администратор'];
        // выставить можно только эти два; старые значения показываем, если встретятся
        $statuses = ['active' => 'Активен', 'blocked' => 'Заблокирован'];
        $statusNames = $statuses + ['inactive' => 'Неактивен (устаревший)', 'under review' => 'На проверке (устаревший)'];
        $isSelf = $user->id === auth()->id();
    @endphp

    <div class="u-card">
        <h2>{{ __('Карточка пользователя') }}</h2>
        <p class="sub">ID {{ $user->id }} · зарегистрирован {{ $user->created_at?->format('d.m.Y') ?? '—' }}</p>

        @if(session('error'))
            <div class="u-alert">{{ session('error') }}</div>
        @endif

        <div class="u-grid">
            <div class="u-field">
                <label>{{ __('Имя') }}</label>
                <div class="u-value">{{ $user->name }}</div>
            </div>

            <div class="u-field">
                <label>Email</label>
                <div class="u-value">{{ $user->email }}</div>
            </div>

            <div class="u-field">
                <label>{{ __('Роль') }}</label>
                <div class="u-value">{{ $roles[$user->role] ?? $user->role }}</div>
            </div>

            <div class="u-field">
                <label>{{ __('Текущий статус') }}</label>
                <div class="u-value">{{ $statusNames[$user->status] ?? $user->status }}</div>
            </div>

            @if($user->employer)
                <div class="u-field full">
                    <label>{{ __('Компания') }}</label>
                    <div class="u-value">{{ $user->employer->company_name }} · {{ $user->employer->phone }}</div>
                </div>
            @endif

            @if($user->applicant)
                <div class="u-field full">
                    <label>{{ __('Анкета соискателя') }}</label>
                    <div class="u-value">{{ $user->applicant->city ?: '—' }} · {{ $user->applicant->phone ?: '—' }}</div>
                </div>
            @endif
        </div>

        <p class="u-note" style="margin-top:20px;">
            {{ __('Имя, email, роль и анкету меняет только сам пользователь. Администратору доступны блокировка, удаление и просмотр.') }}
        </p>
    </div>

    @unless($isSelf)
        <div class="u-card">
            <h2>{{ __('Доступ к аккаунту') }}</h2>
            <p class="sub">{{ __('Блокировка действует сразу: открытая сессия пользователя завершается.') }}</p>

            <form action="{{ route('superadmin.users.update', $user) }}" method="post">
                @csrf
                @method('PATCH')

                <div class="u-field">
                    <label for="status">{{ __('Статус') }}</label>
                    <select name="status" id="status">
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $user->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="u-err">{{ $message }}</div>@enderror
                </div>

                <div class="u-actions">
                    <a href="{{ route('superadmin.users') }}" class="u-btn u-secondary">{{ __('Назад') }}</a>
                    <button type="submit" class="u-btn u-primary">{{ __('Сохранить статус') }}</button>
                </div>
            </form>
        </div>
    @endunless

    @if($isSelf)
        <div class="u-card">
            <h2>{{ __('Отказ от роли администратора') }}</h2>

            @if($adminsCount <= 1)
                <p class="u-note">
                    {{ __('Вы единственный администратор. Пока не появится второй, снять с себя роль нельзя.') }}
                </p>
            @else
                <p class="sub">{{ __('Выберите, кем вы останетесь на площадке. Действие необратимо через интерфейс.') }}</p>

                <form action="{{ route('superadmin.users.resign') }}" method="post"
                      onsubmit="return confirm('{{ __('Снять с себя роль администратора?') }}');">
                    @csrf

                    <div class="u-field">
                        <label for="role">{{ __('Новая роль') }}</label>
                        <select name="role" id="role">
                            <option value="applicant">{{ __('Соискатель') }}</option>
                            <option value="employer">{{ __('Работодатель') }}</option>
                        </select>
                        @error('role')<div class="u-err">{{ $message }}</div>@enderror
                    </div>

                    <div class="u-actions">
                        <a href="{{ route('superadmin.users') }}" class="u-btn u-secondary">{{ __('Назад') }}</a>
                        <button type="submit" class="u-btn u-danger">{{ __('Отказаться от роли') }}</button>
                    </div>
                </form>
            @endif
        </div>
    @endif
@endsection
