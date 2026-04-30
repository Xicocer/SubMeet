<?php

namespace Tests\Unit;

use App\Models\AuthUser;
use App\Services\AuthUserProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthUserProjectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_local_projection_from_auth_user_event(): void
    {
        $projector = app(AuthUserProjector::class);

        $projector->handle([
            'event_type' => 'auth.user.created',
            'payload' => [
                'id' => 15,
                'full_name' => 'Иван Иванов',
                'organizer_profile' => [
                    'company_name' => 'Milo Concert Hall',
                ],
                'email' => 'ivan@example.com',
                'phone' => '+79990000000',
                'birth_date' => '2000-01-01',
                'status' => 1,
                'role' => [
                    'id' => 2,
                    'role' => 'organizer',
                ],
            ],
        ]);

        $this->assertDatabaseHas('auth_users', [
            'auth_user_id' => 15,
            'email' => 'ivan@example.com',
            'company_name' => 'Milo Concert Hall',
            'role' => 'organizer',
        ]);
    }

    public function test_it_updates_existing_projection_from_auth_user_event(): void
    {
        $projector = app(AuthUserProjector::class);

        AuthUser::query()->create([
            'auth_user_id' => 15,
            'full_name' => 'Иван Иванов',
            'company_name' => 'Old Company',
            'email' => 'ivan@example.com',
            'phone' => '+79990000000',
            'birth_date' => '2000-01-01',
            'role' => 'user',
            'role_id' => 1,
            'status' => 1,
            'synced_at' => now(),
        ]);

        $projector->handle([
            'event_type' => 'auth.user.updated',
            'payload' => [
                'id' => 15,
                'full_name' => 'Иван Организатор',
                'organizer_profile' => [
                    'company_name' => 'ДКХ',
                ],
                'email' => 'ivan@example.com',
                'phone' => '+79991111111',
                'birth_date' => '2000-01-01',
                'status' => 1,
                'role' => [
                    'id' => 2,
                    'role' => 'organizer',
                ],
            ],
        ]);

        $this->assertDatabaseHas('auth_users', [
            'auth_user_id' => 15,
            'full_name' => 'Иван Организатор',
            'company_name' => 'ДКХ',
            'phone' => '+79991111111',
            'role' => 'organizer',
            'role_id' => 2,
        ]);
    }
}
