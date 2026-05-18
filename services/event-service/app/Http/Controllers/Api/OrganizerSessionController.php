<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSession;
use App\Services\BookingServiceClient;
use App\Services\EventTeaserService;
use App\Services\HallServiceClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class OrganizerSessionController extends Controller
{
    public function __construct(
        private readonly HallServiceClient $hallServiceClient,
        private readonly BookingServiceClient $bookingServiceClient,
        private readonly EventTeaserService $teaserService,
    ) {
    }

    public function index(Request $request, int $eventId): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');
        $event = $this->findOrganizerEventOrFail($eventId, $organizer['id']);

        $sessions = EventSession::query()
            ->where('event_id', $event->id)
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

    public function store(Request $request, int $eventId): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');
        $token = $this->requireBearerToken($request);

        $validated = $request->validate([
            'hall_rental_request_id' => ['required', 'integer', 'min:1'],
            'base_price' => ['required', 'numeric', 'min:0'],
        ]);

        $event = $this->findOrganizerEventOrFail($eventId, $organizer['id']);
        $this->ensureEventCanReceiveSessions($event);

        $rentalRequest = $this->resolveApprovedRentalRequestOrFail(
            $token,
            (int) $validated['hall_rental_request_id'],
            $event->id,
            (int) $organizer['id'],
        );
        $hall = $this->resolvePublicHallOrFail((int) ($rentalRequest['hall_id'] ?? 0));
        $startTime = (string) ($rentalRequest['requested_start'] ?? '');
        $endTime = (string) ($rentalRequest['requested_end'] ?? '');

        $this->ensureNoHallOverlap(
            hallId: (int) ($rentalRequest['hall_id'] ?? 0),
            startTime: $startTime,
            endTime: $endTime,
        );

        $session = EventSession::query()->create([
            'event_id' => $event->id,
            'hall_id' => (int) ($rentalRequest['hall_id'] ?? 0),
            'hall_rental_request_id' => (int) $validated['hall_rental_request_id'],
            'start_time' => $startTime,
            'end_time' => $endTime,
            'base_price' => $validated['base_price'],
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        $this->teaserService->sync($event->fresh(['tags']));

        return response()->json([
            'message' => 'Session created successfully.',
            'session' => $this->transformSession($session, $this->transformHallSummary($hall)),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');
        $token = $this->requireBearerToken($request);

        $validated = $request->validate([
            'hall_rental_request_id' => ['required', 'integer', 'min:1'],
            'base_price' => ['required', 'numeric', 'min:0'],
        ]);

        $session = $this->findOrganizerSessionOrFail($id, $organizer['id']);
        $event = $session->event;
        $this->ensureEventCanReceiveSessions($event);

        $impact = $this->loadSessionBookingImpact($token, $session->id);

        if ($this->hasProtectedBookings($impact)) {
            throw ValidationException::withMessages([
                'session' => ['Session time, hall and price cannot be changed after reservations or paid tickets appear.'],
            ]);
        }

        $rentalRequest = $this->resolveApprovedRentalRequestOrFail(
            $token,
            (int) $validated['hall_rental_request_id'],
            $session->event_id,
            (int) $organizer['id'],
            $session->id,
        );
        $hall = $this->resolvePublicHallOrFail((int) ($rentalRequest['hall_id'] ?? 0));
        $startTime = (string) ($rentalRequest['requested_start'] ?? '');
        $endTime = (string) ($rentalRequest['requested_end'] ?? '');

        $this->ensureNoHallOverlap(
            hallId: (int) ($rentalRequest['hall_id'] ?? 0),
            startTime: $startTime,
            endTime: $endTime,
            ignoreSessionId: $session->id,
        );

        $session->update([
            'hall_id' => (int) ($rentalRequest['hall_id'] ?? 0),
            'hall_rental_request_id' => (int) $validated['hall_rental_request_id'],
            'start_time' => $startTime,
            'end_time' => $endTime,
            'base_price' => $validated['base_price'],
        ]);

        $this->teaserService->sync($event->fresh(['tags']));

        return response()->json([
            'message' => 'Session updated successfully.',
            'session' => $this->transformSession($session->fresh(), $this->transformHallSummary($hall)),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');
        $token = $this->requireBearerToken($request);
        $session = $this->findOrganizerSessionOrFail($id, $organizer['id']);
        $impact = $this->loadSessionBookingImpact($token, $session->id);

        if ($this->hasProtectedBookings($impact)) {
            throw ValidationException::withMessages([
                'session' => ['The session cannot be cancelled while it has active reservations or paid tickets.'],
            ]);
        }

        $hall = $this->loadPublicHallMap([$session->hall_id]);

        $session->update([
            'status' => EventSession::STATUS_CANCELLED,
        ]);

        $freshSession = $session->fresh(['event.tags']);

        if ($freshSession?->event instanceof Event) {
            $this->teaserService->sync($freshSession->event);
        }

        return response()->json([
            'message' => 'Session cancelled.',
            'session' => $this->transformSession($freshSession, $hall[$session->hall_id] ?? null),
        ]);
    }

    private function findOrganizerEventOrFail(int $eventId, int $organizerId): Event
    {
        return Event::query()
            ->whereKey($eventId)
            ->where('organizer_id', $organizerId)
            ->firstOrFail();
    }

    private function findOrganizerSessionOrFail(int $sessionId, int $organizerId): EventSession
    {
        return EventSession::query()
            ->whereKey($sessionId)
            ->whereHas('event', fn ($query) => $query->where('organizer_id', $organizerId))
            ->firstOrFail();
    }

    private function ensureNoHallOverlap(
        int $hallId,
        string $startTime,
        string $endTime,
        ?int $ignoreSessionId = null,
    ): void {
        $query = EventSession::query()
            ->where('hall_id', $hallId)
            ->where('status', '!=', EventSession::STATUS_CANCELLED)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);

        if ($ignoreSessionId !== null) {
            $query->whereKeyNot($ignoreSessionId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'hall_id' => ['There is already an overlapping session in this hall.'],
            ]);
        }
    }

    private function ensureEventCanReceiveSessions(Event $event): void
    {
        if ($event->status === Event::STATUS_PUBLISHED) {
            return;
        }

        throw ValidationException::withMessages([
            'event' => ['Сеансы можно создавать и редактировать только после публикации события администратором. Пока событие не опубликовано, оно остается в подготовке.'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadSessionBookingImpact(string $token, int $sessionId): array
    {
        try {
            return $this->bookingServiceClient->getOrganizerSessionBookingImpact($token, $sessionId) ?? [];
        } catch (ConnectionException $exception) {
            throw new ServiceUnavailableHttpException(null, 'Booking service is unavailable.', $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $impact
     */
    private function hasProtectedBookings(array $impact): bool
    {
        return (int) ($impact['active_reservations_count'] ?? 0) > 0
            || (int) ($impact['confirmed_bookings_count'] ?? 0) > 0;
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
    private function resolveApprovedRentalRequestOrFail(
        string $token,
        int $requestId,
        int $eventId,
        int $organizerId,
        ?int $ignoreSessionId = null,
    ): array
    {
        try {
            $rentalRequest = $this->hallServiceClient->getOrganizerRentalRequest($token, $requestId);
        } catch (ConnectionException $exception) {
            throw new ServiceUnavailableHttpException(null, 'Hall service is unavailable.', $exception);
        }

        if ($rentalRequest === null) {
            throw ValidationException::withMessages([
                'hall_rental_request_id' => ['Rental request was not found for the current organizer.'],
            ]);
        }

        if ((int) ($rentalRequest['organizer_id'] ?? 0) !== $organizerId) {
            throw ValidationException::withMessages([
                'hall_rental_request_id' => ['Rental request belongs to another organizer.'],
            ]);
        }

        if ((int) ($rentalRequest['event_id'] ?? 0) !== $eventId) {
            throw ValidationException::withMessages([
                'hall_rental_request_id' => ['Rental request is linked to another event.'],
            ]);
        }

        if (($rentalRequest['status'] ?? null) !== 'approved') {
            throw ValidationException::withMessages([
                'hall_rental_request_id' => ['Only approved venue rental requests can be converted into sessions.'],
            ]);
        }

        $existingSessionQuery = EventSession::query()
            ->where('hall_rental_request_id', $requestId)
            ->where('status', '!=', EventSession::STATUS_CANCELLED);

        if ($ignoreSessionId !== null) {
            $existingSessionQuery->whereKeyNot($ignoreSessionId);
        }

        if ($existingSessionQuery->exists()) {
            throw ValidationException::withMessages([
                'hall_rental_request_id' => ['A session has already been created for this approved rental request.'],
            ]);
        }

        return $rentalRequest;
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
     * @return array<string, mixed>
     */
    private function resolvePublicHallOrFail(int $hallId): array
    {
        try {
            $hall = $this->hallServiceClient->getHall($hallId);
        } catch (ConnectionException $exception) {
            throw new ServiceUnavailableHttpException(null, 'Hall service is unavailable.', $exception);
        }

        if ($hall === null) {
            throw ValidationException::withMessages([
                'hall_rental_request_id' => ['The linked hall was not found or is unavailable.'],
            ]);
        }

        return $hall;
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
            'venue_owner_id' => $hall['venue_owner_id'] ?? null,
            'status' => $hall['status'] ?? null,
            'hourly_rate' => $hall['hourly_rate'] ?? null,
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
            'hall_rental_request_id' => $session->hall_rental_request_id,
            'hall' => $hall,
            'start_time' => $session->start_time?->toISOString(),
            'end_time' => $session->end_time?->toISOString(),
            'base_price' => $session->base_price,
            'status' => $session->status,
            'created_at' => $session->created_at?->toISOString(),
            'updated_at' => $session->updated_at?->toISOString(),
        ];
    }
}
