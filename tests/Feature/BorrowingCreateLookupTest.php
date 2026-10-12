<?php

namespace Tests\Feature;

use App\Enums\BorrowingStatus;
use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Models\Borrowing;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\SubCategory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class BorrowingCreateLookupTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_create_page_shows_location_and_quick_search_modes(): void
    {
        $admin = $this->makeAdmin();

        $html = $this->actingAs($admin)
            ->get(route('borrowings.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Cari Berdasarkan Lokasi', $html);
        $this->assertStringContainsString('Cari Nomor Inventaris / QR', $html);
        $this->assertStringContainsString('data-inventory-selector', $html);
        $this->assertStringContainsString('data-require-borrowable="1"', $html);
        $this->assertStringContainsString('data-status-filter="aktif"', $html);
        $this->assertStringContainsString('Kampus → Gedung → Lantai → Ruangan', $html);
        $this->assertStringContainsString('Nomor · Nama · QR · Barcode · Serial', $html);
        $this->assertStringContainsString('Scan QR', $html);
        $this->assertStringContainsString('inventory-selector.js', $html);
        $this->assertStringContainsString('btnBorrowSubmit', $html);
        $this->assertStringContainsString('disabled', $html);
        $this->assertStringNotContainsString('INV-SHOULD-NOT-EMBED', $html);
    }

    public function test_location_cascade_and_items_only_aktif(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->seedLocationTree();

        $aktif = $this->makeItem($ctx, [
            'inventory_number' => 'CBB-HB-26-FUR-KRS-000001',
            'name' => 'Kursi Aktif',
            'status' => InventoryStatus::Aktif,
        ]);
        $this->makeItem($ctx, [
            'inventory_number' => 'CBB-HB-26-FUR-KRS-000002',
            'name' => 'Kursi Dipinjam',
            'status' => InventoryStatus::Dipinjam,
        ]);

        $this->actingAs($admin)
            ->getJson(route('inventory.selector.campuses'))
            ->assertOk()
            ->assertJsonFragment(['id' => $ctx['campus']->id]);

        $this->actingAs($admin)
            ->getJson(route('inventory.selector.buildings', ['campus_id' => $ctx['campus']->id]))
            ->assertOk()
            ->assertJsonFragment(['id' => $ctx['building']->id]);

        $this->actingAs($admin)
            ->getJson(route('inventory.selector.floors', ['building_id' => $ctx['building']->id]))
            ->assertOk()
            ->assertJsonFragment(['id' => $ctx['floor']->id]);

        $this->actingAs($admin)
            ->getJson(route('inventory.selector.rooms', ['floor_id' => $ctx['floor']->id]))
            ->assertOk()
            ->assertJsonFragment(['id' => $ctx['room']->id]);

        $this->actingAs($admin)
            ->getJson(route('inventory.selector.categories', [
                'room_id' => $ctx['room']->id,
                'status' => 'aktif',
            ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $ctx['category']->id]);

        $this->actingAs($admin)
            ->getJson(route('inventory.selector.sub-categories', [
                'room_id' => $ctx['room']->id,
                'category_id' => $ctx['category']->id,
                'status' => 'aktif',
            ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $ctx['sub']->id]);

        $items = $this->actingAs($admin)
            ->getJson(route('inventory.selector.items', [
                'room_id' => $ctx['room']->id,
                'category_id' => $ctx['category']->id,
                'sub_category_id' => $ctx['sub']->id,
                'status' => 'aktif',
            ]))
            ->assertOk()
            ->json('results');

        $ids = collect($items)->pluck('id')->all();
        $this->assertContains($aktif->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_quick_search_requires_two_chars_and_excludes_non_aktif(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->seedLocationTree();

        $this->makeItem($ctx, [
            'inventory_number' => 'CBB-HB-26-FUR-KRS-000012',
            'name' => 'Kursi Kuliah',
            'status' => InventoryStatus::Aktif,
        ]);
        $this->makeItem($ctx, [
            'inventory_number' => 'CBB-HB-26-FUR-KRS-000099',
            'name' => 'Kursi Rusak',
            'status' => InventoryStatus::RusakBerat,
        ]);

        $this->actingAs($admin)
            ->getJson(route('inventory.selector.search', ['q' => 'K', 'status' => 'aktif']))
            ->assertOk()
            ->assertJsonPath('results', []);

        $results = $this->actingAs($admin)
            ->getJson(route('inventory.selector.search', ['q' => 'Kursi', 'status' => 'aktif']))
            ->assertOk()
            ->json('results');

        $this->assertCount(1, $results);
        $this->assertStringContainsString('Kursi Kuliah', $results[0]['text']);
    }

    public function test_resolve_code_shows_borrowed_badge_payload_and_blocks_store(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->seedLocationTree();

        $item = $this->makeItem($ctx, [
            'inventory_number' => 'CBB-HB-26-FUR-KRS-000777',
            'name' => 'Meja Dipinjam',
            'status' => InventoryStatus::Dipinjam,
        ]);

        Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Budi Santoso',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDays(3)->toDateString(),
            'status' => BorrowingStatus::Dipinjam,
            'approved_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->postJson(route('inventory.selector.resolve-code'), [
                'code' => 'https://example.test/scan/CBB-HB-26-FUR-KRS-000777',
            ])
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('item.borrowable', false)
            ->assertJsonPath('item.active_borrowing.borrower_name', 'Budi Santoso');

        $this->actingAs($admin)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), [
                'inventory_item_id' => $item->id,
                'borrower_name' => 'Orang Lain',
                'borrower_phone' => '081234567890',
                'borrow_date' => now()->format('Y-m-d\TH:i'),
                'expected_return_date' => now()->addDay()->format('Y-m-d\TH:i'),
                'notes' => 'Kuliah',
            ])
            ->assertRedirect(route('borrowings.create'))
            ->assertSessionHasErrors('inventory_item_id');
    }

    public function test_store_aktif_item_still_works(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->seedLocationTree();
        $item = $this->makeItem($ctx, [
            'inventory_number' => 'CBB-HB-26-FUR-KRS-000888',
            'name' => 'Kursi Siap Pinjam',
            'status' => InventoryStatus::Aktif,
        ]);

        $this->actingAs($admin)
            ->post(route('borrowings.store'), [
                'inventory_item_id' => $item->id,
                'borrower_name' => 'Ani',
                'borrower_email' => 'ani@undira.ac.id',
                'borrower_phone' => '081234567890',
                'borrow_date' => now()->format('Y-m-d\TH:i'),
                'expected_return_date' => now()->addDays(2)->format('Y-m-d\TH:i'),
                'notes' => 'Kuliah',
            ])
            ->assertRedirect(route('borrowings.index'));

        $this->assertDatabaseHas('borrowings', [
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Ani',
            'status' => BorrowingStatus::MenungguPersetujuan->value,
        ]);
        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);
    }

    public function test_partial_location_search_scopes_available_items(): void
    {
        $admin = $this->makeAdmin();
        $cibubur = $this->seedLocationTree();
        $other = $this->seedSecondCampus();

        $match = $this->makeItem($cibubur, [
            'inventory_number' => 'KENKO-UNIT-000051',
            'name' => 'Pulpen Kenko',
            'barcode' => 'BC-KENKO-51',
            'serial_number' => 'SN-KENKO-51',
            'status' => InventoryStatus::Aktif,
        ]);
        $otherBuilding = $this->makeItem($cibubur, [
            'inventory_number' => 'CBB-HB2-MEJA-1',
            'name' => 'Meja Gedung Lain',
            'room_id' => $cibubur['otherRoom']->id,
            'status' => InventoryStatus::Aktif,
        ]);
        $borrowed = $this->makeItem($cibubur, [
            'inventory_number' => 'CBB-DIPINJAM-1',
            'name' => 'Pulpen Kenko Dipinjam',
            'status' => InventoryStatus::Dipinjam,
        ]);
        $elsewhere = $this->makeItem($other, [
            'inventory_number' => 'TJD-KENKO-1',
            'name' => 'Pulpen Kenko Tanjung Duren',
            'status' => InventoryStatus::Aktif,
        ]);

        $this->actingAs($admin)
            ->get(route('borrowings.create'))
            ->assertOk()
            ->assertSee('data-partial-scope="1"', false);

        $campusOnly = $this->actingAs($admin)
            ->getJson(route('inventory.selector.search', [
                'q' => 'Pulpen Kenko',
                'status' => 'aktif',
                'campus_id' => $cibubur['campus']->id,
            ]))
            ->assertOk()
            ->json('results');

        $campusIds = collect($campusOnly)->pluck('id')->all();
        $this->assertContains($match->id, $campusIds);
        $this->assertNotContains($elsewhere->id, $campusIds);
        $this->assertNotContains($borrowed->id, $campusIds);
        $this->assertStringContainsString($cibubur['campus']->name, $campusOnly[0]['location']);
        $this->assertSame($match->inventory_number, $campusOnly[0]['inventory_number']);

        $byBuilding = collect($this->actingAs($admin)
            ->getJson(route('inventory.selector.items', [
                'partial' => 1,
                'q' => 'Meja',
                'status' => 'aktif',
                'campus_id' => $cibubur['campus']->id,
                'building_id' => $cibubur['otherBuilding']->id,
            ]))
            ->assertOk()
            ->json('results'))->pluck('id')->all();
        $this->assertSame([$otherBuilding->id], $byBuilding);

        $byFloor = collect($this->actingAs($admin)
            ->getJson(route('inventory.selector.search', [
                'q' => 'Kenko',
                'status' => 'aktif',
                'campus_id' => $cibubur['campus']->id,
                'building_id' => $cibubur['building']->id,
                'floor_id' => $cibubur['floor']->id,
            ]))
            ->assertOk()
            ->json('results'))->pluck('id')->all();
        $this->assertContains($match->id, $byFloor);
        $this->assertNotContains($otherBuilding->id, $byFloor);

        $byRoom = collect($this->actingAs($admin)
            ->getJson(route('inventory.selector.search', [
                'q' => 'Kenko',
                'status' => 'aktif',
                'room_id' => $cibubur['room']->id,
            ]))
            ->assertOk()
            ->json('results'))->pluck('id')->all();
        $this->assertSame([$match->id], $byRoom);

        $this->actingAs($admin)
            ->getJson(route('inventory.selector.categories', [
                'campus_id' => $cibubur['campus']->id,
                'status' => 'aktif',
            ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $cibubur['category']->id]);

        $this->actingAs($admin)
            ->getJson(route('inventory.selector.sub-categories', [
                'campus_id' => $cibubur['campus']->id,
                'category_id' => $cibubur['category']->id,
                'status' => 'aktif',
            ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $cibubur['sub']->id]);

        $byCategory = collect($this->actingAs($admin)
            ->getJson(route('inventory.selector.search', [
                'q' => 'Pulpen',
                'status' => 'aktif',
                'campus_id' => $cibubur['campus']->id,
                'category_id' => $cibubur['category']->id,
            ]))
            ->assertOk()
            ->json('results'))->pluck('id')->all();
        $this->assertContains($match->id, $byCategory);
        $this->assertNotContains($elsewhere->id, $byCategory);

        $byNumber = $this->actingAs($admin)
            ->getJson(route('inventory.selector.search', [
                'q' => 'KENKO-UNIT',
                'status' => 'aktif',
                'campus_id' => $cibubur['campus']->id,
            ]))
            ->assertOk()
            ->json('results');
        $this->assertSame($match->id, $byNumber[0]['id']);

        $byBarcode = collect($this->actingAs($admin)
            ->getJson(route('inventory.selector.search', [
                'q' => 'BC-KENKO-51',
                'status' => 'aktif',
            ]))
            ->assertOk()
            ->json('results'))->pluck('id')->all();
        $this->assertSame([$match->id], $byBarcode);

        $this->actingAs($admin)
            ->getJson(route('inventory.selector.items', [
                'campus_id' => $cibubur['campus']->id,
                'status' => 'aktif',
                'q' => 'Pulpen',
            ]))
            ->assertOk()
            ->assertJsonPath('results', []);

        $this->actingAs($admin)
            ->getJson(route('inventory.selector.search', [
                'q' => 'Pulpen',
                'status' => 'aktif',
                'campus_id' => $cibubur['campus']->id,
                'building_id' => $other['building']->id,
            ]))
            ->assertOk()
            ->assertJsonPath('results', []);

        $this->actingAs($admin)
            ->getJson(route('inventory.selector.items', [
                'partial' => 1,
                'status' => 'aktif',
            ]))
            ->assertOk()
            ->assertJsonPath('results', []);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)
            ->getJson(route('inventory.selector.search', [
                'q' => 'Pulpen Kenko',
                'status' => 'aktif',
                'campus_id' => $cibubur['campus']->id,
            ]))
            ->assertOk();
        $this->assertLessThan(15, count(DB::getQueryLog()));
    }

    public function test_api_v1_inventory_search_with_token(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->seedLocationTree();
        $this->makeItem($ctx, [
            'inventory_number' => 'CBB-HB-26-FUR-KRS-000333',
            'name' => 'Proyektor API',
            'status' => InventoryStatus::Aktif,
        ]);

        $token = Str::random(64);
        Cache::put('api_token:'.$token, $admin->id, now()->addHour());

        $this->withToken($token)
            ->getJson('/api/v1/inventory/search?q=Proyektor')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Proyektor API']);

        $this->withToken($token)
            ->getJson('/api/v1/inventory/search?q=P')
            ->assertOk()
            ->assertJsonPath('results', []);
    }

    /**
     * @return array{campus:Campus,building:Building,floor:Floor,room:Room,category:Category,sub:SubCategory}
     */
    protected function seedLocationTree(): array
    {
        $campus = Campus::query()->create([
            'code' => 'CBB', 'name' => 'Kampus CBB', 'slug' => 'kampus-cbb-borrow', 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'HB', 'name' => 'Gedung HB', 'slug' => 'gedung-hb-borrow', 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L2', 'name' => 'Lantai 2', 'floor_number' => 2, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'A201', 'name' => 'Ruang A201', 'room_type' => 'Kelas', 'capacity' => 40, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'FUR', 'name' => 'Furniture', 'slug' => 'furniture-borrow', 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'KRS', 'name' => 'Kursi', 'slug' => 'kursi-borrow', 'is_active' => true,
        ]);

        $otherBuilding = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'HB2', 'name' => 'Gedung HB2', 'slug' => 'gedung-hb2-borrow', 'is_active' => true,
        ]);
        $otherFloor = Floor::query()->create([
            'building_id' => $otherBuilding->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $otherRoom = Room::query()->create([
            'floor_id' => $otherFloor->id, 'code' => 'B101', 'name' => 'Ruang B101', 'room_type' => 'Kelas', 'capacity' => 20, 'is_active' => true,
        ]);

        return compact('campus', 'building', 'floor', 'room', 'category', 'sub', 'otherBuilding', 'otherFloor', 'otherRoom');
    }

    /**
     * @return array{campus:Campus,building:Building,floor:Floor,room:Room,category:Category,sub:SubCategory}
     */
    protected function seedSecondCampus(): array
    {
        $campus = Campus::query()->create([
            'code' => 'TJD', 'name' => 'Kampus TJD', 'slug' => 'kampus-tjd-borrow', 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'GA', 'name' => 'Gedung GA', 'slug' => 'gedung-ga-borrow', 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'C101', 'name' => 'Ruang C101', 'room_type' => 'Kelas', 'capacity' => 30, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'ATK', 'name' => 'Alat Tulis', 'slug' => 'atk-borrow', 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'PL', 'name' => 'Pulpen', 'slug' => 'pulpen-borrow', 'is_active' => true,
        ]);

        return compact('campus', 'building', 'floor', 'room', 'category', 'sub');
    }

    /**
     * @param  array{campus:Campus,building:Building,floor:Floor,room:Room,category:Category,sub:SubCategory}  $ctx
     * @param  array<string, mixed>  $overrides
     */
    protected function makeItem(array $ctx, array $overrides = []): InventoryItem
    {
        return InventoryItem::query()->create(array_merge([
            'inventory_number' => 'INV-BORROW-'.uniqid(),
            'barcode' => 'BC-'.uniqid(),
            'name' => 'Barang Borrow',
            'category_id' => $ctx['category']->id,
            'sub_category_id' => $ctx['sub']->id,
            'room_id' => $ctx['room']->id,
            'condition' => InventoryCondition::Bagus,
            'status' => InventoryStatus::Aktif,
            'quantity' => 1,
            'unit' => 'unit',
            'responsible_person' => 'PIC Lab',
        ], $overrides));
    }
}
