{{ $headline }}

{{ $text }}

@if($url)
{{ __('Подробности — в вашем кабинете на сайте:') }} {{ $url }}
@endif

{{ config('app.name') }}
