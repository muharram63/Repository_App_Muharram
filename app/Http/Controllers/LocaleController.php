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

        // Запоминаем и в аккаунте: на этом языке человеку пишутся уведомления,
        // а их отправляет чужой запрос или очередь, где сессии нет.
        $request->user()?->forceFill(['locale' => $locale])->save();

        return back();
    }
}
