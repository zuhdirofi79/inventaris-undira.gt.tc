<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\BorrowingStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Models\AuditLog;
use App\Models\Borrowing;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryConditionMaster;
use App\Models\InventoryItem;
use App\Models\InventoryMasterConfiguration;
use App\Models\InventoryMutation;
use App\Models\InventoryPhoto;
use App\Models\InventorySequence;
use App\Models\Maintenance;
use App\Models\OrgUnit;
use App\Models\ProcurementType;
use App\Models\PurchaseYear;
use App\Models\Room;
use App\Models\Setting;
use App\Models\SubCategory;
use Database\Seeders\InventorySettingSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class InventoryMasterConfigurationDeleteTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_deleting_configuration_permanently_removes_related_unused_inventory(): void
    {
        Storage::fake('public');
        $admin = $this->makeSuperAdmin();
        $payload = $this->world(['quantity' => 5, 'asset_name' => 'Pulpen Salah']);

        $this->actingAs($admin)->post(route('master-configurations.store'), $payload)
            ->assertRedirect(route('rooms.show', $payload['room_id']));

        $config = InventoryMasterConfiguration::query()->firstOrFail();
        $items = InventoryItem::query()->where('name', 'Pulpen Salah')->orderBy('id')->get();
        $this->assertCount(5, $items);
        $numbers = $items->pluck('inventory_number')->all();

        $createLogs = AuditLog::query()->where('action', AuditAction::Create)->count();

        $this->actingAs($admin)
            ->get(route('categories.index', ['tab' => 'konfigurasi']))
            ->assertOk()
            ->assertSee('Hapus Konfigurasi Inventaris?')
            ->assertSee('5 inventaris terkait akan ikut dihapus permanen');

        $this->actingAs($admin)
            ->delete(route('master-configurations.destroy', $config))
            ->assertRedirect(route('categories.index', ['tab' => 'konfigurasi']))
            ->assertSessionHas('success', 'Konfigurasi dan data inventaris terkait berhasil dihapus.');

        $this->assertDatabaseMissing('inventory_master_configurations', ['id' => $config->id]);
        $this->assertNull(InventoryMasterConfiguration::withTrashed()->find($config->id));

        foreach ($items as $item) {
            $this->assertDatabaseMissing('inventory_items', ['id' => $item->id]);
            $this->assertNull(InventoryItem::withTrashed()->find($item->id));
        }

        $this->assertGreaterThanOrEqual($createLogs, AuditLog::query()->where('action', AuditAction::Create)->count());
        $this->assertTrue(AuditLog::query()->where('action', AuditAction::ForceDelete)->exists());

        $this->actingAs($admin)->get(route('rooms.show', $payload['room_id']))->assertOk();

        $roomItems = $this->actingAs($admin)->getJson(route('locations.explore.items', [
            'room' => $payload['room_id'],
            'category' => $payload['category_id'],
            'subCategory' => $payload['sub_category_id'],
        ]));
        $roomItems->assertOk()->assertJsonPath('meta.total', 0);
        $this->assertSame([], $roomItems->json('rows'));

        $search = $this->actingAs($admin)->getJson(route('inventory.selector.search', [
            'q' => $numbers[0],
            'status' => 'aktif',
        ]));
        $search->assertOk();
        $this->assertSame([], array_values(array_filter(
            $search->json('results') ?? [],
            fn ($row) => ($row['inventory_number'] ?? null) === $numbers[0]
        )));

        $this->get(route('inventory.scan', ['inventoryNumber' => $numbers[0]]))->assertNotFound();
    }

    public function test_configuration_without_inventory_is_deleted(): void
    {
        $admin = $this->makeSuperAdmin();
        $payload = $this->world();
        $config = InventoryMasterConfiguration::query()->create([
            'campus_id' => $payload['campus_id'],
            'building_id' => $payload['building_id'],
            'floor_id' => $payload['floor_id'],
            'org_unit_id' => $payload['org_unit_id'],
            'room_id' => $payload['room_id'],
            'procurement_type_id' => $payload['procurement_type_id'],
            'purchase_year_id' => $payload['purchase_year_id'],
            'category_id' => $payload['category_id'],
            'sub_category_id' => $payload['sub_category_id'],
            'inventory_condition_id' => $payload['inventory_condition_id'],
            'generated_location_code' => 'LOC-EMPTY',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('master-configurations.destroy', $config))
            ->assertRedirect(route('categories.index', ['tab' => 'konfigurasi']))
            ->assertSessionHas('success', 'Konfigurasi berhasil dihapus.');

        $this->assertNull(InventoryMasterConfiguration::withTrashed()->find($config->id));
    }

    public function test_other_configuration_and_its_inventory_stay(): void
    {
        $admin = $this->makeSuperAdmin();
        $first = $this->world(['quantity' => 2, 'asset_name' => 'Pulpen Salah', 'category_code' => 'ATK', 'sub_code' => 'PL']);
        $second = $this->world([
            'quantity' => 2,
            'asset_name' => 'Kursi Benar',
            'category_code' => 'FUR',
            'sub_code' => 'KRS',
            'campus_code' => 'CB2',
        ]);

        $this->actingAs($admin)->post(route('master-configurations.store'), $first)->assertRedirect();
        $this->actingAs($admin)->post(route('master-configurations.store'), $second)->assertRedirect();

        $wrong = InventoryMasterConfiguration::query()->where('category_id', $first['category_id'])->firstOrFail();
        $keptNumber = InventoryItem::query()->where('name', 'Kursi Benar')->value('inventory_number');

        $this->actingAs($admin)->delete(route('master-configurations.destroy', $wrong))->assertRedirect();

        $this->assertNull(InventoryMasterConfiguration::withTrashed()->find($wrong->id));
        $this->assertSame(0, InventoryItem::withTrashed()->where('name', 'Pulpen Salah')->count());
        $this->assertNotNull(InventoryMasterConfiguration::query()->where('category_id', $second['category_id'])->first());
        $this->assertSame($keptNumber, InventoryItem::query()->where('name', 'Kursi Benar')->value('inventory_number'));
        $this->assertSame(2, InventoryItem::query()->where('name', 'Kursi Benar')->count());
    }

    public function test_same_location_different_condition_is_not_deleted(): void
    {
        $admin = $this->makeSuperAdmin();
        $base = $this->world(['quantity' => 1, 'asset_name' => 'Pulpen Baik']);
        $rusak = InventoryConditionMaster::query()->create([
            'code' => 'RUSAK',
            'name' => 'Rusak',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('master-configurations.store'), $base)->assertRedirect();

        $damaged = $base;
        $damaged['inventory_condition_id'] = $rusak->id;
        $damaged['asset_name'] = 'Pulpen Rusak';
        $this->actingAs($admin)->post(route('master-configurations.store'), $damaged)->assertRedirect();

        $baikConfig = InventoryMasterConfiguration::query()
            ->where('inventory_condition_id', $base['inventory_condition_id'])
            ->firstOrFail();

        $this->assertSame('BAIK', InventoryItem::query()->where('name', 'Pulpen Baik')->firstOrFail()->condition->value);
        $this->assertSame('RUSAK', InventoryItem::query()->where('name', 'Pulpen Rusak')->firstOrFail()->condition->value);

        $this->actingAs($admin)->delete(route('master-configurations.destroy', $baikConfig))->assertRedirect();

        $this->assertSame(0, InventoryItem::withTrashed()->where('name', 'Pulpen Baik')->count());
        $this->assertSame(1, InventoryItem::query()->where('name', 'Pulpen Rusak')->count());
        $this->assertNotNull(InventoryMasterConfiguration::query()->where('inventory_condition_id', $rusak->id)->first());
    }

    public function test_photo_exclusive_to_deleted_inventory_is_removed(): void
    {
        Storage::fake('public');
        $admin = $this->makeSuperAdmin();
        $payload = $this->world(['quantity' => 1, 'asset_name' => 'Dengan Foto']);
        $this->actingAs($admin)->post(route('master-configurations.store'), $payload)->assertRedirect();

        $item = InventoryItem::query()->where('name', 'Dengan Foto')->firstOrFail();
        $path = 'inventory/photos/salah-input.jpg';
        Storage::disk('public')->put($path, 'image');
        InventoryPhoto::query()->create([
            'inventory_item_id' => $item->id,
            'path' => $path,
            'is_primary' => true,
        ]);

        $config = InventoryMasterConfiguration::query()->firstOrFail();
        $this->actingAs($admin)->delete(route('master-configurations.destroy', $config))->assertRedirect();

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('inventory_photos', ['inventory_item_id' => $item->id]);
        $this->assertNull(InventoryItem::withTrashed()->find($item->id));
    }

    public function test_inventory_with_borrowing_history_blocks_delete(): void
    {
        $admin = $this->makeSuperAdmin();
        $payload = $this->world(['quantity' => 2, 'asset_name' => 'Sudah Dipinjam']);
        $this->actingAs($admin)->post(route('master-configurations.store'), $payload)->assertRedirect();

        $config = InventoryMasterConfiguration::query()->firstOrFail();
        $item = InventoryItem::query()->where('name', 'Sudah Dipinjam')->firstOrFail();
        Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Peminjam',
            'borrower_phone' => '081234567890',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'status' => BorrowingStatus::MenungguPersetujuan,
            'requested_by' => $admin->id,
            'notes' => 'Riwayat',
        ]);

        $this->actingAs($admin)
            ->from(route('categories.index', ['tab' => 'konfigurasi']))
            ->delete(route('master-configurations.destroy', $config))
            ->assertRedirect(route('categories.index', ['tab' => 'konfigurasi']))
            ->assertSessionHas('error');

        $this->assertNotNull(InventoryMasterConfiguration::query()->find($config->id));
        $this->assertSame(2, InventoryItem::query()->where('name', 'Sudah Dipinjam')->count());
        $this->assertDatabaseHas('borrowings', ['inventory_item_id' => $item->id]);
    }

    public function test_inventory_with_maintenance_history_blocks_delete(): void
    {
        $admin = $this->makeSuperAdmin();
        $payload = $this->world(['quantity' => 1, 'asset_name' => 'Sudah Maintenance']);
        $this->actingAs($admin)->post(route('master-configurations.store'), $payload)->assertRedirect();

        $config = InventoryMasterConfiguration::query()->firstOrFail();
        $item = InventoryItem::query()->where('name', 'Sudah Maintenance')->firstOrFail();
        Maintenance::query()->create([
            'inventory_item_id' => $item->id,
            'created_by' => $admin->id,
            'maintenance_number' => 'MNT-CFG-'.uniqid(),
            'maintenance_type' => MaintenanceType::Corrective,
            'service_date' => now()->toDateString(),
            'status' => MaintenanceStatus::Waiting,
        ]);

        $this->actingAs($admin)
            ->from(route('categories.index', ['tab' => 'konfigurasi']))
            ->delete(route('master-configurations.destroy', $config))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotNull(InventoryMasterConfiguration::query()->find($config->id));
        $this->assertNotNull(InventoryItem::query()->find($item->id));
        $this->assertDatabaseHas('maintenances', ['inventory_item_id' => $item->id]);
    }

    public function test_moved_inventory_with_mutation_blocks_delete_and_keeps_history(): void
    {
        $admin = $this->makeSuperAdmin();
        $payload = $this->world(['quantity' => 1, 'asset_name' => 'Sudah Mutasi']);
        $this->actingAs($admin)->post(route('master-configurations.store'), $payload)->assertRedirect();

        $otherRoom = Room::query()->create([
            'floor_id' => $payload['floor_id'],
            'code' => 'R202',
            'name' => 'Ruang Lain',
            'is_active' => true,
        ]);
        $item = InventoryItem::query()->where('name', 'Sudah Mutasi')->firstOrFail();
        InventoryMutation::query()->create([
            'inventory_item_id' => $item->id,
            'from_room_id' => $payload['room_id'],
            'to_room_id' => $otherRoom->id,
            'moved_by' => $admin->id,
            'reason' => 'Pindah',
        ]);
        $item->update(['room_id' => $otherRoom->id]);

        $config = InventoryMasterConfiguration::query()->firstOrFail();
        $this->actingAs($admin)
            ->from(route('categories.index', ['tab' => 'konfigurasi']))
            ->delete(route('master-configurations.destroy', $config))
            ->assertSessionHas('error');

        $this->assertNotNull(InventoryMasterConfiguration::query()->find($config->id));
        $this->assertSame($otherRoom->id, $item->fresh()->room_id);
        $this->assertDatabaseHas('inventory_mutations', ['inventory_item_id' => $item->id]);
    }

    public function test_failed_delete_rolls_back_every_change(): void
    {
        $admin = $this->makeSuperAdmin();
        $payload = $this->world(['quantity' => 3, 'asset_name' => 'Rollback']);
        $this->actingAs($admin)->post(route('master-configurations.store'), $payload)->assertRedirect();

        $config = InventoryMasterConfiguration::query()->firstOrFail();
        $ids = InventoryItem::query()->where('name', 'Rollback')->orderBy('id')->pluck('id')->all();
        $seen = 0;

        InventoryItem::deleting(function (InventoryItem $item) use (&$seen, $ids) {
            if (! in_array($item->id, $ids, true)) {
                return;
            }
            $seen++;
            if ($seen >= 2) {
                throw new \RuntimeException('simulated failure');
            }
        });

        $this->actingAs($admin)
            ->delete(route('master-configurations.destroy', $config))
            ->assertStatus(500);

        $this->assertNotNull(InventoryMasterConfiguration::query()->find($config->id));
        $this->assertSame(3, InventoryItem::query()->whereIn('id', $ids)->count());
    }

    public function test_viewer_cannot_delete_configuration(): void
    {
        $admin = $this->makeSuperAdmin();
        $viewer = $this->makeViewer();
        $payload = $this->world(['quantity' => 1, 'asset_name' => 'Tidak Boleh']);
        $this->actingAs($admin)->post(route('master-configurations.store'), $payload)->assertRedirect();
        $config = InventoryMasterConfiguration::query()->firstOrFail();

        $this->actingAs($viewer)
            ->delete(route('master-configurations.destroy', $config))
            ->assertForbidden();

        $this->assertNotNull(InventoryMasterConfiguration::query()->find($config->id));
        $this->assertSame(1, InventoryItem::query()->where('name', 'Tidak Boleh')->count());
    }

    public function test_next_inventory_number_does_not_reuse_deleted_sequence(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(InventorySettingSeeder::class);
        Setting::set('inventory.sequence_padding', '6', 'inventory');

        $payload = $this->world(['quantity' => 2, 'asset_name' => 'Urutan']);
        $this->actingAs($admin)->post(route('master-configurations.store'), $payload)->assertRedirect();

        $numbers = InventoryItem::query()->where('name', 'Urutan')->orderBy('inventory_number')->pluck('inventory_number');
        $this->assertCount(2, $numbers);
        $lastBefore = (int) InventorySequence::query()
            ->where('campus_id', $payload['campus_id'])
            ->where('sub_category_id', $payload['sub_category_id'])
            ->value('last_value');

        $config = InventoryMasterConfiguration::query()->firstOrFail();
        $this->actingAs($admin)->delete(route('master-configurations.destroy', $config))->assertRedirect();

        $this->assertSame($lastBefore, (int) InventorySequence::query()
            ->where('campus_id', $payload['campus_id'])
            ->where('sub_category_id', $payload['sub_category_id'])
            ->value('last_value'));

        $again = $payload;
        $again['asset_name'] = 'Urutan Baru';
        $again['quantity'] = 1;
        $this->actingAs($admin)->post(route('master-configurations.store'), $again)->assertRedirect();

        $fresh = InventoryItem::query()->where('name', 'Urutan Baru')->firstOrFail();
        $this->assertNotContains($fresh->inventory_number, $numbers->all());
        $this->assertStringEndsWith('000003', $fresh->inventory_number);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function world(array $overrides = []): array
    {
        $suffix = uniqid();
        $campusCode = $overrides['campus_code'] ?? 'CBB';
        $categoryCode = $overrides['category_code'] ?? 'ATK';
        $subCode = $overrides['sub_code'] ?? 'PL';

        $campus = Campus::query()->create([
            'code' => $campusCode.$suffix,
            'name' => 'Undira '.$suffix,
            'slug' => 'undira-'.$suffix,
            'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'code' => 'A',
            'name' => 'Gedung A',
            'slug' => 'gedung-a-'.$suffix,
            'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id,
            'code' => 'L1',
            'name' => 'Lantai 1',
            'floor_number' => 1,
            'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id,
            'code' => 'A103',
            'name' => 'Kelas A103',
            'is_active' => true,
        ]);
        $org = OrgUnit::query()->create([
            'code' => 'OU'.$suffix,
            'name' => 'Unit '.$suffix,
            'is_active' => true,
        ]);
        $proc = ProcurementType::query()->create([
            'code' => 'PR'.$suffix,
            'name' => 'Pengadaan '.$suffix,
            'is_active' => true,
        ]);
        $year = PurchaseYear::query()->firstOrCreate(
            ['year' => 2026],
            ['is_active' => true],
        );
        $category = Category::query()->create([
            'code' => $categoryCode.$suffix,
            'name' => $categoryCode === 'ATK' ? 'Alat Tulis' : 'Furniture',
            'slug' => 'cat-'.$suffix,
            'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id,
            'code' => $subCode.$suffix,
            'name' => $subCode === 'PL' ? 'Pulpen' : 'Kursi',
            'slug' => 'sub-'.$suffix,
            'is_active' => true,
        ]);
        $condition = InventoryConditionMaster::query()->firstOrCreate(
            ['code' => 'BAIK'],
            ['name' => 'Baik', 'is_active' => true],
        );

        return [
            'campus_id' => $campus->id,
            'building_id' => $building->id,
            'floor_id' => $floor->id,
            'org_unit_id' => $org->id,
            'room_id' => $room->id,
            'procurement_type_id' => $proc->id,
            'purchase_year_id' => $year->id,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'inventory_condition_id' => $condition->id,
            'is_active' => 1,
            'create_assets' => 1,
            'asset_name' => $overrides['asset_name'] ?? 'Barang Uji',
            'quantity' => $overrides['quantity'] ?? 1,
            'unit' => 'Unit',
        ];
    }
}
