<?php

namespace Database\Seeders;

use App\Models\AuthUser;
use Illuminate\Database\Seeder;

class AuthUserProjectionSeeder extends Seeder
{
    public function run(): void
    {
        $organizers = [
            [
                'auth_user_id' => 1101,
                'full_name' => 'Марина Соколова',
                'company_name' => 'ДКХ',
                'email' => 'dkh@submeet.local',
                'phone' => '+79990002101',
                'birth_date' => null,
                'role' => 'organizer',
                'role_id' => 2,
                'status' => 1,
            ],
            [
                'auth_user_id' => 1102,
                'full_name' => 'Артем Воронов',
                'company_name' => 'Milo Concert Hall',
                'email' => 'milo@submeet.local',
                'phone' => '+79990002102',
                'birth_date' => null,
                'role' => 'organizer',
                'role_id' => 2,
                'status' => 1,
            ],
            [
                'auth_user_id' => 1103,
                'full_name' => 'Екатерина Новикова',
                'company_name' => 'City Light Events',
                'email' => 'citylight@submeet.local',
                'phone' => '+79990002103',
                'birth_date' => null,
                'role' => 'organizer',
                'role_id' => 2,
                'status' => 1,
            ],
            [
                'auth_user_id' => 1104,
                'full_name' => 'Илья Лебедев',
                'company_name' => 'Old Arena Group',
                'email' => 'oldarena@submeet.local',
                'phone' => '+79990002104',
                'birth_date' => null,
                'role' => 'organizer',
                'role_id' => 2,
                'status' => 0,
            ],
        ];

        foreach ($organizers as $organizer) {
            AuthUser::query()->updateOrCreate(
                ['auth_user_id' => $organizer['auth_user_id']],
                [
                    'full_name' => $organizer['full_name'],
                    'company_name' => $organizer['company_name'],
                    'email' => $organizer['email'],
                    'phone' => $organizer['phone'],
                    'birth_date' => $organizer['birth_date'],
                    'role' => $organizer['role'],
                    'role_id' => $organizer['role_id'],
                    'status' => $organizer['status'],
                    'synced_at' => now(),
                ]
            );
        }
    }
}
