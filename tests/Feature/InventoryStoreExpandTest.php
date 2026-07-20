<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\OrgUnit;
use App\Models\Room;
use App\Models\Setting;
use App\Models\SubCategory;
use Database\Seeders\InventorySettingSeeder;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class InventoryStoreExpandTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_store_expands_quantity_into_one_record_per_unit(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(InventorySettingSeeder::class);

        [$room, $category, $subCategory, $orgUnit] = $this->seedLocationMasters();

        $response = $this->actingAs($admin)->post(route('inventory.store'), [
            'name' => 'Kursi Uji Phase5',
            'category_id' => $category->id,
            'sub_category_id' => $subCategory->id,
            'org_unit_id' => $orgUnit->id,
            'room_id' => $room->id,
            'condition' => 'bagus',
            'status' => 'aktif',
            'quantity' => 3,
            'unit' => 'Unit',
        ]);

        $response->assertRedirect();
        $this->assertSame(3, InventoryItem::query()->where('name', 'Kursi Uji Phase5')->count());
        $this->assertSame(3, InventoryItem::query()->where('name', 'Kursi Uji Phase5')->where('quantity', 1)->count());
    }

    /**
     * @return array{0: Room, 1: Category, 2: SubCategory, 3: OrgUnit}
     */
    protected function seedLocationMasters(): array
    {
        Setting::set('inventory.number_format', '{campus}-{org_unit}-{sub_category}-{sequence}', 'inventory');
        Setting::set('inventory.sequence_padding', '6', 'inventory');

        $campus = Campus::query()->create([
            'code' => 'TST',
            'name' => 'Kampus Test',
            'slug' => 'kampus-test',
            'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'code' => 'A',
            'name' => 'Gedung A',
            'slug' => 'gedung-a',
            'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id,
            'code' => 'L1',
            'name' => 'Lantai 1',
            'floor_number' => 1,
            'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id,
            'code' => 'R101',
            'name' => 'Ruang 101',
            'room_type' => 'Kelas',
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'FUR',
            'name' => 'Furniture',
            'slug' => 'furniture',
            'is_active' => true,
        ]);
        $subCategory = SubCategory::query()->create([
            'category_id' => $category->id,
            'code' => 'CHR',
            'name' => 'Chair',
            'slug' => 'chair',
            'is_active' => true,
        ]);
        $orgUnit = OrgUnit::query()->create([
            'code' => 'PPPA',
            'name' => 'PPPA',
            'is_active' => true,
        ]);

        return [$room, $category, $subCategory, $orgUnit];
    }
}
