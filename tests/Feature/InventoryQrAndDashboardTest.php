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

    public function test_ensure_codes_creates_qr_and_barcode_files(): void
    {
        $item = $this->makeItem();

        app(InventoryCodeService::class)->ensureCodes($item);
        $item->refresh();

        $this->assertNotNull($item->qr_code_path);
        $this->assertSame($item->inventory_number, $item->barcode);
        Storage::disk('public')->assertExists($item->qr_code_path);
        Storage::disk('public')->assertExists('inventory/barcodes/'.str_replace(['/', '\\'], '-', $item->inventory_number).'.png');
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

    public function test_inline_barcode_returns_png_with_correct_mime(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem();
        app(InventoryCodeService::class)->ensureCodes($item);

        $response = $this->actingAs($admin)->get(route('inventory.barcode', $item));

        $response->assertOk();
        $this->assertStringStartsWith('image/png', (string) $response->headers->get('content-type'));
        $this->assertNotEmpty($response->getContent());
        $this->assertStringNotContainsString('attachment', (string) $response->headers->get('content-disposition'));
    }

    public function test_label_print_page_embeds_relative_code_routes(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem();
        app(InventoryCodeService::class)->ensureCodes($item);
        $item->refresh();

        $response = $this->actingAs($admin)->get(route('inventory.label.print', $item));

        $response->assertOk();
        $response->assertSee(route('inventory.qr', $item, false), false);
        $response->assertSee(route('inventory.barcode', $item, false), false);
        $response->assertDontSee('http://localhost/storage/', false);
        $response->assertSee($item->inventory_number, false);
    }

    public function test_qr_payload_uses_scan_route(): void
    {
        $item = $this->makeItem();
        $service = app(InventoryCodeService::class);
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

    public function test_dashboard_stats_come_from_database(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->makeItem(['price' => 1500000, 'name' => 'Kursi Dashboard']);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Total Inventaris');
        $response->assertSee('Statistik per Kampus');
        $response->assertSee('Statistik per Sub Klasifikasi');
        $response->assertSee('Kampus × Sub Klasifikasi');
        $response->assertSee('Inventaris per Kampus');

        $summary = app(DashboardStatsService::class)->summary();
        $this->assertGreaterThanOrEqual(1, $summary['total_inventory']);
        $this->assertGreaterThanOrEqual(1, $summary['total_campuses']);
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
