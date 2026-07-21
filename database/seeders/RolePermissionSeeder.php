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
        'unit.manage',
        'procurement.manage',
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
        'mutation.view',
        'mutation.create',
        'trash.view',
        'trash.restore',
        'trash.force-delete',
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

        // Remove legacy orphan permission name if present
        Permission::query()->where('name', 'deletion-approve')->delete();

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

        // Admin operasional: CRUD tanpa Trash / force delete
        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $adminPermissions = Permission::query()
            ->whereNotIn('name', [
                'trash.view',
                'trash.restore',
                'trash.force-delete',
                'user.manage',
                'settings.manage',
                'backup.manage',
            ])
            ->pluck('name')
            ->all();

        $admin->syncPermissions($adminPermissions);
    }
}
