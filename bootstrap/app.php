<?php

use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\IsApplicant;
use App\Http\Middleware\IsEmployer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            // без этого «выйти на всех устройствах» не завершает чужие сессии
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            // блокировка аккаунта действует немедленно, а не со следующего входа
            \App\Http\Middleware\EnsureAccountIsActive::class,
            \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->alias([
            'is_admin' => IsAdmin::class,
            'is_employer'=> IsEmployer::class,
            'is_applicant'=> IsApplicant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
