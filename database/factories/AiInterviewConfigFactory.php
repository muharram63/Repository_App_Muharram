<?php

namespace Database\Factories;

use App\Models\AiInterviewConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiInterviewConfig>
 */
class AiInterviewConfigFactory extends Factory
{
    /**
     * По умолчанию собеседование включено и настройки непротиворечивы: в
     * тестах почти всегда нужна рабочая вакансия, а выключенное состояние
     * запрашивается явно через disabled().
     */
    public function definition(): array
    {
        return [
            'enabled' => true,
            'level' => 'middle',
            'language' => 'ru',
            'questions_count' => 6,
            'weight_documents' => 30,
            'weight_interview' => 45,
            'weight_test' => 25,
            'threshold_reject' => 45,
            'threshold_accept' => 70,
            'test_time_limit_minutes' => 60,
            'response_sla' => 'в течение 3 дней',
            'decision_mode' => 'auto',
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['enabled' => false]);
    }

    /**
     * ИИ считает и пишет отчёт, а статус отклика ставит работодатель.
     */
    public function advisory(): static
    {
        return $this->state(fn () => ['decision_mode' => 'advisory']);
    }

    /**
     * Веса, не дающие сотню, — состояние, которое приложение должно отвергать.
     */
    public function brokenWeights(): static
    {
        return $this->state(fn () => [
            'weight_documents' => 50,
            'weight_interview' => 50,
            'weight_test' => 50,
        ]);
    }
}
