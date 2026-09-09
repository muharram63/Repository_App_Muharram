@extends('layouts.auth-simple')

@section('title', __('Новый пароль — Workio'))
@section('heading', __('Новый пароль'))
@section('lead', __('Придумайте пароль не короче 8 символов.'))

@section('content')
    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="field">
            <label for="email">{{ __('Email') }}</label>
            <input type="email" name="email" id="email" value="{{ old('email', $request->email) }}" required autofocus>
            @error('email')<div class="err">{{ $message }}</div>@enderror
        </div>

        <div class="field">
            <label for="password">{{ __('Новый пароль') }}</label>
            <input type="password" name="password" id="password" autocomplete="new-password" required>
            @error('password')<div class="err">{{ $message }}</div>@enderror
        </div>

        <div class="field">
            <label for="password_confirmation">{{ __('Повторите пароль') }}</label>
            <input type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password" required>
            @error('password_confirmation')<div class="err">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn-primary">{{ __('Сохранить пароль') }}</button>
    </form>

    <a href="{{ route('login') }}" class="btn-link">{{ __('Вернуться ко входу') }}</a>
@endsection
