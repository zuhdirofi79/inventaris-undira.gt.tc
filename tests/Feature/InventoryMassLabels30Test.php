<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\SubCategory;
use App\Services\InventoryMassLabelService;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_mass_labels_paginates_beyond_forty_five_items(): void
    {
        $admin = $this->makeAdmin();
        $ids = [];
        for ($i = 1; $i <= 46; $i++) {
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

    public function test_label_layout_defaults_to_forty_five_per_page(): void
    {
        $service = app(\App\Services\InventoryMassLabelService::class);
        $layout = $service->layout();

        $this->assertSame(5, $layout['columns']);
        $this->assertSame(9, $layout['rows']);
        $this->assertSame(45, $layout['per_page']);
        $this->assertGreaterThanOrEqual(12.0, $layout['qr_size_mm']);
        $this->assertLessThanOrEqual(15.0, $layout['qr_size_mm']);
        $this->assertGreaterThanOrEqual(7.0, $layout['logo_width_mm']);
        $this->assertLessThanOrEqual(12.0, $layout['logo_width_mm']);
        $this->assertLessThan($layout['qr_size_mm'], $layout['logo_max_height_mm']);
        $this->assertLessThanOrEqual(4.0, $layout['logo_max_height_mm']);
        $this->assertGreaterThanOrEqual(1.2, $layout['gap_x_mm']);
        $this->assertGreaterThanOrEqual(1.2, $layout['gap_y_mm']);
        $this->assertLessThanOrEqual(4.0, $layout['gap_x_mm']);
        $this->assertLessThanOrEqual(4.0, $layout['gap_y_mm']);
        // Compact card with cutting gaps (derived from A4 5×9).
        $this->assertTrue($layout['label_width_mm'] > 30.0 && $layout['label_width_mm'] < 40.0);
        $this->assertTrue($layout['label_height_mm'] > 24.0 && $layout['label_height_mm'] < 33.0);
        $this->assertTrue($layout['label_width_mm'] >= $layout['label_height_mm']);
        // 9 rows + gaps + margins must fit A4 portrait.
        $totalH = (2 * $layout['page_margin_mm'])
            + ($layout['label_height_mm'] * $layout['rows'])
            + ($layout['gap_y_mm'] * ($layout['rows'] - 1));
        $this->assertLessThanOrEqual(297.0, $totalH + 1.0);
        // Row pitch tiles usable A4 area (minus DomPDF slack).
        $usableH = 297.0 - (2 * $layout['page_margin_mm']) - 1.2;
        $this->assertEqualsWithDelta($usableH, $layout['row_pitch_mm'] * $layout['rows'], 0.08);
        $this->assertEqualsWithDelta(
            $layout['row_pitch_mm'],
            $layout['label_height_mm'] + $layout['gap_y_mm'],
            0.08
        );
        $this->assertLessThanOrEqual(1.5, $layout['label_padding_bottom_mm']);
        // Content budget: QR + number row + pads must fit inside label height.
        $contentBudget = $layout['qr_size_mm']
            + $layout['number_row_mm']
            + $layout['label_padding_mm']
            + $layout['label_padding_bottom_mm'];
        $this->assertLessThanOrEqual($layout['label_height_mm'] + 0.01, $contentBudget);
    }

    public function test_name_and_inventory_number_typography_are_dynamic(): void
    {
        $service = app(\App\Services\InventoryMassLabelService::class);

        $short = $service->nameTypography('Meja Dosen');
        $long = $service->nameTypography('Mesin Pendingin Ruangan Split Wall Mounted Inverter');
        $this->assertGreaterThan($long['pt'], $short['pt']);
        $this->assertStringNotContainsString('...', $long['text']);

        $number = $service->inventoryNumberTypography('CB-UNDIRA-26-FUR-KR-000001');
        $this->assertGreaterThanOrEqual(4.8, $number['pt']);
        $this->assertFalse($number['edge_case']);
        $this->assertSame(mb_strlen('CB-UNDIRA-26-FUR-KR-000001'), 26);
    }

    #[DataProvider('massLabelPageCountProvider')]
    public function test_mass_labels_pdf_page_count_matches_chunk_size(int $count, int $expectedPages): void
    {
        $service = app(InventoryMassLabelService::class);
        $qr = $service->qrPngDataUri('CBB-UNDIRA-26-ELI-000001', 180);
        $this->assertNotNull($qr);

        $labels = collect(range(1, $count))->map(function (int $i) use ($qr, $service) {
            $name = ($i % 5 === 0)
                ? 'Mesin Pendingin Ruangan Split Wall Mounted Inverter '.$i
                : 'Monitor 24 Inch '.$i;
            $nameMeta = $service->nameTypography($name);
            $number = 'CBB-UNDIRA-26-ELI-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT);
            $numberMeta = $service->inventoryNumberTypography($number);

            return (object) [
                'inventory_number' => $number,
                'name' => $nameMeta['text'],
                'name_pt' => $nameMeta['pt'],
                'name_max_height_pt' => $nameMeta['max_height_pt'],
                'category' => 'Elektronik',
                'category_pt' => 7.0,
                'room' => 'Ruang A101',
                'room_pt' => 7.0,
                'number_pt' => $numberMeta['pt'],
                'number_letter_spacing' => $numberMeta['letter_spacing'],
                'location_lines' => ['Ruang A101'],
                'qr_src' => $qr,
            ];
        });

        $pdf = $service->renderPdfBinary($labels);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame(
            $expectedPages,
            $this->countPdfPages($pdf),
            "Expected {$expectedPages} page(s) for {$count} label(s)."
        );
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    public static function massLabelPageCountProvider(): array
    {
        return [
            '1 item' => [1, 1],
            '5 items' => [5, 1],
            '44 items' => [44, 1],
            '45 items' => [45, 1],
            '46 items' => [46, 2],
            '90 items' => [90, 2],
            '91 items' => [91, 3],
            '100 items' => [100, 3],
        ];
    }

    protected function countPdfPages(string $pdfBinary): int
    {
        // Prefer explicit /Type /Page dictionaries (exclude /Pages).
        if (preg_match_all('/\/Type\s*\/Page(?!s)\b/', $pdfBinary, $m)) {
            return count($m[0]);
        }

        if (preg_match('/\/Count\s+(\d+)/', $pdfBinary, $m)) {
            return (int) $m[1];
        }

        $this->fail('Unable to determine PDF page count from binary.');
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

        $html = $this->actingAs($admin)
            ->get(route('inventory.items'))
            ->assertOk()
            ->assertSee('Cetak Label QR')
            ->assertSee('45 Label / A4')
            ->assertSee('5 kolom × 9 baris')
            ->assertDontSee('30 Label / A4')
            ->assertSee('id="massLabelsModal"', false)
            ->assertSee(route('inventory.labels.mass30', [], false), false)
            ->getContent();

        // Modal must be outside .content-wrapper (stacking context) via @stack('modals').
        $contentPos = strpos($html, 'content-wrapper');
        $modalPos = strpos($html, 'id="massLabelsModal"');
        $this->assertNotFalse($contentPos);
        $this->assertNotFalse($modalPos);
        $this->assertGreaterThan(
            $contentPos,
            $modalPos,
            'massLabelsModal should render after content-wrapper (body-level @stack(\'modals\'))'
        );

        $item = $this->makeItem([
            'inventory_number' => 'MASS-INDIV-001',
            'barcode' => 'MASS-INDIV-001',
        ]);

        $this->actingAs($admin)
            ->get(route('inventory.label.print', $item))
            ->assertOk()
            ->assertSee('Cetak Label')
            ->assertDontSee('45 Label / A4');
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
