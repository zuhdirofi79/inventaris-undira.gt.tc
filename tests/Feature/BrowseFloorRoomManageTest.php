<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Services\LocationHierarchyService;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class BrowseFloorRoomManageTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    private function seedFloor(): array
    {
        $campus = Campus::query()->create([
            'code' => 'BRW',
            'name' => 'Kampus Browse',
            'slug' => 'kampus-browse',
            'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'code' => 'GB',
            'name' => 'Gedung Browse',
            'slug' => 'gedung-browse',
            'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id,
            'code' => 'L2',
            'name' => 'Lantai 2',
            'floor_number' => 2,
            'is_active' => true,
        ]);

        return compact('campus', 'building', 'floor');
    }

    public function test_super_admin_can_create_room_via_ajax_from_browse(): void
    {
        $admin = $this->makeSuperAdmin();
        ['floor' => $floor] = $this->seedFloor();

        $response = $this->actingAs($admin)->postJson(route('admin.locations.rooms.store'), [
            'floor_id' => $floor->id,
            'code' => 'BR-201',
            'name' => 'Lab Browse 201',
            'room_type' => 'Lab',
            'capacity' => 30,
            'is_active' => true,
            'notes' => 'Ruangan uji',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('message', 'Ruangan berhasil ditambahkan.');
        $response->assertJsonPath('data.code', 'BR-201');
        $this->assertDatabaseHas('rooms', [
            'floor_id' => $floor->id,
            'code' => 'BR-201',
            'name' => 'Lab Browse 201',
            'notes' => 'Ruangan uji',
        ]);

        $page = $this->actingAs($admin)->get(route('floors.show', $floor));
        $page->assertOk();
        $page->assertSee('Tambah Ruangan');
        $page->assertSee('Lab Browse 201');

        $tree = app(LocationHierarchyService::class)->activeTree($floor->building->campus_id);
        $ids = $tree->first()->buildings->first()->floors->first()->rooms->pluck('code')->all();
        $this->assertContains('BR-201', $ids);

        $ajax = $this->actingAs($admin)->getJson(route('master-data.rooms.by-floor', $floor));
        $ajax->assertOk()->assertJsonFragment(['code' => 'BR-201']);
    }

    public function test_viewer_cannot_manage_rooms(): void
    {
        $viewer = $this->makeViewer();
        ['floor' => $floor] = $this->seedFloor();

        $page = $this->actingAs($viewer)->get(route('floors.show', $floor));
        $page->assertOk();
        $page->assertDontSee('Tambah Ruangan');

        $this->actingAs($viewer)->postJson(route('admin.locations.rooms.store'), [
            'floor_id' => $floor->id,
            'code' => 'X-1',
            'name' => 'Forbidden',
            'room_type' => 'Kelas',
            'capacity' => 10,
            'is_active' => true,
        ])->assertForbidden();
    }

    public function test_delete_room_with_inventory_moves_both_to_trash(): void
    {
        $admin = $this->makeSuperAdmin();
        ['floor' => $floor] = $this->seedFloor();

        $room = Room::query()->create([
            'floor_id' => $floor->id,
            'code' => 'HAS-INV',
            'name' => 'Ada Inventaris',
            'room_type' => 'Kelas',
            'capacity' => 20,
            'is_active' => true,
        ]);

        $category = Category::query()->create([
            'code' => 'FUR',
            'name' => 'Furniture',
            'slug' => 'furniture',
            'is_active' => true,
        ]);

        $item = InventoryItem::query()->create([
            'room_id' => $room->id,
            'category_id' => $category->id,
            'name' => 'Kursi',
            'inventory_number' => 'INV-TEST-001',
            'status' => 'aktif',
            'condition' => 'bagus',
        ]);

        $response = $this->actingAs($admin)->deleteJson(route('admin.locations.rooms.destroy', $room));
        $response->assertOk();
        $this->assertSoftDeleted($room);
        $this->assertSoftDeleted($item);
    }

    public function test_duplicate_and_update_room_via_ajax(): void
    {
        $admin = $this->makeSuperAdmin();
        ['floor' => $floor] = $this->seedFloor();

        $room = Room::query()->create([
            'floor_id' => $floor->id,
            'code' => 'DUP-1',
            'name' => 'Ruang Asli',
            'room_type' => 'Kantor',
            'capacity' => 5,
            'is_active' => true,
        ]);

        $dup = $this->actingAs($admin)->postJson(route('admin.locations.rooms.duplicate', $room));
        $dup->assertCreated();
        $dup->assertJsonPath('data.name', 'Ruang Asli (Salinan)');

        $update = $this->actingAs($admin)->putJson(route('admin.locations.rooms.update', $room), [
            'code' => 'DUP-1',
            'name' => 'Ruang Edited',
            'room_type' => 'Lab',
            'capacity' => 8,
            'is_active' => true,
            'notes' => 'Updated',
        ]);
        $update->assertOk();
        $update->assertJsonPath('message', 'Ruangan berhasil diperbarui.');
        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'name' => 'Ruang Edited', 'capacity' => 8]);
    }
}
