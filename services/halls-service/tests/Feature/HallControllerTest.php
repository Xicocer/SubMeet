<?php

namespace Tests\Feature;

use App\Models\Hall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HallControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_active_hall_details(): void
    {
        $hall = Hall::query()->create([
            'venue_owner_id' => 44,
            'name' => 'Arena',
            'address' => 'Нижний Новгород, ул. Алексеевская, 4',
            'description' => 'Active hall for public booking.',
            'layout' => [
                'elements' => [
                    ['id' => 'stage-1', 'type' => 'stage', 'x' => 0, 'y' => 0, 'width' => 300, 'height' => 60],
                ],
            ],
            'seat_capacity' => 0,
            'vip_capacity' => 0,
            'dancefloor_capacity' => 100,
            'total_capacity' => 100,
            'status' => Hall::STATUS_ACTIVE,
        ]);

        $this
            ->getJson("/api/halls/{$hall->id}")
            ->assertOk()
            ->assertJsonPath('id', $hall->id)
            ->assertJsonPath('name', 'Arena')
            ->assertJsonPath('address', 'Нижний Новгород, ул. Алексеевская, 4')
            ->assertJsonPath('layout.elements.0.id', 'stage-1');
    }
}
