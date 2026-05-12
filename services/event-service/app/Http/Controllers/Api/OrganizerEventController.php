<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Tag;
use App\Services\BookingServiceClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class OrganizerEventController extends Controller
{
    public function __construct(
        private readonly BookingServiceClient $bookingServiceClient,
    ) {
    }

    public function myEvents(Request $request): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'status' => ['nullable', 'in:draft,pending_review,published,cancelled,archived'],
        ]);

        $events = Event::query()
            ->where('organizer_id', $organizer['id'])
            ->with([
                'category:id,name,slug',
                'ageRating:id,label,min_age',
                'organizer:id,auth_user_id,full_name,company_name,email',
                'tags:id,name,slug',
            ])
            ->when(
                $validated['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('status', $status)
            )
            ->latest('created_at')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString()
            ->through(fn (Event $event) => $this->transformEvent($event));

        return response()->json($events);
    }

    public function store(Request $request): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'poster_url' => ['nullable', 'url', 'max:2048'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'age_rating_id' => ['required', 'integer', 'exists:age_ratings,id'],
            'status' => ['nullable', 'in:draft,pending_review,published'],
            'tags' => ['nullable', 'array', 'max:12'],
            'tags.*' => ['string', 'min:2', 'max:40', 'distinct'],
        ]);

        $event = DB::transaction(function () use ($validated, $organizer): Event {
            $event = Event::query()->create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'poster_url' => $validated['poster_url'] ?? null,
                'category_id' => $validated['category_id'],
                'age_rating_id' => $validated['age_rating_id'],
                'organizer_id' => $organizer['id'],
                'status' => $this->resolveOrganizerRequestedStatus($validated['status'] ?? null),
            ]);

            $this->syncTags($event, $validated['tags'] ?? []);

            return $event;
        });

        $event->load([
            'category:id,name,slug',
            'ageRating:id,label,min_age',
            'organizer:id,auth_user_id,full_name,company_name,email',
            'tags:id,name,slug',
        ]);

        return response()->json([
            'message' => 'Мероприятие успешно создано.',
            'event' => $this->transformEvent($event),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');
        $token = $this->requireBearerToken($request);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'poster_url' => ['nullable', 'url', 'max:2048'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'age_rating_id' => ['required', 'integer', 'exists:age_ratings,id'],
            'status' => ['nullable', 'in:draft,pending_review,published'],
            'tags' => ['nullable', 'array', 'max:12'],
            'tags.*' => ['string', 'min:2', 'max:40', 'distinct'],
        ]);

        $event = $this->findOrganizerEventOrFail($id, $organizer['id']);
        $impact = $this->loadEventBookingImpact($token, $event->id);

        if ($this->hasCriticalEventChanges($event, $validated) && $this->hasProtectedBookings($impact)) {
            throw ValidationException::withMessages([
                'event' => ['Critical event fields cannot be changed after reservations or paid tickets appear.'],
            ]);
        }

        DB::transaction(function () use ($event, $validated): void {
            $event->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'poster_url' => $validated['poster_url'] ?? null,
                'category_id' => $validated['category_id'],
                'age_rating_id' => $validated['age_rating_id'],
                'status' => array_key_exists('status', $validated)
                    ? $this->resolveOrganizerRequestedStatus($validated['status'])
                    : $event->status,
            ]);

            $this->syncTags($event, $validated['tags'] ?? []);
        });

        $event = $event->fresh([
            'category:id,name,slug',
            'ageRating:id,label,min_age',
            'organizer:id,auth_user_id,full_name,company_name,email',
            'tags:id,name,slug',
        ]);

        return response()->json([
            'message' => 'Мероприятие успешно обновлено.',
            'event' => $this->transformEvent($event),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');
        $token = $this->requireBearerToken($request);

        $validated = $request->validate([
            'status' => ['required', 'in:cancelled,archived'],
        ]);

        $event = $this->findOrganizerEventOrFail($id, $organizer['id']);
        $impact = $this->loadEventBookingImpact($token, $event->id);
        $futureScheduledSessionsCount = $this->countFutureScheduledSessions($event->id);

        if ($validated['status'] === Event::STATUS_CANCELLED) {
            if ($this->hasProtectedBookings($impact)) {
                throw ValidationException::withMessages([
                    'status' => ['The event cannot be cancelled while it has active reservations or paid tickets.'],
                ]);
            }

            DB::transaction(function () use ($event): void {
                $event->update([
                    'status' => Event::STATUS_CANCELLED,
                ]);

                EventSession::query()
                    ->where('event_id', $event->id)
                    ->where('status', '!=', EventSession::STATUS_COMPLETED)
                    ->update([
                        'status' => EventSession::STATUS_CANCELLED,
                    ]);
            });
        } else {
            if ($this->hasActiveReservations($impact)) {
                throw ValidationException::withMessages([
                    'status' => ['The event cannot be archived while there are active reservations awaiting action.'],
                ]);
            }

            if ($futureScheduledSessionsCount > 0) {
                throw ValidationException::withMessages([
                    'status' => ['Cancel or finish all future scheduled sessions before archiving the event.'],
                ]);
            }

            $event->update([
                'status' => Event::STATUS_ARCHIVED,
            ]);
        }

        $event->load([
            'category:id,name,slug',
            'ageRating:id,label,min_age',
            'organizer:id,auth_user_id,full_name,company_name,email',
            'tags:id,name,slug',
        ]);

        return response()->json([
            'message' => 'Статус мероприятия успешно изменен.',
            'event' => $this->transformEvent($event),
        ]);
    }

    private function findOrganizerEventOrFail(int $eventId, int $organizerId): Event
    {
        return Event::query()
            ->whereKey($eventId)
            ->where('organizer_id', $organizerId)
            ->firstOrFail();
    }

    private function requireBearerToken(Request $request): string
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            abort(401, 'Missing bearer token.');
        }

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadEventBookingImpact(string $token, int $eventId): array
    {
        try {
            return $this->bookingServiceClient->getOrganizerEventBookingImpact($token, $eventId) ?? [];
        } catch (ConnectionException $exception) {
            throw new ServiceUnavailableHttpException(null, 'Booking service is unavailable.', $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function hasCriticalEventChanges(Event $event, array $validated): bool
    {
        return $event->title !== $validated['title']
            || (int) $event->category_id !== (int) $validated['category_id']
            || (int) $event->age_rating_id !== (int) $validated['age_rating_id'];
    }

    /**
     * @param  array<string, mixed>  $impact
     */
    private function hasProtectedBookings(array $impact): bool
    {
        return $this->hasActiveReservations($impact) || ((int) ($impact['confirmed_bookings_count'] ?? 0) > 0);
    }

    /**
     * @param  array<string, mixed>  $impact
     */
    private function hasActiveReservations(array $impact): bool
    {
        return (int) ($impact['active_reservations_count'] ?? 0) > 0;
    }

    private function countFutureScheduledSessions(int $eventId): int
    {
        return EventSession::query()
            ->where('event_id', $eventId)
            ->where('status', EventSession::STATUS_SCHEDULED)
            ->where('start_time', '>=', now())
            ->count();
    }

    private function transformEvent(Event $event): array
    {
        return [
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
            'tags' => $event->tags
                ->map(fn (Tag $tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ])
                ->values()
                ->all(),
            'organizer_id' => $event->organizer_id,
            'organizer' => [
                'id' => $event->organizer?->auth_user_id,
                'full_name' => $event->organizer?->full_name,
                'company_name' => $event->organizer?->company_name,
                'display_name' => $event->organizer?->company_name ?? $event->organizer?->full_name,
                'email' => $event->organizer?->email,
            ],
            'status' => $event->status,
            'moderation_note' => $event->moderation_note,
            'moderated_at' => $event->moderated_at?->toISOString(),
            'created_at' => $event->created_at?->toISOString(),
            'updated_at' => $event->updated_at?->toISOString(),
        ];
    }

    private function resolveOrganizerRequestedStatus(?string $status): string
    {
        return match ($status) {
            Event::STATUS_PUBLISHED,
            Event::STATUS_PENDING_REVIEW => Event::STATUS_PENDING_REVIEW,
            default => Event::STATUS_DRAFT,
        };
    }

    /**
     * @param  array<int, string>  $tagNames
     */
    private function syncTags(Event $event, array $tagNames): void
    {
        $tagIds = collect($tagNames)
            ->map(fn ($tagName) => trim((string) $tagName))
            ->filter(fn (string $tagName) => $tagName !== '')
            ->mapWithKeys(function (string $tagName): array {
                $slug = Str::slug($tagName);

                if ($slug === '') {
                    $slug = Str::lower(Str::replace(' ', '-', Str::squish($tagName)));
                }

                return [$slug => Str::squish($tagName)];
            })
            ->map(function (string $name, string $slug): int {
                $tag = Tag::query()->firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $name],
                );

                if ($tag->name !== $name) {
                    $tag->update(['name' => $name]);
                }

                return $tag->id;
            })
            ->values()
            ->all();

        $event->tags()->sync($tagIds);
    }
}
