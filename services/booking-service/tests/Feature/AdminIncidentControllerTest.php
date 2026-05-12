<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\SessionSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminIncidentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_problem_incidents(): void
    {
        $this->fakeAdminAuth();

        $snapshot = SessionSnapshot::query()->create([
            'event_session_id' => 11,
            'event_id' => 99,
            'hall_id' => 5,
            'organizer_id' => 44,
            'event_title' => 'Jazz Night',
            'hall_name' => 'Blue Hall',
            'hall_address' => 'Main street',
            'hall_layout' => [
                'elements' => [],
                'levels' => [],
            ],
            'base_price' => 2200,
            'currency' => 'RUB',
            'status' => 'scheduled',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        $booking = Booking::query()->create([
            'user_id' => 7,
            'session_snapshot_id' => $snapshot->id,
            'status' => Booking::STATUS_PAYMENT_PENDING,
            'flow_type' => Booking::FLOW_PURCHASE,
            'total_amount' => 2200,
            'currency' => 'RUB',
        ]);

        Payment::query()->create([
            'booking_id' => $booking->id,
            'provider' => 'mock',
            'status' => Payment::STATUS_FAILED,
            'amount' => 2200,
            'currency' => 'RUB',
            'external_reference' => 'pay_fail_123',
            'failure_reason' => 'Card was declined',
        ]);

        $this->withToken('admin-token')
            ->getJson('/api/admin/incidents')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.snapshot.event_title', 'Jazz Night')
            ->assertJsonPath('data.0.status', Payment::STATUS_FAILED);
    }

    public function test_admin_can_search_problem_incidents(): void
    {
        $this->fakeAdminAuth();

        $matchingSnapshot = SessionSnapshot::query()->create([
            'event_session_id' => 12,
            'event_id' => 100,
            'hall_id' => 6,
            'organizer_id' => 45,
            'event_title' => 'Festival Heat',
            'hall_name' => 'Red Hall',
            'hall_address' => 'Center',
            'hall_layout' => [
                'elements' => [],
                'levels' => [],
            ],
            'base_price' => 1800,
            'currency' => 'RUB',
            'status' => 'scheduled',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        $matchingBooking = Booking::query()->create([
            'user_id' => 8,
            'session_snapshot_id' => $matchingSnapshot->id,
            'status' => Booking::STATUS_PAYMENT_PENDING,
            'flow_type' => Booking::FLOW_PURCHASE,
            'total_amount' => 1800,
            'currency' => 'RUB',
        ]);

        Payment::query()->create([
            'booking_id' => $matchingBooking->id,
            'provider' => 'mock',
            'status' => Payment::STATUS_CANCELLED,
            'amount' => 1800,
            'currency' => 'RUB',
            'external_reference' => 'festival_ref',
            'failure_reason' => 'Cancelled by user',
        ]);

        $otherSnapshot = SessionSnapshot::query()->create([
            'event_session_id' => 13,
            'event_id' => 101,
            'hall_id' => 7,
            'organizer_id' => 46,
            'event_title' => 'Business Forum',
            'hall_name' => 'White Hall',
            'hall_address' => 'North',
            'hall_layout' => [
                'elements' => [],
                'levels' => [],
            ],
            'base_price' => 2500,
            'currency' => 'RUB',
            'status' => 'scheduled',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        $otherBooking = Booking::query()->create([
            'user_id' => 9,
            'session_snapshot_id' => $otherSnapshot->id,
            'status' => Booking::STATUS_PAYMENT_PENDING,
            'flow_type' => Booking::FLOW_PURCHASE,
            'total_amount' => 2500,
            'currency' => 'RUB',
        ]);

        Payment::query()->create([
            'booking_id' => $otherBooking->id,
            'provider' => 'mock',
            'status' => Payment::STATUS_FAILED,
            'amount' => 2500,
            'currency' => 'RUB',
            'external_reference' => 'forum_ref',
            'failure_reason' => 'Technical failure',
        ]);

        $this->withToken('admin-token')
            ->getJson('/api/admin/incidents?search=Festival')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.snapshot.event_title', 'Festival Heat');
    }

    private function fakeAdminAuth(): void
    {
        Http::preventStrayRequests();

        Http::fake(function (HttpRequest $request) {
            if ($request->url() === 'http://127.0.0.1:8000/api/me') {
                return Http::response([
                    'user' => [
                        'id' => 1,
                        'full_name' => 'Platform Admin',
                        'email' => 'admin@example.com',
                        'role' => [
                            'role' => 'admin',
                        ],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });
    }
}
