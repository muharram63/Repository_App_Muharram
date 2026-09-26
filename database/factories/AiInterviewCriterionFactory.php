<?php

namespace Database\Factories;

use App\Models\AiInterviewCriterion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiInterviewCriterion>
 */
class AiInterviewCriterionFactory extends Factory
{
    public function definition(): array
    {
        $label = fake()->randomElement(['PHP и Laravel', 'SQL', 'Работа в команде', 'Английский язык']);

        return [
            // ключ обязан быть уникальным в пределах вакансии, поэтому слаг
            // с хвостом: иначе два критерия в одном тесте упирались бы в unique
            'key' => Str::slug($label, '_').'_'.fake()->unique()->numberBetween(1, 9999),
            'label' => $label,
            'description' => 'Проверяется вопросами и тестовым заданием.',
            'kind' => 'must',
            'weight' => 20,
            'source' => 'ai',
            // по умолчанию подтверждён: неподтверждённое состояние в оценке
            // не участвует и запрашивается явно через pending()
            'confirmed_at' => now(),
            'position' => 0,
        ];
    }

    /**
     * Желательный критерий: влияет на балл, но воротами не является.
     */
    public function nice(): static
    {
        return $this->state(fn () => ['kind' => 'nice']);
    }

    /**
     * Предложен моделью, но работодатель ещё не подтвердил.
     */
    public function pending(): static
    {
        return $this->state(fn () => ['confirmed_at' => null]);
    }

    public function addedByEmployer(): static
    {
        return $this->state(fn () => ['source' => 'employer']);
    }
}
