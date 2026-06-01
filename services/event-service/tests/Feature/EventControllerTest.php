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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EventControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_published_events_with_filters(): void
    {
        $concertCategory = Category::query()->create([
            'name' => 'Concert',
            'slug' => 'concert',
        ]);

        $theaterCategory = Category::query()->create([
            'name' => 'Theater',
            'slug' => 'theater',
        ]);

        $age12 = AgeRating::query()->create([
            'label' => '12+',
            'min_age' => 12,
        ]);

        $age16 = AgeRating::query()->create([
            'label' => '16+',
            'min_age' => 16,
        ]);

        Event::query()->create([
            'title' => 'rock concert',
            'description' => 'Main music event',
            'poster_url' => 'https://example.com/poster-rock.jpg',
            'category_id' => $concertCategory->id,
            'age_rating_id' => $age16->id,
            'organizer_id' => 10,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        $concertEvent = Event::query()
            ->where('organizer_id', 10)
            ->where('status', Event::STATUS_PUBLISHED)
            ->firstOrFail();

        $rockTag = Tag::query()->create([
            'name' => 'rock',
            'slug' => 'rock',
        ]);

        $concertEvent->tags()->sync([$rockTag->id]);

        Event::query()->create([
            'title' => 'Family theater show',
            'description' => 'For everyone',
            'poster_url' => 'https://example.com/poster-theater.jpg',
            'category_id' => $theaterCategory->id,
            'age_rating_id' => $age12->id,
            'organizer_id' => 11,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        Event::query()->create([
            'title' => 'Draft rock festival',
            'description' => 'Should stay hidden',
            'poster_url' => null,
            'category_id' => $concertCategory->id,
            'age_rating_id' => $age16->id,
            'organizer_id' => 12,
            'status' => Event::STATUS_DRAFT,
        ]);

        $response = $this->getJson('/api/events?category=concert&age=16&search=rock&sort=title_desc&per_page=5');

        $response
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('per_page', 5)
            ->assertJsonPath('data.0.title', 'rock concert')
            ->assertJsonPath('data.0.category.slug', 'concert')
            ->assertJsonPath('data.0.age_rating.min_age', 16)
            ->assertJsonFragment(['slug' => 'rock'])
            ->assertJsonPath('data.0.is_wanted', false)
            ->assertJsonPath('data.0.is_teaser', true);
    }

    public function test_index_matches_russian_word_forms_with_search_fallback(): void
    {
        $category = Category::query()->create([
            'name' => 'Концерт',
            'slug' => 'concert',
        ]);

        $ageRating = AgeRating::query()->create([
            'label' => '16+',
            'min_age' => 16,
        ]);

        Event::query()->create([
            'title' => 'Ночной рок-концерт на крыше',
            'description' => 'Живой звук и летняя сцена',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 10,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        $response = $this->getJson('/api/events?search=концерты&per_page=5');

        $response
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.title', 'Ночной рок-концерт на крыше');
    }

    public function test_show_returns_full_published_event_card(): void
    {
        Http::preventStrayRequests();
        Http::fake(function (HttpRequest $request) {
            if (str_contains($request->url(), '/api/events/') && str_contains($request->url(), '/hall-rental-requests')) {
                return Http::response([
                    'data' => [],
                ], 200);
            }

            return Http::response([
                'message' => 'Unexpected request: ' . $request->url(),
            ], 500);
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
            'title' => 'Big rock concert',
            'description' => 'Detailed event description',
            'poster_url' => 'https://example.com/poster.jpg',
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 25,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        $eventTag = Tag::query()->create([
            'name' => 'premiere',
            'slug' => 'premiere',
        ]);

        $event->tags()->sync([$eventTag->id]);

        $response = $this->getJson("/api/events/{$event->id}");

        $response
            ->assertOk()
            ->assertJsonPath('id', $event->id)
            ->assertJsonPath('title', 'Big rock concert')
            ->assertJsonPath('description', 'Detailed event description')
            ->assertJsonPath('category.slug', 'concert')
            ->assertJsonPath('age_rating.label', '16+')
            ->assertJsonPath('organizer_id', 25)
            ->assertJsonPath('status', Event::STATUS_PUBLISHED)
            ->assertJsonFragment(['name' => 'premiere', 'slug' => 'premiere'])
            ->assertJsonPath('is_wanted', false)
            ->assertJsonPath('is_teaser', true)
            ->assertJsonPath('tentative_dates', []);
    }

    public function test_show_marks_event_as_wanted_for_authorized_user(): void
    {
        Http::preventStrayRequests();
        Http::fake(function (HttpRequest $request) {
            if ($request->url() === 'http://127.0.0.1:8000/api/me') {
                return Http::response([
                    'user' => [
                        'id' => 501,
                        'status' => 1,
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
            'title' => 'Big rock concert',
            'description' => 'Detailed event description',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 25,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        EventFavorite::query()->create([
            'user_id' => 501,
            'event_id' => $event->id,
        ]);

        $this->withToken('token-user-1')
            ->getJson("/api/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('is_wanted', true);
    }

    public function test_show_returns_404_for_non_published_event(): void
    {
        $category = Category::query()->create([
            'name' => 'Concert',
            'slug' => 'concert',
        ]);

        $ageRating = AgeRating::query()->create([
            'label' => '16+',
            'min_age' => 16,
        ]);

        $event = Event::query()->create([
            'title' => 'Draft event',
            'description' => 'Hidden event',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 1,
            'status' => Event::STATUS_DRAFT,
        ]);

        $this->getJson("/api/events/{$event->id}")
            ->assertNotFound();
    }

    public function test_sessions_returns_only_available_sessions_for_event(): void
    {
        Carbon::setTestNow('2026-04-24 12:00:00');
        Http::preventStrayRequests();
        Http::fake(function (HttpRequest $request) {
            if ($request->url() === 'http://127.0.0.1:8002/api/halls/101') {
                return Http::response([
                    'id' => 101,
                    'name' => 'Main Arena',
                    'address' => 'Nizhny Novgorod, Bolshaya Pokrovskaya 1',
                    'description' => 'Public hall',
                    'organizer_id' => 7,
                    'status' => 'active',
                    'capacities' => [
                        'seat' => 120,
                        'vip' => 10,
                        'dancefloor' => 0,
                        'total' => 130,
                    ],
                ], 200);
            }

            if ($request->url() === 'http://127.0.0.1:8002/api/halls/102' || $request->url() === 'http://127.0.0.1:8002/api/halls/103') {
                return Http::response([
                    'message' => 'Hall not found.',
                ], 404);
            }

            return Http::response([
                'message' => 'Unexpected request: ' . $request->url(),
            ], 500);
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
            'title' => 'Night rock concert',
            'description' => 'Description',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 7,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        EventSession::query()->create([
            'event_id' => $event->id,
            'hall_id' => 101,
            'start_time' => Carbon::now()->addDay(),
            'end_time' => Carbon::now()->addDay()->addHours(2),
            'base_price' => 2500,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        EventSession::query()->create([
            'event_id' => $event->id,
            'hall_id' => 102,
            'start_time' => Carbon::now()->subDay(),
            'end_time' => Carbon::now()->subDay()->addHours(2),
            'base_price' => 2000,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        EventSession::query()->create([
            'event_id' => $event->id,
            'hall_id' => 103,
            'start_time' => Carbon::now()->addDays(2),
            'end_time' => Carbon::now()->addDays(2)->addHours(2),
            'base_price' => 3000,
            'status' => EventSession::STATUS_CANCELLED,
        ]);

        $response = $this->getJson("/api/events/{$event->id}/sessions");

        $response
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.hall_id', 101)
            ->assertJsonPath('0.hall.name', 'Main Arena')
            ->assertJsonPath('0.hall.address', 'Nizhny Novgorod, Bolshaya Pokrovskaya 1')
            ->assertJsonPath('0.status', EventSession::STATUS_SCHEDULED);

        Carbon::setTestNow();
    }
}
