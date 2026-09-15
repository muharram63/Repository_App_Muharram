{{--
    Атрибут темы для тега <html>. Подключается прямо внутри тега:
    <html lang="ru" @include('partials.theme')>

    Три состояния, а не два. «Системная» — это не синоним светлой: при ней
    атрибут не выводится вовсе, и тему выбирает CSS по prefers-color-scheme,
    то есть по настройке самого устройства. Явный выбор пользователя атрибут
    ставит и перебивает системную настройку в обе стороны.
--}}
@php($appTheme = auth()->check() ? (auth()->user()->theme ?: 'light') : session('theme', 'light'))
@if($appTheme === 'dark') data-theme="dark"@elseif($appTheme === 'light') data-theme="light"@endif
