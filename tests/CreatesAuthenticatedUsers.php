<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait CreatesAuthenticatedUsers
{
    use RefreshDatabase;

    protected function seedRoles(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }

    protected function makeUserWithRole(string $role, array $overrides = []): User
    {
        $this->seedRoles();

        $user = User::factory()->create(array_merge([
            'is_active' => true,
            'email_verified_at' => now(),
        ], $overrides));

        $user->syncRoles([$role]);

        return $user;
    }

    protected function makeSuperAdmin(array $overrides = []): User
    {
        return $this->makeUserWithRole('super-admin', $overrides);
    }

    protected function makeAdmin(array $overrides = []): User
    {
        return $this->makeUserWithRole('admin', $overrides);
    }

    protected function makeStaff(array $overrides = []): User
    {
        return $this->makeUserWithRole('staff', $overrides);
    }

    protected function makeViewer(array $overrides = []): User
    {
        return $this->makeUserWithRole('viewer', $overrides);
    }
}
