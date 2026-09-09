@extends('layouts.auth-simple')

@section('title', __('Подтверждение пароля — Workio'))
@section('heading', __('Подтвердите пароль'))
@section('lead', __('Это защищённый раздел. Введите пароль ещё раз, чтобы продолжить.'))

@section('content')
    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div class="field">
            <label for="password">{{ __('Пароль') }}</label>
            <input type="password" name="password" id="password" autocomplete="current-password" required autofocus>
            @error('password')<div class="err">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn-primary">{{ __('Подтвердить') }}</button>
    </form>
@endsection
