<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Borrowing;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\InventorySequence;
use App\Models\Room;
use App\Models\SubCategory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class InventoryPurgeCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * @return array{0: Campus, 1: Room, 2: Category, 3: SubCategory, 4: InventoryItem}
     */
    protected function seedInventoryFixture(): array
    {
        $campus = Campus::query()->create([
            'code' => 'CBB', 'name' => 'Kampus Purge', 'slug' => 'kampus-purge', 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'A', 'name' => 'Gedung A', 'slug' => 'gedung-a-purge', 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'A104', 'name' => 'Kelas A104', 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'FUR', 'name' => 'Furniture', 'slug' => 'furniture-purge', 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'KR', 'name' => 'Kursi', 'slug' => 'kursi-purge', 'is_active' => true,
        ]);
        $item = InventoryItem::query()->create([
            'name' => 'Kursi Purge',
            'inventory_number' => 'UND-CBB-PURGE-0001',
            'barcode' => 'UND-CBB-PURGE-0001',
            'room_id' => $room->id,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'condition' => 'bagus',
            'status' => 'aktif',
            'quantity' => 1,
        ]);
        Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Tester',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDays(3)->toDateString(),
            'status' => 'dipinjam',
        ]);
        InventorySequence::query()->create([
            'campus_id' => $campus->id,
            'classifier_type' => SubCategory::class,
            'classifier_id' => $sub->id,
            'last_value' => 12,
        ]);

        return [$campus, $room, $category, $sub, $item];
    }

    public function test_dry_run_does_not_delete_data(): void
    {
        [, , , , $item] = $this->seedInventoryFixture();
        $usersBefore = User::query()->count();
        $campusesBefore = Campus::query()->count();

        $exit = Artisan::call('inventory:purge', ['--dry-run' => true]);

        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id]);
        $this->assertSame(1, Borrowing::query()->count());
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertSame($campusesBefore, Campus::query()->count());
    }

    public function test_command_refuses_production_environment(): void
    {
        $this->seedInventoryFixture();
        config(['app.env' => 'production']);

        $exit = Artisan::call('inventory:purge', [
            '--execute' => true,
            '--confirm' => 'PURGE INVENTORY DEVELOPMENT DATA',
        ]);

        $this->assertSame(1, $exit);
        $this->assertSame(1, InventoryItem::query()->count());
    }

    public function test_execute_purges_inventory_and_preserves_master_data(): void
    {
        [$campus, $room, $category, $sub, $item] = $this->seedInventoryFixture();
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super-admin');

        $exit = Artisan::call('inventory:purge', [
            '--execute' => true,
            '--reset-sequences' => true,
            '--confirm' => 'PURGE INVENTORY DEVELOPMENT DATA',
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame(0, InventoryItem::withTrashed()->count());
        $this->assertSame(0, Borrowing::withTrashed()->count());
        $this->assertDatabaseHas('campuses', ['id' => $campus->id]);
        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('sub_categories', ['id' => $sub->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('inventory_items', ['id' => $item->id]);

        $seq = InventorySequence::query()->first();
        $this->assertNotNull($seq);
        $this->assertSame(0, (int) $seq->last_value);

        $this->assertTrue(
            AuditLog::query()
                ->where('description', 'INVENTORY_DATA_PURGE')
                ->where('action', AuditAction::Delete)
                ->exists()
        );
    }

    public function test_sequence_reset_requires_explicit_flag(): void
    {
        $this->seedInventoryFixture();

        Artisan::call('inventory:purge', [
            '--execute' => true,
            '--confirm' => 'PURGE INVENTORY DEVELOPMENT DATA',
        ]);

        $seq = InventorySequence::query()->first();
        $this->assertNotNull($seq);
        $this->assertSame(12, (int) $seq->last_value);
    }

    public function test_legacy_create_redirects_to_konfigurasi(): void
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->assignRole('super-admin');

        $response = $this->actingAs($user)->get(route('inventory.create'));
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('categories', $location);
        $this->assertStringContainsString('konfigurasi', $location);
    }

    public function test_data_inventaris_points_to_konfigurasi_not_import_form(): void
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->assignRole('super-admin');

        $html = $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Konfigurasi Inventaris', $html);
        $this->assertStringContainsString('Penambahan inventaris dilakukan melalui menu', $html);
        $this->assertStringNotContainsString('inventory/import', $html);
    }

    public function test_dashboard_has_no_nilai_chart_after_cleanup(): void
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->assignRole('super-admin');

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Total Nilai', $html);
        $this->assertStringNotContainsString('Nilai Inventaris per Kampus', $html);
        $this->assertStringNotContainsString('chartValueCampus', $html);
    }
}
