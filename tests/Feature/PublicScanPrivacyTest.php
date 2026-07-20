<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use Tests\TestCase;

class PublicScanPrivacyTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_guest_public_scan_hides_serial_number(): void
    {
        $item = $this->createItemWithSerial();

        $response = $this->get(route('inventory.scan', $item->inventory_number));

        $response->assertOk();
        $response->assertSee('Login untuk melihat');
        $response->assertDontSee('SN-SECRET-999');
    }

    protected function createItemWithSerial(): InventoryItem
    {
        $campus = Campus::query()->create([
            'code' => 'PS', 'name' => 'Public Scan', 'slug' => 'public-scan', 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'A', 'name' => 'A', 'slug' => 'a', 'is_active' => true,
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
            'inventory_number' => 'PS-NA-ELK-000001',
            'barcode' => 'PS-NA-ELK-000001',
            'name' => 'Device Privacy',
            'category_id' => $category->id,
            'room_id' => $room->id,
            'serial_number' => 'SN-SECRET-999',
            'condition' => 'bagus',
            'status' => 'aktif',
            'quantity' => 1,
            'unit' => 'Unit',
        ]);
    }
}
