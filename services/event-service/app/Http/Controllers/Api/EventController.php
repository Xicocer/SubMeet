<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventFavorite;
use App\Models\EventSession;
use App\Models\Tag;
use App\Services\AuthServiceClient;
use App\Services\HallServiceClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private readonly HallServiceClient $hallServiceClient,
        private readonly AuthServiceClient $authServiceClient,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'age' => ['nullable', 'integer', 'min:0'],
            'sort' => ['nullable', 'in:newest,oldest,title_asc,title_desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Event::query()
            ->published()
            ->with([
                'category:id,name,slug',
                'ageRating:id,label,min_age',
                'organizer:id,auth_user_id,full_name,company_name,email',
                'tags:id,name,slug',
            ]);

        $query->when(
            $validated['search'] ?? null,
            fn ($builder, string $search) => $builder->where('title', 'like', '%' . $search . '%')
        );

        $query->when(
            $validated['category'] ?? null,
            fn ($builder, string $categorySlug) => $builder->whereHas(
                'category',
                fn ($categoryQuery) => $categoryQuery->where('slug', $categorySlug)
            )
        );

        $query->when(
            $validated['age'] ?? null,
            fn ($builder, int $age) => $builder->whereHas(
                'ageRating',
                fn ($ageRatingQuery) => $ageRatingQuery->where('min_age', $age)
            )
        );

        match ($validated['sort'] ?? 'newest') {
            'oldest' => $query->oldest('created_at'),
            'title_asc' => $query->orderBy('title'),
            'title_desc' => $query->orderByDesc('title'),
            default => $query->latest('created_at'),
        };

        $authUserId = $this->resolveOptionalAuthUserId($request);

        $events = $query
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        $favoriteEventIds = $this->loadFavoriteEventIds(
            $authUserId,
            $events->getCollection()->pluck('id')->all(),
        );

        $events->setCollection(
            $events->getCollection()->map(
                fn (Event $event) => $this->transformEvent(
                    $event,
                    false,
                    isset($favoriteEventIds[$event->id]),
                )
            )
        );

        return response()->json($events);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $event = Event::query()
            ->published()
            ->with([
                'category:id,name,slug',
                'ageRating:id,label,min_age',
                'organizer:id,auth_user_id,full_name,company_name,email',
                'tags:id,name,slug',
            ])
            ->findOrFail($id);

        $authUserId = $this->resolveOptionalAuthUserId($request);

        return response()->json($this->transformEvent(
            $event,
            true,
            $authUserId !== null
                ? EventFavorite::query()
                    ->where('user_id', $authUserId)
                    ->where('event_id', $event->id)
                    ->exists()
                : false,
        ));
    }

    public function sessions(int $id): JsonResponse
    {
        $event = Event::query()
            ->published()
            ->findOrFail($id);

        $sessions = EventSession::query()
            ->where('event_id', $event->id)
            ->available()
            ->orderBy('start_time')
            ->get();

        $hallMap = $this->loadPublicHallMap($sessions->pluck('hall_id')->all());

        return response()->json(
            $sessions->map(
                fn (EventSession $session) => $this->transformSession(
                    $session,
                    $hallMap[$session->hall_id] ?? null,
                )
            )
        );
    }

    private function transformEvent(Event $event, bool $detailed = false, bool $isWanted = false): array
    {
        $payload = [
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
            'tags' => $event->tags
                ->map(fn (Tag $tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ])
                ->values()
                ->all(),
            'organizer' => [
                'id' => $event->organizer?->auth_user_id,
                'full_name' => $event->organizer?->full_name,
                'company_name' => $event->organizer?->company_name,
                'display_name' => $event->organizer?->company_name ?? $event->organizer?->full_name,
                'email' => $event->organizer?->email,
            ],
            'is_wanted' => $isWanted,
        ];

        if ($detailed) {
            $payload['description'] = $event->description;
            $payload['organizer_id'] = $event->organizer_id;
            $payload['status'] = $event->status;
            $payload['created_at'] = $event->created_at?->toISOString();
            $payload['updated_at'] = $event->updated_at?->toISOString();
        }

        return $payload;
    }

    private function resolveOptionalAuthUserId(Request $request): ?int
    {
        $token = $request->bearerToken();

        if (!$token) {
            return null;
        }

        try {
            $user = $this->authServiceClient->getCurrentUser($token);
        } catch (ConnectionException) {
            return null;
        }

        if (!$user || (int) ($user['status'] ?? 0) !== 1) {
            return null;
        }

        return (int) ($user['id'] ?? 0);
    }

    /**
     * @param  array<int, int>  $eventIds
     * @return array<int, true>
     */
    private function loadFavoriteEventIds(?int $userId, array $eventIds): array
    {
        if ($userId === null || $eventIds === []) {
            return [];
        }

        return EventFavorite::query()
            ->where('user_id', $userId)
            ->whereIn('event_id', $eventIds)
            ->pluck('event_id')
            ->mapWithKeys(fn ($eventId) => [(int) $eventId => true])
            ->all();
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
            'organizer_id' => $hall['organizer_id'] ?? null,
            'status' => $hall['status'] ?? null,
            'capacities' => $hall['capacities'] ?? null,
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
