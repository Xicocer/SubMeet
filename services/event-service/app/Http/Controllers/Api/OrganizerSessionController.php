<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrganizerSessionController extends Controller
{
    public function index(Request $request, int $eventId): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');
        $event = $this->findOrganizerEventOrFail($eventId, $organizer['id']);

        $sessions = EventSession::query()
            ->where('event_id', $event->id)
            ->orderBy('start_time')
            ->get()
            ->map(fn (EventSession $session) => $this->transformSession($session));

        return response()->json($sessions);
    }

    public function store(Request $request, int $eventId): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');

        $validated = $request->validate([
            'hall_id' => ['required', 'integer', 'min:1'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'base_price' => ['required', 'numeric', 'min:0'],
        ]);

        $event = $this->findOrganizerEventOrFail($eventId, $organizer['id']);

        $this->ensureNoHallOverlap(
            hallId: (int) $validated['hall_id'],
            startTime: (string) $validated['start_time'],
            endTime: (string) $validated['end_time'],
        );

        $session = EventSession::query()->create([
            'event_id' => $event->id,
            'hall_id' => $validated['hall_id'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'base_price' => $validated['base_price'],
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        return response()->json([
            'message' => 'Сеанс успешно создан.',
            'session' => $this->transformSession($session),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');

        $validated = $request->validate([
            'hall_id' => ['required', 'integer', 'min:1'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'base_price' => ['required', 'numeric', 'min:0'],
        ]);

        $session = $this->findOrganizerSessionOrFail($id, $organizer['id']);

        $this->ensureNoHallOverlap(
            hallId: (int) $validated['hall_id'],
            startTime: (string) $validated['start_time'],
            endTime: (string) $validated['end_time'],
            ignoreSessionId: $session->id,
        );

        $session->update([
            'hall_id' => $validated['hall_id'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'base_price' => $validated['base_price'],
        ]);

        return response()->json([
            'message' => 'Сеанс успешно обновлен.',
            'session' => $this->transformSession($session->fresh()),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');
        $session = $this->findOrganizerSessionOrFail($id, $organizer['id']);

        $session->update([
            'status' => EventSession::STATUS_CANCELLED,
        ]);

        return response()->json([
            'message' => 'Сеанс отменен.',
            'session' => $this->transformSession($session->fresh()),
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
                'hall_id' => ['В этом зале уже есть пересекающийся сеанс.'],
            ]);
        }
    }

    private function transformSession(EventSession $session): array
    {
        return [
            'id' => $session->id,
            'event_id' => $session->event_id,
            'hall_id' => $session->hall_id,
            'start_time' => $session->start_time?->toISOString(),
            'end_time' => $session->end_time?->toISOString(),
            'base_price' => $session->base_price,
            'status' => $session->status,
            'created_at' => $session->created_at?->toISOString(),
            'updated_at' => $session->updated_at?->toISOString(),
        ];
    }
}
