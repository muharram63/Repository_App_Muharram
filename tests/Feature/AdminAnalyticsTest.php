<?php

use App\Models\User;

// ===== D5 =====
test('the analytics page renders on a non-mysql database', function () {
    $employerUser = User::factory()->employer()->create();
    $employer = makeEmployer($employerUser);
    makeVacancy($employer);

    $applicant = makeApplicant(User::factory()->applicant()->create());
    makeResume($applicant);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/superadmin/analytics')
        ->assertOk();
});
