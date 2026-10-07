<?php

namespace App\Http\Middleware;

use App\Models\AiInterview;
use App\Models\AiInterviewTurn;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Язык собеседования — язык всего, что кандидат видит и слышит в нём.
 *
 * Анна говорит на языке, который выбрал работодатель, а страница раньше
 * оставалась на языке интерфейса кандидата. Выходило вперемешку: Анна
 * спрашивает по-таджикски, кнопки и подсказки по-русски, ошибки модели
 * всегда по-русски, а голос браузера читал таджикский текст русским
 * голосом. Теперь на время собеседования всё на одном языке: интерфейс,
 * реплики, сообщения об ошибках, голос и распознавание речи.
 *
 * Права здесь не проверяются — только язык. Чужое собеседование всё равно
 * не откроется: это решает контроллер.
 */
class InterviewLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($locale = $this->locale($request)) {
            App::setLocale($locale);
        }

        return $next($request);
    }

    private function locale(Request $request): ?string
    {
        $interview = $request->route('interview');

        if (! $interview && ($turn = $request->route('turn'))) {
            $turn = $turn instanceof AiInterviewTurn ? $turn : AiInterviewTurn::find((int) $turn);
            $interview = $turn?->interview;
        }

        if ($interview !== null && ! $interview instanceof AiInterview) {
            $interview = AiInterview::find((int) $interview);
        }

        $locale = $interview?->config?->candidateLocale();

        return $locale && in_array($locale, config('app.available_locales', ['ru']), true) ? $locale : null;
    }
}
