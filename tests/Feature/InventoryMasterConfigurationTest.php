<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryConditionMaster;
use App\Models\InventoryMasterConfiguration;
use App\Models\OrgUnit;
use App\Models\ProcurementType;
use App\Models\PurchaseYear;
use App\Models\Room;
use App\Models\RoomFunction;
use App\Models\Setting;
use App\Models\SubCategory;
use App\Services\InventoryCodeService;
use App\Services\LocationCodeGeneratorService;
use Database\Seeders\InventorySettingSeeder;
use Database\Seeders\MasterLookupSeeder;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class InventoryMasterConfigurationTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_super_admin_can_open_kategori_konfigurasi_page(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(MasterLookupSeeder::class);

        $response = $this->actingAs($admin)->get(route('categories.index', ['tab' => 'konfigurasi']));

        $response->assertOk();
        $response->assertSee('Konfigurasi Inventaris');
        $response->assertSee('Kode Lokasi Otomatis');
        $response->assertSee('Kode Ruangan');
    }

    public function test_viewer_cannot_store_master_options(): void
    {
        $viewer = $this->makeViewer();

        $response = $this->actingAs($viewer)->postJson(route('master-data.options.store'), [
            'type' => 'purchase_year',
            'year' => 2099,
        ]);

        $response->assertForbidden();
    }

    public function test_location_code_format_and_room_code_are_separate(): void
    {
        $campus = Campus::query()->create([
            'code' => 'CBB', 'name' => 'Cibubur', 'slug' => 'cibubur', 'is_active' => true,
        ]);
        $org = OrgUnit::query()->create(['code' => 'PPPA', 'name' => 'PPPA', 'is_active' => true]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'A', 'name' => 'Gedung A', 'slug' => 'a', 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'A-102', 'name' => 'Ruang A-102', 'is_active' => true,
        ]);

        $code = app(LocationCodeGeneratorService::class)->generate($campus, $org, $room);

        $this->assertSame('CBB-PPPA-A-102', $code);
        $this->assertSame('A-102', $room->code);
        $this->assertNotSame($room->code, $code);
    }

    public function test_preview_does_not_consume_sequence(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(InventorySettingSeeder::class);
        Setting::set('inventory.sequence_padding', '6', 'inventory');

        [$campus, $category, $sub] = $this->seedMinimalMasters();

        $service = app(InventoryCodeService::class);
        $before = $service->peekNextSequence($campus->id, $sub->id, $category->id);

        $this->actingAs($admin)->getJson(route('master-configurations.preview', [
            'campus_id' => $campus->id,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
        ]))->assertOk();

        $after = $service->peekNextSequence($campus->id, $sub->id, $category->id);
        $this->assertSame($before['next_value'], $after['next_value']);
        $this->assertSame($before['last_value'], $after['last_value']);
    }

    public function test_sequence_continues_across_rooms_same_campus_subclass(): void
    {
        $this->seed(InventorySettingSeeder::class);
        Setting::set('inventory.number_format', '{sequence}', 'inventory');
        Setting::set('inventory.sequence_padding', '6', 'inventory');

        [$campus, $category, $sub, $org, $room1, $room2] = $this->seedTwoRooms();

        $service = app(InventoryCodeService::class);
        $n1 = $service->allocateSequence($campus->id, $sub->id, $category->id);
        $n2 = $service->allocateSequence($campus->id, $sub->id, $category->id);

        $this->assertSame(1, $n1);
        $this->assertSame(2, $n2);

        // Different room must not reset scope
        $peek = $service->peekNextSequence($campus->id, $sub->id, $category->id);
        $this->assertSame(3, $peek['next_value']);
        $this->assertNotSame($room1->id, $room2->id);
    }

    public function test_store_configuration_and_reject_duplicate(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(MasterLookupSeeder::class);
        $payload = $this->validConfigPayload();
        $payload['create_assets'] = 0;

        $this->actingAs($admin)->post(route('master-configurations.store'), $payload)
            ->assertRedirect(route('categories.index', ['tab' => 'konfigurasi']));

        $this->assertSame(1, InventoryMasterConfiguration::query()->count());
        $cfg = InventoryMasterConfiguration::query()->first();
        $this->assertStringContainsString('-', $cfg->generated_location_code);

        $this->actingAs($admin)->post(route('master-configurations.store'), $payload)
            ->assertSessionHasErrors();
    }

    public function test_store_with_assets_creates_items_in_room_with_codes(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(MasterLookupSeeder::class);
        $this->seed(InventorySettingSeeder::class);
        Setting::set('inventory.number_format', '{sequence}', 'inventory');
        Setting::set('inventory.sequence_padding', '6', 'inventory');

        $payload = $this->validConfigPayload();
        $roomId = $payload['room_id'];
        $payload['create_assets'] = 1;
        $payload['asset_name'] = 'Kursi Konfigurasi';
        $payload['quantity'] = 2;
        $payload['unit'] = 'Unit';
        $payload['brand'] = 'Chitose';
        $payload['price'] = 150000;

        $this->actingAs($admin)->post(route('master-configurations.store'), $payload)
            ->assertRedirect(route('rooms.show', $roomId));

        $items = \App\Models\InventoryItem::query()->where('room_id', $roomId)->where('name', 'Kursi Konfigurasi')->get();
        $this->assertCount(2, $items);

        foreach ($items as $item) {
            $this->assertSame($roomId, (int) $item->room_id);
            $this->assertNotEmpty($item->inventory_number);
            $this->assertNotEmpty($item->qr_code_path);
            $this->assertNotEmpty($item->qr_code_path);
        }

        $this->actingAs($admin)->get(route('rooms.show', $roomId))
            ->assertOk()
            ->assertSee('Kursi Konfigurasi');
    }

    public function test_inventory_create_redirects_to_konfigurasi(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(MasterLookupSeeder::class);
        $payload = $this->validConfigPayload();
        $roomId = $payload['room_id'];

        $this->actingAs($admin)
            ->get(route('inventory.create', ['room_id' => $roomId]))
            ->assertRedirect(route('categories.index', [
                'tab' => 'konfigurasi',
                'create' => 1,
                'room_id' => $roomId,
                'floor_id' => $payload['floor_id'],
                'building_id' => $payload['building_id'],
                'campus_id' => $payload['campus_id'],
            ]));
    }

    public function test_buildings_endpoint_scoped_to_campus(): void
    {
        $admin = $this->makeSuperAdmin();
        $c1 = Campus::query()->create(['code' => 'C1', 'name' => 'C1', 'slug' => 'c1', 'is_active' => true]);
        $c2 = Campus::query()->create(['code' => 'C2', 'name' => 'C2', 'slug' => 'c2', 'is_active' => true]);
        Building::query()->create(['campus_id' => $c1->id, 'code' => 'B1', 'name' => 'B1', 'slug' => 'b1', 'is_active' => true]);
        Building::query()->create(['campus_id' => $c2->id, 'code' => 'B2', 'name' => 'B2', 'slug' => 'b2', 'is_active' => true]);

        $response = $this->actingAs($admin)->getJson(route('master-data.buildings.by-campus', $c1));
        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('code')->all();
        $this->assertSame(['B1'], $ids);
    }

    public function test_soft_deleted_inventory_does_not_reuse_sequence(): void
    {
        $this->seed(InventorySettingSeeder::class);
        Setting::set('inventory.sequence_padding', '6', 'inventory');
        [$campus, $category, $sub] = $this->seedMinimalMasters();

        $service = app(InventoryCodeService::class);
        $this->assertSame(1, $service->allocateSequence($campus->id, $sub->id, $category->id));
        $this->assertSame(2, $service->allocateSequence($campus->id, $sub->id, $category->id));

        // Soft-deleting assets must never free sequence slots (last_value stays).
        $next = $service->allocateSequence($campus->id, $sub->id, $category->id);
        $this->assertSame(3, $next);
        $peek = $service->peekNextSequence($campus->id, $sub->id, $category->id);
        $this->assertSame(3, $peek['last_value']);
        $this->assertSame(4, $peek['next_value']);
    }

    /**
     * @return array{0: Campus, 1: Category, 2: SubCategory}
     */
    protected function seedMinimalMasters(): array
    {
        $campus = Campus::query()->create([
            'code' => 'TST', 'name' => 'Test', 'slug' => 'test-cfg', 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'A', 'name' => 'A', 'slug' => 'a-cfg', 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'L1', 'floor_number' => 1, 'is_active' => true,
        ]);
        Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R1', 'name' => 'R1', 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'FUR', 'name' => 'Furniture', 'slug' => 'furniture-cfg', 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'KRS', 'name' => 'Kursi', 'slug' => 'kursi-cfg', 'is_active' => true,
        ]);

        return [$campus, $category, $sub];
    }

    /**
     * @return array{0: Campus, 1: Category, 2: SubCategory, 3: OrgUnit, 4: Room, 5: Room}
     */
    protected function seedTwoRooms(): array
    {
        [$campus, $category, $sub] = $this->seedMinimalMasters();
        $org = OrgUnit::query()->create(['code' => 'PPPA', 'name' => 'PPPA', 'is_active' => true]);
        $floor = Floor::query()->first();
        $room1 = Room::query()->first();
        $room2 = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R2', 'name' => 'R2', 'is_active' => true,
        ]);

        return [$campus, $category, $sub, $org, $room1, $room2];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validConfigPayload(): array
    {
        $campus = Campus::query()->create([
            'code' => 'CBB', 'name' => 'Cibubur', 'slug' => 'cbb-cfg', 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'A', 'name' => 'Gedung A', 'slug' => 'ga-cfg', 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'A-101', 'name' => 'A-101', 'is_active' => true,
        ]);
        $org = OrgUnit::query()->where('code', 'PPPA')->first()
            ?? OrgUnit::query()->create(['code' => 'PPPA', 'name' => 'PPPA', 'is_active' => true]);
        $proc = ProcurementType::query()->where('is_active', true)->first()
            ?? ProcurementType::query()->create(['code' => 'UNDIRA', 'name' => 'UNDIRA', 'is_active' => true]);
        $year = PurchaseYear::query()->where('year', 2024)->first()
            ?? PurchaseYear::query()->create(['year' => 2024, 'is_active' => true]);
        $category = Category::query()->create([
            'code' => 'ELK', 'name' => 'Elektronik', 'slug' => 'elk-cfg', 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'PC', 'name' => 'PC', 'slug' => 'pc-cfg', 'is_active' => true,
        ]);
        $cond = InventoryConditionMaster::query()->where('code', 'BAIK')->first()
            ?? InventoryConditionMaster::query()->create(['code' => 'BAIK', 'name' => 'Baik', 'is_active' => true]);
        $rf = RoomFunction::query()->where('code', 'KELAS')->first();

        return [
            'campus_id' => $campus->id,
            'building_id' => $building->id,
            'floor_id' => $floor->id,
            'org_unit_id' => $org->id,
            'room_function_id' => $rf?->id,
            'room_id' => $room->id,
            'procurement_type_id' => $proc->id,
            'purchase_year_id' => $year->id,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'inventory_condition_id' => $cond->id,
            'is_active' => 1,
        ];
    }
}
