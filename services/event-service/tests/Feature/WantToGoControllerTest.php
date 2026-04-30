<?php

namespace Tests\Feature;

use App\Models\AgeRating;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventFavorite;
use App\Models\EventSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WantToGoControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_view_and_remove_event_in_want_to_go_list(): void
    {
        Carbon::setTestNow('2026-04-30 18:00:00');
        $this->fakeAuthenticatedUser();

        $category = Category::query()->create([
            'name' => 'Concert',
            'slug' => 'concert',
        ]);

        $ageRating = AgeRating::query()->create([
            'label' => '16+',
            'min_age' => 16,
        ]);

        $event = Event::query()->create([
            'title' => 'Rock Night',
            'description' => 'Live at the arena.',
            'poster_url' => 'https://example.com/poster.jpg',
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 100,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        EventSession::query()->create([
            'event_id' => $event->id,
            'hall_id' => 55,
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHours(2),
            'base_price' => 2200,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        $this->withToken('token-user-1')
            ->postJson("/api/events/{$event->id}/want-to-go")
            ->assertCreated()
            ->assertJsonPath('event_id', $event->id)
            ->assertJsonPath('is_wanted', true);

        $this->withToken('token-user-1')
            ->getJson('/api/me/want-to-go')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $event->id)
            ->assertJsonPath('0.is_wanted', true)
            ->assertJsonPath('0.minimum_price', 2200)
            ->assertJsonPath('0.next_session.hall.name', 'Main Hall');

        $this->withToken('token-user-1')
            ->deleteJson("/api/events/{$event->id}/want-to-go")
            ->assertOk()
            ->assertJsonPath('is_wanted', false);

        $this->assertDatabaseMissing('event_favorites', [
            'user_id' => 501,
            'event_id' => $event->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_index_prunes_events_without_future_sessions_from_want_to_go_list(): void
    {
        Carbon::setTestNow('2026-04-30 18:00:00');
        $this->fakeAuthenticatedUser();

        $category = Category::query()->create([
            'name' => 'Concert',
            'slug' => 'concert',
        ]);

        $ageRating = AgeRating::query()->create([
            'label' => '16+',
            'min_age' => 16,
        ]);

        $activeEvent = Event::query()->create([
            'title' => 'Future Event',
            'description' => 'Upcoming show.',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 100,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        $pastEvent = Event::query()->create([
            'title' => 'Past Event',
            'description' => 'Already finished.',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 101,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        EventSession::query()->create([
            'event_id' => $activeEvent->id,
            'hall_id' => 55,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
            'base_price' => 1800,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        EventSession::query()->create([
            'event_id' => $pastEvent->id,
            'hall_id' => 56,
            'start_time' => now()->subDay(),
            'end_time' => now()->subDay()->addHours(2),
            'base_price' => 1500,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        EventFavorite::query()->create([
            'user_id' => 501,
            'event_id' => $activeEvent->id,
        ]);

        EventFavorite::query()->create([
            'user_id' => 501,
            'event_id' => $pastEvent->id,
        ]);

        $this->withToken('token-user-1')
            ->getJson('/api/me/want-to-go')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $activeEvent->id);

        $this->assertDatabaseHas('event_favorites', [
            'user_id' => 501,
            'event_id' => $activeEvent->id,
        ]);

        $this->assertDatabaseMissing('event_favorites', [
            'user_id' => 501,
            'event_id' => $pastEvent->id,
        ]);

        Carbon::setTestNow();
    }

    private function fakeAuthenticatedUser(): void
    {
        Http::preventStrayRequests();

        Http::fake(function (HttpRequest $request) {
            if ($request->url() === 'http://127.0.0.1:8000/api/me') {
                return Http::response([
                    'user' => [
                        'id' => 501,
                        'full_name' => 'Test User',
                        'email' => 'user@example.com',
                        'status' => 1,
                        'role' => [
                            'role' => 'user',
                        ],
                    ],
                ], 200);
            }

            if ($request->url() === 'http://127.0.0.1:8002/api/halls/55') {
                return Http::response([
                    'id' => 55,
                    'name' => 'Main Hall',
                    'address' => 'Moscow, Tverskaya 1',
                    'description' => 'Arena',
                    'organizer_id' => 100,
                    'status' => 'active',
                    'capacities' => [
                        'seat' => 200,
                        'vip' => 20,
                        'dancefloor' => 50,
                        'total' => 270,
                    ],
                ], 200);
            }

            if ($request->url() === 'http://127.0.0.1:8002/api/halls/56') {
                return Http::response([
                    'id' => 56,
                    'name' => 'Archive Hall',
                    'address' => 'Moscow, Arbat 2',
                    'description' => 'Archive',
                    'organizer_id' => 101,
                    'status' => 'active',
                    'capacities' => [
                        'seat' => 100,
                        'vip' => 0,
                        'dancefloor' => 0,
                        'total' => 100,
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });
    }
}
