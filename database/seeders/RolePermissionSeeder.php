<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private array $permissions = [
        'inventory.view',
        'inventory.create',
        'inventory.update',
        'inventory.delete',
        'inventory.import',
        'inventory.export',
        'inventory.restore',
        'location.view',
        'location.manage',
        'category.manage',
        'supplier.manage',
        'vendor.manage',
        'user.manage',
        'report.view',
        'report.export',
        'audit-log.view',
        'settings.manage',
        'backup.manage',
        'borrowing.view',
        'borrowing.create',
        'borrowing.update',
        'borrowing.delete',
        'borrowing.manage',
        'maintenance.view',
        'maintenance.create',
        'maintenance.update',
        'maintenance.delete',
        'maintenance.manage',
        'stock-opname.view',
        'stock-opname.create',
        'stock-opname.update',
        'stock-opname.delete',
        'stock-opname.manage',
        'deletion-approval.view',
        'deletion-approval.request',
        'deletion-approval.approve',
        'deletion-approve',
        'mutation.view',
        'mutation.create',
    ];

    /**
     * @var list<string>
     */
    private array $viewerPermissions = [
        'inventory.view',
        'location.view',
        'report.view',
        'report.export',
        'borrowing.view',
        'maintenance.view',
        'stock-opname.view',
        'mutation.view',
        'deletion-approval.view',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $superAdmin = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'web',
        ]);

        $superAdmin->syncPermissions(Permission::all());

        $viewer = Role::firstOrCreate([
            'name' => 'viewer',
            'guard_name' => 'web',
        ]);

        $viewer->syncPermissions($this->viewerPermissions);
    }
}
