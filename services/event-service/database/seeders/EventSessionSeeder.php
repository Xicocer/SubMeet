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
        $sessionsByEvent = [
            'Ночной рок-концерт на крыше' => [
                [
                    'hall_id' => 101,
                    'start_time' => CarbonImmutable::parse('2026-05-02 19:00:00'),
                    'end_time' => CarbonImmutable::parse('2026-05-02 21:30:00'),
                    'base_price' => 2800,
                    'status' => EventSession::STATUS_SCHEDULED,
                ],
                [
                    'hall_id' => 101,
                    'start_time' => CarbonImmutable::parse('2026-05-03 20:00:00'),
                    'end_time' => CarbonImmutable::parse('2026-05-03 22:30:00'),
                    'base_price' => 3200,
                    'status' => EventSession::STATUS_SCHEDULED,
                ],
            ],
            'Большой весенний стендап' => [
                [
                    'hall_id' => 202,
                    'start_time' => CarbonImmutable::parse('2026-05-06 18:30:00'),
                    'end_time' => CarbonImmutable::parse('2026-05-06 20:00:00'),
                    'base_price' => 1800,
                    'status' => EventSession::STATUS_SCHEDULED,
                ],
                [
                    'hall_id' => 202,
                    'start_time' => CarbonImmutable::parse('2026-05-07 21:00:00'),
                    'end_time' => CarbonImmutable::parse('2026-05-07 22:30:00'),
                    'base_price' => 2200,
                    'status' => EventSession::STATUS_SCHEDULED,
                ],
            ],
            'Иммерсивный спектакль "Тишина сцены"' => [
                [
                    'hall_id' => 303,
                    'start_time' => CarbonImmutable::parse('2026-05-08 19:00:00'),
                    'end_time' => CarbonImmutable::parse('2026-05-08 21:00:00'),
                    'base_price' => 2400,
                    'status' => EventSession::STATUS_SCHEDULED,
                ],
                [
                    'hall_id' => 303,
                    'start_time' => CarbonImmutable::parse('2026-05-10 19:30:00'),
                    'end_time' => CarbonImmutable::parse('2026-05-10 21:30:00'),
                    'base_price' => 2600,
                    'status' => EventSession::STATUS_SCHEDULED,
                ],
            ],
            'Городская выставка цифрового искусства' => [
                [
                    'hall_id' => 404,
                    'start_time' => CarbonImmutable::parse('2026-05-01 12:00:00'),
                    'end_time' => CarbonImmutable::parse('2026-05-01 18:00:00'),
                    'base_price' => 900,
                    'status' => EventSession::STATUS_SCHEDULED,
                ],
                [
                    'hall_id' => 404,
                    'start_time' => CarbonImmutable::parse('2026-05-09 11:00:00'),
                    'end_time' => CarbonImmutable::parse('2026-05-09 17:00:00'),
                    'base_price' => 1100,
                    'status' => EventSession::STATUS_CANCELLED,
                ],
            ],
        ];

        foreach ($sessionsByEvent as $eventTitle => $sessions) {
            $event = Event::query()->where('title', $eventTitle)->first();

            if (!$event) {
                throw new InvalidArgumentException("Event [{$eventTitle}] for session seeding was not found.");
            }

            foreach ($sessions as $session) {
                EventSession::query()->updateOrCreate(
                    [
                        'event_id' => $event->id,
                        'hall_id' => $session['hall_id'],
                        'start_time' => $session['start_time'],
                    ],
                    [
                        'end_time' => $session['end_time'],
                        'base_price' => $session['base_price'],
                        'status' => $session['status'],
                    ]
                );
            }
        }
    }
}
