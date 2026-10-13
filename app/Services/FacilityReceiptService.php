<?php

namespace App\Services;

use App\Enums\VehicleServiceType;
use App\Models\RoomBorrowing;
use App\Models\VehicleBorrowing;
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class FacilityReceiptService
{
    /**
     * @return array<string, mixed>
     */
    public function room(RoomBorrowing $borrowing): array
    {
        $borrowing->loadMissing([
            'room.floor.building.campus',
            'campus',
            'approvedBy',
        ]);

        $room = $borrowing->room;
        $floor = $room?->floor;
        $building = $floor?->building;
        $campus = $borrowing->campus?->name ?: ($building?->campus?->name ?: '—');
        $start = $this->moment($borrowing->starts_at);
        $end = $this->moment($borrowing->ends_at);

        $rows = [
            ['Nama Peminjam', $borrowing->borrower_name],
            ['Email', $borrowing->borrower_email ?: '—'],
            ['Telepon', $borrowing->borrower_phone ?: '—'],
            ['Kampus', $campus],
            ['Gedung', $building?->name ?: '—'],
            ['Lantai', $floor?->name ?: '—'],
            ['Ruangan', $room?->name ?: '—'],
            ['Jenis Kegiatan', $borrowing->activity_type?->label() ?: '—'],
            ['Keperluan', $borrowing->purpose ?: '—'],
            ['Tanggal & Jam Mulai', $start],
            ['Tanggal & Jam Selesai', $end],
            ['Disetujui Oleh', $borrowing->approvedBy?->name ?: '—'],
            ['Tanggal Persetujuan', $borrowing->approved_at ? $this->moment($borrowing->approved_at) : '—'],
        ];
        if (filled($borrowing->rejection_reason)) {
            $rows[] = ['Alasan Penolakan', $borrowing->rejection_reason];
        }

        return $this->envelope(
            $borrowing->borrower_name,
            $borrowing->displayLabel(),
            $borrowing->displayColor(),
            (string) $borrowing->approval_number,
            $rows,
            'Rincian Ruangan',
            ['No', 'Kampus', 'Gedung', 'Lantai', 'Ruangan', 'Tanggal & Jam Mulai', 'Tanggal & Jam Selesai', 'Status'],
            [[
                '1',
                $campus,
                $building?->name ?: '—',
                $floor?->name ?: '—',
                $room?->name ?: '—',
                $start,
                $end,
                $borrowing->displayLabel(),
            ]],
            route('room-borrowings.approval', $borrowing),
            route('room-borrowings.receipt', $borrowing),
            route('room-borrowings.receipt.pdf', $borrowing),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function vehicle(VehicleBorrowing $borrowing): array
    {
        $borrowing->loadMissing(['campus', 'route', 'vehicle', 'driver', 'approvedBy']);

        $vehicle = 'Belum ditetapkan';
        $driver = 'Belum ditetapkan';
        if ($borrowing->assigned_service === VehicleServiceType::GoCar) {
            $vehicle = 'GoCar';
            $driver = 'Tidak menggunakan driver internal';
        } elseif ($borrowing->vehicle || $borrowing->driver) {
            $vehicle = $borrowing->vehicle?->label() ?: 'Belum ditetapkan';
            $driver = $borrowing->driver?->label() ?: 'Belum ditetapkan';
        }

        $start = $this->moment($borrowing->starts_at);
        $end = $this->moment($borrowing->ends_at);
        $rows = [
            ['Nama Peminjam', $borrowing->borrower_name],
            ['Email', $borrowing->borrower_email ?: '—'],
            ['Telepon', $borrowing->borrower_phone ?: '—'],
            ['Unit', $borrowing->unit ?: '—'],
            ['Kampus', $borrowing->campus?->name ?: '—'],
            ['Asal', $borrowing->origin ?: ($borrowing->route?->name ?: '—')],
            ['Tujuan', $borrowing->destination ?: '—'],
            ['Keperluan', $borrowing->purpose ?: '—'],
            ['Jenis Layanan', $borrowing->service_type?->label() ?: '—'],
            ['Tanggal & Jam Mulai', $start],
            ['Tanggal & Jam Selesai', $end],
            ['Kendaraan', $vehicle],
            ['Driver', $driver],
            ['Disetujui Oleh', $borrowing->approvedBy?->name ?: '—'],
            ['Tanggal Persetujuan', $borrowing->approved_at ? $this->moment($borrowing->approved_at) : '—'],
        ];
        if (filled($borrowing->rejection_reason)) {
            $rows[] = ['Alasan Penolakan', $borrowing->rejection_reason];
        }

        $passengers = collect($borrowing->passengerRows())
            ->map(fn (array $row) => $row['name'])
            ->implode(', ');
        if ($passengers !== '') {
            $rows[] = ['Penumpang', $passengers];
        }

        return $this->envelope(
            $borrowing->borrower_name,
            $borrowing->displayLabel(),
            $borrowing->displayColor(),
            (string) $borrowing->approval_number,
            $rows,
            'Rincian Perjalanan',
            ['No', 'Jenis Layanan', 'Tujuan', 'Kendaraan', 'Driver', 'Tanggal & Jam Mulai', 'Tanggal & Jam Selesai', 'Status'],
            [[
                '1',
                $borrowing->service_type?->label() ?: '—',
                $borrowing->destination ?: '—',
                $vehicle,
                $driver,
                $start,
                $end,
                $borrowing->displayLabel(),
            ]],
            route('vehicle-borrowings.approval', $borrowing),
            route('vehicle-borrowings.receipt', $borrowing),
            route('vehicle-borrowings.receipt.pdf', $borrowing),
        );
    }

    /**
     * @param  list<array{0: string, 1: string}>  $rows
     * @param  list<string>  $columns
     * @param  list<list<string>>  $lines
     * @return array<string, mixed>
     */
    private function envelope(
        string $borrowerName,
        string $statusLabel,
        string $statusColor,
        string $number,
        array $rows,
        string $tableTitle,
        array $columns,
        array $lines,
        string $detailUrl,
        string $receiptUrl,
        string $pdfUrl,
    ): array {
        return [
            'borrowerName' => $borrowerName,
            'statusLabel' => $statusLabel,
            'statusColor' => $statusColor,
            'number' => $number,
            'rows' => $rows,
            'tableTitle' => $tableTitle,
            'columns' => $columns,
            'lines' => $lines,
            'logoDataUri' => $this->logoDataUri(),
            'qrDataUri' => $this->qrDataUri($detailUrl),
            'detailUrl' => $detailUrl,
            'receiptUrl' => $receiptUrl,
            'pdfUrl' => $pdfUrl,
            'generatedAt' => now()->timezone((string) config('app.timezone'))->format('d/m/Y H:i'),
        ];
    }

    private function moment(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        return $value->timezone((string) config('app.timezone'))->format('d/m/Y H:i');
    }

    private function logoDataUri(): ?string
    {
        $logoPath = public_path('images/logo-undira.png');
        if (! is_file($logoPath)) {
            $logoPath = public_path('images/undira.png');
        }

        return is_file($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;
    }

    private function qrDataUri(string $url): ?string
    {
        $absolute = URL::to($url);
        $svg = (string) QrCode::format('svg')->size(180)->margin(1)->generate($absolute);

        return $svg === '' ? null : 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
