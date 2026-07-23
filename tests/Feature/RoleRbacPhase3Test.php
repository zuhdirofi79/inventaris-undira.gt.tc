<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\Campus;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryMutation;
use App\Models\Maintenance;
use App\Models\StockOpname;
use App\Support\RoleLabels;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class RoleRbacPhase3Test extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_seeder_creates_four_canonical_roles(): void
    {
        $this->seedRoles();

        $this->assertTrue(Role::query()->where('name', 'super-admin')->exists());
        $this->assertTrue(Role::query()->where('name', 'admin')->exists());
        $this->assertTrue(Role::query()->where('name', 'staff')->exists());
        $this->assertTrue(Role::query()->where('name', 'viewer')->exists());

        $this->assertFalse(Permission::query()->where('name', 'borrowing.manage')->exists());
        $this->assertFalse(Permission::query()->where('name', 'maintenance.manage')->exists());
        $this->assertFalse(Permission::query()->where('name', 'stock-opname.manage')->exists());
    }

    public function test_staff_has_operational_permissions_but_not_admin_system(): void
    {
        $staff = $this->makeStaff();

        $this->assertTrue($staff->can('inventory.view'));
        $this->assertTrue($staff->can('inventory.create'));
        $this->assertTrue($staff->can('inventory.update'));
        $this->assertTrue($staff->can('mutation.create'));
        $this->assertTrue($staff->can('borrowing.create'));
        $this->assertTrue($staff->can('stock-opname.update'));
        $this->assertTrue($staff->can('report.export'));

        $this->assertFalse($staff->can('inventory.delete'));
        $this->assertFalse($staff->can('category.manage'));
        $this->assertFalse($staff->can('location.manage'));
        $this->assertFalse($staff->can('user.manage'));
        $this->assertFalse($staff->can('settings.manage'));
        $this->assertFalse($staff->can('trash.view'));
        $this->assertFalse($staff->can('audit-log.view'));
        $this->assertFalse($staff->can('deletion-approval.approve'));
    }

    public function test_admin_cannot_access_trash_or_user_manage(): void
    {
        $admin = $this->makeAdmin();

        $this->assertTrue($admin->can('category.manage'));
        $this->assertTrue($admin->can('inventory.delete'));
        $this->assertFalse($admin->can('trash.view'));
        $this->assertFalse($admin->can('user.manage'));
        $this->assertFalse($admin->can('settings.manage'));

        $this->actingAs($admin)
            ->get(route('trash.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_viewer_is_read_only_and_cannot_export_reports(): void
    {
        $viewer = $this->makeViewer();

        $this->assertTrue($viewer->can('inventory.view'));
        $this->assertTrue($viewer->can('report.view'));
        $this->assertFalse($viewer->can('report.export'));
        $this->assertFalse($viewer->can('inventory.create'));
        $this->assertFalse($viewer->can('mutation.create'));

        $this->actingAs($viewer)
            ->get(route('inventory.index'))
            ->assertOk();

        $this->actingAs($viewer)
            ->get(route('mutations.create'))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('reports.generate'), ['format' => 'pdf'])
            ->assertForbidden();
    }

    public function test_staff_can_open_operational_pages_but_not_master_manage(): void
    {
        $staff = $this->makeStaff();

        $this->actingAs($staff)->get(route('borrowings.index'))->assertOk();
        $this->actingAs($staff)->get(route('maintenances.index'))->assertOk();
        $this->actingAs($staff)->get(route('mutations.index'))->assertOk();
        $this->actingAs($staff)->get(route('stock-opnames.index'))->assertOk();
        $this->actingAs($staff)->get(route('mutations.create'))->assertOk();

        $this->actingAs($staff)->get(route('admin.locations.campuses.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('suppliers.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('trash.index'))->assertForbidden();
    }

    public function test_super_admin_can_access_trash_via_permission(): void
    {
        $super = $this->makeSuperAdmin();

        $this->assertTrue($super->can('trash.view'));
        $this->assertTrue($super->can('trash.restore'));
        $this->assertTrue($super->can('trash.force-delete'));

        $this->actingAs($super)
            ->get(route('trash.index'))
            ->assertOk();
    }

    public function test_policies_map_to_spatie_permissions(): void
    {
        $staff = $this->makeStaff();
        $viewer = $this->makeViewer();

        $this->assertTrue($staff->can('create', Borrowing::class));
        $this->assertTrue($staff->can('create', Maintenance::class));
        $this->assertTrue($staff->can('create', InventoryMutation::class));
        $this->assertTrue($staff->can('create', StockOpname::class));
        $this->assertTrue($staff->can('create', InventoryItem::class));
        $this->assertFalse($staff->can('create', Category::class));
        $this->assertFalse($staff->can('create', Campus::class));

        $this->assertTrue($viewer->can('viewAny', InventoryItem::class));
        $this->assertFalse($viewer->can('create', InventoryItem::class));
        $this->assertFalse($viewer->can('create', Borrowing::class));
    }

    public function test_role_labels_are_human_readable(): void
    {
        $this->assertSame('Super Admin', RoleLabels::label('super-admin'));
        $this->assertSame('Admin', RoleLabels::label('admin'));
        $this->assertSame('Staff', RoleLabels::label('staff'));
        $this->assertSame('Viewer', RoleLabels::label('viewer'));
    }
}
