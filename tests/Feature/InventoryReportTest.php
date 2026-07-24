<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\SubCategory;
use App\Services\InventoryReportService;
use Illuminate\Http\Request;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class InventoryReportTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_report_index_loads_for_viewer(): void
    {
        $viewer = $this->makeViewer();

        $this->actingAs($viewer)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Dashboard Laporan')
            ->assertSee('Inventaris Lengkap')
            ->assertSee('Rekapitulasi Inventaris');
    }

    public function test_report_builds_hierarchy_from_inventory_items(): void
    {
        $admin = $this->makeSuperAdmin();
        [$campus, $room, $category, $sub] = $this->seedLocationWithCategory();

        InventoryItem::query()->create([
            'name' => 'Meja Dosen Report',
            'inventory_number' => 'RPT-001',
            'barcode' => 'RPT-001',
            'room_id' => $room->id,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'condition' => 'bagus',
            'status' => 'aktif',
            'price' => 100000,
            'quantity' => 1,
        ]);

        InventoryItem::query()->create([
            'name' => 'Kursi Mahasiswa Report',
            'inventory_number' => 'RPT-002',
            'barcode' => 'RPT-002',
            'room_id' => $room->id,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'condition' => 'bagus',
            'status' => 'aktif',
            'price' => 50000,
            'quantity' => 1,
        ]);

        $request = Request::create('/reports/generate', 'POST', [
            'format' => 'view',
            'campus_id' => $campus->id,
        ]);
        $request->setUserResolver(fn () => $admin);

        $report = app(InventoryReportService::class)->build($request);

        $this->assertSame(2, $report['summary']['total_items']);
        $this->assertArrayNotHasKey('total_value', $report['summary']);
        $this->assertCount(1, $report['tree']);
        $this->assertSame($campus->name, $report['tree'][0]['label']);
        $this->assertSame(2, $report['tree'][0]['totals']['count']);
        $this->assertArrayNotHasKey('value', $report['tree'][0]['totals']);

        $roomNode = $report['tree'][0]['buildings'][0]['floors'][0]['rooms'][0];
        $this->assertSame(2, $roomNode['totals']['count']);
        $this->assertSame('Furniture', $roomNode['categories'][0]['label']);
        $this->assertSame('Meja', $roomNode['categories'][0]['sub_categories'][0]['label']);
        $this->assertCount(2, $roomNode['categories'][0]['sub_categories'][0]['items']);
    }

    public function test_report_view_and_pdf_use_same_source(): void
    {
        $admin = $this->makeSuperAdmin();
        [$campus, $room, $category, $sub] = $this->seedLocationWithCategory();

        InventoryItem::query()->create([
            'name' => 'LCD Proyektor Report',
            'inventory_number' => 'RPT-PDF-1',
            'barcode' => 'RPT-PDF-1',
            'room_id' => $room->id,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'condition' => 'bagus',
            'status' => 'aktif',
            'price' => 2000000,
            'quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('reports.generate'), [
                'format' => 'view',
                'campus_id' => $campus->id,
            ])
            ->assertOk()
            ->assertSee('LCD Proyektor Report')
            ->assertSee($campus->name)
            ->assertSee('Grand Total');

        $this->actingAs($admin)
            ->post(route('reports.generate'), [
                'format' => 'pdf',
                'campus_id' => $campus->id,
            ])
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_portrait_and_landscape_differ_in_layout_and_paper(): void
    {
        $admin = $this->makeSuperAdmin();
        [$campus, $room, $category, $sub] = $this->seedLocationWithCategory();

        InventoryItem::query()->create([
            'name' => 'Meja Kerja Panjang Sekali Nama Barang Report',
            'inventory_number' => 'CBB-UNDIRA-26-FUR-MJA-000999',
            'barcode' => 'CBB-UNDIRA-26-FUR-MJA-000999',
            'serial_number' => 'SN-VERY-LONG-SERIAL-XYZ-001',
            'brand' => 'MerkPanjang',
            'room_id' => $room->id,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'condition' => 'bagus',
            'status' => 'aktif',
            'price' => 1500000,
            'quantity' => 1,
            'responsible_person' => 'PIC Nama Sangat Panjang Untuk Uji Potong',
        ]);

        $portrait = $this->actingAs($admin)
            ->post(route('reports.export', 'lengkap'), [
                'format' => 'pdf-portrait',
                'orientation' => 'portrait',
                'campus_id' => $campus->id,
            ])
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $landscape = $this->actingAs($admin)
            ->post(route('reports.export', 'lengkap'), [
                'format' => 'pdf-landscape',
                'orientation' => 'landscape',
                'campus_id' => $campus->id,
            ])
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertNotSame($portrait->getContent(), $landscape->getContent());

        $request = \Illuminate\Http\Request::create('/reports/lengkap/export', 'POST', [
            'campus_id' => $campus->id,
        ]);
        $request->setUserResolver(fn () => $admin);

        $docPortrait = app(\App\Services\Reports\EamReportService::class)
            ->buildInventoryDocument($request, \App\Enums\InventoryReportType::Complete, 'portrait');
        $htmlPortrait = view('exports.inventory-pdf', $docPortrait)->render();
        $this->assertStringContainsString('rpt-table--portrait', $htmlPortrait);
        $this->assertStringNotContainsString('Serial Number', $htmlPortrait);
        $this->assertStringNotContainsString('>Merk<', $htmlPortrait);

        $docLandscape = app(\App\Services\Reports\EamReportService::class)
            ->buildInventoryDocument($request, \App\Enums\InventoryReportType::Complete, 'landscape');
        $htmlLandscape = view('exports.inventory-pdf', $docLandscape)->render();
        $this->assertStringContainsString('rpt-table--landscape', $htmlLandscape);
        $this->assertStringContainsString('Serial Number', $htmlLandscape);
        $this->assertStringContainsString('Merk', $htmlLandscape);
        $this->assertStringContainsString('Nilai', $htmlLandscape);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.inventory-pdf', $docLandscape);
        $pdf->setPaper('a4', 'landscape');
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $this->assertGreaterThan($canvas->get_height(), $canvas->get_width());
    }

    public function test_viewer_can_load_cascade_buildings_for_report(): void
    {
        $viewer = $this->makeViewer();
        $campus = Campus::query()->create([
            'code' => 'CBB', 'name' => 'Cibubur', 'slug' => 'cbb-rpt', 'is_active' => true,
        ]);
        Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'A', 'name' => 'Gedung A', 'slug' => 'ga-rpt', 'is_active' => true,
        ]);

        $this->actingAs($viewer)
            ->getJson(route('master-data.buildings.by-campus', $campus))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Gedung A');
    }

    /**
     * @return array{0: Campus, 1: Room, 2: Category, 3: SubCategory}
     */
    protected function seedLocationWithCategory(): array
    {
        $campus = Campus::query()->create([
            'code' => 'TST', 'name' => 'UNDIRA CIBUBUR', 'slug' => 'undira-cibubur-rpt', 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'A', 'name' => 'Gedung A', 'slug' => 'gedung-a-rpt', 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'A101', 'name' => 'Ruang A101', 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'FUR', 'name' => 'Furniture', 'slug' => 'furniture-rpt', 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'MJA', 'name' => 'Meja', 'slug' => 'meja-rpt', 'is_active' => true,
        ]);

        return [$campus, $room, $category, $sub];
    }
}
