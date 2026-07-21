<?php

namespace Tests\Feature;

use App\Enums\InventoryStatus;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\SubCategory;
use App\Models\User;
use App\Services\DashboardStatsService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardCampusStatCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function makeAdmin(): User
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->syncRoles(['super-admin']);

        return $user;
    }

    /**
     * @return array{campus: Campus, room: Room}
     */
    protected function makeCampusTree(string $code, string $name): array
    {
        $campus = Campus::query()->create([
            'code' => $code, 'name' => $name, 'slug' => strtolower($code), 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'A', 'name' => 'Gedung A', 'slug' => $code.'-a', 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => $code.'-101', 'name' => 'Ruang 101', 'is_active' => true,
        ]);

        return compact('campus', 'room');
    }

    protected function makeCategory(string $code, string $name): Category
    {
        return Category::query()->create([
            'code' => $code, 'name' => $name, 'slug' => strtolower($code), 'is_active' => true,
        ]);
    }

    protected function makeItem(Room $room, Category $category, array $overrides = []): InventoryItem
    {
        $sub = SubCategory::query()->firstOrCreate(
            ['category_id' => $category->id, 'code' => $category->code.'S'],
            ['name' => $category->name.' Sub', 'slug' => strtolower($category->code).'-sub', 'is_active' => true]
        );

        return InventoryItem::query()->create(array_merge([
            'inventory_number' => 'INV-'.uniqid(),
            'name' => 'Item '.$category->code,
            'room_id' => $room->id,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'status' => InventoryStatus::Aktif->value,
            'condition' => 'bagus',
            'quantity' => 1,
            'unit' => 'Unit',
        ], $overrides));
    }

    public function test_dashboard_renders_equal_structure_for_empty_and_filled_campuses(): void
    {
        $admin = $this->makeAdmin();
        $filled = $this->makeCampusTree('CBB', 'Undira Cibubur');
        $empty = $this->makeCampusTree('GVL', 'Undira Greenville');
        $cat = $this->makeCategory('FUR', 'Furniture');
        $this->makeItem($filled['room'], $cat);

        $html = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Statistik per Kampus', $html);
        $this->assertStringContainsString('Undira Cibubur', $html);
        $this->assertStringContainsString('Undira Greenville', $html);
        $this->assertStringContainsString('CBB', $html);
        $this->assertStringContainsString('GVL', $html);
        $this->assertStringContainsString('campus-stat-card', $html);
        $this->assertStringContainsString('Belum ada data inventaris pada kampus ini.', $html);
        $this->assertStringContainsString('Dipinjam', $html);
        $this->assertStringContainsString('Maintenance', $html);
        $this->assertStringContainsString('Lihat Inventaris Kampus', $html);
        $this->assertStringNotContainsString('Total Nilai', $html);
        $this->assertStringNotContainsString('Tampilkan', $html); // no expand collapse
        $this->assertSame(2, substr_count($html, 'Kategori Inventaris'));
    }

    public function test_category_preview_limited_to_three_sorted_descending(): void
    {
        $admin = $this->makeAdmin();
        $tree = $this->makeCampusTree('TJD', 'Undira Tanjung Duren');

        $cats = [
            $this->makeCategory('A1', 'Alpha'),
            $this->makeCategory('B2', 'Beta'),
            $this->makeCategory('C3', 'Charlie'),
            $this->makeCategory('D4', 'Delta'),
            $this->makeCategory('E5', 'Echo'),
        ];

        // Counts by index: Alpha 5, Beta 4, Charlie 3, Delta 2, Echo 1
        foreach ($cats as $i => $cat) {
            $count = 5 - $i;
            for ($n = 0; $n < $count; $n++) {
                $this->makeItem($tree['room'], $cat, [
                    'inventory_number' => 'INV-'.$cat->code.'-'.$n,
                    'name' => $cat->name.' '.$n,
                ]);
            }
        }

        $stats = app(DashboardStatsService::class)->campusStats();
        $campus = collect($stats)->firstWhere('code', 'TJD');
        $this->assertNotNull($campus);
        $this->assertCount(5, $campus['categories']);
        $this->assertSame('Alpha', $campus['categories'][0]['name']);
        $this->assertSame(5, $campus['categories'][0]['total']);
        $this->assertSame('Beta', $campus['categories'][1]['name']);
        $this->assertSame('Charlie', $campus['categories'][2]['name']);

        $html = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('+ 2 kategori lainnya', $html);

        // Isolate the TJD campus card markup for preview assertions.
        $this->assertTrue(preg_match(
            '/<article class="card card-undira campus-stat-card h-100">.*?Undira Tanjung Duren.*?<\/article>/s',
            $html,
            $matches
        ) === 1);
        $cardHtml = $matches[0];
        $this->assertStringContainsString('Alpha', $cardHtml);
        $this->assertStringContainsString('Beta', $cardHtml);
        $this->assertStringContainsString('Charlie', $cardHtml);
        $this->assertStringContainsString('+ 2 kategori lainnya', $cardHtml);
        $this->assertStringNotContainsString('Delta', $cardHtml);
        $this->assertStringNotContainsString('Echo', $cardHtml);
        $this->assertSame(3, substr_count($cardHtml, 'campus-stat-cat-list__name'));
    }

    public function test_status_and_location_counts_match_service(): void
    {
        $admin = $this->makeAdmin();
        $tree = $this->makeCampusTree('CBB', 'Cibubur');
        $cat = $this->makeCategory('ELK', 'Elektronik');
        $this->makeItem($tree['room'], $cat, ['status' => InventoryStatus::Aktif->value]);
        $this->makeItem($tree['room'], $cat, [
            'inventory_number' => 'INV-BORROW',
            'status' => InventoryStatus::Dipinjam->value,
        ]);

        $stats = collect(app(DashboardStatsService::class)->campusStats())->firstWhere('code', 'CBB');

        $this->assertSame(1, $stats['buildings']);
        $this->assertSame(1, $stats['floors']);
        $this->assertSame(1, $stats['rooms']);
        $this->assertSame(1, $stats['available']);
        $this->assertSame(1, $stats['borrowed']);
        $this->assertSame(2, $stats['total_units']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('inventory.campus', $tree['campus']->id), false);
    }
}
