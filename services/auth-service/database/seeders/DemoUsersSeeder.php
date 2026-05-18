<?php

namespace Database\Seeders;

use App\Models\OrganizerProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $roleIds = Role::query()->pluck('id', 'role');

        OrganizerProfile::query()->delete();
        User::query()->delete();

        $users = [
            [
                'id' => 1201,
                'full_name' => 'Анна Смирнова',
                'email' => 'anna@submeet.local',
                'phone' => '+79990001101',
                'birth_date' => '1998-04-12',
                'password' => 'Password123!',
                'role' => 'user',
                'status' => 1,
            ],
            [
                'id' => 1202,
                'full_name' => 'Никита Орлов',
                'email' => 'nikita@submeet.local',
                'phone' => '+79990001102',
                'birth_date' => '1995-08-23',
                'password' => 'Password123!',
                'role' => 'user',
                'status' => 1,
            ],
            [
                'id' => 1203,
                'full_name' => 'Ольга Миронова',
                'email' => 'olga@submeet.local',
                'phone' => '+79990001103',
                'birth_date' => '2001-02-03',
                'password' => 'Password123!',
                'role' => 'user',
                'status' => 1,
            ],
            [
                'id' => 1101,
                'full_name' => 'Марина Соколова',
                'email' => 'dkh@submeet.local',
                'phone' => '+79990002101',
                'birth_date' => null,
                'password' => 'Password123!',
                'role' => 'organizer',
                'status' => 1,
                'profile' => [
                    'company_name' => 'ДКХ Booking',
                    'moderation_status' => 'approved',
                    'moderation_note' => 'Организатор проверен и может публиковать мероприятия.',
                ],
            ],
            [
                'id' => 1102,
                'full_name' => 'Артем Воронов',
                'email' => 'milo@submeet.local',
                'phone' => '+79990002102',
                'birth_date' => null,
                'password' => 'Password123!',
                'role' => 'organizer',
                'status' => 1,
                'profile' => [
                    'company_name' => 'Milo Live',
                    'moderation_status' => 'approved',
                    'moderation_note' => 'Организатор одобрен для работы с площадками и событиями.',
                ],
            ],
            [
                'id' => 1103,
                'full_name' => 'Екатерина Новикова',
                'email' => 'citylight@submeet.local',
                'phone' => '+79990002103',
                'birth_date' => null,
                'password' => 'Password123!',
                'role' => 'organizer',
                'status' => 1,
                'profile' => [
                    'company_name' => 'City Light Events',
                    'moderation_status' => 'pending',
                    'moderation_note' => 'Ожидает проверки документов организатора.',
                ],
            ],
            [
                'id' => 1104,
                'full_name' => 'Илья Лебедев',
                'email' => 'oldarena@submeet.local',
                'phone' => '+79990002104',
                'birth_date' => null,
                'password' => 'Password123!',
                'role' => 'organizer',
                'status' => 0,
                'profile' => [
                    'company_name' => 'Old Arena Group',
                    'moderation_status' => 'blocked',
                    'moderation_note' => 'Аккаунт организатора временно ограничен после ручной проверки.',
                ],
            ],
            [
                'id' => 1151,
                'full_name' => 'Виктор Платонов',
                'email' => 'arena@submeet.local',
                'phone' => '+79990002201',
                'birth_date' => null,
                'password' => 'Password123!',
                'role' => 'venue_owner',
                'status' => 1,
                'profile' => [
                    'company_name' => 'Arena Spaces',
                    'moderation_status' => 'approved',
                    'moderation_note' => 'Владелец площадок одобрен. Можно принимать заявки на аренду.',
                ],
            ],
            [
                'id' => 1152,
                'full_name' => 'Дарья Кравцова',
                'email' => 'roof@submeet.local',
                'phone' => '+79990002202',
                'birth_date' => null,
                'password' => 'Password123!',
                'role' => 'venue_owner',
                'status' => 1,
                'profile' => [
                    'company_name' => 'Roofline Venues',
                    'moderation_status' => 'approved',
                    'moderation_note' => 'Площадки доступны для бронирования организаторами.',
                ],
            ],
            [
                'id' => 1153,
                'full_name' => 'София Белова',
                'email' => 'loft@submeet.local',
                'phone' => '+79990002203',
                'birth_date' => null,
                'password' => 'Password123!',
                'role' => 'venue_owner',
                'status' => 1,
                'profile' => [
                    'company_name' => 'Loft District',
                    'moderation_status' => 'pending',
                    'moderation_note' => 'Ожидает проверки прав на площадку.',
                ],
            ],
            [
                'id' => 1154,
                'full_name' => 'Павел Чернов',
                'email' => 'closedhall@submeet.local',
                'phone' => '+79990002204',
                'birth_date' => null,
                'password' => 'Password123!',
                'role' => 'venue_owner',
                'status' => 0,
                'profile' => [
                    'company_name' => 'Closed Hall Ops',
                    'moderation_status' => 'blocked',
                    'moderation_note' => 'Площадка заблокирована до завершения проверки документов.',
                ],
            ],
            [
                'id' => 1301,
                'full_name' => 'Вадим Администратор',
                'email' => 'admin@submeet.local',
                'phone' => '+79990003101',
                'birth_date' => '1994-01-15',
                'password' => 'Password123!',
                'role' => 'admin',
                'status' => 1,
            ],
        ];

        foreach ($users as $userData) {
            $user = User::query()->create([
                'id' => $userData['id'],
                'full_name' => $userData['full_name'],
                'email' => $userData['email'],
                'phone' => $userData['phone'],
                'birth_date' => $userData['birth_date'],
                'password' => $userData['password'],
                'role_id' => $roleIds[(string) $userData['role']] ?? null,
                'status' => $userData['status'],
            ]);

            if (!in_array($userData['role'], ['organizer', 'venue_owner'], true)) {
                continue;
            }

            $moderationStatus = (string) data_get($userData, 'profile.moderation_status', 'pending');
            $moderatedAt = match ($moderationStatus) {
                'approved' => now()->subDays(10),
                'blocked' => now()->subDays(2),
                'rejected' => now()->subDay(),
                default => null,
            };

            OrganizerProfile::query()->create([
                'user_id' => $user->id,
                'company_name' => data_get($userData, 'profile.company_name'),
                'moderation_status' => $moderationStatus,
                'moderation_note' => data_get($userData, 'profile.moderation_note'),
                'moderated_at' => $moderatedAt,
            ]);
        }
    }
}
