<?php

namespace Database\Factories;

use App\Models\AiInterview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiInterview>
 */
class AiInterviewFactory extends Factory
{
    /**
     * Вакансию и соискателя фабрика не создаёт: их сборка требует цепочки
     * справочников, и в тестах она уже есть в помощниках makeVacancy() и
     * makeApplicant(). Здесь — только состояние самого собеседования.
     */
    public function definition(): array
    {
        return [
            'stage' => 'consent',
            'follow_up_round' => 0,
            'requires_review' => false,
        ];
    }

    /**
     * Согласие дано — с этого момента процесс вообще может идти.
     */
    public function consented(): static
    {
        return $this->state(fn () => [
            'stage' => 'documents',
            'consent_at' => now(),
            'consent_ip' => '127.0.0.1',
        ]);
    }

    /**
     * Дошёл до собеседования.
     */
    public function atInterview(): static
    {
        return $this->consented()->state(fn () => ['stage' => 'interview']);
    }

    /**
     * Дошёл до тестового задания.
     */
    public function atTest(): static
    {
        return $this->consented()->state(fn () => [
            'stage' => 'test',
            'documents_score' => 80,
            'interview_score' => 72,
        ]);
    }

    /**
     * Решение принято.
     */
    public function decided(string $outcome = 'passed', int $total = 78): static
    {
        return $this->consented()->state(fn () => [
            'stage' => 'done',
            'documents_score' => 80,
            'interview_score' => 75,
            'test_score' => 74,
            'total_score' => $total,
            'outcome' => $outcome,
            'requires_review' => $outcome === 'manual_review',
            'decided_at' => now(),
        ]);
    }
}
