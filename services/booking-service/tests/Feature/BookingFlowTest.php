<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\LoyaltyPointAccount;
use App\Models\Payment;
use App\Models\SessionSeat;
use App\Models\SessionStandingArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_availability_creates_snapshot_and_returns_layout_state(): void
    {
        $this->fakeUpstream();

        $response = $this->getJson('/api/sessions/900/availability');

        $response
            ->assertOk()
            ->assertJsonPath('session.event_session_id', 900)
            ->assertJsonPath('session.event_title', 'Rock Night')
            ->assertJsonPath('summary.seats_total', 2)
            ->assertJsonPath('summary.seats_free', 2)
            ->assertJsonPath('summary.standing_total', 10)
            ->assertJsonPath('summary.standing_available', 10);

        $this->assertDatabaseHas('session_snapshots', [
            'event_session_id' => 900,
            'event_title' => 'Rock Night',
        ]);

        $this->assertDatabaseCount('session_seats', 2);
        $this->assertDatabaseCount('session_standing_areas', 1);
    }

    public function test_store_creates_pending_booking_and_reserves_inventory(): void
    {
        $this->fakeUpstream();

        $response = $this
            ->withToken('token-user-1')
            ->postJson('/api/bookings', [
                'session_id' => 900,
                'seat_ids' => ['seat-a1', 'vip-a2'],
                'standing' => [
                    [
                        'element_id' => 'dance-1',
                        'quantity' => 2,
                    ],
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('booking.status', Booking::STATUS_RESERVED)
            ->assertJsonPath('booking.total_amount', '4500.00')
            ->assertJsonPath('booking.payment', null);

        $this->assertDatabaseHas('session_seats', [
            'element_id' => 'seat-a1',
            'status' => SessionSeat::STATUS_RESERVED,
        ]);

        $this->assertDatabaseHas('session_seats', [
            'element_id' => 'vip-a2',
            'status' => SessionSeat::STATUS_RESERVED,
        ]);

        $this->assertDatabaseHas('session_standing_areas', [
            'element_id' => 'dance-1',
            'capacity_available' => 8,
        ]);
    }

    public function test_availability_reflects_reduced_dancefloor_capacity_after_booking(): void
    {
        $this->fakeUpstream();

        $this
            ->withToken('token-user-1')
            ->postJson('/api/bookings', [
                'session_id' => 900,
                'standing' => [
                    [
                        'element_id' => 'dance-1',
                        'quantity' => 1,
                    ],
                ],
            ])
            ->assertCreated();

        $response = $this->getJson('/api/sessions/900/availability');

        $response
            ->assertOk()
            ->assertJsonPath('summary.standing_total', 10)
            ->assertJsonPath('summary.standing_available', 9)
            ->assertJsonPath('layout.elements.3.capacity_total', 10)
            ->assertJsonPath('layout.elements.3.capacity_available', 9);

        $cacheControl = (string) $response->headers->get('Cache-Control', '');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
    }

    public function test_store_rejects_double_booking_for_same_seat(): void
    {
        $this->fakeUpstream();

        $this
            ->withToken('token-user-1')
            ->postJson('/api/bookings', [
                'session_id' => 900,
                'seat_ids' => ['seat-a1'],
            ])
            ->assertCreated();

        $this
            ->withToken('token-user-2')
            ->postJson('/api/bookings', [
                'session_id' => 900,
                'seat_ids' => ['seat-a1'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('seat_ids');
    }

    public function test_purchase_checkout_flow_confirms_booking_after_status_refresh(): void
    {
        $this->fakeUpstream();

        $bookingId = $this
            ->withToken('token-user-1')
            ->postJson('/api/bookings/purchase', [
                'session_id' => 900,
                'seat_ids' => ['seat-a1'],
            ])
            ->assertCreated()
            ->json('booking.id');

        $this
            ->withToken('token-user-1')
            ->postJson("/api/bookings/{$bookingId}/pay")
            ->assertOk()
            ->assertJsonPath('booking.status', Booking::STATUS_PAYMENT_PENDING)
            ->assertJsonPath('booking.payment.status', Payment::STATUS_PENDING)
            ->assertJsonPath('booking.payment.confirmation_url', 'http://127.0.0.1:5173/checkout/' . $bookingId);

        $this
            ->withToken('token-user-1')
            ->postJson("/api/bookings/{$bookingId}/refresh-payment")
            ->assertOk()
            ->assertJsonPath('booking.status', Booking::STATUS_CONFIRMED)
            ->assertJsonPath('booking.payment.status', Payment::STATUS_PAID)
            ->assertJsonPath('booking.ticket.code', fn ($value) => is_string($value) && $value !== '');

        $this->assertDatabaseHas('session_seats', [
            'element_id' => 'seat-a1',
            'status' => SessionSeat::STATUS_BOOKED,
        ]);
    }

    public function test_guest_can_purchase_ticket_with_email_and_access_token(): void
    {
        $this->fakeUpstream();

        $response = $this
            ->postJson('/api/bookings/guest-purchase', [
                'session_id' => 900,
                'customer_email' => 'guest@example.com',
                'guest_birth_date' => '1990-05-10',
                'seat_ids' => ['seat-a1'],
            ])
            ->assertCreated()
            ->assertJsonPath('booking.user_id', null)
            ->assertJsonPath('booking.customer_email', 'guest@example.com')
            ->assertJsonPath('booking.is_guest', true)
            ->assertJsonPath('booking.total_amount', '1000.00')
            ->assertJsonPath('booking.guest_access_token', fn ($value) => is_string($value) && strlen($value) >= 32);

        $bookingId = $response->json('booking.id');
        $guestToken = $response->json('booking.guest_access_token');

        $this
            ->postJson("/api/guest/bookings/{$bookingId}/refresh-payment", [
                'token' => $guestToken,
            ])
            ->assertOk()
            ->assertJsonPath('booking.status', Booking::STATUS_CONFIRMED)
            ->assertJsonPath('booking.ticket.code', fn ($value) => is_string($value) && $value !== '');

        $this->assertDatabaseHas('session_seats', [
            'element_id' => 'seat-a1',
            'status' => SessionSeat::STATUS_BOOKED,
        ]);
    }

    public function test_registered_user_can_spend_and_earn_loyalty_points(): void
    {
        $this->fakeUpstream();

        LoyaltyPointAccount::query()->create([
            'user_id' => 501,
            'balance' => 1000,
            'earned_total' => 1000,
            'spent_total' => 0,
        ]);

        $bookingId = $this
            ->withToken('token-user-1')
            ->postJson('/api/bookings/purchase', [
                'session_id' => 900,
                'seat_ids' => ['seat-a1'],
                'loyalty_points_to_spend' => 900,
            ])
            ->assertCreated()
            ->assertJsonPath('booking.subtotal_amount', '1000.00')
            ->assertJsonPath('booking.discount_amount', '800.00')
            ->assertJsonPath('booking.total_amount', '200.00')
            ->assertJsonPath('booking.loyalty_points_spent', 800)
            ->assertJsonPath('booking.loyalty_points_earned', 150)
            ->json('booking.id');

        $this
            ->withToken('token-user-1')
            ->postJson("/api/bookings/{$bookingId}/refresh-payment")
            ->assertOk()
            ->assertJsonPath('booking.status', Booking::STATUS_CONFIRMED);

        $this->assertDatabaseHas('loyalty_point_accounts', [
            'user_id' => 501,
            'balance' => 350,
            'earned_total' => 1150,
            'spent_total' => 800,
        ]);
    }

    public function test_cancel_releases_seats_and_standing_capacity(): void
    {
        $this->fakeUpstream();

        $bookingId = $this
            ->withToken('token-user-1')
            ->postJson('/api/bookings', [
                'session_id' => 900,
                'seat_ids' => ['seat-a1'],
                'standing' => [
                    [
                        'element_id' => 'dance-1',
                        'quantity' => 3,
                    ],
                ],
            ])
            ->assertCreated()
            ->json('booking.id');

        $this
            ->withToken('token-user-1')
            ->postJson("/api/bookings/{$bookingId}/cancel")
            ->assertOk()
            ->assertJsonPath('booking.status', Booking::STATUS_CANCELLED)
            ->assertJsonPath('booking.payment', null);

        $this->assertDatabaseHas('session_seats', [
            'element_id' => 'seat-a1',
            'status' => SessionSeat::STATUS_FREE,
            'booking_id' => null,
        ]);

        $this->assertDatabaseHas('session_standing_areas', [
            'element_id' => 'dance-1',
            'capacity_available' => 10,
        ]);
    }

    public function test_store_rejects_underage_user(): void
    {
        $this->fakeUpstream([
            'token-user-3' => [
                'id' => 503,
                'full_name' => 'Teen User',
                'email' => 'teen@example.com',
                'birth_date' => '2014-02-10',
                'status' => 1,
                'role' => [
                    'role' => 'user',
                ],
            ],
        ]);

        $this
            ->withToken('token-user-3')
            ->postJson('/api/bookings', [
                'session_id' => 900,
                'seat_ids' => ['seat-a1'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('user');
    }

    /**
     * @param  array<string, array<string, mixed>>  $userOverrides
     */
    private function fakeUpstream(array $userOverrides = []): void
    {
        $users = array_merge([
            'token-user-1' => [
                'id' => 501,
                'full_name' => 'Adult User One',
                'email' => 'adult1@example.com',
                'birth_date' => '1995-07-14',
                'status' => 1,
                'role' => [
                    'role' => 'user',
                ],
            ],
            'token-user-2' => [
                'id' => 502,
                'full_name' => 'Adult User Two',
                'email' => 'adult2@example.com',
                'birth_date' => '1992-09-01',
                'status' => 1,
                'role' => [
                    'role' => 'user',
                ],
            ],
        ], $userOverrides);

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

            if ($request->url() === 'http://127.0.0.1:8001/api/sessions/900') {
                return Http::response($this->sessionPayload(), 200);
            }

            if ($request->url() === 'http://127.0.0.1:8002/api/halls/300') {
                return Http::response($this->hallPayload(), 200);
            }

            return Http::response([], 404);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionPayload(): array
    {
        return [
            'id' => 900,
            'event_id' => 44,
            'hall_id' => 300,
            'start_time' => now()->addDays(5)->toISOString(),
            'end_time' => now()->addDays(5)->addHours(2)->toISOString(),
            'base_price' => 1000.00,
            'status' => 'scheduled',
            'event' => [
                'id' => 44,
                'title' => 'Rock Night',
                'category' => [
                    'id' => 7,
                    'name' => 'Concert',
                    'slug' => 'concert',
                ],
                'age_rating' => [
                    'id' => 3,
                    'label' => '18+',
                    'min_age' => 18,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function hallPayload(): array
    {
        return [
            'id' => 300,
            'name' => 'Main Arena',
            'description' => 'Large concert hall.',
            'organizer_id' => 99,
            'status' => 'active',
            'capacities' => [
                'seat' => 1,
                'vip' => 1,
                'dancefloor' => 10,
                'total' => 12,
            ],
            'layout' => [
                'canvas' => [
                    'width' => 1200,
                    'height' => 800,
                ],
                'levels' => [
                    [
                        'id' => 'parterre',
                        'name' => 'Parterre',
                        'order' => 1,
                    ],
                ],
                'elements' => [
                    [
                        'id' => 'stage-1',
                        'type' => 'stage',
                        'label' => 'Main Stage',
                        'x' => 350,
                        'y' => 50,
                        'width' => 500,
                        'height' => 120,
                    ],
                    [
                        'id' => 'seat-a1',
                        'type' => 'seat',
                        'label' => 'A1',
                        'level_id' => 'parterre',
                        'row' => 'A',
                        'number' => '1',
                        'x' => 300,
                        'y' => 250,
                    ],
                    [
                        'id' => 'vip-a2',
                        'type' => 'vip_seat',
                        'label' => 'VIP A2',
                        'level_id' => 'parterre',
                        'row' => 'A',
                        'number' => '2',
                        'x' => 360,
                        'y' => 250,
                    ],
                    [
                        'id' => 'dance-1',
                        'type' => 'dancefloor',
                        'label' => 'Dancefloor',
                        'level_id' => 'parterre',
                        'capacity' => 10,
                        'x' => 250,
                        'y' => 380,
                        'width' => 700,
                        'height' => 220,
                    ],
                ],
            ],
        ];
    }
}
