<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Payment;
use App\Models\SessionSeat;
use App\Models\SessionSnapshot;
use App\Models\SessionStandingArea;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentGatewayResult;
use App\Services\Payments\TicketDocumentService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        private readonly SessionSnapshotSynchronizer $snapshotSynchronizer,
        private readonly PaymentGatewayInterface $paymentGateway,
        private readonly TicketDocumentService $ticketDocumentService,
    ) {
    }

    public function getAvailabilityBySessionId(int $sessionId): SessionSnapshot
    {
        $snapshot = $this->snapshotSynchronizer->syncByEventSessionId($sessionId);

        DB::transaction(function () use ($snapshot): void {
            SessionSnapshot::query()
                ->whereKey($snapshot->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->expirePendingBookingsForSnapshot($snapshot->id);
        }, 3);

        return $snapshot->fresh(['seats', 'standingAreas']);
    }

    /**
     * @param  array<string, mixed>  $authUser
     * @param  array<int, string>  $seatElementIds
     * @param  array<int, array{element_id: string, quantity: int}>  $standingSelections
     */
    public function reserveBooking(
        array $authUser,
        int $sessionId,
        array $seatElementIds,
        array $standingSelections,
        string $flowType = Booking::FLOW_RESERVATION,
    ): Booking {
        return $this->createReservation($authUser, $sessionId, $seatElementIds, $standingSelections, $flowType);
    }

    /**
     * @param  array<string, mixed>  $authUser
     * @param  array<int, string>  $seatElementIds
     * @param  array<int, array{element_id: string, quantity: int}>  $standingSelections
     */
    public function purchaseBooking(
        array $authUser,
        int $sessionId,
        array $seatElementIds,
        array $standingSelections,
    ): Booking {
        $booking = $this->createReservation(
            authUser: $authUser,
            sessionId: $sessionId,
            seatElementIds: $seatElementIds,
            standingSelections: $standingSelections,
            flowType: Booking::FLOW_PURCHASE,
        );

        return $this->initiatePaymentForBooking($authUser, $booking->id);
    }

    /**
     * @param  array<string, mixed>  $authUser
     */
    public function initiatePaymentForBooking(array $authUser, int $bookingId): Booking
    {
        return DB::transaction(function () use ($authUser, $bookingId): Booking {
            $booking = Booking::query()
                ->with(['snapshot', 'items', 'payment'])
                ->whereKey($bookingId)
                ->where('user_id', (int) $authUser['id'])
                ->lockForUpdate()
                ->first();

            if ($booking === null) {
                throw (new ModelNotFoundException())->setModel(Booking::class, [$bookingId]);
            }

            if ($booking->status === Booking::STATUS_CONFIRMED) {
                return $this->ensureTicketIssued($booking);
            }

            if ($booking->status === Booking::STATUS_CANCELLED || $booking->status === Booking::STATUS_EXPIRED) {
                throw ValidationException::withMessages([
                    'booking' => ['Only active bookings can be paid.'],
                ]);
            }

            if ($booking->reserved_until !== null && $booking->reserved_until->isPast()) {
                $this->releaseBooking($booking, Booking::STATUS_EXPIRED);

                throw ValidationException::withMessages([
                    'booking' => ['This booking reservation has expired.'],
                ]);
            }

            $booking->loadMissing('snapshot');
            $this->ensureSnapshotIsBookable($booking->snapshot);

            $gatewayResult = $this->paymentGateway->createPayment(
                idempotenceKey: (string) Str::uuid(),
                amount: (float) $booking->total_amount,
                currency: $booking->currency,
                description: $this->buildPaymentDescription($booking),
                returnUrl: $this->buildPaymentReturnUrl($booking),
                metadata: [
                    'booking_id' => (string) $booking->id,
                    'user_id' => (string) $booking->user_id,
                    'event_session_id' => (string) $booking->snapshot?->event_session_id,
                ],
            );

            return $this->applyGatewayResultToBooking($booking, $gatewayResult);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $authUser
     */
    public function refreshPaymentForBooking(array $authUser, int $bookingId): Booking
    {
        return DB::transaction(function () use ($authUser, $bookingId): Booking {
            $booking = Booking::query()
                ->with(['snapshot', 'items', 'payment'])
                ->whereKey($bookingId)
                ->where('user_id', (int) $authUser['id'])
                ->lockForUpdate()
                ->first();

            if ($booking === null) {
                throw (new ModelNotFoundException())->setModel(Booking::class, [$bookingId]);
            }

            if ($booking->status === Booking::STATUS_CONFIRMED) {
                return $this->ensureTicketIssued($booking);
            }

            $payment = $booking->payment;

            if ($payment === null || $payment->external_reference === null) {
                throw ValidationException::withMessages([
                    'payment' => ['Payment has not been initiated for this booking yet.'],
                ]);
            }

            $gatewayResult = $this->paymentGateway->getPayment($payment->external_reference);

            return $this->applyGatewayResultToBooking($booking, $gatewayResult);
        }, 3);
    }

    public function syncPaymentByProviderReference(string $providerPaymentId): ?Booking
    {
        $payment = Payment::query()
            ->with(['booking.snapshot', 'booking.items', 'booking.payment'])
            ->where('external_reference', $providerPaymentId)
            ->first();

        if ($payment === null || $payment->booking === null) {
            return null;
        }

        return DB::transaction(function () use ($payment): Booking {
            $booking = Booking::query()
                ->with(['snapshot', 'items', 'payment'])
                ->whereKey($payment->booking_id)
                ->lockForUpdate()
                ->firstOrFail();

            $gatewayResult = $this->paymentGateway->getPayment($providerPaymentId);

            return $this->applyGatewayResultToBooking($booking, $gatewayResult);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $authUser
     */
    public function cancelBooking(array $authUser, int $bookingId): Booking
    {
        return DB::transaction(function () use ($authUser, $bookingId): Booking {
            $booking = Booking::query()
                ->with(['items', 'payment'])
                ->whereKey($bookingId)
                ->where('user_id', (int) $authUser['id'])
                ->lockForUpdate()
                ->first();

            if ($booking === null) {
                throw (new ModelNotFoundException())->setModel(Booking::class, [$bookingId]);
            }

            if ($booking->status === Booking::STATUS_CANCELLED) {
                return $booking->fresh(['snapshot', 'items.seat', 'items.standingArea', 'payment']);
            }

            if (!in_array($booking->status, [Booking::STATUS_RESERVED, Booking::STATUS_PAYMENT_PENDING], true)) {
                throw ValidationException::withMessages([
                    'booking' => ['Only unpaid bookings can be cancelled.'],
                ]);
            }

            $this->releaseBooking($booking, Booking::STATUS_CANCELLED);

            return $booking->fresh(['snapshot', 'items.seat', 'items.standingArea', 'payment']);
        }, 3);
    }

    public function ensureTicketIssued(Booking $booking): Booking
    {
        if ($booking->ticket_pdf_path !== null && $booking->ticket_issued_at !== null) {
            return $booking->fresh(['snapshot', 'items.seat', 'items.standingArea', 'payment']);
        }

        return $this->ticketDocumentService->issueForBooking(
            $booking->fresh(['snapshot', 'items.seat', 'items.standingArea', 'payment']),
        );
    }

    /**
     * @param  array<string, mixed>  $authUser
     * @return array{status: string, message: string, booking: ?Booking}
     */
    public function verifyTicketForOrganizer(array $authUser, string $rawTicketPayload): array
    {
        $organizerId = (int) ($authUser['id'] ?? 0);
        $ticketCode = $this->extractTicketCode($rawTicketPayload);

        if ($ticketCode === '') {
            throw ValidationException::withMessages([
                'ticket_code' => ['Ticket code or QR payload is required.'],
            ]);
        }

        return DB::transaction(function () use ($organizerId, $ticketCode): array {
            $booking = Booking::query()
                ->with(['snapshot', 'items', 'payment'])
                ->where('ticket_code', $ticketCode)
                ->lockForUpdate()
                ->first();

            if ($booking === null || $booking->snapshot === null) {
                return [
                    'status' => 'not_found',
                    'message' => 'Ticket was not found.',
                    'booking' => null,
                ];
            }

            $snapshot = $booking->snapshot;

            if ($snapshot->organizer_id === null) {
                $session = $this->snapshotSynchronizer->syncByEventSessionId($snapshot->event_session_id);
                $snapshot = $session;
                $booking->setRelation('snapshot', $snapshot);
            }

            if ((int) $snapshot->organizer_id !== $organizerId) {
                throw new AuthorizationException('This ticket belongs to another organizer event.');
            }

            if ($booking->status !== Booking::STATUS_CONFIRMED) {
                return [
                    'status' => 'invalid',
                    'message' => 'Only paid tickets can be validated.',
                    'booking' => $booking->fresh(['snapshot', 'items.seat', 'items.standingArea', 'payment']),
                ];
            }

            if ($booking->ticket_used_at !== null) {
                return [
                    'status' => 'already_used',
                    'message' => 'This ticket has already been used.',
                    'booking' => $booking->fresh(['snapshot', 'items.seat', 'items.standingArea', 'payment']),
                ];
            }

            $booking->update([
                'ticket_used_at' => now(),
                'ticket_used_by_organizer_id' => $organizerId,
            ]);

            return [
                'status' => 'validated',
                'message' => 'Ticket is valid and has been marked as used.',
                'booking' => $booking->fresh(['snapshot', 'items.seat', 'items.standingArea', 'payment']),
            ];
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $authUser
     * @param  array<int, string>  $seatElementIds
     * @param  array<int, array{element_id: string, quantity: int}>  $standingSelections
     */
    private function createReservation(
        array $authUser,
        int $sessionId,
        array $seatElementIds,
        array $standingSelections,
        string $flowType,
    ): Booking {
        $snapshot = $this->snapshotSynchronizer->syncByEventSessionId($sessionId);

        return DB::transaction(function () use ($authUser, $snapshot, $seatElementIds, $standingSelections, $flowType): Booking {
            $lockedSnapshot = SessionSnapshot::query()
                ->whereKey($snapshot->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->expirePendingBookingsForSnapshot($lockedSnapshot->id);
            $this->ensureSnapshotIsBookable($lockedSnapshot);
            $this->ensureUserMeetsAgeRequirement($authUser, $lockedSnapshot);

            $seatElementIds = array_values(array_unique($seatElementIds));
            $standingSelections = $this->deduplicateStandingSelections($standingSelections);

            if ($seatElementIds === [] && $standingSelections === []) {
                throw ValidationException::withMessages([
                    'items' => ['Select at least one seat or one standing ticket.'],
                ]);
            }

            $seats = SessionSeat::query()
                ->where('session_snapshot_id', $lockedSnapshot->id)
                ->whereIn('element_id', $seatElementIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('element_id');

            if (count($seatElementIds) !== $seats->count()) {
                throw ValidationException::withMessages([
                    'seat_ids' => ['One or more selected seats do not exist.'],
                ]);
            }

            foreach ($seats as $seat) {
                if ($seat->status !== SessionSeat::STATUS_FREE) {
                    throw ValidationException::withMessages([
                        'seat_ids' => ["Seat [{$seat->label}] is no longer available."],
                    ]);
                }
            }

            $standingAreas = SessionStandingArea::query()
                ->where('session_snapshot_id', $lockedSnapshot->id)
                ->whereIn('element_id', array_keys($standingSelections))
                ->lockForUpdate()
                ->get()
                ->keyBy('element_id');

            if (count($standingSelections) !== $standingAreas->count()) {
                throw ValidationException::withMessages([
                    'standing' => ['One or more selected standing areas do not exist.'],
                ]);
            }

            foreach ($standingSelections as $elementId => $quantity) {
                $area = $standingAreas[$elementId];

                if ($quantity > $area->capacity_available) {
                    throw ValidationException::withMessages([
                        'standing' => ["Standing area [{$area->label}] does not have enough free capacity."],
                    ]);
                }
            }

            $reservedUntil = now()->addMinutes((int) config('booking.reservation_ttl_minutes', 15));
            $totalAmount = $this->calculateTotalAmount($seats, $standingSelections, $standingAreas);

            $booking = Booking::query()->create([
                'user_id' => (int) $authUser['id'],
                'session_snapshot_id' => $lockedSnapshot->id,
                'status' => Booking::STATUS_RESERVED,
                'flow_type' => $flowType,
                'total_amount' => $totalAmount,
                'currency' => (string) config('booking.currency', 'RUB'),
                'reserved_until' => $reservedUntil,
            ]);

            foreach ($seats as $seat) {
                $seat->update([
                    'status' => SessionSeat::STATUS_RESERVED,
                    'booking_id' => $booking->id,
                    'reserved_until' => $reservedUntil,
                ]);

                BookingItem::query()->create([
                    'booking_id' => $booking->id,
                    'item_type' => BookingItem::TYPE_SEAT,
                    'session_seat_id' => $seat->id,
                    'external_element_id' => $seat->element_id,
                    'label' => $seat->label,
                    'quantity' => 1,
                    'unit_price' => $seat->price,
                    'total_price' => $seat->price,
                    'meta' => [
                        'type' => $seat->type,
                        'level_id' => $seat->level_id,
                        'row' => $seat->row_label,
                        'number' => $seat->seat_number,
                    ],
                ]);
            }

            foreach ($standingSelections as $elementId => $quantity) {
                $area = $standingAreas[$elementId];

                $area->update([
                    'capacity_available' => $area->capacity_available - $quantity,
                ]);

                BookingItem::query()->create([
                    'booking_id' => $booking->id,
                    'item_type' => BookingItem::TYPE_STANDING,
                    'session_standing_area_id' => $area->id,
                    'external_element_id' => $area->element_id,
                    'label' => $area->label,
                    'quantity' => $quantity,
                    'unit_price' => $area->price,
                    'total_price' => round((float) $area->price * $quantity, 2),
                    'meta' => [
                        'level_id' => $area->level_id,
                    ],
                ]);
            }

            return $booking->fresh(['snapshot', 'items.seat', 'items.standingArea', 'payment']);
        }, 3);
    }

    private function applyGatewayResultToBooking(Booking $booking, PaymentGatewayResult $gatewayResult): Booking
    {
        $payment = $booking->payment;

        if ($payment === null) {
            $payment = Payment::query()->create([
                'booking_id' => $booking->id,
                'provider' => $this->paymentGateway->providerName(),
                'status' => $this->mapGatewayPaymentStatus($gatewayResult),
                'amount' => $booking->total_amount,
                'currency' => $booking->currency,
                'external_reference' => $gatewayResult->providerPaymentId,
                'confirmation_url' => $gatewayResult->confirmationUrl,
                'failure_reason' => $gatewayResult->failureReason,
                'payload' => $gatewayResult->payload,
                'paid_at' => $gatewayResult->isPaid ? now() : null,
                'cancelled_at' => $gatewayResult->isCancelled ? now() : null,
                'last_synced_at' => now(),
            ]);
        } else {
            $payment->update([
                'provider' => $this->paymentGateway->providerName(),
                'status' => $this->mapGatewayPaymentStatus($gatewayResult),
                'amount' => $booking->total_amount,
                'currency' => $booking->currency,
                'external_reference' => $gatewayResult->providerPaymentId,
                'confirmation_url' => $gatewayResult->confirmationUrl,
                'failure_reason' => $gatewayResult->failureReason,
                'payload' => $gatewayResult->payload,
                'last_synced_at' => now(),
                'paid_at' => $gatewayResult->isPaid ? now() : null,
                'cancelled_at' => $gatewayResult->isCancelled ? now() : null,
            ]);
        }

        if ($gatewayResult->isPaid) {
            return $this->confirmBooking($booking->fresh(['snapshot', 'items', 'payment']));
        }

        if ($gatewayResult->isCancelled) {
            $booking->update([
                'status' => $booking->reserved_until !== null && $booking->reserved_until->isFuture()
                    ? Booking::STATUS_RESERVED
                    : Booking::STATUS_EXPIRED,
            ]);

            if ($booking->status === Booking::STATUS_EXPIRED) {
                $this->releaseBooking($booking, Booking::STATUS_EXPIRED);
            }

            return $booking->fresh(['snapshot', 'items.seat', 'items.standingArea', 'payment']);
        }

        $booking->update([
            'status' => Booking::STATUS_PAYMENT_PENDING,
        ]);

        return $booking->fresh(['snapshot', 'items.seat', 'items.standingArea', 'payment']);
    }

    private function confirmBooking(Booking $booking): Booking
    {
        SessionSeat::query()
            ->where('booking_id', $booking->id)
            ->lockForUpdate()
            ->update([
                'status' => SessionSeat::STATUS_BOOKED,
                'reserved_until' => null,
            ]);

        $booking->update([
            'status' => Booking::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'reserved_until' => null,
        ]);

        if ($booking->payment !== null) {
            $booking->payment->update([
                'status' => Payment::STATUS_PAID,
                'paid_at' => now(),
                'cancelled_at' => null,
                'last_synced_at' => now(),
            ]);
        }

        return $this->ensureTicketIssued($booking);
    }

    private function mapGatewayPaymentStatus(PaymentGatewayResult $gatewayResult): string
    {
        if ($gatewayResult->isPaid) {
            return Payment::STATUS_PAID;
        }

        if ($gatewayResult->isCancelled) {
            return Payment::STATUS_CANCELLED;
        }

        return Payment::STATUS_PENDING;
    }

    private function buildPaymentDescription(Booking $booking): string
    {
        $prefix = trim((string) config('payments.description_prefix', 'Submeet ticket'));
        $title = trim((string) ($booking->snapshot?->event_title ?? 'Event'));

        return $prefix . ' #' . $booking->id . ' - ' . $title;
    }

    private function extractTicketCode(string $rawTicketPayload): string
    {
        $value = trim($rawTicketPayload);

        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $ticketCode = trim((string) ($decoded['ticket_code'] ?? ''));

                if ($ticketCode !== '') {
                    return $ticketCode;
                }
            }
        }

        return $value;
    }

    private function buildPaymentReturnUrl(Booking $booking): string
    {
        if ($this->paymentGateway->providerName() === 'mock') {
            $checkoutBaseUrl = rtrim((string) config('payments.checkout_url', 'http://127.0.0.1:5173/checkout'), '/');

            return $checkoutBaseUrl . '/' . $booking->id;
        }

        $baseUrl = trim((string) config('payments.return_url', 'http://127.0.0.1:5173/profile'));
        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl . $separator . http_build_query([
            'booking' => $booking->id,
            'payment' => 'return',
        ]);
    }

    private function ensureSnapshotIsBookable(SessionSnapshot $snapshot): void
    {
        if ($snapshot->status !== 'scheduled') {
            throw ValidationException::withMessages([
                'session_id' => ['This session is not available for booking.'],
            ]);
        }

        if ($snapshot->starts_at === null || $snapshot->starts_at->isPast()) {
            throw ValidationException::withMessages([
                'session_id' => ['Booking is unavailable for a past session.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $authUser
     */
    private function ensureUserMeetsAgeRequirement(array $authUser, SessionSnapshot $snapshot): void
    {
        $minimumAge = (int) $snapshot->event_min_age;

        if ($minimumAge < 1) {
            return;
        }

        $birthDate = $authUser['birth_date'] ?? null;

        if (!is_string($birthDate) || trim($birthDate) === '') {
            throw ValidationException::withMessages([
                'user' => ['Birth date is required to validate the age restriction.'],
            ]);
        }

        $age = CarbonImmutable::parse($birthDate)->age;

        if ($age < $minimumAge) {
            throw ValidationException::withMessages([
                'user' => ["This event is available only for users aged {$minimumAge}+."],
            ]);
        }
    }

    /**
     * @param  array<int, array{element_id: string, quantity: int}>  $standingSelections
     * @return array<string, int>
     */
    private function deduplicateStandingSelections(array $standingSelections): array
    {
        $normalized = [];

        foreach ($standingSelections as $selection) {
            $elementId = trim((string) ($selection['element_id'] ?? ''));
            $quantity = (int) ($selection['quantity'] ?? 0);

            if ($elementId === '' || $quantity < 1) {
                continue;
            }

            if (isset($normalized[$elementId])) {
                throw ValidationException::withMessages([
                    'standing' => ['Duplicate standing areas are not allowed in a single booking request.'],
                ]);
            }

            $normalized[$elementId] = $quantity;
        }

        return $normalized;
    }

    /**
     * @param  Collection<int, SessionSeat>  $seats
     * @param  array<string, int>  $standingSelections
     * @param  Collection<int|string, SessionStandingArea>  $standingAreas
     */
    private function calculateTotalAmount(
        Collection $seats,
        array $standingSelections,
        Collection $standingAreas,
    ): float {
        $seatTotal = $seats->sum(fn (SessionSeat $seat) => (float) $seat->price);

        $standingTotal = 0.0;

        foreach ($standingSelections as $elementId => $quantity) {
            $standingTotal += (float) $standingAreas[$elementId]->price * $quantity;
        }

        return round($seatTotal + $standingTotal, 2);
    }

    private function expirePendingBookingsForSnapshot(int $snapshotId): void
    {
        $expiredBookings = Booking::query()
            ->with(['items', 'payment'])
            ->where('session_snapshot_id', $snapshotId)
            ->awaitingCheckout()
            ->whereNotNull('reserved_until')
            ->where('reserved_until', '<=', now())
            ->lockForUpdate()
            ->get();

        foreach ($expiredBookings as $booking) {
            $this->releaseBooking($booking, Booking::STATUS_EXPIRED);
        }
    }

    private function releaseBooking(Booking $booking, string $targetStatus): void
    {
        $booking->loadMissing(['items', 'payment']);

        $seatIds = $booking->items
            ->where('item_type', BookingItem::TYPE_SEAT)
            ->pluck('session_seat_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($seatIds !== []) {
            SessionSeat::query()
                ->whereIn('id', $seatIds)
                ->lockForUpdate()
                ->update([
                    'status' => SessionSeat::STATUS_FREE,
                    'booking_id' => null,
                    'reserved_until' => null,
                ]);
        }

        $standingQuantities = [];

        foreach ($booking->items->where('item_type', BookingItem::TYPE_STANDING) as $item) {
            $standingAreaId = (int) $item->session_standing_area_id;

            if ($standingAreaId < 1) {
                continue;
            }

            $standingQuantities[$standingAreaId] = ($standingQuantities[$standingAreaId] ?? 0) + (int) $item->quantity;
        }

        if ($standingQuantities !== []) {
            $areas = SessionStandingArea::query()
                ->whereIn('id', array_keys($standingQuantities))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($standingQuantities as $standingAreaId => $quantity) {
                /** @var SessionStandingArea|null $area */
                $area = $areas->get($standingAreaId);

                if ($area === null) {
                    continue;
                }

                $area->update([
                    'capacity_available' => min(
                        $area->capacity_total,
                        $area->capacity_available + $quantity,
                    ),
                ]);
            }
        }

        $booking->update([
            'status' => $targetStatus,
            'reserved_until' => null,
            'cancelled_at' => $targetStatus === Booking::STATUS_CANCELLED ? now() : $booking->cancelled_at,
        ]);

        if ($booking->payment !== null && $booking->payment->status !== Payment::STATUS_PAID) {
            $booking->payment->update([
                'status' => $targetStatus === Booking::STATUS_EXPIRED ? Payment::STATUS_FAILED : Payment::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'last_synced_at' => now(),
                'payload' => array_merge($booking->payment->payload ?? [], [
                    'booking_release_reason' => $targetStatus,
                ]),
            ]);
        }
    }
}
