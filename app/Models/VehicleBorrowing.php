<?php

namespace App\Models;

use App\Enums\FacilityRequestStatus;
use App\Enums\VehicleServiceType;
use App\Models\Concerns\TracksApprovalCancellation;
use App\Models\Concerns\TracksTrashMeta;
use App\Support\ApprovalCancellationWindow;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleBorrowing extends Model
{
    use SoftDeletes;
    use TracksApprovalCancellation;
    use TracksTrashMeta;

    protected $fillable = [
        'campus_id',
        'route_id',
        'borrower_name',
        'borrower_email',
        'borrower_phone',
        'unit',
        'purpose',
        'passenger_count',
        'passengers',
        'service_type',
        'origin',
        'destination',
        'destination_maps_url',
        'starts_at',
        'ends_at',
        'status',
        'assigned_vehicle_id',
        'assigned_driver_id',
        'assigned_service',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'approval_number',
        'supporting_document_path',
        'supporting_document_original_name',
        'supporting_document_size',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'supporting_document_size' => 'integer',
            'passenger_count' => 'integer',
            'passengers' => 'array',
            'status' => FacilityRequestStatus::class,
            'service_type' => VehicleServiceType::class,
            'assigned_service' => VehicleServiceType::class,
        ];
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(OperationalRoute::class, 'route_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(OperationalVehicle::class, 'assigned_vehicle_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(OperationalDriver::class, 'assigned_driver_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function displayLabel(): string
    {
        if ($this->status === FacilityRequestStatus::Disetujui && $this->hasEnded()) {
            return 'Selesai';
        }

        return $this->status?->label() ?? '—';
    }

    public function displayColor(): string
    {
        if ($this->status === FacilityRequestStatus::Disetujui && $this->hasEnded()) {
            return 'secondary';
        }

        return $this->status?->badgeColor() ?? 'secondary';
    }

    public function hasEnded(?CarbonInterface $moment = null): bool
    {
        $moment ??= now();

        return $this->ends_at !== null && $this->ends_at->lt($moment);
    }

    public function hasStarted(?CarbonInterface $moment = null): bool
    {
        $moment ??= now();

        return $this->starts_at !== null && $this->starts_at->lte($moment);
    }

    public function canCancelApproval(?CarbonInterface $moment = null): bool
    {
        return $this->status === FacilityRequestStatus::Disetujui
            && $this->cancelled_at === null
            && $this->isWithinApprovalCancelWindow($moment)
            && ! $this->hasStarted($moment);
    }

    public function approvalCancelBlockReason(?CarbonInterface $moment = null): ?string
    {
        if ($this->status === FacilityRequestStatus::PersetujuanDibatalkan || $this->cancelled_at !== null) {
            return 'Persetujuan sudah dibatalkan.';
        }

        if ($this->status !== FacilityRequestStatus::Disetujui) {
            return 'Hanya peminjaman yang berstatus Disetujui yang dapat dibatalkan.';
        }

        if ($this->hasStarted($moment)) {
            return 'Perjalanan atau jadwal kendaraan sudah dimulai; pembatalan tidak diizinkan.';
        }

        if (! $this->isWithinApprovalCancelWindow($moment)) {
            return ApprovalCancellationWindow::remainingLabel($this->approved_at, $moment);
        }

        return null;
    }

    public function scheduleText(): string
    {
        $start = $this->starts_at?->timezone((string) config('app.timezone'))->format('d/m/Y H:i');
        $end = $this->ends_at?->timezone((string) config('app.timezone'))->format('d/m/Y H:i');

        return trim(($start ?: '—').' – '.($end ?: '—'));
    }

    public function tripText(): string
    {
        $route = trim((string) ($this->origin ?: $this->route?->name));
        $destination = trim((string) $this->destination);

        if ($route !== '' && $destination !== '') {
            return $route.' → '.$destination;
        }

        return $destination !== '' ? $destination : ($route !== '' ? $route : '—');
    }

    /**
     * Stored Google Maps destination link, or null when absent.
     */
    public function destinationMapsUrl(): ?string
    {
        $url = trim((string) ($this->destination_maps_url ?? ''));

        return $url !== '' ? $url : null;
    }

    /**
     * @return list<array{name: string, unit: string, title: string}>
     */
    public function passengerRows(): array
    {
        $rows = is_array($this->passengers) ? $this->passengers : [];
        $clean = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim(strip_tags((string) ($row['name'] ?? '')));
            if ($name === '') {
                continue;
            }
            $clean[] = [
                'name' => $name,
                'unit' => trim(strip_tags((string) ($row['unit'] ?? ''))),
                'title' => trim(strip_tags((string) ($row['title'] ?? ''))),
            ];
        }

        return $clean;
    }
}
