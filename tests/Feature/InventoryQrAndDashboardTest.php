<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\SubCategory;
use App\Services\DashboardStatsService;
use App\Services\InventoryCodeService;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class InventoryQrAndDashboardTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_ensure_codes_creates_qr_file_only(): void
    {
        $item = $this->makeItem();

        app(InventoryCodeService::class)->ensureCodes($item);
        $item->refresh();

        $this->assertNotNull($item->qr_code_path);
        Storage::disk('public')->assertExists($item->qr_code_path);
        Storage::disk('public')->assertMissing('inventory/barcodes/'.str_replace(['/', '\\'], '-', $item->inventory_number).'.png');
    }

    public function test_detail_page_auto_generates_missing_codes(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem(['qr_code_path' => null, 'barcode' => null]);

        $response = $this->actingAs($admin)->get(route('inventory.show', $item));

        $response->assertOk();
        $item->refresh();
        $this->assertNotNull($item->qr_code_path);
        $response->assertSee('Unduh QR Code');
        $response->assertSee('Cetak Label');
        $response->assertDontSee('Unduh Barcode');
        $response->assertSee('Timeline Inventaris');
    }

    public function test_inline_qr_returns_svg_with_correct_mime(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem();
        app(InventoryCodeService::class)->ensureCodes($item);

        $response = $this->actingAs($admin)->get(route('inventory.qr', $item));

        $response->assertOk();
        $response->assertHeader('content-type', 'image/svg+xml; charset=UTF-8');
        $this->assertStringContainsString('svg', strtolower($response->getContent()));
        $this->assertStringNotContainsString('attachment', (string) $response->headers->get('content-disposition'));
    }

    public function test_barcode_routes_are_removed(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem();

        $this->actingAs($admin)->get('/inventory/'.$item->id.'/barcode')->assertNotFound();
        $this->actingAs($admin)->get('/inventory/'.$item->id.'/barcode/download')->assertNotFound();
    }

    public function test_label_print_page_embeds_relative_qr_route_only(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem();
        app(InventoryCodeService::class)->ensureCodes($item);
        $item->refresh();

        $response = $this->actingAs($admin)->get(route('inventory.label.print', $item));

        $response->assertOk();
        $response->assertSee(route('inventory.qr', $item, false), false);
        $response->assertDontSee('/barcode', false);
        $response->assertDontSee('http://localhost/storage/', false);
        $response->assertSee($item->inventory_number, false);
        $response->assertSee('Ruangan:', false);
        $response->assertSee('PIC:', false);
    }

    public function test_qr_file_is_named_from_inventory_number_not_scan_url(): void
    {
        $item = $this->makeItem(['inventory_number' => 'CBB-HIBAH-26-EL-LPT-000099']);
        $service = app(InventoryCodeService::class);

        $path = $service->generateQrCode($item);

        $this->assertSame('inventory/qrcodes/CBB-HIBAH-26-EL-LPT-000099.svg', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringNotContainsString('scan', $path);
        $this->assertStringNotContainsString('http', $path);

        // scanUrl remains available for UI links, but is not used as QR filename/payload source.
        $url = $service->scanUrl($item->inventory_number);
        $this->assertStringContainsString('/scan/'.$item->inventory_number, $url);
    }

    public function test_authenticated_scan_redirects_to_detail(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem();
        app(InventoryCodeService::class)->ensureCodes($item);

        $response = $this->actingAs($admin)->get(route('inventory.scan', $item->inventory_number));

        $response->assertRedirect(route('inventory.show', $item));
    }

    public function test_public_scan_shows_rich_detail(): void
    {
        $item = $this->makeItem();
        app(InventoryCodeService::class)->ensureCodes($item);

        $response = $this->get(route('inventory.scan', $item->inventory_number));

        $response->assertOk();
        $response->assertSee($item->name);
        $response->assertSee('Kampus');
        $response->assertSee('Gedung');
        $response->assertSee('Riwayat Perpindahan');
        $response->assertSee('Riwayat Maintenance');
    }

    public function test_dashboard_stats_come_from_database(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->makeItem(['price' => 1500000, 'name' => 'Kursi Dashboard']);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Total Inventaris');
        $response->assertSee('Total Unit Kerja');
        $response->assertSee('Total User');
        $response->assertSee('Inventaris per Kondisi');
        $response->assertSee('Inventaris per Unit Kerja');

        $summary = app(DashboardStatsService::class)->summary();
        $this->assertGreaterThanOrEqual(1, $summary['total_inventory']);
        $this->assertGreaterThanOrEqual(1, $summary['total_campuses']);
        $this->assertArrayHasKey('by_condition', $summary['charts']);
        $this->assertArrayHasKey('by_org_unit', $summary['charts']);
        $this->assertNotEmpty($summary['charts']['by_campus']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeItem(array $overrides = []): InventoryItem
    {
        $campus = Campus::query()->create([
            'code' => 'CBB', 'name' => 'Cibubur', 'slug' => 'cibubur', 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'A', 'name' => 'Gedung A', 'slug' => 'a', 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'A-101', 'name' => 'Ruang A-101', 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'FUR', 'name' => 'Furniture', 'slug' => 'furniture', 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'KR', 'name' => 'Kursi', 'slug' => 'kursi', 'is_active' => true,
        ]);

        return InventoryItem::query()->create(array_merge([
            'inventory_number' => 'CBB-TEST-KR-000001',
            'barcode' => null,
            'qr_code_path' => null,
            'name' => 'Kursi Test',
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'condition' => 'bagus',
            'status' => 'aktif',
            'room_id' => $room->id,
            'price' => 500000,
            'quantity' => 1,
            'unit' => 'Unit',
        ], $overrides));
    }
}
