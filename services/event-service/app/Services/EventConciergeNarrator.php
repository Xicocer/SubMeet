<?php

namespace App\Services;

use App\Ai\Agents\EventDiscoveryConciergeAgent;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

class EventConciergeNarrator
{
    /**
     * @param  array<string, mixed>  $analysis
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array{
     *     answer: string,
     *     mode: string,
     *     items: array<int, array{event_id: int, assistant_note: string}>
     * }
     */
    public function narrate(string $query, array $analysis, array $candidates): array
    {
        if ($candidates === []) {
            return [
                'answer' => 'Сейчас в каталоге нет подходящих живых событий под такой запрос. Попробуй изменить время или настроение, и я быстро соберу новую подборку.',
                'mode' => 'fallback',
                'items' => [],
            ];
        }

        if (! $this->isConfigured()) {
            return $this->fallback($query, $analysis, $candidates);
        }

        try {
            $response = (new EventDiscoveryConciergeAgent)->prompt(
                $this->buildPrompt($query, $analysis, $candidates),
                provider: config('services.concierge.provider'),
                model: config('services.concierge.model') ?: null,
                timeout: (int) config('services.concierge.timeout', 30),
            );

            $items = collect(Arr::get($response->toArray(), 'highlights', []))
                ->map(function (mixed $item): ?array {
                    if (! is_array($item)) {
                        return null;
                    }

                    $eventId = (int) ($item['event_id'] ?? 0);
                    $note = trim((string) ($item['note'] ?? ''));

                    if ($eventId < 1 || $note === '') {
                        return null;
                    }

                    return [
                        'event_id' => $eventId,
                        'assistant_note' => Str::limit($note, 160),
                    ];
                })
                ->filter()
                ->values()
                ->all();

            $answer = trim((string) Arr::get($response->toArray(), 'answer', ''));

            if ($answer === '' || $items === []) {
                return $this->fallback($query, $analysis, $candidates);
            }

            return [
                'answer' => $answer,
                'mode' => 'ai',
                'items' => $items,
            ];
        } catch (Throwable) {
            return $this->fallback($query, $analysis, $candidates);
        }
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array{
     *     answer: string,
     *     mode: string,
     *     items: array<int, array{event_id: int, assistant_note: string}>
     * }
     */
    private function fallback(string $query, array $analysis, array $candidates): array
    {
        $intent = $analysis['intent_label'] ?? null;
        $timeframe = $analysis['timeframe_label'] ?? null;
        $intro = 'Я собрал живую подборку из уже опубликованных событий';

        if (is_string($intent) && $intent !== '') {
            $intro .= ' под формат "' . $intent . '"';
        }

        if (is_string($timeframe) && $timeframe !== '') {
            $intro .= ' ' . $timeframe;
        }

        $answer = $intro . '. Сначала показываю самые близкие по времени и настроению варианты, чтобы тебе не приходилось заново искать их в каталоге.';

        $items = collect(array_slice($candidates, 0, 4))
            ->map(function (array $candidate): array {
                $category = (string) ($candidate['category_name'] ?? $candidate['category'] ?? 'событие');
                $date = (string) ($candidate['event_date'] ?? '');
                $price = (int) ($candidate['price'] ?? 0);

                $note = 'Хороший вариант в категории "' . $category . '"';

                if ($date !== '') {
                    $note .= ', ближайшая дата ' . $date;
                }

                if ($price > 0) {
                    $note .= ', от ' . $price . ' ₽';
                }

                return [
                    'event_id' => (int) $candidate['id'],
                    'assistant_note' => Str::limit($note . '.', 160),
                ];
            })
            ->all();

        return [
            'answer' => $answer,
            'mode' => 'fallback',
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @param  array<int, array<string, mixed>>  $candidates
     */
    private function buildPrompt(string $query, array $analysis, array $candidates): string
    {
        $summary = [
            'user_query' => $query,
            'intent_label' => $analysis['intent_label'] ?? null,
            'timeframe_label' => $analysis['timeframe_label'] ?? null,
            'preferred_categories' => $analysis['preferred_categories'] ?? [],
            'preferred_tags' => $analysis['preferred_tags'] ?? [],
            'candidates' => array_map(function (array $candidate): array {
                return [
                    'id' => (int) ($candidate['id'] ?? 0),
                    'title' => (string) ($candidate['title'] ?? ''),
                    'category_name' => $candidate['category_name'] ?? $candidate['category'] ?? null,
                    'event_date' => $candidate['event_date'] ?? null,
                    'price' => $candidate['price'] ?? null,
                    'age_rating' => $candidate['age_rating'] ?? null,
                    'city' => $candidate['city'] ?? null,
                    'venue_address' => $candidate['venue_address'] ?? null,
                    'hall_name' => $candidate['hall_name'] ?? null,
                    'tags' => $candidate['tags'] ?? '',
                    'description' => Str::limit((string) ($candidate['description'] ?? ''), 240),
                ];
            }, array_slice($candidates, 0, 8)),
        ];

        return "Подбери лучшую короткую рекомендацию для пользователя.\n"
            . "Верни только события из списка кандидатов.\n"
            . "Данные:\n"
            . json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function isConfigured(): bool
    {
        $provider = (string) config('services.concierge.provider', 'openai');
        $providerConfig = config('ai.providers.' . $provider, []);

        if ($provider === 'ollama') {
            return filled($providerConfig['url'] ?? null);
        }

        return filled($providerConfig['key'] ?? null);
    }
}
