<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function chatFixture(): array
{
    $employerUser = User::factory()->employer()->create();
    $applicantUser = User::factory()->applicant()->create();

    $employer = makeEmployer($employerUser);
    $applicant = makeApplicant($applicantUser);

    $conversation = Conversation::create([
        'employer_id' => $employer->id,
        'applicant_id' => $applicant->id,
    ]);

    return [$employerUser, $applicantUser, $conversation];
}

test('chat attachments land on the private disk, not the public one', function () {
    Storage::fake('local');
    Storage::fake('public');

    [$employerUser, , $conversation] = chatFixture();

    $this->actingAs($employerUser)->post('/chats/'.$conversation->id.'/messages', [
        'body' => 'Резюме во вложении',
        'attachment' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf'),
    ])->assertRedirect();

    $message = Message::latest('id')->first();

    expect($message->attachment_path)->toStartWith('chat/'.$conversation->id.'/');
    expect(Storage::disk('local')->exists($message->attachment_path))->toBeTrue();
    expect(Storage::disk('public')->exists($message->attachment_path))->toBeFalse();
});

test('only chat participants can download an attachment', function () {
    Storage::fake('local');

    [$employerUser, $applicantUser, $conversation] = chatFixture();
    Storage::disk('local')->put('chat/'.$conversation->id.'/cv.pdf', 'содержимое');

    $message = Message::create([
        'conversation_id' => $conversation->id,
        'user_id' => $employerUser->id,
        'body' => null,
        'attachment_path' => 'chat/'.$conversation->id.'/cv.pdf',
        'attachment_name' => 'cv.pdf',
        'attachment_mime' => 'application/pdf',
        'attachment_size' => 9,
    ]);

    $url = '/chats/'.$conversation->id.'/files/'.$message->id;

    $this->actingAs($employerUser)->get($url)->assertOk();
    $this->actingAs($applicantUser)->get($url)->assertOk();

    // посторонний работодатель
    $stranger = User::factory()->employer()->create();
    makeEmployer($stranger);
    $this->actingAs($stranger)->get($url)->assertForbidden();

    // гость
    auth()->logout();
    $this->get($url)->assertRedirect(route('login', absolute: false));
});

test('resume documents go to the private disk and respect hide_profile', function () {
    Storage::fake('local');

    $ownerUser = User::factory()->applicant()->create();
    $owner = makeApplicant($ownerUser);
    Storage::disk('local')->put('resumes/cv.docx', 'содержимое');
    $resume = makeResume($owner, 'resumes/cv.docx');

    $url = '/resume/'.$resume->id.'/document';

    $viewer = User::factory()->employer()->create();

    $this->actingAs($ownerUser)->get($url)->assertOk();
    $this->actingAs($viewer)->get($url)->assertOk();

    // анкета скрыта — документ закрывается вместе с ней, но владелец его видит
    UserSetting::updateOrCreate(['user_id' => $ownerUser->id], ['hide_profile' => true]);

    $this->actingAs($viewer)->get($url)->assertNotFound();
    $this->actingAs($ownerUser)->get($url)->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
});

test('resume uploads no longer reach the public disk', function () {
    Storage::fake('local');
    Storage::fake('public');

    $user = User::factory()->applicant()->create();
    makeApplicant($user);

    $this->actingAs($user)->post('/applicant/resume', [
        'profession' => 'PHP-разработчик',
        'experience_years' => 3,
        'desired_position' => 'Backend',
        'desired_salary' => 5000,
        'languages' => 'русский',
        'documents' => UploadedFile::fake()->create('cv.docx', 20, 'application/msword'),
    ])->assertRedirect();

    $resume = App\Models\Resume::latest('id')->first();

    expect($resume->documents)->toStartWith('resumes/');
    expect(Storage::disk('local')->exists($resume->documents))->toBeTrue();
    expect(Storage::disk('public')->exists($resume->documents))->toBeFalse();
});
