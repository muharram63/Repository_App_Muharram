<?php

use App\Models\AdminComment;
use App\Models\Applicant;
use App\Models\Complaint;
use App\Models\Employer;
use App\Models\Resume;
use App\Models\User;
use App\Models\Vacancy;

test('an admin cannot delete anything that belongs to an employer or an applicant', function () {
    $admin = User::factory()->admin()->create();

    $employerUser = User::factory()->employer()->create();
    $employer = makeEmployer($employerUser);
    $vacancy = makeVacancy($employer);

    $applicantUser = User::factory()->applicant()->create();
    $applicant = makeApplicant($applicantUser);
    $resume = makeResume($applicant);

    // маршрутов удаления чужого имущества больше нет
    $this->actingAs($admin)->delete('/superadmin/vacancy/'.$vacancy->id)->assertNotFound();
    $this->actingAs($admin)->delete('/superadmin/companies/'.$employer->id)->assertNotFound();
    $this->actingAs($admin)->delete('/superadmin/resumes/'.$resume->id)->assertNotFound();
    $this->actingAs($admin)->delete('/superadmin/applicants/'.$applicant->id)->assertNotFound();

    expect(Vacancy::find($vacancy->id))->not->toBeNull();
    expect(Employer::find($employer->id))->not->toBeNull();
    expect(Resume::find($resume->id))->not->toBeNull();
    expect(Applicant::find($applicant->id))->not->toBeNull();
});

test('the admin lists no longer offer a delete button', function () {
    $admin = User::factory()->admin()->create();

    $employerUser = User::factory()->employer()->create();
    $employer = makeEmployer($employerUser);
    $vacancy = makeVacancy($employer);

    $applicantUser = User::factory()->applicant()->create();
    makeResume(makeApplicant($applicantUser));

    foreach (['/superadmin/vacancy', '/superadmin/companies', '/superadmin/resumes', '/superadmin/applicants'] as $uri) {
        $this->actingAs($admin)->get($uri)->assertOk()->assertDontSee('Удалить');
    }

    // карточки объектов тоже открываются и тоже без удаления
    $this->actingAs($admin)->get('/superadmin/vacancy/'.$vacancy->id.'/show')
        ->assertOk()->assertDontSee('Удалить');
    $this->actingAs($admin)->get('/superadmin/companies/'.$employer->id.'/show')
        ->assertOk()->assertDontSee('Удалить компанию');
});

test('an admin still blocks an account from the user card', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->applicant()->create();

    $this->actingAs($admin)
        ->patch('/superadmin/users/'.$user->id, ['status' => 'blocked'])
        ->assertRedirect();

    expect($user->fresh()->status)->toBe('blocked');

    $this->actingAs($admin)
        ->patch('/superadmin/users/'.$user->id, ['status' => 'active'])
        ->assertRedirect();

    expect($user->fresh()->status)->toBe('active');
});

test('an admin still deletes a user, a complaint and a support message', function () {
    $admin = User::factory()->admin()->create();

    $user = User::factory()->applicant()->create();
    makeApplicant($user);

    $reporter = User::factory()->employer()->create();
    $employer = makeEmployer($reporter);
    $vacancy = makeVacancy($employer);

    $complaint = Complaint::create([
        'user_id' => $user->id,
        'target_type' => 'vacancy',
        'target_id' => $vacancy->id,
        'reason' => 'fake',
    ]);

    $comment = AdminComment::create([
        'user_id' => $user->id,
        'topic' => 'question',
        'body' => 'Достаточно длинный текст обращения.',
    ]);

    $this->actingAs($admin)->delete('/superadmin/complaints/'.$complaint->id)->assertRedirect();
    expect(Complaint::find($complaint->id))->toBeNull();

    $this->actingAs($admin)->delete('/superadmin/comments/'.$comment->id)->assertRedirect();
    expect(AdminComment::find($comment->id))->toBeNull();

    $this->actingAs($admin)->delete('/superadmin/users/'.$user->id)->assertRedirect();
    expect(User::find($user->id))->toBeNull();
});

test('the owner still decides the fate of a complaint', function () {
    $ownerUser = User::factory()->employer()->create();
    $employer = makeEmployer($ownerUser);
    $vacancy = makeVacancy($employer);

    $reporter = User::factory()->applicant()->create();
    makeApplicant($reporter);

    $complaint = Complaint::create([
        'user_id' => $reporter->id,
        'target_type' => 'vacancy',
        'target_id' => $vacancy->id,
        'reason' => 'fake',
    ]);

    // администратор заблокировал владельца — статус жалобы не изменился
    $this->actingAs(User::factory()->admin()->create())
        ->post('/superadmin/complaints/'.$complaint->id.'/act', ['action' => 'block_user']);

    expect($complaint->fresh()->status)->toBe('new');

    // а владелец объекта его ставит сам
    $ownerUser->status = 'active';
    $ownerUser->save();

    $this->actingAs($ownerUser)
        ->post('/complaints/'.$complaint->id.'/status', ['status' => 'resolved'])
        ->assertRedirect();

    expect($complaint->fresh()->status)->toBe('resolved');
});
