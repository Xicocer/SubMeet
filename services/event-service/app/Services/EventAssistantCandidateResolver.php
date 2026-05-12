<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Throwable;

class EventAssistantCandidateResolver
{
    public function __construct(
        private readonly RecommendationServiceClient $recommendationServiceClient,
        private readonly EventRecommendationCatalogBuilder $catalogBuilder,
    ) {
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return array{
     *     candidate_source: string,
     *     items: array<int, array<string, mixed>>
     * }
     */
    public function resolve(array $analysis, ?int $userId, ?string $token): array
    {
        $candidateSource = 'recommendation_service';

        try {
            $candidates = $this->recommendationServiceClient->preview(
                userId: $userId,
                token: $token,
                preferredCategories: $analysis['preferred_categories'],
                preferredTags: $analysis['preferred_tags'],
                referenceDate: $analysis['reference_date'],
                limit: (int) config('services.concierge.candidate_limit', 12),
            );
        } catch (Throwable) {
            $candidateSource = 'catalog_fallback';
            $candidates = $this->catalogBuilder->buildPublishedRecommendationCatalog();
        }

        $items = $this->rankCandidates($candidates, $analysis);

        if ($items !== []) {
            return [
                'candidate_source' => $candidateSource,
                'items' => $items,
            ];
        }

        return [
            'candidate_source' => 'catalog_fallback',
            'items' => $this->rankCandidates(
                $this->catalogBuilder->buildPublishedRecommendationCatalog(),
                $analysis,
            ),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @param  array<string, mixed>  $analysis
     * @return array<int, array<string, mixed>>
     */
    private function rankCandidates(array $candidates, array $analysis): array
    {
        $startDate = isset($analysis['start_date']) && $analysis['start_date'] !== null
            ? Carbon::parse($analysis['start_date'])->startOfDay()
            : null;
        $endDate = isset($analysis['end_date']) && $analysis['end_date'] !== null
            ? Carbon::parse($analysis['end_date'])->endOfDay()
            : null;
        $preferredCategories = collect($analysis['preferred_categories'] ?? [])
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->values()
            ->all();
        $preferredTags = collect($analysis['preferred_tags'] ?? [])
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->values()
            ->all();

        return collect($candidates)
            ->map(function (array $candidate, int $index) use ($startDate, $endDate, $preferredCategories, $preferredTags): array {
                $score = (float) ($candidate['score'] ?? 0) + max(0, 0.6 - ($index * 0.03));
                $eventDate = isset($candidate['event_date']) && is_string($candidate['event_date']) && $candidate['event_date'] !== ''
                    ? Carbon::parse($candidate['event_date'])->startOfDay()
                    : null;
                $searchable = mb_strtolower(implode(' ', [
                    (string) ($candidate['title'] ?? ''),
                    (string) ($candidate['description'] ?? ''),
                    (string) ($candidate['category'] ?? ''),
                    (string) ($candidate['category_name'] ?? ''),
                    (string) ($candidate['tags'] ?? ''),
                    (string) ($candidate['hall_name'] ?? ''),
                    (string) ($candidate['venue_address'] ?? ''),
                ]));

                if ($startDate && $endDate && $eventDate) {
                    if ($eventDate->betweenIncluded($startDate, $endDate)) {
                        $score += 1.8;
                    } elseif ($eventDate->lessThan($startDate)) {
                        $score -= 0.6;
                    } else {
                        $score -= 0.2;
                    }
                }

                if (in_array((string) ($candidate['category'] ?? ''), $preferredCategories, true)) {
                    $score += 0.8;
                }

                foreach ($preferredTags as $preferredTag) {
                    if ($preferredTag !== '' && str_contains($searchable, mb_strtolower($preferredTag))) {
                        $score += 0.35;
                    }
                }

                $candidate['assistant_rank_score'] = round($score, 4);

                return $candidate;
            })
            ->sortByDesc('assistant_rank_score')
            ->values()
            ->all();
    }
}
