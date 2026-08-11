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
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class InventoryItemsDatatableTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_items_page_does_not_load_datatables_i18n_from_cdn(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('inventory.items'));

        $response->assertOk();
        $response->assertDontSee('cdn.datatables.net/plug-ins', false);
        $response->assertDontSee('http://cdn.datatables.net', false);
        $response->assertSee('datatablesIdLanguage', false);
        $response->assertSee('Tidak ada data yang tersedia pada tabel ini', false);
        $this->assertFileExists(public_path('vendor/datatables/i18n/id.json'));
    }

    public function test_datatables_assets_are_page_scoped_not_global(): void
    {
        $admin = $this->makeAdmin();

        $items = $this->actingAs($admin)->get(route('inventory.items'));
        $items->assertOk();
        $items->assertSee('cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js', false);
        $items->assertSee('cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css', false);
        $items->assertSee('inventory-items-page', false);
        $items->assertSee('inventory-items-table-shell', false);
        $items->assertSee('col-12 col-sm-6 col-md-3', false);
        $items->assertSee('scrollX: true', false);
        // Shell must not create a second overflow-x scroller alongside DataTables scrollX.
        $itemsHtml = $items->getContent();
        $this->assertDoesNotMatchRegularExpression(
            '/\.inventory-items-table-shell\s*\{[^}]*overflow-x\s*:\s*auto/i',
            $itemsHtml,
            'Table shell must not set overflow-x:auto when DataTables scrollX is enabled.'
        );
        $this->assertStringContainsString('jquery-3.7.1.min.js', $itemsHtml);
        $jqueryPos = strpos($itemsHtml, 'jquery-3.7.1.min.js');
        $dtCorePos = strpos($itemsHtml, 'jquery.dataTables.min.js');
        $dtBsPos = strpos($itemsHtml, 'dataTables.bootstrap5.min.js');
        $this->assertNotFalse($jqueryPos);
        $this->assertNotFalse($dtCorePos);
        $this->assertNotFalse($dtBsPos);
        $this->assertLessThan($dtCorePos, $jqueryPos, 'jQuery must load before DataTables core');
        $this->assertLessThan($dtBsPos, $dtCorePos, 'DataTables core must load before Bootstrap5 integration');

        // Non-DataTables inventory page must not pay the DataTables CDN cost.
        $index = $this->actingAs($admin)->get(route('inventory.index'));
        $index->assertOk();
        $index->assertDontSee('cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js', false);
        $index->assertDontSee('cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css', false);
    }

    public function test_admin_users_page_loads_page_scoped_datatables_assets(): void
    {
        // user.manage is granted to super-admin (plain admin may receive 403).
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAs($admin)->get(route('admin.users.index'));
        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css', $html);
        $this->assertStringContainsString('jquery.dataTables.min.js', $html);
        $this->assertStringContainsString('dataTables.bootstrap5.min.js', $html);

        $jqueryPos = strpos($html, 'jquery-3.7.1.min.js');
        $dtCorePos = strpos($html, 'jquery.dataTables.min.js');
        $this->assertNotFalse($jqueryPos);
        $this->assertNotFalse($dtCorePos);
        $this->assertLessThan($dtCorePos, $jqueryPos, 'Admin Users: jQuery must load before DataTables core');
    }

    /**
     * Living frontend contract: the only AJAX consumer of inventory.items JSON is items.blade.php.
     * Required columns/actions must stay available; removed heavy keys must not be referenced there.
     */
    public function test_items_blade_is_only_ajax_consumer_and_uses_slim_contract_fields(): void
    {
        $blade = (string) file_get_contents(resource_path('views/inventory/items.blade.php'));

        foreach ([
            "data: 'id'",
            "data: 'inventory_number'",
            "data: 'name'",
            "data: 'category'",
            "data: 'pic_name'",
            "data: 'full_location'",
            "data: 'status'",
            "data: 'condition'",
            'row.id',
        ] as $required) {
            $this->assertStringContainsString($required, $blade, "Frontend contract missing: {$required}");
        }

        foreach ([
            "data: 'qr_url'",
            "data: 'sub_category'",
            "data: 'subCategory'",
            "data: 'specification'",
            "data: 'notes'",
            "data: 'warranty_info'",
            'row.qr_url',
            'row.sub_category',
            'row.subCategory',
            'row.specification',
            'row.notes',
            'row.warranty_info',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $blade, "Frontend unexpectedly references: {$forbidden}");
        }

        // Checkbox + mass label use selected id values only (not other payload fields).
        $this->assertStringContainsString('selectedIds', $blade);
        $this->assertStringContainsString("name=\"inventory_ids[]\"", $blade);
        $this->assertStringContainsString('checkPageAll', $blade);
    }

    public function test_items_page_does_not_depend_on_suppliers_or_vendors(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('inventory.items'));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringNotContainsString('filterSupplier', $html);
        $this->assertStringNotContainsString('filterVendor', $html);
        $this->assertStringNotContainsString('Semua Supplier', $html);
        $this->assertStringNotContainsString('Semua Vendor', $html);
        $this->assertStringNotContainsString('$suppliers', $html);
        $this->assertStringNotContainsString('$vendors', $html);
    }

    public function test_datatable_ajax_returns_frontend_contract_fields(): void
    {
        $admin = $this->makeAdmin();
        $pic = User::factory()->create(['name' => 'PIC Kontrak', 'is_active' => true]);
        $item = $this->seedItem('DT-AJAX-001', [
            'name' => 'Proyektor Kontrak',
            'pic_id' => $pic->id,
            'responsible_person' => 'Legacy PIC',
            'status' => InventoryStatus::Aktif->value,
            'condition' => InventoryCondition::Bagus->value,
            'specification' => 'SHOULD-NOT-APPEAR',
            'notes' => 'SHOULD-NOT-APPEAR',
            'warranty_info' => 'SHOULD-NOT-APPEAR',
        ]);

        Model::preventLazyLoading();
        try {
            $response = $this->datatableRequest($admin, [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'order' => [['column' => 1, 'dir' => 'asc']],
                'search' => ['value' => '', 'regex' => 'false'],
            ]);
        } finally {
            Model::preventLazyLoading(false);
        }

        $response->assertOk();
        $response->assertJsonPath('draw', 1);
        $response->assertJsonPath('recordsTotal', 1);
        $response->assertJsonPath('recordsFiltered', 1);

        $row = $response->json('data.0');
        $this->assertIsArray($row);
        $this->assertSame($item->id, $row['id']);
        $this->assertSame('DT-AJAX-001', $row['inventory_number']);
        $this->assertSame('Proyektor Kontrak', $row['name']);
        $this->assertSame('PIC Kontrak', $row['pic_name']);
        $this->assertNotEmpty($row['full_location']);
        $this->assertStringContainsString('Kampus', $row['full_location']);
        $this->assertIsArray($row['category']);
        $this->assertNotEmpty($row['category']['name']);
        $this->assertSame(InventoryStatus::Aktif->value, $row['status']['value']);
        $this->assertNotEmpty($row['status']['label']);
        $this->assertSame(InventoryCondition::Bagus->value, $row['condition']['value']);
        $this->assertNotEmpty($row['condition']['label']);

        // Optimization guards (justified by blade consumer audit above — not the sole proof).
        $this->assertArrayNotHasKey('specification', $row);
        $this->assertArrayNotHasKey('notes', $row);
        $this->assertArrayNotHasKey('warranty_info', $row);
        $this->assertArrayNotHasKey('qr_url', $row);
        $this->assertArrayNotHasKey('sub_category', $row);
        $this->assertArrayNotHasKey('sub_category_id', $row);
    }

    public function test_datatable_detects_draw_without_ajax_header(): void
    {
        $admin = $this->makeAdmin();
        $this->seedItem('DT-DRAW-001');

        // Some browsers / proxies strip X-Requested-With; draw param must still force JSON.
        $response = $this->actingAs($admin)
            ->get(route('inventory.items', [
                'draw' => 2,
                'start' => 0,
                'length' => 10,
                'order' => [['column' => 1, 'dir' => 'asc']],
                'search' => ['value' => '', 'regex' => 'false'],
            ]));

        $response->assertOk();
        $response->assertJsonPath('draw', 2);
        $this->assertIsArray($response->json('data'));
    }

    public function test_datatable_handles_search_array_safely(): void
    {
        $admin = $this->makeAdmin();
        $this->seedItem('DT-SEARCH-001', ['name' => 'Kursi Lab Unik']);
        $this->seedItem('DT-SEARCH-002', ['name' => 'Meja Biasa']);

        $hit = $this->datatableRequest($admin, [
            'draw' => 3,
            'start' => 0,
            'length' => 10,
            'order' => [['column' => 1, 'dir' => 'asc']],
            'search' => ['value' => 'Kursi Lab', 'regex' => 'false'],
        ]);

        $hit->assertOk();
        $hit->assertJsonPath('draw', 3);
        $this->assertSame(1, (int) $hit->json('recordsFiltered'));
        $this->assertSame('DT-SEARCH-001', $hit->json('data.0.inventory_number'));

        $miss = $this->datatableRequest($admin, [
            'draw' => 4,
            'start' => 0,
            'length' => 10,
            'order' => [['column' => 1, 'dir' => 'asc']],
            'search' => ['value' => 'TidakAdaHasilXYZ', 'regex' => 'false'],
        ]);

        $miss->assertOk();
        $this->assertSame(0, (int) $miss->json('recordsFiltered'));
        $this->assertSame([], $miss->json('data'));
    }

    public function test_datatable_pagination_and_sorting_protocol(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->seedSharedContext();

        foreach (['ZZ-SORT-002', 'AA-SORT-001', 'MM-SORT-003'] as $number) {
            $this->seedItemInContext($ctx, $number);
        }

        $page1 = $this->datatableRequest($admin, [
            'draw' => 10,
            'start' => 0,
            'length' => 2,
            'order' => [['column' => 1, 'dir' => 'asc']],
            'search' => ['value' => '', 'regex' => 'false'],
        ]);
        $page1->assertOk();
        $page1->assertJsonPath('recordsTotal', 3);
        $page1->assertJsonPath('recordsFiltered', 3);
        $this->assertCount(2, $page1->json('data'));
        $this->assertSame('AA-SORT-001', $page1->json('data.0.inventory_number'));
        $this->assertSame('MM-SORT-003', $page1->json('data.1.inventory_number'));

        $page2 = $this->datatableRequest($admin, [
            'draw' => 11,
            'start' => 2,
            'length' => 2,
            'order' => [['column' => 1, 'dir' => 'asc']],
            'search' => ['value' => '', 'regex' => 'false'],
        ]);
        $page2->assertOk();
        $this->assertCount(1, $page2->json('data'));
        $this->assertSame('ZZ-SORT-002', $page2->json('data.0.inventory_number'));

        $desc = $this->datatableRequest($admin, [
            'draw' => 12,
            'start' => 0,
            'length' => 10,
            'order' => [['column' => 1, 'dir' => 'desc']],
            'search' => ['value' => '', 'regex' => 'false'],
        ]);
        $desc->assertOk();
        $this->assertSame('ZZ-SORT-002', $desc->json('data.0.inventory_number'));
    }

    public function test_datatable_filter_by_category_and_status(): void
    {
        $admin = $this->makeAdmin();
        $ctxA = $this->seedSharedContext('A');
        $ctxB = $this->seedSharedContext('B');

        $this->seedItemInContext($ctxA, 'FLT-A-001', [
            'status' => InventoryStatus::Aktif->value,
            'condition' => InventoryCondition::Bagus->value,
        ]);
        $this->seedItemInContext($ctxB, 'FLT-B-001', [
            'status' => InventoryStatus::Dipinjam->value,
            'condition' => InventoryCondition::Cukup->value,
        ]);

        $byCategory = $this->datatableRequest($admin, [
            'draw' => 20,
            'start' => 0,
            'length' => 25,
            'order' => [['column' => 1, 'dir' => 'asc']],
            'search' => ['value' => '', 'regex' => 'false'],
            'category_id' => $ctxA['category']->id,
        ]);
        $byCategory->assertOk();
        $this->assertSame(1, (int) $byCategory->json('recordsFiltered'));
        $this->assertSame('FLT-A-001', $byCategory->json('data.0.inventory_number'));

        $byStatus = $this->datatableRequest($admin, [
            'draw' => 21,
            'start' => 0,
            'length' => 25,
            'order' => [['column' => 1, 'dir' => 'asc']],
            'search' => ['value' => '', 'regex' => 'false'],
            'status' => InventoryStatus::Dipinjam->value,
        ]);
        $byStatus->assertOk();
        $this->assertSame(1, (int) $byStatus->json('recordsFiltered'));
        $this->assertSame('FLT-B-001', $byStatus->json('data.0.inventory_number'));
    }

    public function test_datatable_query_count_does_not_scale_with_row_count(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->seedSharedContext('Q');
        $picA = User::factory()->create(['name' => 'PIC Alpha', 'is_active' => true]);
        $picB = User::factory()->create(['name' => 'PIC Beta', 'is_active' => true]);

        $statuses = [
            InventoryStatus::Aktif,
            InventoryStatus::Dipinjam,
            InventoryStatus::Maintenance,
            InventoryStatus::RusakRingan,
        ];
        $conditions = [
            InventoryCondition::Bagus,
            InventoryCondition::Cukup,
            InventoryCondition::Baru,
            InventoryCondition::RusakRingan,
        ];

        $this->seedItemInContext($ctx, 'Q-0001', [
            'pic_id' => $picA->id,
            'status' => $statuses[0]->value,
            'condition' => $conditions[0]->value,
        ]);

        $count1 = $this->measureDatatableQueries($admin, 10);
        $this->assertNotEmpty($count1['rows']);
        $this->assertNotEmpty($count1['rows'][0]['full_location']);

        for ($i = 2; $i <= 25; $i++) {
            $this->seedItemInContext($ctx, sprintf('Q-%04d', $i), [
                'pic_id' => ($i % 2 === 0) ? $picA->id : $picB->id,
                'status' => $statuses[$i % count($statuses)]->value,
                'condition' => $conditions[$i % count($conditions)]->value,
                'name' => 'Item batch '.$i,
            ]);
        }
        $count25 = $this->measureDatatableQueries($admin, 25);

        for ($i = 26; $i <= 50; $i++) {
            $this->seedItemInContext($ctx, sprintf('Q-%04d', $i), [
                'pic_id' => ($i % 2 === 0) ? $picA->id : $picB->id,
                'status' => $statuses[$i % count($statuses)]->value,
                'condition' => $conditions[$i % count($conditions)]->value,
                'name' => 'Item batch '.$i,
            ]);
        }
        $count50 = $this->measureDatatableQueries($admin, 50);

        for ($i = 51; $i <= 100; $i++) {
            $this->seedItemInContext($ctx, sprintf('Q-%04d', $i), [
                'pic_id' => ($i % 2 === 0) ? $picA->id : $picB->id,
                'status' => $statuses[$i % count($statuses)]->value,
                'condition' => $conditions[$i % count($conditions)]->value,
                'name' => 'Item batch '.$i,
            ]);
        }
        $count100 = $this->measureDatatableQueries($admin, 100);

        // Relation/eager-load queries must stay flat (not linear with page size).
        $this->assertSame(
            $count1['relation_queries'],
            $count25['relation_queries'],
            'Relation query count changed between 1 and 25 rows (possible N+1).'
        );
        $this->assertSame(
            $count25['relation_queries'],
            $count50['relation_queries'],
            'Relation query count changed between 25 and 50 rows (possible N+1).'
        );
        $this->assertSame(
            $count50['relation_queries'],
            $count100['relation_queries'],
            'Relation query count changed between 50 and 100 rows (possible N+1).'
        );

        // Total query count should also stay in a tight band (auth/protocol noise allowed).
        $this->assertLessThanOrEqual(
            $count1['total_queries'] + 3,
            $count100['total_queries'],
            sprintf(
                'Total queries grew too much: 1-row=%d, 25=%d, 50=%d, 100=%d',
                $count1['total_queries'],
                $count25['total_queries'],
                $count50['total_queries'],
                $count100['total_queries'],
            )
        );

        $this->assertSame(1, $count1['records_filtered']);
        $this->assertSame(25, $count25['records_filtered']);
        $this->assertSame(50, $count50['records_filtered']);
        $this->assertSame(100, $count100['records_filtered']);
        $this->assertCount(1, $count1['rows']);
        $this->assertCount(25, $count25['rows']);
        $this->assertCount(50, $count50['rows']);
        $this->assertCount(100, $count100['rows']);

        // Keep measured numbers visible in PHPUnit output on failure context.
        fwrite(STDERR, sprintf(
            "\n[Inventory DT query measurement] 1row: total=%d relation=%d | 25: total=%d relation=%d | 50: total=%d relation=%d | 100: total=%d relation=%d\n",
            $count1['total_queries'],
            $count1['relation_queries'],
            $count25['total_queries'],
            $count25['relation_queries'],
            $count50['total_queries'],
            $count50['relation_queries'],
            $count100['total_queries'],
            $count100['relation_queries'],
        ));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function datatableRequest(User $admin, array $params)
    {
        return $this->actingAs($admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('inventory.items', $params));
    }

    /**
     * @return array{
     *     total_queries: int,
     *     relation_queries: int,
     *     records_filtered: int,
     *     rows: list<array<string, mixed>>
     * }
     */
    protected function measureDatatableQueries(User $admin, int $length): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Model::preventLazyLoading();
        try {
            $response = $this->datatableRequest($admin, [
                'draw' => 100 + $length,
                'start' => 0,
                'length' => $length,
                'order' => [['column' => 1, 'dir' => 'asc']],
                'search' => ['value' => '', 'regex' => 'false'],
            ]);
        } finally {
            Model::preventLazyLoading(false);
        }

        $response->assertOk();
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        // Count only location/category/pic relation tables used by the slim payload.
        // Exclude auth/permission noise (roles, model_has_roles, etc.).
        $relationQueries = collect($log)->filter(function (array $query) {
            $sql = strtolower($query['query']);

            foreach (['"categories"', '"rooms"', '"floors"', '"buildings"', '"campuses"'] as $table) {
                if (str_contains($sql, 'from '.$table)) {
                    return true;
                }
            }

            // PIC eager-load: users selected by id list (not anonymous auth-only patterns).
            return str_contains($sql, 'from "users"') && str_contains($sql, ' in (');
        })->count();

        return [
            'total_queries' => count($log),
            'relation_queries' => $relationQueries,
            'records_filtered' => (int) $response->json('recordsFiltered'),
            'rows' => $response->json('data') ?? [],
        ];
    }

    /**
     * @return array{campus: Campus, building: Building, floor: Floor, room: Room, category: Category, sub: SubCategory}
     */
    protected function seedSharedContext(string $suffix = '1'): array
    {
        $campus = Campus::query()->create([
            'code' => 'KC'.$suffix,
            'name' => 'Kampus DT '.$suffix,
            'slug' => 'kampus-dt-'.strtolower($suffix).'-'.uniqid(),
            'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'code' => 'BG'.$suffix,
            'name' => 'Gedung '.$suffix,
            'slug' => 'gedung-dt-'.strtolower($suffix).'-'.uniqid(),
            'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id,
            'code' => 'L1'.$suffix,
            'name' => 'Lantai 1',
            'floor_number' => 1,
            'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id,
            'code' => 'R'.$suffix,
            'name' => 'Ruang '.$suffix,
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'CT'.$suffix,
            'name' => 'Kategori '.$suffix,
            'slug' => 'cat-dt-'.strtolower($suffix).'-'.uniqid(),
            'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id,
            'code' => 'SC'.$suffix,
            'name' => 'Sub '.$suffix,
            'slug' => 'sub-dt-'.strtolower($suffix).'-'.uniqid(),
            'is_active' => true,
        ]);

        return compact('campus', 'building', 'floor', 'room', 'category', 'sub');
    }

    /**
     * @param  array{campus: Campus, building: Building, floor: Floor, room: Room, category: Category, sub: SubCategory}  $ctx
     * @param  array<string, mixed>  $overrides
     */
    protected function seedItemInContext(array $ctx, string $number, array $overrides = []): InventoryItem
    {
        return InventoryItem::query()->create(array_merge([
            'inventory_number' => $number,
            'barcode' => $number,
            'name' => 'Item '.$number,
            'category_id' => $ctx['category']->id,
            'sub_category_id' => $ctx['sub']->id,
            'condition' => InventoryCondition::Bagus->value,
            'status' => InventoryStatus::Aktif->value,
            'room_id' => $ctx['room']->id,
            'specification' => 'Spec '.$number,
            'notes' => 'Notes '.$number,
            'warranty_info' => 'Warranty '.$number,
            'price' => 100000,
            'quantity' => 1,
            'unit' => 'Unit',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function seedItem(string $number, array $overrides = []): InventoryItem
    {
        static $n = 0;
        $n++;

        return $this->seedItemInContext($this->seedSharedContext((string) $n), $number, $overrides);
    }
}
