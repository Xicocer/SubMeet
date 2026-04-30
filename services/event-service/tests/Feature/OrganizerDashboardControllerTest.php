<?php

namespace Tests\Feature;

use App\Models\AgeRating;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrganizerDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_only_current_organizer_metrics(): void
    {
        [$category, $ageRating] = $this->createEventDependencies();
        $this->fakeOrganizerAuth(77);

        $publishedEvent = Event::query()->create([
            'title' => 'Main Concert',
            'description' => 'Live show',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 77,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        $draftEvent = Event::query()->create([
            'title' => 'Draft Premiere',
            'description' => 'Soon',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 77,
            'status' => Event::STATUS_DRAFT,
        ]);

        $publishedEvent->forceFill([
            'updated_at' => now()->subMinute(),
        ])->save();

        $draftEvent->forceFill([
            'updated_at' => now(),
        ])->save();

        Event::query()->create([
            'title' => 'Foreign Event',
            'description' => 'Other organizer',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 88,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        EventSession::query()->create([
            'event_id' => $publishedEvent->id,
            'hall_id' => 501,
            'start_time' => now()->addDays(5),
            'end_time' => now()->addDays(5)->addHours(2),
            'base_price' => 2500,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        EventSession::query()->create([
            'event_id' => $draftEvent->id,
            'hall_id' => 502,
            'start_time' => now()->addDays(8),
            'end_time' => now()->addDays(8)->addHours(2),
            'base_price' => 1900,
            'status' => EventSession::STATUS_CANCELLED,
        ]);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->getJson('/api/organizer/dashboard')
            ->assertOk()
            ->assertJsonPath('metrics.events_total', 2)
            ->assertJsonPath('metrics.events_published', 1)
            ->assertJsonPath('metrics.events_draft', 1)
            ->assertJsonPath('metrics.sessions_total', 2)
            ->assertJsonPath('metrics.sessions_upcoming', 1)
            ->assertJsonPath('upcoming_sessions.0.event_title', 'Main Concert')
            ->assertJsonPath('recent_events.0.title', 'Draft Premiere');
    }

    private function fakeOrganizerAuth(int $organizerId): void
    {
        Http::fake([
            'http://127.0.0.1:8000/api/me' => Http::response([
                'user' => [
                    'id' => $organizerId,
                    'full_name' => 'Dashboard Organizer',
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
