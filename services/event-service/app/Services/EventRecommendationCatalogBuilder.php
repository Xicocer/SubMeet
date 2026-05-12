<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Tag;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;

class EventRecommendationCatalogBuilder
{
    public function __construct(
        private readonly HallServiceClient $hallServiceClient,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildPublishedRecommendationCatalog(): array
    {
        $events = Event::query()
            ->published()
            ->with([
                'category:id,name,slug',
                'ageRating:id,label,min_age',
                'tags:id,name,slug',
            ])
            ->get();

        $availableSessions = EventSession::query()
            ->available()
            ->whereIn('event_id', $events->pluck('id')->all())
            ->orderBy('start_time')
            ->get()
            ->groupBy('event_id');

        $hallMap = $this->loadHallMap(
            $availableSessions
                ->flatten()
                ->pluck('hall_id')
                ->all()
        );

        return $events
            ->map(function (Event $event) use ($availableSessions, $hallMap): ?array {
                /** @var Collection<int, EventSession> $eventSessions */
                $eventSessions = $availableSessions->get($event->id, collect());
                /** @var EventSession|null $nextSession */
                $nextSession = $eventSessions->first();

                if ($nextSession === null) {
                    return null;
                }

                $hall = $hallMap[$nextSession->hall_id] ?? null;
                $capacityTotal = (int) data_get($hall, 'capacities.total', 0);

                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'category' => $event->category?->slug ?? 'event',
                    'category_name' => $event->category?->name,
                    'tags' => $this->buildTags($event),
                    'description' => (string) ($event->description ?? ''),
                    'poster_url' => $event->poster_url,
                    'age_rating' => (int) ($event->ageRating?->min_age ?? 0),
                    'price' => (int) round((float) $nextSession->base_price),
                    'event_date' => $nextSession->start_time?->toDateString(),
                    'status' => $event->status,
                    'city' => $this->extractCityFromAddress((string) ($hall['address'] ?? '')),
                    'venue_address' => $hall['address'] ?? null,
                    'available_tickets' => $capacityTotal > 0 ? $capacityTotal : 1,
                    'next_session_id' => $nextSession->id,
                    'hall_id' => $nextSession->hall_id,
                    'hall_name' => $hall['name'] ?? null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $hallIds
     * @return array<int, array<string, mixed>>
     */
    private function loadHallMap(array $hallIds): array
    {
        $hallMap = [];

        try {
            foreach (array_unique($hallIds) as $hallId) {
                $hall = $this->hallServiceClient->getHall((int) $hallId);

                if ($hall !== null) {
                    $hallMap[(int) $hallId] = $hall;
                }
            }
        } catch (ConnectionException) {
            return [];
        }

        return $hallMap;
    }

    private function buildTags(Event $event): string
    {
        $tags = $event->tags
            ->flatMap(fn (Tag $tag) => [$tag->slug, $tag->name])
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => trim(mb_strtolower($value)))
            ->unique()
            ->take(12)
            ->values();

        if ($tags->isNotEmpty()) {
            return $tags->implode(' ');
        }

        return collect([$event->category?->slug, $event->category?->name])
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => trim(mb_strtolower($value)))
            ->unique()
            ->implode(' ');
    }

    private function extractCityFromAddress(string $address): ?string
    {
        $address = trim($address);

        if ($address === '') {
            return null;
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $address))));

        if ($parts === []) {
            return null;
        }

        $candidate = $parts[0];

        return mb_strlen($candidate) >= 2 ? $candidate : null;
    }
}
