<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\SubCategory;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class InventoryMassLabels30Test extends TestCase
{
    use CreatesAuthenticatedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_mass_labels_30_pdf_from_selected_ids(): void
    {
        $admin = $this->makeAdmin();
        $items = [];
        for ($i = 1; $i <= 3; $i++) {
            $items[] = $this->makeItem([
                'inventory_number' => 'MASS-30-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'barcode' => 'MASS-30-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'name' => 'Kursi Mass '.$i,
            ]);
        }

        $response = $this->actingAs($admin)->post(route('inventory.labels.mass30'), [
            'scope' => 'selected',
            'inventory_ids' => array_map(fn (InventoryItem $i) => $i->id, $items),
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_mass_labels_30_pdf_from_filter_scope(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem([
            'inventory_number' => 'MASS-FILTER-001',
            'barcode' => 'MASS-FILTER-001',
            'name' => 'Meja Filter Mass',
        ]);

        $response = $this->actingAs($admin)->post(route('inventory.labels.mass30'), [
            'scope' => 'filter',
            'campus_id' => $item->room->floor->building->campus_id,
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_mass_labels_30_rejects_empty_selection(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('inventory.labels.mass30'), [
            'scope' => 'selected',
            'inventory_ids' => [],
        ])->assertSessionHasErrors('inventory_ids');
    }

    public function test_mass_labels_paginates_beyond_thirty_items(): void
    {
        $admin = $this->makeAdmin();
        $ids = [];
        for ($i = 1; $i <= 31; $i++) {
            $ids[] = $this->makeItem([
                'inventory_number' => 'MASS-PAGE-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'barcode' => 'MASS-PAGE-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'name' => 'Item Halaman '.$i,
            ])->id;
        }

        $response = $this->actingAs($admin)->post(route('inventory.labels.mass30'), [
            'scope' => 'selected',
            'inventory_ids' => $ids,
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        // DomPDF marks page breaks; content should be substantially larger than a 3-item PDF.
        $this->assertGreaterThan(5000, strlen($response->getContent()));
    }

    public function test_mass_labels_skips_empty_inventory_number_without_failing(): void
    {
        $admin = $this->makeAdmin();
        $valid = $this->makeItem([
            'inventory_number' => 'MASS-SKIP-OK',
            'barcode' => 'MASS-SKIP-OK',
            'name' => 'Valid Item',
        ]);
        $invalid = $this->makeItem([
            'inventory_number' => 'MASS-SKIP-TMP',
            'barcode' => 'MASS-SKIP-TMP',
            'name' => 'Invalid Item',
        ]);
        // Bypass model guards if any — force empty number after create.
        \Illuminate\Support\Facades\DB::table('inventory_items')
            ->where('id', $invalid->id)
            ->update(['inventory_number' => '', 'barcode' => '']);

        $response = $this->actingAs($admin)->post(route('inventory.labels.mass30'), [
            'scope' => 'selected',
            'inventory_ids' => [$valid->id, $invalid->id],
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_label_layout_defaults_to_thirty_per_page(): void
    {
        $service = app(\App\Services\InventoryMassLabelService::class);
        $layout = $service->layout();

        $this->assertSame(3, $layout['columns']);
        $this->assertSame(10, $layout['rows']);
        $this->assertSame(30, $layout['per_page']);
        $this->assertGreaterThanOrEqual(15.0, $layout['qr_size_mm']);
        $this->assertGreaterThanOrEqual(14.0, $layout['logo_width_mm']);
        $this->assertLessThanOrEqual(18.0, $layout['logo_width_mm']);
        $this->assertLessThan($layout['qr_size_mm'], $layout['logo_max_height_mm']);
    }

    public function test_undira_logo_resolves_as_embedded_data_uri(): void
    {
        $logo = app(\App\Services\InventoryMassLabelService::class)->resolveLogo();

        $this->assertNotNull($logo['src']);
        $this->assertNull($logo['error']);
        $this->assertStringEndsWith('logo-undira.png', str_replace('\\', '/', (string) $logo['path']));
        $this->assertStringStartsWith('data:image/png;base64,', $logo['src']);
        $this->assertGreaterThan(100, strlen($logo['src']));
        $this->assertFileExists(public_path('images/logo-undira.png'));
    }

    public function test_items_page_has_mass_label_button_without_changing_individual_print(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('inventory.items'))
            ->assertOk()
            ->assertSee('Cetak Label QR')
            ->assertSee(route('inventory.labels.mass30', [], false), false);

        $item = $this->makeItem([
            'inventory_number' => 'MASS-INDIV-001',
            'barcode' => 'MASS-INDIV-001',
        ]);

        $this->actingAs($admin)
            ->get(route('inventory.label.print', $item))
            ->assertOk()
            ->assertSee('Cetak Label')
            ->assertDontSee('30 Label / A4');
    }

    public function test_legacy_labels_route_still_works(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem([
            'inventory_number' => 'MASS-LEGACY-001',
            'barcode' => 'MASS-LEGACY-001',
        ]);

        $this->actingAs($admin)
            ->post(route('inventory.labels'), [
                'inventory_ids' => [$item->id],
            ])
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeItem(array $overrides = []): InventoryItem
    {
        static $n = 0;
        $n++;
        $suffix = (string) $n;

        $campus = Campus::query()->create([
            'code' => 'M'.$suffix, 'name' => 'Kampus Mass '.$suffix, 'slug' => 'kampus-mass-'.$suffix, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'B'.$suffix, 'name' => 'Gedung '.$suffix, 'slug' => 'gedung-mass-'.$suffix, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1'.$suffix, 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'A101'.$suffix, 'name' => 'Ruang A101', 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'F'.$suffix, 'name' => 'Furniture', 'slug' => 'furniture-mass-'.$suffix, 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'K'.$suffix, 'name' => 'Kursi', 'slug' => 'kursi-mass-'.$suffix, 'is_active' => true,
        ]);

        return InventoryItem::query()->create(array_merge([
            'inventory_number' => 'MASS-DEF-'.$suffix,
            'barcode' => 'MASS-DEF-'.$suffix,
            'qr_code_path' => null,
            'name' => 'Barang Mass',
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
