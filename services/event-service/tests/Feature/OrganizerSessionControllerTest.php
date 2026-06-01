<?php

namespace Tests\Feature;

use App\Models\AgeRating;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrganizerSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_session_for_own_event(): void
    {
        Carbon::setTestNow('2026-04-24 10:00:00');
        $event = $this->createOrganizerEvent(77);
        $rentalRequest = $this->rentalRequestPayload(
            requestId: 501,
            hallId: 101,
            organizerId: 77,
            eventId: $event->id,
            startTime: Carbon::now()->addDay()->toDateTimeString(),
            endTime: Carbon::now()->addDay()->addHours(2)->toDateTimeString(),
        );
        $this->fakeOrganizerDependencies(77, [
            101 => $this->hallPayload(101, 77, 'Main Arena'),
        ], [], [
            501 => $rentalRequest,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer organizer-token')
            ->postJson("/api/organizer/events/{$event->id}/sessions", [
                'hall_rental_request_id' => 501,
                'base_price' => 2500,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('session.event_id', $event->id)
            ->assertJsonPath('session.hall_id', 101)
            ->assertJsonPath('session.hall.name', 'Main Arena')
            ->assertJsonPath('session.hall.address', 'Нижний Новгород, ул. Большая Покровская, 1')
            ->assertJsonPath('session.status', EventSession::STATUS_SCHEDULED);

        $this->assertDatabaseHas('event_sessions', [
            'event_id' => $event->id,
            'hall_id' => 101,
            'hall_rental_request_id' => 501,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        Carbon::setTestNow();
    }

    public function test_index_returns_sessions_for_current_organizer_event_with_hall_data(): void
    {
        Carbon::setTestNow('2026-04-24 10:00:00');
        $event = $this->createOrganizerEvent(77);
        $this->fakeOrganizerDependencies(77, [
            101 => $this->hallPayload(101, 77, 'Main Arena'),
            102 => $this->hallPayload(102, 77, 'VIP Hall'),
        ]);

        EventSession::query()->create([
            'event_id' => $event->id,
            'hall_id' => 101,
            'start_time' => Carbon::now()->addDay()->setTime(18, 0),
            'end_time' => Carbon::now()->addDay()->setTime(20, 0),
            'base_price' => 3000,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        EventSession::query()->create([
            'event_id' => $event->id,
            'hall_id' => 102,
            'start_time' => Carbon::now()->addDays(2)->setTime(18, 0),
            'end_time' => Carbon::now()->addDays(2)->setTime(20, 0),
            'base_price' => 3500,
            'status' => EventSession::STATUS_CANCELLED,
        ]);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->getJson("/api/organizer/events/{$event->id}/sessions")
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.hall_id', 101)
            ->assertJsonPath('0.hall.name', 'Main Arena')
            ->assertJsonPath('0.hall.address', 'Нижний Новгород, ул. Большая Покровская, 1')
            ->assertJsonPath('1.hall.name', 'VIP Hall')
            ->assertJsonPath('1.status', EventSession::STATUS_CANCELLED);

        Carbon::setTestNow();
    }

    public function test_store_returns_404_for_foreign_event(): void
    {
        Carbon::setTestNow('2026-04-24 10:00:00');
        $event = $this->createOrganizerEvent(88);
        $this->fakeOrganizerDependencies(77, [
            101 => $this->hallPayload(101, 77, 'Main Arena'),
        ]);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->postJson("/api/organizer/events/{$event->id}/sessions", [
                'hall_rental_request_id' => 501,
                'base_price' => 2500,
            ])
            ->assertNotFound();

        Carbon::setTestNow();
    }

    public function test_store_rejects_foreign_or_missing_hall(): void
    {
        Carbon::setTestNow('2026-04-24 10:00:00');
        $event = $this->createOrganizerEvent(77);
        $this->fakeOrganizerDependencies(77);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->postJson("/api/organizer/events/{$event->id}/sessions", [
                'hall_rental_request_id' => 999,
                'base_price' => 2500,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hall_rental_request_id']);

        Carbon::setTestNow();
    }

    public function test_store_rejects_intersecting_sessions_in_same_hall(): void
    {
        Carbon::setTestNow('2026-04-24 10:00:00');
        $event = $this->createOrganizerEvent(77);
        $rentalRequest = $this->rentalRequestPayload(
            requestId: 502,
            hallId: 101,
            organizerId: 77,
            eventId: $event->id,
            startTime: Carbon::now()->addDay()->setTime(19, 0)->toDateTimeString(),
            endTime: Carbon::now()->addDay()->setTime(21, 0)->toDateTimeString(),
        );
        $this->fakeOrganizerDependencies(77, [
            101 => $this->hallPayload(101, 77, 'Main Arena'),
        ], [], [
            502 => $rentalRequest,
        ]);

        EventSession::query()->create([
            'event_id' => $event->id,
            'hall_id' => 101,
            'start_time' => Carbon::now()->addDay()->setTime(18, 0),
            'end_time' => Carbon::now()->addDay()->setTime(20, 0),
            'base_price' => 3000,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->postJson("/api/organizer/events/{$event->id}/sessions", [
                'hall_rental_request_id' => 502,
                'base_price' => 3200,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hall_id']);

        Carbon::setTestNow();
    }

    public function test_update_edits_only_own_session(): void
    {
        Carbon::setTestNow('2026-04-24 10:00:00');
        $event = $this->createOrganizerEvent(77);
        $session = EventSession::query()->create([
            'event_id' => $event->id,
            'hall_id' => 101,
            'hall_rental_request_id' => 601,
            'start_time' => Carbon::now()->addDay()->setTime(18, 0),
            'end_time' => Carbon::now()->addDay()->setTime(20, 0),
            'base_price' => 3000,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);
        $rentalRequest = $this->rentalRequestPayload(
            requestId: 602,
            hallId: 102,
            organizerId: 77,
            eventId: $event->id,
            startTime: Carbon::now()->addDay()->setTime(21, 0)->toDateTimeString(),
            endTime: Carbon::now()->addDay()->setTime(23, 0)->toDateTimeString(),
        );

        $this->fakeOrganizerDependencies(77, [
            102 => $this->hallPayload(102, 77, 'Balcony Hall'),
        ], [], [
            602 => $rentalRequest,
        ]);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->putJson("/api/organizer/sessions/{$session->id}", [
                'hall_rental_request_id' => 602,
                'base_price' => 3500,
            ])
            ->assertOk()
            ->assertJsonPath('session.hall_id', 102)
            ->assertJsonPath('session.hall.name', 'Balcony Hall')
            ->assertJsonPath('session.hall.address', 'Нижний Новгород, ул. Большая Покровская, 3');

        $this->assertDatabaseHas('event_sessions', [
            'id' => $session->id,
            'hall_id' => 102,
            'hall_rental_request_id' => 602,
            'base_price' => 3500,
        ]);

        Carbon::setTestNow();
    }

    public function test_update_rejects_intersection_with_another_session(): void
    {
        Carbon::setTestNow('2026-04-24 10:00:00');
        $event = $this->createOrganizerEvent(77);
        $rentalRequest = $this->rentalRequestPayload(
            requestId: 603,
            hallId: 200,
            organizerId: 77,
            eventId: $event->id,
            startTime: Carbon::now()->addDay()->setTime(19, 0)->toDateTimeString(),
            endTime: Carbon::now()->addDay()->setTime(21, 0)->toDateTimeString(),
        );
        $this->fakeOrganizerDependencies(77, [
            200 => $this->hallPayload(200, 77, 'Main Arena'),
        ], [], [
            603 => $rentalRequest,
        ]);

        EventSession::query()->create([
            'event_id' => $event->id,
            'hall_id' => 200,
            'start_time' => Carbon::now()->addDay()->setTime(18, 0),
            'end_time' => Carbon::now()->addDay()->setTime(20, 0),
            'base_price' => 3000,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        $sessionToUpdate = EventSession::query()->create([
            'event_id' => $event->id,
            'hall_id' => 201,
            'hall_rental_request_id' => 604,
            'start_time' => Carbon::now()->addDay()->setTime(21, 0),
            'end_time' => Carbon::now()->addDay()->setTime(22, 0),
            'base_price' => 2500,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->putJson("/api/organizer/sessions/{$sessionToUpdate->id}", [
                'hall_rental_request_id' => 603,
                'base_price' => 3500,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hall_id']);

        Carbon::setTestNow();
    }

    public function test_destroy_changes_status_to_cancelled(): void
    {
        $event = $this->createOrganizerEvent(77);
        $session = EventSession::query()->create([
            'event_id' => $event->id,
            'hall_id' => 101,
            'hall_rental_request_id' => 701,
            'start_time' => '2026-04-25 18:00:00',
            'end_time' => '2026-04-25 20:00:00',
            'base_price' => 3000,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        $this->fakeOrganizerDependencies(77, [
            101 => $this->hallPayload(101, 77, 'Main Arena'),
        ]);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->deleteJson("/api/organizer/sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('session.status', EventSession::STATUS_CANCELLED)
            ->assertJsonPath('session.hall.name', 'Main Arena')
            ->assertJsonPath('session.hall.address', 'Нижний Новгород, ул. Большая Покровская, 1');

        $this->assertDatabaseHas('event_sessions', [
            'id' => $session->id,
            'status' => EventSession::STATUS_CANCELLED,
        ]);
    }

    public function test_update_rejects_session_changes_when_bookings_exist(): void
    {
        Carbon::setTestNow('2026-04-24 10:00:00');
        $event = $this->createOrganizerEvent(77);
        $session = EventSession::query()->create([
            'event_id' => $event->id,
            'hall_id' => 101,
            'hall_rental_request_id' => 801,
            'start_time' => Carbon::now()->addDay()->setTime(18, 0),
            'end_time' => Carbon::now()->addDay()->setTime(20, 0),
            'base_price' => 3000,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        $this->fakeOrganizerDependencies(77, [
            102 => $this->hallPayload(102, 77, 'Balcony Hall'),
        ], [
            $session->id => $this->guardPayload(eventSessionId: $session->id, confirmedBookingsCount: 1, confirmedTicketsCount: 2),
        ]);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->putJson("/api/organizer/sessions/{$session->id}", [
                'hall_rental_request_id' => 802,
                'base_price' => 3500,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['session']);

        Carbon::setTestNow();
    }

    public function test_destroy_rejects_session_cancellation_with_active_reservations(): void
    {
        Carbon::setTestNow('2026-04-24 10:00:00');
        $event = $this->createOrganizerEvent(77);
        $session = EventSession::query()->create([
            'event_id' => $event->id,
            'hall_id' => 101,
            'hall_rental_request_id' => 901,
            'start_time' => Carbon::now()->addDay()->setTime(18, 0),
            'end_time' => Carbon::now()->addDay()->setTime(20, 0),
            'base_price' => 3000,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        $this->fakeOrganizerDependencies(77, [
            101 => $this->hallPayload(101, 77, 'Main Arena'),
        ], [
            $session->id => $this->guardPayload(eventSessionId: $session->id, activeReservationsCount: 1, activeReservedTicketsCount: 3),
        ]);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->deleteJson("/api/organizer/sessions/{$session->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['session']);

        Carbon::setTestNow();
    }

    /**
     * @param  array<int, array<string, mixed>>  $halls
     * @param  array<int, array<string, mixed>>  $sessionImpacts
     * @param  array<int, array<string, mixed>>  $rentalRequests
     */
    private function fakeOrganizerDependencies(
        int $organizerId,
        array $halls = [],
        array $sessionImpacts = [],
        array $rentalRequests = [],
    ): void
    {
        Http::preventStrayRequests();

        Http::fake(function (HttpRequest $request) use ($organizerId, $halls, $sessionImpacts, $rentalRequests) {
            if ($request->url() === 'http://127.0.0.1:8000/api/me') {
                return Http::response([
                    'user' => [
                        'id' => $organizerId,
                        'full_name' => 'Event Organizer',
                        'email' => 'organizer@example.com',
                        'role' => [
                            'role' => 'organizer',
                        ],
                    ],
                ], 200);
            }

            if (preg_match('#^http://127\.0\.0\.1:8002/api/halls/(\d+)$#', $request->url(), $matches) === 1) {
                $hallId = (int) $matches[1];

                if (array_key_exists($hallId, $halls)) {
                    return Http::response($halls[$hallId], 200);
                }

                return Http::response([
                    'message' => 'Hall not found.',
                ], 404);
            }

            if (preg_match('#^http://127\.0\.0\.1:8002/api/organizer/halls/(\d+)$#', $request->url(), $matches) === 1) {
                $hallId = (int) $matches[1];

                if (array_key_exists($hallId, $halls)) {
                    return Http::response($halls[$hallId], 200);
                }

                return Http::response([
                    'message' => 'Hall not found.',
                ], 404);
            }

            if (preg_match('#^http://127\.0\.0\.1:8002/api/organizer/hall-rental-requests/(\d+)$#', $request->url(), $matches) === 1) {
                $requestId = (int) $matches[1];

                if (array_key_exists($requestId, $rentalRequests)) {
                    return Http::response($rentalRequests[$requestId], 200);
                }

                return Http::response([
                    'message' => 'Rental request not found.',
                ], 404);
            }

            if (preg_match('#^http://127\.0\.0\.1:8003/api/organizer/guards/sessions/(\d+)/booking-impact$#', $request->url(), $matches) === 1) {
                $sessionId = (int) $matches[1];

                return Http::response(
                    $sessionImpacts[$sessionId] ?? $this->guardPayload(eventSessionId: $sessionId),
                    200
                );
            }

            return Http::response([
                'message' => 'Unexpected request: ' . $request->url(),
            ], 500);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function hallPayload(int $hallId, int $organizerId, string $name): array
    {
        $address = match ($hallId) {
            101 => 'Нижний Новгород, ул. Большая Покровская, 1',
            102 => 'Нижний Новгород, ул. Большая Покровская, 3',
            default => 'Нижний Новгород, ул. Варварская, 5',
        };

        return [
            'id' => $hallId,
            'name' => $name,
            'address' => $address,
            'description' => 'Organizer hall used for sessions.',
            'organizer_id' => $organizerId,
            'venue_owner_id' => 900 + $hallId,
            'hourly_rate' => 2500,
            'status' => 'active',
            'capacities' => [
                'seat' => 120,
                'vip' => 24,
                'dancefloor' => 80,
                'total' => 224,
            ],
            'layout_meta' => [
                'levels_count' => 2,
                'elements_count' => 8,
                'has_dancefloor' => true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rentalRequestPayload(
        int $requestId,
        int $hallId,
        int $organizerId,
        int $eventId,
        string $startTime,
        string $endTime,
        string $status = 'approved',
    ): array {
        return [
            'id' => $requestId,
            'hall_id' => $hallId,
            'event_id' => $eventId,
            'organizer_id' => $organizerId,
            'status' => $status,
            'requested_start' => $startTime,
            'requested_end' => $endTime,
            'hourly_rate' => 2500,
            'total_amount' => 5000,
            'duration_minutes' => 120,
            'organizer_message' => null,
            'response_note' => null,
            'responded_at' => Carbon::now()?->toISOString(),
            'created_at' => Carbon::now()?->toISOString(),
            'updated_at' => Carbon::now()?->toISOString(),
        ];
    }

    private function createOrganizerEvent(int $organizerId): Event
    {
        $category = Category::query()->create([
            'name' => 'РљРѕРЅС†РµСЂС‚',
            'slug' => 'concert',
        ]);

        $ageRating = AgeRating::query()->create([
            'label' => '16+',
            'min_age' => 16,
        ]);

        return Event::query()->create([
            'title' => 'Р‘РѕР»СЊС€РѕР№ РєРѕРЅС†РµСЂС‚',
            'description' => 'РћРїРёСЃР°РЅРёРµ',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => $organizerId,
            'status' => Event::STATUS_PUBLISHED,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function guardPayload(
        ?int $eventId = null,
        ?int $eventSessionId = null,
        int $confirmedBookingsCount = 0,
        int $confirmedTicketsCount = 0,
        int $activeReservationsCount = 0,
        int $activeReservedTicketsCount = 0,
    ): array {
        return [
            'event_id' => $eventId,
            'event_session_id' => $eventSessionId,
            'has_confirmed_bookings' => $confirmedBookingsCount > 0,
            'confirmed_bookings_count' => $confirmedBookingsCount,
            'confirmed_tickets_count' => $confirmedTicketsCount,
            'active_reservations_count' => $activeReservationsCount,
            'active_reserved_tickets_count' => $activeReservedTicketsCount,
            'latest_confirmed_at' => null,
        ];
    }
}
