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
use App\Services\LocationHierarchyService;
use App\Support\InventarisCache;
use Illuminate\Support\Facades\Cache;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class InventoryFilterEmptyStateTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_active_tree_always_returns_collection_even_with_corrupt_cache(): void
    {
        $campus = Campus::query()->create([
            'code' => 'CFE',
            'name' => 'Kampus Filter Empty',
            'slug' => 'kampus-filter-empty',
            'is_active' => true,
        ]);

        $cacheKey = InventarisCache::key('location.tree.'.$campus->id);
        Cache::put($cacheKey, ['not' => 'a-collection'], InventarisCache::TTL);

        $tree = app(LocationHierarchyService::class)->activeTree($campus->id);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $tree);
        $this->assertTrue($tree->every(fn ($row) => $row instanceof Campus));
    }

    public function test_active_tree_returns_empty_collection_for_unknown_campus(): void
    {
        $tree = app(LocationHierarchyService::class)->activeTree(999999);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $tree);
        $this->assertTrue($tree->isEmpty());
    }

    public function test_campus_filter_with_missing_room_shows_empty_state_not_500(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->seedTree();
        $this->makeItem($ctx);

        $response = $this->actingAs($admin)->get(route('inventory.campus', [
            'campus' => $ctx['campus'],
            'building_id' => $ctx['building']->id,
            'floor_id' => $ctx['floor']->id,
            'room_id' => 999999,
        ]));

        $response->assertOk();
        $response->assertSee('Ruangan tidak ditemukan');
        $response->assertSee('Data inventaris pada lokasi yang dipilih belum tersedia');
        $response->assertSee('Tampilkan Semua Inventaris');
        $response->assertSee('filterBuilding', false);
        $response->assertDontSee('Server Error');
    }

    public function test_campus_filter_with_valid_room_but_no_inventory_shows_empty_state(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->seedTree('E');
        // Room exists but has no inventory items.

        $response = $this->actingAs($admin)->get(route('inventory.campus', [
            'campus' => $ctx['campus'],
            'building_id' => $ctx['building']->id,
            'floor_id' => $ctx['floor']->id,
            'room_id' => $ctx['room']->id,
        ]));

        $response->assertOk();
        $response->assertSee('Ruangan tidak ditemukan');
        $response->assertSee('Tampilkan Semua Inventaris');
    }

    /**
     * @return array{campus:Campus,building:Building,floor:Floor,room:Room,category:Category}
     */
    protected function seedTree(string $suffix = 'F'): array
    {
        $campus = Campus::query()->create([
            'code' => 'C'.$suffix,
            'name' => 'Kampus '.$suffix,
            'slug' => 'kampus-'.$suffix.'-filter-empty',
            'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'code' => 'B'.$suffix,
            'name' => 'Gedung '.$suffix,
            'slug' => 'gedung-'.$suffix.'-filter-empty',
            'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id,
            'code' => 'L1'.$suffix,
            'name' => 'Lantai 1 '.$suffix,
            'floor_number' => 1,
            'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id,
            'code' => 'R101'.$suffix,
            'name' => 'Ruang 101 '.$suffix,
            'room_type' => 'Kelas',
            'capacity' => 20,
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'CAT'.$suffix,
            'name' => 'Kategori '.$suffix,
            'slug' => 'kategori-'.$suffix.'-filter-empty',
            'is_active' => true,
        ]);

        return compact('campus', 'building', 'floor', 'room', 'category');
    }

    /**
     * @param  array{campus:Campus,building:Building,floor:Floor,room:Room,category:Category}  $ctx
     */
    protected function makeItem(array $ctx): InventoryItem
    {
        return InventoryItem::query()->create([
            'inventory_number' => 'INV-FE-'.uniqid(),
            'barcode' => 'BC-FE-'.uniqid(),
            'name' => 'Barang Filter Empty',
            'category_id' => $ctx['category']->id,
            'room_id' => $ctx['room']->id,
            'condition' => InventoryCondition::Bagus,
            'status' => InventoryStatus::Aktif,
            'quantity' => 1,
            'unit' => 'unit',
        ]);
    }
}
