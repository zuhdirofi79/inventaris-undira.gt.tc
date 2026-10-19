<?php

namespace Tests\Feature;

use App\Enums\BorrowingStatus;
use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Mail\RoomBorrowingDecisionMail;
use App\Models\Borrowing;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\OperationalRoute;
use App\Models\Room;
use App\Models\RoomBorrowing;
use App\Models\SubCategory;
use App\Models\User;
use App\Models\VehicleBorrowing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class BorrowingBorrowerEmailBindingTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_viewer_create_forms_autofill_name_and_email_as_readonly(): void
    {
        $viewer = $this->makeViewer([
            'name' => 'Azwar Viewer',
            'email' => 'autofill.viewer@undira.ac.id',
        ]);

        foreach ([
            route('borrowings.create'),
            route('room-borrowings.create'),
            route('vehicle-borrowings.create'),
        ] as $url) {
            $this->actingAs($viewer)
                ->get($url)
                ->assertOk()
                ->assertSee('value="Azwar Viewer"', false)
                ->assertSee('value="autofill.viewer@undira.ac.id"', false)
                ->assertSee('Email diambil otomatis dari akun yang sedang login.');
        }
    }

    public function test_admin_create_forms_keep_identity_editable(): void
    {
        $admin = $this->makeAdmin([
            'name' => 'Admin Operasional',
            'email' => 'admin.operasional@undira.ac.id',
        ]);

        $this->actingAs($admin)
            ->get(route('borrowings.create'))
            ->assertOk()
            ->assertSee('Admin/Super Admin dapat mengisi email peminjam atas nama orang lain.')
            ->assertDontSee('Email diambil otomatis dari akun yang sedang login.');
    }

    public function test_room_store_viewer_ignores_spoofed_identity(): void
    {
        Mail::fake();
        $viewer = $this->makeViewer([
            'name' => 'Pemohon Ruang Asli',
            'email' => 'pemohon.ruang@undira.ac.id',
        ]);
        $place = $this->place();

        $this->actingAs($viewer)
            ->post(route('room-borrowings.store'), [
                'room_id' => $place['room']->id,
                'borrower_name' => 'Nama Palsu',
                'borrower_email' => 'orang.lain@undira.ac.id',
                'borrower_phone' => '081234567890',
                'borrower_affiliation' => 'PPPA',
                'activity_type' => 'rapat',
                'purpose' => 'Rapat koordinasi persiapan kegiatan universitas',
                'participant_internal' => '1',
                'participant_internal_count' => 20,
                'approval_document' => UploadedFile::fake()->create('persetujuan.pdf', 80, 'application/pdf'),
                'starts_at' => '2026-11-20T09:00',
                'ends_at' => '2026-11-20T11:00',
            ])
            ->assertRedirect();

        $saved = RoomBorrowing::query()->first();
        $this->assertNotNull($saved);
        $this->assertSame('Pemohon Ruang Asli', $saved->borrower_name);
        $this->assertSame('pemohon.ruang@undira.ac.id', $saved->borrower_email);
        $this->assertSame($viewer->id, $saved->requested_by);
    }

    public function test_vehicle_store_viewer_ignores_spoofed_identity(): void
    {
        Mail::fake();
        $viewer = $this->makeViewer([
            'name' => 'Pemohon Kendaraan Asli',
            'email' => 'pemohon.kendaraan@undira.ac.id',
        ]);
        $campus = Campus::query()->create([
            'code' => 'EMV',
            'name' => 'Kampus Email Vehicle',
            'slug' => 'kampus-email-vehicle-'.uniqid(),
            'is_active' => true,
        ]);
        $route = OperationalRoute::query()->create(['name' => 'Rute Email', 'is_active' => true]);

        $this->actingAs($viewer)
            ->post(route('vehicle-borrowings.store'), [
                'campus_id' => $campus->id,
                'route_id' => $route->id,
                'borrower_name' => 'Nama Palsu',
                'borrower_email' => 'hacker@undira.ac.id',
                'borrower_phone' => '081234567890',
                'unit' => 'PPPA',
                'purpose' => 'Antar dokumen',
                'service_type' => 'drop_off',
                'destination' => 'Grogol',
                'passenger_count' => 1,
                'passengers' => [
                    ['name' => 'Pemohon', 'unit' => 'Biro', 'title' => 'Staff'],
                ],
                'starts_at' => '2026-11-21T09:00',
                'ends_at' => '2026-11-21T11:00',
            ])
            ->assertRedirect();

        $saved = VehicleBorrowing::query()->first();
        $this->assertNotNull($saved);
        $this->assertSame('Pemohon Kendaraan Asli', $saved->borrower_name);
        $this->assertSame('pemohon.kendaraan@undira.ac.id', $saved->borrower_email);
    }

    public function test_inventory_store_viewer_ignores_spoofed_identity(): void
    {
        $viewer = $this->makeViewer([
            'name' => 'Pemohon Inventaris Asli',
            'email' => 'pemohon.inventaris@undira.ac.id',
        ]);
        $item = $this->inventoryItem();

        $this->actingAs($viewer)
            ->post(route('borrowings.store'), [
                'inventory_item_id' => $item->id,
                'borrower_name' => 'Nama Palsu',
                'borrower_email' => 'bukan.pemohon@undira.ac.id',
                'borrower_phone' => '081234567890',
                'borrower_affiliation' => 'PPPA',
                'borrow_date' => now()->format('Y-m-d\TH:i'),
                'expected_return_date' => now()->addDay()->format('Y-m-d\TH:i'),
                'notes' => 'Keperluan uji',
                'supporting_document' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertRedirect();

        $saved = Borrowing::query()->first();
        $this->assertNotNull($saved);
        $this->assertSame('Pemohon Inventaris Asli', $saved->borrower_name);
        $this->assertSame('pemohon.inventaris@undira.ac.id', $saved->borrower_email);
    }

    public function test_admin_can_store_inventory_on_behalf_of_another_borrower(): void
    {
        $admin = $this->makeAdmin([
            'name' => 'Admin Inventaris',
            'email' => 'admin.inventaris@undira.ac.id',
        ]);
        $item = $this->inventoryItem();

        $this->actingAs($admin)
            ->post(route('borrowings.store'), [
                'inventory_item_id' => $item->id,
                'borrower_name' => 'Dosen Tamu',
                'borrower_email' => 'dosen.tamu@undira.ac.id',
                'borrower_phone' => '081234567890',
                'borrower_affiliation' => 'PPPA',
                'borrow_date' => now()->format('Y-m-d\TH:i'),
                'expected_return_date' => now()->addDay()->format('Y-m-d\TH:i'),
                'notes' => 'Keperluan atas nama orang lain',
                'supporting_document' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertRedirect();

        $saved = Borrowing::query()->first();
        $this->assertNotNull($saved);
        $this->assertSame('Dosen Tamu', $saved->borrower_name);
        $this->assertSame('dosen.tamu@undira.ac.id', $saved->borrower_email);
        $this->assertNotSame('admin.inventaris@undira.ac.id', $saved->borrower_email);
    }

    public function test_super_admin_can_store_room_on_behalf_of_another_borrower(): void
    {
        Mail::fake();
        $super = $this->makeSuperAdmin([
            'name' => 'Super Admin',
            'email' => 'super.admin@undira.ac.id',
        ]);
        $place = $this->place('Proxy Room');

        $this->actingAs($super)
            ->post(route('room-borrowings.store'), [
                'room_id' => $place['room']->id,
                'borrower_name' => 'Panitia Acara',
                'borrower_email' => 'panitia@undira.ac.id',
                'borrower_phone' => '081234567890',
                'borrower_affiliation' => 'PPPA',
                'activity_type' => 'rapat',
                'purpose' => 'Pengajuan atas nama panitia',
                'participant_internal' => '1',
                'participant_internal_count' => 10,
                'approval_document' => UploadedFile::fake()->create('persetujuan.pdf', 80, 'application/pdf'),
                'starts_at' => '2026-11-24T09:00',
                'ends_at' => '2026-11-24T11:00',
            ])
            ->assertRedirect();

        $saved = RoomBorrowing::query()->first();
        $this->assertNotNull($saved);
        $this->assertSame('Panitia Acara', $saved->borrower_name);
        $this->assertSame('panitia@undira.ac.id', $saved->borrower_email);
        $this->assertSame($super->id, $saved->requested_by);
    }

    public function test_admin_can_store_vehicle_on_behalf_of_another_borrower(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin([
            'name' => 'Admin Kendaraan',
            'email' => 'admin.kendaraan@undira.ac.id',
        ]);
        $campus = Campus::query()->create([
            'code' => 'PXV',
            'name' => 'Kampus Proxy Vehicle',
            'slug' => 'kampus-proxy-vehicle-'.uniqid(),
            'is_active' => true,
        ]);
        $route = OperationalRoute::query()->create(['name' => 'Rute Proxy', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('vehicle-borrowings.store'), [
                'campus_id' => $campus->id,
                'route_id' => $route->id,
                'borrower_name' => 'Tamu Undangan',
                'borrower_email' => 'tamu@undira.ac.id',
                'borrower_phone' => '081234567890',
                'unit' => 'PPPA',
                'purpose' => 'Antar tamu',
                'service_type' => 'drop_off',
                'destination' => 'Bandara',
                'passenger_count' => 1,
                'passengers' => [
                    ['name' => 'Tamu', 'unit' => 'Eksternal', 'title' => 'Guest'],
                ],
                'starts_at' => '2026-11-25T09:00',
                'ends_at' => '2026-11-25T11:00',
            ])
            ->assertRedirect();

        $saved = VehicleBorrowing::query()->first();
        $this->assertNotNull($saved);
        $this->assertSame('Tamu Undangan', $saved->borrower_name);
        $this->assertSame('tamu@undira.ac.id', $saved->borrower_email);
    }

    public function test_admin_edit_can_update_borrower_identity(): void
    {
        $requester = $this->makeAdmin(['email' => 'historis@undira.ac.id']);
        $editor = $this->makeAdmin(['email' => 'admin.editor@undira.ac.id']);
        $item = $this->inventoryItem();

        $this->actingAs($requester)
            ->post(route('borrowings.store'), [
                'inventory_item_id' => $item->id,
                'borrower_name' => 'Historis',
                'borrower_email' => 'historis@undira.ac.id',
                'borrower_phone' => '081234567890',
                'borrower_affiliation' => 'PPPA',
                'borrow_date' => now()->format('Y-m-d\TH:i'),
                'expected_return_date' => now()->addDays(2)->format('Y-m-d\TH:i'),
                'notes' => 'Catatan awal',
                'supporting_document' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertRedirect();

        $borrowing = Borrowing::query()->first();
        $this->assertSame('historis@undira.ac.id', $borrowing->borrower_email);

        $this->actingAs($editor)
            ->from(route('borrowings.edit', $borrowing))
            ->put(route('borrowings.update', $borrowing), [
                'borrower_name' => 'Historis Diedit',
                'borrower_email' => 'peminjam.baru@undira.ac.id',
                'borrower_phone' => '081234567890',
                'borrower_affiliation' => 'PPPA',
                'borrow_date' => now()->format('Y-m-d\TH:i'),
                'expected_return_date' => now()->addDays(2)->format('Y-m-d\TH:i'),
                'notes' => 'Catatan diubah admin',
                'status' => BorrowingStatus::MenungguPersetujuan->value,
            ])
            ->assertRedirect(route('borrowings.show', $borrowing));

        $fresh = $borrowing->fresh();
        $this->assertSame('peminjam.baru@undira.ac.id', $fresh->borrower_email);
        $this->assertSame('Historis Diedit', $fresh->borrower_name);
    }

    public function test_non_admin_editor_keeps_historical_borrower_identity(): void
    {
        $staff = $this->makeStaff([
            'name' => 'Pemilik Transaksi',
            'email' => 'pemilik@undira.ac.id',
        ]);
        $item = $this->inventoryItem();

        $this->actingAs($staff)
            ->post(route('borrowings.store'), [
                'inventory_item_id' => $item->id,
                'borrower_name' => 'Spoof Name',
                'borrower_email' => 'spoof@undira.ac.id',
                'borrower_phone' => '081234567890',
                'borrower_affiliation' => 'PPPA',
                'borrow_date' => now()->format('Y-m-d\TH:i'),
                'expected_return_date' => now()->addDays(2)->format('Y-m-d\TH:i'),
                'notes' => 'Catatan awal',
                'supporting_document' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertRedirect();

        $borrowing = Borrowing::query()->first();
        $this->assertSame('Pemilik Transaksi', $borrowing->borrower_name);
        $this->assertSame('pemilik@undira.ac.id', $borrowing->borrower_email);

        $this->actingAs($staff)
            ->from(route('borrowings.edit', $borrowing))
            ->put(route('borrowings.update', $borrowing), [
                'borrower_name' => 'Nama Diubah',
                'borrower_email' => 'diubah@undira.ac.id',
                'borrower_phone' => '081234567891',
                'borrower_affiliation' => 'PPPA',
                'borrow_date' => now()->format('Y-m-d\TH:i'),
                'expected_return_date' => now()->addDays(3)->format('Y-m-d\TH:i'),
                'notes' => 'Catatan diubah pemilik',
                'status' => BorrowingStatus::MenungguPersetujuan->value,
            ])
            ->assertRedirect(route('borrowings.show', $borrowing));

        $fresh = $borrowing->fresh();
        $this->assertSame('Pemilik Transaksi', $fresh->borrower_name);
        $this->assertSame('pemilik@undira.ac.id', $fresh->borrower_email);
        $this->assertSame('081234567891', $fresh->borrower_phone);
    }

    public function test_room_approval_mail_goes_to_borrower_not_admin(): void
    {
        Mail::fake();
        $viewer = $this->makeViewer(['email' => 'penerima.ruang@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin.ruang@undira.ac.id']);
        $place = $this->place('Email Approval');

        $this->actingAs($viewer)->post(route('room-borrowings.store'), [
            'room_id' => $place['room']->id,
            'borrower_name' => 'Penerima',
            'borrower_email' => 'salah@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrower_affiliation' => 'PPPA',
            'activity_type' => 'rapat',
            'purpose' => 'Rapat uji email approval',
            'participant_internal' => '1',
            'participant_internal_count' => 5,
            'approval_document' => UploadedFile::fake()->create('persetujuan.pdf', 80, 'application/pdf'),
            'starts_at' => '2026-11-22T09:00',
            'ends_at' => '2026-11-22T11:00',
        ])->assertRedirect();

        $borrowing = RoomBorrowing::query()->first();
        $this->assertSame('penerima.ruang@undira.ac.id', $borrowing->borrower_email);

        $this->actingAs($admin)
            ->post(route('room-borrowings.decide', $borrowing), ['decision' => 'approve'])
            ->assertRedirect();

        Mail::assertSent(RoomBorrowingDecisionMail::class, function (RoomBorrowingDecisionMail $mail) {
            return $mail->hasTo('penerima.ruang@undira.ac.id')
                && ! $mail->hasTo('admin.ruang@undira.ac.id');
        });
    }

    public function test_admin_proxy_room_approval_mail_goes_to_manual_borrower_email(): void
    {
        Mail::fake();
        $adminRequester = $this->makeAdmin(['email' => 'admin.pemohon@undira.ac.id']);
        $adminApprover = $this->makeAdmin(['email' => 'admin.approver@undira.ac.id']);
        $place = $this->place('Proxy Mail');

        $this->actingAs($adminRequester)->post(route('room-borrowings.store'), [
            'room_id' => $place['room']->id,
            'borrower_name' => 'Peminjam Manual',
            'borrower_email' => 'peminjam.manual@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrower_affiliation' => 'PPPA',
            'activity_type' => 'rapat',
            'purpose' => 'Uji notifikasi atas nama orang lain',
            'participant_internal' => '1',
            'participant_internal_count' => 5,
            'approval_document' => UploadedFile::fake()->create('persetujuan.pdf', 80, 'application/pdf'),
            'starts_at' => '2026-11-26T09:00',
            'ends_at' => '2026-11-26T11:00',
        ])->assertRedirect();

        $borrowing = RoomBorrowing::query()->first();
        $this->assertSame('peminjam.manual@undira.ac.id', $borrowing->borrower_email);

        $this->actingAs($adminApprover)
            ->post(route('room-borrowings.decide', $borrowing), ['decision' => 'approve'])
            ->assertRedirect();

        Mail::assertSent(RoomBorrowingDecisionMail::class, function (RoomBorrowingDecisionMail $mail) {
            return $mail->hasTo('peminjam.manual@undira.ac.id')
                && ! $mail->hasTo('admin.pemohon@undira.ac.id')
                && ! $mail->hasTo('admin.approver@undira.ac.id');
        });
    }

    public function test_account_without_email_cannot_store_room_borrowing(): void
    {
        $viewer = $this->makeViewer(['email' => 'temp-empty@undira.ac.id']);
        User::query()->whereKey($viewer->id)->update(['email' => '']);
        $viewer->refresh();
        $place = $this->place('Tanpa Email');

        $this->actingAs($viewer)
            ->from(route('room-borrowings.create'))
            ->post(route('room-borrowings.store'), [
                'room_id' => $place['room']->id,
                'borrower_name' => 'Tanpa Email',
                'borrower_email' => 'cadangan@undira.ac.id',
                'borrower_phone' => '081234567890',
                'borrower_affiliation' => 'PPPA',
                'activity_type' => 'rapat',
                'purpose' => 'Tidak boleh tersimpan',
                'participant_internal' => '1',
                'participant_internal_count' => 3,
                'approval_document' => UploadedFile::fake()->create('persetujuan.pdf', 80, 'application/pdf'),
                'starts_at' => '2026-11-23T09:00',
                'ends_at' => '2026-11-23T11:00',
            ])
            ->assertRedirect(route('room-borrowings.create'))
            ->assertSessionHasErrors('borrower_email');

        $this->assertSame(0, RoomBorrowing::query()->count());
    }

    /**
     * @return array{campus: Campus, building: Building, floor: Floor, room: Room}
     */
    private function place(string $suffix = 'Email'): array
    {
        $campus = Campus::query()->create([
            'code' => 'C'.substr(md5($suffix.uniqid()), 0, 4),
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

    private function inventoryItem(): InventoryItem
    {
        $place = $this->place('Inv');
        $category = Category::query()->create([
            'code' => 'CAT'.substr(uniqid(), -4),
            'name' => 'Kategori Email',
            'slug' => 'kategori-email-'.uniqid(),
            'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id,
            'code' => 'SUB'.substr(uniqid(), -4),
            'name' => 'Sub Email',
            'slug' => 'sub-email-'.uniqid(),
            'is_active' => true,
        ]);

        return InventoryItem::query()->create([
            'name' => 'Barang Email Bind',
            'inventory_number' => 'INV-EMAIL-'.uniqid(),
            'barcode' => 'BC-EMAIL-'.uniqid(),
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
