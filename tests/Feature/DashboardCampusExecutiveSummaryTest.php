<?php

namespace Tests\Feature;

use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\SubCategory;
use App\Services\DashboardCampusExecutiveService;
use App\Support\InventarisCache;
use Illuminate\Support\Facades\Cache;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class DashboardCampusExecutiveSummaryTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_summary_aggregates_status_per_campus_category_subcategory(): void
    {
        [$campus, $room, $furniture, $kursi] = $this->seedTree('CBB', 'UNDIRA CIBUBUR');

        $this->makeItem($room, $furniture, $kursi, InventoryStatus::Aktif);
        $this->makeItem($room, $furniture, $kursi, InventoryStatus::Aktif);
        $this->makeItem($room, $furniture, $kursi, InventoryStatus::Dipinjam);
        $this->makeItem($room, $furniture, $kursi, InventoryStatus::Maintenance);
        $this->makeItem($room, $furniture, $kursi, InventoryStatus::RusakRingan);

        Cache::forget(DashboardCampusExecutiveService::CACHE_KEY);

        $summary = app(DashboardCampusExecutiveService::class)->summary();
        $cibubur = collect($summary['campuses'])->firstWhere('campus_code', 'CBB');

        $this->assertNotNull($cibubur);
        $this->assertSame(5, $cibubur['total']);
        $this->assertSame(2, $cibubur['aktif']);
        $this->assertSame(1, $cibubur['dipinjam']);
        $this->assertSame(1, $cibubur['maintenance']);
        $this->assertSame(1, $cibubur['rusak']);

        $cat = $cibubur['categories'][0];
        $this->assertSame('Furniture', $cat['name']);
        $this->assertSame(5, $cat['total']);
        $this->assertSame('Kursi', $cat['subcategories'][0]['name']);
        $this->assertSame(2, $cat['subcategories'][0]['aktif']);
        $this->assertTrue(Cache::has(DashboardCampusExecutiveService::CACHE_KEY));
    }

    public function test_dashboard_renders_executive_section_and_keeps_existing_campus_stats(): void
    {
        $admin = $this->makeAdmin();
        [$campus, $room, $cat, $sub] = $this->seedTree('GRV', 'UNDIRA GREENVILLE');
        $this->makeItem($room, $cat, $sub, InventoryStatus::Aktif);

        $html = $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Statistik per Kampus', $html);
        $this->assertStringContainsString('Ringkasan Inventaris per Kampus', $html);
        $this->assertStringContainsString('Distribusi inventaris berdasarkan kategori dan sub kategori.', $html);
        $this->assertStringContainsString('UNDIRA GREENVILLE', $html);
        $this->assertStringContainsString('Refresh Data', $html);
        $this->assertStringContainsString('Search SubKategori', $html);
        $this->assertStringContainsString('chartCampus', $html);
    }

    public function test_staff_can_view_but_cannot_refresh(): void
    {
        $staff = $this->makeStaff();
        $this->seedTree('TJD', 'UNDIRA TANJUNG DUREN');

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Refresh Data', false);

        $this->actingAs($staff)
            ->postJson(route('dashboard.campus-summary.refresh'))
            ->assertForbidden();
    }

    public function test_admin_refresh_clears_cache_via_ajax(): void
    {
        $admin = $this->makeAdmin();
        [$campus, $room, $cat, $sub] = $this->seedTree('CBB2', 'UNDIRA CIBUBUR 2');
        $this->makeItem($room, $cat, $sub, InventoryStatus::Aktif);

        app(DashboardCampusExecutiveService::class)->summary();
        $this->assertTrue(Cache::has(DashboardCampusExecutiveService::CACHE_KEY));

        $this->actingAs($admin)
            ->postJson(route('dashboard.campus-summary.refresh'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['html', 'message', 'generated_at']);
    }

    public function test_inventaris_cache_flush_forgets_executive_cache(): void
    {
        Cache::put(DashboardCampusExecutiveService::CACHE_KEY, ['campuses' => []], 300);
        InventarisCache::flush();
        $this->assertFalse(Cache::has(DashboardCampusExecutiveService::CACHE_KEY));
    }

    /**
     * @return array{0:Campus,1:Room,2:Category,3:SubCategory}
     */
    protected function seedTree(string $code, string $name): array
    {
        $campus = Campus::query()->create([
            'code' => $code, 'name' => $name, 'slug' => strtolower($code).'-exec-'.uniqid(), 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'B1', 'name' => 'Gedung 1', 'slug' => 'b1-'.uniqid(), 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R1', 'name' => 'Ruang 1', 'room_type' => 'Kelas', 'capacity' => 40, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'FUR'.substr(uniqid(), -4), 'name' => 'Furniture', 'slug' => 'fur-'.uniqid(), 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'KRS', 'name' => 'Kursi', 'slug' => 'kursi-'.uniqid(), 'is_active' => true,
        ]);

        return [$campus, $room, $category, $sub];
    }

    protected function makeItem(Room $room, Category $category, SubCategory $sub, InventoryStatus $status): InventoryItem
    {
        return InventoryItem::query()->create([
            'inventory_number' => 'INV-EXEC-'.uniqid(),
            'barcode' => 'BC-'.uniqid(),
            'name' => 'Item '.$sub->name,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'room_id' => $room->id,
            'condition' => InventoryCondition::Bagus,
            'status' => $status,
            'quantity' => 1,
            'unit' => 'unit',
            'responsible_person' => 'PIC',
        ]);
    }
}
