<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Canonical permission catalog (used by routes / policies / menus).
     *
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
        'maintenance.view',
        'maintenance.create',
        'maintenance.update',
        'maintenance.delete',
        'stock-opname.view',
        'stock-opname.create',
        'stock-opname.update',
        'stock-opname.delete',
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
     * Viewer — read-only.
     *
     * @var list<string>
     */
    private array $viewerPermissions = [
        'inventory.view',
        'location.view',
        'report.view',
        'borrowing.view',
        'maintenance.view',
        'stock-opname.view',
        'mutation.view',
        'deletion-approval.view',
    ];

    /**
     * Staff — operasional harian (tanpa master data / admin sistem / hapus permanen).
     *
     * @var list<string>
     */
    private array $staffPermissions = [
        'inventory.view',
        'inventory.create',
        'inventory.update',
        'inventory.export',
        'location.view',
        'report.view',
        'report.export',
        'borrowing.view',
        'borrowing.create',
        'borrowing.update',
        'maintenance.view',
        'maintenance.create',
        'maintenance.update',
        'stock-opname.view',
        'stock-opname.create',
        'stock-opname.update',
        'mutation.view',
        'mutation.create',
        'deletion-approval.view',
        'deletion-approval.request',
    ];

    /**
     * Admin — CRUD operasional + master data, tanpa trash / user / settings / backup.
     *
     * @var list<string>
     */
    private array $adminExcludedPermissions = [
        'trash.view',
        'trash.restore',
        'trash.force-delete',
        'user.manage',
        'settings.manage',
        'backup.manage',
    ];

    /**
     * Legacy permission names removed from the catalog.
     *
     * @var list<string>
     */
    private array $orphanPermissions = [
        'deletion-approve',
        'borrowing.manage',
        'maintenance.manage',
        'stock-opname.manage',
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

        Permission::query()->whereIn('name', $this->orphanPermissions)->delete();

        $superAdmin = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'web',
        ]);
        $superAdmin->syncPermissions(
            Permission::query()->where('guard_name', 'web')->pluck('name')->all()
        );

        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);
        $admin->syncPermissions(
            Permission::query()
                ->where('guard_name', 'web')
                ->whereNotIn('name', $this->adminExcludedPermissions)
                ->pluck('name')
                ->all()
        );

        $staff = Role::firstOrCreate([
            'name' => 'staff',
            'guard_name' => 'web',
        ]);
        $staff->syncPermissions($this->staffPermissions);

        $viewer = Role::firstOrCreate([
            'name' => 'viewer',
            'guard_name' => 'web',
        ]);
        $viewer->syncPermissions($this->viewerPermissions);
    }
}
