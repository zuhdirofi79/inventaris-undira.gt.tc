<?php

namespace Tests\Feature;

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
use App\Models\Vendor;
use App\Services\MaintenanceDashboardStatsService;
use App\Services\MaintenanceReportService;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class MaintenancePhaseCDashboardReportTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_dashboard_page_renders_cards_and_charts(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem();
        $this->makeWo($item, ['status' => MaintenanceStatus::Waiting, 'cost' => 100000]);

        $this->actingAs($admin)
            ->get(route('maintenance-dashboard.index'))
            ->assertOk()
            ->assertSee('Dashboard Pemeliharaan')
            ->assertSee('Total WO')
            ->assertSee('Tren Maintenance Bulanan')
            ->assertSee('Jadwal Overdue');
    }

    public function test_dashboard_stats_include_waiting_and_cost(): void
    {
        $item = $this->makeItem();
        $this->makeWo($item, [
            'status' => MaintenanceStatus::Waiting,
            'cost' => 250000,
            'service_date' => now()->toDateString(),
        ]);
        $this->makeWo($item, [
            'status' => MaintenanceStatus::InProgress,
            'technician' => 'Budi',
            'started_at' => now()->subHours(4),
        ]);

        $summary = app(MaintenanceDashboardStatsService::class)->summary();

        $this->assertSame(1, $summary['cards']['waiting']);
        $this->assertSame(1, $summary['cards']['on_progress']);
        $this->assertGreaterThanOrEqual(250000, $summary['cards']['cost_this_month']);
        $this->assertArrayHasKey('monthly_trend', $summary['charts']);
        $this->assertArrayHasKey('top_assets', $summary['charts']);
    }

    public function test_report_history_view_and_excel_export(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem();
        $this->makeWo($item, [
            'maintenance_number' => 'MNT-RPT-0001',
            'technician' => 'Andi Teknisi',
            'cost' => 50000,
        ]);

        $this->actingAs($admin)
            ->post(route('maintenance-reports.generate'), [
                'type' => 'history',
                'format' => 'view',
                'date_from' => now()->subMonth()->toDateString(),
                'date_to' => now()->toDateString(),
            ])
            ->assertOk()
            ->assertSee('Riwayat Pemeliharaan')
            ->assertSee('MNT-RPT-0001')
            ->assertSee('Andi Teknisi');

        $this->actingAs($admin)
            ->post(route('maintenance-reports.generate'), [
                'type' => 'cost',
                'format' => 'excel',
                'date_from' => now()->subMonth()->toDateString(),
                'date_to' => now()->toDateString(),
            ])
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_report_service_builds_all_types(): void
    {
        $item = $this->makeItem(['status' => InventoryStatus::PerluPenggantian]);
        $vendor = Vendor::query()->create(['name' => 'CV Servis', 'is_active' => true]);
        $this->makeWo($item, [
            'vendor_id' => $vendor->id,
            'technician' => 'Citra',
            'status' => MaintenanceStatus::Completed,
            'cost' => 75000,
            'completed_at' => now(),
        ]);
        MaintenanceSchedule::query()->create([
            'inventory_item_id' => $item->id,
            'maintenance_type' => MaintenanceType::Preventive,
            'interval_value' => 6,
            'interval_unit' => 'months',
            'next_maintenance_date' => now()->addDays(10)->toDateString(),
            'remind_before_days' => 7,
            'is_active' => true,
        ]);

        $service = app(MaintenanceReportService::class);
        foreach (array_keys(MaintenanceReportService::TYPES) as $type) {
            $report = $service->build(['type' => $type]);
            $this->assertSame($type, $report['type']);
            $this->assertNotEmpty($report['headings']);
            $this->assertIsArray($report['rows']);
        }
    }

    public function test_reports_index_lists_types(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAs($admin)
            ->get(route('maintenance-reports.index'))
            ->assertOk()
            ->assertSee('Performa Vendor')
            ->assertSee('Pemeliharaan Preventif');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeWo(InventoryItem $item, array $overrides = []): Maintenance
    {
        return Maintenance::query()->create(array_merge([
            'maintenance_number' => 'MNT-C-'.uniqid(),
            'inventory_item_id' => $item->id,
            'maintenance_type' => MaintenanceType::Repair,
            'priority' => 'medium',
            'service_date' => now()->toDateString(),
            'status' => MaintenanceStatus::Waiting,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeItem(array $overrides = []): InventoryItem
    {
        $campus = Campus::query()->create([
            'code' => 'CBB', 'name' => 'Cibubur', 'slug' => 'cibubur-c'.uniqid(), 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'A', 'name' => 'Gedung A', 'slug' => 'a-c'.uniqid(), 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R1', 'name' => 'Ruang 1', 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'EL', 'name' => 'Elektronik', 'slug' => 'el-c'.uniqid(), 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'PC', 'name' => 'PC', 'slug' => 'pc-c'.uniqid(), 'is_active' => true,
        ]);

        return InventoryItem::query()->create(array_merge([
            'inventory_number' => 'INV-C-'.uniqid(),
            'name' => 'PC Lab',
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'room_id' => $room->id,
            'condition' => 'bagus',
            'status' => InventoryStatus::Aktif,
            'quantity' => 1,
            'unit' => 'Unit',
        ], $overrides));
    }
}
