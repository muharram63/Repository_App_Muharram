<?php

/*
 * Цифры на главной и в статистике совпадают со списками, которые
 * открываются по клику: скрытые и заблокированные компании не считаются.
 */

use App\Models\User;
use App\Models\UserSetting;
use App\Support\PublicNumbers;

test('скрытые и заблокированные компании и их вакансии в счётчики не попадают', function () {
    $visible = makeEmployer(User::factory()->employer()->create());
    makeVacancy($visible);

    $hiddenUser = User::factory()->employer()->create();
    $hidden = makeEmployer($hiddenUser);
    UserSetting::updateOrCreate(['user_id' => $hiddenUser->id], ['hide_company' => true]);

    $blockedUser = User::factory()->employer()->create();
    $blocked = makeEmployer($blockedUser);
    makeVacancy($blocked);
    $blockedUser->forceFill(['status' => 'blocked'])->save();

    $this->get(route('public.home'))
        ->assertOk()
        ->assertViewHas('companiesCount', 1)
        ->assertViewHas('activeVacancies', 1);

    expect(PublicNumbers::all()['headline']['companies'])->toBe(1);
});
