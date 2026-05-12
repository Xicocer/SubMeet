<?php

namespace Tests\Feature;

use App\Models\OrganizerProfile;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrganizerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_list_organizers_with_moderation_status(): void
    {
        $admin = $this->createAdmin();
        $organizer = $this->createOrganizer('pending');

        $this->actingAs($admin)
            ->getJson('/api/admin/organizers')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.user_id', $organizer->id)
            ->assertJsonPath('data.0.moderation_status', 'pending');
    }

    public function test_admin_can_approve_organizer(): void
    {
        $admin = $this->createAdmin();
        $organizer = $this->createOrganizer('pending');

        $this->actingAs($admin)
            ->patchJson("/api/admin/organizers/{$organizer->id}/moderation", [
                'status' => 'approved',
                'note' => 'Documents look good.',
            ])
            ->assertOk()
            ->assertJsonPath('organizer.moderation_status', 'approved');

        $this->assertDatabaseHas('organizer_profiles', [
            'user_id' => $organizer->id,
            'moderation_status' => 'approved',
        ]);
    }

    public function test_admin_can_search_organizers(): void
    {
        $admin = $this->createAdmin();
        $matchingOrganizer = $this->createOrganizer('pending', 'Milo Concert Hall', 'manager@milo.test', '+79990000002');
        $this->createOrganizer('approved', 'DKH', 'manager@dkh.test', '+79990000003');

        $this->actingAs($admin)
            ->getJson('/api/admin/organizers?search=Milo')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.user_id', $matchingOrganizer->id);
    }

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $userRoleId = Role::query()->where('role', 'user')->value('id');
        $user = User::query()->create([
            'full_name' => 'Regular User',
            'email' => 'user@example.com',
            'phone' => '+79990000001',
            'birth_date' => '2000-01-01',
            'password' => 'password123',
            'role_id' => $userRoleId,
            'status' => 1,
        ]);

        $this->actingAs($user)
            ->getJson('/api/admin/organizers')
            ->assertForbidden();
    }

    private function createAdmin(): User
    {
        $adminRoleId = Role::query()->where('role', 'admin')->value('id');

        return User::query()->create([
            'full_name' => 'Platform Admin',
            'email' => 'admin@example.com',
            'phone' => '+79990000000',
            'birth_date' => '1995-01-01',
            'password' => 'password123',
            'role_id' => $adminRoleId,
            'status' => 1,
        ]);
    }

    private function createOrganizer(
        string $moderationStatus,
        string $companyName = 'DKH',
        string $email = 'organizer@example.com',
        string $phone = '+79990000002',
    ): User
    {
        $organizerRoleId = Role::query()->where('role', 'organizer')->value('id');

        $user = User::query()->create([
            'full_name' => 'Organizer User',
            'email' => $email,
            'phone' => $phone,
            'birth_date' => null,
            'password' => 'password123',
            'role_id' => $organizerRoleId,
            'status' => 1,
        ]);

        OrganizerProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => $companyName,
            'moderation_status' => $moderationStatus,
        ]);

        return $user;
    }
}
