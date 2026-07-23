<?php

namespace Tests\Feature;

use App\Enums\BorrowingStatus;
use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Borrowing;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Maintenance;
use App\Models\Room;
use App\Models\Supplier;
use App\Models\Vendor;
use App\Services\DashboardStatsService;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class DashboardPhase5Test extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_summary_includes_supplier_vendor_and_lost_counts(): void
    {
        $this->seedRoles();

        Campus::query()->create([
            'code' => 'D5', 'name' => 'Kampus D5', 'slug' => 'kampus-d5', 'is_active' => true,
        ]);
        Supplier::query()->create(['name' => 'Supplier A', 'is_active' => true]);
        Supplier::query()->create(['name' => 'Supplier Off', 'is_active' => false]);
        Vendor::query()->create(['name' => 'Vendor A', 'is_active' => true]);

        $item = $this->makeItem(['status' => InventoryStatus::Hilang]);

        $summary = app(DashboardStatsService::class)->summary();

        $this->assertSame(1, $summary['total_campuses']);
        $this->assertSame(1, $summary['total_suppliers']);
        $this->assertSame(1, $summary['total_vendors']);
        $this->assertSame(1, $summary['lost']);
        $this->assertSame($item->id, $item->id);
        $this->assertArrayHasKey('borrowings_monthly', $summary['charts']);
        $this->assertArrayHasKey('maintenances_monthly', $summary['charts']);
        $this->assertCount(6, $summary['charts']['borrowings_monthly']);
        $this->assertCount(6, $summary['charts']['maintenances_monthly']);
    }

    public function test_monthly_charts_count_current_month_activity(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem();

        Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Budi',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDays(2)->toDateString(),
            'status' => BorrowingStatus::Dipinjam,
            'approved_by' => $admin->id,
        ]);

        Maintenance::query()->create([
            'inventory_item_id' => $item->id,
            'service_date' => now()->toDateString(),
            'status' => MaintenanceStatus::Scheduled,
        ]);

        $charts = app(DashboardStatsService::class)->chartData();
        $borrowTotal = collect($charts['borrowings_monthly'])->sum('total');
        $maintTotal = collect($charts['maintenances_monthly'])->sum('total');

        $this->assertSame(1, $borrowTotal);
        $this->assertSame(1, $maintTotal);
    }

    public function test_dashboard_page_shows_quick_actions_and_new_stat_labels(): void
    {
        $admin = $this->makeSuperAdmin();
        Supplier::query()->create(['name' => 'PT Sumber', 'is_active' => true]);

        $html = $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Aksi Cepat', $html);
        $this->assertStringContainsString('Jumlah Inventaris', $html);
        $this->assertStringContainsString('Barang Aktif', $html);
        $this->assertStringContainsString('Barang Hilang', $html);
        $this->assertStringContainsString('Jumlah Supplier', $html);
        $this->assertStringContainsString('Jumlah Vendor', $html);
        $this->assertStringContainsString('Grafik Peminjaman', $html);
        $this->assertStringContainsString('Grafik Maintenance', $html);
        $this->assertStringContainsString('chartBorrowing', $html);
        $this->assertStringContainsString('Tambah Inventaris', $html);
    }

    public function test_viewer_quick_actions_exclude_create_links(): void
    {
        $viewer = $this->makeViewer();

        $html = $this->actingAs($viewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Lihat Inventaris', $html);
        $this->assertStringNotContainsString('Tambah Inventaris', $html);
        $this->assertStringNotContainsString('route(\'borrowings.create\')', $html);
        $this->assertStringNotContainsString(route('borrowings.create'), $html);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeItem(array $overrides = []): InventoryItem
    {
        $campus = Campus::query()->first() ?? Campus::query()->create([
            'code' => 'D5B', 'name' => 'Kampus Item', 'slug' => 'kampus-item-d5', 'is_active' => true,
        ]);
        $building = Building::query()->first() ?? Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'A', 'name' => 'Gedung A', 'slug' => 'ga-d5', 'is_active' => true,
        ]);
        $floor = Floor::query()->first() ?? Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->first() ?? Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R1', 'name' => 'Ruang 1', 'room_type' => 'Lab', 'capacity' => 10, 'is_active' => true,
        ]);
        $category = Category::query()->first() ?? Category::query()->create([
            'code' => 'D5C', 'name' => 'Kategori D5', 'slug' => 'kategori-d5', 'is_active' => true,
        ]);

        return InventoryItem::query()->create(array_merge([
            'inventory_number' => 'INV-D5-'.uniqid(),
            'barcode' => 'BC-D5-'.uniqid(),
            'name' => 'Barang Dashboard',
            'category_id' => $category->id,
            'room_id' => $room->id,
            'condition' => InventoryCondition::Bagus,
            'status' => InventoryStatus::Aktif,
            'quantity' => 1,
            'unit' => 'unit',
        ], $overrides));
    }
}
