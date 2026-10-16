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
use App\Notifications\OperationalBorrowingNotification;
use App\Services\RoomBorrowingService;
use App\Services\VehicleBorrowingService;
use App\Support\RoleLabels;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class DriverObRoleAndNotificationTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_seeder_creates_driver_and_ob_with_viewer_permissions(): void
    {
        $this->seedRoles();

        foreach (['driver', 'ob'] as $roleName) {
            $role = Role::findByName($roleName, 'web');
            $this->assertTrue($role->hasPermissionTo('borrowing.create'));
            $this->assertTrue($role->hasPermissionTo('room-borrowing.create'));
            $this->assertTrue($role->hasPermissionTo('vehicle-borrowing.create'));
            $this->assertFalse($role->hasPermissionTo('borrowing.approve'));
            $this->assertFalse($role->hasPermissionTo('user.manage'));
        }

        $this->assertSame('Driver', RoleLabels::label('driver'));
        $this->assertSame('OB', RoleLabels::label('ob'));
    }

    public function test_driver_can_open_borrowing_forms_but_not_user_management(): void
    {
        $driver = $this->makeDriver(['email' => 'driver1@undira.ac.id']);

        $this->actingAs($driver)->get(route('borrowings.create'))->assertOk();
        $this->actingAs($driver)->get(route('room-borrowings.create'))->assertOk();
        $this->actingAs($driver)->get(route('vehicle-borrowings.create'))->assertOk();
        $this->actingAs($driver)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_driver_and_ob_see_approved_history_across_requesters(): void
    {
        $viewer = $this->makeViewer(['email' => 'owner.hist@undira.ac.id']);
        $driver = $this->makeDriver(['email' => 'driver.hist@undira.ac.id']);
        $ob = $this->makeOb(['email' => 'ob.hist@undira.ac.id']);
        $place = $this->place('Hist');

        RoomBorrowing::query()->create([
            'room_id' => $place['room']->id,
            'campus_id' => $place['campus']->id,
            'borrower_name' => 'Pemilik',
            'borrower_email' => 'owner.hist@undira.ac.id',
            'borrower_phone' => '081234567890',
            'activity_type' => 'rapat',
            'purpose' => 'Riwayat disetujui',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'status' => FacilityRequestStatus::Disetujui,
            'requested_by' => $viewer->id,
            'approved_at' => now(),
            'approval_number' => 'APR-ROOM-HIST',
        ]);

        RoomBorrowing::query()->create([
            'room_id' => $place['room']->id,
            'campus_id' => $place['campus']->id,
            'borrower_name' => 'Pending',
            'borrower_email' => 'pending.hist@undira.ac.id',
            'borrower_phone' => '081234567891',
            'activity_type' => 'rapat',
            'purpose' => 'Belum ACC',
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'status' => FacilityRequestStatus::MenungguPersetujuan,
            'requested_by' => $viewer->id,
        ]);

        $this->actingAs($driver)
            ->get(route('borrowing-records.index'))
            ->assertOk()
            ->assertSee('APR-ROOM-HIST')
            ->assertDontSee('Belum ACC');

        $this->actingAs($ob)
            ->get(route('borrowing-records.index'))
            ->assertOk()
            ->assertSee('APR-ROOM-HIST')
            ->assertDontSee('Belum ACC');

        $this->actingAs($viewer)
            ->get(route('borrowing-records.index'))
            ->assertOk()
            ->assertSee('APR-ROOM-HIST')
            ->assertSee('Belum ACC');
    }

    public function test_vehicle_submission_notifies_active_drivers(): void
    {
        Notification::fake();
        $admin = $this->makeAdmin(['email' => 'admin.veh@undira.ac.id']);
        $driver = $this->makeDriver(['email' => 'driver.notify@undira.ac.id']);
        $inactive = $this->makeDriver(['email' => 'driver.off@undira.ac.id', 'is_active' => false]);
        $ob = $this->makeOb(['email' => 'ob.no.veh@undira.ac.id']);
        $place = $this->place('VehNotif');
        $route = OperationalRoute::query()->create(['name' => 'Rute Notif', 'is_active' => true]);

        app(VehicleBorrowingService::class)->submit($admin, [
            'campus_id' => $place['campus']->id,
            'route_id' => $route->id,
            'borrower_name' => 'Admin Proxy',
            'borrower_email' => 'proxy@undira.ac.id',
            'borrower_phone' => '081234567890',
            'purpose' => 'Antar',
            'service_type' => VehicleServiceType::DropOff->value,
            'destination' => 'Grogol',
            'passenger_count' => 1,
            'passengers' => [['name' => 'A', 'unit' => 'Biro', 'title' => 'Staff']],
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
        ]);

        Notification::assertSentTo($driver, OperationalBorrowingNotification::class);
        Notification::assertNotSentTo($inactive, OperationalBorrowingNotification::class);
        Notification::assertNotSentTo($ob, OperationalBorrowingNotification::class);
    }

    public function test_room_and_inventory_notify_ob_and_teknisi_prep_only_approved_items(): void
    {
        Notification::fake();
        $admin = $this->makeAdmin(['email' => 'admin.ops@undira.ac.id']);
        $ob = $this->makeOb(['email' => 'ob.notify@undira.ac.id']);
        $teknisi = $this->makeTeknisi(['email' => 'teknisi.notify@undira.ac.id']);
        $driver = $this->makeDriver(['email' => 'driver.no.inv@undira.ac.id']);
        $place = $this->place('Ops');
        $ok = $this->inventoryItem($place, 'INV-OK-N');
        $rej = $this->inventoryItem($place, 'INV-REJ-N');

        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $ok->id,
            'borrower_name' => 'Pemohon',
            'borrower_email' => 'pemohon@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrow_date' => now()->addDay(),
            'expected_return_date' => now()->addDays(2),
            'status' => BorrowingStatus::MenungguPersetujuan,
            'notes' => 'Uji notif',
            'requested_by' => $admin->id,
        ]);
        BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $ok->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::MenungguPersetujuan,
        ]);
        BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $rej->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::MenungguPersetujuan,
        ]);

        app(\App\Services\NotificationService::class)->notifyBorrowingCreated($borrowing);

        Notification::assertSentTo($ob, OperationalBorrowingNotification::class);
        Notification::assertSentTo($teknisi, OperationalBorrowingNotification::class);
        Notification::assertNotSentTo($driver, OperationalBorrowingNotification::class);

        Notification::fake();

        $okLine = $borrowing->items()->where('inventory_item_id', $ok->id)->first();
        $rejLine = $borrowing->items()->where('inventory_item_id', $rej->id)->first();
        $okLine->update(['status' => BorrowingItemStatus::Disetujui]);
        $rejLine->update(['status' => BorrowingItemStatus::Ditolak]);
        $borrowing->update([
            'status' => BorrowingStatus::Disetujui,
            'approved_at' => now(),
            'approval_number' => 'APR-OPS-1',
            'approved_by' => $admin->id,
        ]);

        app(\App\Services\NotificationService::class)->notifyBorrowingApproved($borrowing->fresh(['items.inventoryItem']));

        Notification::assertSentTo($teknisi, OperationalBorrowingNotification::class, function (OperationalBorrowingNotification $n) {
            $payload = $n->payload;

            return ($payload['type'] ?? '') === 'inventory_prep'
                && str_contains((string) ($payload['message'] ?? ''), '1 inventaris')
                && str_contains(implode(' ', $payload['mail_lines'] ?? []), 'INV-OK-N')
                && ! str_contains(implode(' ', $payload['mail_lines'] ?? []), 'INV-REJ-N');
        });

        app(RoomBorrowingService::class)->submit($admin, [
            'room_id' => $place['room']->id,
            'borrower_name' => 'Panitia',
            'borrower_email' => 'panitia@undira.ac.id',
            'borrower_phone' => '081234567890',
            'activity_type' => 'rapat',
            'purpose' => 'Notifikasi ruang',
            'participant_internal' => '1',
            'participant_internal_count' => 5,
            'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDays(3)->addHours(2)->format('Y-m-d\TH:i'),
        ], UploadedFile::fake()->create('persetujuan.pdf', 40, 'application/pdf'));

        Notification::assertSentTo($ob, OperationalBorrowingNotification::class);
    }

    public function test_user_management_lists_driver_and_ob_roles(): void
    {
        $super = $this->makeSuperAdmin();

        $html = $this->actingAs($super)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('value="driver"', $html);
        $this->assertStringContainsString('value="ob"', $html);
        $this->assertStringContainsString('>Driver</span>', $html);
        $this->assertStringContainsString('>OB</span>', $html);
    }

    /**
     * @return array{campus: Campus, building: Building, floor: Floor, room: Room}
     */
    private function place(string $suffix): array
    {
        $campus = Campus::query()->create([
            'code' => 'N'.substr(md5($suffix.uniqid()), 0, 3),
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
            'name' => 'Kategori Notif',
            'slug' => 'kategori-notif-'.uniqid(),
            'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id,
            'code' => 'SUB'.substr(uniqid(), -4),
            'name' => 'Sub Notif',
            'slug' => 'sub-notif-'.uniqid(),
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
