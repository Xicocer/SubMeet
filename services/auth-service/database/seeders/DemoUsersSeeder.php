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
        $roleIds = Role::query()
            ->pluck('id', 'role');

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
                'id' => 1101,
                'full_name' => 'Марина Соколова',
                'email' => 'dkh@submeet.local',
                'phone' => '+79990002101',
                'birth_date' => null,
                'password' => 'Password123!',
                'role' => 'organizer',
                'status' => 1,
                'organizer_profile' => [
                    'company_name' => 'ДКХ',
                    'moderation_status' => 'approved',
                    'moderation_note' => 'Проверенные реквизиты, можно публиковать мероприятия.',
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
                'organizer_profile' => [
                    'company_name' => 'Milo Concert Hall',
                    'moderation_status' => 'approved',
                    'moderation_note' => 'Аккаунт одобрен для полноценной работы в системе.',
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
                'organizer_profile' => [
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
                'organizer_profile' => [
                    'company_name' => 'Old Arena Group',
                    'moderation_status' => 'blocked',
                    'moderation_note' => 'Аккаунт временно ограничен после ручной проверки.',
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
            $roleName = (string) $userData['role'];
            $moderationStatus = (string) data_get($userData, 'organizer_profile.moderation_status', 'pending');
            $moderatedAt = match ($moderationStatus) {
                'approved' => now()->subDays(12),
                'blocked' => now()->subDays(2),
                'rejected' => now()->subDay(),
                default => null,
            };

            $user = User::query()->updateOrCreate(
                ['id' => $userData['id']],
                [
                    'full_name' => $userData['full_name'],
                    'email' => $userData['email'],
                    'phone' => $userData['phone'],
                    'birth_date' => $userData['birth_date'],
                    'password' => $userData['password'],
                    'role_id' => $roleIds[$roleName] ?? null,
                    'status' => $userData['status'],
                ]
            );

            if ($roleName !== 'organizer') {
                OrganizerProfile::query()->where('user_id', $user->id)->delete();
                continue;
            }

            OrganizerProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'company_name' => data_get($userData, 'organizer_profile.company_name'),
                    'moderation_status' => $moderationStatus,
                    'moderation_note' => data_get($userData, 'organizer_profile.moderation_note'),
                    'moderated_at' => $moderatedAt,
                ]
            );
        }
    }
}
