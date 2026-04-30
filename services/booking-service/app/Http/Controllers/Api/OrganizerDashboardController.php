<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OrganizerDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');
        $organizerId = (int) ($organizer['id'] ?? 0);

        $statusCounts = Booking::query()
            ->whereHas('snapshot', fn ($query) => $query->where('organizer_id', $organizerId))
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $activeReservations = Booking::query()
            ->whereHas('snapshot', fn ($query) => $query->where('organizer_id', $organizerId))
            ->whereIn('status', [Booking::STATUS_RESERVED, Booking::STATUS_PAYMENT_PENDING])
            ->where(function ($query) {
                $query
                    ->whereNull('reserved_until')
                    ->orWhere('reserved_until', '>', now());
            })
            ->count();

        $confirmedBookings = Booking::query()
            ->with(['snapshot', 'items'])
            ->where('status', Booking::STATUS_CONFIRMED)
            ->whereHas('snapshot', fn ($query) => $query->where('organizer_id', $organizerId))
            ->get();

        $ticketsSold = (int) $confirmedBookings->sum(fn (Booking $booking) => $this->bookingItemQuantity($booking));
        $ticketsUsed = (int) $confirmedBookings
            ->filter(fn (Booking $booking) => $booking->ticket_used_at !== null)
            ->sum(fn (Booking $booking) => $this->bookingItemQuantity($booking));

        $revenueTotal = round((float) $confirmedBookings->sum(fn (Booking $booking) => (float) $booking->total_amount), 2);
        $revenueLast30Days = round((float) $confirmedBookings
            ->filter(fn (Booking $booking) => $booking->confirmed_at !== null && $booking->confirmed_at->gte(now()->subDays(30)))
            ->sum(fn (Booking $booking) => (float) $booking->total_amount), 2);

        $averageOrderValue = $confirmedBookings->count() > 0
            ? round($revenueTotal / $confirmedBookings->count(), 2)
            : 0.0;

        $recentBookings = Booking::query()
            ->with(['snapshot', 'items'])
            ->whereHas('snapshot', fn ($query) => $query->where('organizer_id', $organizerId))
            ->orderByRaw('COALESCE(confirmed_at, created_at) DESC')
            ->limit(6)
            ->get()
            ->map(fn (Booking $booking) => $this->transformRecentBooking($booking))
            ->values()
            ->all();

        $recentCheckIns = Booking::query()
            ->with(['snapshot', 'items'])
            ->whereNotNull('ticket_used_at')
            ->whereHas('snapshot', fn ($query) => $query->where('organizer_id', $organizerId))
            ->orderByDesc('ticket_used_at')
            ->limit(5)
            ->get()
            ->map(fn (Booking $booking) => [
                'booking_id' => $booking->id,
                'event_title' => $booking->snapshot?->event_title,
                'hall_name' => $booking->snapshot?->hall_name,
                'ticket_code' => $booking->ticket_code,
                'tickets_used' => $this->bookingItemQuantity($booking),
                'used_at' => $booking->ticket_used_at?->toISOString(),
            ])
            ->values()
            ->all();

        $topEvents = $confirmedBookings
            ->filter(fn (Booking $booking) => $booking->snapshot !== null)
            ->groupBy(fn (Booking $booking) => (string) $booking->snapshot?->event_id)
            ->map(function (Collection $group): array {
                /** @var Booking $first */
                $first = $group->first();

                return [
                    'event_id' => $first->snapshot?->event_id,
                    'event_title' => $first->snapshot?->event_title,
                    'bookings_confirmed' => $group->count(),
                    'tickets_sold' => (int) $group->sum(fn (Booking $booking) => $this->bookingItemQuantity($booking)),
                    'revenue' => round((float) $group->sum(fn (Booking $booking) => (float) $booking->total_amount), 2),
                ];
            })
            ->sortByDesc('revenue')
            ->take(5)
            ->values()
            ->all();

        $salesLast7Days = collect(range(6, 0))
            ->map(function (int $offset) use ($confirmedBookings): array {
                $date = now()->subDays($offset)->toDateString();
                $dayBookings = $confirmedBookings
                    ->filter(fn (Booking $booking) => $booking->confirmed_at !== null && $booking->confirmed_at->toDateString() === $date);

                return [
                    'date' => $date,
                    'bookings_confirmed' => $dayBookings->count(),
                    'tickets_sold' => (int) $dayBookings->sum(fn (Booking $booking) => $this->bookingItemQuantity($booking)),
                    'revenue' => round((float) $dayBookings->sum(fn (Booking $booking) => (float) $booking->total_amount), 2),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'metrics' => [
                'bookings_total' => (int) $statusCounts->sum(),
                'bookings_confirmed' => (int) ($statusCounts[Booking::STATUS_CONFIRMED] ?? 0),
                'bookings_reserved' => (int) ($statusCounts[Booking::STATUS_RESERVED] ?? 0),
                'bookings_payment_pending' => (int) ($statusCounts[Booking::STATUS_PAYMENT_PENDING] ?? 0),
                'bookings_cancelled' => (int) ($statusCounts[Booking::STATUS_CANCELLED] ?? 0),
                'bookings_expired' => (int) ($statusCounts[Booking::STATUS_EXPIRED] ?? 0),
                'tickets_sold' => $ticketsSold,
                'tickets_used' => $ticketsUsed,
                'revenue_total' => $revenueTotal,
                'revenue_last_30_days' => $revenueLast30Days,
                'average_order_value' => $averageOrderValue,
                'active_reservations' => $activeReservations,
            ],
            'recent_bookings' => $recentBookings,
            'recent_check_ins' => $recentCheckIns,
            'top_events' => $topEvents,
            'sales_last_7_days' => $salesLast7Days,
        ]);
    }

    private function bookingItemQuantity(Booking $booking): int
    {
        return (int) $booking->items->sum('quantity');
    }

    /**
     * @return array<string, mixed>
     */
    private function transformRecentBooking(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'status' => $booking->status,
            'flow_type' => $booking->flow_type,
            'event_title' => $booking->snapshot?->event_title,
            'hall_name' => $booking->snapshot?->hall_name,
            'tickets_count' => $this->bookingItemQuantity($booking),
            'total_amount' => $booking->total_amount,
            'confirmed_at' => $booking->confirmed_at?->toISOString(),
            'created_at' => $booking->created_at?->toISOString(),
        ];
    }
}
