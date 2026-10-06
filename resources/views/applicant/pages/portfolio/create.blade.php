<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" @include('partials.theme')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Workio — Новая работа') }}</title>
    @include('applicant.partials.css')
</head>
<body>

<div class="app">
    @include('applicant.partials.sidebar')

    <main class="main">
        @include('applicant.pages.portfolio._head', [
            'title' => __('Новая работа'),
            'subtitle' => __('Добавьте фото или видео выполненной работы'),
        ])

        <div class="card" style="max-width:860px;">
            @include('applicant.pages.portfolio._form')
        </div>
    </main>
</div>

@include('applicant.partials.js')
</body>
</html>
