<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['user', 'organizer', 'venue_owner', 'admin'] as $roleName) {
            Role::query()->updateOrCreate(
                ['role' => $roleName],
                ['updated_at' => now()]
            );
        }
    }
}
