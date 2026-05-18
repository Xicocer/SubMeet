<?php

namespace Database\Seeders;

use App\Models\AuthUser;
use Illuminate\Database\Seeder;

class AuthUserProjectionSeeder extends Seeder
{
    public function run(): void
    {
        AuthUser::query()->delete();

        $authUsers = [
            [
                'auth_user_id' => 1101,
                'full_name' => 'Марина Соколова',
                'company_name' => 'ДКХ Booking',
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
                'company_name' => 'Milo Live',
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
            [
                'auth_user_id' => 1151,
                'full_name' => 'Виктор Платонов',
                'company_name' => 'Arena Spaces',
                'email' => 'arena@submeet.local',
                'phone' => '+79990002201',
                'birth_date' => null,
                'role' => 'venue_owner',
                'role_id' => 3,
                'status' => 1,
            ],
            [
                'auth_user_id' => 1152,
                'full_name' => 'Дарья Кравцова',
                'company_name' => 'Roofline Venues',
                'email' => 'roof@submeet.local',
                'phone' => '+79990002202',
                'birth_date' => null,
                'role' => 'venue_owner',
                'role_id' => 3,
                'status' => 1,
            ],
        ];

        foreach ($authUsers as $authUser) {
            AuthUser::query()->create([
                ...$authUser,
                'synced_at' => now(),
            ]);
        }
    }
}
