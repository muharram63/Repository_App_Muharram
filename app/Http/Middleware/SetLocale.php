<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Язык интерфейса: из сессии, иначе из настроек приложения.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $available = config('app.available_locales', ['ru']);
        $locale = $request->session()->get('locale')
            ?: ($request->user()?->locale ?: config('app.locale'));

        if (! in_array($locale, $available, true)) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
