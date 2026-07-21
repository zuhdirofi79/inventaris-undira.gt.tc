<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\SubCategory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class TrashCascadeTest extends TestCase
{
    use CreatesAuthenticatedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_soft_delete_building_cascades_to_inventory(): void
    {
        $admin = $this->makeSuperAdmin();
        [$building, $room, $item] = $this->seedBuildingTree();

        $this->actingAs($admin)
            ->delete(route('admin.locations.buildings.destroy', $building))
            ->assertRedirect();

        $this->assertSoftDeleted($building);
        $this->assertSoftDeleted($room);
        $this->assertSoftDeleted($item);
        $this->assertSame(0, InventoryItem::query()->count());
        $this->assertSame(1, InventoryItem::onlyTrashed()->count());
    }

    public function test_restore_building_restores_descendants(): void
    {
        $admin = $this->makeSuperAdmin();
        [$building, $room, $item] = $this->seedBuildingTree();

        $this->actingAs($admin)->delete(route('admin.locations.buildings.destroy', $building));

        $this->actingAs($admin)
            ->post(route('trash.restore', ['gedung', $building->id]))
            ->assertRedirect();

        $this->assertNull($building->fresh()->deleted_at);
        $this->assertNull($room->fresh()->deleted_at);
        $this->assertNull($item->fresh()->deleted_at);
        $this->assertSame(1, InventoryItem::query()->count());
    }

    public function test_force_delete_room_removes_inventory_permanently(): void
    {
        $admin = $this->makeSuperAdmin();
        [, $room, $item] = $this->seedBuildingTree();

        $this->actingAs($admin)->delete(route('admin.locations.rooms.destroy', $room));

        $this->actingAs($admin)
            ->delete(route('trash.force-delete', ['ruangan', $room->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
        $this->assertDatabaseMissing('inventory_items', ['id' => $item->id]);
    }

    public function test_soft_delete_building_cascades_configs_and_inventory(): void
    {
        $admin = $this->makeSuperAdmin();
        [$building, $room, $item] = $this->seedBuildingTree();

        \App\Models\InventoryMasterConfiguration::query()->create([
            'campus_id' => $building->campus_id,
            'building_id' => $building->id,
            'floor_id' => $room->floor_id,
            'org_unit_id' => \App\Models\OrgUnit::query()->create(['code' => 'U1', 'name' => 'Unit 1', 'is_active' => true])->id,
            'room_id' => $room->id,
            'procurement_type_id' => \App\Models\ProcurementType::query()->create(['code' => 'P1', 'name' => 'Proc', 'is_active' => true])->id,
            'purchase_year_id' => \App\Models\PurchaseYear::query()->create(['year' => 2024, 'is_active' => true])->id,
            'category_id' => $item->category_id,
            'sub_category_id' => $item->sub_category_id,
            'inventory_condition_id' => \App\Models\InventoryConditionMaster::query()->create(['code' => 'BAIK', 'name' => 'Baik', 'is_active' => true])->id,
            'generated_location_code' => 'TEST-CFG',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.locations.buildings.destroy', $building))
            ->assertRedirect();

        $this->assertSoftDeleted($building);
        $this->assertSoftDeleted($room);
        $this->assertSoftDeleted($item);
        $this->assertSame(1, \App\Models\InventoryMasterConfiguration::onlyTrashed()->count());
    }

    public function test_viewer_cannot_open_trash(): void
    {
        $viewer = $this->makeViewer();

        $this->actingAs($viewer)
            ->get(route('trash.index'))
            ->assertForbidden();
    }

    /**
     * @return array{0: Building, 1: Room, 2: InventoryItem}
     */
    protected function seedBuildingTree(): array
    {
        $campus = Campus::query()->create([
            'code' => 'CBB', 'name' => 'Cibubur', 'slug' => 'cbb-trash', 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'A', 'name' => 'Gedung A', 'slug' => 'ga-trash', 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'A101', 'name' => 'Ruang A101', 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'FUR', 'name' => 'Furniture', 'slug' => 'fur-trash', 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'MJA', 'name' => 'Meja', 'slug' => 'mja-trash', 'is_active' => true,
        ]);
        $item = InventoryItem::query()->create([
            'name' => 'Meja Trash',
            'inventory_number' => 'TRASH-001',
            'barcode' => 'TRASH-001',
            'room_id' => $room->id,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'condition' => 'bagus',
            'status' => 'aktif',
            'quantity' => 1,
        ]);

        return [$building, $room, $item];
    }
}
