<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class EventDiscoveryQueryAnalyzer
{
    /**
     * @return array{
     *     normalized_query: string,
     *     intent: string|null,
     *     intent_label: string|null,
     *     timeframe_label: string|null,
     *     reference_date: string,
     *     start_date: string|null,
     *     end_date: string|null,
     *     preferred_categories: array<int, string>,
     *     preferred_tags: array<int, string>
     * }
     */
    public function analyze(string $query): array
    {
        $normalizedQuery = $this->normalize($query);
        $now = CarbonImmutable::now();
        $timeframe = $this->detectTimeframe($normalizedQuery, $now);
        $intent = $this->detectIntent($normalizedQuery);

        return [
            'normalized_query' => $normalizedQuery,
            'intent' => $intent['key'],
            'intent_label' => $intent['label'],
            'timeframe_label' => $timeframe['label'],
            'reference_date' => ($timeframe['reference'] ?? $now)->toISOString(),
            'start_date' => isset($timeframe['start']) ? $timeframe['start']->toDateString() : null,
            'end_date' => isset($timeframe['end']) ? $timeframe['end']->toDateString() : null,
            'preferred_categories' => $intent['categories'],
            'preferred_tags' => $intent['tags'],
        ];
    }

    private function normalize(string $query): string
    {
        $query = mb_strtolower($query);
        $query = preg_replace('/\s+/u', ' ', $query) ?? $query;

        return trim($query);
    }

    /**
     * @return array{
     *     key: string|null,
     *     label: string|null,
     *     categories: array<int, string>,
     *     tags: array<int, string>
     * }
     */
    private function detectIntent(string $query): array
    {
        $definitions = [
            [
                'key' => 'date',
                'label' => 'свидание',
                'matches' => ['свидан', 'девушк', 'парн', 'романт', 'парой'],
                'categories' => ['theater', 'concert', 'cinema'],
                'tags' => ['романтика', 'jazz', 'acoustic', 'театр', 'вечер'],
            ],
            [
                'key' => 'friends',
                'label' => 'компания друзей',
                'matches' => ['друз', 'компани', 'тусов', 'потанцев', 'вечерин'],
                'categories' => ['concert', 'festival', 'standup'],
                'tags' => ['party', 'dance', 'rock', 'comedy', 'electronic'],
            ],
            [
                'key' => 'family',
                'label' => 'семейный выход',
                'matches' => ['семь', 'ребен', 'дет', 'родител'],
                'categories' => ['theater', 'cinema', 'lecture'],
                'tags' => ['family', 'kids', 'children', 'classic', 'show'],
            ],
            [
                'key' => 'calm',
                'label' => 'спокойный культурный вечер',
                'matches' => ['спокойн', 'культур', 'уютн', 'атмосфер'],
                'categories' => ['theater', 'lecture', 'cinema'],
                'tags' => ['culture', 'art', 'classic', 'theater'],
            ],
        ];

        foreach ($definitions as $definition) {
            foreach ($definition['matches'] as $match) {
                if (Str::contains($query, $match)) {
                    return [
                        'key' => $definition['key'],
                        'label' => $definition['label'],
                        'categories' => $definition['categories'],
                        'tags' => $definition['tags'],
                    ];
                }
            }
        }

        return [
            'key' => null,
            'label' => null,
            'categories' => [],
            'tags' => [],
        ];
    }

    /**
     * @return array{
     *     label: string|null,
     *     reference?: CarbonImmutable,
     *     start?: CarbonImmutable,
     *     end?: CarbonImmutable
     * }
     */
    private function detectTimeframe(string $query, CarbonImmutable $now): array
    {
        if (Str::contains($query, ['сегодня', 'сегодняшн', 'сегодня вечером'])) {
            return [
                'label' => 'сегодня',
                'reference' => $now,
                'start' => $now->startOfDay(),
                'end' => $now->endOfDay(),
            ];
        }

        if (Str::contains($query, ['завтра'])) {
            $tomorrow = $now->addDay();

            return [
                'label' => 'завтра',
                'reference' => $tomorrow,
                'start' => $tomorrow->startOfDay(),
                'end' => $tomorrow->endOfDay(),
            ];
        }

        if (Str::contains($query, ['на следующей неделе', 'следующую неделю'])) {
            $nextWeekStart = $now->addWeek()->startOfWeek();

            return [
                'label' => 'на следующей неделе',
                'reference' => $nextWeekStart,
                'start' => $nextWeekStart,
                'end' => $nextWeekStart->endOfWeek(),
            ];
        }

        if (Str::contains($query, ['на выходных', 'в выходные', 'эти выходные'])) {
            $weekendStart = $now->startOfWeek()->addDays(5);

            if ($now->greaterThan($weekendStart->endOfDay())) {
                $weekendStart = $weekendStart->addWeek();
            }

            return [
                'label' => 'на выходных',
                'reference' => $weekendStart,
                'start' => $weekendStart,
                'end' => $weekendStart->addDay()->endOfDay(),
            ];
        }

        if (Str::contains($query, ['вечером', 'сегодня вечером'])) {
            return [
                'label' => 'сегодня вечером',
                'reference' => $now,
                'start' => $now->setTime(17, 0),
                'end' => $now->endOfDay(),
            ];
        }

        return [
            'label' => null,
            'reference' => $now,
        ];
    }
}
