<?php

namespace Tests\Feature;

use App\Exports\InventoryHierarchicalExport;
use App\Exports\InventoryReportWorkbookExport;
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

class InventoryExcelDetailSheetTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_detail_sheet_contains_only_inventory_rows_without_summary_preamble(): void
    {
        $admin = $this->makeSuperAdmin();
        [$campus, $room, $category, $sub] = $this->seedLocation();

        InventoryItem::query()->create([
            'name' => 'Meja Excel Detail',
            'inventory_number' => 'XLS-DET-001',
            'barcode' => 'XLS-DET-001',
            'room_id' => $room->id,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'condition' => 'bagus',
            'status' => 'aktif',
            'price' => 150000,
            'quantity' => 1,
            'brand' => 'Olympic',
            'serial_number' => 'SN-001',
        ]);

        InventoryItem::query()->create([
            'name' => 'Kursi Excel Detail',
            'inventory_number' => 'XLS-DET-002',
            'barcode' => 'XLS-DET-002',
            'room_id' => $room->id,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'condition' => 'rusak_ringan',
            'status' => 'maintenance',
            'price' => 75000,
            'quantity' => 1,
        ]);

        $request = Request::create('/inventory/export/excel', 'GET', [
            'campus_id' => $campus->id,
        ]);
        $request->setUserResolver(fn () => $admin);

        $report = app(InventoryReportService::class)->build($request);
        $detail = new InventoryHierarchicalExport(
            $report['tree'],
            $report['summary'],
            $report['filters'],
            $report['meta'],
        );

        $this->assertSame('Detail Inventaris', $detail->title());
        $this->assertSame('No', $detail->headings()[0]);
        $this->assertSame('Nomor Inventaris', $detail->headings()[1]);

        $rows = $detail->array();
        $this->assertCount(2, $rows);
        $this->assertSame(1, $rows[0][0]);
        $this->assertSame('XLS-DET-001', $rows[0][1]);
        $this->assertSame('Meja Excel Detail', $rows[0][2]);

        $flat = collect($rows)->flatten()->map(fn ($v) => mb_strtolower((string) $v))->implode(' ');
        $this->assertStringNotContainsString('filter', $flat);
        $this->assertStringNotContainsString('ringkasan', $flat);
        $this->assertStringNotContainsString('rekap sub kategori', $flat);
        $this->assertStringNotContainsString('grand total', $flat);
        $this->assertStringNotContainsString('total inventaris', $flat);
    }

    public function test_workbook_keeps_filters_on_ringkasan_only(): void
    {
        $admin = $this->makeSuperAdmin();
        [$campus, $room, $category, $sub] = $this->seedLocation();

        InventoryItem::query()->create([
            'name' => 'Barang Ringkas',
            'inventory_number' => 'XLS-SUM-001',
            'barcode' => 'XLS-SUM-001',
            'room_id' => $room->id,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'condition' => 'bagus',
            'status' => 'aktif',
            'price' => 100000,
            'quantity' => 1,
        ]);

        $request = Request::create('/inventory/export/excel', 'GET', [
            'campus_id' => $campus->id,
            'status' => 'aktif',
            'category_id' => $category->id,
        ]);
        $request->setUserResolver(fn () => $admin);

        $report = app(InventoryReportService::class)->build($request);
        $workbook = new InventoryReportWorkbookExport(
            $report['tree'],
            $report['summary'],
            $report['filters'],
            $report['meta'],
        );

        $sheets = $workbook->sheets();
        $this->assertSame('Ringkasan', $sheets[0]->title());
        $this->assertSame('Detail Inventaris', $sheets[3]->title());

        $ringkasanRows = $sheets[0]->array();
        $ringkasanFlat = collect($ringkasanRows)->flatten()->map(fn ($v) => (string) $v)->implode(' | ');
        $this->assertStringContainsString('Filter', $ringkasanFlat);

        $detailRows = $sheets[3]->array();
        $this->assertNotEmpty($detailRows);
        foreach ($detailRows as $row) {
            $this->assertIsInt($row[0]);
            $this->assertStringStartsWith('XLS-', (string) $row[1]);
        }

        $detailFlat = collect($detailRows)->flatten()->map(fn ($v) => mb_strtolower((string) $v))->implode(' ');
        $this->assertStringNotContainsString('filter', $detailFlat);
        $this->assertStringNotContainsString('ringkasan', $detailFlat);
        $this->assertStringNotContainsString('info lokasi', $detailFlat);
    }

    public function test_excel_http_export_returns_xlsx_for_filtered_campus(): void
    {
        $admin = $this->makeSuperAdmin();
        [$campus, $room, $category, $sub] = $this->seedLocation();

        InventoryItem::query()->create([
            'name' => 'HTTP Export Item',
            'inventory_number' => 'XLS-HTTP-001',
            'barcode' => 'XLS-HTTP-001',
            'room_id' => $room->id,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'condition' => 'bagus',
            'status' => 'aktif',
            'price' => 99000,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($admin)->get(route('inventory.export.excel', [
            'campus_id' => $campus->id,
            'status' => 'aktif',
            'category_id' => $category->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
    }

    /**
     * @return array{0: Campus, 1: Room, 2: Category, 3: SubCategory}
     */
    protected function seedLocation(): array
    {
        static $n = 0;
        $n++;
        $s = (string) $n;

        $campus = Campus::query()->create([
            'code' => 'XC'.$s, 'name' => 'Kampus Excel '.$s, 'slug' => 'kampus-excel-'.$s, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'XB'.$s, 'name' => 'Gedung Excel '.$s, 'slug' => 'gedung-excel-'.$s, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'XL'.$s, 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'XR'.$s, 'name' => 'Ruang Excel '.$s, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'XF'.$s, 'name' => 'Furniture', 'slug' => 'furniture-excel-'.$s, 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'XM'.$s, 'name' => 'Meja', 'slug' => 'meja-excel-'.$s, 'is_active' => true,
        ]);

        return [$campus, $room, $category, $sub];
    }
}
