<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * Сервер видеовстреч. Публичный meet.jit.si с августа 2023 не даёт
     * создавать комнаты без входа в аккаунт: участники видят «Waiting for a
     * moderator» и встреча не начинается. Поэтому по умолчанию используем
     * инстанс, где анонимные комнаты разрешены, а домен выносим в настройки —
     * чтобы подключить свой сервер без правки кода.
     */
    'jitsi' => [
        'domain' => env('JITSI_DOMAIN', 'meet.ffmuc.net'),
    ],

    /*
     * Gemini — диалоговый помощник по составлению резюме. Ключ бесплатного
     * тарифа выдаётся в Google AI Studio. Без ключа помощник выключается сам,
     * а обычная форма создания резюме продолжает работать как раньше.
     */
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        // flash-lite: у каждой модели своя бесплатная квота, а у этой она самая
        // щедрая и отвечает она быстрее — для диалога в реальном времени важнее
        // скорость, чем глубина рассуждений
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
        // ревизию API фиксируем: формат ответа Google уже менял, и без этого
        // заголовка помощник однажды сломался бы сам по себе
        'revision' => env('GEMINI_API_REVISION', '2026-05-20'),
        // ответ не приходит по частям: пока модель пишет, не видно ни байта,
        // поэтому общий таймаут щедрый, а на установку связи — короткий
        'timeout' => (int) env('GEMINI_TIMEOUT', 60),
        'connect_timeout' => (int) env('GEMINI_CONNECT_TIMEOUT', 10),
    ],

];
