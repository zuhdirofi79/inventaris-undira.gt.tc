<?php

namespace Tests\Feature;

use App\Enums\FacilityRequestStatus;
use App\Enums\VehicleServiceType;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Floor;
use App\Models\OperationalRoute;
use App\Models\Room;
use App\Models\RoomBorrowing;
use App\Models\VehicleBorrowing;
use App\Notifications\FacilityRequestSubmittedNotification;
use App\Notifications\OperationalBorrowingNotification;
use App\Services\RoomBorrowingService;
use App\Services\VehicleBorrowingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class FacilityApproverNotificationTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_room_submission_notifies_admin_super_admin_and_ob_not_viewer(): void
    {
        Notification::fake();
        $viewer = $this->makeViewer(['email' => 'viewer.room.notif@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin.room.notif@undira.ac.id']);
        $super = $this->makeSuperAdmin(['email' => 'super.room.notif@undira.ac.id']);
        $ob = $this->makeOb(['email' => 'ob.room.notif@undira.ac.id']);
        $place = $this->place('RN');

        $this->actingAs($viewer)
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id))
            ->assertRedirect();

        $saved = RoomBorrowing::query()->latest('id')->first();
        $this->assertNotNull($saved);
        $this->assertSame(FacilityRequestStatus::MenungguPersetujuan, $saved->status);

        Notification::assertSentTo($admin, FacilityRequestSubmittedNotification::class, function (FacilityRequestSubmittedNotification $n) use ($saved) {
            $p = $n->payload;

            return ($p['type'] ?? '') === 'room_submitted'
                && ($p['room_borrowing_id'] ?? null) === $saved->id
                && str_contains((string) ($p['message'] ?? ''), $saved->borrower_name)
                && str_contains((string) ($p['url'] ?? ''), '/room-borrowings/'.$saved->id);
        });
        Notification::assertSentTo($super, FacilityRequestSubmittedNotification::class);
        Notification::assertSentTo($ob, OperationalBorrowingNotification::class);
        Notification::assertNotSentTo($viewer, FacilityRequestSubmittedNotification::class);
        Notification::assertNotSentTo($viewer, OperationalBorrowingNotification::class);
    }

    public function test_room_approver_notification_appears_in_navbar_unread_feed(): void
    {
        // Persist to database so the unread JSON endpoint can read it.
        $viewer = $this->makeViewer(['email' => 'viewer.feed@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin.feed@undira.ac.id']);
        $place = $this->place('RF');

        $this->actingAs($viewer)
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id))
            ->assertRedirect();

        $saved = RoomBorrowing::query()->latest('id')->firstOrFail();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'notifiable_type' => $admin->getMorphClass(),
            'type' => FacilityRequestSubmittedNotification::class,
        ]);

        $response = $this->actingAs($admin)->getJson(route('notifications.unread'));
        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, (int) $response->json('count'));
        $response->assertJsonFragment(['title' => 'Peminjaman Ruangan Baru']);
        $this->assertStringContainsString('/room-borrowings/'.$saved->id, (string) data_get($response->json('notifications'), '0.url'));

        $this->actingAs($viewer)
            ->getJson(route('notifications.unread'))
            ->assertOk()
            ->assertJsonPath('count', 0);
    }

    public function test_invalid_room_submission_creates_no_approver_notification(): void
    {
        Notification::fake();
        $admin = $this->makeAdmin();
        $viewer = $this->makeViewer();
        $place = $this->place('RX');

        $this->actingAs($viewer)
            ->from(route('room-borrowings.create'))
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, [
                'activity_type' => '',
            ]))
            ->assertSessionHasErrors('activity_type');

        $this->assertSame(0, RoomBorrowing::query()->count());
        Notification::assertNotSentTo($admin, FacilityRequestSubmittedNotification::class);
    }

    public function test_vehicle_submission_notifies_approvers_and_driver(): void
    {
        Notification::fake();
        $viewer = $this->makeViewer(['email' => 'viewer.veh.notif@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin.veh.notif@undira.ac.id']);
        $driver = $this->makeDriver(['email' => 'driver.veh.notif@undira.ac.id']);
        $campus = Campus::query()->create([
            'code' => 'VN1', 'name' => 'Kampus VN', 'slug' => 'kampus-vn-'.uniqid(), 'is_active' => true,
        ]);
        $route = OperationalRoute::query()->create(['name' => 'Rute VN', 'is_active' => true]);

        app(VehicleBorrowingService::class)->submit($viewer, [
            'campus_id' => $campus->id,
            'route_id' => $route->id,
            'borrower_name' => 'Pemohon Kendaraan',
            'borrower_email' => 'pemohon.kendaraan@undira.ac.id',
            'borrower_phone' => '081234567890',
            'unit' => 'Biro PPPA',
            'purpose' => 'Antar dokumen',
            'service_type' => VehicleServiceType::DropOff->value,
            'destination' => 'Grogol',
            'passenger_count' => 1,
            'passengers' => [
                ['name' => 'Pemohon', 'unit' => 'Biro', 'title' => 'Staff'],
            ],
            'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDays(2)->addHours(2)->format('Y-m-d\TH:i'),
        ]);

        $saved = VehicleBorrowing::query()->latest('id')->first();
        $this->assertNotNull($saved);

        Notification::assertSentTo($admin, FacilityRequestSubmittedNotification::class, function (FacilityRequestSubmittedNotification $n) use ($saved) {
            return ($n->payload['type'] ?? '') === 'vehicle_submitted'
                && ($n->payload['vehicle_borrowing_id'] ?? null) === $saved->id;
        });
        Notification::assertSentTo($driver, OperationalBorrowingNotification::class);
        Notification::assertNotSentTo($viewer, FacilityRequestSubmittedNotification::class);
    }

    public function test_room_approver_notification_is_not_duplicated_on_repeat_call(): void
    {
        $admin = $this->makeAdmin();
        $place = $this->place('RD');

        $borrowing = app(RoomBorrowingService::class)->submit($admin, [
            'room_id' => $place['room']->id,
            'borrower_name' => 'Panitia',
            'borrower_email' => 'panitia@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrower_affiliation' => 'BEM',
            'activity_type' => 'rapat',
            'purpose' => 'Dedup notifikasi',
            'participant_internal' => true,
            'participant_internal_count' => 5,
            'starts_at' => now()->addDays(4)->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDays(4)->addHours(2)->format('Y-m-d\TH:i'),
        ], UploadedFile::fake()->create('persetujuan.pdf', 40, 'application/pdf'));

        $query = fn () => $admin->notifications()
            ->where('type', FacilityRequestSubmittedNotification::class)
            ->where('data->room_borrowing_id', $borrowing->id)
            ->count();

        $this->assertSame(1, $query());

        app(\App\Services\NotificationService::class)->notifyRoomBorrowingSubmitted($borrowing);

        $this->assertSame(1, $query());
    }

    /**
     * @return array{campus: Campus, building: Building, floor: Floor, room: Room}
     */
    private function place(string $suffix): array
    {
        $id = uniqid();
        $campus = Campus::query()->create([
            'code' => 'C'.$suffix, 'name' => 'Kampus '.$suffix, 'slug' => 'kampus-'.$suffix.'-'.$id, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'B1', 'name' => 'Gedung 1', 'slug' => 'gedung-'.$suffix.'-'.$id, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R1', 'name' => 'Kelas A104', 'room_type' => 'Kelas', 'capacity' => 40, 'is_active' => true,
        ]);

        return compact('campus', 'building', 'floor', 'room');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function roomPayload(int $roomId, array $overrides = []): array
    {
        return array_merge([
            'room_id' => $roomId,
            'borrower_name' => 'Azwar',
            'borrower_email' => 'azwar@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrower_affiliation' => 'Teknik Perminyakan',
            'activity_type' => 'rapat',
            'purpose' => 'Rapat koordinasi',
            'participant_internal' => '1',
            'participant_internal_count' => 10,
            'approval_document' => UploadedFile::fake()->create('persetujuan.pdf', 80, 'application/pdf'),
            'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDays(2)->addHours(6)->format('Y-m-d\TH:i'),
        ], $overrides);
    }
}
