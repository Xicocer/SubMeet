<?php

namespace Tests\Feature;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_user_registration_creates_regular_account(): void
    {
        $response = $this->postJson('/api/register', [
            'full_name' => 'Иван Петров',
            'email' => 'ivan@example.com',
            'phone' => '+79990001122',
            'birth_date' => '2000-01-01',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.role.role', 'user')
            ->assertJsonPath('user.organizer_profile', null);

        $this->assertDatabaseHas('users', [
            'email' => 'ivan@example.com',
        ]);
    }

    public function test_organizer_registration_creates_company_account(): void
    {
        $response = $this->postJson('/api/organizers/register', [
            'company_name' => 'Milo Concert Hall',
            'full_name' => 'Мария Петрова',
            'email' => 'organizer@example.com',
            'phone' => '+79990002233',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.role.role', 'organizer')
            ->assertJsonPath('user.organizer_profile.company_name', 'Milo Concert Hall');

        $this->assertDatabaseHas('organizer_profiles', [
            'company_name' => 'Milo Concert Hall',
        ]);
    }

    public function test_user_registration_normalizes_phone_from_masked_8_prefix(): void
    {
        $response = $this->postJson('/api/register', [
            'full_name' => 'Иван Петров',
            'email' => 'masked@example.com',
            'phone' => '8 (999) 123-45-67',
            'birth_date' => '2000-01-01',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.phone', '+79991234567');

        $this->assertDatabaseHas('users', [
            'email' => 'masked@example.com',
            'phone' => '+79991234567',
        ]);
    }
}
