<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizerGuardController extends Controller
{
    public function eventBookingImpact(Request $request, int $eventId): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');

        return response()->json(
            $this->buildImpactPayload(
                $organizer['id'] ?? 0,
                [
                    'event_id' => $eventId,
                ],
            )
        );
    }

    public function sessionBookingImpact(Request $request, int $sessionId): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');

        return response()->json(
            $this->buildImpactPayload(
                $organizer['id'] ?? 0,
                [
                    'event_session_id' => $sessionId,
                ],
            )
        );
    }

    /**
     * @param  array<string, int>  $filters
     * @return array<string, mixed>
     */
    private function buildImpactPayload(int $organizerId, array $filters): array
    {
        $baseQuery = Booking::query()
            ->with('items')
            ->whereHas('snapshot', function ($query) use ($organizerId, $filters): void {
                $query->where('organizer_id', $organizerId);

                if (isset($filters['event_id'])) {
                    $query->where('event_id', $filters['event_id']);
                }

                if (isset($filters['event_session_id'])) {
                    $query->where('event_session_id', $filters['event_session_id']);
                }
            });

        $confirmedBookings = (clone $baseQuery)
            ->where('status', Booking::STATUS_CONFIRMED)
            ->get();

        $activeReservations = (clone $baseQuery)
            ->whereIn('status', [
                Booking::STATUS_RESERVED,
                Booking::STATUS_PAYMENT_PENDING,
            ])
            ->where(function ($query): void {
                $query
                    ->whereNull('reserved_until')
                    ->orWhere('reserved_until', '>', now());
            })
            ->get();

        return [
            'event_id' => $filters['event_id'] ?? null,
            'event_session_id' => $filters['event_session_id'] ?? null,
            'has_confirmed_bookings' => $confirmedBookings->isNotEmpty(),
            'confirmed_bookings_count' => $confirmedBookings->count(),
            'confirmed_tickets_count' => (int) $confirmedBookings->sum(
                fn (Booking $booking) => (int) $booking->items->sum('quantity')
            ),
            'active_reservations_count' => $activeReservations->count(),
            'active_reserved_tickets_count' => (int) $activeReservations->sum(
                fn (Booking $booking) => (int) $booking->items->sum('quantity')
            ),
            'latest_confirmed_at' => $confirmedBookings
                ->pluck('confirmed_at')
                ->filter()
                ->sortDesc()
                ->first()?->toISOString(),
        ];
    }
}
