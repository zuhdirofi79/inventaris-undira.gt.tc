<?php

namespace Tests\Feature;

use App\Enums\FacilityRequestStatus;
use App\Enums\VehicleServiceType;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Floor;
use App\Models\OperationalDriver;
use App\Models\OperationalVehicle;
use App\Models\Room;
use App\Models\RoomBorrowing;
use App\Models\VehicleBorrowing;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class FacilityBorrowingReportTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_sidebar_nests_three_borrowing_report_links(): void
    {
        $admin = $this->makeAdmin(['email' => 'facility-report-sidebar@undira.ac.id']);

        $html = $this->actingAs($admin)
            ->get(route('reports.show', 'peminjaman-ruangan'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<p>Laporan Peminjaman</p>', $html);
        $this->assertStringContainsString('<p>Laporan Peminjaman Inventaris</p>', $html);
        $this->assertStringContainsString('<p>Laporan Peminjaman Ruangan</p>', $html);
        $this->assertStringContainsString('<p>Laporan Peminjaman Kendaraan</p>', $html);
        $this->assertStringContainsString('menu-open', $html);
        $this->assertStringContainsString('active', $this->linkClass($html, route('reports.show', 'peminjaman-ruangan')));
        $this->assertStringNotContainsString('active', $this->linkClass($html, route('reports.show', 'dipinjam')));
        $this->assertStringNotContainsString('active', $this->linkClass($html, route('reports.show', 'peminjaman-kendaraan')));
    }

    public function test_room_report_preview_filters_by_campus_and_excludes_vehicle_rows(): void
    {
        $admin = $this->makeAdmin(['email' => 'facility-report-room@undira.ac.id']);
        [$campusA, $roomA] = $this->place('Cibubur Rpt');
        [$campusB, $roomB] = $this->place('Tanjung Rpt');

        $roomRow = $this->roomLoan($admin, $campusA, $roomA, 'Peminjam Ruang A', FacilityRequestStatus::Disetujui, 'RNG-TEST-A');
        $this->roomLoan($admin, $campusB, $roomB, 'Peminjam Ruang B', FacilityRequestStatus::Disetujui, 'RNG-TEST-B');
        $this->vehicleLoan($admin, $campusA, 'Peminjam Kendaraan Campur', VehicleServiceType::DropOff, 'KND-TEST-X');

        $json = $this->actingAs($admin)
            ->getJson(route('reports.data', 'peminjaman-ruangan').'?campus_id='.$campusA->id)
            ->assertOk()
            ->json();

        $borrowers = collect($json['data'])->pluck('borrower')->all();
        $this->assertContains('Peminjam Ruang A', $borrowers);
        $this->assertNotContains('Peminjam Ruang B', $borrowers);
        $this->assertNotContains('Peminjam Kendaraan Campur', $borrowers);
        $this->assertSame(1, $json['meta']['total']);
        $this->assertSame('RNG-TEST-A', $json['data'][0]['approval_number']);
        $this->assertSame('PRU-'.str_pad((string) $roomRow->id, 6, '0', STR_PAD_LEFT), $json['data'][0]['request_number']);
    }

    public function test_room_report_exports_print_pdf_excel_csv_from_room_query(): void
    {
        $admin = $this->makeAdmin(['email' => 'facility-report-room-export@undira.ac.id']);
        [$campus, $room] = $this->place('Export Ruang');
        $this->roomLoan($admin, $campus, $room, 'Export Ruang Peminjam', FacilityRequestStatus::Disetujui, 'RNG-EXPORT-1');

        $print = $this->actingAs($admin)
            ->post(route('reports.export', 'peminjaman-ruangan'), [
                'format' => 'print',
                'campus_id' => $campus->id,
            ])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Export Ruang Peminjam', $print);
        $this->assertStringContainsString('RNG-EXPORT-1', $print);
        $this->assertStringContainsString('Laporan Peminjaman Ruangan', $print);
        $this->assertStringContainsString('STATISTIK PEMINJAMAN RUANGAN', $print);
        $this->assertStringContainsString('Total Pengajuan', $print);
        $this->assertStringContainsString('Top Peminjam', $print);
        $this->assertStringNotContainsString('Total Inventaris', $print);
        $this->assertStringNotContainsString('STATISTIK KOMPOSISI INVENTARIS', $print);
        $this->assertStringNotContainsString('REKAP SUB KATEGORI INVENTARIS', $print);

        $this->actingAs($admin)
            ->post(route('reports.export', 'peminjaman-ruangan'), [
                'format' => 'pdf-portrait',
                'campus_id' => $campus->id,
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('reports.export', 'peminjaman-ruangan'), [
                'format' => 'pdf-landscape',
                'campus_id' => $campus->id,
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('reports.export', 'peminjaman-ruangan'), [
                'format' => 'excel',
                'campus_id' => $campus->id,
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('reports.export', 'peminjaman-ruangan'), [
                'format' => 'csv',
                'campus_id' => $campus->id,
            ])
            ->assertOk();
    }

    public function test_vehicle_report_filters_by_service_type_and_campus(): void
    {
        $admin = $this->makeAdmin(['email' => 'facility-report-vehicle@undira.ac.id']);
        [$campusA] = $this->place('Kendaraan A');
        [$campusB] = $this->place('Kendaraan B');

        $vehicle = OperationalVehicle::query()->create([
            'name' => 'Avanza Rpt',
            'plate_number' => 'B 1000 RPT',
            'category' => 'MPV',
            'is_active' => true,
        ]);
        $driver = OperationalDriver::query()->create([
            'name' => 'Driver Rpt',
            'phone' => '081111111111',
            'is_active' => true,
        ]);

        $match = $this->vehicleLoan(
            $admin,
            $campusA,
            'Peminjam Drop Off',
            VehicleServiceType::DropOff,
            'KND-DROP-1',
            $vehicle->id,
            $driver->id
        );
        $this->vehicleLoan($admin, $campusA, 'Peminjam Round Trip', VehicleServiceType::RoundTrip, 'KND-RT-1');
        $this->vehicleLoan($admin, $campusB, 'Peminjam Kampus Lain', VehicleServiceType::DropOff, 'KND-OTHER-1');
        [, $roomA] = $this->place('Bukan Kendaraan Room');
        $this->roomLoan($admin, $campusA, $roomA, 'Bukan Kendaraan', FacilityRequestStatus::Disetujui, 'RNG-NOT-VEH');

        $json = $this->actingAs($admin)
            ->getJson(route('reports.data', 'peminjaman-kendaraan').'?campus_id='.$campusA->id.'&service_type='.VehicleServiceType::DropOff->value)
            ->assertOk()
            ->json();

        $borrowers = collect($json['data'])->pluck('borrower')->all();
        $this->assertContains('Peminjam Drop Off', $borrowers);
        $this->assertNotContains('Peminjam Round Trip', $borrowers);
        $this->assertNotContains('Peminjam Kampus Lain', $borrowers);
        $this->assertNotContains('Bukan Kendaraan', $borrowers);
        $this->assertSame(1, $json['meta']['total']);
        $this->assertSame('Drop-off', $json['data'][0]['service_type']);
        $this->assertStringContainsString('Avanza Rpt', $json['data'][0]['vehicle']);
        $this->assertSame('Driver Rpt', $json['data'][0]['driver']);
        $this->assertSame('PKD-'.str_pad((string) $match->id, 6, '0', STR_PAD_LEFT), $json['data'][0]['request_number']);
    }

    public function test_vehicle_report_exports_and_empty_filter_message(): void
    {
        $admin = $this->makeAdmin(['email' => 'facility-report-vehicle-export@undira.ac.id']);
        [$campus] = $this->place('Kendaraan Export');
        $this->vehicleLoan($admin, $campus, 'Export Kendaraan Peminjam', VehicleServiceType::Pickup, 'KND-EXPORT-1');

        $print = $this->actingAs($admin)
            ->post(route('reports.export', 'peminjaman-kendaraan'), [
                'format' => 'print',
                'campus_id' => $campus->id,
            ])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Export Kendaraan Peminjam', $print);
        $this->assertStringContainsString('Penjemputan', $print);
        $this->assertStringContainsString('STATISTIK PEMINJAMAN KENDARAAN', $print);
        $this->assertStringContainsString('Total Pengajuan', $print);
        $this->assertStringContainsString('Penugasan Pengemudi', $print);
        $this->assertStringNotContainsString('Total Inventaris', $print);
        $this->assertStringNotContainsString('STATISTIK KOMPOSISI INVENTARIS', $print);
        $this->assertStringNotContainsString('Total Ruangan', $print);

        $empty = $this->actingAs($admin)
            ->getJson(route('reports.data', 'peminjaman-kendaraan').'?campus_id='.$campus->id.'&service_type='.VehicleServiceType::GoCar->value)
            ->assertOk()
            ->json();

        $this->assertSame(0, $empty['meta']['total']);
        $this->assertSame([], $empty['data']);

        $emptyPrint = $this->actingAs($admin)
            ->post(route('reports.export', 'peminjaman-kendaraan'), [
                'format' => 'print',
                'campus_id' => $campus->id,
                'service_type' => VehicleServiceType::GoCar->value,
            ])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Tidak ada data untuk filter ini', $emptyPrint);

        $this->actingAs($admin)
            ->post(route('reports.export', 'peminjaman-kendaraan'), [
                'format' => 'excel',
                'campus_id' => $campus->id,
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('reports.export', 'peminjaman-kendaraan'), [
                'format' => 'csv',
                'campus_id' => $campus->id,
            ])
            ->assertOk();
    }

    public function test_inverted_date_range_is_rejected(): void
    {
        $admin = $this->makeAdmin(['email' => 'facility-report-dates@undira.ac.id']);

        $this->actingAs($admin)
            ->getJson(route('reports.data', 'peminjaman-ruangan').'?tanggal_awal=2026-10-20&tanggal_akhir=2026-10-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tanggal_awal']);
    }

    public function test_report_permission_required_for_pages_and_exports(): void
    {
        $viewer = $this->makeViewer(['email' => 'facility-report-viewer@undira.ac.id']);

        $this->actingAs($viewer)
            ->get(route('reports.show', 'peminjaman-ruangan'))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get(route('reports.show', 'peminjaman-kendaraan'))
            ->assertForbidden();

        $viewerOnly = $this->makeViewer(['email' => 'facility-report-view-only@undira.ac.id']);
        $viewerOnly->syncRoles([]);
        $viewerOnly->syncPermissions(['report.view']);

        $this->actingAs($viewerOnly)
            ->get(route('reports.show', 'peminjaman-ruangan'))
            ->assertOk();

        $this->actingAs($viewerOnly)
            ->post(route('reports.export', 'peminjaman-ruangan'), ['format' => 'excel'])
            ->assertForbidden();

        $this->actingAs($viewerOnly)
            ->post(route('reports.export', 'peminjaman-kendaraan'), ['format' => 'pdf-portrait'])
            ->assertForbidden();
    }

    public function test_inventory_borrowed_report_still_loads(): void
    {
        $admin = $this->makeAdmin(['email' => 'facility-report-regression@undira.ac.id']);

        $this->actingAs($admin)
            ->get(route('reports.show', 'dipinjam'))
            ->assertOk()
            ->assertSee('Inventaris Dipinjam')
            ->assertSee('Riwayat dan status peminjaman inventaris');

        $this->actingAs($admin)
            ->getJson(route('reports.data', 'dipinjam'))
            ->assertOk();
    }

    public function test_room_report_ranks_borrower_unit_and_excludes_pending_from_approved(): void
    {
        $admin = $this->makeAdmin(['email' => 'facility-report-room-rank@undira.ac.id']);
        [$campus, $room] = $this->place('Rank Room');

        for ($i = 0; $i < 3; $i++) {
            $this->roomLoan(
                $admin,
                $campus,
                $room,
                'Azwar Rank',
                FacilityRequestStatus::Disetujui,
                'RNG-RANK-A-'.$i,
                'PPPA',
                'rapat',
                10,
                5
            );
        }
        $this->roomLoan(
            $admin,
            $campus,
            $room,
            'Azwar Rank',
            FacilityRequestStatus::Disetujui,
            'RNG-RANK-B-1',
            'Fakultas Teknik',
            'seminar',
            20,
            0
        );
        $this->roomLoan(
            $admin,
            $campus,
            $room,
            'Azwar Rank',
            FacilityRequestStatus::MenungguPersetujuan,
            null,
            'PPPA',
            'rapat',
            99,
            99
        );
        $this->roomLoan(
            $admin,
            $campus,
            $room,
            'User Ditolak',
            FacilityRequestStatus::Ditolak,
            null,
            'UKM',
            'lainnya',
            1,
            0
        );

        $print = $this->actingAs($admin)
            ->post(route('reports.export', 'peminjaman-ruangan'), [
                'format' => 'print',
                'campus_id' => $campus->id,
            ])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Azwar Rank', $print);
        $this->assertStringContainsString('PPPA', $print);
        $this->assertStringContainsString('Fakultas Teknik', $print);
        $this->assertStringContainsString('3 kali', $print);
        $this->assertStringContainsString('Berdasarkan Jenis Kegiatan', $print);
        $this->assertStringContainsString('Ringkasan per Kampus', $print);
        $this->assertMatchesRegularExpression('/Tingkat Persetujuan[\s\S]*?80(?:\.0)?%/', $print);
        $this->assertStringNotContainsString('Total Inventaris', $print);
    }

    public function test_vehicle_report_ranks_assigned_vehicles_and_drivers_only(): void
    {
        $admin = $this->makeAdmin(['email' => 'facility-report-vehicle-rank@undira.ac.id']);
        [$campus] = $this->place('Rank Vehicle');

        $used = OperationalVehicle::query()->create([
            'name' => 'Mobil Digunakan',
            'plate_number' => 'B 2222 RPT',
            'category' => 'SUV',
            'is_active' => true,
        ]);
        OperationalVehicle::query()->create([
            'name' => 'Mobil Tidak Pernah',
            'plate_number' => 'B 3333 RPT',
            'category' => 'MPV',
            'is_active' => true,
        ]);
        $driver = OperationalDriver::query()->create([
            'name' => 'Driver Ranking',
            'phone' => '081222222222',
            'is_active' => true,
        ]);

        $this->vehicleLoan(
            $admin,
            $campus,
            'Peminjam Rank Veh',
            VehicleServiceType::DropOff,
            'KND-RANK-1',
            $used->id,
            $driver->id,
            'PPPA',
            4
        );
        $this->vehicleLoan(
            $admin,
            $campus,
            'Peminjam Rank Veh',
            VehicleServiceType::RoundTrip,
            'KND-RANK-2',
            $used->id,
            $driver->id,
            'PPPA',
            6
        );
        $this->vehicleLoan($admin, $campus, 'Pending Veh', VehicleServiceType::Pickup, null, null, null, 'Biro', 2);

        $print = $this->actingAs($admin)
            ->post(route('reports.export', 'peminjaman-kendaraan'), [
                'format' => 'print',
                'campus_id' => $campus->id,
            ])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Mobil Digunakan', $print);
        $this->assertStringContainsString('Driver Ranking', $print);
        $this->assertStringContainsString('PPPA', $print);
        $this->assertStringContainsString('2 kali', $print);
        $this->assertStringNotContainsString('Mobil Tidak Pernah', $print);
        $this->assertStringNotContainsString('Total Inventaris', $print);
        $this->assertStringNotContainsString('Komposisi Berdasarkan Kategori', $print);
    }

    public function test_room_report_empty_filter_shows_zero_stats_without_inventory(): void
    {
        $admin = $this->makeAdmin(['email' => 'facility-report-room-empty@undira.ac.id']);
        [$campus] = $this->place('Empty Room Stats');

        $print = $this->actingAs($admin)
            ->post(route('reports.export', 'peminjaman-ruangan'), [
                'format' => 'print',
                'campus_id' => $campus->id,
                'tanggal_awal' => '2099-01-01',
                'tanggal_akhir' => '2099-01-31',
            ])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Tidak ada data untuk filter ini', $print);
        $this->assertStringContainsString('STATISTIK PEMINJAMAN RUANGAN', $print);
        $this->assertStringNotContainsString('Total Inventaris', $print);
    }

    /**
     * @return array{0: Campus, 1: Room}
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

        return [$campus, $room];
    }

    private function roomLoan(
        $owner,
        Campus $campus,
        Room $room,
        string $borrower,
        FacilityRequestStatus $status,
        ?string $approvalNumber = null,
        ?string $unit = null,
        ?string $activityType = null,
        int $internal = 0,
        int $external = 0,
    ): RoomBorrowing {
        return RoomBorrowing::query()->create([
            'room_id' => $room->id,
            'campus_id' => $campus->id,
            'borrower_name' => $borrower,
            'borrower_email' => 'room@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrower_affiliation' => $unit,
            'activity_type' => $activityType,
            'purpose' => 'Keperluan laporan',
            'participant_internal' => $internal > 0,
            'participant_internal_count' => $internal,
            'participant_external' => $external > 0,
            'participant_external_count' => $external,
            'starts_at' => '2026-10-15 09:00:00',
            'ends_at' => '2026-10-15 11:00:00',
            'status' => $status,
            'approval_number' => $approvalNumber,
            'requested_by' => $owner->id,
            'approved_by' => $status === FacilityRequestStatus::Disetujui ? $owner->id : null,
            'approved_at' => $status === FacilityRequestStatus::Disetujui ? now() : null,
        ]);
    }

    private function vehicleLoan(
        $owner,
        Campus $campus,
        string $borrower,
        VehicleServiceType $service,
        ?string $approvalNumber = null,
        ?int $vehicleId = null,
        ?int $driverId = null,
        string $unit = 'Biro Umum',
        int $passengers = 0,
    ): VehicleBorrowing {
        return VehicleBorrowing::query()->create([
            'campus_id' => $campus->id,
            'borrower_name' => $borrower,
            'borrower_email' => 'vehicle@undira.ac.id',
            'borrower_phone' => '081298765432',
            'unit' => $unit,
            'purpose' => 'Perjalanan dinas',
            'service_type' => $service,
            'origin' => 'Kampus',
            'destination' => 'Tujuan '.$borrower,
            'passenger_count' => $passengers,
            'starts_at' => '2026-10-16 08:00:00',
            'ends_at' => '2026-10-16 12:00:00',
            'status' => $approvalNumber ? FacilityRequestStatus::Disetujui : FacilityRequestStatus::MenungguPersetujuan,
            'approval_number' => $approvalNumber,
            'assigned_vehicle_id' => $vehicleId,
            'assigned_driver_id' => $driverId,
            'requested_by' => $owner->id,
            'approved_by' => $approvalNumber ? $owner->id : null,
            'approved_at' => $approvalNumber ? now() : null,
        ]);
    }

    private function linkClass(string $html, string $url): string
    {
        $pattern = '/<a href="'.preg_quote($url, '/').'" class="([^"]*)"/';
        $this->assertSame(1, preg_match($pattern, $html, $matches), 'Missing sidebar link for '.$url);

        return $matches[1];
    }
}
