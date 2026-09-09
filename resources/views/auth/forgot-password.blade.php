@extends('layouts.auth-simple')

@section('title', __('Восстановление пароля — Workio'))
@section('heading', __('Забыли пароль?'))
@section('lead', __('Укажите почту, и мы пришлём ссылку для создания нового пароля.'))

@section('content')
    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="field">
            <label for="email">{{ __('Email') }}</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus>
            @error('email')<div class="err">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn-primary">{{ __('Прислать ссылку') }}</button>
    </form>

    <a href="{{ route('login') }}" class="btn-link">{{ __('Вернуться ко входу') }}</a>
@endsection
