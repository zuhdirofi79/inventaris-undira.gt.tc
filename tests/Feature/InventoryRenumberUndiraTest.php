<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\ProcurementType;
use App\Models\Room;
use App\Models\Setting;
use App\Models\SubCategory;
use Database\Seeders\InventorySettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InventoryRenumberUndiraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_dry_run_previews_without_changing_numbers(): void
    {
        $this->seed(InventorySettingSeeder::class);
        Setting::set('inventory.sequence_padding', '6', 'inventory');
        $item = $this->makeCompleteItem(['inventory_number' => 'OLD-LEGACY-001']);

        $this->artisan('inventory:renumber-undira', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame('OLD-LEGACY-001', $item->fresh()->inventory_number);
    }

    public function test_force_renumbers_and_rebuilds_qr_with_plain_number(): void
    {
        $this->seed(InventorySettingSeeder::class);
        Setting::set('inventory.sequence_padding', '6', 'inventory');
        $item = $this->makeCompleteItem([
            'inventory_number' => 'OLD-LEGACY-002',
            'purchase_year' => 2026,
        ]);

        $this->artisan('inventory:renumber-undira', ['--force' => true])
            ->assertSuccessful();

        $item->refresh();
        $this->assertMatchesRegularExpression(
            '/^CBB-HIBAH-26-EL-LPT-\d{6}$/',
            $item->inventory_number,
        );
        $this->assertNotNull($item->qr_code_path);
        Storage::disk('public')->assertExists($item->qr_code_path);
        $qr = Storage::disk('public')->get($item->qr_code_path);
        $this->assertStringContainsString('<svg', strtolower($qr));
    }

    public function test_force_aborts_when_master_data_incomplete(): void
    {
        $this->seed(InventorySettingSeeder::class);
        $item = $this->makeCompleteItem(['inventory_number' => 'OLD-INCOMPLETE-1']);
        $item->procurement_type_id = null;
        $item->saveQuietly();

        $this->artisan('inventory:renumber-undira', ['--force' => true])
            ->assertFailed();

        $this->assertSame('OLD-INCOMPLETE-1', $item->fresh()->inventory_number);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeCompleteItem(array $overrides = []): InventoryItem
    {
        $campus = Campus::query()->firstOrCreate(
            ['code' => 'CBB'],
            ['name' => 'Cibubur', 'slug' => 'cibubur-renumber', 'is_active' => true],
        );
        $building = Building::query()->firstOrCreate(
            ['campus_id' => $campus->id, 'code' => 'A'],
            ['name' => 'Gedung A', 'slug' => 'gedung-a-renumber', 'is_active' => true],
        );
        $floor = Floor::query()->firstOrCreate(
            ['building_id' => $building->id, 'code' => 'L1'],
            ['name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true],
        );
        $room = Room::query()->firstOrCreate(
            ['floor_id' => $floor->id, 'code' => 'LAB-1'],
            ['name' => 'Lab 1', 'is_active' => true],
        );
        $category = Category::query()->firstOrCreate(
            ['code' => 'EL'],
            ['name' => 'Elektronik', 'slug' => 'elektronik-renumber', 'is_active' => true],
        );
        $sub = SubCategory::query()->firstOrCreate(
            ['category_id' => $category->id, 'code' => 'LPT'],
            ['name' => 'Laptop', 'slug' => 'laptop-renumber', 'is_active' => true],
        );
        $proc = ProcurementType::query()->firstOrCreate(
            ['code' => 'HIBAH'],
            ['name' => 'Hibah', 'is_active' => true],
        );

        return InventoryItem::query()->create(array_merge([
            'inventory_number' => 'LEGACY-'.uniqid(),
            'name' => 'Laptop Renumber',
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'procurement_type_id' => $proc->id,
            'purchase_year' => 2026,
            'condition' => 'bagus',
            'status' => 'aktif',
            'room_id' => $room->id,
            'quantity' => 1,
            'unit' => 'Unit',
        ], $overrides));
    }
}
