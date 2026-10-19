<?php

namespace Tests\Feature;

use App\Enums\FacilityRequestStatus;
use App\Enums\VehicleServiceType;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Floor;
use App\Models\Room;
use App\Models\RoomBorrowing;
use App\Models\VehicleBorrowing;
use App\Services\ReceiptVerificationService;
use Illuminate\Support\Facades\URL;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class ReceiptVerificationQrTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_room_receipt_shows_dual_qr_and_approver_name_from_database(): void
    {
        $admin = $this->makeAdmin(['name' => 'Azwar Syifa Mufiqi', 'email' => 'qr-room-admin@undira.ac.id']);
        $owner = $this->makeViewer(['email' => 'qr-room-owner@undira.ac.id']);
        $place = $this->place('QR Room');
        $borrowing = RoomBorrowing::query()->create([
            'room_id' => $place['room']->id,
            'campus_id' => $place['campus']->id,
            'borrower_name' => 'Peminjam QR',
            'borrower_email' => 'peminjam.qr@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrower_affiliation' => 'PPPA',
            'purpose' => 'Rapat',
            'starts_at' => '2026-10-17 09:00:00',
            'ends_at' => '2026-10-17 15:00:00',
            'status' => FacilityRequestStatus::Disetujui,
            'approval_number' => 'RNG-QR-ROOM-001',
            'requested_by' => $owner->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $html = $this->actingAs($admin)
            ->get(route('room-borrowings.receipt', $borrowing))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('PEMINJAM', $html);
        $this->assertStringContainsString('BIRO PPPA', $html);
        $this->assertStringContainsString('Azwar Syifa Mufiqi', $html);
        $this->assertStringContainsString('Disetujui oleh:', $html);
        $this->assertStringContainsString('sign-qr', $html);
        $this->assertStringNotContainsString('sign-rule', $html);
        $this->assertStringNotContainsString('(Biro PPPA)', $html);
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'class="sign-qr"'));
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'data:image/svg+xml;base64,'));

        $other = $this->makeAdmin(['name' => 'Bukan Approver', 'email' => 'qr-room-other@undira.ac.id']);
        $asOther = $this->actingAs($other)
            ->get(route('room-borrowings.receipt', $borrowing))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Azwar Syifa Mufiqi', $asOther);
        $this->assertStringNotContainsString('Bukan Approver', $asOther);
    }

    public function test_signed_verification_pages_are_read_only_and_reject_tampered_urls(): void
    {
        $admin = $this->makeAdmin(['name' => 'Admin QR', 'email' => 'qr-verify-admin@undira.ac.id']);
        $owner = $this->makeViewer(['email' => 'qr-verify-owner@undira.ac.id']);
        $place = $this->place('QR Verify');
        $borrowing = VehicleBorrowing::query()->create([
            'campus_id' => $place['campus']->id,
            'borrower_name' => 'Pemohon Kendaraan',
            'borrower_email' => 'kendaraan.qr@undira.ac.id',
            'borrower_phone' => '081298765432',
            'unit' => 'Biro Umum',
            'purpose' => 'Dinas',
            'service_type' => VehicleServiceType::DropOff,
            'origin' => 'Kampus',
            'destination' => 'Kantor',
            'passenger_count' => 2,
            'starts_at' => '2026-10-18 08:00:00',
            'ends_at' => '2026-10-18 12:00:00',
            'status' => FacilityRequestStatus::Disetujui,
            'approval_number' => 'KND-QR-001',
            'requested_by' => $owner->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $service = app(ReceiptVerificationService::class);
        $borrowerUrl = $service->signedUrl(
            ReceiptVerificationService::KIND_KENDARAAN,
            $borrowing->id,
            ReceiptVerificationService::PURPOSE_BORROWER
        );
        $approvalUrl = $service->signedUrl(
            ReceiptVerificationService::KIND_KENDARAAN,
            $borrowing->id,
            ReceiptVerificationService::PURPOSE_APPROVAL
        );

        auth()->logout();

        $borrowerPage = $this->get($borrowerUrl)->assertOk()->getContent();
        $this->assertStringContainsString('Verifikasi Catatan Peminjaman', $borrowerPage);
        $this->assertStringContainsString('Pemohon Kendaraan', $borrowerPage);
        $this->assertStringContainsString('KND-QR-001', $borrowerPage);
        $this->assertStringNotContainsString('kendaraan.qr@undira.ac.id', $borrowerPage);
        $this->assertStringNotContainsString('081298765432', $borrowerPage);

        $approvalPage = $this->get($approvalUrl)->assertOk()->getContent();
        $this->assertStringContainsString('Verifikasi Keputusan Biro PPPA', $approvalPage);
        $this->assertStringContainsString('Admin QR', $approvalPage);
        $this->assertStringContainsString('Disetujui oleh', $approvalPage);

        $tampered = preg_replace('#/kendaraan/'.$borrowing->id.'/#', '/kendaraan/999999/', $approvalUrl) ?? $approvalUrl;
        $this->get($tampered)->assertForbidden();

        $unsigned = URL::route('receipt.verify', [
            'kind' => 'kendaraan',
            'id' => $borrowing->id,
            'purpose' => 'approval',
        ]);
        $this->assertStringNotContainsString('signature=', $unsigned);
        $this->get($unsigned)->assertForbidden();
    }

    /**
     * @return array{campus: Campus, room: Room}
     */
    private function place(string $name): array
    {
        $suffix = substr(md5($name.uniqid('', true)), 0, 8);
        $campus = Campus::query()->create([
            'code' => 'C'.$suffix,
            'name' => 'Kampus '.$name,
            'slug' => 'kampus-'.$suffix,
            'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'code' => 'B'.$suffix,
            'name' => 'Gedung '.$name,
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
            'name' => 'Ruang '.$name,
            'room_type' => 'Kelas',
            'capacity' => 30,
            'is_active' => true,
        ]);

        return ['campus' => $campus, 'room' => $room];
    }
}
