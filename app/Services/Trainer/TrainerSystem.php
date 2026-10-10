<?php

namespace App\Services\Trainer;

use App\Models\Category;
use App\Models\City;
use App\Models\Employer;
use App\Models\Industry;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Служебный аккаунт «Тренажёр Workio».
 *
 * Тренировка идёт на готовом движке ИИ-собеседования, а у собеседования
 * обязательно есть вакансия, и у кандидата — одно собеседование на вакансию.
 * Поэтому каждой тренировке — своя скрытая учебная вакансия, и все они
 * принадлежат этому служебному работодателю. Ни один настоящий работодатель
 * их не видит: в отчёт по вакансии пускают только её владельца, а войти в
 * служебный аккаунт нельзя — пароль случайный и нигде не хранится.
 *
 * Уведомления служебному работодателю выключены все: о «новых кандидатах» и
 * «готовых отчётах» тренировок ему знать незачем, а почта по фиктивному
 * адресу ушла бы в никуда.
 */
class TrainerSystem
{
    /** Служебный работодатель — создаётся при первой тренировке. */
    public static function employer(): Employer
    {
        $user = User::where('email', config('trainer.system_email'))->first();

        if (! $user) {
            $user = new User([
                'name' => 'Тренажёр Workio',
                'email' => config('trainer.system_email'),
                'password' => Hash::make(Str::random(48)),
            ]);
            // роль и статус вне массового заполнения — ставим явно
            $user->role = 'employer';
            $user->save();

            UserSetting::updateOrCreate(['user_id' => $user->id], [
                'notify_new_candidates' => false, 'notify_reports' => false, 'notify_reminders' => false,
                'channel_site' => false, 'channel_browser' => false, 'channel_email' => false,
            ]);
        }

        $employer = Employer::where('user_id', $user->id)->first() ?? Employer::create([
            'user_id' => $user->id,
            'company_name' => 'Тренажёр Workio',
            'job' => 'Служебный аккаунт тренажёра собеседований',
            'email_company' => $user->email,
            'phone' => '—',
            'category_id' => self::category()->id,
            'description' => 'Учебные вакансии тренажёра собеседований. Не публикуются.',
            'city_id' => self::city()->id,
            'industry_id' => self::industry()->id,
            'website_url' => '—',
        ]);

        return $employer;
    }

    /** Номер служебного работодателя или null, если тренировок ещё не было. */
    public static function employerId(): ?int
    {
        $userId = self::userId();

        return $userId ? Employer::where('user_id', $userId)->value('id') : null;
    }

    public static function userId(): ?int
    {
        return User::where('email', config('trainer.system_email'))->value('id');
    }

    public static function city(): City
    {
        return City::orderBy('id')->first()
            ?? City::create(['country' => 'Таджикистан', 'region' => 'Душанбе']);
    }

    private static function category(): Category
    {
        return Category::orderBy('id')->first()
            ?? Category::create(['name' => 'Тренажёр', 'description' => 'Тренажёр собеседований', 'slug' => 'trainer']);
    }

    private static function industry(): Industry
    {
        return Industry::orderBy('id')->first()
            ?? Industry::create(['name' => 'Тренажёр', 'category_id' => self::category()->id, 'parent_id' => 0, 'description' => 'Тренажёр собеседований']);
    }
}
