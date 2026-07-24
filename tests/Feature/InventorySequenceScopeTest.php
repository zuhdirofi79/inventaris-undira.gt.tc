<?php

namespace Tests\Feature;

use App\Enums\InventoryStatus;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\ProcurementType;
use App\Models\Room;
use App\Models\Setting;
use App\Models\SubCategory;
use App\Services\InventoryCodeService;
use Database\Seeders\InventorySettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventorySequenceScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InventorySettingSeeder::class);
        Setting::set('inventory.sequence_padding', '6', 'inventory');
    }

    public function test_same_campus_same_subcategory_increments(): void
    {
        [$campus, $cat, $sub] = $this->masters('CBB', 'FUR', 'KRS');
        $service = app(InventoryCodeService::class);

        $this->assertSame(1, $service->allocateSequence($campus->id, $sub->id));
        $this->assertSame(2, $service->allocateSequence($campus->id, $sub->id));
        $this->assertSame(3, $service->allocateSequence($campus->id, $sub->id));
    }

    public function test_different_campus_starts_from_one(): void
    {
        [$cbb, $cat, $sub] = $this->masters('CBB', 'FUR', 'KRS');
        $krg = Campus::query()->create([
            'code' => 'KRG', 'name' => 'Kranggan', 'slug' => 'kranggan-seq', 'is_active' => true,
        ]);
        $service = app(InventoryCodeService::class);

        $this->assertSame(1, $service->allocateSequence($cbb->id, $sub->id));
        $this->assertSame(2, $service->allocateSequence($cbb->id, $sub->id));
        $this->assertSame(1, $service->allocateSequence($krg->id, $sub->id));
        $this->assertSame(2, $service->allocateSequence($krg->id, $sub->id));
    }

    public function test_different_subcategory_starts_from_one(): void
    {
        [$campus, $cat, $kursi] = $this->masters('CBB', 'FUR', 'KRS');
        $meja = SubCategory::query()->create([
            'category_id' => $cat->id, 'code' => 'MJA', 'name' => 'Meja', 'slug' => 'meja-seq', 'is_active' => true,
        ]);
        $service = app(InventoryCodeService::class);

        $this->assertSame(1, $service->allocateSequence($campus->id, $kursi->id));
        $this->assertSame(2, $service->allocateSequence($campus->id, $kursi->id));
        $this->assertSame(1, $service->allocateSequence($campus->id, $meja->id));
    }

    public function test_deactivated_or_destroyed_item_does_not_recycle_sequence(): void
    {
        [$campus, $cat, $sub, $room, $proc] = $this->mastersWithRoom('CBB', 'FUR', 'KRS');
        $service = app(InventoryCodeService::class);

        $n1 = $service->generateInventoryNumber($campus, $proc, 2026, $cat, $sub);
        $item = InventoryItem::query()->create([
            'inventory_number' => $n1,
            'name' => 'Kursi Rusak',
            'category_id' => $cat->id,
            'sub_category_id' => $sub->id,
            'room_id' => $room->id,
            'procurement_type_id' => $proc->id,
            'purchase_year' => 2026,
            'condition' => 'rusak_berat',
            'status' => InventoryStatus::TidakDapatDiperbaiki,
            'quantity' => 1,
            'unit' => 'Unit',
        ]);

        $item->delete(); // soft delete / dinonaktifkan dari daftar aktif
        $item->forceDelete(); // dimusnahkan

        $n2 = $service->generateInventoryNumber($campus, $proc, 2026, $cat, $sub);
        $this->assertStringEndsWith('-000002', $n2);
        $this->assertNotSame($n1, $n2);
    }

    public function test_no_duplicate_inventory_numbers_for_same_scope(): void
    {
        [$campus, $cat, $sub, $room, $proc] = $this->mastersWithRoom('CBB', 'FUR', 'KRS');
        $service = app(InventoryCodeService::class);

        $numbers = [];
        for ($i = 0; $i < 5; $i++) {
            $numbers[] = $service->generateInventoryNumber($campus, $proc, 2026, $cat, $sub);
        }

        $this->assertCount(5, array_unique($numbers));
        $this->assertSame('CBB-'.$proc->code.'-26-FUR-KRS-000001', $numbers[0]);
        $this->assertSame('CBB-'.$proc->code.'-26-FUR-KRS-000005', $numbers[4]);
    }

    public function test_preview_follows_campus_and_subcategory_combination(): void
    {
        [$cbb, $cat, $kursi] = $this->masters('CBB', 'FUR', 'KRS');
        $meja = SubCategory::query()->create([
            'category_id' => $cat->id, 'code' => 'MJA', 'name' => 'Meja', 'slug' => 'meja-prev', 'is_active' => true,
        ]);
        $krg = Campus::query()->create([
            'code' => 'KRG', 'name' => 'Kranggan', 'slug' => 'krg-prev', 'is_active' => true,
        ]);
        $service = app(InventoryCodeService::class);

        $service->allocateSequence($cbb->id, $kursi->id); // 1
        $service->allocateSequence($cbb->id, $kursi->id); // 2 → next 3

        $peekKursi = $service->peekNextSequence($cbb->id, $kursi->id);
        $this->assertSame(3, $peekKursi['next_value']);
        $this->assertSame('000003', $peekKursi['next_padded']);

        $peekMeja = $service->peekNextSequence($cbb->id, $meja->id);
        $this->assertSame(1, $peekMeja['next_value']);
        $this->assertSame('000001', $peekMeja['next_padded']);

        $peekKrg = $service->peekNextSequence($krg->id, $kursi->id);
        $this->assertSame(1, $peekKrg['next_value']);
        $this->assertSame('000001', $peekKrg['next_padded']);
    }

    /**
     * @return array{0: Campus, 1: Category, 2: SubCategory}
     */
    protected function masters(string $campusCode, string $catCode, string $subCode): array
    {
        $campus = Campus::query()->create([
            'code' => $campusCode, 'name' => $campusCode, 'slug' => strtolower($campusCode).'-'.uniqid(), 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => $catCode, 'name' => $catCode, 'slug' => strtolower($catCode).'-'.uniqid(), 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => $subCode, 'name' => $subCode, 'slug' => strtolower($subCode).'-'.uniqid(), 'is_active' => true,
        ]);

        return [$campus, $category, $sub];
    }

    /**
     * @return array{0: Campus, 1: Category, 2: SubCategory, 3: Room, 4: ProcurementType}
     */
    protected function mastersWithRoom(string $campusCode, string $catCode, string $subCode): array
    {
        [$campus, $category, $sub] = $this->masters($campusCode, $catCode, $subCode);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'A', 'name' => 'Gedung A', 'slug' => 'a-'.uniqid(), 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R1', 'name' => 'Ruang 1', 'is_active' => true,
        ]);
        $proc = ProcurementType::query()->firstOrCreate(
            ['code' => 'HB'],
            ['name' => 'Hibah', 'is_active' => true],
        );

        return [$campus, $category, $sub, $room, $proc];
    }
}
