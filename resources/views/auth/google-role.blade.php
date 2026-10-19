@extends('layouts.auth-simple')

@section('title', __('Регистрация через Google — Workio'))
@section('heading', __('Почти готово'))
@section('lead', __('Аккаунт :email ещё не зарегистрирован в Workio. Кто вы?', ['email' => $pending['email']]))

@section('content')
    {{-- роль определяет кабинет; админа так получить нельзя — только эти две --}}
    <form method="POST" action="{{ route('auth.google.role.store') }}">
        @csrf

        <div class="field">
            <label>
                <input type="radio" name="role" value="applicant" checked>
                {{ __('Соискатель или мастер — ищу работу или заказы') }}
            </label>
            <label>
                <input type="radio" name="role" value="employer">
                {{ __('Работодатель — ищу сотрудников') }}
            </label>
            @error('role')<div class="err">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn-primary">{{ __('Создать аккаунт') }}</button>
    </form>

    <a href="{{ route('login') }}" class="btn-link">{{ __('Вернуться ко входу') }}</a>
@endsection
