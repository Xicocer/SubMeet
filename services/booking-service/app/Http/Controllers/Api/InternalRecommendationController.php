<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;

class InternalRecommendationController extends Controller
{
    public function interactions(): JsonResponse
    {
        $bookings = Booking::query()
            ->with('snapshot')
            ->whereIn('status', [
                Booking::STATUS_RESERVED,
                Booking::STATUS_PAYMENT_PENDING,
                Booking::STATUS_CONFIRMED,
            ])
            ->whereNotNull('user_id')
            ->whereHas('snapshot')
            ->orderBy('id')
            ->get();

        $interactionId = 1;

        $payload = $bookings
            ->map(function (Booking $booking) use (&$interactionId): ?array {
                $snapshot = $booking->snapshot;

                if ($snapshot === null) {
                    return null;
                }

                $action = $booking->status === Booking::STATUS_CONFIRMED
                    ? 'purchase'
                    : 'booking';

                $timestamp = $booking->status === Booking::STATUS_CONFIRMED
                    ? ($booking->confirmed_at ?? $booking->created_at)
                    : $booking->created_at;

                return [
                    'id' => $interactionId++,
                    'user_id' => (int) $booking->user_id,
                    'event_id' => (int) $snapshot->event_id,
                    'action' => $action,
                    'rating' => '',
                    'created_at' => $timestamp?->format('Y-m-d H:i:s'),
                ];
            })
            ->filter()
            ->values()
            ->all();

        return response()->json($payload);
    }
}
