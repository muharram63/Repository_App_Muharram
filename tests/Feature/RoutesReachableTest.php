<?php

/*
 * Маршрут, до которого нельзя дойти, — обещание без исполнителя.
 *
 * Такой адрес открыт наружу, его никто не поддерживает, и со временем он
 * расходится с кодом: страница давно работает иначе, а метод отвечает как
 * умел год назад. Проверяем, что каждый маршрут модуля откуда-то достижим.
 *
 * Общие маршруты площадки сюда не берём: часть из них framework зовёт по
 * имени сам (сброс пароля, подтверждение почты), часть собирается строкой в
 * JS. Ограничиваемся своим модулем, где адреса заводили мы.
 */

use Illuminate\Support\Facades\Route;

/** Весь PHP и Blade проекта одним текстом — в нём и ищем упоминания. */
function projectSource(): string
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $text = '';

    foreach ([resource_path('views'), app_path()] as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

        foreach ($files as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $text .= file_get_contents($file->getPathname());
            }
        }
    }

    return $cache = $text;
}

test('до каждого адреса ИИ-собеседования можно дойти', function () {
    $source = projectSource();
    $unreachable = [];

    foreach (Route::getRoutes() as $route) {
        $name = $route->getName();

        if (! $name || ! str_starts_with($name, 'applicant.ai.') && ! str_starts_with($name, 'employer.ai.')) {
            continue;
        }

        if (str_contains($source, "'".$name."'") || str_contains($source, '"'.$name.'"')) {
            continue;
        }

        $unreachable[] = $name;
    }

    /*
     * Так ушли четыре адреса: два опроса состояния, которыми страницы так и не
     * стали пользоваться (они просто перезагружаются целиком), отдельное
     * удаление критерия, оставшееся после переноса кнопки в форму, и страница
     * проверки аватара — у неё не было входа, и ссылка на неё появилась на
     * списке собеседований.
     */
    expect($unreachable)->toBe([], 'адреса без единой ссылки: '.implode(', ', $unreachable));
});

test('страница проверки аватара открывается со списка собеседований', function () {
    [$vacancy] = makeAiVacancy();
    [, , $applicantUser] = makeAiInterview($vacancy);

    $this->actingAs($applicantUser)->get(route('applicant.ai.index'))
        ->assertOk()
        ->assertSee(route('applicant.ai.avatar'), false);

    // и сама страница жива
    $this->actingAs($applicantUser)->get(route('applicant.ai.avatar'))->assertOk();
});

test('удалённые адреса больше не объявлены', function () {
    $names = collect(Route::getRoutes())->map(fn ($r) => $r->getName())->filter()->all();

    foreach ([
        'applicant.ai.chat.state',
        'applicant.ai.task.state',
        'employer.ai.criteria.destroy',
    ] as $gone) {
        expect($names)->not->toContain($gone, $gone.' снова объявлен');
    }
});

test('критерий по-прежнему удаляется — кнопкой своей формы', function () {
    [$vacancy, , $employerUser] = makeAiVacancy([
        ['key' => 'php', 'label' => 'PHP', 'kind' => 'must', 'weight' => 50],
        ['key' => 'sql', 'label' => 'SQL', 'kind' => 'nice', 'weight' => 50],
    ]);

    $drop = App\Models\AiInterviewCriterion::where('key', 'sql')->sole();

    // путь удаления остался один, и он живой
    $this->actingAs($employerUser)
        ->patch(route('employer.ai.criteria.save', $vacancy), ['remove' => $drop->id])
        ->assertSessionHas('status');

    expect(App\Models\AiInterviewCriterion::pluck('key')->all())->toBe(['php']);
});
