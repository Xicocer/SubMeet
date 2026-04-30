<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\SessionSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalRecommendationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_recommendation_interactions_returns_live_booking_signals(): void
    {
        config()->set('services.internal.api_key', 'test-internal-key');

        $snapshot = SessionSnapshot::query()->create([
            'event_session_id' => 900,
            'event_id' => 44,
            'hall_id' => 55,
            'event_title' => 'Rock Night',
            'event_category_name' => 'Concert',
            'event_category_slug' => 'concert',
            'event_age_rating_label' => '16+',
            'event_min_age' => 16,
            'hall_name' => 'Main Hall',
            'hall_address' => 'Moscow, Tverskaya 1',
            'hall_layout' => [],
            'base_price' => 1500,
            'currency' => 'RUB',
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(2),
            'status' => 'scheduled',
            'synced_at' => now(),
        ]);

        Booking::query()->create([
            'user_id' => 501,
            'session_snapshot_id' => $snapshot->id,
            'status' => Booking::STATUS_RESERVED,
            'total_amount' => 1500,
            'currency' => 'RUB',
            'reserved_until' => now()->addMinutes(15),
        ]);

        Booking::query()->create([
            'user_id' => 502,
            'session_snapshot_id' => $snapshot->id,
            'status' => Booking::STATUS_CONFIRMED,
            'total_amount' => 3000,
            'currency' => 'RUB',
            'reserved_until' => null,
            'confirmed_at' => now()->subMinute(),
        ]);

        $this->withHeader('X-Internal-Api-Key', 'test-internal-key')
            ->getJson('/api/internal/recommendations/interactions')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.user_id', 501)
            ->assertJsonPath('0.event_id', 44)
            ->assertJsonPath('0.action', 'booking')
            ->assertJsonPath('1.user_id', 502)
            ->assertJsonPath('1.action', 'purchase');
    }
}
