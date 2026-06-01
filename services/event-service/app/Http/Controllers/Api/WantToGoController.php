<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventFavorite;
use App\Models\EventSession;
use App\Services\EventTeaserService;
use App\Services\HallServiceClient;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class WantToGoController extends Controller
{
    public function __construct(
        private readonly HallServiceClient $hallServiceClient,
        private readonly EventTeaserService $teaserService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user', []);
        $userId = (int) ($authUser['id'] ?? 0);

        $publishedEventIds = Event::query()
            ->published()
            ->pluck('id');

        EventFavorite::query()
            ->where('user_id', $userId)
            ->when(
                $publishedEventIds->isNotEmpty(),
                fn ($query) => $query->whereNotIn('event_id', $publishedEventIds->all()),
                fn ($query) => $query
            )
            ->delete();

        if ($publishedEventIds->isEmpty()) {
            return response()->json([]);
        }

        /** @var EloquentCollection<int, EventFavorite> $favorites */
        $favorites = EventFavorite::query()
            ->with([
                'event' => fn ($query) => $query->withCount([
                    'sessions',
                    'sessions as available_sessions_count' => fn ($sessionQuery) => $sessionQuery->available(),
                ]),
                'event.category:id,name,slug',
                'event.ageRating:id,label,min_age',
                'event.organizer:id,auth_user_id,full_name,company_name,email',
                'event.tags:id,name,slug',
            ])
            ->where('user_id', $userId)
            ->whereIn('event_id', $publishedEventIds->all())
            ->latest('created_at')
            ->get();

        $staleFavorites = $favorites
            ->filter(function (EventFavorite $favorite): bool {
                $event = $favorite->event;

                if (!$event instanceof Event) {
                    return false;
                }

                return (int) ($event->getAttribute('sessions_count') ?? 0) > 0
                    && (int) ($event->getAttribute('available_sessions_count') ?? 0) === 0;
            });

        if ($staleFavorites->isNotEmpty()) {
            EventFavorite::query()
                ->whereIn('id', $staleFavorites->pluck('id')->all())
                ->delete();

            $favorites = $favorites
                ->reject(fn (EventFavorite $favorite) => $staleFavorites->contains('id', $favorite->id))
                ->values();
        }

        $events = $favorites
            ->pluck('event')
            ->filter();

        $availableSessions = EventSession::query()
            ->available()
            ->whereIn('event_id', $events->pluck('id')->all())
            ->orderBy('start_time')
            ->get()
            ->groupBy('event_id');

        $hallMap = $this->loadPublicHallMap(
            $availableSessions
                ->flatten()
                ->pluck('hall_id')
                ->all()
        );

        $payload = $favorites
            ->map(fn (EventFavorite $favorite) => $this->transformFavorite(
                $favorite,
                $availableSessions,
                $hallMap,
            ))
            ->filter()
            ->values()
            ->all();

        return response()->json($payload);
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user', []);
        $userId = (int) ($authUser['id'] ?? 0);

        $event = Event::query()
            ->published()
            ->whereKey($id)
            ->firstOrFail();

        $favorite = EventFavorite::query()->firstOrCreate([
            'user_id' => $userId,
            'event_id' => $event->id,
        ]);

        return response()->json([
            'message' => $favorite->wasRecentlyCreated
                ? 'Event added to want-to-go list.'
                : 'Event is already in the want-to-go list.',
            'event_id' => $event->id,
            'is_wanted' => true,
        ], $favorite->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user', []);
        $userId = (int) ($authUser['id'] ?? 0);

        EventFavorite::query()
            ->where('user_id', $userId)
            ->where('event_id', $id)
            ->delete();

        return response()->json([
            'message' => 'Event removed from want-to-go list.',
            'event_id' => $id,
            'is_wanted' => false,
        ]);
    }

    /**
     * @param  Collection<int, Collection<int, EventSession>>  $availableSessions
     * @param  array<int, array<string, mixed>>  $hallMap
     * @return array<string, mixed>|null
     */
    private function transformFavorite(
        EventFavorite $favorite,
        Collection $availableSessions,
        array $hallMap,
    ): ?array {
        $event = $favorite->event;

        if (!$event instanceof Event) {
            return null;
        }

        /** @var Collection<int, EventSession> $eventSessions */
        $eventSessions = $availableSessions->get($event->id, collect());
        /** @var EventSession|null $nextSession */
        $nextSession = $eventSessions->first();
        $minimumPrice = $eventSessions
            ->map(fn (EventSession $session) => (float) $session->base_price)
            ->min();

        return [
            'id' => $event->id,
            'title' => $event->title,
            'poster_url' => $event->poster_url,
            'category' => [
                'id' => $event->category?->id,
                'name' => $event->category?->name,
                'slug' => $event->category?->slug,
            ],
            'age_rating' => [
                'id' => $event->ageRating?->id,
                'label' => $event->ageRating?->label,
                'min_age' => $event->ageRating?->min_age,
            ],
            'tags' => $this->teaserService->transformTags($event),
            'organizer' => [
                'id' => $event->organizer?->auth_user_id,
                'full_name' => $event->organizer?->full_name,
                'company_name' => $event->organizer?->company_name,
                'display_name' => $event->organizer?->company_name ?? $event->organizer?->full_name,
                'email' => $event->organizer?->email,
            ],
            'description' => $event->description,
            'is_wanted' => true,
            'is_teaser' => $this->teaserService->isTeaser($event),
            'has_available_sessions' => $this->teaserService->hasAvailableSessions($event),
            'teaser_reason' => $this->teaserService->isTeaser($event) ? EventTeaserService::REASON : null,
            'wanted_at' => $favorite->created_at?->toISOString(),
            'minimum_price' => $minimumPrice !== null ? round($minimumPrice, 2) : null,
            'next_session' => $nextSession
                ? $this->transformSession(
                    $nextSession,
                    $hallMap[$nextSession->hall_id] ?? null,
                )
                : null,
        ];
    }

    /**
     * @param  array<int, int>  $hallIds
     * @return array<int, array<string, mixed>>
     */
    private function loadPublicHallMap(array $hallIds): array
    {
        $hallMap = [];

        try {
            foreach (array_unique($hallIds) as $hallId) {
                $hall = $this->hallServiceClient->getHall((int) $hallId);

                if ($hall !== null) {
                    $hallMap[(int) $hallId] = $this->transformHallSummary($hall);
                }
            }
        } catch (ConnectionException) {
            return [];
        }

        return $hallMap;
    }

    /**
     * @param  array<string, mixed>|null  $hall
     * @return array<string, mixed>|null
     */
    private function transformHallSummary(?array $hall): ?array
    {
        if ($hall === null) {
            return null;
        }

        return [
            'id' => $hall['id'] ?? null,
            'name' => $hall['name'] ?? null,
            'address' => $hall['address'] ?? null,
            'description' => $hall['description'] ?? null,
            'photo_urls' => $hall['photo_urls'] ?? [],
            'organizer_id' => $hall['organizer_id'] ?? null,
            'venue_owner_id' => $hall['venue_owner_id'] ?? null,
            'status' => $hall['status'] ?? null,
            'capacities' => $hall['capacities'] ?? null,
            'layout_meta' => $hall['layout_meta'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $hall
     * @return array<string, mixed>
     */
    private function transformSession(EventSession $session, ?array $hall = null): array
    {
        return [
            'id' => $session->id,
            'event_id' => $session->event_id,
            'hall_id' => $session->hall_id,
            'hall' => $hall,
            'start_time' => $session->start_time?->toISOString(),
            'end_time' => $session->end_time?->toISOString(),
            'base_price' => $session->base_price,
            'status' => $session->status,
        ];
    }
}
