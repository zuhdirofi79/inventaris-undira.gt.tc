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
use App\Models\OperationalDriver;
use App\Models\OperationalRoute;
use App\Models\OperationalVehicle;
use App\Models\Room;
use App\Models\RoomBorrowing;
use App\Models\SubCategory;
use App\Models\VehicleBorrowing;
use App\Services\DashboardBorrowingScheduleService;
use App\Services\OnlinePresenceService;
use Illuminate\Support\Carbon;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class DashboardScheduleAndPresenceTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_viewer_dashboard_shows_borrowing_shortcuts_and_widgets(): void
    {
        $viewer = $this->makeViewer(['name' => 'Viewer Dashboard', 'email' => 'viewer.dash@undira.ac.id']);

        $this->actingAs($viewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pinjam Inventaris')
            ->assertSee('Pinjam Ruangan')
            ->assertSee('Pinjam Kendaraan')
            ->assertSee(route('borrowings.create'), false)
            ->assertSee(route('room-borrowings.create'), false)
            ->assertSee(route('vehicle-borrowings.create'), false)
            ->assertSee('Peminjaman Hari Ini')
            ->assertSee('Jadwal Peminjaman Mendatang')
            ->assertSee('Pengguna Online')
            ->assertDontSee('Tambah Inventaris')
            ->assertDontSee(route('room-borrowings.approvals'), false);
    }

    public function test_admin_dashboard_keeps_existing_shortcuts(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pinjam Inventaris')
            ->assertSee('Pinjam Ruangan')
            ->assertSee('Pinjam Kendaraan')
            ->assertSee('Jelajahi Lokasi')
            ->assertSee('Tambah Inventaris')
            ->assertSee('Status Inventaris')
            ->assertSee('Analitik');
    }

    public function test_today_widget_shows_only_approved_inventory_items(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 10:00:00', 'Asia/Jakarta'));

        $place = $this->place('Sched Inv');
        $itemOk = $this->inventoryItem($place, 'INV-OK');
        $itemRejected = $this->inventoryItem($place, 'INV-REJ');
        $itemPending = $this->inventoryItem($place, 'INV-PEND');

        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $itemOk->id,
            'borrower_name' => 'Pemohon',
            'borrower_email' => 'pemohon@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrow_date' => '2026-10-16 09:00:00',
            'expected_return_date' => '2026-10-16 15:00:00',
            'status' => BorrowingStatus::Disetujui,
            'notes' => 'Uji jadwal',
        ]);

        BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $itemOk->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::Disetujui,
        ]);
        BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $itemRejected->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::Ditolak,
        ]);
        BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $itemPending->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::MenungguPersetujuan,
        ]);

        $today = app(DashboardBorrowingScheduleService::class)->today();
        $titles = collect($today)->pluck('title')->implode(' | ');

        $this->assertStringContainsString('INV-OK', $titles);
        $this->assertStringNotContainsString('INV-REJ', $titles);
        $this->assertStringNotContainsString('INV-PEND', $titles);

        Carbon::setTestNow();
    }

    public function test_today_and_upcoming_include_approved_room_and_vehicle(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 08:00:00', 'Asia/Jakarta'));
        $place = $this->place('Sched Fac');
        $route = OperationalRoute::query()->create(['name' => 'Rute Dash', 'is_active' => true]);
        $vehicle = OperationalVehicle::query()->create([
            'name' => 'Xpander',
            'plate_number' => 'B 1234 UND',
            'category' => 'MPV',
            'passenger_capacity' => 7,
            'is_active' => true,
        ]);
        $driver = OperationalDriver::query()->create([
            'name' => 'Pak Sopir',
            'phone' => '081200000001',
            'is_active' => true,
        ]);

        RoomBorrowing::query()->create([
            'room_id' => $place['room']->id,
            'campus_id' => $place['campus']->id,
            'borrower_name' => 'Panitia',
            'borrower_email' => 'panitia@undira.ac.id',
            'borrower_phone' => '081234567890',
            'activity_type' => 'rapat',
            'purpose' => 'Rapat hari ini',
            'starts_at' => '2026-10-16 09:00:00',
            'ends_at' => '2026-10-16 11:00:00',
            'status' => FacilityRequestStatus::Disetujui,
            'requested_by' => $this->makeViewer(['email' => 'room.req@undira.ac.id'])->id,
        ]);

        RoomBorrowing::query()->create([
            'room_id' => $place['room']->id,
            'campus_id' => $place['campus']->id,
            'borrower_name' => 'Pending Room',
            'borrower_email' => 'pending.room@undira.ac.id',
            'borrower_phone' => '081234567891',
            'activity_type' => 'rapat',
            'purpose' => 'Belum disetujui',
            'starts_at' => '2026-10-16 13:00:00',
            'ends_at' => '2026-10-16 15:00:00',
            'status' => FacilityRequestStatus::MenungguPersetujuan,
            'requested_by' => $this->makeViewer(['email' => 'room.pend@undira.ac.id'])->id,
        ]);

        VehicleBorrowing::query()->create([
            'campus_id' => $place['campus']->id,
            'route_id' => $route->id,
            'borrower_name' => 'Tamu',
            'borrower_email' => 'tamu@undira.ac.id',
            'borrower_phone' => '081234567892',
            'purpose' => 'Antar dokumen',
            'service_type' => VehicleServiceType::DropOff,
            'origin' => 'Kampus Cibubur',
            'destination' => 'Tanjung Duren',
            'passenger_count' => 1,
            'passengers' => [['name' => 'Tamu', 'unit' => 'Biro', 'title' => 'Staff']],
            'starts_at' => '2026-10-17 10:00:00',
            'ends_at' => '2026-10-17 12:00:00',
            'status' => FacilityRequestStatus::Disetujui,
            'assigned_vehicle_id' => $vehicle->id,
            'assigned_driver_id' => $driver->id,
            'requested_by' => $this->makeViewer(['email' => 'veh.req@undira.ac.id'])->id,
        ]);

        VehicleBorrowing::query()->create([
            'campus_id' => $place['campus']->id,
            'route_id' => $route->id,
            'borrower_name' => 'GoCar User',
            'borrower_email' => 'gocar@undira.ac.id',
            'borrower_phone' => '081234567893',
            'purpose' => 'Layanan eksternal',
            'service_type' => VehicleServiceType::DropOff,
            'origin' => 'Kampus Cibubur',
            'destination' => 'Grogol',
            'passenger_count' => 1,
            'passengers' => [['name' => 'Go', 'unit' => 'Biro', 'title' => 'Staff']],
            'starts_at' => '2026-10-16 14:00:00',
            'ends_at' => '2026-10-16 16:00:00',
            'status' => FacilityRequestStatus::Disetujui,
            'assigned_service' => VehicleServiceType::GoCar,
            'requested_by' => $this->makeViewer(['email' => 'veh.gocar@undira.ac.id'])->id,
        ]);

        $service = app(DashboardBorrowingScheduleService::class);
        $today = collect($service->today());
        $upcoming = collect($service->upcoming())->flatMap(fn (array $day) => $day['entries']);

        $this->assertTrue($today->contains(fn (array $e) => $e['type'] === 'ruangan' && str_contains($e['title'], $place['room']->name)));
        $this->assertFalse($today->contains(fn (array $e) => ($e['meta'] ?? '') === 'Pending Room' || str_contains((string) ($e['meta'] ?? ''), 'Pending Room')));
        $this->assertTrue($today->contains(fn (array $e) => $e['type'] === 'kendaraan' && str_contains($e['title'], 'GoCar')));
        $this->assertTrue($upcoming->contains(fn (array $e) => $e['type'] === 'kendaraan' && str_contains($e['title'], 'Xpander') && str_contains($e['title'], 'B 1234 UND')));

        Carbon::setTestNow();
    }

    public function test_pending_and_rejected_do_not_appear_in_upcoming(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 08:00:00', 'Asia/Jakarta'));
        $place = $this->place('Sched Pend');

        RoomBorrowing::query()->create([
            'room_id' => $place['room']->id,
            'campus_id' => $place['campus']->id,
            'borrower_name' => 'Ditolak',
            'borrower_email' => 'ditolak@undira.ac.id',
            'borrower_phone' => '081234567890',
            'activity_type' => 'rapat',
            'purpose' => 'Ditolak',
            'starts_at' => '2026-10-18 09:00:00',
            'ends_at' => '2026-10-18 11:00:00',
            'status' => FacilityRequestStatus::Ditolak,
            'requested_by' => $this->makeViewer(['email' => 'rej@undira.ac.id'])->id,
        ]);

        $upcoming = collect(app(DashboardBorrowingScheduleService::class)->upcoming())
            ->flatMap(fn (array $day) => $day['entries']);

        $this->assertFalse($upcoming->contains(fn (array $e) => str_contains((string) ($e['meta'] ?? ''), 'Ditolak')));

        Carbon::setTestNow();
    }

    public function test_online_presence_uses_last_seen_window_not_last_login_only(): void
    {
        $active = $this->makeViewer([
            'name' => 'Aktif Sekarang',
            'email' => 'aktif@undira.ac.id',
            'last_login_at' => now()->subDays(2),
            'last_seen_at' => now()->subMinutes(1),
        ]);
        $stale = $this->makeAdmin([
            'name' => 'Pernah Login',
            'email' => 'stale@undira.ac.id',
            'last_login_at' => now()->subMinutes(2),
            'last_seen_at' => now()->subMinutes(20),
        ]);

        $snapshot = app(OnlinePresenceService::class)->dashboardSnapshot();
        $names = collect($snapshot['users'])->pluck('name');

        $this->assertTrue($names->contains('Aktif Sekarang'));
        $this->assertFalse($names->contains('Pernah Login'));
        $this->assertSame(1, $snapshot['total']);
        $this->assertNotNull($active->id);
        $this->assertNotNull($stale->id);
    }

    public function test_heartbeat_updates_last_seen_for_authenticated_user(): void
    {
        $user = $this->makeViewer(['email' => 'hb@undira.ac.id', 'last_seen_at' => null]);

        $this->actingAs($user)
            ->postJson(route('presence.heartbeat'))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertNotNull($user->fresh()->last_seen_at);
    }

    public function test_dashboard_empty_states_render_without_error(): void
    {
        $viewer = $this->makeViewer(['email' => 'empty.dash@undira.ac.id']);

        $this->actingAs($viewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tidak ada peminjaman terjadwal untuk hari ini.')
            ->assertSee('Tidak ada jadwal peminjaman yang disetujui untuk periode mendatang.');
    }

    /**
     * @return array{campus: Campus, building: Building, floor: Floor, room: Room}
     */
    private function place(string $suffix): array
    {
        $campus = Campus::query()->create([
            'code' => 'D'.substr(md5($suffix.uniqid()), 0, 3),
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
        static $categoryId = null;
        static $subId = null;

        if ($categoryId === null) {
            $category = Category::query()->create([
                'code' => 'CAT'.substr(uniqid(), -4),
                'name' => 'Kategori Dash',
                'slug' => 'kategori-dash-'.uniqid(),
                'is_active' => true,
            ]);
            $sub = SubCategory::query()->create([
                'category_id' => $category->id,
                'code' => 'SUB'.substr(uniqid(), -4),
                'name' => 'Sub Dash',
                'slug' => 'sub-dash-'.uniqid(),
                'is_active' => true,
            ]);
            $categoryId = $category->id;
            $subId = $sub->id;
        }

        return InventoryItem::query()->create([
            'name' => 'Barang '.$number,
            'inventory_number' => $number,
            'barcode' => 'BC-'.$number.'-'.uniqid(),
            'category_id' => $categoryId,
            'sub_category_id' => $subId,
            'room_id' => $place['room']->id,
            'status' => InventoryStatus::Aktif,
            'condition' => InventoryCondition::Bagus,
            'quantity' => 1,
            'unit' => 'unit',
            'responsible_person' => 'PIC',
        ]);
    }
}
