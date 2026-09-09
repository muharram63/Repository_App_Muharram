<?php

namespace App\Http\Controllers;

use App\Models\MatchAnalysis;
use App\Models\Resume;
use App\Models\Vacancy;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\MatchAnalyst;
use Illuminate\Http\JsonResponse;

/**
 * Разбор совпадения кандидата и вакансии.
 *
 * Страница запрашивает его отдельно, уже после отрисовки: обращение к модели
 * занимает секунды, и держать из-за него саму вакансию было бы неправильно.
 */
class MatchController extends Controller
{
    public function __construct(private readonly MatchAnalyst $analyst)
    {
    }

    public function show(Vacancy $vacancy, Resume $resume): JsonResponse
    {
        $this->authorizePair($vacancy, $resume);

        // готовый разбор отдаём сразу: пересчитывать нечего, тексты те же
        if ($cached = MatchAnalysis::cached($vacancy, $resume)) {
            return response()->json([
                'analysis' => $cached->payload,
                'updated_at' => $cached->updated_at->format('d.m.Y H:i'),
            ]);
        }

        if (! $this->analyst->available()) {
            return response()->json([
                'error' => 'Разбор недоступен: в .env не задан GEMINI_API_KEY.',
            ], 503);
        }

        try {
            $analysis = $this->analyst->analyse($vacancy, $resume);
        } catch (AiUnavailableException $e) {
            return response()->json(array_filter([
                'error' => $e->getMessage(),
                'retry_after' => $e->retryAfter,
            ]), 503);
        }

        $stored = MatchAnalysis::remember($vacancy, $resume, $analysis);

        return response()->json([
            'analysis' => $analysis,
            'updated_at' => $stored->updated_at->format('d.m.Y H:i'),
        ]);
    }

    /**
     * Разбор видят только две стороны: владелец резюме и владелец вакансии.
     * Иначе по чужой паре можно было бы собирать сведения о кандидатах.
     */
    private function authorizePair(Vacancy $vacancy, Resume $resume): void
    {
        $user = auth()->user();

        $isCandidate = $user->applicant && $resume->applicant_id === $user->applicant->id;
        $isEmployer = $user->employer && $vacancy->employer_id === $user->employer->id;

        abort_if(! $isCandidate && ! $isEmployer, 403);
    }
}
