<?php

namespace Tests\Feature;

use App\Models\AgeRating;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompletePastSessionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_marks_past_scheduled_sessions_as_completed(): void
    {
        $category = Category::query()->create([
            'name' => 'Concert',
            'slug' => 'concert',
        ]);

        $ageRating = AgeRating::query()->create([
            'label' => '12+',
            'min_age' => 12,
        ]);

        $event = Event::query()->create([
            'title' => 'Big Show',
            'description' => 'Concert description',
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 14,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        $pastScheduled = EventSession::query()->create([
            'event_id' => $event->id,
            'hall_id' => 22,
            'start_time' => now()->subDays(2),
            'end_time' => now()->subDays(2)->addHours(2),
            'base_price' => 1500,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        $futureScheduled = EventSession::query()->create([
            'event_id' => $event->id,
            'hall_id' => 22,
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHours(2),
            'base_price' => 1500,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        $pastCancelled = EventSession::query()->create([
            'event_id' => $event->id,
            'hall_id' => 22,
            'start_time' => now()->subDay(),
            'end_time' => now()->subDay()->addHours(2),
            'base_price' => 1500,
            'status' => EventSession::STATUS_CANCELLED,
        ]);

        $this->artisan('sessions:complete-past')
            ->expectsOutput('Completed 1 past session(s).')
            ->assertSuccessful();

        $this->assertDatabaseHas('event_sessions', [
            'id' => $pastScheduled->id,
            'status' => EventSession::STATUS_COMPLETED,
        ]);

        $this->assertDatabaseHas('event_sessions', [
            'id' => $futureScheduled->id,
            'status' => EventSession::STATUS_SCHEDULED,
        ]);

        $this->assertDatabaseHas('event_sessions', [
            'id' => $pastCancelled->id,
            'status' => EventSession::STATUS_CANCELLED,
        ]);
    }
}
