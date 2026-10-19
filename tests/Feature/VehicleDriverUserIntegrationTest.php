<?php

namespace Tests\Feature;

use App\Enums\FacilityRequestStatus;
use App\Enums\VehicleServiceType;
use App\Mail\VehicleBorrowingDecisionMail;
use App\Mail\VehicleDriverAssignmentMail;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Floor;
use App\Models\OperationalDriver;
use App\Models\OperationalRoute;
use App\Models\OperationalVehicle;
use App\Models\Room;
use App\Models\User;
use App\Models\VehicleBorrowing;
use App\Notifications\OperationalBorrowingNotification;
use App\Services\OperationalDriverDirectory;
use Database\Seeders\DriverTestUsersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class VehicleDriverUserIntegrationTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_driver_test_seeder_is_idempotent_and_lists_on_master(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DriverTestUsersSeeder::class);
        $this->seed(DriverTestUsersSeeder::class);

        $this->assertSame(3, User::query()->role('driver')->whereIn('email', [
            'driver1@example.test',
            'driver2@example.test',
            'driver3@example.test',
        ])->count());

        $admin = $this->makeAdmin(['email' => 'fleet.admin@undira.ac.id']);
        $html = $this->actingAs($admin)
            ->get(route('vehicle-fleet.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Driver Uji 1', $html);
        $this->assertStringContainsString('Driver Uji 2', $html);
        $this->assertStringContainsString('Driver Uji 3', $html);
        $this->assertStringContainsString('081111100001', $html);
        $this->assertStringContainsString('User Management', $html);
    }

    public function test_non_driver_users_do_not_appear_as_assignable_drivers(): void
    {
        $admin = $this->makeAdmin(['email' => 'fleet.filter@undira.ac.id']);
        $viewer = $this->makeViewer(['email' => 'not.driver@undira.ac.id', 'phone' => '081299999999']);
        $driver = $this->makeDriver(['email' => 'yes.driver@undira.ac.id', 'phone' => '081288888888', 'name' => 'Sopir Valid']);

        app(OperationalDriverDirectory::class)->syncFromUsers();

        $assignable = app(OperationalDriverDirectory::class)->assignable();
        $this->assertTrue($assignable->contains(fn (OperationalDriver $d) => (int) $d->user_id === (int) $driver->id));
        $this->assertFalse($assignable->contains(fn (OperationalDriver $d) => (int) $d->user_id === (int) $viewer->id));

        $html = $this->actingAs($admin)->get(route('vehicle-fleet.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Sopir Valid', $html);
        $this->assertStringNotContainsString('not.driver@undira.ac.id', $html);
    }

    public function test_approval_notifies_borrower_and_assigned_driver_with_email(): void
    {
        Mail::fake();
        Notification::fake();

        $admin = $this->makeAdmin(['email' => 'approve.drv@undira.ac.id']);
        $requester = $this->makeViewer(['email' => 'pemohon.drv@undira.ac.id', 'name' => 'Pemohon Kendaraan']);
        $driverUser = $this->makeDriver(['email' => 'driver.assign@undira.ac.id', 'name' => 'Driver Ditugaskan', 'phone' => '081233344455']);
        $otherDriver = $this->makeDriver(['email' => 'driver.other@undira.ac.id', 'name' => 'Driver Lain']);

        $directory = app(OperationalDriverDirectory::class);
        $driver = $directory->ensureForUser($driverUser);
        $directory->ensureForUser($otherDriver);

        $place = $this->place('Assign');
        $route = OperationalRoute::query()->create(['name' => 'Rute Assign', 'is_active' => true]);
        $vehicle = OperationalVehicle::query()->create([
            'name' => 'Innova Assign',
            'plate_number' => 'B 5555 TST',
            'category' => 'MPV',
            'passenger_capacity' => 7,
            'is_active' => true,
        ]);

        $this->actingAs($requester)->post(route('vehicle-borrowings.store'), [
            'campus_id' => $place['campus']->id,
            'route_id' => $route->id,
            'borrower_name' => 'Pemohon Kendaraan',
            'borrower_email' => 'pemohon.drv@undira.ac.id',
            'borrower_phone' => '081200011122',
            'unit' => 'PPPA',
            'purpose' => 'Antar dokumen',
            'service_type' => VehicleServiceType::DropOff->value,
            'destination' => 'Kampus Grogol',
            'passenger_count' => 1,
            'passengers' => [['name' => 'Pemohon Kendaraan', 'unit' => 'PPPA', 'title' => 'Staff']],
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
            'supporting_document' => UploadedFile::fake()->create('berkas.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        $pending = VehicleBorrowing::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('vehicle-borrowings.decide', $pending), [
                'decision' => 'approve',
                'assignment' => 'fleet',
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
            ])
            ->assertRedirect();

        $pending->refresh();
        $this->assertSame(FacilityRequestStatus::Disetujui, $pending->status);
        $this->assertSame($vehicle->id, $pending->assigned_vehicle_id);
        $this->assertSame($driver->id, $pending->assigned_driver_id);

        Mail::assertSent(VehicleBorrowingDecisionMail::class);
        Mail::assertSent(VehicleDriverAssignmentMail::class);

        Notification::assertSentTo($requester, OperationalBorrowingNotification::class, function (OperationalBorrowingNotification $n) {
            return ($n->payload['event'] ?? null) === 'approved';
        });
        Notification::assertSentTo($driverUser, OperationalBorrowingNotification::class, function (OperationalBorrowingNotification $n) {
            return ($n->payload['event'] ?? null) === 'driver_assigned';
        });
        Notification::assertNotSentTo($otherDriver, OperationalBorrowingNotification::class);
    }

    public function test_rejection_notifies_only_borrower_not_drivers(): void
    {
        Mail::fake();
        Notification::fake();

        $admin = $this->makeAdmin(['email' => 'reject.drv@undira.ac.id']);
        $requester = $this->makeViewer(['email' => 'pemohon.reject@undira.ac.id']);
        $driverUser = $this->makeDriver(['email' => 'driver.reject@undira.ac.id']);
        app(OperationalDriverDirectory::class)->ensureForUser($driverUser);

        $place = $this->place('Reject');
        $route = OperationalRoute::query()->create(['name' => 'Rute Reject', 'is_active' => true]);

        $this->actingAs($requester)->post(route('vehicle-borrowings.store'), [
            'campus_id' => $place['campus']->id,
            'route_id' => $route->id,
            'borrower_name' => 'Pemohon Tolak',
            'borrower_email' => 'pemohon.reject@undira.ac.id',
            'borrower_phone' => '081200011133',
            'purpose' => 'Ditolak',
            'service_type' => VehicleServiceType::DropOff->value,
            'destination' => 'Tujuan',
            'passenger_count' => 1,
            'passengers' => [['name' => 'A', 'unit' => 'U', 'title' => 'T']],
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
            'supporting_document' => UploadedFile::fake()->create('berkas.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        $pending = VehicleBorrowing::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('vehicle-borrowings.decide', $pending), [
                'decision' => 'reject',
                'rejection_reason' => 'Jadwal tidak tersedia minggu ini.',
            ])
            ->assertRedirect();

        $pending->refresh();
        $this->assertSame(FacilityRequestStatus::Ditolak, $pending->status);
        $this->assertNull($pending->assigned_vehicle_id);
        $this->assertNull($pending->assigned_driver_id);
        $this->assertSame('Jadwal tidak tersedia minggu ini.', $pending->rejection_reason);

        Mail::assertSent(VehicleBorrowingDecisionMail::class);
        Mail::assertNotSent(VehicleDriverAssignmentMail::class);

        Notification::assertSentTo($requester, OperationalBorrowingNotification::class, function (OperationalBorrowingNotification $n) {
            return ($n->payload['event'] ?? null) === 'rejected';
        });
        Notification::assertNotSentTo($driverUser, OperationalBorrowingNotification::class);
    }

    public function test_gocar_approval_does_not_require_internal_driver_or_notify_drivers(): void
    {
        Mail::fake();
        Notification::fake();

        $admin = $this->makeAdmin(['email' => 'gocar.admin@undira.ac.id']);
        $requester = $this->makeViewer(['email' => 'gocar.req@undira.ac.id']);
        $driverUser = $this->makeDriver(['email' => 'gocar.driver@undira.ac.id']);
        app(OperationalDriverDirectory::class)->ensureForUser($driverUser);

        $place = $this->place('GoCar');
        $route = OperationalRoute::query()->create(['name' => 'Rute GoCar', 'is_active' => true]);

        $this->actingAs($requester)->post(route('vehicle-borrowings.store'), [
            'campus_id' => $place['campus']->id,
            'route_id' => $route->id,
            'borrower_name' => 'Pemohon GoCar',
            'borrower_email' => 'gocar.req@undira.ac.id',
            'borrower_phone' => '081200011144',
            'purpose' => 'GoCar',
            'service_type' => VehicleServiceType::DropOff->value,
            'destination' => 'Bandara',
            'passenger_count' => 1,
            'passengers' => [['name' => 'A', 'unit' => 'U', 'title' => 'T']],
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
            'supporting_document' => UploadedFile::fake()->create('berkas.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        $pending = VehicleBorrowing::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('vehicle-borrowings.decide', $pending), [
                'decision' => 'approve',
                'assignment' => 'gocar',
            ])
            ->assertRedirect();

        $pending->refresh();
        $this->assertSame(FacilityRequestStatus::Disetujui, $pending->status);
        $this->assertNull($pending->assigned_vehicle_id);
        $this->assertNull($pending->assigned_driver_id);
        $this->assertSame(VehicleServiceType::GoCar, $pending->assigned_service);

        Mail::assertNotSent(VehicleDriverAssignmentMail::class);
        Notification::assertNotSentTo($driverUser, OperationalBorrowingNotification::class);
        Notification::assertSentTo($requester, OperationalBorrowingNotification::class);
    }

    public function test_inactive_or_role_revoked_driver_cannot_be_assigned(): void
    {
        $admin = $this->makeAdmin(['email' => 'inactive.drv.admin@undira.ac.id']);
        $requester = $this->makeViewer(['email' => 'inactive.drv.req@undira.ac.id']);
        $inactive = $this->makeDriver(['email' => 'inactive.drv@undira.ac.id', 'is_active' => false]);
        $revoked = $this->makeDriver(['email' => 'revoked.drv@undira.ac.id']);
        $directory = app(OperationalDriverDirectory::class);
        $inactiveDriver = $directory->ensureForUser($inactive);
        $revokedDriver = $directory->ensureForUser($revoked);
        $revoked->syncRoles(['viewer']);
        $directory->syncFromUsers();

        $place = $this->place('Inactive');
        $route = OperationalRoute::query()->create(['name' => 'Rute Inactive', 'is_active' => true]);
        $vehicle = OperationalVehicle::query()->create([
            'name' => 'Mobil',
            'plate_number' => 'B 9 INA',
            'category' => 'MPV',
            'is_active' => true,
        ]);

        $this->actingAs($requester)->post(route('vehicle-borrowings.store'), [
            'campus_id' => $place['campus']->id,
            'route_id' => $route->id,
            'borrower_name' => 'Pemohon',
            'borrower_email' => 'inactive.drv.req@undira.ac.id',
            'borrower_phone' => '081200011155',
            'purpose' => 'Uji',
            'service_type' => VehicleServiceType::DropOff->value,
            'destination' => 'Tujuan',
            'passenger_count' => 1,
            'passengers' => [['name' => 'A', 'unit' => 'U', 'title' => 'T']],
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
            'supporting_document' => UploadedFile::fake()->create('berkas.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        $pending = VehicleBorrowing::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('vehicle-borrowings.decide', $pending), [
                'decision' => 'approve',
                'assignment' => 'fleet',
                'vehicle_id' => $vehicle->id,
                'driver_id' => $inactiveDriver->id,
            ])
            ->assertSessionHasErrors('driver_id');

        $this->actingAs($admin)
            ->post(route('vehicle-borrowings.decide', $pending), [
                'decision' => 'approve',
                'assignment' => 'fleet',
                'vehicle_id' => $vehicle->id,
                'driver_id' => $revokedDriver->fresh()->id,
            ])
            ->assertSessionHasErrors('driver_id');
    }

    public function test_viewer_cannot_approve_vehicle_borrowing(): void
    {
        $viewer = $this->makeViewer(['email' => 'viewer.noapprove@undira.ac.id']);
        $place = $this->place('NoApprove');
        $borrowing = VehicleBorrowing::query()->create([
            'campus_id' => $place['campus']->id,
            'borrower_name' => 'X',
            'borrower_email' => 'x@undira.ac.id',
            'borrower_phone' => '0812',
            'purpose' => 'Y',
            'service_type' => VehicleServiceType::DropOff,
            'destination' => 'Z',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => FacilityRequestStatus::MenungguPersetujuan,
            'requested_by' => $viewer->id,
        ]);

        $this->actingAs($viewer)
            ->post(route('vehicle-borrowings.decide', $borrowing), [
                'decision' => 'approve',
                'assignment' => 'gocar',
            ])
            ->assertForbidden();
    }

    /**
     * @return array{campus: Campus, room: Room}
     */
    private function place(string $label): array
    {
        $suffix = substr(md5($label.uniqid('', true)), 0, 8);
        $campus = Campus::query()->create([
            'code' => 'C'.$suffix,
            'name' => 'Kampus '.$label,
            'slug' => 'kampus-'.$suffix,
            'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'code' => 'B'.$suffix,
            'name' => 'Gedung '.$label,
            'slug' => 'gedung-'.$suffix,
            'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id,
            'code' => 'F'.$suffix,
            'name' => 'Lantai 1',
            'floor_number' => 1,
            'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id,
            'code' => 'R'.$suffix,
            'name' => 'Ruang '.$label,
            'room_type' => 'Kelas',
            'capacity' => 20,
            'is_active' => true,
        ]);

        return compact('campus', 'room');
    }
}
