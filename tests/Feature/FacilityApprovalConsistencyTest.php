<?php

namespace Tests\Feature;

use App\Enums\FacilityRequestStatus;
use App\Enums\RoomActivityType;
use App\Enums\VehicleServiceType;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Floor;
use App\Models\Room;
use App\Models\RoomBorrowing;
use App\Models\User;
use App\Models\VehicleBorrowing;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class FacilityApprovalConsistencyTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_room_history_links_number_actions_and_receipt_without_inventory_columns(): void
    {
        $admin = $this->makeAdmin();
        $owner = $this->makeViewer(['email' => 'room-receipt-owner@undira.ac.id']);
        $other = $this->makeViewer(['email' => 'room-receipt-other@undira.ac.id']);
        $room = $this->room($owner);
        $long = str_repeat('Rapat koordinasi ruang ', 12);
        $room->update([
            'approval_number' => 'RNG-20261013-101500-321',
            'status' => FacilityRequestStatus::Disetujui,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'purpose' => $long,
            'approval_document_path' => 'room-borrowings/'.$room->id.'/approval.pdf',
            'approval_document_original_name' => 'persetujuan.pdf',
        ]);
        Storage::disk('local')->put($room->approval_document_path, "%PDF-1.4\nroom\n%%EOF\n");
        $before = $room->fresh()->only(['status', 'approved_at', 'purpose', 'updated_at']);

        $history = $this->actingAs($admin)->get(route('room-borrowings.history'))->assertOk()->getContent();
        $this->assertStringContainsString(route('room-borrowings.approval', $room), $history);
        $this->assertStringContainsString('RNG-20261013-101500-321', $history);
        $this->assertStringContainsString(route('room-borrowings.show', $room), $history);
        $this->assertStringContainsString(route('room-borrowings.documents.preview', [$room, 'approval']), $history);
        $this->assertStringContainsString(route('room-borrowings.receipt.pdf', $room), $history);
        $this->assertStringContainsString('Lihat Detail', $history);
        $this->assertStringContainsString('Dokumen Pendukung', $history);
        $this->assertStringContainsString('Receipt PDF', $history);

        $detail = $this->actingAs($admin)->get(route('room-borrowings.approval', $room))->assertOk()->getContent();
        $this->assertStringContainsString('Detail Approval Peminjaman', $detail);
        $this->assertStringContainsString('Lihat Receipt', $detail);
        $this->assertStringContainsString('Download PDF', $detail);
        $this->assertStringContainsString('Ruang Rapat', $detail);
        $this->assertStringContainsString($long, $detail);
        $this->assertStringNotContainsString('Nomor Inventaris', $detail);

        $receipt = $this->actingAs($admin)->get(route('room-borrowings.receipt', $room))->assertOk()->getContent();
        $this->assertStringContainsString('Receipt Approval Peminjaman', $receipt);
        $this->assertStringContainsString('Rincian Ruangan', $receipt);
        $this->assertStringContainsString('PEMINJAM', $receipt);
        $this->assertStringContainsString('BIRO PPPA', $receipt);
        $this->assertStringContainsString('Disetujui oleh:', $receipt);
        $this->assertStringContainsString($admin->name, $receipt);
        $this->assertStringNotContainsString('(Biro PPPA)', $receipt);
        $this->assertStringContainsString('overflow-wrap: break-word', $receipt);
        $this->assertStringNotContainsString('Nomor Inventaris', $receipt);
        $this->assertStringNotContainsString('ATURAN DAN TANGGUNG JAWAB PEMINJAM', $receipt);

        $pdf = $this->actingAs($admin)->get(route('room-borrowings.receipt.pdf', $room))->assertOk();
        $binary = $pdf->getContent();
        $this->assertIsString($binary);
        $this->assertStringStartsWith('%PDF', $binary);
        $this->assertMatchesRegularExpression('/MediaBox\s*\[\s*0(?:\.\d+)?\s+0(?:\.\d+)?\s+595(?:\.\d+)?\s+841(?:\.\d+)?\s*\]/', $binary);

        $room->refresh();
        $this->assertSame($before['status'], $room->status);
        $this->assertTrue($room->approved_at->eq($before['approved_at']));
        $this->assertSame($before['purpose'], $room->purpose);
        $this->assertTrue($room->updated_at->eq($before['updated_at']));

        $this->actingAs($other)->get(route('room-borrowings.approval', $room))->assertForbidden();
        $this->actingAs($other)->get(route('room-borrowings.receipt.pdf', $room))->assertForbidden();
        auth()->logout();
        $this->get(route('room-borrowings.approval', $room))->assertRedirect(route('login'));
        $this->actingAs($owner)->get(route('room-borrowings.approval', $room))->assertOk();
    }

    public function test_vehicle_history_receipt_uses_assignment_and_not_room_or_inventory_data(): void
    {
        $admin = $this->makeAdmin();
        $owner = $this->makeViewer(['email' => 'vehicle-receipt-owner@undira.ac.id']);
        $other = $this->makeViewer(['email' => 'vehicle-receipt-other@undira.ac.id']);
        $plain = $this->vehicle($owner, 'KND-20261013-101500-111', 'Kampus Sudirman');
        $gocar = $this->vehicle($owner, 'KND-20261013-101500-222', 'Bandara');
        $gocar->update(['assigned_service' => VehicleServiceType::GoCar]);

        $history = $this->actingAs($admin)->get(route('vehicle-borrowings.history'))->assertOk()->getContent();
        $this->assertStringContainsString(route('vehicle-borrowings.approval', $plain), $history);
        $this->assertStringContainsString(route('vehicle-borrowings.show', $plain), $history);
        $this->assertStringContainsString(route('vehicle-borrowings.receipt.pdf', $plain), $history);
        $this->assertStringNotContainsString(route('vehicle-borrowings.document.preview', $plain), $history);

        $detail = $this->actingAs($admin)->get(route('vehicle-borrowings.approval', $plain))->assertOk()->getContent();
        $this->assertStringContainsString('Kampus Sudirman', $detail);
        $this->assertStringContainsString('Belum ditetapkan', $detail);
        $this->assertStringContainsString('Drop-off', $detail);
        $this->assertStringNotContainsString('Nomor Inventaris', $detail);
        $this->assertStringNotContainsString('Ruang Rapat', $detail);

        $go = $this->actingAs($admin)->get(route('vehicle-borrowings.receipt', $gocar))->assertOk()->getContent();
        $this->assertStringContainsString('GoCar', $go);
        $this->assertStringContainsString('Tidak menggunakan driver internal', $go);
        $this->assertStringContainsString('Rincian Perjalanan', $go);
        $this->assertStringNotContainsString('Nomor Inventaris', $go);

        $pdf = $this->actingAs($admin)->get(route('vehicle-borrowings.receipt.pdf', $gocar))->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());

        $pending = $this->vehicle($owner, null, 'Belum bernomor');
        $pendingHistory = $this->actingAs($admin)->get(route('vehicle-borrowings.approvals'))->assertOk()->getContent();
        $this->assertStringNotContainsString(route('vehicle-borrowings.receipt.pdf', $pending), $pendingHistory);
        $this->actingAs($admin)->get(route('vehicle-borrowings.approval', $pending))->assertNotFound();
        $this->actingAs($other)->get(route('vehicle-borrowings.approval', $plain))->assertForbidden();
        $this->actingAs($this->makeTeknisi())->get(route('vehicle-borrowings.receipt', $plain))->assertForbidden();
    }

    public function test_combined_records_link_each_approval_number_to_its_own_module(): void
    {
        $admin = $this->makeAdmin();
        $owner = $this->makeViewer(['email' => 'records-owner@undira.ac.id']);
        $room = $this->room($owner);
        $room->update(['approval_number' => 'RNG-20261013-090000-100', 'status' => FacilityRequestStatus::Disetujui]);
        $vehicle = $this->vehicle($owner, 'KND-20261013-090000-100', 'Tujuan Khusus');

        $html = $this->actingAs($admin)->get(route('borrowing-records.index'))->assertOk()->getContent();
        $this->assertStringContainsString(route('room-borrowings.approval', $room), $html);
        $this->assertStringContainsString(route('vehicle-borrowings.approval', $vehicle), $html);
        $this->assertStringNotContainsString(route('borrowing.approval.public', 'RNG-20261013-090000-100'), $html);
        $this->assertStringNotContainsString(route('borrowing.approval.public', 'KND-20261013-090000-100'), $html);
    }

    private function room(User $owner): RoomBorrowing
    {
        $suffix = uniqid();
        $campus = Campus::query()->create([
            'code' => 'CR'.$suffix, 'name' => 'Kampus Ruang', 'slug' => 'kampus-ruang-'.$suffix, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'BR'.$suffix, 'name' => 'Gedung Ruang', 'slug' => 'gedung-ruang-'.$suffix, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'FR'.$suffix, 'name' => 'Lantai 2', 'floor_number' => 2, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'RR'.$suffix, 'name' => 'Ruang Rapat', 'room_type' => 'Kelas', 'capacity' => 20, 'is_active' => true,
        ]);

        return RoomBorrowing::query()->create([
            'room_id' => $room->id,
            'campus_id' => $campus->id,
            'borrower_name' => 'Peminjam Ruangan',
            'borrower_email' => 'ruang@undira.ac.id',
            'borrower_phone' => '081234567890',
            'activity_type' => RoomActivityType::Rapat->value,
            'purpose' => 'Rapat koordinasi',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'status' => FacilityRequestStatus::MenungguPersetujuan,
            'requested_by' => $owner->id,
        ]);
    }

    private function vehicle(User $owner, ?string $number, string $destination): VehicleBorrowing
    {
        $suffix = uniqid();
        $campus = Campus::query()->create([
            'code' => 'CV'.$suffix, 'name' => 'Kampus Kendaraan', 'slug' => 'kampus-kendaraan-'.$suffix, 'is_active' => true,
        ]);

        return VehicleBorrowing::query()->create([
            'campus_id' => $campus->id,
            'borrower_name' => 'Peminjam Kendaraan',
            'borrower_email' => 'kendaraan@undira.ac.id',
            'borrower_phone' => '081234567890',
            'unit' => 'Biro Umum',
            'purpose' => 'Kunjungan dinas',
            'service_type' => VehicleServiceType::DropOff,
            'origin' => 'Kampus Cibubur',
            'destination' => $destination,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(3),
            'status' => $number ? FacilityRequestStatus::Disetujui : FacilityRequestStatus::MenungguPersetujuan,
            'approval_number' => $number,
            'requested_by' => $owner->id,
            'approved_by' => $number ? $owner->id : null,
            'approved_at' => $number ? now() : null,
        ]);
    }
}
