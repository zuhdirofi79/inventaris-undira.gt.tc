<?php

namespace Tests\Feature;

use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Enums\StockOpnameStatus;
use App\Models\AuditLog;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\InventoryMutation;
use App\Models\Room;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class MutationAndStockOpnamePhase2Test extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_mutation_store_updates_room_in_transaction_and_writes_audit(): void
    {
        $admin = $this->makeSuperAdmin();
        [$item, $fromRoom, $toRoom] = $this->makeItemWithTwoRooms();

        $this->actingAs($admin)
            ->post(route('mutations.store'), [
                'inventory_item_id' => $item->id,
                'to_room_id' => $toRoom->id,
                'reason' => 'Relokasi lab',
                'notes' => 'Pindah semester genap',
            ])
            ->assertRedirect(route('mutations.index'));

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'room_id' => $toRoom->id,
            'updated_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('inventory_mutations', [
            'inventory_item_id' => $item->id,
            'from_room_id' => $fromRoom->id,
            'to_room_id' => $toRoom->id,
            'moved_by' => $admin->id,
            'reason' => 'Relokasi lab',
        ]);

        $this->assertTrue(
            AuditLog::query()
                ->where('description', 'MUTATION_CREATED')
                ->where('auditable_type', (new InventoryMutation)->getMorphClass())
                ->exists()
        );
    }

    public function test_mutation_rejects_same_room_destination(): void
    {
        $admin = $this->makeSuperAdmin();
        [$item, $fromRoom] = $this->makeItemWithTwoRooms();

        $this->actingAs($admin)
            ->from(route('mutations.create'))
            ->post(route('mutations.store'), [
                'inventory_item_id' => $item->id,
                'to_room_id' => $fromRoom->id,
                'reason' => 'Tidak boleh',
            ])
            ->assertRedirect(route('mutations.create'))
            ->assertSessionHasErrors(['to_room_id']);

        $this->assertSame(0, InventoryMutation::query()->count());
        $this->assertSame($fromRoom->id, $item->fresh()->room_id);
    }

    public function test_stock_opname_start_only_from_draft(): void
    {
        $admin = $this->makeSuperAdmin();
        $opname = $this->makeStockOpname(StockOpnameStatus::InProgress, $admin->id);

        $this->actingAs($admin)
            ->from(route('stock-opnames.show', $opname))
            ->post(route('stock-opnames.start', $opname))
            ->assertRedirect(route('stock-opnames.show', $opname))
            ->assertSessionHas('error');

        $this->assertSame(StockOpnameStatus::InProgress, $opname->fresh()->status);
    }

    public function test_stock_opname_happy_path_start_scan_complete(): void
    {
        $admin = $this->makeSuperAdmin();
        [$item] = $this->makeItemWithTwoRooms();
        $opname = $this->makeStockOpname(StockOpnameStatus::Draft, $admin->id);

        $this->actingAs($admin)
            ->post(route('stock-opnames.start', $opname))
            ->assertRedirect()
            ->assertSessionHas('success');

        $opname->refresh();
        $this->assertSame(StockOpnameStatus::InProgress, $opname->status);
        $this->assertNotNull($opname->started_at);

        $this->actingAs($admin)
            ->postJson(route('stock-opnames.scan', $opname), [
                'inventory_number' => $item->inventory_number,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Berhasil discan.');

        $this->assertDatabaseHas('stock_opname_items', [
            'stock_opname_id' => $opname->id,
            'inventory_item_id' => $item->id,
        ]);
        $this->assertNotNull(
            StockOpnameItem::query()
                ->where('stock_opname_id', $opname->id)
                ->where('inventory_item_id', $item->id)
                ->value('scanned_at')
        );

        $this->actingAs($admin)
            ->post(route('stock-opnames.complete', $opname))
            ->assertRedirect()
            ->assertSessionHas('success');

        $opname->refresh();
        $this->assertSame(StockOpnameStatus::Completed, $opname->status);
        $this->assertNotNull($opname->completed_at);

        $this->assertTrue(AuditLog::query()->where('description', 'STOCK_OPNAME_STARTED')->exists());
        $this->assertTrue(AuditLog::query()->where('description', 'STOCK_OPNAME_COMPLETED')->exists());
    }

    public function test_stock_opname_scan_blocked_when_draft(): void
    {
        $admin = $this->makeSuperAdmin();
        [$item] = $this->makeItemWithTwoRooms();
        $opname = $this->makeStockOpname(StockOpnameStatus::Draft, $admin->id);

        $this->actingAs($admin)
            ->postJson(route('stock-opnames.scan', $opname), [
                'inventory_number' => $item->inventory_number,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Scan hanya diperbolehkan saat stock opname sedang berlangsung.');

        $this->assertSame(0, StockOpnameItem::query()->count());
    }

    public function test_stock_opname_complete_blocked_when_draft(): void
    {
        $admin = $this->makeSuperAdmin();
        $opname = $this->makeStockOpname(StockOpnameStatus::Draft, $admin->id);

        $this->actingAs($admin)
            ->from(route('stock-opnames.show', $opname))
            ->post(route('stock-opnames.complete', $opname))
            ->assertRedirect(route('stock-opnames.show', $opname))
            ->assertSessionHas('error');

        $this->assertSame(StockOpnameStatus::Draft, $opname->fresh()->status);
    }

    public function test_stock_opname_completed_cannot_be_deleted(): void
    {
        $admin = $this->makeSuperAdmin();
        $opname = $this->makeStockOpname(StockOpnameStatus::Completed, $admin->id);

        $this->actingAs($admin)
            ->from(route('stock-opnames.show', $opname))
            ->delete(route('stock-opnames.destroy', $opname))
            ->assertRedirect(route('stock-opnames.show', $opname))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('stock_opnames', ['id' => $opname->id]);
    }

    public function test_stock_opname_draft_can_be_deleted(): void
    {
        $admin = $this->makeSuperAdmin();
        $opname = $this->makeStockOpname(StockOpnameStatus::Draft, $admin->id);

        $this->actingAs($admin)
            ->delete(route('stock-opnames.destroy', $opname))
            ->assertRedirect(route('stock-opnames.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('stock_opnames', ['id' => $opname->id]);
        $this->assertTrue(AuditLog::query()->where('description', 'STOCK_OPNAME_DELETED')->exists());
    }

    /**
     * @return array{0: InventoryItem, 1: Room, 2: Room}
     */
    protected function makeItemWithTwoRooms(): array
    {
        $campus = Campus::query()->create([
            'code' => 'MUT', 'name' => 'Kampus Mutasi', 'slug' => 'kampus-mutasi', 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'B1', 'name' => 'Gedung 1', 'slug' => 'gedung-1-mut', 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $fromRoom = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R01', 'name' => 'Ruang Asal', 'room_type' => 'Lab', 'capacity' => 10, 'is_active' => true,
        ]);
        $toRoom = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R02', 'name' => 'Ruang Tujuan', 'room_type' => 'Lab', 'capacity' => 10, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'ELC', 'name' => 'Elektronik', 'slug' => 'elektronik-mut', 'is_active' => true,
        ]);

        $item = InventoryItem::query()->create([
            'inventory_number' => 'INV-MUT-001',
            'barcode' => 'INV-MUT-001',
            'name' => 'Laptop Mutasi',
            'category_id' => $category->id,
            'room_id' => $fromRoom->id,
            'condition' => InventoryCondition::Bagus,
            'status' => InventoryStatus::Aktif,
            'quantity' => 1,
            'unit' => 'unit',
        ]);

        return [$item, $fromRoom, $toRoom];
    }

    protected function makeStockOpname(StockOpnameStatus $status, ?int $createdBy = null): StockOpname
    {
        $campus = Campus::query()->first() ?? Campus::query()->create([
            'code' => 'SOP', 'name' => 'Kampus Opname', 'slug' => 'kampus-opname', 'is_active' => true,
        ]);

        return StockOpname::query()->create([
            'title' => 'Opname '.$status->value,
            'campus_id' => $campus->id,
            'status' => $status,
            'created_by' => $createdBy ?? $this->makeSuperAdmin()->id,
            'started_at' => $status === StockOpnameStatus::Draft ? null : now()->subHour(),
            'completed_at' => $status === StockOpnameStatus::Completed ? now() : null,
            'notes' => null,
        ]);
    }
}
