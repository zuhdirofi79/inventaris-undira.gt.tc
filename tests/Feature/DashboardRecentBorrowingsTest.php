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
use App\Models\OperationalRoute;
use App\Models\Room;
use App\Models\RoomBorrowing;
use App\Models\SubCategory;
use App\Models\VehicleBorrowing;
use App\Services\DashboardRecentBorrowingsService;
use Illuminate\Support\Carbon;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class DashboardRecentBorrowingsTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_viewer_dashboard_shows_recent_borrowing_cards(): void
    {
        $viewer = $this->makeViewer(['email' => 'viewer.recent@undira.ac.id']);

        $this->actingAs($viewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pengajuan Peminjaman Terbaru')
            ->assertSee('Peminjaman Disetujui Terbaru')
            ->assertSee('Belum ada pengajuan peminjaman terbaru')
            ->assertSee('Belum ada peminjaman yang disetujui')
            ->assertSee(route('borrowing-records.index'), false)
            ->assertDontSee(route('room-borrowings.approvals'), false);
    }

    public function test_submissions_include_inventory_room_and_vehicle(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 12:00:00', 'Asia/Jakarta'));
        $admin = $this->makeAdmin(['email' => 'admin.recent@undira.ac.id']);
        $place = $this->place('Recent Sub');
        $item = $this->inventoryItem($place, 'INV-SUB-1');
        $route = OperationalRoute::query()->create(['name' => 'Rute Recent', 'is_active' => true]);

        Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Pemohon Inventaris',
            'borrower_email' => 'inv@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrow_date' => '2026-10-17 09:00:00',
            'expected_return_date' => '2026-10-17 12:00:00',
            'status' => BorrowingStatus::MenungguPersetujuan,
            'notes' => 'Uji',
            'requested_by' => $admin->id,
            'created_at' => now()->subMinutes(3),
        ]);

        RoomBorrowing::query()->create([
            'room_id' => $place['room']->id,
            'campus_id' => $place['campus']->id,
            'borrower_name' => 'Pemohon Ruang',
            'borrower_email' => 'room@undira.ac.id',
            'borrower_phone' => '081234567891',
            'activity_type' => 'rapat',
            'purpose' => 'Rapat koordinasi',
            'starts_at' => '2026-10-18 09:00:00',
            'ends_at' => '2026-10-18 11:00:00',
            'status' => FacilityRequestStatus::MenungguPersetujuan,
            'requested_by' => $admin->id,
            'created_at' => now()->subMinutes(2),
        ]);

        VehicleBorrowing::query()->create([
            'campus_id' => $place['campus']->id,
            'route_id' => $route->id,
            'borrower_name' => 'Pemohon Kendaraan',
            'borrower_email' => 'veh@undira.ac.id',
            'borrower_phone' => '081234567892',
            'purpose' => 'Antar dokumen',
            'service_type' => VehicleServiceType::DropOff,
            'origin' => 'Cibubur',
            'destination' => 'Tanjung Duren',
            'passenger_count' => 1,
            'passengers' => [['name' => 'A', 'unit' => 'Biro', 'title' => 'Staff']],
            'starts_at' => '2026-10-19 09:00:00',
            'ends_at' => '2026-10-19 11:00:00',
            'status' => FacilityRequestStatus::MenungguPersetujuan,
            'requested_by' => $admin->id,
            'created_at' => now()->subMinute(),
        ]);

        $payload = app(DashboardRecentBorrowingsService::class)->forUser($admin);
        $types = collect($payload['submissions'])->pluck('type')->all();

        $this->assertContains('inventaris', $types);
        $this->assertContains('ruangan', $types);
        $this->assertContains('kendaraan', $types);
        $this->assertTrue(collect($payload['submissions'])->contains(
            fn (array $row) => $row['type'] === 'kendaraan' && str_contains($row['detail'], 'Drop-off')
        ));
        $this->assertSame([], array_filter($payload['approved'], fn ($r) => ($r['status_label'] ?? '') === 'Menunggu Persetujuan'));

        Carbon::setTestNow();
    }

    public function test_approved_inventory_counts_only_approved_items(): void
    {
        $admin = $this->makeAdmin(['email' => 'admin.partial@undira.ac.id']);
        $place = $this->place('Partial');
        $ok = $this->inventoryItem($place, 'INV-OK-P');
        $rej = $this->inventoryItem($place, 'INV-REJ-P');
        $pend = $this->inventoryItem($place, 'INV-PEND-P');

        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $ok->id,
            'borrower_name' => 'Sebagian',
            'borrower_email' => 'sebagian@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrow_date' => now()->addDay()->format('Y-m-d H:i:s'),
            'expected_return_date' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'status' => BorrowingStatus::Disetujui,
            'notes' => 'Partial',
            'requested_by' => $admin->id,
            'approved_at' => now(),
            'approval_number' => 'APR-PARTIAL-1',
        ]);

        BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $ok->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::Disetujui,
        ]);
        BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $rej->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::Ditolak,
        ]);
        BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $pend->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::MenungguPersetujuan,
        ]);

        $approved = collect(app(DashboardRecentBorrowingsService::class)->forUser($admin)['approved']);
        $row = $approved->first(fn (array $r) => $r['type'] === 'inventaris' && str_contains($r['number'], 'APR-PARTIAL-1'));

        $this->assertNotNull($row);
        $this->assertStringContainsString('Barang INV-OK-P', $row['detail']);
        $this->assertStringNotContainsString('INV-REJ-P', $row['detail']);
        $this->assertStringNotContainsString('INV-PEND-P', $row['detail']);
        $this->assertStringContainsString('1 item disetujui', (string) $row['meta']);
    }

    public function test_rejected_appears_in_submissions_not_approved(): void
    {
        $admin = $this->makeAdmin(['email' => 'admin.reject@undira.ac.id']);
        $place = $this->place('Reject');

        RoomBorrowing::query()->create([
            'room_id' => $place['room']->id,
            'campus_id' => $place['campus']->id,
            'borrower_name' => 'Ditolak Room',
            'borrower_email' => 'rej@undira.ac.id',
            'borrower_phone' => '081234567890',
            'activity_type' => 'rapat',
            'purpose' => 'Ditolak',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'status' => FacilityRequestStatus::Ditolak,
            'requested_by' => $admin->id,
            'rejected_at' => now(),
            'created_at' => now(),
        ]);

        $payload = app(DashboardRecentBorrowingsService::class)->forUser($admin);

        $this->assertTrue(collect($payload['submissions'])->contains(
            fn (array $row) => $row['type'] === 'ruangan' && $row['borrower'] === 'Ditolak Room'
        ));
        $this->assertFalse(collect($payload['approved'])->contains(
            fn (array $row) => ($row['borrower'] ?? '') === 'Ditolak Room'
        ));
    }

    public function test_approved_vehicle_uses_assigned_service_without_fake_plate(): void
    {
        $admin = $this->makeAdmin(['email' => 'admin.gocar@undira.ac.id']);
        $place = $this->place('GoCar');
        $route = OperationalRoute::query()->create(['name' => 'Rute GoCar', 'is_active' => true]);

        VehicleBorrowing::query()->create([
            'campus_id' => $place['campus']->id,
            'route_id' => $route->id,
            'borrower_name' => 'GoCar User',
            'borrower_email' => 'gocar.recent@undira.ac.id',
            'borrower_phone' => '081234567893',
            'purpose' => 'Layanan eksternal',
            'service_type' => VehicleServiceType::DropOff,
            'origin' => 'Cibubur',
            'destination' => 'Grogol',
            'passenger_count' => 1,
            'passengers' => [['name' => 'Go', 'unit' => 'Biro', 'title' => 'Staff']],
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'status' => FacilityRequestStatus::Disetujui,
            'assigned_service' => VehicleServiceType::GoCar,
            'requested_by' => $admin->id,
            'approved_at' => now(),
            'approval_number' => 'APR-GOCAR-1',
        ]);

        $row = collect(app(DashboardRecentBorrowingsService::class)->forUser($admin)['approved'])
            ->first(fn (array $r) => $r['type'] === 'kendaraan');

        $this->assertNotNull($row);
        $this->assertStringContainsString('GoCar', $row['detail']);
        $this->assertStringNotContainsString('B ', $row['detail']);
    }

    public function test_semua_links_point_to_borrowing_records(): void
    {
        $admin = $this->makeAdmin(['email' => 'admin.links@undira.ac.id']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('borrowing-records.index'), false)
            ->assertSee(route('borrowing-records.index', ['status' => 'disetujui']), false);
    }

    public function test_admin_dashboard_keeps_existing_sections(): void
    {
        $admin = $this->makeAdmin(['email' => 'admin.reg@undira.ac.id']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Aksi Cepat')
            ->assertSee('Status Inventaris')
            ->assertSee('Peminjaman Hari Ini')
            ->assertSee('Pengajuan Peminjaman Terbaru')
            ->assertSee('Analitik')
            ->assertSee('Inventaris Terbaru');
    }

    /**
     * @return array{campus: Campus, building: Building, floor: Floor, room: Room}
     */
    private function place(string $suffix): array
    {
        $campus = Campus::query()->create([
            'code' => 'R'.substr(md5($suffix.uniqid()), 0, 3),
            'name' => 'Kampus '.$suffix,
            'slug' => 'kampus-'.uniqid(),
            'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'code' => 'B1',
            'name' => 'Gedung '.$suffix,
            'slug' => 'gedung-'.uniqid(),
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
            'name' => 'Kategori Recent',
            'slug' => 'kategori-recent-'.uniqid(),
            'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id,
            'code' => 'SUB'.substr(uniqid(), -4),
            'name' => 'Sub Recent',
            'slug' => 'sub-recent-'.uniqid(),
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
}
