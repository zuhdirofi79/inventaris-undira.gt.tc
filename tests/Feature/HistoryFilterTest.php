<?php

namespace Tests\Feature;

use App\Enums\BorrowingItemStatus;
use App\Enums\BorrowingStatus;
use App\Enums\FacilityRequestStatus;
use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Enums\VehicleServiceType;
use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\RoomBorrowing;
use App\Models\SubCategory;
use App\Models\VehicleBorrowing;
use Illuminate\Support\Carbon;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class HistoryFilterTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_inventory_approval_history_filters_by_campus_and_decision_date(): void
    {
        $admin = $this->makeAdmin(['email' => 'hist.inv@undira.ac.id']);
        $a = $this->place('HistA');
        $b = $this->place('HistB');
        $itemA = $this->inventoryItem($a, 'INV-HIST-A');
        $itemB = $this->inventoryItem($b, 'INV-HIST-B');

        $inRange = $this->approvedInventory($admin, $itemA, 'APR-HIST-A', Carbon::parse('2026-10-10 09:00:00'));
        $this->approvedInventory($admin, $itemB, 'APR-HIST-B', Carbon::parse('2026-10-10 10:00:00'));
        $this->approvedInventory($admin, $itemA, 'APR-HIST-OLD', Carbon::parse('2026-09-01 08:00:00'));

        $this->actingAs($admin)
            ->get(route('borrowing-approvals.index', [
                'campus_id' => $a['campus']->id,
                'date_from' => '2026-10-01',
                'date_to' => '2026-10-31',
            ]))
            ->assertOk()
            ->assertSee('APR-HIST-A')
            ->assertDontSee('APR-HIST-B')
            ->assertDontSee('APR-HIST-OLD')
            ->assertSee('name="campus_id"', false)
            ->assertSee('value="'.$a['campus']->id.'"', false);

        $this->actingAs($admin)
            ->get(route('borrowing-approvals.export.pdf', [
                'campus_id' => $a['campus']->id,
                'date_from' => '2026-10-01',
                'date_to' => '2026-10-31',
            ]))
            ->assertOk();

        $this->assertSame($inRange->id, Borrowing::query()->where('approval_number', 'APR-HIST-A')->value('id'));
    }

    public function test_inventory_approval_rejects_inverted_date_range(): void
    {
        $admin = $this->makeAdmin(['email' => 'hist.inv.bad@undira.ac.id']);

        $this->actingAs($admin)
            ->from(route('borrowing-approvals.index'))
            ->get(route('borrowing-approvals.index', [
                'date_from' => '2026-10-20',
                'date_to' => '2026-10-01',
            ]))
            ->assertRedirect(route('borrowing-approvals.index'))
            ->assertSessionHasErrors('date_to');
    }

    public function test_room_and_vehicle_approval_history_filter_by_decision_date(): void
    {
        $admin = $this->makeAdmin(['email' => 'hist.fac@undira.ac.id']);
        $place = $this->place('FacHist');

        RoomBorrowing::query()->create([
            'room_id' => $place['room']->id,
            'campus_id' => $place['campus']->id,
            'borrower_name' => 'Room In',
            'borrower_email' => 'room.in@undira.ac.id',
            'borrower_phone' => '081234567890',
            'activity_type' => 'rapat',
            'purpose' => 'Room In Range',
            'starts_at' => Carbon::parse('2026-11-01 08:00:00'),
            'ends_at' => Carbon::parse('2026-11-01 10:00:00'),
            'status' => FacilityRequestStatus::Disetujui,
            'requested_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => Carbon::parse('2026-10-12 11:00:00'),
            'approval_number' => 'APR-ROOM-IN',
        ]);
        RoomBorrowing::query()->create([
            'room_id' => $place['room']->id,
            'campus_id' => $place['campus']->id,
            'borrower_name' => 'Room Out',
            'borrower_email' => 'room.out@undira.ac.id',
            'borrower_phone' => '081234567891',
            'activity_type' => 'rapat',
            'purpose' => 'Room Out Range',
            'starts_at' => Carbon::parse('2026-11-02 08:00:00'),
            'ends_at' => Carbon::parse('2026-11-02 10:00:00'),
            'status' => FacilityRequestStatus::Disetujui,
            'requested_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => Carbon::parse('2026-09-01 11:00:00'),
            'approval_number' => 'APR-ROOM-OUT',
        ]);

        VehicleBorrowing::query()->create([
            'campus_id' => $place['campus']->id,
            'borrower_name' => 'Veh In',
            'borrower_email' => 'veh.in@undira.ac.id',
            'borrower_phone' => '081234567892',
            'purpose' => 'Veh In Range',
            'service_type' => VehicleServiceType::DropOff,
            'origin' => 'Kampus',
            'destination' => 'Grogol',
            'passenger_count' => 1,
            'passengers' => [['name' => 'A', 'unit' => 'Biro', 'title' => 'Staff']],
            'starts_at' => Carbon::parse('2026-11-03 08:00:00'),
            'ends_at' => Carbon::parse('2026-11-03 10:00:00'),
            'status' => FacilityRequestStatus::Disetujui,
            'requested_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => Carbon::parse('2026-10-15 14:00:00'),
            'approval_number' => 'APR-VEH-IN',
        ]);
        VehicleBorrowing::query()->create([
            'campus_id' => $place['campus']->id,
            'borrower_name' => 'Veh Out',
            'borrower_email' => 'veh.out@undira.ac.id',
            'borrower_phone' => '081234567893',
            'purpose' => 'Veh Out Range',
            'service_type' => VehicleServiceType::DropOff,
            'origin' => 'Kampus',
            'destination' => 'Cibubur',
            'passenger_count' => 1,
            'passengers' => [['name' => 'B', 'unit' => 'Biro', 'title' => 'Staff']],
            'starts_at' => Carbon::parse('2026-11-04 08:00:00'),
            'ends_at' => Carbon::parse('2026-11-04 10:00:00'),
            'status' => FacilityRequestStatus::Ditolak,
            'requested_by' => $admin->id,
            'approved_by' => $admin->id,
            'rejected_at' => Carbon::parse('2026-08-01 14:00:00'),
            'approval_number' => 'APR-VEH-OUT',
            'rejection_reason' => 'Tidak tersedia',
        ]);

        $this->actingAs($admin)
            ->get(route('room-borrowings.history', [
                'date_from' => '2026-10-01',
                'date_to' => '2026-10-31',
            ]))
            ->assertOk()
            ->assertSee('APR-ROOM-IN')
            ->assertDontSee('APR-ROOM-OUT')
            ->assertSee('name="date_from"', false);

        $this->actingAs($admin)
            ->get(route('vehicle-borrowings.history', [
                'date_from' => '2026-10-01',
                'date_to' => '2026-10-31',
            ]))
            ->assertOk()
            ->assertSee('APR-VEH-IN')
            ->assertDontSee('APR-VEH-OUT')
            ->assertSee('name="date_to"', false);
    }

    public function test_combined_borrowing_records_filter_by_schedule_start_date(): void
    {
        $admin = $this->makeAdmin(['email' => 'hist.rec@undira.ac.id']);
        $place = $this->place('RecHist');
        $item = $this->inventoryItem($place, 'INV-REC-1');

        Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Inv Start In',
            'borrower_email' => 'inv.in@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrow_date' => Carbon::parse('2026-10-18 09:00:00'),
            'expected_return_date' => Carbon::parse('2026-10-19 09:00:00'),
            'status' => BorrowingStatus::MenungguPersetujuan,
            'requested_by' => $admin->id,
            'notes' => 'Rec In',
        ]);
        Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Inv Start Out',
            'borrower_email' => 'inv.out@undira.ac.id',
            'borrower_phone' => '081234567891',
            'borrow_date' => Carbon::parse('2026-09-01 09:00:00'),
            'expected_return_date' => Carbon::parse('2026-09-02 09:00:00'),
            'status' => BorrowingStatus::MenungguPersetujuan,
            'requested_by' => $admin->id,
            'notes' => 'Rec Out',
        ]);

        RoomBorrowing::query()->create([
            'room_id' => $place['room']->id,
            'campus_id' => $place['campus']->id,
            'borrower_name' => 'Room Start In',
            'borrower_email' => 'room.start@undira.ac.id',
            'borrower_phone' => '081234567892',
            'activity_type' => 'rapat',
            'purpose' => 'Schedule In',
            'starts_at' => Carbon::parse('2026-10-20 08:00:00'),
            'ends_at' => Carbon::parse('2026-10-20 10:00:00'),
            'status' => FacilityRequestStatus::MenungguPersetujuan,
            'requested_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('borrowing-records.index', [
                'date_from' => '2026-10-15',
                'date_to' => '2026-10-25',
            ]))
            ->assertOk()
            ->assertSee('Inv Start In')
            ->assertSee('Room Start In')
            ->assertDontSee('Inv Start Out')
            ->assertSee('name="date_from"', false);
    }

    /**
     * @return array{campus: Campus, building: Building, floor: Floor, room: Room}
     */
    private function place(string $suffix): array
    {
        $campus = Campus::query()->create([
            'code' => 'H'.substr(md5($suffix.uniqid()), 0, 3),
            'name' => 'Kampus '.$suffix,
            'slug' => 'kampus-hist-'.uniqid(),
            'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'code' => 'B1',
            'name' => 'Gedung '.$suffix,
            'slug' => 'gedung-hist-'.uniqid(),
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
            'code' => 'R1',
            'name' => 'Ruang '.$suffix,
            'room_type' => 'Lab',
            'capacity' => 20,
            'is_active' => true,
        ]);

        return compact('campus', 'building', 'floor', 'room');
    }

    /**
     * @param  array{campus: Campus, building: Building, floor: Floor, room: Room}  $place
     */
    private function inventoryItem(array $place, string $number): InventoryItem
    {
        $category = Category::query()->create([
            'code' => 'CAT'.substr(uniqid(), -4),
            'name' => 'Kategori Hist',
            'slug' => 'kategori-hist-'.uniqid(),
            'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id,
            'code' => 'SUB'.substr(uniqid(), -4),
            'name' => 'Sub Hist',
            'slug' => 'sub-hist-'.uniqid(),
            'is_active' => true,
        ]);

        return InventoryItem::query()->create([
            'name' => 'Barang '.$number,
            'inventory_number' => $number,
            'barcode' => 'BC-'.$number.'-'.uniqid(),
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

    private function approvedInventory($admin, InventoryItem $item, string $number, Carbon $decidedAt): Borrowing
    {
        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Pemohon '.$number,
            'borrower_email' => strtolower($number).'@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrow_date' => $decidedAt->copy()->addDay(),
            'expected_return_date' => $decidedAt->copy()->addDays(2),
            'status' => BorrowingStatus::Disetujui,
            'requested_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => $decidedAt,
            'approval_number' => $number,
            'notes' => 'Hist filter',
        ]);
        BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $item->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::Disetujui,
        ]);

        return $borrowing;
    }
}
