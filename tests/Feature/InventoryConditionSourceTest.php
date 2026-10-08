<?php

namespace Tests\Feature;

use App\Enums\InventoryStatus;
use App\Imports\InventoryImport;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryConditionMaster;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\SubCategory;
use App\Support\InventoryConditionCatalog;
use Illuminate\Support\Collection;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class InventoryConditionSourceTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_edit_and_filter_use_master_conditions_and_reject_unknown_values(): void
    {
        $admin = $this->makeAdmin();
        $this->seedConditions();
        $item = $this->makeItem('BAIK');

        $edit = $this->actingAs($admin)->get(route('inventory.edit', $item))->assertOk()->getContent();
        $this->assertStringContainsString('value="BAIK"', $edit);
        $this->assertStringContainsString('value="LAYAK"', $edit);
        $this->assertStringContainsString('value="RUSAK"', $edit);
        $this->assertStringContainsString('>Baik<', $edit);
        $this->assertStringNotContainsString('value="bagus"', $edit);
        $this->assertStringNotContainsString('value="baru"', $edit);
        $this->assertStringNotContainsString('value="cukup"', $edit);

        $itemsPage = $this->actingAs($admin)->get(route('inventory.items'))->assertOk()->getContent();
        $this->assertStringContainsString('value="BAIK"', $itemsPage);
        $this->assertStringContainsString('value="LAYAK"', $itemsPage);
        $this->assertStringNotContainsString('value="bagus"', $itemsPage);

        $this->actingAs($admin)
            ->put(route('inventory.update', $item), [
                'condition' => 'Baik',
                'status' => InventoryStatus::Aktif->value,
            ])
            ->assertRedirect();

        $this->assertSame('BAIK', $item->fresh()->condition->value);
        $this->assertSame('Baik', $item->fresh()->condition->label());

        $this->actingAs($admin)
            ->from(route('inventory.edit', $item))
            ->put(route('inventory.update', $item), [
                'condition' => 'Bagus',
                'status' => InventoryStatus::Aktif->value,
            ])
            ->assertRedirect(route('inventory.edit', $item))
            ->assertSessionHasErrors('condition');

        $this->assertSame('BAIK', $item->fresh()->condition->value);
    }

    public function test_new_master_condition_appears_and_inactive_stays_on_existing_items(): void
    {
        $admin = $this->makeAdmin();
        $this->seedConditions();
        $current = $this->makeItem('LAYAK');

        InventoryConditionMaster::query()->where('code', 'LAYAK')->update(['is_active' => false]);
        InventoryConditionMaster::query()->create([
            'code' => 'SANGAT_BAIK',
            'name' => 'Sangat Baik',
            'is_active' => true,
            'sort_order' => 4,
        ]);
        InventoryConditionCatalog::flush();

        $legacyEdit = $this->actingAs($admin)->get(route('inventory.edit', $current))->assertOk()->getContent();
        $this->assertStringContainsString('value="LAYAK"', $legacyEdit);
        $this->assertStringContainsString('value="SANGAT_BAIK"', $legacyEdit);

        $fresh = $this->makeItem('BAIK');
        $freshEdit = $this->actingAs($admin)->get(route('inventory.edit', $fresh))->assertOk()->getContent();
        $this->assertStringNotContainsString('value="LAYAK"', $freshEdit);
        $this->assertStringContainsString('value="SANGAT_BAIK"', $freshEdit);
        $this->assertSame('Layak', $current->fresh()->condition->label());
    }

    public function test_import_accepts_master_name_and_rejects_legacy_label(): void
    {
        $this->seedConditions();

        $rejected = new InventoryImport(true);
        $rejected->collection(new Collection([$this->importRow('Bagus')]));
        $this->assertNotEmpty($rejected->errors);
        $this->assertStringContainsString('Master Data Kondisi Aset', $rejected->errors[0]['errors'][0]);

        $accepted = new InventoryImport(true);
        $accepted->collection(new Collection([$this->importRow('Baik')]));
        $messages = collect($accepted->errors)->flatMap(fn (array $row) => $row['errors'])->implode(' ');
        $this->assertStringNotContainsString('Master Data Kondisi Aset', $messages);
        $this->assertStringContainsString('tidak ditemukan', $messages);
    }

    private function seedConditions(): void
    {
        foreach ([
            ['BAIK', 'Baik', 1],
            ['LAYAK', 'Layak', 2],
            ['RUSAK', 'Rusak', 3],
        ] as [$code, $name, $order]) {
            InventoryConditionMaster::query()->create([
                'code' => $code,
                'name' => $name,
                'is_active' => true,
                'sort_order' => $order,
            ]);
        }
        InventoryConditionCatalog::flush();
    }

    private function makeItem(string $condition): InventoryItem
    {
        $suffix = uniqid();
        $campus = Campus::query()->create([
            'code' => 'C'.$suffix, 'name' => 'Kampus', 'slug' => 'k-'.$suffix, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'B'.$suffix, 'name' => 'Gedung', 'slug' => 'g-'.$suffix, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'F'.$suffix, 'name' => 'Lantai', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R'.$suffix, 'name' => 'Ruang', 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'K'.$suffix, 'name' => 'Kategori', 'slug' => 'c-'.$suffix, 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'S'.$suffix, 'name' => 'Sub', 'slug' => 's-'.$suffix, 'is_active' => true,
        ]);

        return InventoryItem::query()->create([
            'inventory_number' => 'INV-'.$suffix,
            'name' => 'Pulpen '.$suffix,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'room_id' => $room->id,
            'condition' => $condition,
            'status' => InventoryStatus::Aktif->value,
            'quantity' => 1,
            'unit' => 'Unit',
        ]);
    }

    private function importRow(string $condition): object
    {
        return new class($condition)
        {
            public function __construct(private string $condition) {}

            public function toArray(): array
            {
                return [
                    'name' => 'Pulpen Import',
                    'category_code' => 'MISSING',
                    'sub_category_code' => 'MISSING',
                    'procurement_type_code' => 'MISSING',
                    'room_code' => 'MISSING',
                    'condition' => $this->condition,
                    'status' => 'aktif',
                    'purchase_year' => 2026,
                ];
            }
        };
    }
}
