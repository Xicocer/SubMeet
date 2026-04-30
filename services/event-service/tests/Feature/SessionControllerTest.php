<?php

namespace Tests\Feature;

use App\Models\AgeRating;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_public_session_with_event_context(): void
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
            'title' => 'Rock Night',
            'description' => 'Big evening concert.',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 77,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        $session = EventSession::query()->create([
            'event_id' => $event->id,
            'hall_id' => 300,
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHours(2),
            'base_price' => 1200,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        $this
            ->getJson("/api/sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('id', $session->id)
            ->assertJsonPath('event.title', 'Rock Night')
            ->assertJsonPath('event.organizer_id', 77)
            ->assertJsonPath('event.category.slug', 'concert')
            ->assertJsonPath('event.age_rating.min_age', 16);
    }
}
