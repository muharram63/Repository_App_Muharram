@extends('layouts.auth-simple')

@section('title', __('Подтверждение почты — Workio'))
@section('heading', __('Подтвердите почту'))
@section('lead', __('Мы отправили ссылку на вашу почту. Не нашли письмо — запросите новое.'))

@section('content')
    @if(session('status') === 'verification-link-sent')
        <div class="note">{{ __('Новая ссылка отправлена на вашу почту.') }}</div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="btn-primary">{{ __('Отправить письмо ещё раз') }}</button>
    </form>

    <div class="row">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn-link">{{ __('Выйти') }}</button>
        </form>
        <a href="{{ route('public.home') }}" class="btn-link">{{ __('На главную') }}</a>
    </div>
@endsection
