<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\LoyaltyPointAccount;
use App\Models\Payment;
use App\Models\SessionSeat;
use App\Models\SessionSnapshot;
use App\Models\SessionStandingArea;
use App\Services\Payments\TicketDocumentService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DemoBookingSeeder extends Seeder
{
    public function run(): void
    {
        $ticketDocumentService = app(TicketDocumentService::class);
        $now = CarbonImmutable::now();

        $this->cleanupDemoData();
        $this->seedLoyaltyAccounts();

        $snapshots = [
            4101 => [
                'event_id' => 3101,
                'organizer_id' => 1101,
                'hall_id' => 2101,
                'event_title' => 'Ночной рок-концерт на крыше',
                'event_category_name' => 'Концерт',
                'event_category_slug' => 'concert',
                'event_age_rating_label' => '16+',
                'event_min_age' => 16,
                'hall_name' => 'Крыша на Покровке',
                'hall_address' => 'Нижний Новгород, ул. Большая Покровская, 18',
                'hall_layout' => $this->buildRooftopConcertLayout(),
                'base_price' => 2800,
                'currency' => 'RUB',
                'starts_at' => $now->addDays(2)->setTime(20, 0),
                'ends_at' => $now->addDays(2)->setTime(22, 30),
                'status' => 'scheduled',
            ],
            4102 => [
                'event_id' => 3101,
                'organizer_id' => 1101,
                'hall_id' => 2101,
                'event_title' => 'Ночной рок-концерт на крыше',
                'event_category_name' => 'Концерт',
                'event_category_slug' => 'concert',
                'event_age_rating_label' => '16+',
                'event_min_age' => 16,
                'hall_name' => 'Крыша на Покровке',
                'hall_address' => 'Нижний Новгород, ул. Большая Покровская, 18',
                'hall_layout' => $this->buildRooftopConcertLayout(),
                'base_price' => 2500,
                'currency' => 'RUB',
                'starts_at' => $now->subDays(7)->setTime(20, 0),
                'ends_at' => $now->subDays(7)->setTime(22, 30),
                'status' => 'completed',
            ],
            4201 => [
                'event_id' => 3102,
                'organizer_id' => 1102,
                'hall_id' => 2102,
                'event_title' => 'Большой весенний стендап',
                'event_category_name' => 'Стендап',
                'event_category_slug' => 'standup',
                'event_age_rating_label' => '18+',
                'event_min_age' => 18,
                'hall_name' => 'Standup Hall',
                'hall_address' => 'Нижний Новгород, ул. Рождественская, 22',
                'hall_layout' => $this->buildComedyHallLayout(),
                'base_price' => 1800,
                'currency' => 'RUB',
                'starts_at' => $now->addDay()->setTime(19, 0),
                'ends_at' => $now->addDay()->setTime(20, 40),
                'status' => 'scheduled',
            ],
            4202 => [
                'event_id' => 3102,
                'organizer_id' => 1102,
                'hall_id' => 2102,
                'event_title' => 'Большой весенний стендап',
                'event_category_name' => 'Стендап',
                'event_category_slug' => 'standup',
                'event_age_rating_label' => '18+',
                'event_min_age' => 18,
                'hall_name' => 'Standup Hall',
                'hall_address' => 'Нижний Новгород, ул. Рождественская, 22',
                'hall_layout' => $this->buildComedyHallLayout(),
                'base_price' => 2100,
                'currency' => 'RUB',
                'starts_at' => $now->addDays(9)->setTime(21, 0),
                'ends_at' => $now->addDays(9)->setTime(22, 40),
                'status' => 'scheduled',
            ],
            4301 => [
                'event_id' => 3103,
                'organizer_id' => 1101,
                'hall_id' => 2103,
                'event_title' => 'Иммерсивный спектакль "Тишина сцены"',
                'event_category_name' => 'Театр',
                'event_category_slug' => 'theater',
                'event_age_rating_label' => '12+',
                'event_min_age' => 12,
                'hall_name' => 'Black Box Arena',
                'hall_address' => 'Нижний Новгород, ул. Варварская, 9',
                'hall_layout' => $this->buildBlackBoxLayout(),
                'base_price' => 2400,
                'currency' => 'RUB',
                'starts_at' => $now->addDays(4)->setTime(18, 30),
                'ends_at' => $now->addDays(4)->setTime(20, 30),
                'status' => 'scheduled',
            ],
        ];

        $createdSnapshots = [];

        foreach ($snapshots as $eventSessionId => $snapshotData) {
            $snapshot = SessionSnapshot::query()->create([
                'event_session_id' => $eventSessionId,
                'event_id' => $snapshotData['event_id'],
                'organizer_id' => $snapshotData['organizer_id'],
                'hall_id' => $snapshotData['hall_id'],
                'event_title' => $snapshotData['event_title'],
                'event_category_name' => $snapshotData['event_category_name'],
                'event_category_slug' => $snapshotData['event_category_slug'],
                'event_age_rating_label' => $snapshotData['event_age_rating_label'],
                'event_min_age' => $snapshotData['event_min_age'],
                'hall_name' => $snapshotData['hall_name'],
                'hall_address' => $snapshotData['hall_address'],
                'hall_layout' => $snapshotData['hall_layout'],
                'base_price' => $snapshotData['base_price'],
                'currency' => $snapshotData['currency'],
                'starts_at' => $snapshotData['starts_at'],
                'ends_at' => $snapshotData['ends_at'],
                'status' => $snapshotData['status'],
                'synced_at' => now(),
            ]);

            $this->seedInventory($snapshot, $snapshotData['hall_layout']['elements']);
            $createdSnapshots[$eventSessionId] = $snapshot->fresh(['seats', 'standingAreas']);
        }

        $this->createBooking(
            snapshot: $createdSnapshots[4101],
            bookingId: 6101,
            userId: 1201,
            status: Booking::STATUS_CONFIRMED,
            flowType: Booking::FLOW_PURCHASE,
            seatElementIds: ['seat-a4', 'seat-a5'],
            standingSelections: [],
            reservedUntil: null,
            confirmedAt: $now->subHours(12),
            createdAt: $now->subHours(13),
            paymentData: [
                'status' => Payment::STATUS_PAID,
                'external_reference' => 'demo-paid-6101',
                'paid_at' => $now->subHours(12),
                'payload' => ['mode' => 'demo', 'loyalty_case' => 'paid_with_80_percent_points_discount'],
            ],
            ticketDocumentService: $ticketDocumentService,
            ticketCode: 'SMROOF4101A5',
            loyaltyPointsSpent: 4480,
        );

        $this->createBooking(
            snapshot: $createdSnapshots[4101],
            bookingId: 6102,
            userId: 1202,
            status: Booking::STATUS_RESERVED,
            flowType: Booking::FLOW_RESERVATION,
            seatElementIds: ['seat-a3'],
            standingSelections: [],
            reservedUntil: $now->addMinutes(35),
            confirmedAt: null,
            createdAt: $now->subMinutes(20),
            paymentData: null,
            ticketDocumentService: $ticketDocumentService,
        );

        $this->createBooking(
            snapshot: $createdSnapshots[4102],
            bookingId: 6103,
            userId: 1201,
            status: Booking::STATUS_CONFIRMED,
            flowType: Booking::FLOW_PURCHASE,
            seatElementIds: ['seat-a1', 'seat-a2'],
            standingSelections: [],
            reservedUntil: null,
            confirmedAt: $now->subDays(7)->addHour(),
            createdAt: $now->subDays(7),
            paymentData: [
                'status' => Payment::STATUS_PAID,
                'external_reference' => 'demo-paid-6103',
                'paid_at' => $now->subDays(7)->addHour(),
                'payload' => ['mode' => 'demo', 'loyalty_case' => 'past_purchase_awarded_points'],
            ],
            ticketDocumentService: $ticketDocumentService,
            ticketCode: 'SMROOF4102A2',
            ticketUsedAt: $now->subDays(7)->addHours(4),
            ticketUsedByOrganizerId: 1101,
        );

        $this->createBooking(
            snapshot: $createdSnapshots[4101],
            bookingId: 6104,
            userId: 1202,
            status: Booking::STATUS_CONFIRMED,
            flowType: Booking::FLOW_PURCHASE,
            seatElementIds: [],
            standingSelections: ['dancefloor-main' => 4],
            reservedUntil: null,
            confirmedAt: $now->subHours(5),
            createdAt: $now->subHours(6),
            paymentData: [
                'status' => Payment::STATUS_PAID,
                'external_reference' => 'demo-paid-6104',
                'paid_at' => $now->subHours(5),
                'payload' => ['mode' => 'demo', 'loyalty_case' => 'large_order_earned_points'],
            ],
            ticketDocumentService: $ticketDocumentService,
            ticketCode: 'SMROOF4101DF',
        );

        $this->createBooking(
            snapshot: $createdSnapshots[4201],
            bookingId: 6201,
            userId: 1202,
            status: Booking::STATUS_CONFIRMED,
            flowType: Booking::FLOW_PURCHASE,
            seatElementIds: ['vip-e1'],
            standingSelections: [],
            reservedUntil: null,
            confirmedAt: $now->subDay()->setTime(21, 10),
            createdAt: $now->subDay()->setTime(20, 55),
            paymentData: [
                'status' => Payment::STATUS_PAID,
                'external_reference' => 'demo-paid-6201',
                'paid_at' => $now->subDay()->setTime(21, 10),
                'payload' => ['mode' => 'demo', 'loyalty_case' => 'partial_points_discount'],
            ],
            ticketDocumentService: $ticketDocumentService,
            ticketCode: 'SMSTAND4201VIP',
            loyaltyPointsSpent: 1000,
        );

        $this->createBooking(
            snapshot: $createdSnapshots[4202],
            bookingId: 6202,
            userId: 1201,
            status: Booking::STATUS_PAYMENT_PENDING,
            flowType: Booking::FLOW_PURCHASE,
            seatElementIds: ['seat-c1'],
            standingSelections: [],
            reservedUntil: $now->addMinutes(45),
            confirmedAt: null,
            createdAt: $now->subMinutes(10),
            paymentData: [
                'status' => Payment::STATUS_PENDING,
                'external_reference' => 'demo-pending-6202',
                'confirmation_url' => 'http://127.0.0.1:5173/checkout/6202',
                'payload' => ['mode' => 'demo-checkout', 'loyalty_case' => 'points_spent_until_payment_finishes'],
            ],
            ticketDocumentService: $ticketDocumentService,
            loyaltyPointsSpent: 1000,
        );

        $this->createBooking(
            snapshot: $createdSnapshots[4202],
            bookingId: 6203,
            userId: 1203,
            status: Booking::STATUS_EXPIRED,
            flowType: Booking::FLOW_PURCHASE,
            seatElementIds: ['seat-c2'],
            standingSelections: [],
            reservedUntil: null,
            confirmedAt: null,
            createdAt: $now->subDays(2),
            paymentData: [
                'status' => Payment::STATUS_FAILED,
                'external_reference' => 'demo-failed-6203',
                'failure_reason' => 'Пользователь не завершил оплату в течение лимита времени.',
                'cancelled_at' => $now->subDays(2)->addMinutes(20),
                'payload' => ['mode' => 'demo-timeout'],
            ],
            ticketDocumentService: $ticketDocumentService,
        );

        $this->createBooking(
            snapshot: $createdSnapshots[4301],
            bookingId: 6301,
            userId: 1202,
            status: Booking::STATUS_CANCELLED,
            flowType: Booking::FLOW_PURCHASE,
            seatElementIds: ['seat-f1'],
            standingSelections: [],
            reservedUntil: null,
            confirmedAt: null,
            createdAt: $now->subDays(3),
            paymentData: [
                'status' => Payment::STATUS_CANCELLED,
                'external_reference' => 'demo-cancelled-6301',
                'failure_reason' => 'Пользователь отменил оплату на demo checkout.',
                'cancelled_at' => $now->subDays(3)->addMinutes(15),
                'payload' => ['mode' => 'demo-cancel'],
            ],
            ticketDocumentService: $ticketDocumentService,
        );

        $this->createBooking(
            snapshot: $createdSnapshots[4301],
            bookingId: 6401,
            userId: null,
            status: Booking::STATUS_CONFIRMED,
            flowType: Booking::FLOW_PURCHASE,
            seatElementIds: ['seat-f2'],
            standingSelections: [],
            reservedUntil: null,
            confirmedAt: $now->subHours(2),
            createdAt: $now->subHours(3),
            paymentData: [
                'status' => Payment::STATUS_PAID,
                'external_reference' => 'demo-guest-paid-6401',
                'paid_at' => $now->subHours(2),
                'payload' => ['mode' => 'demo', 'guest_checkout' => true],
            ],
            ticketDocumentService: $ticketDocumentService,
            ticketCode: 'SMGUEST4301F2',
            customerEmail: 'guest.ticket@example.com',
            guestAccessToken: 'demo_guest_access_token_6401_please_show_ticket',
            ticketSentAt: $now->subHours(2)->addMinutes(2),
        );
    }

    private function cleanupDemoData(): void
    {
        $ticketPaths = Booking::query()
            ->whereNotNull('ticket_pdf_path')
            ->pluck('ticket_pdf_path')
            ->filter()
            ->all();

        foreach ($ticketPaths as $ticketPath) {
            Storage::disk((string) config('payments.tickets.disk', 'local'))->delete($ticketPath);
        }

        Payment::query()->delete();
        BookingItem::query()->delete();
        Booking::query()->delete();
        LoyaltyPointAccount::query()->delete();
        SessionSeat::query()->delete();
        SessionStandingArea::query()->delete();
        SessionSnapshot::query()->delete();
    }

    private function seedLoyaltyAccounts(): void
    {
        $now = now();

        $accounts = [
            [
                'user_id' => 1201,
                'balance' => 6000,
                'earned_total' => 6000,
                'spent_total' => 0,
            ],
            [
                'user_id' => 1202,
                'balance' => 1200,
                'earned_total' => 1200,
                'spent_total' => 0,
            ],
            [
                'user_id' => 1203,
                'balance' => 350,
                'earned_total' => 350,
                'spent_total' => 0,
            ],
        ];

        foreach ($accounts as $account) {
            LoyaltyPointAccount::query()->create([
                ...$account,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function createBooking(
        SessionSnapshot $snapshot,
        int $bookingId,
        ?int $userId,
        string $status,
        string $flowType,
        array $seatElementIds,
        array $standingSelections,
        ?\Carbon\CarbonInterface $reservedUntil,
        ?\Carbon\CarbonInterface $confirmedAt,
        \Carbon\CarbonInterface $createdAt,
        ?array $paymentData,
        TicketDocumentService $ticketDocumentService,
        ?string $ticketCode = null,
        ?\Carbon\CarbonInterface $ticketUsedAt = null,
        ?int $ticketUsedByOrganizerId = null,
        ?string $customerEmail = null,
        ?string $guestAccessToken = null,
        int $loyaltyPointsSpent = 0,
        ?int $loyaltyPointsEarned = null,
        ?\Carbon\CarbonInterface $ticketSentAt = null,
    ): void {
        $seatMap = SessionSeat::query()
            ->where('session_snapshot_id', $snapshot->id)
            ->whereIn('element_id', $seatElementIds)
            ->get()
            ->keyBy('element_id');

        $standingMap = SessionStandingArea::query()
            ->where('session_snapshot_id', $snapshot->id)
            ->whereIn('element_id', array_keys($standingSelections))
            ->get()
            ->keyBy('element_id');

        $totalAmount = 0.0;

        foreach ($seatElementIds as $seatElementId) {
            $seat = $seatMap->get($seatElementId);
            if ($seat !== null) {
                $totalAmount += (float) $seat->price;
            }
        }

        foreach ($standingSelections as $elementId => $quantity) {
            $area = $standingMap->get($elementId);
            if ($area !== null) {
                $totalAmount += (float) $area->price * $quantity;
            }
        }

        $subtotalAmount = round($totalAmount, 2);
        $maxPointsDiscount = (int) floor($subtotalAmount * ((float) config('booking.loyalty_max_discount_percent', 80) / 100));
        $loyaltyPointsSpent = $userId !== null
            ? min($loyaltyPointsSpent, $maxPointsDiscount, (int) floor($subtotalAmount))
            : 0;
        $discountAmount = (float) $loyaltyPointsSpent;
        $totalAmount = round(max(0, $subtotalAmount - $discountAmount), 2);
        $loyaltyPointsEarned ??= ($userId !== null
            && $flowType === Booking::FLOW_PURCHASE
            && !in_array($status, [Booking::STATUS_CANCELLED, Booking::STATUS_EXPIRED], true))
            ? (int) floor($subtotalAmount * ((float) config('booking.loyalty_earn_percent', 15) / 100))
            : 0;
        $loyaltyPointsAwardedAt = $status === Booking::STATUS_CONFIRMED && $userId !== null && $loyaltyPointsEarned > 0
            ? ($confirmedAt ?? $createdAt)
            : null;

        $booking = Booking::unguarded(fn () => Booking::query()->create([
            'id' => $bookingId,
            'user_id' => $userId,
            'customer_email' => $customerEmail,
            'guest_access_token' => $guestAccessToken,
            'session_snapshot_id' => $snapshot->id,
            'status' => $status,
            'flow_type' => $flowType,
            'subtotal_amount' => $subtotalAmount,
            'discount_amount' => $discountAmount,
            'loyalty_points_spent' => $loyaltyPointsSpent,
            'loyalty_points_earned' => $loyaltyPointsEarned,
            'loyalty_points_awarded_at' => $loyaltyPointsAwardedAt,
            'total_amount' => $totalAmount,
            'currency' => 'RUB',
            'ticket_code' => $ticketCode,
            'reserved_until' => $reservedUntil,
            'confirmed_at' => $confirmedAt,
            'ticket_sent_at' => $ticketSentAt,
            'cancelled_at' => $status === Booking::STATUS_CANCELLED ? $createdAt->copy()->addMinutes(15) : null,
        ]));

        $booking->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $confirmedAt ?? $createdAt,
        ])->save();

        foreach ($seatElementIds as $seatElementId) {
            /** @var SessionSeat|null $seat */
            $seat = $seatMap->get($seatElementId);

            if ($seat === null) {
                continue;
            }

            if (in_array($status, [Booking::STATUS_CONFIRMED, Booking::STATUS_RESERVED, Booking::STATUS_PAYMENT_PENDING], true)) {
                $seat->update([
                    'status' => $status === Booking::STATUS_CONFIRMED
                        ? SessionSeat::STATUS_BOOKED
                        : SessionSeat::STATUS_RESERVED,
                    'booking_id' => $booking->id,
                    'reserved_until' => $status === Booking::STATUS_CONFIRMED ? null : $reservedUntil,
                ]);
            }

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
            /** @var SessionStandingArea|null $area */
            $area = $standingMap->get($elementId);

            if ($area === null) {
                continue;
            }

            if (in_array($status, [Booking::STATUS_CONFIRMED, Booking::STATUS_RESERVED, Booking::STATUS_PAYMENT_PENDING], true)) {
                $area->update([
                    'capacity_available' => max(0, $area->capacity_available - $quantity),
                ]);
            }

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

        if ($paymentData !== null) {
            $payment = Payment::query()->create([
                'booking_id' => $booking->id,
                'provider' => 'mock',
                'status' => $paymentData['status'],
                'amount' => $totalAmount,
                'currency' => 'RUB',
                'external_reference' => $paymentData['external_reference'] ?? null,
                'confirmation_url' => $paymentData['confirmation_url'] ?? null,
                'failure_reason' => $paymentData['failure_reason'] ?? null,
                'payload' => $paymentData['payload'] ?? null,
                'paid_at' => $paymentData['paid_at'] ?? null,
                'cancelled_at' => $paymentData['cancelled_at'] ?? null,
                'last_synced_at' => $confirmedAt ?? $createdAt,
            ]);

            $payment->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $paymentData['paid_at'] ?? $paymentData['cancelled_at'] ?? $createdAt,
            ])->save();
        }

        $this->applySeededLoyaltyEffects(
            userId: $userId,
            loyaltyPointsSpent: $loyaltyPointsSpent,
            loyaltyPointsEarned: $loyaltyPointsAwardedAt !== null ? $loyaltyPointsEarned : 0,
        );

        if ($status === Booking::STATUS_CONFIRMED) {
            $issuedBooking = $ticketDocumentService->issueForBooking(
                $booking->fresh(['snapshot', 'items.seat', 'items.standingArea', 'payment'])
            );

            if ($ticketUsedAt !== null) {
                $issuedBooking->update([
                    'ticket_used_at' => $ticketUsedAt,
                    'ticket_used_by_organizer_id' => $ticketUsedByOrganizerId,
                ]);
            }
        }
    }

    private function applySeededLoyaltyEffects(?int $userId, int $loyaltyPointsSpent, int $loyaltyPointsEarned): void
    {
        if ($userId === null || ($loyaltyPointsSpent < 1 && $loyaltyPointsEarned < 1)) {
            return;
        }

        $account = LoyaltyPointAccount::query()->firstOrCreate(
            ['user_id' => $userId],
            ['balance' => 0, 'earned_total' => 0, 'spent_total' => 0],
        );

        $account->update([
            'balance' => max(0, $account->balance - $loyaltyPointsSpent) + $loyaltyPointsEarned,
            'earned_total' => $account->earned_total + $loyaltyPointsEarned,
            'spent_total' => $account->spent_total + $loyaltyPointsSpent,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $elements
     */
    private function seedInventory(SessionSnapshot $snapshot, array $elements): void
    {
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $type = (string) ($element['type'] ?? '');
            $elementId = trim((string) ($element['id'] ?? ''));

            if ($elementId === '') {
                continue;
            }

            if (in_array($type, ['seat', 'vip_seat'], true)) {
                SessionSeat::query()->create([
                    'session_snapshot_id' => $snapshot->id,
                    'element_id' => $elementId,
                    'type' => $type,
                    'label' => (string) ($element['label'] ?? 'Seat'),
                    'level_id' => $this->nullableString($element['level_id'] ?? null),
                    'row_label' => $this->nullableString($element['row'] ?? null),
                    'seat_number' => $this->nullableString($element['number'] ?? null),
                    'price' => $type === 'vip_seat'
                        ? round((float) $snapshot->base_price * 1.5, 2)
                        : (float) $snapshot->base_price,
                    'status' => SessionSeat::STATUS_FREE,
                ]);

                continue;
            }

            if (in_array($type, ['dancefloor', 'table'], true)) {
                $capacity = (int) ($element['capacity'] ?? ($type === 'table' ? 2 : 0));

                if ($capacity < 1) {
                    continue;
                }

                SessionStandingArea::query()->create([
                    'session_snapshot_id' => $snapshot->id,
                    'element_id' => $elementId,
                    'label' => (string) ($element['label'] ?? ($type === 'table' ? 'Table' : 'Dancefloor')),
                    'level_id' => $this->nullableString($element['level_id'] ?? null),
                    'price' => (float) $snapshot->base_price,
                    'capacity_total' => $capacity,
                    'capacity_available' => $capacity,
                ]);
            }
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRooftopConcertLayout(): array
    {
        return [
            'canvas' => ['width' => 960, 'height' => 640],
            'levels' => [
                ['id' => 'parter', 'name' => 'Партер', 'order' => 1],
            ],
            'elements' => [
                ['id' => 'stage-main', 'type' => 'stage', 'label' => 'Главная сцена', 'x' => 340, 'y' => 48, 'width' => 280, 'height' => 96],
                ['id' => 'dancefloor-main', 'type' => 'dancefloor', 'label' => 'Dancefloor', 'x' => 340, 'y' => 160, 'width' => 280, 'height' => 170, 'capacity' => 80, 'level_id' => null],
                ['id' => 'seat-a1', 'type' => 'seat', 'label' => 'A-1', 'x' => 340, 'y' => 350, 'width' => 42, 'height' => 42, 'row' => 'A', 'number' => '1', 'level_id' => 'parter'],
                ['id' => 'seat-a2', 'type' => 'seat', 'label' => 'A-2', 'x' => 400, 'y' => 350, 'width' => 42, 'height' => 42, 'row' => 'A', 'number' => '2', 'level_id' => 'parter'],
                ['id' => 'seat-a3', 'type' => 'seat', 'label' => 'A-3', 'x' => 460, 'y' => 350, 'width' => 42, 'height' => 42, 'row' => 'A', 'number' => '3', 'level_id' => 'parter'],
                ['id' => 'seat-a4', 'type' => 'seat', 'label' => 'A-4', 'x' => 520, 'y' => 350, 'width' => 42, 'height' => 42, 'row' => 'A', 'number' => '4', 'level_id' => 'parter'],
                ['id' => 'seat-a5', 'type' => 'seat', 'label' => 'A-5', 'x' => 580, 'y' => 350, 'width' => 42, 'height' => 42, 'row' => 'A', 'number' => '5', 'level_id' => 'parter'],
                ['id' => 'vip-b1', 'type' => 'vip_seat', 'label' => 'VIP B-1', 'x' => 370, 'y' => 420, 'width' => 44, 'height' => 44, 'row' => 'B', 'number' => '1', 'level_id' => 'parter'],
                ['id' => 'vip-b2', 'type' => 'vip_seat', 'label' => 'VIP B-2', 'x' => 430, 'y' => 420, 'width' => 44, 'height' => 44, 'row' => 'B', 'number' => '2', 'level_id' => 'parter'],
                ['id' => 'vip-b3', 'type' => 'vip_seat', 'label' => 'VIP B-3', 'x' => 490, 'y' => 420, 'width' => 44, 'height' => 44, 'row' => 'B', 'number' => '3', 'level_id' => 'parter'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildComedyHallLayout(): array
    {
        return [
            'canvas' => ['width' => 960, 'height' => 640],
            'levels' => [
                ['id' => 'main', 'name' => 'Основной зал', 'order' => 1],
                ['id' => 'balcony', 'name' => 'Балкон', 'order' => 2],
            ],
            'elements' => [
                ['id' => 'stage-standup', 'type' => 'stage', 'label' => 'Comedy Stage', 'x' => 320, 'y' => 52, 'width' => 320, 'height' => 100],
                ['id' => 'seat-c1', 'type' => 'seat', 'label' => 'C-1', 'x' => 300, 'y' => 240, 'width' => 42, 'height' => 42, 'row' => 'C', 'number' => '1', 'level_id' => 'main'],
                ['id' => 'seat-c2', 'type' => 'seat', 'label' => 'C-2', 'x' => 360, 'y' => 240, 'width' => 42, 'height' => 42, 'row' => 'C', 'number' => '2', 'level_id' => 'main'],
                ['id' => 'seat-c3', 'type' => 'seat', 'label' => 'C-3', 'x' => 420, 'y' => 240, 'width' => 42, 'height' => 42, 'row' => 'C', 'number' => '3', 'level_id' => 'main'],
                ['id' => 'seat-c4', 'type' => 'seat', 'label' => 'C-4', 'x' => 480, 'y' => 240, 'width' => 42, 'height' => 42, 'row' => 'C', 'number' => '4', 'level_id' => 'main'],
                ['id' => 'seat-c5', 'type' => 'seat', 'label' => 'C-5', 'x' => 540, 'y' => 240, 'width' => 42, 'height' => 42, 'row' => 'C', 'number' => '5', 'level_id' => 'main'],
                ['id' => 'seat-d1', 'type' => 'seat', 'label' => 'D-1', 'x' => 330, 'y' => 320, 'width' => 42, 'height' => 42, 'row' => 'D', 'number' => '1', 'level_id' => 'main'],
                ['id' => 'seat-d2', 'type' => 'seat', 'label' => 'D-2', 'x' => 390, 'y' => 320, 'width' => 42, 'height' => 42, 'row' => 'D', 'number' => '2', 'level_id' => 'main'],
                ['id' => 'seat-d3', 'type' => 'seat', 'label' => 'D-3', 'x' => 450, 'y' => 320, 'width' => 42, 'height' => 42, 'row' => 'D', 'number' => '3', 'level_id' => 'main'],
                ['id' => 'seat-d4', 'type' => 'seat', 'label' => 'D-4', 'x' => 510, 'y' => 320, 'width' => 42, 'height' => 42, 'row' => 'D', 'number' => '4', 'level_id' => 'main'],
                ['id' => 'table-main-1', 'type' => 'table', 'label' => 'Table 1', 'x' => 610, 'y' => 300, 'width' => 118, 'height' => 86, 'capacity' => 4, 'level_id' => 'main'],
                ['id' => 'vip-e1', 'type' => 'vip_seat', 'label' => 'VIP E-1', 'x' => 360, 'y' => 420, 'width' => 44, 'height' => 44, 'row' => 'E', 'number' => '1', 'level_id' => 'balcony'],
                ['id' => 'vip-e2', 'type' => 'vip_seat', 'label' => 'VIP E-2', 'x' => 430, 'y' => 420, 'width' => 44, 'height' => 44, 'row' => 'E', 'number' => '2', 'level_id' => 'balcony'],
                ['id' => 'vip-e3', 'type' => 'vip_seat', 'label' => 'VIP E-3', 'x' => 500, 'y' => 420, 'width' => 44, 'height' => 44, 'row' => 'E', 'number' => '3', 'level_id' => 'balcony'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBlackBoxLayout(): array
    {
        return [
            'canvas' => ['width' => 960, 'height' => 640],
            'levels' => [
                ['id' => 'immersive', 'name' => 'Иммерсивный круг', 'order' => 1],
            ],
            'elements' => [
                ['id' => 'stage-blackbox', 'type' => 'stage', 'label' => 'Black Box Stage', 'x' => 280, 'y' => 60, 'width' => 400, 'height' => 90],
                ['id' => 'seat-f1', 'type' => 'seat', 'label' => 'F-1', 'x' => 280, 'y' => 260, 'width' => 42, 'height' => 42, 'row' => 'F', 'number' => '1', 'level_id' => 'immersive'],
                ['id' => 'seat-f2', 'type' => 'seat', 'label' => 'F-2', 'x' => 340, 'y' => 260, 'width' => 42, 'height' => 42, 'row' => 'F', 'number' => '2', 'level_id' => 'immersive'],
                ['id' => 'seat-f3', 'type' => 'seat', 'label' => 'F-3', 'x' => 400, 'y' => 260, 'width' => 42, 'height' => 42, 'row' => 'F', 'number' => '3', 'level_id' => 'immersive'],
                ['id' => 'seat-f4', 'type' => 'seat', 'label' => 'F-4', 'x' => 460, 'y' => 260, 'width' => 42, 'height' => 42, 'row' => 'F', 'number' => '4', 'level_id' => 'immersive'],
                ['id' => 'seat-f5', 'type' => 'seat', 'label' => 'F-5', 'x' => 520, 'y' => 260, 'width' => 42, 'height' => 42, 'row' => 'F', 'number' => '5', 'level_id' => 'immersive'],
                ['id' => 'seat-f6', 'type' => 'seat', 'label' => 'F-6', 'x' => 580, 'y' => 260, 'width' => 42, 'height' => 42, 'row' => 'F', 'number' => '6', 'level_id' => 'immersive'],
            ],
        ];
    }
}
