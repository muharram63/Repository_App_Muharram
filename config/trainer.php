<?php

/*
 * Тренажёр собеседований.
 *
 * daily_limit — не больше стольких тренировок в сутки на одного пользователя
 * (TRAINER_DAILY_LIMIT в .env). Тренировка — полтора-два десятка обращений к
 * модели; без предела один человек за вечер исчерпал бы суточную квоту ключа.
 *
 * system_email — служебный аккаунт «Тренажёр Workio», которому принадлежат
 * скрытые учебные вакансии. Войти в него нельзя: пароль случайный и нигде не
 * хранится.
 */
return [
    'daily_limit' => (int) env('TRAINER_DAILY_LIMIT', 3),

    'system_email' => env('TRAINER_SYSTEM_EMAIL', 'trainer@workio.system'),

    // уровни: сколько вопросов и минут на задание
    'levels' => [
        'junior' => ['label' => 'Junior', 'questions' => 5, 'minutes' => 30, 'experience' => 'not'],
        'middle' => ['label' => 'Middle', 'questions' => 6, 'minutes' => 40, 'experience' => '3_years'],
        'senior' => ['label' => 'Senior', 'questions' => 7, 'minutes' => 45, 'experience' => '3-6years'],
    ],
];
