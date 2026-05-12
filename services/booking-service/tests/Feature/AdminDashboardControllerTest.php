<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Payment;
use App\Models\SessionSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_returns_platform_booking_metrics(): void
    {
        $this->fakeAdminAuth();

        $snapshot = SessionSnapshot::query()->create([
            'event_session_id' => 1001,
            'event_id' => 701,
            'organizer_id' => 77,
            'hall_id' => 33,
            'event_title' => 'Rock Night',
            'event_category_name' => 'Concert',
            'event_category_slug' => 'concert',
            'event_age_rating_label' => '16+',
            'event_min_age' => 16,
            'hall_name' => 'Main Hall',
            'hall_address' => 'Moscow, Center 1',
            'hall_layout' => [],
            'base_price' => 1500,
            'currency' => 'RUB',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'status' => 'scheduled',
            'synced_at' => now(),
        ]);

        $booking = Booking::query()->create([
            'user_id' => 500,
            'session_snapshot_id' => $snapshot->id,
            'status' => Booking::STATUS_CONFIRMED,
            'flow_type' => Booking::FLOW_PURCHASE,
            'total_amount' => 4200,
            'currency' => 'RUB',
            'confirmed_at' => now(),
        ]);

        BookingItem::query()->create([
            'booking_id' => $booking->id,
            'item_type' => BookingItem::TYPE_SEAT,
            'external_element_id' => 'seat-a1',
            'label' => 'A-1',
            'quantity' => 2,
            'unit_price' => 2100,
            'total_price' => 4200,
            'meta' => [],
        ]);

        Payment::query()->create([
            'booking_id' => $booking->id,
            'provider' => 'mock',
            'status' => Payment::STATUS_FAILED,
            'amount' => 4200,
            'currency' => 'RUB',
            'failure_reason' => 'Card declined',
        ]);

        $this->withToken('admin-token')
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('metrics.bookings_total', 1)
            ->assertJsonPath('metrics.bookings_confirmed', 1)
            ->assertJsonPath('metrics.tickets_sold', 2)
            ->assertJsonPath('metrics.payments_failed', 1)
            ->assertJsonPath('recent_problem_payments.0.status', Payment::STATUS_FAILED);
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
