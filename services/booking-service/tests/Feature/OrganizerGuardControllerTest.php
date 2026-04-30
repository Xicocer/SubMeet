<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\SessionSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrganizerGuardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_booking_impact_returns_confirmed_and_reserved_counts(): void
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

        $snapshot = $this->createSnapshot(77, 101, 7001);

        $confirmedBooking = Booking::query()->create([
            'user_id' => 501,
            'session_snapshot_id' => $snapshot->id,
            'status' => Booking::STATUS_CONFIRMED,
            'flow_type' => Booking::FLOW_PURCHASE,
            'total_amount' => 3200,
            'currency' => 'RUB',
            'ticket_code' => 'TICKET-001',
            'confirmed_at' => now()->subDay(),
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
            'session_snapshot_id' => $snapshot->id,
            'status' => Booking::STATUS_RESERVED,
            'flow_type' => Booking::FLOW_RESERVATION,
            'total_amount' => 1500,
            'currency' => 'RUB',
            'reserved_until' => now()->addMinutes(10),
        ]);

        BookingItem::query()->create([
            'booking_id' => $reservedBooking->id,
            'item_type' => BookingItem::TYPE_STANDING,
            'external_element_id' => 'dancefloor',
            'label' => 'Dancefloor',
            'quantity' => 3,
            'unit_price' => 500,
            'total_price' => 1500,
            'meta' => [],
        ]);

        $this
            ->withToken('token-organizer-77')
            ->getJson('/api/organizer/guards/events/101/booking-impact')
            ->assertOk()
            ->assertJsonPath('event_id', 101)
            ->assertJsonPath('confirmed_bookings_count', 1)
            ->assertJsonPath('confirmed_tickets_count', 2)
            ->assertJsonPath('active_reservations_count', 1)
            ->assertJsonPath('active_reserved_tickets_count', 3);
    }

    private function createSnapshot(int $organizerId, int $eventId, int $eventSessionId): SessionSnapshot
    {
        return SessionSnapshot::query()->create([
            'event_session_id' => $eventSessionId,
            'event_id' => $eventId,
            'organizer_id' => $organizerId,
            'hall_id' => 55,
            'event_title' => 'Rock Night',
            'event_category_name' => 'Concert',
            'event_category_slug' => 'concert',
            'event_age_rating_label' => '16+',
            'event_min_age' => 16,
            'hall_name' => 'Main Hall',
            'hall_address' => 'Moscow, Tverskaya 1',
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
