<?php

namespace Tests\Feature;

use App\Enums\BorrowingStatus;
use App\Enums\FacilityRequestStatus;
use App\Enums\InventoryStatus;
use App\Enums\VehicleServiceType;
use App\Models\Borrowing;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\OperationalDriver;
use App\Models\OperationalVehicle;
use App\Models\Room;
use App\Models\RoomBorrowing;
use App\Models\SubCategory;
use App\Models\VehicleBorrowing;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class BorrowingHistoryTrashTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_super_admin_can_soft_delete_all_borrowing_kinds_into_trash(): void
    {
        $super = $this->makeSuperAdmin(['email' => 'trash-borrow-super@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'trash-borrow-admin@undira.ac.id']);
        [$campus, $room, $item] = $this->seedPlace('Trash Hist');

        $inventory = $this->inventoryLoan($super, $item, 'Hist Inventaris');
        $roomLoan = $this->roomLoan($super, $campus, $room, 'Hist Ruangan');
        $vehicleLoan = $this->vehicleLoan($super, $campus, 'Hist Kendaraan');

        $this->actingAs($admin)
            ->post(route('borrowing-records.trash', ['inventaris', $inventory->id]))
            ->assertForbidden();

        $this->actingAs($super)
            ->get(route('borrowing-records.index'))
            ->assertOk()
            ->assertSee('Hist Inventaris')
            ->assertSee('Hist Ruangan')
            ->assertSee('Hist Kendaraan')
            ->assertSee('Pindahkan ke Trash', false);

        foreach ([
            ['inventaris', $inventory->id, 'Hist Inventaris'],
            ['ruangan', $roomLoan->id, 'Hist Ruangan'],
            ['kendaraan', $vehicleLoan->id, 'Hist Kendaraan'],
        ] as [$kind, $id, $borrower]) {
            $this->actingAs($super)
                ->post(route('borrowing-records.trash', [$kind, $id]))
                ->assertRedirect();
        }

        $this->assertSoftDeleted($inventory);
        $this->assertSoftDeleted($roomLoan);
        $this->assertSoftDeleted($vehicleLoan);
        $this->assertNotNull($inventory->fresh()->deleted_by);
        $this->assertSame($super->id, $inventory->fresh()->deleted_by);

        $active = $this->actingAs($super)->get(route('borrowing-records.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Hist Inventaris', $active);
        $this->assertStringNotContainsString('Hist Ruangan', $active);
        $this->assertStringNotContainsString('Hist Kendaraan', $active);

        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'deleted_at' => null]);

        $trash = $this->actingAs($super)
            ->get(route('trash.index', ['tab' => 'riwayat_peminjaman']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Riwayat Peminjaman', $trash);
        $this->assertStringContainsString('Hist Inventaris', $trash);
        $this->assertStringContainsString('Hist Ruangan', $trash);
        $this->assertStringContainsString('Hist Kendaraan', $trash);
        $this->assertStringContainsString('Peminjaman Inventaris', $trash);
        $this->assertStringContainsString('Peminjaman Ruangan', $trash);
        $this->assertStringContainsString('Peminjaman Kendaraan Operasional', $trash);
    }

    public function test_trashed_borrowings_are_excluded_from_reports_and_can_be_restored(): void
    {
        $super = $this->makeSuperAdmin(['email' => 'trash-borrow-report@undira.ac.id']);
        [$campus, $room] = $this->seedPlace('Trash Rpt');
        $roomLoan = $this->roomLoan($super, $campus, $room, 'Ruang Laporan Trash');
        $vehicleLoan = $this->vehicleLoan($super, $campus, 'Kendaraan Laporan Trash');

        $this->actingAs($super)->post(route('borrowing-records.trash', ['ruangan', $roomLoan->id]));
        $this->actingAs($super)->post(route('borrowing-records.trash', ['kendaraan', $vehicleLoan->id]));

        $roomReport = $this->actingAs($super)
            ->getJson(route('reports.data', 'peminjaman-ruangan'))
            ->assertOk()
            ->json();
        $this->assertFalse(collect($roomReport['data'])->contains(fn ($row) => ($row['borrower'] ?? '') === 'Ruang Laporan Trash'));

        $vehicleReport = $this->actingAs($super)
            ->getJson(route('reports.data', 'peminjaman-kendaraan'))
            ->assertOk()
            ->json();
        $this->assertFalse(collect($vehicleReport['data'])->contains(fn ($row) => ($row['borrower'] ?? '') === 'Kendaraan Laporan Trash'));

        $this->actingAs($super)
            ->post(route('trash.restore', ['peminjaman_ruangan', $roomLoan->id]))
            ->assertRedirect();

        $this->assertNull($roomLoan->fresh()->deleted_at);
        $this->actingAs($super)
            ->get(route('borrowing-records.index'))
            ->assertOk()
            ->assertSee('Ruang Laporan Trash');
    }

    public function test_force_delete_from_trash_removes_transaction_not_masters_and_respects_type(): void
    {
        $super = $this->makeSuperAdmin(['email' => 'trash-borrow-force@undira.ac.id']);
        [$campusA, $roomA] = $this->seedPlace('Force A');
        [$campusB, $roomB] = $this->seedPlace('Force B');

        $roomLoan = $this->roomLoan($super, $campusA, $roomA, 'Force Room');
        $vehicle = OperationalVehicle::query()->create([
            'name' => 'Force Car',
            'plate_number' => 'B 999 TRH',
            'category' => 'MPV',
            'is_active' => true,
        ]);
        $vehicleLoan = $this->vehicleLoan($super, $campusB, 'Force Vehicle', $vehicle->id);

        // Same numeric id possible across tables — create room with id matching vehicle after soft delete path.
        $this->actingAs($super)->post(route('borrowing-records.trash', ['ruangan', $roomLoan->id]));
        $this->actingAs($super)->post(route('borrowing-records.trash', ['kendaraan', $vehicleLoan->id]));

        $this->actingAs($super)
            ->delete(route('trash.force-delete', ['peminjaman_ruangan', $roomLoan->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('room_borrowings', ['id' => $roomLoan->id]);
        $this->assertDatabaseHas('rooms', ['id' => $roomA->id]);
        $this->assertDatabaseHas('vehicle_borrowings', ['id' => $vehicleLoan->id]);
        $this->assertNotNull($vehicleLoan->fresh()->deleted_at);
        $this->assertDatabaseHas('operational_vehicles', ['id' => $vehicle->id]);

        $this->actingAs($this->makeAdmin(['email' => 'trash-borrow-force-admin@undira.ac.id']))
            ->delete(route('trash.force-delete', ['peminjaman_kendaraan', $vehicleLoan->id]))
            ->assertForbidden();
    }

    public function test_double_soft_delete_is_idempotent(): void
    {
        $super = $this->makeSuperAdmin(['email' => 'trash-borrow-double@undira.ac.id']);
        [$campus, $room] = $this->seedPlace('Double');
        $loan = $this->roomLoan($super, $campus, $room, 'Double Soft');

        $this->actingAs($super)->post(route('borrowing-records.trash', ['ruangan', $loan->id]))->assertRedirect();
        $deletedAt = $loan->fresh()->deleted_at;

        $this->actingAs($super)
            ->post(route('borrowing-records.trash', ['ruangan', $loan->id]))
            ->assertNotFound();

        $this->assertTrue($loan->fresh()->deleted_at->equalTo($deletedAt));
        $this->assertSame(1, RoomBorrowing::onlyTrashed()->whereKey($loan->id)->count());
    }

    /**
     * @return array{0: Campus, 1: Room, 2: InventoryItem}
     */
    private function seedPlace(string $name): array
    {
        $suffix = substr(md5($name.uniqid('', true)), 0, 8);
        $campus = Campus::query()->create([
            'code' => 'C'.$suffix, 'name' => 'Kampus '.$name, 'slug' => 'kampus-'.$suffix, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'B'.$suffix, 'name' => 'Gedung '.$name, 'slug' => 'gedung-'.$suffix, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'F'.$suffix, 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R'.$suffix, 'name' => 'Ruang '.$name, 'room_type' => 'Kelas', 'capacity' => 20, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'K'.$suffix, 'name' => 'Kat '.$name, 'slug' => 'kat-'.$suffix, 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'S'.$suffix, 'name' => 'Sub '.$name, 'slug' => 'sub-'.$suffix, 'is_active' => true,
        ]);
        $item = InventoryItem::query()->create([
            'inventory_number' => 'INV-'.$suffix,
            'name' => 'Barang '.$name,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'room_id' => $room->id,
            'status' => InventoryStatus::Aktif,
            'condition' => 'baik',
        ]);

        return [$campus, $room, $item];
    }

    private function inventoryLoan($owner, InventoryItem $item, string $borrower): Borrowing
    {
        return Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => $borrower,
            'borrower_email' => 'inv@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrow_date' => '2026-10-10 09:00:00',
            'expected_return_date' => '2026-10-11 09:00:00',
            'status' => BorrowingStatus::Dikembalikan,
            'requested_by' => $owner->id,
            'approved_by' => $owner->id,
            'approved_at' => now(),
            'approval_number' => 'PIN-TRASH-'.uniqid(),
            'return_date' => '2026-10-11',
        ]);
    }

    private function roomLoan($owner, Campus $campus, Room $room, string $borrower): RoomBorrowing
    {
        return RoomBorrowing::query()->create([
            'room_id' => $room->id,
            'campus_id' => $campus->id,
            'borrower_name' => $borrower,
            'borrower_email' => 'room@undira.ac.id',
            'borrower_phone' => '081234567890',
            'purpose' => 'Keperluan trash',
            'starts_at' => '2026-10-12 09:00:00',
            'ends_at' => '2026-10-12 11:00:00',
            'status' => FacilityRequestStatus::Disetujui,
            'requested_by' => $owner->id,
            'approved_by' => $owner->id,
            'approved_at' => now(),
            'approval_number' => 'RNG-TRASH-'.uniqid(),
        ]);
    }

    private function vehicleLoan($owner, Campus $campus, string $borrower, ?int $vehicleId = null): VehicleBorrowing
    {
        return VehicleBorrowing::query()->create([
            'campus_id' => $campus->id,
            'borrower_name' => $borrower,
            'borrower_email' => 'veh@undira.ac.id',
            'borrower_phone' => '081298765432',
            'unit' => 'Biro',
            'purpose' => 'Dinas',
            'service_type' => VehicleServiceType::DropOff,
            'origin' => 'Kampus',
            'destination' => 'Tujuan '.$borrower,
            'starts_at' => '2026-10-13 08:00:00',
            'ends_at' => '2026-10-13 10:00:00',
            'status' => FacilityRequestStatus::Disetujui,
            'assigned_vehicle_id' => $vehicleId,
            'requested_by' => $owner->id,
            'approved_by' => $owner->id,
            'approved_at' => now(),
            'approval_number' => 'KND-TRASH-'.uniqid(),
        ]);
    }
}
