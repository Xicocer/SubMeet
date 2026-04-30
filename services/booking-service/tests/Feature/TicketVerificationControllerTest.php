<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\SessionSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TicketVerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_can_validate_own_ticket(): void
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

        $booking = $this->createConfirmedTicketBooking(77, 'TICKET-ABC-001');

        $this
            ->withToken('token-organizer-77')
            ->postJson('/api/organizer/tickets/verify', [
                'ticket_code' => json_encode(['ticket_code' => 'TICKET-ABC-001'], JSON_UNESCAPED_UNICODE),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'validated')
            ->assertJsonPath('booking.id', $booking->id)
            ->assertJsonPath('booking.ticket.code', 'TICKET-ABC-001');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'ticket_code' => 'TICKET-ABC-001',
            'ticket_used_by_organizer_id' => 77,
        ]);
    }

    public function test_repeated_verification_returns_already_used(): void
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

        $booking = $this->createConfirmedTicketBooking(77, 'TICKET-ABC-002');
        $booking->update([
            'ticket_used_at' => now()->subMinute(),
            'ticket_used_by_organizer_id' => 77,
        ]);

        $this
            ->withToken('token-organizer-77')
            ->postJson('/api/organizer/tickets/verify', [
                'ticket_code' => 'TICKET-ABC-002',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'already_used');
    }

    public function test_other_organizer_cannot_validate_foreign_ticket(): void
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
            'token-organizer-88' => [
                'id' => 88,
                'full_name' => 'Organizer Two',
                'email' => 'organizer2@example.com',
                'status' => 1,
                'role' => [
                    'role' => 'organizer',
                ],
            ],
        ]);

        $this->createConfirmedTicketBooking(77, 'TICKET-ABC-003');

        $this
            ->withToken('token-organizer-88')
            ->postJson('/api/organizer/tickets/verify', [
                'ticket_code' => 'TICKET-ABC-003',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'This ticket belongs to another organizer event.');
    }

    public function test_regular_user_cannot_access_ticket_verification(): void
    {
        $this->fakeAuthUsers([
            'token-user-1' => [
                'id' => 501,
                'full_name' => 'User',
                'email' => 'user@example.com',
                'status' => 1,
                'role' => [
                    'role' => 'user',
                ],
            ],
        ]);

        $this
            ->withToken('token-user-1')
            ->postJson('/api/organizer/tickets/verify', [
                'ticket_code' => 'TICKET-ABC-004',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Organizer access is required.');
    }

    private function createConfirmedTicketBooking(int $organizerId, string $ticketCode): Booking
    {
        $snapshot = SessionSnapshot::query()->create([
            'event_session_id' => random_int(1000, 9000),
            'event_id' => random_int(10, 99),
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

        return Booking::query()->create([
            'user_id' => 501,
            'session_snapshot_id' => $snapshot->id,
            'status' => Booking::STATUS_CONFIRMED,
            'flow_type' => Booking::FLOW_PURCHASE,
            'total_amount' => 1500,
            'currency' => 'RUB',
            'ticket_code' => $ticketCode,
            'ticket_pdf_path' => 'tickets/' . strtolower($ticketCode) . '.pdf',
            'ticket_issued_at' => now()->subMinute(),
            'confirmed_at' => now()->subMinutes(5),
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
