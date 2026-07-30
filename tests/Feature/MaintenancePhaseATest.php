<?php

namespace Tests\Feature;

use App\Enums\InventoryStatus;
use App\Enums\MaintenanceResult;
use App\Enums\MaintenanceStatus;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Maintenance;
use App\Models\Room;
use App\Models\Setting;
use App\Models\SubCategory;
use App\Services\PrimarySuperAdminService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class MaintenancePhaseATest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_scan_page_and_lookup_returns_asset_card(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem(['inventory_number' => 'CBB-HIBAH-26-EL-LPT-000777']);

        $this->actingAs($admin)->get(route('maintenances.scan'))->assertOk()->assertSee('Scan Inventory QR Code');

        $this->actingAs($admin)
            ->postJson(route('maintenances.scan.lookup'), ['code' => 'CBB-HIBAH-26-EL-LPT-000777'])
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('item.inventory_number', 'CBB-HIBAH-26-EL-LPT-000777')
            ->assertJsonPath('item.name', $item->name);
    }

    public function test_store_sets_inventory_under_maintenance_and_blocks_borrow_status(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem();

        $this->actingAs($admin)
            ->post(route('maintenances.store'), [
                'inventory_item_id' => $item->id,
                'maintenance_type' => 'repair',
                'priority' => 'high',
                'technician' => 'Teknisi A',
                'service_date' => now()->toDateString(),
                'description' => 'Perbaikan LCD',
                'status' => 'waiting',
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame(InventoryStatus::Maintenance, $item->status);
        $this->assertFalse($item->status->isBorrowable());

        $wo = Maintenance::query()->where('inventory_item_id', $item->id)->first();
        $this->assertNotNull($wo->maintenance_number);
        $this->assertSame(MaintenanceStatus::Waiting, $wo->status);
        $this->assertSame('Teknisi A', $wo->technician);
    }

    public function test_complete_repaired_returns_aktif(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem();
        $wo = $this->createOpenWo($item);

        $this->actingAs($admin)
            ->put(route('maintenances.update', $wo), [
                'maintenance_type' => 'repair',
                'priority' => 'medium',
                'service_date' => $wo->service_date->format('Y-m-d'),
                'status' => 'completed',
                'result' => 'repaired',
                'cost' => 150000,
            ])
            ->assertRedirect();

        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);
        $this->assertSame(MaintenanceResult::Repaired, $wo->fresh()->result);
    }

    public function test_complete_cannot_repair_sets_new_status(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem();
        $wo = $this->createOpenWo($item);

        $this->actingAs($admin)
            ->put(route('maintenances.update', $wo), [
                'maintenance_type' => 'repair',
                'priority' => 'critical',
                'service_date' => $wo->service_date->format('Y-m-d'),
                'status' => 'completed',
                'result' => 'cannot_repair',
            ])
            ->assertRedirect();

        $this->assertSame(InventoryStatus::TidakDapatDiperbaiki, $item->fresh()->status);
    }

    public function test_complete_need_replacement_sets_new_status(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem();
        $wo = $this->createOpenWo($item);

        $this->actingAs($admin)
            ->put(route('maintenances.update', $wo), [
                'maintenance_type' => 'replacement',
                'priority' => 'high',
                'service_date' => $wo->service_date->format('Y-m-d'),
                'status' => 'completed',
                'result' => 'need_replacement',
            ])
            ->assertRedirect();

        $this->assertSame(InventoryStatus::PerluPenggantian, $item->fresh()->status);
    }

    public function test_photo_before_upload_on_create(): void
    {
        Storage::fake('public');
        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem();

        $this->actingAs($admin)
            ->post(route('maintenances.store'), [
                'inventory_item_id' => $item->id,
                'maintenance_type' => 'repair',
                'priority' => 'low',
                'service_date' => now()->toDateString(),
                'status' => 'waiting',
                'photos_before' => [UploadedFile::fake()->image('before.jpg')],
            ])
            ->assertRedirect();

        $wo = Maintenance::query()->where('inventory_item_id', $item->id)->first();
        $this->assertSame(1, $wo->photos()->where('type', 'before')->count());
        Storage::disk('public')->assertExists($wo->photos()->first()->path);
        $this->assertNotNull($wo->photos()->first()->url);
    }

    public function test_detail_page_can_add_and_delete_photos(): void
    {
        Storage::fake('public');
        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem();
        $wo = $this->createOpenWo($item);

        $this->actingAs($admin)
            ->post(route('maintenances.photos.store', $wo), [
                'type' => 'before',
                'photos' => [
                    UploadedFile::fake()->image('a.jpg'),
                    UploadedFile::fake()->image('b.png'),
                ],
            ])
            ->assertRedirect();

        $wo->refresh();
        $this->assertSame(2, $wo->photos()->where('type', 'before')->count());
        $photo = $wo->photos()->where('type', 'before')->first();
        Storage::disk('public')->assertExists($photo->path);

        $this->actingAs($admin)
            ->delete(route('maintenances.photos.destroy', [$wo, $photo]))
            ->assertRedirect();

        Storage::disk('public')->assertMissing($photo->path);
        $this->assertSame(1, $wo->photos()->where('type', 'before')->count());
    }

    public function test_damaged_list_and_delete_only_primary_super_admin(): void
    {
        $primary = $this->makeSuperAdmin();
        Setting::set(
            PrimarySuperAdminService::SETTING_KEY,
            (string) $primary->id,
            PrimarySuperAdminService::SETTING_GROUP,
        );
        $staff = $this->makeStaff();
        $item = $this->makeItem(['status' => InventoryStatus::PerluPenggantian]);

        $this->actingAs($staff)->get(route('inventory.damaged.index'))->assertOk()->assertSee($item->inventory_number);
        $this->actingAs($staff)->delete(route('inventory.damaged.destroy', $item))->assertForbidden();

        $this->actingAs($primary)->delete(route('inventory.damaged.destroy', $item))->assertRedirect();
        $this->assertSoftDeleted($item);
    }

    public function test_index_shows_large_scan_button(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAs($admin)
            ->get(route('maintenances.index'))
            ->assertOk()
            ->assertSee('Scan Inventory QR Code')
            ->assertSee(route('maintenances.scan', absolute: false), false);
    }

    protected function createOpenWo(InventoryItem $item): Maintenance
    {
        return Maintenance::query()->create([
            'maintenance_number' => 'MNT-TEST-'.uniqid(),
            'inventory_item_id' => $item->id,
            'maintenance_type' => 'repair',
            'priority' => 'medium',
            'service_date' => now()->toDateString(),
            'status' => MaintenanceStatus::Waiting,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeItem(array $overrides = []): InventoryItem
    {
        $campus = Campus::query()->create([
            'code' => 'CBB', 'name' => 'Cibubur', 'slug' => 'cibubur-m'.uniqid(), 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'A', 'name' => 'Gedung A', 'slug' => 'a-m'.uniqid(), 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R1', 'name' => 'Ruang 1', 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'EL', 'name' => 'Elektronik', 'slug' => 'el-m'.uniqid(), 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'LPT', 'name' => 'Laptop', 'slug' => 'lpt-m'.uniqid(), 'is_active' => true,
        ]);

        return InventoryItem::query()->create(array_merge([
            'inventory_number' => 'INV-MNT-'.uniqid(),
            'name' => 'Laptop Maintenance',
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'room_id' => $room->id,
            'condition' => 'bagus',
            'status' => InventoryStatus::Aktif,
            'quantity' => 1,
            'unit' => 'Unit',
            'responsible_person' => 'PIC Lab',
            'purchase_year' => 2026,
        ], $overrides));
    }
}
