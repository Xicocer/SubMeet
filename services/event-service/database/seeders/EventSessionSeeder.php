<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

class EventSessionSeeder extends Seeder
{
    public function run(): void
    {
        $now = CarbonImmutable::now();

        $sessions = [
            [
                'id' => 4101,
                'event_id' => 3101,
                'hall_id' => 2101,
                'start_time' => $now->addDays(2)->setTime(20, 0),
                'end_time' => $now->addDays(2)->setTime(22, 30),
                'base_price' => 2800,
                'status' => EventSession::STATUS_SCHEDULED,
            ],
            [
                'id' => 4102,
                'event_id' => 3101,
                'hall_id' => 2101,
                'start_time' => $now->subDays(7)->setTime(20, 0),
                'end_time' => $now->subDays(7)->setTime(22, 30),
                'base_price' => 2500,
                'status' => EventSession::STATUS_COMPLETED,
            ],
            [
                'id' => 4201,
                'event_id' => 3102,
                'hall_id' => 2102,
                'start_time' => $now->addDay()->setTime(19, 0),
                'end_time' => $now->addDay()->setTime(20, 40),
                'base_price' => 1800,
                'status' => EventSession::STATUS_SCHEDULED,
            ],
            [
                'id' => 4202,
                'event_id' => 3102,
                'hall_id' => 2102,
                'start_time' => $now->addDays(9)->setTime(21, 0),
                'end_time' => $now->addDays(9)->setTime(22, 40),
                'base_price' => 2100,
                'status' => EventSession::STATUS_SCHEDULED,
            ],
            [
                'id' => 4301,
                'event_id' => 3103,
                'hall_id' => 2103,
                'start_time' => $now->addDays(4)->setTime(18, 30),
                'end_time' => $now->addDays(4)->setTime(20, 30),
                'base_price' => 2400,
                'status' => EventSession::STATUS_SCHEDULED,
            ],
            [
                'id' => 4401,
                'event_id' => 3104,
                'hall_id' => 2104,
                'start_time' => $now->addDays(3)->setTime(12, 0),
                'end_time' => $now->addDays(3)->setTime(18, 0),
                'base_price' => 900,
                'status' => EventSession::STATUS_SCHEDULED,
            ],
            [
                'id' => 4501,
                'event_id' => 3105,
                'hall_id' => 2102,
                'start_time' => $now->addDays(6)->setTime(20, 30),
                'end_time' => $now->addDays(6)->setTime(22, 0),
                'base_price' => 2600,
                'status' => EventSession::STATUS_SCHEDULED,
            ],
            [
                'id' => 4601,
                'event_id' => 3106,
                'hall_id' => 2101,
                'start_time' => $now->addDays(8)->setTime(19, 30),
                'end_time' => $now->addDays(8)->setTime(21, 30),
                'base_price' => 2300,
                'status' => EventSession::STATUS_SCHEDULED,
            ],
            [
                'id' => 4701,
                'event_id' => 3107,
                'hall_id' => 2101,
                'start_time' => $now->addDays(11)->setTime(18, 0),
                'end_time' => $now->addDays(11)->setTime(23, 0),
                'base_price' => 3500,
                'status' => EventSession::STATUS_CANCELLED,
            ],
        ];

        foreach ($sessions as $sessionData) {
            if (!Event::query()->whereKey($sessionData['event_id'])->exists()) {
                throw new InvalidArgumentException("Demo event [{$sessionData['event_id']}] was not found for session seeding.");
            }

            EventSession::query()->updateOrCreate(
                ['id' => $sessionData['id']],
                [
                    'event_id' => $sessionData['event_id'],
                    'hall_id' => $sessionData['hall_id'],
                    'start_time' => $sessionData['start_time'],
                    'end_time' => $sessionData['end_time'],
                    'base_price' => $sessionData['base_price'],
                    'status' => $sessionData['status'],
                ]
            );
        }
    }
}
