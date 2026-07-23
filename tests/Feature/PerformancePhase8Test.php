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
use App\Services\DashboardStatsService;
use App\Support\InventarisCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class PerformancePhase8Test extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_inventory_index_uses_aggregated_campus_stats_without_per_campus_loops(): void
    {
        $admin = $this->makeAdmin();
        $this->makeItem();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Kampus P8');

        $queries = collect(DB::getQueryLog());
        $perCampusStatusLoops = $queries->filter(function (array $query) {
            $sql = strtolower($query['query']);

            // Old pattern: separate COUNT(*) ... WHERE status = ? per campus.
            return str_contains($sql, 'select count(*)')
                && str_contains($sql, 'inventory_items')
                && str_contains($sql, 'status');
        });

        $this->assertSame(0, $perCampusStatusLoops->count());
        $this->assertTrue(
            $queries->contains(fn (array $q) => str_contains(strtolower($q['query']), 'group by')
                && str_contains(strtolower($q['query']), 'inventory_items.status'))
        );
    }

    public function test_inventory_lookup_returns_limited_select2_results(): void
    {
        $admin = $this->makeAdmin();
        $this->makeItem(['name' => 'Proyektor Lookup', 'inventory_number' => 'INV-LOOKUP-1']);
        $this->makeItem(['name' => 'Kursi Biasa', 'inventory_number' => 'INV-OTHER-1']);

        $this->actingAs($admin)
            ->getJson(route('inventory.lookup', ['q' => 'LOOKUP']))
            ->assertOk()
            ->assertJsonFragment(['text' => 'INV-LOOKUP-1 - Proyektor Lookup'])
            ->assertJsonMissing(['text' => 'INV-OTHER-1 - Kursi Biasa']);
    }

    public function test_ops_create_pages_do_not_embed_full_inventory_option_lists(): void
    {
        $admin = $this->makeAdmin();
        $this->makeItem(['name' => 'Barang Heavy', 'inventory_number' => 'INV-HEAVY-1']);

        foreach ([route('borrowings.create'), route('mutations.create'), route('maintenances.create')] as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('select2-inventory', $html);
            $this->assertStringNotContainsString('INV-HEAVY-1 - Barang Heavy', $html);
        }
    }

    public function test_dashboard_summary_is_cached_and_flushed_with_tree_cache(): void
    {
        Cache::flush();
        $this->makeAdmin();
        $this->makeItem();

        $service = app(DashboardStatsService::class);
        $first = $service->summary();
        $second = $service->summary();

        $this->assertSame($first['total_inventory'], $second['total_inventory']);
        $this->assertTrue(Cache::has(InventarisCache::key('dashboard.summary')));

        \App\Services\DashboardTreeService::flushCache();
        $this->assertFalse(Cache::has(InventarisCache::key('dashboard.summary')));
    }

    public function test_report_export_requires_at_least_one_filter(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->from(route('reports.index'))
            ->post(route('reports.generate'), ['format' => 'excel'])
            ->assertRedirect(route('reports.index'))
            ->assertSessionHas('error');
    }

    public function test_unused_dashboard_ajax_routes_are_removed(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get('/dashboard/stats')->assertNotFound();
        $this->actingAs($admin)->get('/dashboard/charts')->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeItem(array $overrides = []): InventoryItem
    {
        $campus = Campus::query()->first() ?? Campus::query()->create([
            'code' => 'P8', 'name' => 'Kampus P8', 'slug' => 'kampus-p8', 'is_active' => true,
        ]);
        $building = Building::query()->first() ?? Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'B8', 'name' => 'Gedung P8', 'slug' => 'gedung-p8', 'is_active' => true,
        ]);
        $floor = Floor::query()->first() ?? Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->first() ?? Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R8', 'name' => 'Ruang P8', 'room_type' => 'Lab', 'capacity' => 10, 'is_active' => true,
        ]);
        $category = Category::query()->first() ?? Category::query()->create([
            'code' => 'P8C', 'name' => 'Kategori P8', 'slug' => 'kategori-p8', 'is_active' => true,
        ]);

        return InventoryItem::query()->create(array_merge([
            'inventory_number' => 'INV-P8-'.uniqid(),
            'barcode' => 'BC-P8-'.uniqid(),
            'name' => 'Barang Phase8',
            'category_id' => $category->id,
            'room_id' => $room->id,
            'condition' => InventoryCondition::Bagus,
            'status' => InventoryStatus::Aktif,
            'quantity' => 1,
            'unit' => 'unit',
        ], $overrides));
    }
}
