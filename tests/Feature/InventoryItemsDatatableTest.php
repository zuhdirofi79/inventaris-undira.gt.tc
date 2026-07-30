<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\SubCategory;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class InventoryItemsDatatableTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_datatable_ajax_returns_valid_json_payload(): void
    {
        $admin = $this->makeAdmin();
        $this->seedItem('DT-AJAX-001');

        $response = $this->actingAs($admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('inventory.items', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'order' => [['column' => 1, 'dir' => 'asc']],
                'search' => ['value' => '', 'regex' => 'false'],
            ]));

        $response->assertOk();
        $response->assertJsonStructure([
            'draw',
            'recordsTotal',
            'recordsFiltered',
            'data',
        ]);
        $this->assertIsArray($response->json('data'));
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

        $response = $this->actingAs($admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('inventory.items', [
                'draw' => 3,
                'start' => 0,
                'length' => 10,
                'order' => [['column' => 1, 'dir' => 'asc']],
                'search' => ['value' => 'Kursi Lab', 'regex' => 'false'],
            ]));

        $response->assertOk();
        $response->assertJsonPath('draw', 3);
        $this->assertGreaterThanOrEqual(1, (int) $response->json('recordsFiltered'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function seedItem(string $number, array $overrides = []): InventoryItem
    {
        static $n = 0;
        $n++;
        $suffix = (string) $n;

        $campus = Campus::query()->create([
            'code' => 'D'.$suffix, 'name' => 'Kampus DT '.$suffix, 'slug' => 'kampus-dt-'.$suffix, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'B'.$suffix, 'name' => 'Gedung '.$suffix, 'slug' => 'gedung-dt-'.$suffix, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1'.$suffix, 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R101'.$suffix, 'name' => 'Ruang 101', 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'C'.$suffix, 'name' => 'Cat '.$suffix, 'slug' => 'cat-dt-'.$suffix, 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'S'.$suffix, 'name' => 'Sub '.$suffix, 'slug' => 'sub-dt-'.$suffix, 'is_active' => true,
        ]);

        return InventoryItem::query()->create(array_merge([
            'inventory_number' => $number,
            'barcode' => $number,
            'name' => 'Item '.$number,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'condition' => 'bagus',
            'status' => 'aktif',
            'room_id' => $room->id,
            'price' => 100000,
            'quantity' => 1,
            'unit' => 'Unit',
        ], $overrides));
    }
}
