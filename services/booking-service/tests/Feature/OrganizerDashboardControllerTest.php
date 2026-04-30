<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\SessionSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrganizerDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_booking_analytics_for_current_organizer(): void
    {
        $this->fakeAuthUsers([
            'token-organizer-77' => [
                'id' => 77,
                'full_name' => 'Organizer One',
                'email' => 'organizer1@example.com',
                'status' => 1,
                'role' => [
                    'role' => 'organizer',
                ],
            ],
        ]);

        $mainSnapshot = $this->createSnapshot(77, 101, 'Rock Night', 'Main Hall');
        $otherSnapshot = $this->createSnapshot(88, 205, 'Foreign Event', 'Foreign Hall');

        $confirmedBooking = Booking::query()->create([
            'user_id' => 501,
            'session_snapshot_id' => $mainSnapshot->id,
            'status' => Booking::STATUS_CONFIRMED,
            'flow_type' => Booking::FLOW_PURCHASE,
            'total_amount' => 3200,
            'currency' => 'RUB',
            'ticket_code' => 'TICKET-AN-001',
            'confirmed_at' => now()->subDay(),
            'ticket_issued_at' => now()->subDay(),
            'ticket_used_at' => now()->subHours(12),
            'ticket_used_by_organizer_id' => 77,
        ]);

        BookingItem::query()->create([
            'booking_id' => $confirmedBooking->id,
            'item_type' => BookingItem::TYPE_SEAT,
            'external_element_id' => 'seat-a1',
            'label' => 'A-1',
            'quantity' => 2,
            'unit_price' => 1600,
            'total_price' => 3200,
            'meta' => [],
        ]);

        $reservedBooking = Booking::query()->create([
            'user_id' => 502,
            'session_snapshot_id' => $mainSnapshot->id,
            'status' => Booking::STATUS_RESERVED,
            'flow_type' => Booking::FLOW_RESERVATION,
            'total_amount' => 1500,
            'currency' => 'RUB',
            'reserved_until' => now()->addMinutes(10),
        ]);

        BookingItem::query()->create([
            'booking_id' => $reservedBooking->id,
            'item_type' => BookingItem::TYPE_STANDING,
            'external_element_id' => 'dancefloor-main',
            'label' => 'Dancefloor',
            'quantity' => 1,
            'unit_price' => 1500,
            'total_price' => 1500,
            'meta' => [],
        ]);

        $foreignBooking = Booking::query()->create([
            'user_id' => 503,
            'session_snapshot_id' => $otherSnapshot->id,
            'status' => Booking::STATUS_CONFIRMED,
            'flow_type' => Booking::FLOW_PURCHASE,
            'total_amount' => 9999,
            'currency' => 'RUB',
            'ticket_code' => 'TICKET-FOREIGN-001',
            'confirmed_at' => now()->subDay(),
            'ticket_issued_at' => now()->subDay(),
        ]);

        BookingItem::query()->create([
            'booking_id' => $foreignBooking->id,
            'item_type' => BookingItem::TYPE_SEAT,
            'external_element_id' => 'foreign-seat',
            'label' => 'F-1',
            'quantity' => 1,
            'unit_price' => 9999,
            'total_price' => 9999,
            'meta' => [],
        ]);

        $this
            ->withToken('token-organizer-77')
            ->getJson('/api/organizer/dashboard')
            ->assertOk()
            ->assertJsonPath('metrics.bookings_total', 2)
            ->assertJsonPath('metrics.bookings_confirmed', 1)
            ->assertJsonPath('metrics.active_reservations', 1)
            ->assertJsonPath('metrics.tickets_sold', 2)
            ->assertJsonPath('metrics.tickets_used', 2)
            ->assertJsonPath('metrics.revenue_total', 3200)
            ->assertJsonPath('recent_bookings.0.event_title', 'Rock Night')
            ->assertJsonPath('top_events.0.event_title', 'Rock Night')
            ->assertJsonPath('recent_check_ins.0.ticket_code', 'TICKET-AN-001');
    }

    private function createSnapshot(int $organizerId, int $eventId, string $eventTitle, string $hallName): SessionSnapshot
    {
        return SessionSnapshot::query()->create([
            'event_session_id' => random_int(1000, 9000),
            'event_id' => $eventId,
            'organizer_id' => $organizerId,
            'hall_id' => 55,
            'event_title' => $eventTitle,
            'event_category_name' => 'Concert',
            'event_category_slug' => 'concert',
            'event_age_rating_label' => '16+',
            'event_min_age' => 16,
            'hall_name' => $hallName,
            'hall_address' => 'Moscow, Center 1',
            'hall_layout' => [],
            'base_price' => 1500,
            'currency' => 'RUB',
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(2),
            'status' => 'scheduled',
            'synced_at' => now(),
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $users
     */
    private function fakeAuthUsers(array $users): void
    {
        Http::preventStrayRequests();

        Http::fake(function (HttpRequest $request) use ($users) {
            if ($request->url() === 'http://127.0.0.1:8000/api/me') {
                $token = str_replace('Bearer ', '', $request->header('Authorization')[0] ?? '');

                if (!isset($users[$token])) {
                    return Http::response([], 401);
                }

                return Http::response([
                    'user' => $users[$token],
                ], 200);
            }

            return Http::response([], 404);
        });
    }
}
