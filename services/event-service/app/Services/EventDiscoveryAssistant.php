<?php

namespace App\Services;

use Throwable;

class EventDiscoveryAssistant
{
    public function __construct(
        private readonly AuthServiceClient $authServiceClient,
        private readonly EventAssistantCandidateResolver $candidateResolver,
        private readonly EventDiscoveryQueryAnalyzer $queryAnalyzer,
        private readonly EventConciergeNarrator $narrator,
    ) {
    }

    /**
     * @return array{
     *     query: string,
     *     answer: string,
     *     mode: string,
     *     candidate_source: string,
     *     personalized: bool,
     *     context: array{intent_label: string|null, timeframe_label: string|null},
     *     items: array<int, array<string, mixed>>
     * }
     */
    public function reply(string $query, ?string $token = null, int $resultLimit = 4): array
    {
        $analysis = $this->queryAnalyzer->analyze($query);
        $authUser = $this->resolveOptionalAuthUser($token);
        $candidatePayload = $this->candidateResolver->resolve(
            analysis: $analysis,
            userId: $authUser['id'] ?? null,
            token: $token,
        );
        $rankedCandidates = $candidatePayload['items'];
        $narration = $this->narrator->narrate($query, $analysis, $rankedCandidates);
        $items = $this->mergeNarrationWithCandidates(
            narrationItems: $narration['items'],
            rankedCandidates: $rankedCandidates,
            resultLimit: $resultLimit,
        );

        return [
            'query' => $query,
            'answer' => $narration['answer'],
            'mode' => $narration['mode'],
            'candidate_source' => $candidatePayload['candidate_source'],
            'personalized' => isset($authUser['id']),
            'context' => [
                'intent_label' => $analysis['intent_label'],
                'timeframe_label' => $analysis['timeframe_label'],
            ],
            'items' => $items,
        ];
    }

    /**
     * @param  array<int, array{event_id: int, assistant_note: string}>  $narrationItems
     * @param  array<int, array<string, mixed>>  $rankedCandidates
     * @return array<int, array<string, mixed>>
     */
    private function mergeNarrationWithCandidates(array $narrationItems, array $rankedCandidates, int $resultLimit): array
    {
        $candidateMap = collect($rankedCandidates)
            ->keyBy(fn (array $candidate) => (int) $candidate['id']);

        $items = collect($narrationItems)
            ->map(function (array $item) use ($candidateMap): ?array {
                $candidate = $candidateMap->get($item['event_id']);

                if (! is_array($candidate)) {
                    return null;
                }

                $candidate['assistant_note'] = $item['assistant_note'];

                return $candidate;
            })
            ->filter()
            ->unique('id')
            ->take($resultLimit)
            ->values();

        if ($items->count() >= $resultLimit || $candidateMap->isEmpty()) {
            return $items->all();
        }

        $fallbackItems = $candidateMap
            ->reject(fn (array $candidate, int $eventId) => $items->contains('id', $eventId))
            ->take($resultLimit - $items->count())
            ->map(function (array $candidate): array {
                $candidate['assistant_note'] = 'Взял этот вариант как один из самых сильных по живой подборке и ближайшим сеансам.';

                return $candidate;
            })
            ->values();

        return $items
            ->concat($fallbackItems)
            ->take($resultLimit)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveOptionalAuthUser(?string $token): ?array
    {
        if (! $token) {
            return null;
        }

        try {
            $user = $this->authServiceClient->getCurrentUser($token);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($user) || (int) ($user['status'] ?? 0) !== 1) {
            return null;
        }

        return $user;
    }
}
