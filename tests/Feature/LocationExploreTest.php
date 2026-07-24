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
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class LocationExploreTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_explore_index_shows_campus_cards(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->seedTree();

        $this->actingAs($admin)
            ->get(route('campuses.index'))
            ->assertOk()
            ->assertSee('Jelajahi Lokasi')
            ->assertSee('Kampus → Gedung → Lantai → Ruangan → Kategori → Sub Kategori → Inventaris')
            ->assertSee($ctx['campus']->name)
            ->assertSee('Lihat Lokasi')
            ->assertDontSee('Expand Semua');
    }

    public function test_explore_page_flow_uses_cards_then_room_accordion(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->seedTree();
        $this->makeItem($ctx, [
            'inventory_number' => 'EXP-FLOW-1',
            'name' => 'Meja Flow',
        ]);

        $this->actingAs($admin)
            ->get(route('campuses.show', $ctx['campus']))
            ->assertOk()
            ->assertSee($ctx['building']->name)
            ->assertSee('Semua Kampus')
            ->assertSee('Cari Gedung');

        $this->actingAs($admin)
            ->get(route('buildings.show', $ctx['building']))
            ->assertOk()
            ->assertSee($ctx['floor']->name)
            ->assertSee('Cari Lantai');

        $this->actingAs($admin)
            ->get(route('floors.show', $ctx['floor']))
            ->assertOk()
            ->assertSee($ctx['room']->name)
            ->assertSee('Cari Ruangan');

        $this->actingAs($admin)
            ->get(route('rooms.show', $ctx['room']))
            ->assertOk()
            ->assertSee('Isi Ruangan')
            ->assertSee($ctx['category']->name)
            ->assertSee($ctx['sub']->name)
            ->assertSee('Cari Inventaris')
            ->assertDontSee('Meja Flow');
    }

    public function test_explore_lazy_endpoints_and_items_actions(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->seedTree();
        $item = $this->makeItem($ctx, [
            'inventory_number' => 'EXP-0001',
            'name' => 'Meja Explore',
            'serial_number' => 'SN-EXP-1',
            'responsible_person' => 'PIC Lab',
        ]);

        $this->actingAs($admin)
            ->getJson(route('locations.explore.stats'))
            ->assertOk()
            ->assertJsonStructure(['total', 'aktif', 'dipinjam', 'maintenance', 'rusak', 'non_aktif']);

        $this->actingAs($admin)
            ->getJson(route('locations.explore.buildings', $ctx['campus']))
            ->assertOk()
            ->assertJsonFragment(['id' => $ctx['building']->id, 'unit_count' => 1]);

        $this->actingAs($admin)
            ->getJson(route('locations.explore.floors', $ctx['building']))
            ->assertOk()
            ->assertJsonFragment(['id' => $ctx['floor']->id, 'unit_count' => 1]);

        $this->actingAs($admin)
            ->getJson(route('locations.explore.rooms', $ctx['floor']))
            ->assertOk()
            ->assertJsonFragment(['id' => $ctx['room']->id, 'name' => $ctx['room']->name, 'unit_count' => 1]);

        $this->actingAs($admin)
            ->getJson(route('locations.explore.categories', $ctx['room']))
            ->assertOk()
            ->assertJsonFragment(['id' => $ctx['category']->id, 'name' => $ctx['category']->name]);

        $this->actingAs($admin)
            ->getJson(route('locations.explore.sub-categories', [$ctx['room'], $ctx['category']]))
            ->assertOk()
            ->assertJsonFragment(['id' => $ctx['sub']->id, 'name' => $ctx['sub']->name]);

        $items = $this->actingAs($admin)
            ->getJson(route('locations.explore.items', [
                $ctx['room'],
                $ctx['category'],
                $ctx['sub']->id,
            ]))
            ->assertOk()
            ->json();

        $this->assertSame(1, $items['meta']['total']);
        $this->assertSame('EXP-0001', $items['rows'][0]['inventory_number']);
        $this->assertSame('Meja Explore', $items['rows'][0]['name']);
        $this->assertSame('PIC Lab', $items['rows'][0]['pic']);
        $this->assertSame('active', $items['rows'][0]['status_tone']);
        $this->assertSame('good', $items['rows'][0]['condition_tone']);
        $this->assertArrayHasKey('photo_url', $items['rows'][0]);
        $this->assertArrayHasKey('condition_label', $items['rows'][0]);
        $this->assertArrayHasKey('status_label', $items['rows'][0]);
        $this->assertNotEmpty($items['rows'][0]['urls']['show']);
        $this->assertNotEmpty($items['rows'][0]['urls']['qr']);
        $this->assertStringContainsString((string) $item->id, $items['rows'][0]['urls']['show']);
    }

    public function test_explore_search_finds_by_name_and_serial(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->seedTree('S');
        $this->makeItem($ctx, [
            'inventory_number' => 'EXP-SRCH-9',
            'name' => 'Proyektor Cari',
            'serial_number' => 'SERIAL-XYZ',
        ]);

        $this->actingAs($admin)
            ->getJson(route('locations.explore.search', ['q' => 'Proyektor']))
            ->assertOk()
            ->assertJsonFragment(['inventory_number' => 'EXP-SRCH-9']);

        $this->actingAs($admin)
            ->getJson(route('locations.explore.search', ['q' => 'SERIAL-XYZ']))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Proyektor Cari']);
    }

    /**
     * @return array{campus:Campus,building:Building,floor:Floor,room:Room,category:Category,sub:SubCategory}
     */
    protected function seedTree(string $suffix = 'E'): array
    {
        $campus = Campus::query()->create([
            'code' => 'CX'.$suffix, 'name' => 'Kampus Explore '.$suffix, 'slug' => 'kampus-explore-'.$suffix, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'BX'.$suffix, 'name' => 'Gedung Explore '.$suffix, 'slug' => 'gedung-explore-'.$suffix, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'LX'.$suffix, 'name' => 'Lantai Explore '.$suffix, 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'RX'.$suffix, 'name' => 'Ruang Explore '.$suffix, 'room_type' => 'Kelas', 'capacity' => 20, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'FUR'.$suffix, 'name' => 'Furniture Explore '.$suffix, 'slug' => 'furniture-explore-'.$suffix, 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'MJ'.$suffix, 'name' => 'Meja Explore '.$suffix, 'slug' => 'meja-explore-'.$suffix, 'is_active' => true,
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
            'inventory_number' => 'INV-EX-'.uniqid(),
            'barcode' => 'BC-EX-'.uniqid(),
            'name' => 'Barang Explore',
            'category_id' => $ctx['category']->id,
            'sub_category_id' => $ctx['sub']->id,
            'room_id' => $ctx['room']->id,
            'condition' => InventoryCondition::Bagus,
            'status' => InventoryStatus::Aktif,
            'quantity' => 1,
            'unit' => 'unit',
        ], $overrides));
    }
}
