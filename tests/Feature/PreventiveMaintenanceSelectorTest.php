<?php

namespace Tests\Feature;

use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Enums\MaintenanceIntervalUnit;
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

class PreventiveMaintenanceSelectorTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_create_page_uses_inventory_selector_and_preview_panel(): void
    {
        $admin = $this->makeAdmin();

        $html = $this->actingAs($admin)
            ->get(route('maintenance-schedules.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-inventory-selector', $html);
        $this->assertStringContainsString('Cari Berdasarkan Lokasi', $html);
        $this->assertStringContainsString('Cari Nomor Inventaris / QR', $html);
        $this->assertStringContainsString('schedulePreviewCard', $html);
        $this->assertStringContainsString('Timeline Maintenance', $html);
        $this->assertStringContainsString('maintenance-schedules/preview', $html);
        $this->assertStringContainsString('maintenance-schedules/suggest-next', $html);
    }

    public function test_preview_ajax_returns_last_maintenance_interval_reminder_pic_timeline(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem([
            'responsible_person' => 'PIC Preventif',
            'inventory_number' => 'CBB-HB-26-EL-LPT-PREV01',
        ]);

        Maintenance::query()->create([
            'maintenance_number' => 'MNT-PREV-0001',
            'inventory_item_id' => $item->id,
            'maintenance_type' => MaintenanceType::Preventive,
            'priority' => 'medium',
            'service_date' => now()->subMonths(2)->toDateString(),
            'status' => MaintenanceStatus::Completed,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->getJson(route('maintenance-schedules.preview', [
                'inventory_item_id' => $item->id,
                'maintenance_type' => 'preventive',
            ]))
            ->assertOk()
            ->assertJsonPath('pic', 'PIC Preventif')
            ->assertJsonPath('last_maintenance.maintenance_number', 'MNT-PREV-0001')
            ->assertJsonStructure([
                'suggested_interval_value',
                'suggested_interval_unit',
                'suggested_remind_before_days',
                'suggested_next_maintenance_date',
                'reminder_starts_at',
                'timeline',
                'duplicate',
            ]);
    }

    public function test_suggest_next_computes_date_from_last_maintenance(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        Maintenance::query()->create([
            'maintenance_number' => 'MNT-PREV-0002',
            'inventory_item_id' => $item->id,
            'maintenance_type' => MaintenanceType::Repair,
            'priority' => 'low',
            'service_date' => '2026-01-15',
            'status' => MaintenanceStatus::Completed,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->getJson(route('maintenance-schedules.suggest-next', [
                'inventory_item_id' => $item->id,
                'interval_value' => 3,
                'interval_unit' => 'months',
                'remind_before_days' => 7,
            ]))
            ->assertOk()
            ->assertJsonPath('next_maintenance_date', '2026-04-15')
            ->assertJsonPath('reminder_starts_at', '2026-04-08');
    }

    public function test_prevents_duplicate_active_schedule_same_type(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        MaintenanceSchedule::query()->create([
            'inventory_item_id' => $item->id,
            'maintenance_type' => MaintenanceType::Preventive,
            'interval_value' => 3,
            'interval_unit' => MaintenanceIntervalUnit::Months,
            'next_maintenance_date' => now()->addMonth()->toDateString(),
            'remind_before_days' => 7,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->from(route('maintenance-schedules.create'))
            ->post(route('maintenance-schedules.store'), [
                'inventory_item_id' => $item->id,
                'maintenance_type' => 'preventive',
                'interval_value' => 6,
                'interval_unit' => 'months',
                'remind_before_days' => 14,
                'is_active' => 1,
            ])
            ->assertRedirect(route('maintenance-schedules.create'))
            ->assertSessionHasErrors('maintenance_type');

        $this->assertSame(1, MaintenanceSchedule::query()->where('inventory_item_id', $item->id)->count());
    }

    public function test_store_auto_generates_next_date_when_omitted(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem(['responsible_person' => 'Auto PIC']);

        $this->actingAs($admin)
            ->post(route('maintenance-schedules.store'), [
                'inventory_item_id' => $item->id,
                'maintenance_type' => 'preventive',
                'interval_value' => 1,
                'interval_unit' => 'months',
                'remind_before_days' => 7,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $schedule = MaintenanceSchedule::query()->where('inventory_item_id', $item->id)->first();
        $this->assertNotNull($schedule);
        $this->assertNotNull($schedule->next_maintenance_date);
        $this->assertSame('Auto PIC', $schedule->responsible_pic);
    }

    public function test_show_displays_timeline(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();
        $schedule = MaintenanceSchedule::query()->create([
            'inventory_item_id' => $item->id,
            'maintenance_type' => MaintenanceType::Preventive,
            'interval_value' => 3,
            'interval_unit' => MaintenanceIntervalUnit::Months,
            'next_maintenance_date' => now()->addMonth()->toDateString(),
            'remind_before_days' => 7,
            'responsible_pic' => 'PIC',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('maintenance-schedules.show', $schedule))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Timeline Maintenance', $html);
        $this->assertStringContainsString('Jadwal berikutnya', $html);
        $this->assertStringContainsString('Mulai pengingat', $html);
    }

    protected function makeItem(array $overrides = []): InventoryItem
    {
        $campus = Campus::query()->create([
            'code' => 'CBB', 'name' => 'Kampus CBB', 'slug' => 'cbb-prev-'.uniqid(), 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'HB', 'name' => 'Gedung HB', 'slug' => 'hb-prev-'.uniqid(), 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R1', 'name' => 'Ruang 1', 'room_type' => 'Lab', 'capacity' => 20, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'EL', 'name' => 'Elektronik', 'slug' => 'el-prev-'.uniqid(), 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'LPT', 'name' => 'Laptop', 'slug' => 'lpt-prev-'.uniqid(), 'is_active' => true,
        ]);

        return InventoryItem::query()->create(array_merge([
            'inventory_number' => 'INV-PREV-'.uniqid(),
            'barcode' => 'BC-'.uniqid(),
            'name' => 'AC Lab',
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
