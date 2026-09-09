<?php

use App\Models\AdminComment;
use App\Models\User;

/**
 * Регрессия: «Ждут ответа» считалось по answered_at, а плашка у строки —
 * по служебному статусу очереди. Админ ставил статус «Отвечено» из выпадающего
 * списка, не написав ответа, и счётчик не двигался.
 */
function commentFrom(User $user): AdminComment
{
    return AdminComment::create([
        'user_id' => $user->id,
        'topic' => 'question',
        'body' => 'Достаточно длинный текст обращения для валидации.',
    ]);
}

function supportUser(): User
{
    $user = User::factory()->applicant()->create();
    makeApplicant($user);

    return $user;
}

test('an admin cannot mark a comment answered without writing a reply', function () {
    $user = supportUser();
    $comment = commentFrom($user);

    $this->actingAs(User::factory()->admin()->create())
        ->patch('/superadmin/comments/'.$comment->id.'/status', ['status' => 'answered'])
        ->assertSessionHasErrors('status');

    $comment->refresh();

    expect($comment->status)->toBe('new');
    expect($comment->isAnswered())->toBeFalse();

    // счётчик и плашка говорят одно и то же
    $page = $this->actingAs($user)->get('/applicant/support');
    expect($page->viewData('waiting'))->toBe(1);
    expect($page->viewData('answered'))->toBe(0);
    expect($comment->userStatusLabel())->toBe('Новое');
});

test('a real reply moves the comment out of waiting', function () {
    $user = supportUser();
    $comment = commentFrom($user);

    $before = $this->actingAs($user)->get('/applicant/support');
    expect($before->viewData('waiting'))->toBe(1);
    expect($before->viewData('answered'))->toBe(0);

    $this->actingAs(User::factory()->admin()->create())
        ->post('/superadmin/comments/'.$comment->id.'/reply', ['reply' => 'Ответ администратора.']);

    $after = $this->actingAs($user)->get('/applicant/support');
    expect($after->viewData('waiting'))->toBe(0);
    expect($after->viewData('answered'))->toBe(1);
    expect($comment->fresh()->userStatusLabel())->toBe('Отвечено');
});

test('an answered comment stays answered for the author even if the admin reopens it', function () {
    $user = supportUser();
    $comment = commentFrom($user);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/superadmin/comments/'.$comment->id.'/reply', ['reply' => 'Ответ есть.']);
    $this->actingAs($admin)->patch('/superadmin/comments/'.$comment->id.'/status', ['status' => 'in_review']);

    $page = $this->actingAs($user)->get('/applicant/support');

    expect($page->viewData('answered'))->toBe(1);
    expect($page->viewData('waiting'))->toBe(0);
    // автор видит «Отвечено», а не служебный статус очереди администратора
    expect($comment->fresh()->userStatusLabel())->toBe('Отвечено');
});

test('a comment closed without a reply stops waiting', function () {
    $user = supportUser();
    $comment = commentFrom($user);

    $this->actingAs(User::factory()->admin()->create())
        ->patch('/superadmin/comments/'.$comment->id.'/status', ['status' => 'closed'])
        ->assertRedirect();

    $page = $this->actingAs($user)->get('/applicant/support');

    expect($page->viewData('waiting'))->toBe(0);
    expect($page->viewData('answered'))->toBe(0);
    expect($page->viewData('closed'))->toBe(1);
    expect($comment->fresh()->userStatusLabel())->toBe('Закрыто');
});
