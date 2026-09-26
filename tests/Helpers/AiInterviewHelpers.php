<?php

/*
|--------------------------------------------------------------------------
| Помощники модуля ИИ-собеседования
|--------------------------------------------------------------------------
|
| Живут отдельным файлом, а не в Pest.php: тот и так на сотню строк, а
| помощники модуля будут расти вместе с ним. Подключается из Pest.php.
|
| Собеседование опирается на существующие сущности площадки — вакансию,
| анкету соискателя и отклик, — поэтому помощники надстраиваются над уже
| готовыми makeEmployer() / makeApplicant() / makeVacancy().
*/

use App\Models\AiInterview;
use App\Models\AiInterviewConfig;
use App\Models\AiInterviewCriterion;
use App\Models\Applicant;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyResponse;

/**
 * Вакансия с включённым ИИ-собеседованием и подтверждёнными критериями.
 *
 * Пустой массив значит «вакансия без критериев», null — «дай один по
 * умолчанию». Разница важна: на ?: пустой массив молча превращался бы в
 * критерий php, и тесты про уникальность ключа падали бы не там, где нужно.
 *
 * @param  array<int,array{key?:string,label?:string,kind?:string,weight?:int}>|null  $criteria
 * @return array{0:Vacancy,1:AiInterviewConfig,2:User}
 */
function makeAiVacancy(?array $criteria = null, array $config = []): array
{
    $employerUser = User::factory()->employer()->create();
    $vacancy = makeVacancy(makeEmployer($employerUser));

    $setup = AiInterviewConfig::factory()->create(['vacancy_id' => $vacancy->id] + $config);

    foreach ($criteria ?? [['key' => 'php', 'label' => 'PHP', 'weight' => 100]] as $one) {
        AiInterviewCriterion::factory()->create(['vacancy_id' => $vacancy->id] + $one);
    }

    return [$vacancy, $setup, $employerUser];
}

/**
 * Кандидат, откликнувшийся на вакансию, и его собеседование.
 *
 * @return array{0:AiInterview,1:Applicant,2:User}
 */
function makeAiInterview(?Vacancy $vacancy = null, array $state = []): array
{
    $vacancy ??= makeAiVacancy()[0];

    $applicantUser = User::factory()->applicant()->create();
    $applicant = makeApplicant($applicantUser);
    makeResume($applicant);

    $response = VacancyResponse::create([
        'applicant_id' => $applicant->id,
        'vacancy_id' => $vacancy->id,
    ]);

    $interview = AiInterview::factory()->create([
        'vacancy_id' => $vacancy->id,
        'applicant_id' => $applicant->id,
        'vacancy_response_id' => $response->id,
    ] + $state);

    return [$interview, $applicant, $applicantUser];
}

/**
 * Сырое значение колонки, минуя Eloquent и его касты.
 *
 * Нужно, чтобы доказать: в базе лежит шифротекст, а не открытые данные.
 * Через модель этого не увидеть — она расшифрует по пути.
 */
function rawColumn(string $table, int $id, string $column): ?string
{
    $row = \Illuminate\Support\Facades\DB::table($table)->where('id', $id)->first([$column]);

    return $row?->{$column};
}
