<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Переключение языка интерфейса.
     */
    public function switch(Request $request, string $locale)
    {
        abort_unless(in_array($locale, config('app.available_locales', []), true), 404);

        $request->session()->put('locale', $locale);

        return back();
    }
}
