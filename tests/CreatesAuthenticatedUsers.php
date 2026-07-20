<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

trait CreatesAuthenticatedUsers
{
    use RefreshDatabase;

    protected function seedRoles(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }

    protected function makeSuperAdmin(array $overrides = []): User
    {
        $this->seedRoles();

        $user = User::factory()->create(array_merge([
            'is_active' => true,
        ], $overrides));

        $user->assignRole('super-admin');

        return $user;
    }

    protected function makeViewer(array $overrides = []): User
    {
        $this->seedRoles();

        $user = User::factory()->create(array_merge([
            'is_active' => true,
        ], $overrides));

        if (! Role::query()->where('name', 'viewer')->exists()) {
            $this->seedRoles();
        }

        $user->assignRole('viewer');

        return $user;
    }
}
