<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * На хостинге обычно нет симлинка public/storage: `php artisan storage:link`
 * там не выполняли или симлинки запрещены. Загрузка при этом проходит, но
 * фото не появляется — выглядит как «не получается загрузить фото».
 * Storage::fake как раз воспроизводит эту ситуацию: файл на диске есть,
 * а по public_path его нет.
 */

test('аватар считается загруженным, даже когда симлинка нет', function () {
    Storage::fake('public');

    Storage::disk('public')->put('avatars/face.jpg', 'binary');

    $user = User::factory()->applicant()->create(['avatar' => 'storage/avatars/face.jpg']);

    expect(is_file(public_path($user->avatar)))->toBeFalse()
        ->and($user->hasAvatar())->toBeTrue();
});

test('без файла на диске аватара нет — рисуем букву-заглушку', function () {
    Storage::fake('public');

    $user = User::factory()->applicant()->create(['avatar' => 'storage/avatars/missing.jpg']);

    expect($user->hasAvatar())->toBeFalse();
});

test('аватар отдаётся по обычной ссылке без симлинка', function () {
    Storage::fake('public');

    Storage::disk('public')->putFileAs('avatars', UploadedFile::fake()->image('face.jpg'), 'face.jpg');

    $this->get('/storage/avatars/face.jpg')
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg');
});

test('несуществующий аватар даёт 404, а не пустой ответ', function () {
    Storage::fake('public');

    $this->get('/storage/avatars/nope.jpg')->assertNotFound();
});

/*
 * Главное ограничение: на том же диске лежат старые вложения чатов и
 * документы резюме. Их отдаёт SecureFileController с проверкой прав,
 * и запасной маршрут не должен становиться обходным путём к ним.
 *
 * Код ответа не проверяем: такие пути перехватывает штатный storage/{path}
 * фреймворка (он требует подписанную ссылку и отвечает 403). Важно одно —
 * содержимое наружу не уходит.
 */
test('запасной маршрут открывает только avatars', function () {
    Storage::fake('public');

    Storage::disk('public')->put('chat/secret.txt', 'приватная переписка');
    Storage::disk('public')->put('resumes/secret.pdf', 'чужое резюме');

    $chat = $this->get('/storage/chat/secret.txt');
    expect($chat->isSuccessful())->toBeFalse()
        ->and($chat->getContent())->not->toContain('приватная переписка');

    $resume = $this->get('/storage/resumes/secret.pdf');
    expect($resume->isSuccessful())->toBeFalse()
        ->and($resume->getContent())->not->toContain('чужое резюме');
});

test('выход из папки avatars через .. не проходит', function () {
    Storage::fake('public');

    Storage::disk('public')->put('chat/secret.txt', 'приватная переписка');

    $response = $this->get('/storage/avatars/..%2Fchat%2Fsecret.txt');

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getContent())->not->toContain('приватная переписка');
});
