<?php

namespace Database\Seeders;

use App\Models\HallUnavailablePeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class HallUnavailablePeriodSeeder extends Seeder
{
    public function run(): void
    {
        HallUnavailablePeriod::query()->delete();

        $now = CarbonImmutable::now();

        $periods = [
            [
                'hall_id' => 2101,
                'unavailable_start' => $now->addDays(5)->setTime(9, 0),
                'unavailable_end' => $now->addDays(5)->setTime(18, 0),
                'reason' => 'Генеральная уборка и проверка звука после ночного концерта.',
            ],
            [
                'hall_id' => 2102,
                'unavailable_start' => $now->addDays(3)->setTime(10, 0),
                'unavailable_end' => $now->addDays(3)->setTime(17, 0),
                'reason' => 'Техническое обслуживание света и микрофонных линий.',
            ],
            [
                'hall_id' => 2103,
                'unavailable_start' => $now->addDays(7)->setTime(12, 0),
                'unavailable_end' => $now->addDays(7)->setTime(22, 0),
                'reason' => 'Закрытый технический прогон площадки.',
            ],
        ];

        foreach ($periods as $period) {
            HallUnavailablePeriod::query()->create($period);
        }
    }
}
