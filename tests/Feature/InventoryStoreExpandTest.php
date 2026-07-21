<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryConditionMaster;
use App\Models\InventoryItem;
use App\Models\OrgUnit;
use App\Models\ProcurementType;
use App\Models\PurchaseYear;
use App\Models\Room;
use App\Models\Setting;
use App\Models\SubCategory;
use Database\Seeders\InventorySettingSeeder;
use Database\Seeders\MasterLookupSeeder;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class InventoryStoreExpandTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_konfigurasi_expands_quantity_into_one_record_per_unit(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(MasterLookupSeeder::class);
        $this->seed(InventorySettingSeeder::class);
        Setting::set('inventory.number_format', '{sequence}', 'inventory');
        Setting::set('inventory.sequence_padding', '6', 'inventory');

        $payload = $this->validAssetPayload();
        $roomId = $payload['room_id'];

        $response = $this->actingAs($admin)->post(route('master-configurations.store'), $payload);

        $response->assertRedirect(route('rooms.show', $roomId));
        $this->assertSame(3, InventoryItem::query()->where('name', 'Kursi Uji Phase5')->count());
        $this->assertSame(3, InventoryItem::query()->where('name', 'Kursi Uji Phase5')->where('quantity', 1)->count());
        $this->assertSame(3, InventoryItem::query()->where('name', 'Kursi Uji Phase5')->where('room_id', $roomId)->count());
    }

    public function test_legacy_inventory_store_is_blocked(): void
    {
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAs($admin)->post(route('inventory.store'), [
            'name' => 'Should Not Create',
            'category_id' => 1,
            'room_id' => 1,
            'condition' => 'bagus',
            'status' => 'aktif',
            'quantity' => 1,
        ]);

        $response->assertRedirect(route('categories.index', ['tab' => 'konfigurasi', 'create' => 1]));
        $this->assertSame(0, InventoryItem::query()->where('name', 'Should Not Create')->count());
    }

    /**
     * @return array<string, mixed>
     */
    protected function validAssetPayload(): array
    {
        $campus = Campus::query()->create([
            'code' => 'TST', 'name' => 'Kampus Test', 'slug' => 'kampus-test-exp', 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'A', 'name' => 'Gedung A', 'slug' => 'gedung-a-exp', 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R101', 'name' => 'Ruang 101', 'room_type' => 'Kelas', 'is_active' => true,
        ]);
        $org = OrgUnit::query()->where('code', 'PPPA')->first()
            ?? OrgUnit::query()->create(['code' => 'PPPA', 'name' => 'PPPA', 'is_active' => true]);
        $proc = ProcurementType::query()->where('is_active', true)->first()
            ?? ProcurementType::query()->create(['code' => 'UNDIRA', 'name' => 'UNDIRA', 'is_active' => true]);
        $year = PurchaseYear::query()->where('year', 2024)->first()
            ?? PurchaseYear::query()->create(['year' => 2024, 'is_active' => true]);
        $category = Category::query()->create([
            'code' => 'FUR', 'name' => 'Furniture', 'slug' => 'furniture-exp', 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'KRS', 'name' => 'Kursi', 'slug' => 'kursi-exp', 'is_active' => true,
        ]);
        $cond = InventoryConditionMaster::query()->where('code', 'BAIK')->first()
            ?? InventoryConditionMaster::query()->create(['code' => 'BAIK', 'name' => 'Baik', 'is_active' => true]);

        return [
            'campus_id' => $campus->id,
            'building_id' => $building->id,
            'floor_id' => $floor->id,
            'org_unit_id' => $org->id,
            'room_id' => $room->id,
            'procurement_type_id' => $proc->id,
            'purchase_year_id' => $year->id,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'inventory_condition_id' => $cond->id,
            'is_active' => 1,
            'create_assets' => 1,
            'asset_name' => 'Kursi Uji Phase5',
            'quantity' => 3,
            'unit' => 'Unit',
        ];
    }
}
