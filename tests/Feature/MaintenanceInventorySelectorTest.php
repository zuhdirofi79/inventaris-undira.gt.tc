<?php

namespace Tests\Feature;

use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Maintenance;
use App\Models\MaintenanceSchedule;
use App\Models\Room;
use App\Models\SubCategory;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class MaintenanceInventorySelectorTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_work_order_create_uses_inventory_selector_and_context(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem([
            'inventory_number' => 'CBB-HB-26-EL-LPT-000111',
            'responsible_person' => 'PIC Lab',
            'warranty_info' => '1 tahun',
            'condition' => InventoryCondition::Bagus,
        ]);

        Maintenance::query()->create([
            'maintenance_number' => 'MNT-TEST-0001',
            'inventory_item_id' => $item->id,
            'maintenance_type' => MaintenanceType::Repair,
            'priority' => 'medium',
            'service_date' => now()->toDateString(),
            'status' => MaintenanceStatus::Completed,
            'created_by' => $admin->id,
        ]);

        MaintenanceSchedule::query()->create([
            'inventory_item_id' => $item->id,
            'maintenance_type' => MaintenanceType::Preventive,
            'interval_value' => 3,
            'interval_unit' => 'months',
            'next_maintenance_date' => now()->addMonth()->toDateString(),
            'remind_before_days' => 7,
            'responsible_pic' => 'PIC Lab',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('maintenances.create', ['inventory_item_id' => $item->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-inventory-selector', $html);
        $this->assertStringContainsString('data-resolve-maintenance="1"', $html);
        $this->assertStringContainsString('data-inventory-maintenance-context', $html);
        $this->assertStringContainsString('Ringkasan Inventaris', $html);
        $this->assertStringContainsString('Riwayat Maintenance', $html);
        $this->assertStringContainsString('Jadwal Pemeliharaan', $html);
        $this->assertStringContainsString('Work Order Terbaru', $html);
        $this->assertStringContainsString('CBB-HB-26-EL-LPT-000111', $html);
        $this->assertStringContainsString('inventory-maintenance-context.js', $html);
    }

    public function test_schedule_create_uses_selector_with_qr(): void
    {
        $admin = $this->makeAdmin();

        $html = $this->actingAs($admin)
            ->get(route('maintenance-schedules.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-inventory-selector', $html);
        $this->assertStringContainsString('data-show-qr="1"', $html);
        $this->assertStringContainsString('data-inventory-maintenance-context', $html);
    }

    public function test_maintenance_context_endpoint_returns_history_and_schedule(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem(['serial_number' => 'SN-MAINT-99']);

        Maintenance::query()->create([
            'maintenance_number' => 'MNT-CTX-0001',
            'inventory_item_id' => $item->id,
            'maintenance_type' => MaintenanceType::Repair,
            'priority' => 'high',
            'service_date' => now()->toDateString(),
            'status' => MaintenanceStatus::Waiting,
            'technician' => 'Teknisi X',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->getJson(route('inventory.selector.maintenance-context', $item))
            ->assertOk()
            ->assertJsonPath('id', $item->id)
            ->assertJsonPath('latest_work_order.maintenance_number', 'MNT-CTX-0001')
            ->assertJsonPath('condition_label', InventoryCondition::Bagus->label())
            ->assertJsonStructure([
                'pic',
                'warranty_label',
                'maintenance_history',
                'maintenance_schedules',
                'latest_work_order',
            ]);
    }

    public function test_resolve_code_with_maintenance_flag_includes_context(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem([
            'inventory_number' => 'CBB-HB-26-EL-LPT-000222',
            'barcode' => 'BC-MAINT-222',
            'serial_number' => 'SN-MAINT-222',
        ]);

        $this->actingAs($admin)
            ->postJson(route('inventory.selector.resolve-code'), [
                'code' => 'SN-MAINT-222',
                'maintenance' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('item.inventory_number', 'CBB-HB-26-EL-LPT-000222')
            ->assertJsonStructure(['item' => ['maintenance_history', 'maintenance_schedules', 'warranty_label']]);
    }

    public function test_scan_lookup_uses_search_service_and_serial(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem([
            'inventory_number' => 'CBB-HB-26-EL-LPT-000333',
            'serial_number' => 'SN-SCAN-333',
        ]);

        $this->actingAs($admin)
            ->postJson(route('maintenances.scan.lookup'), ['code' => 'SN-SCAN-333'])
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('item.inventory_number', $item->inventory_number)
            ->assertJsonStructure(['item' => ['maintenance_history', 'latest_work_order', 'condition_label']]);
    }

    protected function makeItem(array $overrides = []): InventoryItem
    {
        $campus = Campus::query()->create([
            'code' => 'CBB', 'name' => 'Kampus CBB', 'slug' => 'cbb-maint-sel-'.uniqid(), 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'HB', 'name' => 'Gedung HB', 'slug' => 'hb-maint-sel-'.uniqid(), 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R1', 'name' => 'Ruang 1', 'room_type' => 'Lab', 'capacity' => 20, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'EL', 'name' => 'Elektronik', 'slug' => 'el-maint-sel-'.uniqid(), 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'LPT', 'name' => 'Laptop', 'slug' => 'lpt-maint-sel-'.uniqid(), 'is_active' => true,
        ]);

        return InventoryItem::query()->create(array_merge([
            'inventory_number' => 'INV-MAINT-'.uniqid(),
            'barcode' => 'BC-'.uniqid(),
            'name' => 'Laptop Lab',
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'room_id' => $room->id,
            'condition' => InventoryCondition::Bagus,
            'status' => InventoryStatus::Aktif,
            'quantity' => 1,
            'unit' => 'unit',
            'responsible_person' => 'PIC',
        ], $overrides));
    }
}
