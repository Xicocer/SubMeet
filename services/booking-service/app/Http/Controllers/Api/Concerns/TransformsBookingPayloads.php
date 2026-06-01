<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Payment;
use App\Models\SessionSeat;
use App\Models\SessionSnapshot;
use App\Models\SessionStandingArea;

trait TransformsBookingPayloads
{
    private function transformAvailability(SessionSnapshot $snapshot): array
    {
        $snapshot->loadMissing(['seats', 'standingAreas']);

        $seatMap = $snapshot->seats->keyBy('element_id');
        $standingAreaMap = $snapshot->standingAreas->keyBy('element_id');
        $layout = $snapshot->hall_layout ?? [];
        $elements = $layout['elements'] ?? [];

        $layout['elements'] = array_map(
            function ($element) use ($seatMap, $standingAreaMap) {
                if (!is_array($element)) {
                    return $element;
                }

                $elementId = (string) ($element['id'] ?? '');
                $type = (string) ($element['type'] ?? '');

                if (in_array($type, ['seat', 'vip_seat'], true) && $seatMap->has($elementId)) {
                    /** @var SessionSeat $seat */
                    $seat = $seatMap[$elementId];
                    $element['booking_state'] = $seat->status;
                    $element['price'] = $seat->price;
                    $element['label'] = $seat->label;
                }

                if (in_array($type, ['dancefloor', 'table'], true) && $standingAreaMap->has($elementId)) {
                    /** @var SessionStandingArea $standingArea */
                    $standingArea = $standingAreaMap[$elementId];
                    $element['price'] = $standingArea->price;
                    $element['capacity_total'] = $standingArea->capacity_total;
                    $element['capacity_available'] = $standingArea->capacity_available;
                    $element['label'] = $standingArea->label;
                }

                return $element;
            },
            is_array($elements) ? $elements : [],
        );

        return [
            'session' => $this->transformSnapshotSummary($snapshot),
            'layout' => $layout,
            'summary' => [
                'seats_total' => $snapshot->seats->count(),
                'seats_free' => $snapshot->seats->where('status', SessionSeat::STATUS_FREE)->count(),
                'seats_reserved' => $snapshot->seats->where('status', SessionSeat::STATUS_RESERVED)->count(),
                'seats_booked' => $snapshot->seats->where('status', SessionSeat::STATUS_BOOKED)->count(),
                'standing_total' => $snapshot->standingAreas->sum('capacity_total'),
                'standing_available' => $snapshot->standingAreas->sum('capacity_available'),
            ],
        ];
    }

    private function transformBooking(Booking $booking, bool $includeGuestAccessToken = false): array
    {
        $booking->loadMissing(['snapshot', 'items.seat', 'items.standingArea', 'payment']);

        $payload = [
            'id' => $booking->id,
            'user_id' => $booking->user_id,
            'customer_email' => $booking->customer_email,
            'is_guest' => $booking->user_id === null,
            'status' => $booking->status,
            'flow_type' => $booking->flow_type,
            'subtotal_amount' => $booking->subtotal_amount,
            'discount_amount' => $booking->discount_amount,
            'loyalty_points_spent' => $booking->loyalty_points_spent,
            'loyalty_points_earned' => $booking->loyalty_points_earned,
            'loyalty_points_awarded_at' => $booking->loyalty_points_awarded_at?->toISOString(),
            'total_amount' => $booking->total_amount,
            'currency' => $booking->currency,
            'reserved_until' => $booking->reserved_until?->toISOString(),
            'confirmed_at' => $booking->confirmed_at?->toISOString(),
            'ticket_issued_at' => $booking->ticket_issued_at?->toISOString(),
            'ticket_sent_at' => $booking->ticket_sent_at?->toISOString(),
            'ticket_used_at' => $booking->ticket_used_at?->toISOString(),
            'ticket_used_by_organizer_id' => $booking->ticket_used_by_organizer_id,
            'cancelled_at' => $booking->cancelled_at?->toISOString(),
            'can_pay' => in_array($booking->status, [Booking::STATUS_RESERVED, Booking::STATUS_PAYMENT_PENDING], true),
            'can_cancel' => in_array($booking->status, [Booking::STATUS_RESERVED, Booking::STATUS_PAYMENT_PENDING], true),
            'session' => $booking->snapshot ? $this->transformSnapshotSummary($booking->snapshot) : null,
            'items' => $booking->items->map(fn (BookingItem $item) => $this->transformBookingItem($item))->values()->all(),
            'payment' => $booking->payment ? $this->transformPayment($booking->payment) : null,
            'ticket' => $booking->ticket_pdf_path !== null ? [
                'code' => $booking->ticket_code,
                'issued_at' => $booking->ticket_issued_at?->toISOString(),
                'used_at' => $booking->ticket_used_at?->toISOString(),
                'used_by_organizer_id' => $booking->ticket_used_by_organizer_id,
                'download_url' => '/api/my/bookings/' . $booking->id . '/ticket',
            ] : null,
            'created_at' => $booking->created_at?->toISOString(),
            'updated_at' => $booking->updated_at?->toISOString(),
        ];

        if ($includeGuestAccessToken && $booking->guest_access_token !== null) {
            $payload['guest_access_token'] = $booking->guest_access_token;
        }

        return $payload;
    }

    private function transformSnapshotSummary(SessionSnapshot $snapshot): array
    {
        return [
            'event_session_id' => $snapshot->event_session_id,
            'event_id' => $snapshot->event_id,
            'event_title' => $snapshot->event_title,
            'category' => [
                'name' => $snapshot->event_category_name,
                'slug' => $snapshot->event_category_slug,
            ],
            'age_rating' => [
                'label' => $snapshot->event_age_rating_label,
                'min_age' => $snapshot->event_min_age,
            ],
            'hall_id' => $snapshot->hall_id,
            'hall_name' => $snapshot->hall_name,
            'hall_address' => $snapshot->hall_address,
            'base_price' => $snapshot->base_price,
            'currency' => $snapshot->currency,
            'status' => $snapshot->status,
            'start_time' => $snapshot->starts_at?->toISOString(),
            'end_time' => $snapshot->ends_at?->toISOString(),
        ];
    }

    private function transformBookingItem(BookingItem $item): array
    {
        return [
            'id' => $item->id,
            'type' => $item->item_type,
            'element_id' => $item->external_element_id,
            'label' => $item->label,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'total_price' => $item->total_price,
            'meta' => $item->meta,
        ];
    }

    private function transformPayment(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'provider' => $payment->provider,
            'status' => $payment->status,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'external_reference' => $payment->external_reference,
            'confirmation_url' => $payment->confirmation_url,
            'failure_reason' => $payment->failure_reason,
            'paid_at' => $payment->paid_at?->toISOString(),
            'cancelled_at' => $payment->cancelled_at?->toISOString(),
            'last_synced_at' => $payment->last_synced_at?->toISOString(),
            'payload' => $payment->payload,
            'created_at' => $payment->created_at?->toISOString(),
            'updated_at' => $payment->updated_at?->toISOString(),
        ];
    }
}
