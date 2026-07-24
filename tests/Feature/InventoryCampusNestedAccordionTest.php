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

class InventoryCampusNestedAccordionTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_campus_page_renders_collapsed_buildings_without_embedding_units(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->seedTree();
        $this->makeItem($ctx, [
            'inventory_number' => 'NEST-UNIT-001',
            'name' => 'Proyektor Nested',
        ]);

        $html = $this->actingAs($admin)
            ->get(route('inventory.campus', $ctx['campus']))
            ->assertOk()
            ->assertSee('Expand Semua')
            ->assertSee('Collapse Semua')
            ->assertSee('Kategori → Sub Kategori')
            ->assertSee('inventory-nested-tree', false)
            ->assertSee($ctx['building']->name)
            ->getContent();

        $this->assertStringNotContainsString('NEST-UNIT-001', $html);
        $this->assertStringNotContainsString('Proyektor Nested', $html);
        $this->assertStringContainsString('data-level="building"', $html);
    }

    public function test_lazy_hierarchy_loads_categories_subcategories_then_items(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->seedTree();
        $item = $this->makeItem($ctx, [
            'inventory_number' => 'NEST-UNIT-002',
            'name' => 'Kursi Nested',
        ]);

        $this->actingAs($admin)
            ->getJson(route('inventory.campus.floors', [$ctx['campus'], $ctx['building']]))
            ->assertOk()
            ->assertJsonFragment(['id' => $ctx['floor']->id, 'unit_count' => 1]);

        $this->actingAs($admin)
            ->getJson(route('inventory.campus.rooms', [$ctx['campus'], $ctx['floor']]))
            ->assertOk()
            ->assertJsonFragment(['id' => $ctx['room']->id, 'unit_count' => 1]);

        $this->actingAs($admin)
            ->getJson(route('inventory.campus.categories', [$ctx['campus'], $ctx['room']]))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $ctx['category']->id,
                'name' => $ctx['category']->name,
                'unit_count' => 1,
            ]);

        $this->actingAs($admin)
            ->getJson(route('inventory.campus.sub-categories', [$ctx['campus'], $ctx['room'], $ctx['category']]))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $ctx['sub']->id,
                'name' => $ctx['sub']->name,
                'unit_count' => 1,
            ]);

        // Opening room must NOT return inventory HTML rows.
        $this->actingAs($admin)
            ->getJson(route('inventory.campus.categories', [$ctx['campus'], $ctx['room']]))
            ->assertOk()
            ->assertJsonMissing(['html' => true])
            ->assertJsonMissing(['name' => 'Kursi Nested']);

        $items = $this->actingAs($admin)
            ->getJson(route('inventory.campus.sub-category-items', [
                $ctx['campus'], $ctx['room'], $ctx['category'], $ctx['sub']->id,
            ]))
            ->assertOk()
            ->json();

        $this->assertSame(1, $items['unit_count']);
        $this->assertStringContainsString('NEST-UNIT-002', $items['html']);
        $this->assertStringContainsString('Kursi Nested', $items['html']);
        $this->assertStringContainsString(route('inventory.show', $item, false), $items['html']);
    }

    public function test_uncategorized_subcategory_bucket_and_wrong_campus_404(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->seedTree('U');
        $this->makeItem($ctx, [
            'inventory_number' => 'NEST-NOSUB-1',
            'name' => 'Tanpa Sub',
            'sub_category_id' => null,
        ]);

        $this->actingAs($admin)
            ->getJson(route('inventory.campus.sub-categories', [$ctx['campus'], $ctx['room'], $ctx['category']]))
            ->assertOk()
            ->assertJsonFragment(['id' => 0, 'name' => 'Tanpa Sub Kategori', 'unit_count' => 1]);

        $this->actingAs($admin)
            ->getJson(route('inventory.campus.sub-category-items', [
                $ctx['campus'], $ctx['room'], $ctx['category'], 0,
            ]))
            ->assertOk()
            ->assertJsonPath('unit_count', 1);

        $ctxB = $this->seedTree('B');
        $this->actingAs($admin)
            ->getJson(route('inventory.campus.floors', [$ctx['campus'], $ctxB['building']]))
            ->assertNotFound();
    }

    /**
     * @return array{campus:Campus,building:Building,floor:Floor,room:Room,category:Category,sub:SubCategory}
     */
    protected function seedTree(string $suffix = 'N'): array
    {
        $campus = Campus::query()->create([
            'code' => 'C'.$suffix,
            'name' => 'Kampus '.$suffix,
            'slug' => 'kampus-'.$suffix.'-nested',
            'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'code' => 'B'.$suffix,
            'name' => 'Gedung '.$suffix,
            'slug' => 'gedung-'.$suffix.'-nested',
            'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id,
            'code' => 'L1'.$suffix,
            'name' => 'Lantai 1 '.$suffix,
            'floor_number' => 1,
            'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id,
            'code' => 'A101'.$suffix,
            'name' => 'Kelas A101 '.$suffix,
            'room_type' => 'Kelas',
            'capacity' => 30,
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'FUR'.$suffix,
            'name' => 'Furniture '.$suffix,
            'slug' => 'furniture-'.$suffix.'-nested',
            'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id,
            'code' => 'KRS'.$suffix,
            'name' => 'Kursi '.$suffix,
            'slug' => 'kursi-'.$suffix.'-nested',
            'is_active' => true,
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
            'inventory_number' => 'INV-N-'.uniqid(),
            'barcode' => 'BC-N-'.uniqid(),
            'name' => 'Barang Nested',
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
