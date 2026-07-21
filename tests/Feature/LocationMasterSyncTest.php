<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Floor;
use App\Models\Room;
use App\Services\LocationHierarchyService;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class LocationMasterSyncTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_room_created_in_kelola_lokasi_appears_everywhere(): void
    {
        $admin = $this->makeSuperAdmin();

        $campus = Campus::query()->create([
            'code' => 'TST',
            'name' => 'Kampus Test Sync',
            'slug' => 'kampus-test-sync',
            'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'code' => 'GT',
            'name' => 'Gedung Test',
            'slug' => 'gedung-test',
            'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id,
            'code' => 'L9',
            'name' => 'Lantai 9',
            'floor_number' => 9,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.locations.rooms.store'), [
            'floor_id' => $floor->id,
            'code' => 'GT-901',
            'name' => 'Ruang Sync 901',
            'room_type' => 'Lab',
            'capacity' => 40,
            'is_active' => '1',
        ]);

        $response->assertRedirect();
        $room = Room::query()->where('code', 'GT-901')->first();
        $this->assertNotNull($room);
        $this->assertTrue($room->is_active);
        $this->assertSame(40, $room->capacity);

        // Jelajahi Lokasi
        $browse = $this->actingAs($admin)->get(route('floors.show', $floor));
        $browse->assertOk();
        $browse->assertSee('Ruang Sync 901');
        $browse->assertSee('GT-901');
        $browse->assertSee('Lab');
        $browse->assertSee('Kap. 40');

        // Hierarchy service (filters / inventaris tree)
        $tree = app(LocationHierarchyService::class)->activeTree($campus->id);
        $roomIds = $tree->first()
            ->buildings->first()
            ->floors->first()
            ->rooms->pluck('id')
            ->all();
        $this->assertContains($room->id, $roomIds);

        // Konfigurasi Inventaris AJAX
        $ajax = $this->actingAs($admin)->getJson(route('master-data.rooms.by-floor', $floor));
        $ajax->assertOk();
        $ajax->assertJsonFragment(['id' => $room->id, 'code' => 'GT-901', 'name' => 'Ruang Sync 901']);

        // Kampus baru muncul di Jelajahi index
        $index = $this->actingAs($admin)->get(route('campuses.index'));
        $index->assertOk();
        $index->assertSee('Kampus Test Sync');
    }

    public function test_inactive_room_is_hidden_from_consumers(): void
    {
        $admin = $this->makeSuperAdmin();
        $viewer = $this->makeViewer();

        $campus = Campus::query()->create([
            'code' => 'INA', 'name' => 'Kampus Inactive', 'slug' => 'kampus-inactive', 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'BI', 'name' => 'Gedung I', 'slug' => 'gedung-i', 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'OFF-1', 'name' => 'Ruang Off', 'room_type' => 'Kantor', 'capacity' => 1, 'is_active' => false,
        ]);

        // Super Admin (location.manage) dapat melihat untuk dikelola
        $browseAdmin = $this->actingAs($admin)->get(route('floors.show', $floor));
        $browseAdmin->assertOk();
        $browseAdmin->assertSee('Ruang Off');

        // Viewer hanya melihat ruangan aktif
        $browseViewer = $this->actingAs($viewer)->get(route('floors.show', $floor));
        $browseViewer->assertOk();
        $browseViewer->assertDontSee('Ruang Off');

        $ajax = $this->actingAs($admin)->getJson(route('master-data.rooms.by-floor', $floor));
        $ajax->assertOk();
        $ajax->assertJsonMissing(['id' => $room->id]);
    }
}
