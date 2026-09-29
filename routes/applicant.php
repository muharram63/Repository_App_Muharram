<?php


use Illuminate\Support\Facades\Route;


Route::get('/profile',[\App\Http\Controllers\Applicant\ProfileController::class,'profile'])->name('dashboard');
Route::post('/profile',[\App\Http\Controllers\Applicant\ProfileController::class,'profileUpdate'])->name('profile.update');


// Помощник объявлен ДО ресурса: иначе resume.show поймает /resume/assistant
// первым и «assistant» уедет в параметр вместо своего маршрута.
Route::prefix('resume/assistant')->name('resume.assistant')->group(function () {
    $controller = \App\Http\Controllers\Applicant\ResumeAssistantController::class;

    Route::get('/', [$controller, 'index']);
    // новый разговор объявлен до {draft}, иначе «new» ушло бы в параметр
    Route::post('new', [$controller, 'start'])->name('.start');
    Route::get('{draft}', [$controller, 'index'])->whereNumber('draft')->name('.open');

    // ходы диалога дороже остальных запросов — ограничиваем частоту,
    // чтобы одна вкладка не сожгла дневную квоту бесплатного ключа
    Route::middleware('throttle:30,1')->group(function () use ($controller) {
        Route::post('{draft}/message', [$controller, 'message'])->whereNumber('draft')->name('.message');
        Route::post('{draft}/finalize', [$controller, 'finalize'])->whereNumber('draft')->name('.finalize');
    });
    Route::post('{draft}/save', [$controller, 'save'])->whereNumber('draft')->name('.save');
    Route::delete('{draft}', [$controller, 'destroy'])->whereNumber('draft')->name('.destroy');
});

// ИИ-собеседование: пока только голос аватара. Страница диалога появится
// вместе с остальными этапами модуля, компонент встанет в неё готовым.
Route::prefix('ai-interview')->name('ai.')->group(function () {
    $speech = \App\Http\Controllers\Applicant\AiSpeechController::class;

    $interview = \App\Http\Controllers\Applicant\AiInterviewController::class;

    Route::get('avatar', [$speech, 'page'])->name('avatar');

    // Мои собеседования: кандидат закрывает вкладку и возвращается, прогресс
    // лежит в базе — к нему нужен путь.
    Route::get('/', [$interview, 'index'])->name('index');

    // вход с вакансии: заводит собеседование или продолжает прежнее
    Route::get('start/{vacancy}', [$interview, 'start'])->whereNumber('vacancy')->name('start');

    Route::get('{interview}', [$interview, 'show'])->whereNumber('interview')->name('interview');
    Route::post('{interview}/consent', [$interview, 'consent'])->whereNumber('interview')->name('consent');
    Route::delete('{interview}', [$interview, 'decline'])->whereNumber('interview')->name('decline');

    Route::get('{interview}/documents', [$interview, 'documents'])
        ->whereNumber('interview')->name('documents');
    Route::post('{interview}/documents', [$interview, 'upload'])
        ->whereNumber('interview')->name('documents.upload');
    Route::get('{interview}/documents/{document}', [$interview, 'file'])
        ->whereNumber('interview')->whereNumber('document')->name('documents.file');
    Route::delete('{interview}/documents/{document}', [$interview, 'destroyDocument'])
        ->whereNumber('interview')->whereNumber('document')->name('documents.destroy');
    Route::post('{interview}/proceed', [$interview, 'proceed'])
        ->whereNumber('interview')->name('proceed');

    // Разговор с ИИ. Реплики идут через модель, поэтому частота ограничена:
    // одна вкладка не должна сжечь дневную квоту бесплатного ключа.
    $chat = \App\Http\Controllers\Applicant\AiChatController::class;

    Route::get('{interview}/chat', [$chat, 'show'])->whereNumber('interview')->name('chat');
    Route::get('{interview}/chat/state', [$chat, 'state'])->whereNumber('interview')->name('chat.state');

    Route::middleware('throttle:40,1')->group(function () use ($chat) {
        Route::post('{interview}/chat/begin', [$chat, 'begin'])
            ->whereNumber('interview')->name('chat.begin');
        Route::post('{interview}/chat/answer', [$chat, 'answer'])
            ->whereNumber('interview')->name('chat.answer');
    });

    // Озвучка произвольного текста — только для страницы проверки. Частота
    // ограничена жёстче остального: иначе маршрут превратился бы в
    // бесплатный синтезатор речи за счёт нашей квоты.
    Route::post('speech/preview', [$speech, 'preview'])
        ->middleware('throttle:10,1')->name('speech.preview');

    // Готовая озвучка реплики и её состояние. Права проверяет контроллер:
    // чужую озвучку не получить, даже зная адрес.
    Route::get('turns/{turn}/speech', [$speech, 'turn'])
        ->whereNumber('turn')->name('speech.turn');
    Route::get('turns/{turn}/speech/status', [$speech, 'status'])
        ->whereNumber('turn')->name('speech.status');
});

// Проверка навыков делом: справочник, прохождение задания и разбор
Route::prefix('skills')->name('skills')->group(function () {
    $skills = \App\Http\Controllers\Applicant\SkillCheckController::class;

    Route::get('/', [$skills, 'index']);
    // составление задания и проверка идут через модель — ограничиваем частоту
    Route::post('start', [$skills, 'start'])->middleware('throttle:20,1')->name('.start');
    Route::get('attempt/{attempt}', [$skills, 'attempt'])->whereNumber('attempt')->name('.attempt');
    Route::post('attempt/{attempt}', [$skills, 'submit'])
        ->whereNumber('attempt')->middleware('throttle:20,1')->name('.submit');
});

Route::resource('resume', \App\Http\Controllers\Applicant\ResumeController::class);
Route::get('responses', [\App\Http\Controllers\Applicant\ResponseController::class, 'index'])->name('responses.index');


Route::get('calls', [\App\Http\Controllers\CallLogController::class, 'index'])->name('calls.index');

Route::get('settings', [\App\Http\Controllers\SettingsController::class, 'index'])->name('settings.index');
Route::patch('settings/account', [\App\Http\Controllers\SettingsController::class, 'account'])->name('settings.account');
Route::patch('settings/password', [\App\Http\Controllers\SettingsController::class, 'password'])->name('settings.password');
Route::patch('settings/devices', [\App\Http\Controllers\SettingsController::class, 'logoutOthers'])->name('settings.devices');
Route::patch('settings/interface', [\App\Http\Controllers\SettingsController::class, 'interface'])->name('settings.interface');
Route::patch('settings/notifications', [\App\Http\Controllers\SettingsController::class, 'notifications'])->name('settings.notifications');
Route::patch('settings/privacy', [\App\Http\Controllers\SettingsController::class, 'privacy'])->name('settings.privacy');
Route::delete('settings', [\App\Http\Controllers\SettingsController::class, 'destroy'])->name('settings.destroy');

Route::get('complaints', [\App\Http\Controllers\ComplaintLogController::class, 'index'])->name('complaints.index');

Route::get('support', [\App\Http\Controllers\SupportController::class, 'index'])->name('support.index');
