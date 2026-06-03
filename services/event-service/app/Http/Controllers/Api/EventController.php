<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventFavorite;
use App\Models\EventSession;
use App\Services\AuthServiceClient;
use App\Services\EventTeaserService;
use App\Services\HallServiceClient;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Throwable;

class EventController extends Controller
{
    public function __construct(
        private readonly HallServiceClient $hallServiceClient,
        private readonly AuthServiceClient $authServiceClient,
        private readonly EventTeaserService $teaserService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:255'],
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
            ])
            ->withCount([
                'sessions as available_sessions_count' => fn ($query) => $query->available(),
            ]);

        $this->applySearchFilter($query, $validated['search'] ?? null);

        $query->when(
            $validated['category'] ?? null,
            fn ($builder, string $categorySlug) => $builder->whereHas(
                'category',
                fn ($categoryQuery) => $categoryQuery->where('slug', $categorySlug)
            )
        );

        $query->when(
            $validated['tag'] ?? null,
            function ($builder, string $tagSlug): void {
                if ($this->teaserService->isReservedTagSlug($tagSlug)) {
                    $builder->whereDoesntHave(
                        'sessions',
                        fn ($sessionQuery) => $sessionQuery->available(),
                    );

                    return;
                }

                $builder->whereHas(
                    'tags',
                    fn ($tagQuery) => $tagQuery->where('slug', $tagSlug)
                );
            }
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
        $availableSessions = $this->loadAvailableSessions(
            $events->getCollection()->pluck('id')->all(),
        );

        $events->setCollection(
            $events->getCollection()->map(
                fn (Event $event) => $this->transformEvent(
                    $event,
                    false,
                    isset($favoriteEventIds[$event->id]),
                    $availableSessions->get($event->id, collect()),
                )
            )
        );

        return response()->json($events);
    }

    private function applySearchFilter(EloquentBuilder $query, ?string $search): void
    {
        $search = trim((string) $search);

        if ($search === '') {
            return;
        }

        try {
            $scoutEventIds = Event::search($search)
                ->where('status', Event::STATUS_PUBLISHED)
                ->keys()
                ->map(fn ($eventId) => (int) $eventId)
                ->all();
        } catch (Throwable) {
            $scoutEventIds = [];
        }

        $searchTerms = $this->searchTerms($search);
        $normalizedSearch = mb_strtolower($search);
        $looksLikeTeaserSearch = str_contains($normalizedSearch, mb_strtolower(EventTeaserService::TAG_NAME))
            || str_contains($normalizedSearch, EventTeaserService::TAG_SLUG);

        $query->where(function (EloquentBuilder $searchQuery) use ($scoutEventIds, $searchTerms, $looksLikeTeaserSearch): void {
            if ($scoutEventIds !== []) {
                $searchQuery->whereKey($scoutEventIds);
            } else {
                $searchQuery->whereRaw('1 = 0');
            }

            foreach ($searchTerms as $term) {
                $like = '%' . $term . '%';

                $searchQuery
                    ->orWhere('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('category', function ($categoryQuery) use ($like): void {
                        $categoryQuery
                            ->where('name', 'like', $like)
                            ->orWhere('slug', 'like', $like);
                    })
                    ->orWhereHas('tags', function ($tagQuery) use ($like): void {
                        $tagQuery
                            ->where('name', 'like', $like)
                            ->orWhere('slug', 'like', $like);
                    });
            }

            if ($looksLikeTeaserSearch) {
                $searchQuery->orWhereDoesntHave(
                    'sessions',
                    fn ($sessionQuery) => $sessionQuery->available(),
                );
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function searchTerms(string $search): array
    {
        $normalizedSearch = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $search) ?? $search));
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $normalizedSearch, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $terms = [$search, $normalizedSearch];

        foreach ($tokens as $token) {
            $terms[] = $token;
            $terms[] = $this->normalizeRussianSearchToken($token);
        }

        return collect($terms)
            ->map(fn (string $term) => trim($term))
            ->filter(fn (string $term) => mb_strlen($term) >= 2)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeRussianSearchToken(string $token): string
    {
        if (mb_strlen($token) <= 4) {
            return $token;
        }

        $endings = [
            'иями', 'ями', 'ами',
            'ого', 'ему', 'ыми', 'ими',
            'ах', 'ях', 'ов', 'ев', 'ей', 'ой', 'ый', 'ий', 'ое', 'ее', 'ая', 'яя', 'ом', 'ем', 'ам', 'ям',
            'ы', 'и', 'а', 'я', 'у', 'ю', 'е', 'о',
        ];

        foreach ($endings as $ending) {
            if (str_ends_with($token, $ending)) {
                $normalized = mb_substr($token, 0, -mb_strlen($ending));

                return mb_strlen($normalized) >= 3 ? $normalized : $token;
            }
        }

        return $token;
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
            ->withCount([
                'sessions as available_sessions_count' => fn ($query) => $query->available(),
            ])
            ->findOrFail($id);

        $authUserId = $this->resolveOptionalAuthUserId($request);
        $availableSessions = $this->loadAvailableSessions([$event->id]);

        return response()->json($this->transformEvent(
            $event,
            true,
            $authUserId !== null
                ? EventFavorite::query()
                    ->where('user_id', $authUserId)
                    ->where('event_id', $event->id)
                    ->exists()
                : false,
            $availableSessions->get($event->id, collect()),
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

    /**
     * @param  Collection<int, EventSession>|null  $availableSessions
     */
    private function transformEvent(
        Event $event,
        bool $detailed = false,
        bool $isWanted = false,
        ?Collection $availableSessions = null,
    ): array {
        $isTeaser = $this->teaserService->isTeaser($event);
        $nextSession = $availableSessions?->first();
        $minimumPrice = $availableSessions
            ? $availableSessions->map(fn (EventSession $session) => (float) $session->base_price)->min()
            : null;

        $payload = [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
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
            'is_wanted' => $isWanted,
            'is_teaser' => $isTeaser,
            'has_available_sessions' => !$isTeaser,
            'teaser_reason' => $isTeaser ? EventTeaserService::REASON : null,
            'available_sessions_count' => (int) ($event->getAttribute('available_sessions_count') ?? 0),
            'minimum_price' => $minimumPrice !== null ? round($minimumPrice, 2) : null,
            'next_session' => $nextSession ? $this->transformSession($nextSession) : null,
        ];

        if ($detailed) {
            $payload['organizer_id'] = $event->organizer_id;
            $payload['status'] = $event->status;
            $payload['tentative_dates'] = $isTeaser ? $this->loadTentativeDates($event->id) : [];
            $payload['created_at'] = $event->created_at?->toISOString();
            $payload['updated_at'] = $event->updated_at?->toISOString();
        }

        return $payload;
    }

    /**
     * @param  array<int, int>  $eventIds
     * @return Collection<int, Collection<int, EventSession>>
     */
    private function loadAvailableSessions(array $eventIds): Collection
    {
        if ($eventIds === []) {
            return collect();
        }

        return EventSession::query()
            ->whereIn('event_id', array_unique($eventIds))
            ->available()
            ->orderBy('start_time')
            ->get()
            ->groupBy('event_id');
    }

    private function loadTentativeDates(int $eventId): array
    {
        try {
            $rentalRequests = $this->hallServiceClient->getEventRentalRequests($eventId);
        } catch (Throwable) {
            return [];
        }

        return collect($rentalRequests)
            ->map(fn (array $rentalRequest) => [
                'id' => $rentalRequest['id'] ?? null,
                'status' => $rentalRequest['status'] ?? null,
                'requested_start' => $rentalRequest['requested_start'] ?? null,
                'requested_end' => $rentalRequest['requested_end'] ?? null,
                'hall' => $rentalRequest['hall'] ?? null,
            ])
            ->filter(fn (array $rentalRequest) => $rentalRequest['requested_start'] !== null && $rentalRequest['requested_end'] !== null)
            ->values()
            ->all();
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
