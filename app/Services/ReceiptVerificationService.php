<?php

namespace App\Services;

use App\Enums\BorrowingItemStatus;
use App\Enums\BorrowingStatus;
use App\Enums\FacilityRequestStatus;
use App\Enums\VehicleServiceType;
use App\Models\Borrowing;
use App\Models\RoomBorrowing;
use App\Models\VehicleBorrowing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReceiptVerificationService
{
    public const KIND_INVENTARIS = 'inventaris';

    public const KIND_RUANGAN = 'ruangan';

    public const KIND_KENDARAAN = 'kendaraan';

    public const PURPOSE_BORROWER = 'borrower';

    public const PURPOSE_APPROVAL = 'approval';

    /**
     * @return list<string>
     */
    public static function kinds(): array
    {
        return [self::KIND_INVENTARIS, self::KIND_RUANGAN, self::KIND_KENDARAAN];
    }

    /**
     * @return list<string>
     */
    public static function purposes(): array
    {
        return [self::PURPOSE_BORROWER, self::PURPOSE_APPROVAL];
    }

    public function signedUrl(string $kind, int|string $id, string $purpose): string
    {
        return URL::signedRoute('receipt.verify', [
            'kind' => $kind,
            'id' => $id,
            'purpose' => $purpose,
        ]);
    }

    public function qrDataUri(string $url, int $size = 140): ?string
    {
        $svg = (string) QrCode::format('svg')->size($size)->margin(1)->generate($url);

        return $svg === '' ? null : 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Payload for the dual-QR signature block on receipts.
     *
     * @return array{
     *     borrowerName: string,
     *     approverName: string|null,
     *     decisionLabel: string,
     *     borrowerQrDataUri: string|null,
     *     approvalQrDataUri: string|null,
     *     borrowerVerifyUrl: string|null,
     *     approvalVerifyUrl: string|null
     * }
     */
    public function signatureBlock(string $kind, Model $model): array
    {
        $id = $model->getKey();
        $borrowerUrl = $this->signedUrl($kind, $id, self::PURPOSE_BORROWER);
        $approvalUrl = $this->signedUrl($kind, $id, self::PURPOSE_APPROVAL);

        $borrowerName = (string) ($model->getAttribute('borrower_name') ?: '—');
        $approverName = null;
        if (method_exists($model, 'approvedBy')) {
            $model->loadMissing('approvedBy');
            $approverName = $model->approvedBy?->name;
        }

        return [
            'borrowerName' => $borrowerName,
            'approverName' => filled($approverName) ? (string) $approverName : null,
            'decisionLabel' => $this->decisionLabel($model),
            'borrowerQrDataUri' => $this->qrDataUri($borrowerUrl, 140),
            'approvalQrDataUri' => $this->qrDataUri($approvalUrl, 140),
            'borrowerVerifyUrl' => $borrowerUrl,
            'approvalVerifyUrl' => $approvalUrl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyPayload(string $kind, int|string $id, string $purpose): array
    {
        $model = $this->resolve($kind, $id);

        return match ($kind) {
            self::KIND_INVENTARIS => $this->inventarisPayload($model, $purpose),
            self::KIND_RUANGAN => $this->ruanganPayload($model, $purpose),
            self::KIND_KENDARAAN => $this->kendaraanPayload($model, $purpose),
            default => throw new NotFoundHttpException,
        };
    }

    protected function resolve(string $kind, int|string $id): Model
    {
        $model = match ($kind) {
            self::KIND_INVENTARIS => Borrowing::query()->find($id),
            self::KIND_RUANGAN => RoomBorrowing::query()->find($id),
            self::KIND_KENDARAAN => VehicleBorrowing::query()->find($id),
            default => null,
        };

        if ($model === null) {
            throw new NotFoundHttpException;
        }

        return $model;
    }

    /**
     * @return array<string, mixed>
     */
    protected function inventarisPayload(Borrowing $borrowing, string $purpose): array
    {
        $borrowing->loadMissing([
            'items.inventoryItem:id,name,inventory_number',
            'approvedBy:id,name',
        ]);

        $tz = (string) config('app.timezone');
        $lines = $borrowing->items->map(function ($line) {
            return [
                'number' => $line->inventoryItem?->inventory_number ?: '—',
                'name' => $line->inventoryItem?->name ?: '—',
                'status' => $line->status instanceof BorrowingItemStatus
                    ? $line->status->label()
                    : (string) $line->status,
            ];
        })->values()->all();

        $counts = [
            'disetujui' => $borrowing->items->where('status', BorrowingItemStatus::Disetujui)->count()
                + $borrowing->items->where('status', BorrowingItemStatus::Dipinjam)->count()
                + $borrowing->items->where('status', BorrowingItemStatus::Dikembalikan)->count(),
            'ditolak' => $borrowing->items->where('status', BorrowingItemStatus::Ditolak)->count(),
            'menunggu' => $borrowing->items->where('status', BorrowingItemStatus::MenungguPersetujuan)->count(),
        ];

        return [
            'purpose' => $purpose,
            'kind' => self::KIND_INVENTARIS,
            'kind_label' => 'Peminjaman Inventaris',
            'title' => $purpose === self::PURPOSE_APPROVAL
                ? 'Verifikasi Keputusan Biro PPPA'
                : 'Verifikasi Catatan Peminjaman',
            'approval_number' => $borrowing->approval_number ?: '—',
            'request_number' => 'PIN-'.str_pad((string) $borrowing->id, 6, '0', STR_PAD_LEFT),
            'borrower_name' => $borrowing->borrower_name ?: '—',
            'unit' => filled($borrowing->borrower_affiliation) ? $borrowing->borrower_affiliation : '—',
            'status' => $borrowing->status instanceof BorrowingStatus
                ? $borrowing->status->label()
                : (string) $borrowing->status,
            'schedule' => collect([
                $borrowing->borrow_date?->timezone($tz)->format('d/m/Y H:i'),
                $borrowing->expected_return_date?->timezone($tz)->format('d/m/Y H:i'),
            ])->filter()->implode(' – ') ?: '—',
            'approver_name' => $borrowing->approvedBy?->name,
            'approved_at' => $borrowing->approved_at?->timezone($tz)->format('d/m/Y H:i'),
            'decision_label' => $this->decisionLabel($borrowing),
            'item_counts' => $counts,
            'items' => $lines,
            'detail' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function ruanganPayload(RoomBorrowing $borrowing, string $purpose): array
    {
        $borrowing->loadMissing([
            'room:id,name,code,floor_id',
            'room.floor:id,name,building_id',
            'room.floor.building:id,name,campus_id',
            'campus:id,name',
            'approvedBy:id,name',
        ]);

        $tz = (string) config('app.timezone');
        $room = $borrowing->room;
        $location = collect([
            $borrowing->campus?->name ?: $room?->floor?->building?->campus?->name,
            $room?->floor?->building?->name,
            $room?->floor?->name,
            $room ? trim(($room->code ? $room->code.' · ' : '').$room->name) : null,
        ])->filter()->implode(' · ') ?: '—';

        return [
            'purpose' => $purpose,
            'kind' => self::KIND_RUANGAN,
            'kind_label' => 'Peminjaman Ruangan',
            'title' => $purpose === self::PURPOSE_APPROVAL
                ? 'Verifikasi Keputusan Biro PPPA'
                : 'Verifikasi Catatan Peminjaman',
            'approval_number' => $borrowing->approval_number ?: '—',
            'request_number' => 'PRU-'.str_pad((string) $borrowing->id, 6, '0', STR_PAD_LEFT),
            'borrower_name' => $borrowing->borrower_name ?: '—',
            'unit' => filled($borrowing->borrower_affiliation) ? $borrowing->borrower_affiliation : '—',
            'status' => $borrowing->displayLabel(),
            'schedule' => collect([
                $borrowing->starts_at?->timezone($tz)->format('d/m/Y H:i'),
                $borrowing->ends_at?->timezone($tz)->format('d/m/Y H:i'),
            ])->filter()->implode(' – ') ?: '—',
            'approver_name' => $borrowing->approvedBy?->name,
            'approved_at' => $borrowing->approved_at?->timezone($tz)->format('d/m/Y H:i'),
            'decision_label' => $this->decisionLabel($borrowing),
            'item_counts' => null,
            'items' => [],
            'detail' => [
                'Lokasi' => $location,
                'Jenis Kegiatan' => $borrowing->activityTypeLabel(),
                'Keperluan' => $borrowing->purpose ?: '—',
                'Peserta' => $borrowing->totalParticipantCount().' orang',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function kendaraanPayload(VehicleBorrowing $borrowing, string $purpose): array
    {
        $borrowing->loadMissing(['campus:id,name', 'vehicle:id,name,plate_number', 'driver:id,name', 'approvedBy:id,name', 'route:id,name']);

        $tz = (string) config('app.timezone');
        $vehicle = 'Belum ditetapkan';
        $driver = 'Belum ditetapkan';
        if ($borrowing->assigned_service === VehicleServiceType::GoCar) {
            $vehicle = 'GoCar';
            $driver = 'Tidak menggunakan driver internal';
        } elseif ($borrowing->vehicle || $borrowing->driver) {
            $vehicle = $borrowing->vehicle?->label() ?: 'Belum ditetapkan';
            $driver = $borrowing->driver?->name ?: 'Belum ditetapkan';
        }

        return [
            'purpose' => $purpose,
            'kind' => self::KIND_KENDARAAN,
            'kind_label' => 'Peminjaman Kendaraan Operasional',
            'title' => $purpose === self::PURPOSE_APPROVAL
                ? 'Verifikasi Keputusan Biro PPPA'
                : 'Verifikasi Catatan Peminjaman',
            'approval_number' => $borrowing->approval_number ?: '—',
            'request_number' => 'PKD-'.str_pad((string) $borrowing->id, 6, '0', STR_PAD_LEFT),
            'borrower_name' => $borrowing->borrower_name ?: '—',
            'unit' => filled($borrowing->unit) ? $borrowing->unit : '—',
            'status' => $borrowing->displayLabel(),
            'schedule' => collect([
                $borrowing->starts_at?->timezone($tz)->format('d/m/Y H:i'),
                $borrowing->ends_at?->timezone($tz)->format('d/m/Y H:i'),
            ])->filter()->implode(' – ') ?: '—',
            'approver_name' => $borrowing->approvedBy?->name,
            'approved_at' => $borrowing->approved_at?->timezone($tz)->format('d/m/Y H:i'),
            'decision_label' => $this->decisionLabel($borrowing),
            'item_counts' => null,
            'items' => [],
            'detail' => [
                'Kampus' => $borrowing->campus?->name ?: '—',
                'Rute / Tujuan' => $borrowing->tripText(),
                'Jenis Layanan' => $borrowing->service_type?->label() ?: '—',
                'Kendaraan' => $vehicle,
                'Pengemudi' => $driver,
                'Penumpang' => max(0, (int) $borrowing->passenger_count).' orang',
            ],
        ];
    }

    protected function decisionLabel(Model $model): string
    {
        $status = $model->getAttribute('status');

        if ($status === FacilityRequestStatus::Ditolak || $status === BorrowingStatus::Ditolak) {
            return 'Ditolak oleh';
        }

        if ($status === FacilityRequestStatus::Disetujui
            || $status === BorrowingStatus::Disetujui
            || $status === BorrowingStatus::Dipinjam
            || $status === BorrowingStatus::Dikembalikan
            || $status === BorrowingStatus::Terlambat) {
            return 'Disetujui oleh';
        }

        return 'Diputuskan oleh';
    }
}
