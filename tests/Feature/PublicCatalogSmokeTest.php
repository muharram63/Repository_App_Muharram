<?php

test('public catalogs render', function () {
    makeVacancy(makeEmployer(App\Models\User::factory()->employer()->create()));
    makeResume(makeApplicant(App\Models\User::factory()->applicant()->create()));

    $this->get('/')->assertOk();
    $this->get('/vacancies')->assertOk();
    $this->get('/resume')->assertOk();
    $this->get('/companies')->assertOk();
});
