<?php

namespace Tests\Feature;

use App\Enums\BorrowingItemStatus;
use App\Enums\BorrowingStatus;
use App\Enums\FacilityRequestStatus;
use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Enums\VehicleServiceType;
use App\Mail\BorrowingCancelledMail;
use App\Mail\RoomBorrowingCancellationMail;
use App\Mail\VehicleBorrowingCancellationMail;
use App\Models\Borrowing;
use App\Models\BorrowingItem;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class ApprovalCancellationTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_admin_can_cancel_room_approval_within_24_hours(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-17 12:00:00', 'Asia/Jakarta'));

        $admin = $this->makeAdmin();
        $place = $this->place('Cancel Room');
        $borrowing = RoomBorrowing::query()->create([
            'room_id' => $place['room']->id,
            'campus_id' => $place['campus']->id,
            'borrower_name' => 'Peminjam Ruang',
            'borrower_email' => 'ruang.cancel@undira.ac.id',
            'borrower_phone' => '081234567890',
            'purpose' => 'Rapat',
            'starts_at' => '2026-10-20 09:00:00',
            'ends_at' => '2026-10-20 11:00:00',
            'status' => FacilityRequestStatus::Disetujui,
            'approval_number' => 'RNG-CANCEL-001',
            'requested_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => Carbon::parse('2026-10-17 09:48:00', 'Asia/Jakarta'),
        ]);

        $this->actingAs($admin)
            ->post(route('room-borrowings.cancel-approval', $borrowing), [
                'cancellation_reason' => 'Jadwal diubah oleh unit.',
            ])
            ->assertRedirect(route('room-borrowings.history'))
            ->assertSessionHas('success');

        $borrowing->refresh();
        $this->assertSame(FacilityRequestStatus::PersetujuanDibatalkan, $borrowing->status);
        $this->assertNotNull($borrowing->cancelled_at);
        $this->assertSame($admin->id, $borrowing->cancelled_by);
        $this->assertSame('Jadwal diubah oleh unit.', $borrowing->cancellation_reason);
        $this->assertSame('RNG-CANCEL-001', $borrowing->approval_number);
        Mail::assertSent(RoomBorrowingCancellationMail::class);

        Carbon::setTestNow();
    }

    public function test_room_cancel_rejected_after_24_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-18 09:48:01', 'Asia/Jakarta'));

        $admin = $this->makeAdmin();
        $place = $this->place('Late Room');
        $borrowing = RoomBorrowing::query()->create([
            'room_id' => $place['room']->id,
            'campus_id' => $place['campus']->id,
            'borrower_name' => 'Peminjam',
            'borrower_email' => 'late@undira.ac.id',
            'borrower_phone' => '081234567890',
            'purpose' => 'Rapat',
            'starts_at' => '2026-10-25 09:00:00',
            'ends_at' => '2026-10-25 11:00:00',
            'status' => FacilityRequestStatus::Disetujui,
            'approval_number' => 'RNG-LATE-001',
            'requested_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => Carbon::parse('2026-10-17 09:48:00', 'Asia/Jakarta'),
        ]);

        $this->actingAs($admin)
            ->from(route('room-borrowings.history'))
            ->post(route('room-borrowings.cancel-approval', $borrowing), [
                'cancellation_reason' => 'Terlambat membatalkan.',
            ])
            ->assertSessionHasErrors('cancellation_reason');

        $this->assertSame(FacilityRequestStatus::Disetujui, $borrowing->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_room_cancel_rejected_when_schedule_already_started(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 10:00:00', 'Asia/Jakarta'));

        $admin = $this->makeAdmin();
        $place = $this->place('Started Room');
        $borrowing = RoomBorrowing::query()->create([
            'room_id' => $place['room']->id,
            'campus_id' => $place['campus']->id,
            'borrower_name' => 'Peminjam',
            'borrower_email' => 'started@undira.ac.id',
            'borrower_phone' => '081234567890',
            'purpose' => 'Rapat',
            'starts_at' => '2026-10-17 09:00:00',
            'ends_at' => '2026-10-17 15:00:00',
            'status' => FacilityRequestStatus::Disetujui,
            'approval_number' => 'RNG-START-001',
            'requested_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => Carbon::parse('2026-10-17 08:00:00', 'Asia/Jakarta'),
        ]);

        $this->actingAs($admin)
            ->from(route('room-borrowings.history'))
            ->post(route('room-borrowings.cancel-approval', $borrowing), [
                'cancellation_reason' => 'Sudah mulai.',
            ])
            ->assertSessionHasErrors('cancellation_reason');

        $this->assertSame(FacilityRequestStatus::Disetujui, $borrowing->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_viewer_cannot_cancel_room_approval(): void
    {
        $viewer = $this->makeViewer();
        $admin = $this->makeAdmin(['email' => 'admin-room-deny@undira.ac.id']);
        $place = $this->place('Deny Room');
        $borrowing = RoomBorrowing::query()->create([
            'room_id' => $place['room']->id,
            'campus_id' => $place['campus']->id,
            'borrower_name' => 'Peminjam',
            'borrower_email' => 'deny@undira.ac.id',
            'borrower_phone' => '081234567890',
            'purpose' => 'Rapat',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'status' => FacilityRequestStatus::Disetujui,
            'approval_number' => 'RNG-DENY-001',
            'requested_by' => $viewer->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->post(route('room-borrowings.cancel-approval', $borrowing), [
                'cancellation_reason' => 'Tidak punya izin.',
            ])
            ->assertForbidden();
    }

    public function test_vehicle_cancel_releases_assignment_and_keeps_history_via_audit(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-17 12:00:00', 'Asia/Jakarta'));

        $admin = $this->makeAdmin();
        $campus = Campus::query()->create([
            'code' => 'CV'.uniqid(),
            'name' => 'Kampus Cancel',
            'slug' => 'kampus-cancel-'.uniqid(),
            'is_active' => true,
        ]);
        $vehicle = OperationalVehicle::query()->create([
            'name' => 'Mobil A',
            'plate_number' => 'B 1 CANCEL',
            'category' => 'MPV',
            'is_active' => true,
            'passenger_capacity' => 7,
        ]);
        $driver = OperationalDriver::query()->create([
            'name' => 'Driver Cancel',
            'phone' => '081111111111',
            'is_active' => true,
        ]);
        $borrowing = VehicleBorrowing::query()->create([
            'campus_id' => $campus->id,
            'borrower_name' => 'Peminjam Kendaraan',
            'borrower_email' => 'kendaraan.cancel@undira.ac.id',
            'borrower_phone' => '081234567890',
            'purpose' => 'Dinas',
            'service_type' => VehicleServiceType::DropOff,
            'origin' => 'Kampus A',
            'destination' => 'Kampus B',
            'starts_at' => '2026-10-20 09:00:00',
            'ends_at' => '2026-10-20 12:00:00',
            'status' => FacilityRequestStatus::Disetujui,
            'approval_number' => 'KND-CANCEL-001',
            'assigned_vehicle_id' => $vehicle->id,
            'assigned_driver_id' => $driver->id,
            'assigned_service' => VehicleServiceType::DropOff,
            'requested_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => Carbon::parse('2026-10-17 09:48:00', 'Asia/Jakarta'),
        ]);

        $this->actingAs($admin)
            ->post(route('vehicle-borrowings.cancel-approval', $borrowing), [
                'cancellation_reason' => 'Kendaraan dialihkan.',
            ])
            ->assertRedirect(route('vehicle-borrowings.history'));

        $borrowing->refresh();
        $this->assertSame(FacilityRequestStatus::PersetujuanDibatalkan, $borrowing->status);
        $this->assertNull($borrowing->assigned_vehicle_id);
        $this->assertNull($borrowing->assigned_driver_id);
        Mail::assertSent(VehicleBorrowingCancellationMail::class);

        Carbon::setTestNow();
    }

    public function test_inventory_cancel_frees_blocking_without_changing_rejected_items(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-17 12:00:00', 'Asia/Jakarta'));

        $admin = $this->makeAdmin();
        $approvedItem = $this->makeItem('INV-OK');
        $rejectedItem = $this->makeItem('INV-NO');

        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $approvedItem->id,
            'borrower_name' => 'Peminjam Inventaris',
            'borrower_email' => 'inv.cancel@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrow_date' => '2026-10-20 09:00:00',
            'expected_return_date' => '2026-10-21 09:00:00',
            'status' => BorrowingStatus::Disetujui,
            'approval_number' => 'INV-CANCEL-001',
            'requested_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => Carbon::parse('2026-10-17 09:48:00', 'Asia/Jakarta'),
        ]);

        BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $approvedItem->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::Disetujui,
        ]);
        BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $rejectedItem->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::Ditolak,
            'rejection_reason' => 'Rusak ringan',
        ]);

        $this->assertTrue(Borrowing::inventoryIsBlocked($approvedItem->id));

        $this->actingAs($admin)
            ->post(route('borrowings.cancel-approval', $borrowing), [
                'cancellation_reason' => 'Salah approval.',
            ])
            ->assertRedirect(route('borrowing-approvals.index'));

        $borrowing->refresh();
        $this->assertSame(BorrowingStatus::PersetujuanDibatalkan, $borrowing->status);
        $this->assertFalse(Borrowing::inventoryIsBlocked($approvedItem->id));

        $statuses = BorrowingItem::query()->where('borrowing_id', $borrowing->id)->pluck('status', 'inventory_item_id');
        $this->assertSame(BorrowingItemStatus::Disetujui, $statuses[$approvedItem->id]);
        $this->assertSame(BorrowingItemStatus::Ditolak, $statuses[$rejectedItem->id]);
        Mail::assertSent(BorrowingCancelledMail::class);

        Carbon::setTestNow();
    }

    public function test_inventory_cancel_rejected_when_item_already_handed_over(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 12:00:00', 'Asia/Jakarta'));

        $admin = $this->makeAdmin();
        $item = $this->makeItem('INV-HAND');
        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Peminjam',
            'borrower_email' => 'hand@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrow_date' => '2026-10-20 09:00:00',
            'expected_return_date' => '2026-10-21 09:00:00',
            'status' => BorrowingStatus::Disetujui,
            'approval_number' => 'INV-HAND-001',
            'requested_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => Carbon::parse('2026-10-17 09:48:00', 'Asia/Jakarta'),
        ]);
        BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $item->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::Dipinjam,
            'borrowed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('borrowing-approvals.index'))
            ->post(route('borrowings.cancel-approval', $borrowing), [
                'cancellation_reason' => 'Sudah diserahkan.',
            ])
            ->assertSessionHasErrors('cancellation_reason');

        $this->assertSame(BorrowingStatus::Disetujui, $borrowing->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_history_pages_show_submission_and_decision_timestamps(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 12:00:00', 'Asia/Jakarta'));

        $admin = $this->makeAdmin();
        $place = $this->place('Stamp Room');
        $borrowing = RoomBorrowing::query()->create([
            'room_id' => $place['room']->id,
            'campus_id' => $place['campus']->id,
            'borrower_name' => 'Stamp User',
            'borrower_email' => 'stamp@undira.ac.id',
            'borrower_phone' => '081234567890',
            'purpose' => 'Rapat',
            'starts_at' => '2026-10-20 09:00:00',
            'ends_at' => '2026-10-20 11:00:00',
            'status' => FacilityRequestStatus::Disetujui,
            'approval_number' => 'RNG-STAMP-001',
            'requested_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => Carbon::parse('2026-10-17 09:48:00', 'Asia/Jakarta'),
        ]);
        $borrowing->forceFill([
            'created_at' => Carbon::parse('2026-10-17 08:05:00', 'Asia/Jakarta'),
        ])->saveQuietly();

        $html = $this->actingAs($admin)
            ->get(route('room-borrowings.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Tanggal &amp; Waktu Pengajuan', $html);
        $this->assertStringContainsString('Tanggal &amp; Waktu Keputusan', $html);
        $this->assertStringContainsString('17/10/2026 08:05', $html);
        $this->assertStringContainsString('17/10/2026 09:48', $html);
        $this->assertStringContainsString('title="Batalkan Persetujuan"', $html);

        Carbon::setTestNow();
    }

    /**
     * @return array{campus: Campus, building: Building, floor: Floor, room: Room}
     */
    private function place(string $label): array
    {
        $suffix = uniqid();
        $campus = Campus::query()->create([
            'code' => 'C'.substr($suffix, -6),
            'name' => 'Kampus '.$label,
            'slug' => 'kampus-'.$suffix,
            'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'code' => 'B'.substr($suffix, -5),
            'name' => 'Gedung '.$label,
            'slug' => 'gedung-'.$suffix,
            'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id,
            'code' => 'F1',
            'name' => 'Lantai 1',
            'floor_number' => 1,
            'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id,
            'code' => 'R'.substr($suffix, -5),
            'name' => 'Ruang '.$label,
            'room_type' => 'Kelas',
            'capacity' => 20,
            'is_active' => true,
        ]);

        return compact('campus', 'building', 'floor', 'room');
    }

    private function makeItem(string $number): InventoryItem
    {
        $place = $this->place($number);
        $suffix = uniqid();
        $category = Category::query()->create([
            'code' => 'CAT'.substr($suffix, -6),
            'name' => 'Kategori '.$number,
            'slug' => 'kategori-'.$suffix,
            'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id,
            'code' => 'SUB'.substr($suffix, -6),
            'name' => 'Sub '.$number,
            'slug' => 'sub-'.$suffix,
            'is_active' => true,
        ]);

        return InventoryItem::query()->create([
            'inventory_number' => $number.'-'.$suffix,
            'barcode' => 'BC-'.$suffix,
            'name' => 'Barang '.$number,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'room_id' => $place['room']->id,
            'status' => InventoryStatus::Aktif,
            'condition' => InventoryCondition::Bagus,
            'quantity' => 1,
            'unit' => 'unit',
            'responsible_person' => 'PIC',
        ]);
    }
}
