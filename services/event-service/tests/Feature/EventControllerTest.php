<?php

namespace Tests\Feature;

use App\Models\AgeRating;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_published_events_with_filters(): void
    {
        $concertCategory = Category::query()->create([
            'name' => 'Концерт',
            'slug' => 'concert',
        ]);

        $theaterCategory = Category::query()->create([
            'name' => 'Театр',
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
            'title' => 'рок концерт',
            'description' => 'Главное музыкальное событие',
            'poster_url' => 'https://example.com/poster-rock.jpg',
            'category_id' => $concertCategory->id,
            'age_rating_id' => $age16->id,
            'organizer_id' => 10,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        Event::query()->create([
            'title' => 'Детский спектакль',
            'description' => 'Для всей семьи',
            'poster_url' => 'https://example.com/poster-theater.jpg',
            'category_id' => $theaterCategory->id,
            'age_rating_id' => $age12->id,
            'organizer_id' => 11,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        Event::query()->create([
            'title' => 'Черновик рок-фестиваля',
            'description' => 'Не должен попасть в публичный список',
            'poster_url' => null,
            'category_id' => $concertCategory->id,
            'age_rating_id' => $age16->id,
            'organizer_id' => 12,
            'status' => Event::STATUS_DRAFT,
        ]);

        $response = $this->getJson(
            '/api/events?category=concert&age=16&search=рок&sort=title_desc&per_page=5'
        );

        $response
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('per_page', 5)
            ->assertJsonPath('data.0.title', 'рок концерт')
            ->assertJsonPath('data.0.category.slug', 'concert')
            ->assertJsonPath('data.0.age_rating.min_age', 16);
    }

    public function test_show_returns_full_published_event_card(): void
    {
        $category = Category::query()->create([
            'name' => 'Концерт',
            'slug' => 'concert',
        ]);

        $ageRating = AgeRating::query()->create([
            'label' => '16+',
            'min_age' => 16,
        ]);

        $event = Event::query()->create([
            'title' => 'Большой рок концерт',
            'description' => 'Подробное описание мероприятия',
            'poster_url' => 'https://example.com/poster.jpg',
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 25,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        $response = $this->getJson("/api/events/{$event->id}");

        $response
            ->assertOk()
            ->assertJsonPath('id', $event->id)
            ->assertJsonPath('title', 'Большой рок концерт')
            ->assertJsonPath('description', 'Подробное описание мероприятия')
            ->assertJsonPath('category.slug', 'concert')
            ->assertJsonPath('age_rating.label', '16+')
            ->assertJsonPath('organizer_id', 25)
            ->assertJsonPath('status', Event::STATUS_PUBLISHED);
    }

    public function test_show_returns_404_for_non_published_event(): void
    {
        $category = Category::query()->create([
            'name' => 'Концерт',
            'slug' => 'concert',
        ]);

        $ageRating = AgeRating::query()->create([
            'label' => '16+',
            'min_age' => 16,
        ]);

        $event = Event::query()->create([
            'title' => 'Черновик',
            'description' => 'Скрытое событие',
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

        $category = Category::query()->create([
            'name' => 'Концерт',
            'slug' => 'concert',
        ]);

        $ageRating = AgeRating::query()->create([
            'label' => '16+',
            'min_age' => 16,
        ]);

        $event = Event::query()->create([
            'title' => 'Ночной рок концерт',
            'description' => 'Описание',
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
            ->assertJsonPath('0.status', EventSession::STATUS_SCHEDULED);

        Carbon::setTestNow();
    }
}
