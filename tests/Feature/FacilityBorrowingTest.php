<?php

namespace Tests\Feature;

use App\Enums\BorrowingStatus;
use App\Enums\FacilityRequestStatus;
use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Enums\VehicleServiceType;
use App\Mail\RoomBorrowingDecisionMail;
use App\Mail\VehicleBorrowingDecisionMail;
use App\Models\Borrowing;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class FacilityBorrowingTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_room_form_uses_database_locations_and_stores_a_valid_schedule(): void
    {
        $viewer = $this->makeViewer();
        $place = $this->place('Cibubur');

        $this->actingAs($viewer)
            ->get(route('room-borrowings.create'))
            ->assertOk()
            ->assertSee('id="campus_id"', false)
            ->assertSee('id="building_id"', false)
            ->assertSee('id="floor_id"', false)
            ->assertSee('id="room_id"', false)
            ->assertSee('Jenis Kegiatan')
            ->assertSee('Tujuan Kegiatan')
            ->assertSee('Dokumen Persetujuan Pimpinan')
            ->assertSee('Layout Acara')
            ->assertSee('Daftar Peserta')
            ->assertDontSee('Download Template Dokumen')
            ->assertSee('selector\\/campuses', false);

        $this->actingAs($viewer)
            ->getJson(route('inventory.selector.campuses'))
            ->assertOk()
            ->assertJsonFragment(['text' => $place['campus']->code.' — '.$place['campus']->name]);

        $this->actingAs($viewer)
            ->getJson(route('inventory.selector.buildings', ['campus_id' => $place['campus']->id]))
            ->assertOk()
            ->assertJsonFragment(['id' => $place['building']->id]);

        $this->actingAs($viewer)
            ->getJson(route('inventory.selector.floors', ['building_id' => $place['building']->id]))
            ->assertOk()
            ->assertJsonFragment(['id' => $place['floor']->id]);

        $this->actingAs($viewer)
            ->getJson(route('inventory.selector.rooms', ['floor_id' => $place['floor']->id]))
            ->assertOk()
            ->assertJsonFragment(['id' => $place['room']->id]);

        $this->actingAs($viewer)
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id))
            ->assertRedirect();

        $this->assertDatabaseHas('room_borrowings', [
            'room_id' => $place['room']->id,
            'campus_id' => $place['campus']->id,
            'activity_type' => 'rapat',
            'status' => FacilityRequestStatus::MenungguPersetujuan->value,
            'requested_by' => $viewer->id,
        ]);
    }

    public function test_room_activity_budget_participants_and_documents_are_validated(): void
    {
        Mail::fake();
        $viewer = $this->makeViewer();
        $other = $this->makeViewer();
        $admin = $this->makeAdmin();
        $place = $this->place('Kampus Kegiatan');

        $this->actingAs($viewer)
            ->from(route('room-borrowings.create'))
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, ['activity_type' => '']))
            ->assertSessionHasErrors('activity_type');

        $this->actingAs($viewer)
            ->from(route('room-borrowings.create'))
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, [
                'ends_at' => '2026-11-02T09:00',
            ]))
            ->assertSessionHasErrors('ends_at');

        $this->actingAs($viewer)
            ->from(route('room-borrowings.create'))
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, [
                'participant_internal' => '0',
                'participant_external' => '0',
            ]))
            ->assertSessionHasErrors('participants');

        $this->actingAs($viewer)
            ->from(route('room-borrowings.create'))
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, [
                'uses_budget' => '1',
                'budget_type' => '',
            ]))
            ->assertSessionHasErrors('budget_type');

        $this->actingAs($viewer)
            ->from(route('room-borrowings.create'))
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, [
                'approval_document' => null,
            ]))
            ->assertSessionHasErrors('approval_document');

        $this->actingAs($viewer)
            ->from(route('room-borrowings.create'))
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, [
                'approval_document' => UploadedFile::fake()->create('besar.pdf', 5121, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('approval_document');

        $this->actingAs($viewer)
            ->from(route('room-borrowings.create'))
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, [
                'layout_document' => UploadedFile::fake()->create('catatan.txt', 12, 'text/plain'),
            ]))
            ->assertSessionHasErrors('layout_document');

        $this->actingAs($viewer)
            ->from(route('room-borrowings.create'))
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, [
                'documentation_url' => 'javascript:alert(1)',
            ]))
            ->assertSessionHasErrors('documentation_url');

        $this->assertSame(0, RoomBorrowing::query()->count());

        $this->actingAs($viewer)
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, [
                'purpose' => '<script>alert(1)</script>Rapat koordinasi persiapan',
                'budget_type' => 'Tidak boleh tersimpan',
                'documentation_url' => 'https://drive.google.com/file/d/kegiatan',
                'borrower_phone' => '0812 3456-7890',
            ]))
            ->assertRedirect();

        $saved = RoomBorrowing::query()->first();
        $this->assertNotNull($saved);
        $this->assertSame('rapat', $saved->activity_type?->value);
        $this->assertSame('alert(1)Rapat koordinasi persiapan', $saved->purpose);
        $this->assertFalse($saved->uses_budget);
        $this->assertNull($saved->budget_type);
        $this->assertTrue($saved->participant_internal);
        $this->assertFalse($saved->participant_external);
        $this->assertSame('https://drive.google.com/file/d/kegiatan', $saved->documentation_url);
        $this->assertNotNull($saved->approval_document_path);
        $this->assertNull($saved->layout_document_path);
        $this->assertNull($saved->participant_document_path);
        Storage::disk('local')->assertExists($saved->approval_document_path);

        $this->actingAs($viewer)
            ->get(route('room-borrowings.show', $saved))
            ->assertOk()
            ->assertSee('Rapat')
            ->assertSee('alert(1)Rapat koordinasi persiapan')
            ->assertSee('Tidak menggunakan anggaran')
            ->assertSee('Internal')
            ->assertSee('https://drive.google.com/file/d/kegiatan')
            ->assertSee('persetujuan.pdf')
            ->assertDontSee('<script>alert(1)</script>', false);

        $this->actingAs($other)
            ->get(route('room-borrowings.documents', [$saved, 'approval']))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get(route('room-borrowings.documents', [$saved, 'approval']))
            ->assertOk();

        $this->actingAs($viewer)
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, [
                'borrower_name' => 'Dengan Anggaran',
                'uses_budget' => '1',
                'budget_type' => 'Anggaran unit',
                'participant_internal' => '1',
                'participant_external' => '1',
                'borrower_phone' => '+62 812-3456',
                'starts_at' => '2026-11-03T09:00',
                'ends_at' => '2026-11-03T11:00',
            ]))
            ->assertRedirect();

        $budgeted = RoomBorrowing::query()->where('borrower_name', 'Dengan Anggaran')->first();
        $this->assertTrue($budgeted->uses_budget);
        $this->assertSame('Anggaran unit', $budgeted->budget_type);
        $this->assertSame('Internal dan External', $budgeted->participantText());

        $this->actingAs($viewer)
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, [
                'borrower_name' => 'Prefix 62',
                'borrower_phone' => '628123456',
                'starts_at' => '2026-11-04T09:00',
                'ends_at' => '2026-11-04T11:00',
            ]))
            ->assertRedirect();

        Storage::disk('local')->put('templates/room-borrowings/persetujuan-pimpinan.pdf', 'template resmi');
        $this->actingAs($viewer)
            ->get(route('room-borrowings.create'))
            ->assertOk()
            ->assertSee('Download Template Dokumen')
            ->assertDontSee('Download Template Layout Acara')
            ->assertDontSee('Download Template Daftar Peserta');

        $this->actingAs($viewer)
            ->get(route('room-borrowings.templates', 'approval'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('room-borrowings.decide', $saved), ['decision' => 'approve'])
            ->assertRedirect();

        Mail::assertSent(RoomBorrowingDecisionMail::class, function (RoomBorrowingDecisionMail $mail) {
            $html = $mail->render();

            return str_contains($html, 'Rapat')
                && str_contains($html, 'alert(1)Rapat koordinasi persiapan')
                && str_contains($html, 'disetujui');
        });
    }

    public function test_room_approval_is_separated_and_campus_filter_does_not_error_on_bad_input(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();
        $first = $this->place('Kampus Satu');
        $second = $this->place('Kampus Dua');
        $viewer = $this->makeViewer();

        $this->actingAs($viewer)->post(route('room-borrowings.store'), $this->roomPayload($first['room']->id, [
            'borrower_name' => 'Pemohon Satu',
        ]));
        $this->actingAs($viewer)->post(route('room-borrowings.store'), $this->roomPayload($second['room']->id, [
            'borrower_name' => 'Pemohon Dua',
        ]));

        $this->actingAs($admin)
            ->get(route('room-borrowings.approvals', ['campus_id' => $first['campus']->id]))
            ->assertOk()
            ->assertSee('Pemohon Satu')
            ->assertDontSee('Pemohon Dua');

        $this->actingAs($admin)
            ->get(route('room-borrowings.approvals', ['campus_id' => 'bukan-angka']))
            ->assertOk()
            ->assertDontSee('Pemohon Satu');
    }

    public function test_room_reject_requires_a_reason_and_does_not_lock_the_room(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();
        $viewer = $this->makeViewer();
        $place = $this->place('Kampus Tolak');
        $this->actingAs($viewer)->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id));
        $pending = RoomBorrowing::query()->first();

        $this->actingAs($admin)
            ->post(route('room-borrowings.decide', $pending), ['decision' => 'reject'])
            ->assertSessionHasErrors('rejection_reason');
        $this->assertSame(FacilityRequestStatus::MenungguPersetujuan, $pending->fresh()->status);
        Mail::assertNothingSent();

        $this->actingAs($admin)
            ->post(route('room-borrowings.decide', $pending), [
                'decision' => 'reject',
                'rejection_reason' => 'Jadwal bentrok dengan kegiatan kampus',
            ])
            ->assertRedirect();

        Mail::assertSent(RoomBorrowingDecisionMail::class, function (RoomBorrowingDecisionMail $mail) {
            return str_contains($mail->render(), 'Jadwal bentrok dengan kegiatan kampus');
        });

        $this->actingAs($viewer)
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, ['borrower_name' => 'Pengganti']))
            ->assertRedirect();
        $this->assertSame(2, RoomBorrowing::query()->count());
    }

    public function test_approved_room_blocks_overlapping_room_and_inventory_without_changing_item_status(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();
        $viewer = $this->makeViewer();
        $place = $this->place('Kampus Pakai');
        $item = $this->itemIn($place, InventoryStatus::Aktif);
        $maintenance = $this->itemIn($place, InventoryStatus::Maintenance, 'INV-MT-'.uniqid());

        $this->actingAs($viewer)->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id));
        $this->actingAs($viewer)->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, [
            'borrower_name' => 'Antrian Kedua',
        ]))->assertRedirect();

        $first = RoomBorrowing::query()->where('borrower_name', 'Sari Ruangan')->first();
        $second = RoomBorrowing::query()->where('borrower_name', 'Antrian Kedua')->first();

        $this->actingAs($admin)
            ->post(route('room-borrowings.decide', $first), ['decision' => 'approve'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('room-borrowings.decide', $second), ['decision' => 'approve'])
            ->assertSessionHasErrors('decision');
        $this->assertSame(FacilityRequestStatus::MenungguPersetujuan, $second->fresh()->status);

        $this->actingAs($viewer)
            ->post(route('borrowings.store'), $this->inventoryPayload($item->id))
            ->assertSessionHasErrors('items');
        $this->assertSame(0, Borrowing::query()->count());
        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);
        $this->assertSame(InventoryStatus::Maintenance, $maintenance->fresh()->status);
        $this->assertNull(Borrowing::query()->where('inventory_item_id', $maintenance->id)->first());
    }

    public function test_ended_or_rejected_room_schedule_does_not_keep_resources_locked(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();
        $viewer = $this->makeViewer();
        $place = $this->place('Kampus Selesai');
        $item = $this->itemIn($place, InventoryStatus::Aktif);

        $ended = RoomBorrowing::query()->create([
            'room_id' => $place['room']->id,
            'campus_id' => $place['campus']->id,
            'borrower_name' => 'Selesai Dulu',
            'borrower_phone' => '081234567890',
            'purpose' => 'Kemarin',
            'starts_at' => '2026-10-01 08:00:00',
            'ends_at' => '2026-10-01 10:00:00',
            'status' => FacilityRequestStatus::Disetujui,
            'requested_by' => $viewer->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
        $this->assertSame('Selesai', $ended->displayLabel());

        $this->actingAs($viewer)
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, [
                'starts_at' => '2026-11-20T09:00',
                'ends_at' => '2026-11-20T11:00',
            ]))
            ->assertRedirect();

        $this->actingAs($viewer)
            ->post(route('borrowings.store'), $this->inventoryPayload($item->id, [
                'borrow_date' => '2026-11-20T09:00',
                'expected_return_date' => '2026-11-20T11:00',
            ]))
            ->assertRedirect(route('borrowings.index'));
        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);
    }

    public function test_room_email_failure_keeps_the_saved_approval(): void
    {
        $admin = $this->makeAdmin();
        $viewer = $this->makeViewer();
        $place = $this->place('Kampus Surat');
        $this->actingAs($viewer)->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, [
            'borrower_email' => 'sari@undira.ac.id',
        ]));
        $pending = RoomBorrowing::query()->first();

        $this->failMail();
        Log::spy();

        $this->actingAs($admin)
            ->post(route('room-borrowings.decide', $pending), ['decision' => 'approve'])
            ->assertRedirect();

        $saved = $pending->fresh();
        $this->assertSame(FacilityRequestStatus::Disetujui, $saved->status);
        $this->assertNotNull($saved->approval_number);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_vehicle_service_fields_follow_the_selected_service(): void
    {
        $viewer = $this->makeViewer();
        $place = $this->place('Kampus Mobil');

        $this->actingAs($viewer)
            ->get(route('vehicle-borrowings.create'))
            ->assertOk()
            ->assertSee('Drop-off')
            ->assertSee('Pulang-pergi')
            ->assertSee('Penjemputan')
            ->assertSee($place['campus']->name)
            ->assertSee('Pilih Rute')
            ->assertSee('Jumlah Penumpang')
            ->assertSee('Daftar Penumpang')
            ->assertSee('Jadwal Mulai')
            ->assertSee('Dokumen Pendukung (Maksimal 5 MB)')
            ->assertDontSee('>GoCar<', false);

        $this->actingAs($viewer)
            ->post(route('vehicle-borrowings.store'), $this->vehiclePayload($place['campus']->id, [
                'service_type' => 'drop_off',
                'destination' => '',
            ]))
            ->assertSessionHasErrors('destination');
        $this->assertSame(0, VehicleBorrowing::query()->count());

        $this->actingAs($viewer)
            ->post(route('vehicle-borrowings.store'), $this->vehiclePayload($place['campus']->id, [
                'service_type' => 'pickup',
                'destination' => 'Grogol, Jakarta Barat',
                'borrower_name' => 'Penjemputan Sari',
            ]))
            ->assertRedirect();

        $saved = VehicleBorrowing::query()->first();
        $this->assertSame(VehicleServiceType::Pickup, $saved->service_type);
        $this->assertNull($saved->assigned_vehicle_id);
        $this->assertNull($saved->assigned_driver_id);
    }

    public function test_vehicle_passengers_phone_document_and_capacity_are_validated(): void
    {
        Storage::fake('local');
        $viewer = $this->makeViewer(['email' => 'form-kendaraan@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin-kapasitas@undira.ac.id']);
        $place = $this->place('Kampus Form');

        $this->actingAs($viewer)
            ->post(route('vehicle-borrowings.store'), $this->vehiclePayload($place['campus']->id, [
                'route_id' => '',
            ]))
            ->assertSessionHasErrors('route_id');

        $this->actingAs($viewer)
            ->post(route('vehicle-borrowings.store'), $this->vehiclePayload($place['campus']->id, [
                'passenger_count' => 0,
            ]))
            ->assertSessionHasErrors('passenger_count');

        $this->actingAs($viewer)
            ->post(route('vehicle-borrowings.store'), $this->vehiclePayload($place['campus']->id, [
                'passenger_count' => -1,
            ]))
            ->assertSessionHasErrors('passenger_count');

        $this->actingAs($viewer)
            ->post(route('vehicle-borrowings.store'), $this->vehiclePayload($place['campus']->id, [
                'ends_at' => '2026-11-02T08:00',
            ]))
            ->assertSessionHasErrors('ends_at');

        $this->actingAs($viewer)
            ->post(route('vehicle-borrowings.store'), $this->vehiclePayload($place['campus']->id, [
                'passenger_count' => 2,
                'passengers' => [
                    ['name' => 'Hanya Satu', 'unit' => 'Biro', 'title' => 'Staff'],
                ],
            ]))
            ->assertSessionHasErrors('passengers');

        foreach (['0812 3456-7890', '+62 812-3456', '628123456'] as $phone) {
            $this->actingAs($viewer)
                ->post(route('vehicle-borrowings.store'), $this->vehiclePayload($place['campus']->id, [
                    'borrower_phone' => $phone,
                    'borrower_name' => 'Telepon '.$phone,
                ]))
                ->assertRedirect();
        }
        $this->assertSame(3, VehicleBorrowing::query()->count());

        $this->actingAs($viewer)
            ->post(route('vehicle-borrowings.store'), $this->vehiclePayload($place['campus']->id, [
                'supporting_document' => UploadedFile::fake()->create('bukti.pdf', 120, 'application/pdf'),
                'borrower_name' => 'Dengan Dokumen',
                'destination' => 'Grogol, Jakarta Barat',
                'purpose' => 'Kunjungan dinas',
                'passengers' => [
                    ['name' => '<script>alert(1)</script>', 'unit' => 'Biro', 'title' => 'Staff'],
                ],
            ]))
            ->assertRedirect();

        $saved = VehicleBorrowing::query()->where('borrower_name', 'Dengan Dokumen')->first();
        $this->assertNotNull($saved->supporting_document_path);
        $this->assertSame('Grogol, Jakarta Barat', $saved->destination);
        $this->assertSame('Kunjungan dinas', $saved->purpose);
        $this->assertSame('alert(1)', $saved->passengerRows()[0]['name']);
        $this->actingAs($viewer)->get(route('vehicle-borrowings.show', $saved))
            ->assertOk()
            ->assertSee('Grogol, Jakarta Barat')
            ->assertSee('Kunjungan dinas')
            ->assertSee('alert(1)')
            ->assertDontSee('<script>alert(1)</script>', false);

        $this->actingAs($viewer)
            ->post(route('vehicle-borrowings.store'), $this->vehiclePayload($place['campus']->id, [
                'supporting_document' => UploadedFile::fake()->create('besar.pdf', 5121, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('supporting_document');

        $this->actingAs($viewer)
            ->post(route('vehicle-borrowings.store'), $this->vehiclePayload($place['campus']->id, [
                'supporting_document' => UploadedFile::fake()->create('catatan.txt', 20, 'text/plain'),
            ]))
            ->assertSessionHasErrors('supporting_document');

        $vehicle = OperationalVehicle::query()->create([
            'name' => 'Avanza Kecil',
            'plate_number' => 'B 9 UND',
            'category' => 'MPV',
            'passenger_capacity' => 1,
            'is_active' => true,
        ]);
        $driver = OperationalDriver::query()->create([
            'name' => 'Sopir Kapasitas',
            'phone' => '081200000111',
            'is_active' => true,
        ]);
        $this->actingAs($viewer)
            ->post(route('vehicle-borrowings.store'), $this->vehiclePayload($place['campus']->id, [
                'borrower_name' => 'Terlalu Penuh',
                'passenger_count' => 2,
                'passengers' => [
                    ['name' => 'Satu', 'unit' => 'A', 'title' => 'Staff'],
                    ['name' => 'Dua', 'unit' => 'B', 'title' => 'Staff'],
                ],
            ]))
            ->assertRedirect();
        $full = VehicleBorrowing::query()->where('borrower_name', 'Terlalu Penuh')->first();
        $this->actingAs($admin)
            ->post(route('vehicle-borrowings.decide', $full), [
                'decision' => 'approve',
                'assignment' => 'fleet',
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
            ])
            ->assertSessionHasErrors('vehicle_id');
        $this->assertSame(FacilityRequestStatus::MenungguPersetujuan, $full->fresh()->status);
    }

    public function test_admin_manages_vehicle_master_and_assigns_fleet_or_gocar_without_double_booking(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();
        $viewer = $this->makeViewer();
        $place = $this->place('Kampus Armada');

        $this->actingAs($viewer)->get(route('vehicle-fleet.index'))->assertForbidden();

        $this->actingAs($admin)->post(route('vehicle-fleet.vehicles.store'), [
            'name' => 'Avanza Operasional',
            'plate_number' => 'B 1001 UND',
            'category' => 'MPV',
        ])->assertRedirect(route('vehicle-fleet.index'));
        $this->actingAs($admin)->post(route('vehicle-fleet.drivers.store'), [
            'name' => 'Budi Sopir',
            'phone' => '081200000001',
        ])->assertRedirect(route('vehicle-fleet.index'));

        $vehicle = OperationalVehicle::query()->first();
        $driver = OperationalDriver::query()->first();
        $this->actingAs($admin)->put(route('vehicle-fleet.vehicles.update', $vehicle), [
            'name' => 'Avanza Operasional',
            'plate_number' => 'B 1001 UND',
            'category' => 'MPV',
            'is_active' => '0',
        ])->assertRedirect();
        $this->assertFalse($vehicle->fresh()->is_active);

        $this->actingAs($viewer)->post(route('vehicle-borrowings.store'), $this->vehiclePayload($place['campus']->id));
        $pending = VehicleBorrowing::query()->first();

        $this->actingAs($admin)->put(route('vehicle-fleet.vehicles.update', $vehicle), [
            'name' => 'Avanza Operasional',
            'plate_number' => 'B 1001 UND',
            'category' => 'MPV',
            'is_active' => '1',
        ]);
        $inactiveDriver = OperationalDriver::query()->create([
            'name' => 'Sopir Nonaktif',
            'phone' => '081200000099',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('vehicle-borrowings.decide', $pending), [
                'decision' => 'approve',
                'assignment' => 'fleet',
                'vehicle_id' => $vehicle->id,
                'driver_id' => $inactiveDriver->id,
            ])
            ->assertSessionHasErrors('driver_id');
        $this->assertSame(FacilityRequestStatus::MenungguPersetujuan, $pending->fresh()->status);

        $this->actingAs($admin)
            ->post(route('vehicle-borrowings.decide', $pending), [
                'decision' => 'approve',
                'assignment' => 'fleet',
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
            ])
            ->assertRedirect();
        $pending->refresh();
        $this->assertSame($vehicle->id, $pending->assigned_vehicle_id);
        $this->assertSame($driver->id, $pending->assigned_driver_id);
        $this->actingAs($admin)->get(route('vehicle-borrowings.show', $pending))->assertSee('B 1001 UND');

        $this->actingAs($viewer)->post(route('vehicle-borrowings.store'), $this->vehiclePayload($place['campus']->id, [
            'borrower_name' => 'Bentrok Armada',
        ]));
        $overlap = VehicleBorrowing::query()->where('borrower_name', 'Bentrok Armada')->first();
        $this->actingAs($admin)
            ->post(route('vehicle-borrowings.decide', $overlap), [
                'decision' => 'approve',
                'assignment' => 'fleet',
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
            ])
            ->assertSessionHasErrors('vehicle_id');

        $this->actingAs($viewer)->post(route('vehicle-borrowings.store'), $this->vehiclePayload($place['campus']->id, [
            'borrower_name' => 'Pakai GoCar',
            'borrower_email' => 'gocar@undira.ac.id',
        ]));
        $gocar = VehicleBorrowing::query()->where('borrower_name', 'Pakai GoCar')->first();
        $this->actingAs($admin)
            ->post(route('vehicle-borrowings.decide', $gocar), [
                'decision' => 'approve',
                'assignment' => 'gocar',
            ])
            ->assertRedirect();
        $this->assertSame(VehicleServiceType::GoCar, $gocar->fresh()->assigned_service);
        Mail::assertSent(VehicleBorrowingDecisionMail::class, function (VehicleBorrowingDecisionMail $mail) use ($gocar) {
            return $mail->borrowing->is($gocar) && str_contains($mail->render(), 'GoCar');
        });
    }

    public function test_vehicle_rejection_email_contains_the_reason_and_smtp_failure_keeps_approval(): void
    {
        $admin = $this->makeAdmin();
        $viewer = $this->makeViewer();
        $place = $this->place('Kampus Tolak Mobil');
        $this->actingAs($viewer)->post(route('vehicle-borrowings.store'), $this->vehiclePayload($place['campus']->id, [
            'borrower_email' => 'tolak@undira.ac.id',
        ]));
        $pending = VehicleBorrowing::query()->first();

        Mail::fake();
        $this->actingAs($admin)
            ->post(route('vehicle-borrowings.decide', $pending), [
                'decision' => 'reject',
                'rejection_reason' => 'Armada sedang servis',
            ])
            ->assertRedirect();
        Mail::assertSent(VehicleBorrowingDecisionMail::class, function (VehicleBorrowingDecisionMail $mail) {
            return str_contains($mail->render(), 'Armada sedang servis');
        });
        $this->assertSame('Armada sedang servis', $pending->fresh()->rejection_reason);

        Mail::clearResolvedInstances();
        $this->app->forgetInstance('mail.manager');
        $this->app->forgetInstance('mailer');

        $this->actingAs($viewer)->post(route('vehicle-borrowings.store'), $this->vehiclePayload($place['campus']->id, [
            'borrower_name' => 'Surat Gagal',
            'borrower_email' => 'gagal@undira.ac.id',
        ]));
        $second = VehicleBorrowing::query()->where('borrower_name', 'Surat Gagal')->first();
        $this->failMail();
        Log::spy();
        $this->actingAs($admin)
            ->post(route('vehicle-borrowings.decide', $second), [
                'decision' => 'approve',
                'assignment' => 'gocar',
            ])
            ->assertRedirect();
        $this->assertSame(FacilityRequestStatus::Disetujui, $second->fresh()->status);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_approval_and_history_pages_do_not_mix_borrowing_types(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();
        $viewer = $this->makeViewer();
        $place = $this->place('Kampus Pisah');
        $item = $this->itemIn($place, InventoryStatus::Aktif);

        $this->actingAs($viewer)->post(route('borrowings.store'), $this->inventoryPayload($item->id, [
            'borrower_name' => 'Orang Inventaris',
        ]));
        $this->actingAs($viewer)->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, [
            'borrower_name' => 'Orang Ruangan',
        ]));
        $this->actingAs($viewer)->post(route('vehicle-borrowings.store'), $this->vehiclePayload($place['campus']->id, [
            'borrower_name' => 'Orang Kendaraan',
        ]));

        $this->actingAs($admin)->get(route('borrowings.index', ['status' => BorrowingStatus::MenungguPersetujuan->value]))
            ->assertOk()->assertSee('Orang Inventaris')->assertDontSee('Orang Ruangan')->assertDontSee('Orang Kendaraan');
        $this->actingAs($admin)->get(route('room-borrowings.approvals'))
            ->assertOk()->assertSee('Orang Ruangan')->assertDontSee('Orang Inventaris')->assertDontSee('Orang Kendaraan');
        $this->actingAs($admin)->get(route('vehicle-borrowings.approvals'))
            ->assertOk()->assertSee('Orang Kendaraan')->assertDontSee('Orang Ruangan')->assertDontSee('Orang Inventaris');

        $room = RoomBorrowing::query()->first();
        $vehicle = VehicleBorrowing::query()->first();
        $this->actingAs($admin)->post(route('room-borrowings.decide', $room), [
            'decision' => 'reject',
            'rejection_reason' => 'Ruangan diperbaiki',
        ]);
        $this->actingAs($admin)->post(route('vehicle-borrowings.decide', $vehicle), [
            'decision' => 'reject',
            'rejection_reason' => 'Tidak ada armada',
        ]);

        $this->actingAs($admin)->get(route('room-borrowings.history'))
            ->assertOk()->assertSee('Orang Ruangan')->assertSee('Ruangan diperbaiki')->assertDontSee('Orang Kendaraan');
        $this->actingAs($admin)->get(route('vehicle-borrowings.history'))
            ->assertOk()->assertSee('Orang Kendaraan')->assertSee('Tidak ada armada')->assertDontSee('Orang Ruangan');
        $this->actingAs($admin)->get(route('borrowing-approvals.index'))
            ->assertOk()->assertDontSee('Orang Ruangan')->assertDontSee('Orang Kendaraan');
    }

    public function test_combined_history_filters_type_and_campus_together(): void
    {
        $admin = $this->makeAdmin();
        $viewer = $this->makeViewer();
        $cibubur = $this->place('Cibubur Gabung');
        $tanjung = $this->place('Tanjung Gabung');
        $item = $this->itemIn($cibubur, InventoryStatus::Aktif, 'INV-GABUNG-'.uniqid());

        $this->actingAs($viewer)->post(route('borrowings.store'), $this->inventoryPayload($item->id, [
            'borrower_name' => 'Inventaris Cibubur',
        ]));
        $this->actingAs($viewer)->post(route('room-borrowings.store'), $this->roomPayload($cibubur['room']->id, [
            'borrower_name' => 'Ruangan Cibubur',
            'purpose' => 'Seminar ruang',
        ]));
        $this->actingAs($viewer)->post(route('vehicle-borrowings.store'), $this->vehiclePayload($tanjung['campus']->id, [
            'borrower_name' => 'Kendaraan Tanjung',
        ]));

        $all = $this->actingAs($admin)->get(route('borrowing-records.index'))->assertOk();
        $all->assertSee('Peminjaman Inventaris')->assertSee('Peminjaman Ruangan')->assertSee('Peminjaman Kendaraan Operasional');
        $all->assertSee('Semua Jenis')->assertSee('Semua Kampus')->assertSee('>Filter<', false)->assertSee('>Reset<', false);
        $all->assertSee('Inventaris Cibubur')->assertSee('Ruangan Cibubur')->assertSee('Kendaraan Tanjung');
        $all->assertSee(route('borrowings.show', Borrowing::query()->where('borrower_name', 'Inventaris Cibubur')->first()), false);
        $all->assertSee(route('room-borrowings.show', RoomBorrowing::query()->where('borrower_name', 'Ruangan Cibubur')->first()), false);
        $all->assertSee(route('vehicle-borrowings.show', VehicleBorrowing::query()->where('borrower_name', 'Kendaraan Tanjung')->first()), false);

        $this->actingAs($admin)->get(route('borrowing-records.index', ['kind' => 'ruangan']))
            ->assertOk()->assertSee('Ruangan Cibubur')->assertDontSee('Kendaraan Tanjung')->assertDontSee('Inventaris Cibubur');

        $this->actingAs($admin)->get(route('borrowing-records.index', ['campus_id' => $cibubur['campus']->id]))
            ->assertOk()->assertSee('Ruangan Cibubur')->assertSee('Inventaris Cibubur')->assertDontSee('Kendaraan Tanjung');

        $filtered = $this->actingAs($admin)->get(route('borrowing-records.index', [
            'kind' => 'ruangan',
            'campus_id' => $cibubur['campus']->id,
            'q' => 'Seminar',
        ]))->assertOk();
        $filtered->assertSee('Ruangan Cibubur')->assertDontSee('Inventaris Cibubur');
        $filtered->assertSee('value="ruangan"', false);
        $filtered->assertSee('value="'.$cibubur['campus']->id.'"', false);

        $this->actingAs($admin)->get(route('borrowing-records.index', ['campus_id' => ['id' => 1]]))
            ->assertOk();
    }

    public function test_combined_history_applies_kind_campus_and_status_together(): void
    {
        $admin = $this->makeAdmin();
        $viewer = $this->makeViewer();
        $tanjung = $this->place('Tanjung Duren');
        $cibubur = $this->place('Cibubur');
        $loanItem = $this->itemIn($tanjung, InventoryStatus::Aktif, 'INV-PINJAM-'.uniqid());
        $approvedItem = $this->itemIn($tanjung, InventoryStatus::Aktif, 'INV-SETUJU-'.uniqid());
        $otherItem = $this->itemIn($cibubur, InventoryStatus::Aktif, 'INV-LAIN-'.uniqid());

        $this->actingAs($viewer)->post(route('borrowings.store'), $this->inventoryPayload($loanItem->id, [
            'borrower_name' => 'Inventaris Dipinjam Tanjung',
        ]))->assertRedirect();
        $this->actingAs($viewer)->post(route('borrowings.store'), $this->inventoryPayload($approvedItem->id, [
            'borrower_name' => 'Inventaris Disetujui Tanjung',
        ]))->assertRedirect();
        $this->actingAs($viewer)->post(route('borrowings.store'), $this->inventoryPayload($otherItem->id, [
            'borrower_name' => 'Inventaris Dipinjam Cibubur',
        ]))->assertRedirect();

        Borrowing::query()->where('borrower_name', 'Inventaris Dipinjam Tanjung')->update(['status' => BorrowingStatus::Dipinjam->value]);
        Borrowing::query()->where('borrower_name', 'Inventaris Disetujui Tanjung')->update(['status' => BorrowingStatus::Disetujui->value]);
        Borrowing::query()->where('borrower_name', 'Inventaris Dipinjam Cibubur')->update(['status' => BorrowingStatus::Dipinjam->value]);

        RoomBorrowing::query()->create([
            'room_id' => $tanjung['room']->id,
            'campus_id' => $tanjung['campus']->id,
            'borrower_name' => 'Ruangan Disetujui Tanjung',
            'borrower_phone' => '081234567890',
            'purpose' => 'Rapat masih berjalan',
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'status' => FacilityRequestStatus::Disetujui,
            'requested_by' => $viewer->id,
        ]);
        RoomBorrowing::query()->create([
            'room_id' => $cibubur['room']->id,
            'campus_id' => $cibubur['campus']->id,
            'borrower_name' => 'Ruangan Disetujui Cibubur',
            'borrower_phone' => '081234567890',
            'purpose' => 'Rapat kampus lain',
            'starts_at' => now()->addDays(4),
            'ends_at' => now()->addDays(4)->addHours(2),
            'status' => FacilityRequestStatus::Disetujui,
            'requested_by' => $viewer->id,
        ]);
        RoomBorrowing::query()->create([
            'room_id' => $tanjung['room']->id,
            'campus_id' => $tanjung['campus']->id,
            'borrower_name' => 'Ruangan Selesai Tanjung',
            'borrower_phone' => '081234567890',
            'purpose' => 'Rapat sudah lewat',
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDays(3)->addHours(2),
            'status' => FacilityRequestStatus::Disetujui,
            'requested_by' => $viewer->id,
        ]);
        $this->storeVehicle($viewer->id, $cibubur['campus']->id, 'Kendaraan Cibubur', FacilityRequestStatus::Disetujui);
        $this->storeVehicle($viewer->id, $tanjung['campus']->id, 'Kendaraan Tanjung', FacilityRequestStatus::MenungguPersetujuan);
        RoomBorrowing::query()->create([
            'room_id' => $tanjung['room']->id,
            'campus_id' => null,
            'borrower_name' => 'Ruangan Tanpa Kampus',
            'borrower_phone' => '081234567890',
            'purpose' => 'Data lama',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(1),
            'status' => FacilityRequestStatus::MenungguPersetujuan,
            'requested_by' => $viewer->id,
        ]);

        $this->actingAs($admin)->get(route('borrowing-records.index', [
            'kind' => 'inventaris',
            'campus_id' => $tanjung['campus']->id,
            'status' => BorrowingStatus::Dipinjam->value,
        ]))->assertOk()
            ->assertSee('Inventaris Dipinjam Tanjung')
            ->assertDontSee('Inventaris Disetujui Tanjung')
            ->assertDontSee('Inventaris Dipinjam Cibubur')
            ->assertDontSee('Ruangan Disetujui Tanjung')
            ->assertDontSee('Kendaraan Cibubur');

        $this->actingAs($admin)->get(route('borrowing-records.index', [
            'kind' => 'ruangan',
            'status' => FacilityRequestStatus::Disetujui->value,
        ]))->assertOk()
            ->assertSee('Ruangan Disetujui Tanjung')
            ->assertSee('Ruangan Disetujui Cibubur')
            ->assertDontSee('Ruangan Selesai Tanjung')
            ->assertDontSee('Kendaraan Cibubur')
            ->assertDontSee('Inventaris Dipinjam Tanjung');

        $this->actingAs($admin)->get(route('borrowing-records.index', [
            'kind' => 'ruangan',
            'status' => 'selesai',
        ]))->assertOk()
            ->assertSee('Ruangan Selesai Tanjung')
            ->assertDontSee('Ruangan Disetujui Tanjung');

        $this->actingAs($admin)->get(route('borrowing-records.index', [
            'kind' => 'kendaraan',
            'campus_id' => $cibubur['campus']->id,
        ]))->assertOk()
            ->assertSee('Kendaraan Cibubur')
            ->assertDontSee('Kendaraan Tanjung')
            ->assertDontSee('Ruangan Disetujui Cibubur');

        $this->actingAs($admin)->get(route('borrowing-records.index', [
            'kind' => 'inventaris',
            'campus_id' => $tanjung['campus']->id,
            'status' => BorrowingStatus::Dikembalikan->value,
            'q' => 'tidak-ada',
        ]))->assertOk()->assertSee('Tidak ada peminjaman yang sesuai dengan filter.');

        $this->actingAs($admin)->get(route('borrowing-records.index'))
            ->assertOk()
            ->assertSee('Ruangan Tanpa Kampus')
            ->assertSee('href="'.route('borrowing-records.index').'"', false);

        $this->assertSame(BorrowingStatus::Dipinjam, Borrowing::query()->where('borrower_name', 'Inventaris Dipinjam Tanjung')->first()->status);
        $this->assertSame(FacilityRequestStatus::Disetujui, RoomBorrowing::query()->where('borrower_name', 'Ruangan Selesai Tanjung')->first()->status);
    }

    public function test_combined_history_pagination_keeps_type_and_campus_query(): void
    {
        $admin = $this->makeAdmin();
        $viewer = $this->makeViewer();
        $place = $this->place('Kampus Halaman');

        $oldest = null;
        foreach (range(1, 11) as $index) {
            $row = RoomBorrowing::query()->create([
                'room_id' => $place['room']->id,
                'campus_id' => $place['campus']->id,
                'borrower_name' => $index === 1 ? 'Peminjam Halaman Dua' : 'Peminjam Halaman Satu '.$index,
                'borrower_phone' => '081234567890',
                'purpose' => 'Rapat '.$index,
                'starts_at' => now()->addDays($index),
                'ends_at' => now()->addDays($index)->addHours(2),
                'status' => FacilityRequestStatus::MenungguPersetujuan,
                'requested_by' => $viewer->id,
            ]);
            $row->forceFill(['created_at' => now()->subMinutes(20 - $index)])->save();
            if ($index === 1) {
                $oldest = $row;
            }
        }

        $page = $this->actingAs($admin)->get(route('borrowing-records.index', [
            'kind' => 'ruangan',
            'campus_id' => $place['campus']->id,
            'status' => FacilityRequestStatus::MenungguPersetujuan->value,
            'q' => 'Rapat',
        ]))->assertOk();
        $page->assertSee('page=2', false);
        $page->assertSee('kind=ruangan', false);
        $page->assertSee('campus_id='.$place['campus']->id, false);
        $page->assertSee('status='.FacilityRequestStatus::MenungguPersetujuan->value, false);
        $page->assertSee('q=Rapat', false);
        $page->assertDontSee('Peminjam Halaman Dua');

        $this->actingAs($admin)->get(route('borrowing-records.index', [
            'kind' => 'ruangan',
            'campus_id' => $place['campus']->id,
            'status' => FacilityRequestStatus::MenungguPersetujuan->value,
            'q' => 'Rapat',
            'page' => 2,
        ]))->assertOk()->assertSee('Peminjam Halaman Dua');

        $this->assertNotNull($oldest);
    }

    public function test_viewer_cannot_open_approval_or_another_users_request(): void
    {
        $owner = $this->makeViewer(['email' => 'owner-room@undira.ac.id']);
        $other = $this->makeViewer(['email' => 'other-room@undira.ac.id']);
        $admin = $this->makeAdmin();
        $place = $this->place('Kampus Akses');

        $this->actingAs($owner)->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id));
        $room = RoomBorrowing::query()->first();
        $this->actingAs($owner)->post(route('vehicle-borrowings.store'), $this->vehiclePayload($place['campus']->id));
        $vehicle = VehicleBorrowing::query()->first();

        $this->actingAs($other)->get(route('room-borrowings.show', $room))->assertForbidden();
        $this->actingAs($other)->get(route('vehicle-borrowings.show', $vehicle))->assertForbidden();
        $this->actingAs($other)->get(route('room-borrowings.approvals'))->assertForbidden();
        $this->actingAs($other)->get(route('vehicle-borrowings.approvals'))->assertForbidden();
        $this->actingAs($other)->get(route('room-borrowings.history'))->assertForbidden();
        $this->actingAs($other)->post(route('room-borrowings.decide', $room), [
            'decision' => 'approve',
        ])->assertForbidden();
        $this->actingAs($other)->post(route('vehicle-borrowings.decide', $vehicle), [
            'decision' => 'approve',
            'assignment' => 'gocar',
        ])->assertForbidden();

        $this->actingAs($owner)->get(route('room-borrowings.show', $room))->assertOk();
        $history = $this->actingAs($other)->get(route('borrowing-records.index'))->assertOk();
        $history->assertDontSee('Sari Ruangan');
        $history->assertDontSee('Sari Kendaraan');
        $this->actingAs($owner)->get(route('borrowing-records.index'))->assertOk()->assertSee('Sari Ruangan');
        $this->actingAs($admin)->post(route('room-borrowings.decide', $room), ['decision' => 'approve'])->assertRedirect();
        $this->assertSame(FacilityRequestStatus::Disetujui, $room->fresh()->status);
    }

    public function test_sidebar_and_inventory_borrowing_remain_available(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin(['email' => 'admin-menu-facility@undira.ac.id']);
        $viewer = $this->makeViewer(['email' => 'viewer-menu-facility@undira.ac.id']);
        $place = $this->place('Kampus Regresi');
        $item = $this->itemIn($place, InventoryStatus::Aktif);

        $adminHtml = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('Approval Inventaris', $adminHtml);
        $this->assertStringContainsString('Approval Ruangan', $adminHtml);
        $this->assertStringContainsString('Approval Kendaraan', $adminHtml);
        $this->assertStringContainsString('Riwayat Approval Inventaris', $adminHtml);
        $this->assertStringContainsString('Riwayat Approval Ruangan', $adminHtml);
        $this->assertStringContainsString('Riwayat Approval Kendaraan', $adminHtml);
        $this->assertStringContainsString('Master Kendaraan', $adminHtml);
        $this->assertStringContainsString(route('borrowing-records.index'), $adminHtml);

        $viewerHtml = $this->actingAs($viewer)->get(route('room-borrowings.create'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Approval Ruangan', $viewerHtml);
        $this->assertStringNotContainsString('Master Kendaraan', $viewerHtml);
        $this->assertStringContainsString('menu-open', $viewerHtml);

        $this->actingAs($viewer)
            ->post(route('borrowings.store'), $this->inventoryPayload($item->id))
            ->assertRedirect(route('borrowings.index'));
        $borrowing = Borrowing::query()->first();
        $this->assertSame(BorrowingStatus::MenungguPersetujuan, $borrowing->status);
        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);

        $this->actingAs($viewer)->get(route('room-borrowings.create'))
            ->assertOk()
            ->assertSee('card-undira', false)
            ->assertSee('undira-layout.css', false);
        $this->assertStringContainsString('.nav-treeview .nav-treeview > .nav-item > .nav-link', file_get_contents(public_path('css/undira-layout.css')));
        $this->assertStringContainsString('body.dark-mode .nav-treeview > .nav-item > .nav-link.active', file_get_contents(public_path('css/undira-layout.css')));
    }

    /**
     * @return array{campus: Campus, building: Building, floor: Floor, room: Room}
     */
    private function place(string $name): array
    {
        $suffix = uniqid();
        $campus = Campus::query()->create([
            'code' => 'CP'.$suffix,
            'name' => $name,
            'slug' => 'kampus-'.$suffix,
            'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'code' => 'GD'.$suffix,
            'name' => 'Gedung '.$name,
            'slug' => 'gedung-'.$suffix,
            'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id,
            'code' => 'LT'.$suffix,
            'name' => 'Lantai 1',
            'floor_number' => 1,
            'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id,
            'code' => 'RM'.$suffix,
            'name' => 'Ruang '.$name,
            'room_type' => 'Kelas',
            'capacity' => 30,
            'is_active' => true,
        ]);

        return compact('campus', 'building', 'floor', 'room');
    }

    private function itemIn(array $place, InventoryStatus $status, ?string $number = null): InventoryItem
    {
        $suffix = uniqid();
        $category = Category::query()->create([
            'code' => 'CT'.$suffix,
            'name' => 'Kategori '.$suffix,
            'slug' => 'kategori-'.$suffix,
            'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id,
            'code' => 'SC'.$suffix,
            'name' => 'Sub '.$suffix,
            'slug' => 'sub-'.$suffix,
            'is_active' => true,
        ]);

        return InventoryItem::query()->create([
            'inventory_number' => $number ?: 'INV-FC-'.$suffix,
            'barcode' => 'BC-'.$suffix,
            'name' => 'Proyektor '.$suffix,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'room_id' => $place['room']->id,
            'condition' => InventoryCondition::Bagus,
            'status' => $status,
            'quantity' => 1,
            'unit' => 'unit',
            'responsible_person' => 'PIC',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storeVehicle(int $userId, int $campusId, string $name, FacilityRequestStatus $status): void
    {
        VehicleBorrowing::query()->create([
            'campus_id' => $campusId,
            'borrower_name' => $name,
            'borrower_email' => 'kendaraan@undira.ac.id',
            'borrower_phone' => '081234567890',
            'purpose' => 'Perjalanan dinas',
            'service_type' => 'drop_off',
            'origin' => 'Kampus',
            'destination' => 'Grogol',
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(3),
            'status' => $status,
            'requested_by' => $userId,
        ]);
    }

    private function roomPayload(int $roomId, array $overrides = []): array
    {
        return array_merge([
            'room_id' => $roomId,
            'borrower_name' => 'Sari Ruangan',
            'borrower_email' => 'sari.ruangan@undira.ac.id',
            'borrower_phone' => '081234567890',
            'activity_type' => 'rapat',
            'purpose' => 'Rapat koordinasi persiapan kegiatan universitas',
            'participant_internal' => '1',
            'approval_document' => UploadedFile::fake()->create('persetujuan.pdf', 80, 'application/pdf'),
            'starts_at' => '2026-11-02T09:00',
            'ends_at' => '2026-11-02T11:00',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function vehiclePayload(int $campusId, array $overrides = []): array
    {
        $routeId = array_key_exists('route_id', $overrides)
            ? $overrides['route_id']
            : OperationalRoute::query()->create([
                'name' => 'Cibubur - Grogol '.uniqid(),
                'is_active' => true,
            ])->id;

        return array_merge([
            'campus_id' => $campusId,
            'route_id' => $routeId,
            'borrower_name' => 'Sari Kendaraan',
            'borrower_email' => 'sari.kendaraan@undira.ac.id',
            'borrower_phone' => '081234567890',
            'purpose' => 'Antar dokumen',
            'service_type' => 'drop_off',
            'destination' => 'Grogol, Jakarta Barat',
            'passenger_count' => 1,
            'passengers' => [
                ['name' => 'Sari', 'unit' => 'Biro PPPA', 'title' => 'Staff'],
            ],
            'starts_at' => '2026-11-02T09:00',
            'ends_at' => '2026-11-02T11:00',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function inventoryPayload(int $itemId, array $overrides = []): array
    {
        return array_merge([
            'inventory_item_ids' => [$itemId],
            'borrower_name' => 'Sari Inventaris',
            'borrower_email' => 'sari.inventaris@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrow_date' => '2026-11-02T09:30',
            'expected_return_date' => '2026-11-02T10:30',
            'notes' => 'Pinjam proyektor',
        ], $overrides);
    }

    private function failMail(): void
    {
        app(MailManager::class)->extend('failing', function () {
            return new Mailer('failing', app('view'), new class implements TransportInterface
            {
                public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
                {
                    throw new RuntimeException('smtp');
                }

                public function __toString(): string
                {
                    return 'failing';
                }
            }, app('events'));
        });
        config(['mail.default' => 'failing']);
    }
}
