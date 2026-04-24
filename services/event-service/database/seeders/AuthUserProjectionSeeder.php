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
                'auth_user_id' => 1,
                'full_name' => 'Марина Соколова',
                'email' => 'marina.sokolova@example.com',
                'phone' => '+79990000001',
                'birth_date' => '1994-03-18',
                'role' => 'organizer',
                'role_id' => 2,
                'status' => 1,
            ],
            [
                'auth_user_id' => 2,
                'full_name' => 'Артем Воронов',
                'email' => 'artem.voronov@example.com',
                'phone' => '+79990000002',
                'birth_date' => '1991-09-07',
                'role' => 'organizer',
                'role_id' => 2,
                'status' => 1,
            ],
            [
                'auth_user_id' => 3,
                'full_name' => 'City Light Events',
                'email' => 'citylight@example.com',
                'phone' => '+79990000003',
                'birth_date' => '1989-06-14',
                'role' => 'organizer',
                'role_id' => 2,
                'status' => 1,
            ],
        ];

        foreach ($organizers as $organizer) {
            AuthUser::query()->updateOrCreate(
                ['auth_user_id' => $organizer['auth_user_id']],
                [
                    'full_name' => $organizer['full_name'],
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
