<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Payment;
use App\Models\SessionSeat;
use App\Models\SessionSnapshot;
use App\Models\SessionStandingArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireReservationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_expires_stale_reservations_and_releases_inventory(): void
    {
        $snapshot = SessionSnapshot::query()->create([
            'event_session_id' => 901,
            'event_id' => 77,
            'organizer_id' => 12,
            'hall_id' => 45,
            'event_title' => 'Late Night Show',
            'hall_name' => 'Arena',
            'hall_layout' => [],
            'base_price' => 1200,
            'currency' => 'RUB',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'status' => 'scheduled',
            'synced_at' => now(),
        ]);

        $seat = SessionSeat::query()->create([
            'session_snapshot_id' => $snapshot->id,
            'element_id' => 'seat-a1',
            'type' => 'seat',
            'label' => 'A-1',
            'price' => 1200,
            'status' => SessionSeat::STATUS_RESERVED,
        ]);

        $standingArea = SessionStandingArea::query()->create([
            'session_snapshot_id' => $snapshot->id,
            'element_id' => 'dance-1',
            'label' => 'Dancefloor',
            'price' => 1200,
            'capacity_total' => 10,
            'capacity_available' => 7,
        ]);

        $booking = Booking::query()->create([
            'user_id' => 501,
            'session_snapshot_id' => $snapshot->id,
            'status' => Booking::STATUS_PAYMENT_PENDING,
            'flow_type' => Booking::FLOW_PURCHASE,
            'total_amount' => 4800,
            'currency' => 'RUB',
            'reserved_until' => now()->subMinute(),
        ]);

        $seat->update([
            'booking_id' => $booking->id,
            'reserved_until' => now()->subMinute(),
        ]);

        BookingItem::query()->create([
            'booking_id' => $booking->id,
            'item_type' => BookingItem::TYPE_SEAT,
            'session_seat_id' => $seat->id,
            'external_element_id' => 'seat-a1',
            'label' => 'A-1',
            'quantity' => 1,
            'unit_price' => 1200,
            'total_price' => 1200,
        ]);

        BookingItem::query()->create([
            'booking_id' => $booking->id,
            'item_type' => BookingItem::TYPE_STANDING,
            'session_standing_area_id' => $standingArea->id,
            'external_element_id' => 'dance-1',
            'label' => 'Dancefloor',
            'quantity' => 3,
            'unit_price' => 1200,
            'total_price' => 3600,
        ]);

        Payment::query()->create([
            'booking_id' => $booking->id,
            'provider' => 'mock',
            'status' => Payment::STATUS_PENDING,
            'amount' => 4800,
            'currency' => 'RUB',
        ]);

        $this->artisan('bookings:expire-reservations')
            ->expectsOutput('Expired 1 booking reservation(s).')
            ->assertSuccessful();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => Booking::STATUS_EXPIRED,
        ]);

        $this->assertDatabaseHas('session_seats', [
            'id' => $seat->id,
            'status' => SessionSeat::STATUS_FREE,
            'booking_id' => null,
        ]);

        $this->assertDatabaseHas('session_standing_areas', [
            'id' => $standingArea->id,
            'capacity_available' => 10,
        ]);

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'status' => Payment::STATUS_FAILED,
        ]);
    }
}
