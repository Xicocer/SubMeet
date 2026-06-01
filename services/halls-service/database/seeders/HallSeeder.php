<?php

namespace Database\Seeders;

use App\Models\Hall;
use App\Models\HallRentalRequest;
use App\Models\HallUnavailablePeriod;
use Illuminate\Database\Seeder;

class HallSeeder extends Seeder
{
    public function run(): void
    {
        HallRentalRequest::query()->delete();
        HallUnavailablePeriod::query()->delete();
        Hall::query()->delete();

        $halls = [
            [
                'id' => 2101,
                'venue_owner_id' => 1152,
                'name' => 'Крыша на Покровке',
                'address' => 'Нижний Новгород, ул. Большая Покровская, 18',
                'description' => 'Открытая концертная площадка на крыше для летних концертов, dj-сетов и камерных фестивалей.',
                'photo_urls' => [
                    'https://picsum.photos/seed/submeet-rooftop-hall-1/1200/800',
                    'https://picsum.photos/seed/submeet-rooftop-hall-2/1200/800',
                    'https://picsum.photos/seed/submeet-rooftop-hall-3/1200/800',
                ],
                'hourly_rate' => 18000,
                'layout' => $this->buildRooftopConcertLayout(),
                'status' => Hall::STATUS_ACTIVE,
            ],
            [
                'id' => 2102,
                'venue_owner_id' => 1151,
                'name' => 'Standup Hall',
                'address' => 'Нижний Новгород, ул. Рождественская, 22',
                'description' => 'Камерная площадка для стендапа, открытых микрофонов и небольших концертов.',
                'photo_urls' => [
                    'https://picsum.photos/seed/submeet-standup-hall-1/1200/800',
                    'https://picsum.photos/seed/submeet-standup-hall-2/1200/800',
                ],
                'hourly_rate' => 14000,
                'layout' => $this->buildComedyHallLayout(),
                'status' => Hall::STATUS_ACTIVE,
            ],
            [
                'id' => 2103,
                'venue_owner_id' => 1151,
                'name' => 'Black Box Arena',
                'address' => 'Нижний Новгород, ул. Варварская, 9',
                'description' => 'Трансформируемая black box сцена для театра, перформансов и иммерсивных шоу.',
                'photo_urls' => [
                    'https://picsum.photos/seed/submeet-blackbox-hall-1/1200/800',
                    'https://picsum.photos/seed/submeet-blackbox-hall-2/1200/800',
                ],
                'hourly_rate' => 22000,
                'layout' => $this->buildBlackBoxLayout(),
                'status' => Hall::STATUS_ACTIVE,
            ],
            [
                'id' => 2104,
                'venue_owner_id' => 1152,
                'name' => 'Digital Pavilion',
                'address' => 'Нижний Новгород, Нижне-Волжская набережная, 3',
                'description' => 'Павильон для выставок, digital-экспозиций и дневных фестивальных форматов.',
                'photo_urls' => [
                    'https://picsum.photos/seed/submeet-pavilion-hall-1/1200/800',
                    'https://picsum.photos/seed/submeet-pavilion-hall-2/1200/800',
                ],
                'hourly_rate' => 26000,
                'layout' => $this->buildExhibitionLayout(),
                'status' => Hall::STATUS_ACTIVE,
            ],
            [
                'id' => 2105,
                'venue_owner_id' => 1151,
                'name' => 'Архивная сцена',
                'address' => 'Нижний Новгород, ул. Пискунова, 11',
                'description' => 'Небольшая архивная площадка, временно выведенная из оборота.',
                'photo_urls' => [
                    'https://picsum.photos/seed/submeet-small-hall-1/1200/800',
                ],
                'hourly_rate' => 9000,
                'layout' => $this->buildSmallStageLayout(),
                'status' => Hall::STATUS_ARCHIVED,
            ],
        ];

        foreach ($halls as $hallData) {
            $capacities = $this->countCapacities($hallData['layout']['elements']);

            Hall::query()->create([
                'id' => $hallData['id'],
                'venue_owner_id' => $hallData['venue_owner_id'],
                'name' => $hallData['name'],
                'address' => $hallData['address'],
                'description' => $hallData['description'],
                'photo_urls' => $hallData['photo_urls'] ?? [],
                'hourly_rate' => $hallData['hourly_rate'],
                'layout' => $hallData['layout'],
                'seat_capacity' => $capacities['seat'],
                'vip_capacity' => $capacities['vip'],
                'dancefloor_capacity' => $capacities['dancefloor'],
                'total_capacity' => $capacities['total'],
                'status' => $hallData['status'],
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $elements
     * @return array{seat: int, table: int, vip: int, dancefloor: int, total: int}
     */
    private function countCapacities(array $elements): array
    {
        $seat = 0;
        $table = 0;
        $vip = 0;
        $dancefloor = 0;

        foreach ($elements as $element) {
            $type = (string) ($element['type'] ?? '');

            if ($type === 'seat') {
                $seat++;
            } elseif ($type === 'vip_seat') {
                $vip++;
            } elseif ($type === 'table') {
                $table += max(1, (int) ($element['capacity'] ?? 2));
            } elseif ($type === 'dancefloor') {
                $dancefloor += (int) ($element['capacity'] ?? 0);
            }
        }

        $seat += $table;

        return [
            'seat' => $seat,
            'table' => $table,
            'vip' => $vip,
            'dancefloor' => $dancefloor,
            'total' => $seat + $vip + $dancefloor,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRooftopConcertLayout(): array
    {
        return [
            'canvas' => ['width' => 960, 'height' => 640],
            'levels' => [
                ['id' => 'parter', 'name' => 'Партер', 'order' => 1],
            ],
            'elements' => [
                ['id' => 'stage-main', 'type' => 'stage', 'label' => 'Главная сцена', 'x' => 340, 'y' => 48, 'width' => 280, 'height' => 96],
                ['id' => 'dancefloor-main', 'type' => 'dancefloor', 'label' => 'Танцпол', 'x' => 340, 'y' => 160, 'width' => 280, 'height' => 170, 'capacity' => 80, 'level_id' => null],
                ['id' => 'seat-a1', 'type' => 'seat', 'label' => 'A-1', 'x' => 340, 'y' => 350, 'width' => 42, 'height' => 42, 'row' => 'A', 'number' => '1', 'level_id' => 'parter'],
                ['id' => 'seat-a2', 'type' => 'seat', 'label' => 'A-2', 'x' => 400, 'y' => 350, 'width' => 42, 'height' => 42, 'row' => 'A', 'number' => '2', 'level_id' => 'parter'],
                ['id' => 'seat-a3', 'type' => 'seat', 'label' => 'A-3', 'x' => 460, 'y' => 350, 'width' => 42, 'height' => 42, 'row' => 'A', 'number' => '3', 'level_id' => 'parter'],
                ['id' => 'seat-a4', 'type' => 'seat', 'label' => 'A-4', 'x' => 520, 'y' => 350, 'width' => 42, 'height' => 42, 'row' => 'A', 'number' => '4', 'level_id' => 'parter'],
                ['id' => 'seat-a5', 'type' => 'seat', 'label' => 'A-5', 'x' => 580, 'y' => 350, 'width' => 42, 'height' => 42, 'row' => 'A', 'number' => '5', 'level_id' => 'parter'],
                ['id' => 'vip-b1', 'type' => 'vip_seat', 'label' => 'VIP B-1', 'x' => 370, 'y' => 420, 'width' => 44, 'height' => 44, 'row' => 'B', 'number' => '1', 'level_id' => 'parter'],
                ['id' => 'vip-b2', 'type' => 'vip_seat', 'label' => 'VIP B-2', 'x' => 430, 'y' => 420, 'width' => 44, 'height' => 44, 'row' => 'B', 'number' => '2', 'level_id' => 'parter'],
                ['id' => 'vip-b3', 'type' => 'vip_seat', 'label' => 'VIP B-3', 'x' => 490, 'y' => 420, 'width' => 44, 'height' => 44, 'row' => 'B', 'number' => '3', 'level_id' => 'parter'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildComedyHallLayout(): array
    {
        return [
            'canvas' => ['width' => 960, 'height' => 640],
            'levels' => [
                ['id' => 'main', 'name' => 'Основной зал', 'order' => 1],
                ['id' => 'balcony', 'name' => 'Балкон', 'order' => 2],
            ],
            'elements' => [
                ['id' => 'stage-standup', 'type' => 'stage', 'label' => 'Comedy Stage', 'x' => 320, 'y' => 52, 'width' => 320, 'height' => 100],
                ['id' => 'seat-c1', 'type' => 'seat', 'label' => 'C-1', 'x' => 300, 'y' => 240, 'width' => 42, 'height' => 42, 'row' => 'C', 'number' => '1', 'level_id' => 'main'],
                ['id' => 'seat-c2', 'type' => 'seat', 'label' => 'C-2', 'x' => 360, 'y' => 240, 'width' => 42, 'height' => 42, 'row' => 'C', 'number' => '2', 'level_id' => 'main'],
                ['id' => 'seat-c3', 'type' => 'seat', 'label' => 'C-3', 'x' => 420, 'y' => 240, 'width' => 42, 'height' => 42, 'row' => 'C', 'number' => '3', 'level_id' => 'main'],
                ['id' => 'seat-c4', 'type' => 'seat', 'label' => 'C-4', 'x' => 480, 'y' => 240, 'width' => 42, 'height' => 42, 'row' => 'C', 'number' => '4', 'level_id' => 'main'],
                ['id' => 'seat-c5', 'type' => 'seat', 'label' => 'C-5', 'x' => 540, 'y' => 240, 'width' => 42, 'height' => 42, 'row' => 'C', 'number' => '5', 'level_id' => 'main'],
                ['id' => 'seat-d1', 'type' => 'seat', 'label' => 'D-1', 'x' => 330, 'y' => 320, 'width' => 42, 'height' => 42, 'row' => 'D', 'number' => '1', 'level_id' => 'main'],
                ['id' => 'seat-d2', 'type' => 'seat', 'label' => 'D-2', 'x' => 390, 'y' => 320, 'width' => 42, 'height' => 42, 'row' => 'D', 'number' => '2', 'level_id' => 'main'],
                ['id' => 'seat-d3', 'type' => 'seat', 'label' => 'D-3', 'x' => 450, 'y' => 320, 'width' => 42, 'height' => 42, 'row' => 'D', 'number' => '3', 'level_id' => 'main'],
                ['id' => 'seat-d4', 'type' => 'seat', 'label' => 'D-4', 'x' => 510, 'y' => 320, 'width' => 42, 'height' => 42, 'row' => 'D', 'number' => '4', 'level_id' => 'main'],
                ['id' => 'table-main-1', 'type' => 'table', 'label' => 'Table 1', 'x' => 610, 'y' => 300, 'width' => 118, 'height' => 86, 'capacity' => 4, 'level_id' => 'main'],
                ['id' => 'vip-e1', 'type' => 'vip_seat', 'label' => 'VIP E-1', 'x' => 360, 'y' => 420, 'width' => 44, 'height' => 44, 'row' => 'E', 'number' => '1', 'level_id' => 'balcony'],
                ['id' => 'vip-e2', 'type' => 'vip_seat', 'label' => 'VIP E-2', 'x' => 430, 'y' => 420, 'width' => 44, 'height' => 44, 'row' => 'E', 'number' => '2', 'level_id' => 'balcony'],
                ['id' => 'vip-e3', 'type' => 'vip_seat', 'label' => 'VIP E-3', 'x' => 500, 'y' => 420, 'width' => 44, 'height' => 44, 'row' => 'E', 'number' => '3', 'level_id' => 'balcony'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBlackBoxLayout(): array
    {
        return [
            'canvas' => ['width' => 960, 'height' => 640],
            'levels' => [
                ['id' => 'immersive', 'name' => 'Иммерсивный круг', 'order' => 1],
            ],
            'elements' => [
                ['id' => 'stage-blackbox', 'type' => 'stage', 'label' => 'Black Box Stage', 'x' => 280, 'y' => 60, 'width' => 400, 'height' => 90],
                ['id' => 'seat-f1', 'type' => 'seat', 'label' => 'F-1', 'x' => 280, 'y' => 260, 'width' => 42, 'height' => 42, 'row' => 'F', 'number' => '1', 'level_id' => 'immersive'],
                ['id' => 'seat-f2', 'type' => 'seat', 'label' => 'F-2', 'x' => 340, 'y' => 260, 'width' => 42, 'height' => 42, 'row' => 'F', 'number' => '2', 'level_id' => 'immersive'],
                ['id' => 'seat-f3', 'type' => 'seat', 'label' => 'F-3', 'x' => 400, 'y' => 260, 'width' => 42, 'height' => 42, 'row' => 'F', 'number' => '3', 'level_id' => 'immersive'],
                ['id' => 'seat-f4', 'type' => 'seat', 'label' => 'F-4', 'x' => 460, 'y' => 260, 'width' => 42, 'height' => 42, 'row' => 'F', 'number' => '4', 'level_id' => 'immersive'],
                ['id' => 'seat-f5', 'type' => 'seat', 'label' => 'F-5', 'x' => 520, 'y' => 260, 'width' => 42, 'height' => 42, 'row' => 'F', 'number' => '5', 'level_id' => 'immersive'],
                ['id' => 'seat-f6', 'type' => 'seat', 'label' => 'F-6', 'x' => 580, 'y' => 260, 'width' => 42, 'height' => 42, 'row' => 'F', 'number' => '6', 'level_id' => 'immersive'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildExhibitionLayout(): array
    {
        return [
            'canvas' => ['width' => 960, 'height' => 640],
            'levels' => [
                ['id' => 'expo', 'name' => 'Экспозиция', 'order' => 1],
            ],
            'elements' => [
                ['id' => 'stage-talks', 'type' => 'stage', 'label' => 'Talk Stage', 'x' => 310, 'y' => 70, 'width' => 340, 'height' => 90],
                ['id' => 'dancefloor-expo', 'type' => 'dancefloor', 'label' => 'Open Zone', 'x' => 300, 'y' => 210, 'width' => 360, 'height' => 180, 'capacity' => 120, 'level_id' => 'expo'],
                ['id' => 'seat-g1', 'type' => 'seat', 'label' => 'G-1', 'x' => 280, 'y' => 450, 'width' => 42, 'height' => 42, 'row' => 'G', 'number' => '1', 'level_id' => 'expo'],
                ['id' => 'seat-g2', 'type' => 'seat', 'label' => 'G-2', 'x' => 340, 'y' => 450, 'width' => 42, 'height' => 42, 'row' => 'G', 'number' => '2', 'level_id' => 'expo'],
                ['id' => 'seat-g3', 'type' => 'seat', 'label' => 'G-3', 'x' => 400, 'y' => 450, 'width' => 42, 'height' => 42, 'row' => 'G', 'number' => '3', 'level_id' => 'expo'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSmallStageLayout(): array
    {
        return [
            'canvas' => ['width' => 800, 'height' => 560],
            'levels' => [
                ['id' => 'small', 'name' => 'Малый зал', 'order' => 1],
            ],
            'elements' => [
                ['id' => 'stage-small', 'type' => 'stage', 'label' => 'Small Stage', 'x' => 260, 'y' => 50, 'width' => 280, 'height' => 80],
                ['id' => 'seat-h1', 'type' => 'seat', 'label' => 'H-1', 'x' => 280, 'y' => 220, 'width' => 42, 'height' => 42, 'row' => 'H', 'number' => '1', 'level_id' => 'small'],
                ['id' => 'seat-h2', 'type' => 'seat', 'label' => 'H-2', 'x' => 340, 'y' => 220, 'width' => 42, 'height' => 42, 'row' => 'H', 'number' => '2', 'level_id' => 'small'],
                ['id' => 'seat-h3', 'type' => 'seat', 'label' => 'H-3', 'x' => 400, 'y' => 220, 'width' => 42, 'height' => 42, 'row' => 'H', 'number' => '3', 'level_id' => 'small'],
            ],
        ];
    }
}
