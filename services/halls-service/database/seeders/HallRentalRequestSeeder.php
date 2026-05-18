<?php

namespace Database\Seeders;

use App\Models\HallRentalRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class HallRentalRequestSeeder extends Seeder
{
    public function run(): void
    {
        HallRentalRequest::query()->delete();

        $now = CarbonImmutable::now();

        $requests = [
            [
                'id' => 5101,
                'hall_id' => 2101,
                'organizer_id' => 1101,
                'event_id' => 3101,
                'requested_start' => $now->addDays(2)->setTime(20, 0),
                'requested_end' => $now->addDays(2)->setTime(22, 30),
                'hourly_rate' => 18000,
                'status' => HallRentalRequest::STATUS_APPROVED,
                'organizer_message' => 'Нужен вечерний слот под концерт на крыше с танцполом и VIP-рядом.',
                'response_note' => 'Слот подтвержден. Монтаж разрешен за два часа до начала.',
                'responded_at' => $now->subDays(5),
            ],
            [
                'id' => 5102,
                'hall_id' => 2101,
                'organizer_id' => 1101,
                'event_id' => 3101,
                'requested_start' => $now->subDays(7)->setTime(20, 0),
                'requested_end' => $now->subDays(7)->setTime(22, 30),
                'hourly_rate' => 18000,
                'status' => HallRentalRequest::STATUS_APPROVED,
                'organizer_message' => 'Повторный слот для уже прошедшего рок-концерта.',
                'response_note' => 'Площадка была предоставлена без ограничений.',
                'responded_at' => $now->subDays(14),
            ],
            [
                'id' => 5201,
                'hall_id' => 2102,
                'organizer_id' => 1102,
                'event_id' => 3102,
                'requested_start' => $now->addDay()->setTime(19, 0),
                'requested_end' => $now->addDay()->setTime(20, 40),
                'hourly_rate' => 14000,
                'status' => HallRentalRequest::STATUS_APPROVED,
                'organizer_message' => 'Нужен зал под весенний стендап с основной рассадкой и VIP-балконом.',
                'response_note' => 'Зал подтвержден. Техрайдер принят.',
                'responded_at' => $now->subDays(3),
            ],
            [
                'id' => 5202,
                'hall_id' => 2102,
                'organizer_id' => 1102,
                'event_id' => 3102,
                'requested_start' => $now->addDays(9)->setTime(21, 0),
                'requested_end' => $now->addDays(9)->setTime(22, 40),
                'hourly_rate' => 14000,
                'status' => HallRentalRequest::STATUS_APPROVED,
                'organizer_message' => 'Нужен второй стендап-вечер через неделю на той же площадке.',
                'response_note' => 'Слот подтвержден повторно.',
                'responded_at' => $now->subDays(2),
            ],
            [
                'id' => 5301,
                'hall_id' => 2103,
                'organizer_id' => 1101,
                'event_id' => 3103,
                'requested_start' => $now->addDays(4)->setTime(18, 30),
                'requested_end' => $now->addDays(4)->setTime(20, 30),
                'hourly_rate' => 22000,
                'status' => HallRentalRequest::STATUS_APPROVED,
                'organizer_message' => 'Нужна black box площадка под иммерсивный театральный показ.',
                'response_note' => 'Одобрено. Просьба прислать схему посадки финально за день до мероприятия.',
                'responded_at' => $now->subDays(4),
            ],
            [
                'id' => 5401,
                'hall_id' => 2104,
                'organizer_id' => 1102,
                'event_id' => 3104,
                'requested_start' => $now->addDays(6)->setTime(12, 0),
                'requested_end' => $now->addDays(6)->setTime(18, 0),
                'hourly_rate' => 26000,
                'status' => HallRentalRequest::STATUS_APPROVED,
                'organizer_message' => 'Хотим провести городскую digital-выставку с открытой дневной программой.',
                'response_note' => 'Площадка подтверждена для дневной выставочной программы.',
                'responded_at' => $now->subDays(2),
            ],
            [
                'id' => 5501,
                'hall_id' => 2103,
                'organizer_id' => 1101,
                'event_id' => 3105,
                'requested_start' => $now->addDays(8)->setTime(20, 30),
                'requested_end' => $now->addDays(8)->setTime(22, 0),
                'hourly_rate' => 22000,
                'status' => HallRentalRequest::STATUS_REJECTED,
                'organizer_message' => 'Планируем камерный джазовый вечер для пар.',
                'response_note' => 'Площадка занята корпоративной репетицией в этот слот.',
                'responded_at' => $now->subDay(),
            ],
            [
                'id' => 5601,
                'hall_id' => 2101,
                'organizer_id' => 1102,
                'event_id' => 3107,
                'requested_start' => $now->addDays(11)->setTime(18, 0),
                'requested_end' => $now->addDays(11)->setTime(23, 0),
                'hourly_rate' => 18000,
                'status' => HallRentalRequest::STATUS_CANCELLED,
                'organizer_message' => 'Open air фестиваль с большой площадкой и танцполом.',
                'response_note' => 'Организатор сам отменил запрос после смены концепции события.',
                'responded_at' => $now->subHours(18),
            ],
        ];

        foreach ($requests as $requestData) {
            $hours = max(
                1,
                $requestData['requested_start']->diffInMinutes($requestData['requested_end'], true) / 60
            );

            HallRentalRequest::query()->create([
                'id' => $requestData['id'],
                'hall_id' => $requestData['hall_id'],
                'organizer_id' => $requestData['organizer_id'],
                'event_id' => $requestData['event_id'],
                'requested_start' => $requestData['requested_start'],
                'requested_end' => $requestData['requested_end'],
                'hourly_rate' => $requestData['hourly_rate'],
                'total_amount' => round($hours * $requestData['hourly_rate'], 2),
                'status' => $requestData['status'],
                'organizer_message' => $requestData['organizer_message'],
                'response_note' => $requestData['response_note'],
                'responded_at' => $requestData['responded_at'],
            ]);
        }
    }
}
