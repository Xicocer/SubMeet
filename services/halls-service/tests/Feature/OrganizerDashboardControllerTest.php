<?php

namespace Tests\Feature;

use App\Models\Hall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrganizerDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_hall_metrics_for_current_venue_owner(): void
    {
        $this->fakeVenueOwnerAuth(77);

        $activeHall = Hall::query()->create([
            'venue_owner_id' => 77,
            'name' => 'Main Arena',
            'address' => 'City Center, 1',
            'description' => 'Primary hall',
            'layout' => $this->validLayout(),
            'seat_capacity' => 200,
            'vip_capacity' => 50,
            'dancefloor_capacity' => 120,
            'total_capacity' => 370,
            'status' => Hall::STATUS_ACTIVE,
        ]);

        $draftHall = Hall::query()->create([
            'venue_owner_id' => 77,
            'name' => 'Studio Hall',
            'address' => 'Riverside, 8',
            'description' => 'Small room',
            'layout' => $this->validLayout(),
            'seat_capacity' => 80,
            'vip_capacity' => 20,
            'dancefloor_capacity' => 0,
            'total_capacity' => 100,
            'status' => Hall::STATUS_DRAFT,
        ]);

        $activeHall->forceFill([
            'updated_at' => now()->subMinute(),
        ])->save();

        $draftHall->forceFill([
            'updated_at' => now(),
        ])->save();

        Hall::query()->create([
            'venue_owner_id' => 88,
            'name' => 'Foreign Hall',
            'address' => 'Other City, 4',
            'description' => 'Ignore this one',
            'layout' => $this->validLayout(),
            'seat_capacity' => 100,
            'vip_capacity' => 10,
            'dancefloor_capacity' => 20,
            'total_capacity' => 130,
            'status' => Hall::STATUS_ACTIVE,
        ]);

        $this->withHeader('Authorization', 'Bearer venue-token')
            ->getJson('/api/venue/dashboard')
            ->assertOk()
            ->assertJsonPath('metrics.halls_total', 2)
            ->assertJsonPath('metrics.halls_active', 1)
            ->assertJsonPath('metrics.halls_draft', 1)
            ->assertJsonPath('metrics.capacity_total', 470)
            ->assertJsonPath('metrics.capacity_largest', 370)
            ->assertJsonPath('recent_halls.0.name', 'Studio Hall');
    }

    private function fakeVenueOwnerAuth(int $venueOwnerId): void
    {
        Http::fake([
            'http://127.0.0.1:8000/api/me' => Http::response([
                'user' => [
                    'id' => $venueOwnerId,
                    'full_name' => 'Venue Owner',
                    'email' => 'venue@example.com',
                    'role' => [
                        'role' => 'venue_owner',
                    ],
                ],
            ], 200),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validLayout(): array
    {
        return [
            'canvas' => [
                'width' => 1200,
                'height' => 800,
            ],
            'levels' => [
                [
                    'id' => 'main',
                    'name' => 'Main',
                    'order' => 1,
                ],
            ],
            'elements' => [
                [
                    'id' => 'stage-main',
                    'type' => 'stage',
                    'label' => 'Main Stage',
                    'x' => 200,
                    'y' => 40,
                    'width' => 450,
                    'height' => 120,
                ],
                [
                    'id' => 'seat-a1',
                    'type' => 'seat',
                    'label' => 'A-1',
                    'row' => 'A',
                    'number' => '1',
                    'level_id' => 'main',
                    'x' => 180,
                    'y' => 260,
                ],
            ],
        ];
    }
}
