<?php

use App\Models\Conversation;
use App\Models\Interview;
use App\Models\Message;
use App\Models\User;

test('attachment size is readable for tiny and empty files', function () {
    $message = new Message();

    $message->attachment_size = 0;
    expect($message->attachmentSizeLabel())->toBe('0 Б');

    $message->attachment_size = 400;
    expect($message->attachmentSizeLabel())->toBe('400 Б');

    $message->attachment_size = 2048;
    expect($message->attachmentSizeLabel())->toBe('2 КБ');

    $message->attachment_size = 2097152;
    expect($message->attachmentSizeLabel())->toBe('2 МБ');
});

test('every call attempt gets its own row in the journal', function () {
    $employerUser = User::factory()->employer()->create();
    $employer = makeEmployer($employerUser);
    $applicantUser = User::factory()->applicant()->create();
    $applicant = makeApplicant($applicantUser);

    $conversation = Conversation::create([
        'employer_id' => $employer->id,
        'applicant_id' => $applicant->id,
    ]);

    $interview = Interview::create([
        'employer_id' => $employer->id,
        'applicant_id' => $applicant->id,
        'scheduled_at' => now(),
        'duration_minutes' => 30,
        'room' => 'workio-'.uniqid(),
        'status' => 'confirmed',
    ]);

    // работодатель позвонил, соискатель перезвонил в ту же комнату
    Message::create([
        'conversation_id' => $conversation->id,
        'user_id' => $employerUser->id,
        'interview_id' => $interview->id,
    ]);
    Message::create([
        'conversation_id' => $conversation->id,
        'user_id' => $applicantUser->id,
        'interview_id' => $interview->id,
    ]);

    $rows = $this->actingAs($employerUser)->get('/employer/calls')->viewData('rows');

    // раньше keyBy оставлял только последнее приглашение — строка была одна
    expect($rows)->toHaveCount(2);
    expect($rows->pluck('direction')->sort()->values()->all())->toBe(['in', 'out']);
});

test('an industry can be created without the meaningless parent number', function () {
    [$city, $category, $industry] = refs();

    $this->actingAs(User::factory()->admin()->create())
        ->post('/superadmin/industries', [
            'name' => 'Логистика',
            'category_id' => $category->id,
            'description' => 'Перевозки',
        ])
        ->assertRedirect();

    expect(App\Models\Industry::where('name', 'Логистика')->exists())->toBeTrue();
});
