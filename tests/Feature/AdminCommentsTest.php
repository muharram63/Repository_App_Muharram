<?php

use App\Models\AdminComment;
use App\Models\Complaint;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\UserNotification;

function supportAuthor(): User
{
    $user = User::factory()->applicant()->create(['name' => 'Пётр Соискатель']);
    makeApplicant($user);

    return $user;
}

test('a user sends a comment to the administration', function () {
    $user = supportAuthor();

    $this->actingAs($user)->post('/support', [
        'topic' => 'problem',
        'body' => 'Не открывается страница откликов, вижу пустой экран.',
    ])->assertRedirect();

    $comment = AdminComment::first();

    expect($comment)->not->toBeNull();
    expect($comment->user_id)->toBe($user->id);
    expect($comment->topic)->toBe('problem');
    expect($comment->status)->toBe('new');

    // и видит своё обращение в кабинете
    $this->actingAs($user)->get('/applicant/support')
        ->assertOk()
        ->assertSee('Не открывается страница откликов');
});

test('the admin section shows the comment', function () {
    $user = supportAuthor();

    AdminComment::create([
        'user_id' => $user->id,
        'topic' => 'question',
        'body' => 'Как продлить публикацию резюме?',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/superadmin/comments')
        ->assertOk()
        ->assertSee('Как продлить публикацию резюме?')
        ->assertSee('Пётр Соискатель');
});

test('the admin section contains nothing but comments addressed to the admin', function () {
    $employerUser = User::factory()->employer()->create();
    $employer = makeEmployer($employerUser);
    $vacancy = makeVacancy($employer);

    $author = supportAuthor();
    $applicant = $author->applicant;

    // обращение к администрации — должно быть видно
    AdminComment::create([
        'user_id' => $author->id,
        'topic' => 'question',
        'body' => 'ОБРАЩЕНИЕ-К-АДМИНУ',
    ]);

    // сообщение в чате — видно быть не должно
    $conversation = Conversation::create([
        'employer_id' => $employer->id,
        'applicant_id' => $applicant->id,
    ]);
    Message::create([
        'conversation_id' => $conversation->id,
        'user_id' => $employerUser->id,
        'body' => 'СООБЩЕНИЕ-ИЗ-ЧАТА',
    ]);

    // жалоба — тоже не должна
    Complaint::create([
        'user_id' => $author->id,
        'target_type' => 'vacancy',
        'target_id' => $vacancy->id,
        'reason' => 'fake',
        'message' => 'ТЕКСТ-ЖАЛОБЫ',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/superadmin/comments')
        ->assertOk()
        ->assertSee('ОБРАЩЕНИЕ-К-АДМИНУ')
        ->assertDontSee('СООБЩЕНИЕ-ИЗ-ЧАТА')
        ->assertDontSee('ТЕКСТ-ЖАЛОБЫ');

    expect(AdminComment::count())->toBe(1);
});

test('a user cannot reach someone elses comment', function () {
    $author = supportAuthor();

    $comment = AdminComment::create([
        'user_id' => $author->id,
        'topic' => 'other',
        'body' => 'Личное обращение автора',
    ]);

    $stranger = User::factory()->employer()->create();
    makeEmployer($stranger);

    // в своём кабинете постороннего чужого обращения нет
    $this->actingAs($stranger)->get('/employer/support')
        ->assertOk()
        ->assertDontSee('Личное обращение автора');

    // и отозвать его он не может
    $this->actingAs($stranger)->delete('/support/'.$comment->id)->assertForbidden();

    expect(AdminComment::find($comment->id))->not->toBeNull();
});

test('the admin replies and the author is notified', function () {
    $author = supportAuthor();

    $comment = AdminComment::create([
        'user_id' => $author->id,
        'topic' => 'account',
        'body' => 'Не приходит письмо для подтверждения почты.',
    ]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/superadmin/comments/'.$comment->id.'/reply', ['reply' => 'Проверьте папку «Спам».'])
        ->assertRedirect();

    $comment->refresh();

    expect($comment->status)->toBe('answered');
    expect($comment->admin_id)->toBe($admin->id);
    expect($comment->answered_at)->not->toBeNull();
    expect($comment->isUnread())->toBeTrue();

    // текст пользователя админ не тронул
    expect($comment->body)->toBe('Не приходит письмо для подтверждения почты.');

    // уведомление в ящике автора
    expect(UserNotification::where('user_id', $author->id)->where('type', 'admin_reply')->count())->toBe(1);

    // автор открыл раздел — ответ прочитан
    $this->actingAs($author)->get('/applicant/support')
        ->assertOk()
        ->assertSee('Проверьте папку');

    expect($comment->fresh()->isUnread())->toBeFalse();
});

test('the daily limit stops a flood of comments', function () {
    $user = supportAuthor();

    for ($i = 0; $i < AdminComment::DAILY_LIMIT; $i++) {
        AdminComment::create([
            'user_id' => $user->id,
            'topic' => 'other',
            'body' => 'Обращение номер '.$i.', достаточно длинное.',
        ]);
    }

    $this->actingAs($user)->post('/support', [
        'topic' => 'other',
        'body' => 'Ещё одно обращение сверх лимита.',
    ])->assertRedirect();

    expect(AdminComment::where('user_id', $user->id)->count())->toBe(AdminComment::DAILY_LIMIT);
});

test('the author withdraws a comment only before it is answered', function () {
    $author = supportAuthor();

    $comment = AdminComment::create([
        'user_id' => $author->id,
        'topic' => 'idea',
        'body' => 'Предлагаю добавить фильтр по зарплате.',
    ]);

    $answered = AdminComment::create([
        'user_id' => $author->id,
        'topic' => 'idea',
        'body' => 'Второе предложение, на него уже ответили.',
        'status' => 'answered',
        'admin_reply' => 'Спасибо',
        'answered_at' => now(),
    ]);

    $this->actingAs($author)->delete('/support/'.$comment->id)->assertRedirect();
    expect(AdminComment::find($comment->id))->toBeNull();

    $this->actingAs($author)->delete('/support/'.$answered->id)->assertRedirect();
    expect(AdminComment::find($answered->id))->not->toBeNull();
});

test('the comments section is closed to everyone but admins', function () {
    $user = supportAuthor();

    $this->actingAs($user)->get('/superadmin/comments')->assertForbidden();

    auth()->logout();
    $this->get('/superadmin/comments')->assertRedirect(route('login', absolute: false));
});
