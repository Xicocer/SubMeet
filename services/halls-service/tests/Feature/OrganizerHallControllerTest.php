<?php

namespace Tests\Feature;

use App\Models\Hall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrganizerHallControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_hall_for_authorized_organizer(): void
    {
        $this->fakeOrganizerAuth(77);

        $response = $this->withHeader('Authorization', 'Bearer organizer-token')
            ->postJson('/api/organizer/halls', [
                'name' => 'Main Arena',
                'address' => 'Нижний Новгород, ул. Большая Покровская, 1',
                'description' => 'Universal concert hall for the MVP.',
                'status' => 'active',
                'layout' => $this->validLayout(),
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('hall.name', 'Main Arena')
            ->assertJsonPath('hall.address', 'Нижний Новгород, ул. Большая Покровская, 1')
            ->assertJsonPath('hall.organizer_id', 77)
            ->assertJsonPath('hall.status', 'active')
            ->assertJsonPath('hall.capacities.seat', 2)
            ->assertJsonPath('hall.capacities.vip', 1)
            ->assertJsonPath('hall.capacities.dancefloor', 150)
            ->assertJsonPath('hall.capacities.total', 153);

        $this->assertDatabaseHas('halls', [
            'name' => 'Main Arena',
            'address' => 'Нижний Новгород, ул. Большая Покровская, 1',
            'organizer_id' => 77,
            'seat_capacity' => 2,
            'vip_capacity' => 1,
            'dancefloor_capacity' => 150,
            'total_capacity' => 153,
            'status' => 'active',
        ]);
    }

    public function test_store_rejects_layout_without_stage(): void
    {
        $this->fakeOrganizerAuth(77);
        $layout = $this->validLayout();
        $layout['elements'] = array_values(array_filter(
            $layout['elements'],
            fn (array $element) => $element['type'] !== 'stage'
        ));

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->postJson('/api/organizer/halls', [
                'name' => 'Broken Hall',
                'address' => 'Нижний Новгород, проспект Гагарина, 10',
                'layout' => $layout,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['layout.stage']);
    }

    public function test_show_returns_only_own_hall(): void
    {
        $this->fakeOrganizerAuth(91);

        $hall = Hall::query()->create([
            'organizer_id' => 91,
            'name' => 'Studio Hall',
            'address' => 'Нижний Новгород, ул. Рождественская, 8',
            'description' => 'Compact format hall.',
            'layout' => $this->validLayout(),
            'seat_capacity' => 2,
            'vip_capacity' => 1,
            'dancefloor_capacity' => 150,
            'total_capacity' => 153,
            'status' => 'draft',
        ]);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->getJson("/api/organizer/halls/{$hall->id}")
            ->assertOk()
            ->assertJsonPath('id', $hall->id)
            ->assertJsonPath('layout.elements.0.type', 'stage');
    }

    public function test_index_returns_only_current_organizer_halls(): void
    {
        $this->fakeOrganizerAuth(77);

        Hall::query()->create([
            'organizer_id' => 77,
            'name' => 'My First Hall',
            'address' => 'Нижний Новгород, Кремль, 1',
            'description' => null,
            'layout' => $this->validLayout(),
            'seat_capacity' => 2,
            'vip_capacity' => 1,
            'dancefloor_capacity' => 150,
            'total_capacity' => 153,
            'status' => 'draft',
        ]);

        Hall::query()->create([
            'organizer_id' => 88,
            'name' => 'Foreign Hall',
            'address' => 'Москва, ул. Тверская, 1',
            'description' => null,
            'layout' => $this->validLayout(),
            'seat_capacity' => 2,
            'vip_capacity' => 1,
            'dancefloor_capacity' => 150,
            'total_capacity' => 153,
            'status' => 'active',
        ]);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->getJson('/api/organizer/halls')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'My First Hall')
            ->assertJsonPath('data.0.organizer_id', 77);
    }

    public function test_update_allows_editing_only_own_hall_and_recalculates_capacity(): void
    {
        $this->fakeOrganizerAuth(77);

        $hall = Hall::query()->create([
            'organizer_id' => 77,
            'name' => 'Old Name',
            'address' => 'Старый адрес, 1',
            'description' => 'Old description',
            'layout' => $this->validLayout(),
            'seat_capacity' => 2,
            'vip_capacity' => 1,
            'dancefloor_capacity' => 150,
            'total_capacity' => 153,
            'status' => 'draft',
        ]);

        $updatedLayout = $this->validLayout();
        $updatedLayout['elements'][] = [
            'id' => 'seat-b1',
            'type' => 'seat',
            'label' => 'B-1',
            'row' => 'B',
            'number' => '1',
            'level_id' => 'parter',
            'x' => 260,
            'y' => 320,
        ];
        $updatedLayout['elements'][4]['capacity'] = 180;

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->putJson("/api/organizer/halls/{$hall->id}", [
                'name' => 'Updated Hall',
                'address' => 'Новый адрес, 15',
                'description' => 'Updated description',
                'status' => 'active',
                'layout' => $updatedLayout,
            ])
            ->assertOk()
            ->assertJsonPath('hall.name', 'Updated Hall')
            ->assertJsonPath('hall.address', 'Новый адрес, 15')
            ->assertJsonPath('hall.capacities.seat', 3)
            ->assertJsonPath('hall.capacities.total', 184);

        $this->assertDatabaseHas('halls', [
            'id' => $hall->id,
            'name' => 'Updated Hall',
            'address' => 'Новый адрес, 15',
            'seat_capacity' => 3,
            'dancefloor_capacity' => 180,
            'total_capacity' => 184,
            'status' => 'active',
        ]);
    }

    public function test_destroy_archives_hall_instead_of_deleting_it(): void
    {
        $this->fakeOrganizerAuth(77);

        $hall = Hall::query()->create([
            'organizer_id' => 77,
            'name' => 'Archive Me',
            'address' => 'Нижний Новгород, пл. Минина, 2',
            'description' => null,
            'layout' => $this->validLayout(),
            'seat_capacity' => 2,
            'vip_capacity' => 1,
            'dancefloor_capacity' => 150,
            'total_capacity' => 153,
            'status' => 'active',
        ]);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->deleteJson("/api/organizer/halls/{$hall->id}")
            ->assertOk()
            ->assertJsonPath('hall.status', 'archived');

        $this->assertDatabaseHas('halls', [
            'id' => $hall->id,
            'status' => 'archived',
        ]);
    }

    public function test_destroy_rejects_archiving_hall_with_future_sessions(): void
    {
        $hall = Hall::query()->create([
            'organizer_id' => 77,
            'name' => 'Busy Hall',
            'address' => 'РќРёР¶РЅРёР№ РќРѕРІРіРѕСЂРѕРґ, РїР». РњРёРЅРёРЅР°, 2',
            'description' => null,
            'layout' => $this->validLayout(),
            'seat_capacity' => 2,
            'vip_capacity' => 1,
            'dancefloor_capacity' => 150,
            'total_capacity' => 153,
            'status' => 'active',
        ]);

        $this->fakeOrganizerAuth(77, [
            'hallUsage' => [
                $hall->id => [
                    'hall_id' => $hall->id,
                    'future_sessions_count' => 1,
                    'has_future_sessions' => true,
                    'future_sessions' => [
                        [
                            'id' => 501,
                            'event_id' => 301,
                            'event_title' => 'Future Show',
                            'start_time' => now()->addDays(3)->toISOString(),
                            'end_time' => now()->addDays(3)->addHours(2)->toISOString(),
                            'status' => 'scheduled',
                        ],
                    ],
                ],
            ],
        ]);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->deleteJson("/api/organizer/halls/{$hall->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hall']);
    }

    public function test_non_organizer_cannot_access_hall_management(): void
    {
        Http::fake([
            'http://127.0.0.1:8000/api/me' => Http::response([
                'user' => [
                    'id' => 10,
                    'full_name' => 'Simple User',
                    'email' => 'user@example.com',
                    'role' => [
                        'role' => 'user',
                    ],
                ],
            ], 200),
        ]);

        $this->withHeader('Authorization', 'Bearer user-token')
            ->getJson('/api/organizer/halls')
            ->assertForbidden()
            ->assertJsonPath('message', 'Only organizers can access hall management.');
    }

    /**
     * @param  array{hallUsage?: array<int, array<string, mixed>>}  $options
     */
    private function fakeOrganizerAuth(int $organizerId, array $options = []): void
    {
        $hallUsage = $options['hallUsage'] ?? [];

        Http::fake(function (Request $request) use ($organizerId, $hallUsage) {
            $url = $request->url();

            if ($url === 'http://127.0.0.1:8000/api/me') {
                return Http::response([
                    'user' => [
                        'id' => $organizerId,
                        'full_name' => 'Hall Organizer',
                        'email' => 'organizer@example.com',
                        'role' => [
                            'role' => 'organizer',
                        ],
                    ],
                ], 200);
            }

            if (preg_match('#^http://127\.0\.0\.1:8001/api/organizer/halls/(\d+)/usage$#', $url, $matches) === 1) {
                $hallId = (int) $matches[1];

                return Http::response(
                    $hallUsage[$hallId] ?? [
                        'hall_id' => $hallId,
                        'future_sessions_count' => 0,
                        'has_future_sessions' => false,
                        'future_sessions' => [],
                    ],
                    200
                );
            }

            return Http::response([], 404);
        });
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
                    'id' => 'parter',
                    'name' => 'Parter',
                    'order' => 1,
                ],
                [
                    'id' => 'vip',
                    'name' => 'VIP Balcony',
                    'order' => 2,
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
                    'level_id' => 'parter',
                    'x' => 180,
                    'y' => 260,
                ],
                [
                    'id' => 'seat-a2',
                    'type' => 'seat',
                    'label' => 'A-2',
                    'row' => 'A',
                    'number' => '2',
                    'level_id' => 'parter',
                    'x' => 220,
                    'y' => 260,
                ],
                [
                    'id' => 'vip-a1',
                    'type' => 'vip_seat',
                    'label' => 'VIP-1',
                    'row' => 'VIP',
                    'number' => '1',
                    'level_id' => 'vip',
                    'x' => 340,
                    'y' => 360,
                ],
                [
                    'id' => 'dancefloor-main',
                    'type' => 'dancefloor',
                    'label' => 'Main Dancefloor',
                    'x' => 250,
                    'y' => 180,
                    'width' => 320,
                    'height' => 110,
                    'capacity' => 150,
                ],
            ],
        ];
    }
}
