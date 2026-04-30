<?php

namespace Tests\Feature;

use App\Models\AgeRating;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrganizerHallUsageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_returns_only_future_sessions_for_current_organizer(): void
    {
        [$category, $ageRating] = $this->createEventDependencies();
        $this->fakeOrganizerAuth(77);

        $event = Event::query()->create([
            'title' => 'Main Concert',
            'description' => 'Live show',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 77,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        $foreignEvent = Event::query()->create([
            'title' => 'Foreign Concert',
            'description' => 'Foreign show',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 88,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        EventSession::query()->create([
            'event_id' => $event->id,
            'hall_id' => 205,
            'start_time' => now()->addDays(3),
            'end_time' => now()->addDays(3)->addHours(2),
            'base_price' => 2500,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        EventSession::query()->create([
            'event_id' => $event->id,
            'hall_id' => 205,
            'start_time' => now()->subDay(),
            'end_time' => now()->subDay()->addHours(2),
            'base_price' => 2500,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        EventSession::query()->create([
            'event_id' => $foreignEvent->id,
            'hall_id' => 205,
            'start_time' => now()->addDays(4),
            'end_time' => now()->addDays(4)->addHours(2),
            'base_price' => 2500,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->getJson('/api/organizer/halls/205/usage')
            ->assertOk()
            ->assertJsonPath('hall_id', 205)
            ->assertJsonPath('future_sessions_count', 1)
            ->assertJsonPath('has_future_sessions', true)
            ->assertJsonPath('future_sessions.0.event_title', 'Main Concert');
    }

    private function fakeOrganizerAuth(int $organizerId): void
    {
        Http::fake([
            'http://127.0.0.1:8000/api/me' => Http::response([
                'user' => [
                    'id' => $organizerId,
                    'full_name' => 'Organizer',
                    'email' => 'organizer@example.com',
                    'role' => [
                        'role' => 'organizer',
                    ],
                ],
            ], 200),
        ]);
    }

    /**
     * @return array{0: Category, 1: AgeRating}
     */
    private function createEventDependencies(): array
    {
        $category = Category::query()->create([
            'name' => 'Concert',
            'slug' => 'concert',
        ]);

        $ageRating = AgeRating::query()->create([
            'label' => '16+',
            'min_age' => 16,
        ]);

        return [$category, $ageRating];
    }
}
