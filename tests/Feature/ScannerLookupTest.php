<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class ScannerLookupTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_scanner_lookup_found_redirects_to_inventory_show(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->createInventoryItem();

        $response = $this->actingAs($admin)->postJson(route('scanner.lookup'), [
            'code' => $item->inventory_number,
        ]);

        $response->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('redirect', route('inventory.show', $item));
    }

    public function test_scanner_lookup_not_found_returns_404(): void
    {
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAs($admin)->postJson(route('scanner.lookup'), [
            'code' => 'DOES-NOT-EXIST-999',
        ]);

        $response->assertNotFound()->assertJsonPath('found', false);
    }

    public function test_viewer_cannot_create_inventory(): void
    {
        $viewer = $this->makeViewer();

        $response = $this->actingAs($viewer)->get(route('inventory.create'));

        $response->assertForbidden();
    }

    protected function createInventoryItem(): InventoryItem
    {
        $campus = Campus::query()->create([
            'code' => 'SC', 'name' => 'Scan Campus', 'slug' => 'scan-campus', 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'B', 'name' => 'B1', 'slug' => 'b1', 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'L1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R1', 'name' => 'R1', 'room_type' => 'Lab', 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'ELK', 'name' => 'Elektronik', 'slug' => 'elektronik', 'is_active' => true,
        ]);

        return InventoryItem::query()->create([
            'inventory_number' => 'SC-NA-ELK-000001',
            'barcode' => 'SC-NA-ELK-000001',
            'name' => 'Laptop Scan',
            'category_id' => $category->id,
            'room_id' => $room->id,
            'condition' => 'bagus',
            'status' => 'aktif',
            'quantity' => 1,
            'unit' => 'Unit',
        ]);
    }
}
