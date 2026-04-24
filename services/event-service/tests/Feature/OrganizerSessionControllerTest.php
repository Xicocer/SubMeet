<?php

namespace Tests\Feature;

use App\Models\AgeRating;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->fakeOrganizerAuth(77);

        $response = $this->withHeader('Authorization', 'Bearer organizer-token')
            ->postJson("/api/organizer/events/{$event->id}/sessions", [
                'hall_id' => 101,
                'start_time' => Carbon::now()->addDay()->toDateTimeString(),
                'end_time' => Carbon::now()->addDay()->addHours(2)->toDateTimeString(),
                'base_price' => 2500,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('session.event_id', $event->id)
            ->assertJsonPath('session.hall_id', 101)
            ->assertJsonPath('session.status', EventSession::STATUS_SCHEDULED);

        $this->assertDatabaseHas('event_sessions', [
            'event_id' => $event->id,
            'hall_id' => 101,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        Carbon::setTestNow();
    }

    public function test_index_returns_sessions_for_current_organizer_event(): void
    {
        Carbon::setTestNow('2026-04-24 10:00:00');
        $event = $this->createOrganizerEvent(77);
        $this->fakeOrganizerAuth(77);

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
            ->assertJsonPath('1.status', EventSession::STATUS_CANCELLED);

        Carbon::setTestNow();
    }

    public function test_store_returns_404_for_foreign_event(): void
    {
        Carbon::setTestNow('2026-04-24 10:00:00');
        $event = $this->createOrganizerEvent(88);
        $this->fakeOrganizerAuth(77);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->postJson("/api/organizer/events/{$event->id}/sessions", [
                'hall_id' => 101,
                'start_time' => Carbon::now()->addDay()->toDateTimeString(),
                'end_time' => Carbon::now()->addDay()->addHours(2)->toDateTimeString(),
                'base_price' => 2500,
            ])
            ->assertNotFound();

        Carbon::setTestNow();
    }

    public function test_store_rejects_intersecting_sessions_in_same_hall(): void
    {
        Carbon::setTestNow('2026-04-24 10:00:00');
        $event = $this->createOrganizerEvent(77);
        $this->fakeOrganizerAuth(77);

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
                'hall_id' => 101,
                'start_time' => Carbon::now()->addDay()->setTime(19, 0)->toDateTimeString(),
                'end_time' => Carbon::now()->addDay()->setTime(21, 0)->toDateTimeString(),
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
            'start_time' => Carbon::now()->addDay()->setTime(18, 0),
            'end_time' => Carbon::now()->addDay()->setTime(20, 0),
            'base_price' => 3000,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        $this->fakeOrganizerAuth(77);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->putJson("/api/organizer/sessions/{$session->id}", [
                'hall_id' => 102,
                'start_time' => Carbon::now()->addDay()->setTime(21, 0)->toDateTimeString(),
                'end_time' => Carbon::now()->addDay()->setTime(23, 0)->toDateTimeString(),
                'base_price' => 3500,
            ])
            ->assertOk()
            ->assertJsonPath('session.hall_id', 102);

        $this->assertDatabaseHas('event_sessions', [
            'id' => $session->id,
            'hall_id' => 102,
            'base_price' => 3500,
        ]);

        Carbon::setTestNow();
    }

    public function test_update_rejects_intersection_with_another_session(): void
    {
        Carbon::setTestNow('2026-04-24 10:00:00');
        $event = $this->createOrganizerEvent(77);
        $this->fakeOrganizerAuth(77);

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
            'start_time' => Carbon::now()->addDay()->setTime(21, 0),
            'end_time' => Carbon::now()->addDay()->setTime(22, 0),
            'base_price' => 2500,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->putJson("/api/organizer/sessions/{$sessionToUpdate->id}", [
                'hall_id' => 200,
                'start_time' => Carbon::now()->addDay()->setTime(19, 0)->toDateTimeString(),
                'end_time' => Carbon::now()->addDay()->setTime(21, 0)->toDateTimeString(),
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
            'start_time' => '2026-04-25 18:00:00',
            'end_time' => '2026-04-25 20:00:00',
            'base_price' => 3000,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        $this->fakeOrganizerAuth(77);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->deleteJson("/api/organizer/sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('session.status', EventSession::STATUS_CANCELLED);

        $this->assertDatabaseHas('event_sessions', [
            'id' => $session->id,
            'status' => EventSession::STATUS_CANCELLED,
        ]);
    }

    private function fakeOrganizerAuth(int $organizerId): void
    {
        Http::fake([
            'http://127.0.0.1:8000/api/me' => Http::response([
                'user' => [
                    'id' => $organizerId,
                    'full_name' => 'Event Organizer',
                    'email' => 'organizer@example.com',
                    'role' => [
                        'role' => 'organizer',
                    ],
                ],
            ], 200),
        ]);
    }

    private function createOrganizerEvent(int $organizerId): Event
    {
        $category = Category::query()->create([
            'name' => 'Концерт',
            'slug' => 'concert',
        ]);

        $ageRating = AgeRating::query()->create([
            'label' => '16+',
            'min_age' => 16,
        ]);

        return Event::query()->create([
            'title' => 'Большой концерт',
            'description' => 'Описание',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => $organizerId,
            'status' => Event::STATUS_DRAFT,
        ]);
    }
}
