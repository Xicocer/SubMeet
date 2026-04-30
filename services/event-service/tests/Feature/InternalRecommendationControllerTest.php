<?php

namespace Tests\Feature;

use App\Models\AgeRating;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventFavorite;
use App\Models\EventSession;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InternalRecommendationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_recommendation_events_returns_flattened_live_catalog(): void
    {
        config()->set('services.internal.api_key', 'test-internal-key');

        Http::preventStrayRequests();
        Http::fake(function (HttpRequest $request) {
            if ($request->url() === 'http://127.0.0.1:8002/api/halls/55') {
                return Http::response([
                    'id' => 55,
                    'name' => 'Main Hall',
                    'address' => 'Moscow, Tverskaya 1',
                    'capacities' => [
                        'total' => 480,
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $category = Category::query()->create([
            'name' => 'Concert',
            'slug' => 'concert',
        ]);

        $ageRating = AgeRating::query()->create([
            'label' => '16+',
            'min_age' => 16,
        ]);

        $event = Event::query()->create([
            'title' => 'Rock Arena Live',
            'description' => 'Big rock music night in the city center.',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 501,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        $tag = Tag::query()->create([
            'name' => 'rock',
            'slug' => 'rock',
        ]);

        $event->tags()->sync([$tag->id]);

        EventSession::query()->create([
            'event_id' => $event->id,
            'hall_id' => 55,
            'start_time' => now()->addDays(5),
            'end_time' => now()->addDays(5)->addHours(2),
            'base_price' => 1800,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        $this->withHeader('X-Internal-Api-Key', 'test-internal-key')
            ->getJson('/api/internal/recommendations/events')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $event->id)
            ->assertJsonPath('0.category', 'concert')
            ->assertJsonPath('0.price', 1800)
            ->assertJsonPath('0.status', 'published')
            ->assertJsonPath('0.city', 'Moscow')
            ->assertJsonPath('0.venue_address', 'Moscow, Tverskaya 1')
            ->assertJsonPath('0.available_tickets', 480)
            ->assertJsonPath('0.tags', 'rock');
    }

    public function test_internal_recommendation_interactions_returns_favorite_signals(): void
    {
        config()->set('services.internal.api_key', 'test-internal-key');

        $category = Category::query()->create([
            'name' => 'Concert',
            'slug' => 'concert',
        ]);

        $ageRating = AgeRating::query()->create([
            'label' => '16+',
            'min_age' => 16,
        ]);

        $event = Event::query()->create([
            'title' => 'Rock Arena Live',
            'description' => 'Big rock music night in the city center.',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 501,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        EventFavorite::query()->create([
            'user_id' => 9001,
            'event_id' => $event->id,
        ]);

        $this->withHeader('X-Internal-Api-Key', 'test-internal-key')
            ->getJson('/api/internal/recommendations/interactions')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.user_id', 9001)
            ->assertJsonPath('0.event_id', $event->id)
            ->assertJsonPath('0.action', 'favorite');
    }
}
