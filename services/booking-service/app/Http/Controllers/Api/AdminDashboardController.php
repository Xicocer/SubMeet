<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $statusCounts = Booking::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $confirmedBookings = Booking::query()
            ->with('items')
            ->where('status', Booking::STATUS_CONFIRMED)
            ->get();

        $paymentStatusCounts = Payment::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $recentProblemPayments = Payment::query()
            ->with('booking.snapshot')
            ->whereIn('status', [Payment::STATUS_FAILED, Payment::STATUS_CANCELLED])
            ->latest('updated_at')
            ->limit(10)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'provider' => $payment->provider,
                'failure_reason' => $payment->failure_reason,
                'booking_id' => $payment->booking_id,
                'event_title' => $payment->booking?->snapshot?->event_title,
                'updated_at' => $payment->updated_at?->toISOString(),
            ])
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
                'tickets_sold' => (int) $confirmedBookings->sum(
                    fn (Booking $booking) => (int) $booking->items->sum('quantity')
                ),
                'revenue_total' => round((float) $confirmedBookings->sum('total_amount'), 2),
                'payments_total' => (int) $paymentStatusCounts->sum(),
                'payments_pending' => (int) ($paymentStatusCounts[Payment::STATUS_PENDING] ?? 0),
                'payments_paid' => (int) ($paymentStatusCounts[Payment::STATUS_PAID] ?? 0),
                'payments_failed' => (int) ($paymentStatusCounts[Payment::STATUS_FAILED] ?? 0),
                'payments_cancelled' => (int) ($paymentStatusCounts[Payment::STATUS_CANCELLED] ?? 0),
                'problem_cases_total' => (int) (($statusCounts[Booking::STATUS_CANCELLED] ?? 0) + ($statusCounts[Booking::STATUS_EXPIRED] ?? 0) + ($paymentStatusCounts[Payment::STATUS_FAILED] ?? 0)),
            ],
            'recent_problem_payments' => $recentProblemPayments,
        ]);
    }
}
