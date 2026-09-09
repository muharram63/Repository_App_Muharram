<?php

use App\Models\Complaint;
use App\Models\User;
use App\Models\UserSetting;

test('a resume leaves the catalog only by the owner privacy setting', function () {
    $applicantUser = User::factory()->applicant()->create();
    $applicant = makeApplicant($applicantUser);

    $resume = makeResume($applicant);

    $this->get('/resume/'.$resume->id)->assertOk();
    $this->get('/resume')->assertOk()->assertSee('resume/'.$resume->id);

    // единственный способ спрятаться — собственная настройка приватности
    UserSetting::updateOrCreate(['user_id' => $applicantUser->id], ['hide_profile' => true]);

    $this->get('/resume/'.$resume->id)->assertNotFound();
    $this->get('/resume')->assertOk()->assertDontSee('resume/'.$resume->id);

    // владелец своё резюме видит всегда
    $this->actingAs($applicantUser)->get('/resume/'.$resume->id)->assertOk();
});

test('an admin cannot hide a resume through a complaint', function () {
    $applicantUser = User::factory()->applicant()->create();
    $resume = makeResume(makeApplicant($applicantUser));

    $reporter = User::factory()->employer()->create();
    makeEmployer($reporter);

    $complaint = Complaint::create([
        'user_id' => $reporter->id,
        'target_type' => 'resume',
        'target_id' => $resume->id,
        'reason' => 'spam',
    ]);

    // администратору доступна только блокировка владельца
    expect($complaint->allowedActions())->toBe(['block_user']);

    $this->actingAs(User::factory()->admin()->create())
        ->post('/superadmin/complaints/'.$complaint->id.'/act', ['action' => 'hide_resume'])
        ->assertSessionHasErrors('action');

    // резюме осталось в каталоге
    $this->get('/resume/'.$resume->id)->assertOk();
});
