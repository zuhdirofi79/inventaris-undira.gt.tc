<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\FacilityRequestStatus;
use App\Enums\VehicleServiceType;
use App\Mail\VehicleBorrowingCancellationMail;
use App\Mail\VehicleBorrowingDecisionMail;
use App\Mail\VehicleDriverAssignmentMail;
use App\Models\Campus;
use App\Models\OperationalDriver;
use App\Models\OperationalRoute;
use App\Models\OperationalVehicle;
use App\Models\User;
use App\Models\VehicleBorrowing;
use App\Support\ScheduleOverlap;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class VehicleBorrowingService
{
    public const DISK = 'local';

    public function __construct(
        private readonly DecisionMailer $mailer,
        private readonly AuditLogService $audit,
        private readonly NotificationService $notifications,
        private readonly OperationalDriverDirectory $driverDirectory,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(User $user, array $data, ?UploadedFile $file = null): VehicleBorrowing
    {
        $campus = Campus::query()->find((int) $data['campus_id']);
        if (! $campus || ! $campus->is_active) {
            throw ValidationException::withMessages([
                'campus_id' => 'Lokasi kampus tidak tersedia.',
            ]);
        }

        $route = OperationalRoute::query()->whereKey((int) $data['route_id'])->where('is_active', true)->first();
        if (! $route) {
            throw ValidationException::withMessages([
                'route_id' => 'Rute tidak tersedia.',
            ]);
        }

        $start = Carbon::createFromFormat('Y-m-d\TH:i', (string) $data['starts_at']);
        $end = Carbon::createFromFormat('Y-m-d\TH:i', (string) $data['ends_at']);
        $storedPath = null;

        try {
            $saved = DB::transaction(function () use ($user, $data, $file, $campus, $route, $start, $end, &$storedPath) {
                $row = VehicleBorrowing::query()->create([
                    'campus_id' => $campus->id,
                    'route_id' => $route->id,
                    'borrower_name' => $data['borrower_name'],
                    'borrower_email' => $data['borrower_email'],
                    'borrower_phone' => $data['borrower_phone'],
                    'unit' => filled($data['unit'] ?? null)
                        ? mb_substr(trim(strip_tags((string) $data['unit'])), 0, 255)
                        : null,
                    'purpose' => trim(strip_tags((string) $data['purpose'])),
                    'passenger_count' => (int) $data['passenger_count'],
                    'passengers' => $this->passengers($data['passengers'] ?? []),
                    'service_type' => $data['service_type'],
                    'origin' => $route->name,
                    'destination' => trim(strip_tags((string) ($data['destination'] ?? ''))),
                    'destination_maps_url' => $this->normalizeMapsUrl($data['destination_maps_url'] ?? null),
                    'starts_at' => $start,
                    'ends_at' => $end,
                    'status' => FacilityRequestStatus::MenungguPersetujuan,
                    'requested_by' => $user->id,
                ]);

                if ($file instanceof UploadedFile) {
                    $meta = $this->storeFile($row, $file);
                    $storedPath = $meta['supporting_document_path'];
                    $row->update($meta);
                }

                return $row->fresh(['campus', 'route']);
            });
        } catch (Throwable $e) {
            if (is_string($storedPath) && $storedPath !== '') {
                Storage::disk(self::DISK)->delete($storedPath);
            }

            if ($file instanceof UploadedFile && ! $e instanceof ValidationException) {
                throw ValidationException::withMessages([
                    'supporting_document' => 'Dokumen tidak dapat disimpan saat ini. Silakan coba kembali.',
                ]);
            }

            throw $e;
        }

        $this->notifications->notifyVehicleBorrowingSubmitted($saved);

        return $saved;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function decide(User $admin, VehicleBorrowing $borrowing, array $data): VehicleBorrowing
    {
        $saved = DB::transaction(function () use ($admin, $borrowing, $data) {
            $fresh = VehicleBorrowing::query()->whereKey($borrowing->id)->lockForUpdate()->first();
            if (! $fresh || $fresh->status !== FacilityRequestStatus::MenungguPersetujuan) {
                throw ValidationException::withMessages([
                    'decision' => 'Pengajuan sudah diproses.',
                ]);
            }

            if ($data['decision'] === 'reject') {
                $fresh->forceFill([
                    'status' => FacilityRequestStatus::Ditolak,
                    'approved_by' => $admin->id,
                    'rejected_at' => now(),
                    'rejection_reason' => trim((string) $data['rejection_reason']),
                    'approval_number' => $this->nextNumber(),
                    'assigned_vehicle_id' => null,
                    'assigned_driver_id' => null,
                    'assigned_service' => null,
                ])->save();

                return $fresh->fresh(['campus', 'route', 'approvedBy']);
            }

            $assignment = (string) ($data['assignment'] ?? '');
            $vehicleId = null;
            $driverId = null;
            $service = $fresh->service_type;

            $overrideNotes = [];
            $vehicleConflicts = [];
            $driverConflicts = [];

            if ($assignment === 'gocar') {
                $service = VehicleServiceType::GoCar;
            } else {
                $vehicleId = (int) ($data['vehicle_id'] ?? 0);
                $driverId = (int) ($data['driver_id'] ?? 0);
                $vehicle = OperationalVehicle::query()->find($vehicleId);
                $driver = OperationalDriver::query()->find($driverId);
                $acknowledged = filter_var($data['acknowledge_conflict'] ?? false, FILTER_VALIDATE_BOOLEAN);

                if (! $vehicle || ! $vehicle->is_active) {
                    throw ValidationException::withMessages([
                        'vehicle_id' => 'Kendaraan tidak tersedia.',
                    ]);
                }

                if (! $driver || ! $this->driverDirectory->isAssignable($driver)) {
                    throw ValidationException::withMessages([
                        'driver_id' => 'Pengemudi tidak tersedia. Pilih akun aktif dengan role Driver.',
                    ]);
                }

                $vehicleConflicts = $this->vehicleConflictSummaries($vehicle->id, $fresh->starts_at, $fresh->ends_at, $fresh->id);
                $driverConflicts = $this->driverConflictSummaries($driver->id, $fresh->starts_at, $fresh->ends_at, $fresh->id);
                $passengers = (int) ($fresh->passenger_count ?? 0);
                $capacityExceeded = $passengers > 0
                    && $vehicle->passenger_capacity !== null
                    && $passengers > (int) $vehicle->passenger_capacity;

                $errors = [];
                if ($vehicleConflicts !== []) {
                    $errors['vehicle_id'] = 'Kendaraan sudah ditugaskan pada jadwal yang tumpang tindih: '
                        .implode('; ', $vehicleConflicts)
                        .'. Centang konfirmasi untuk melanjutkan penugasan bersama.';
                    $overrideNotes[] = 'vehicle_overlap';
                }
                if ($capacityExceeded) {
                    $errors['vehicle_id'] = ($errors['vehicle_id'] ?? '')
                        .(isset($errors['vehicle_id']) ? ' ' : '')
                        .'Jumlah penumpang pengajuan ini ('.$passengers.') melebihi kapasitas kendaraan ('
                        .(int) $vehicle->passenger_capacity
                        .'). Centang konfirmasi jika Admin tetap menetapkan kendaraan ini.';
                    $overrideNotes[] = 'capacity';
                }
                if ($driverConflicts !== []) {
                    $errors['driver_id'] = 'Pengemudi sudah ditugaskan pada jadwal yang tumpang tindih: '
                        .implode('; ', $driverConflicts)
                        .'. Centang konfirmasi untuk melanjutkan penugasan bersama.';
                    $overrideNotes[] = 'driver_overlap';
                }

                if ($errors !== [] && ! $acknowledged) {
                    $errors['acknowledge_conflict'] = 'Centang konfirmasi untuk melanjutkan penugasan meskipun ada peringatan konflik atau kapasitas.';
                    throw ValidationException::withMessages($errors);
                }
            }

            $fresh->forceFill([
                'status' => FacilityRequestStatus::Disetujui,
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'rejected_at' => null,
                'rejection_reason' => null,
                'approval_number' => $this->nextNumber(),
                'assigned_vehicle_id' => $vehicleId,
                'assigned_driver_id' => $driverId,
                'assigned_service' => $service,
            ])->save();

            if ($overrideNotes !== []) {
                $this->audit->log(
                    $admin,
                    AuditAction::Update,
                    $fresh,
                    null,
                    [
                        'event' => 'vehicle_assignment_override',
                        'vehicle_id' => $vehicleId,
                        'driver_id' => $driverId,
                        'overrides' => $overrideNotes,
                        'vehicle_conflicts' => $vehicleConflicts,
                        'driver_conflicts' => $driverConflicts,
                    ],
                    'Admin mengonfirmasi penugasan kendaraan/pengemudi meskipun ada peringatan konflik atau kapasitas.',
                );
            }

            return $fresh->fresh(['campus', 'route', 'vehicle', 'driver.user', 'approvedBy', 'requestedBy']);
        });

        $this->mailer->send(
            $saved->borrower_email,
            new VehicleBorrowingDecisionMail($saved),
            'Email peminjaman kendaraan gagal dikirim.',
            $saved->id,
        );

        if ($saved->status === FacilityRequestStatus::Disetujui && $saved->assigned_driver_id) {
            $saved->loadMissing('driver.user');
            $driverUser = $saved->driver?->user;
            if ($driverUser instanceof User && $driverUser->is_active) {
                $this->mailer->send(
                    $driverUser->email,
                    new VehicleDriverAssignmentMail($saved),
                    'Email penugasan pengemudi gagal dikirim.',
                    $saved->id,
                );
            }
        }

        $this->notifications->notifyVehicleBorrowingDecided($saved);

        return $saved;
    }

    public function cancelApproval(User $admin, VehicleBorrowing $borrowing, string $reason): VehicleBorrowing
    {
        $reason = trim($reason);

        $saved = DB::transaction(function () use ($admin, $borrowing, $reason) {
            $fresh = VehicleBorrowing::query()->whereKey($borrowing->id)->lockForUpdate()->firstOrFail();
            $block = $fresh->approvalCancelBlockReason();
            if ($block !== null) {
                throw ValidationException::withMessages([
                    'cancellation_reason' => $block,
                ]);
            }

            $previousAssignment = [
                'assigned_vehicle_id' => $fresh->assigned_vehicle_id,
                'assigned_driver_id' => $fresh->assigned_driver_id,
                'assigned_service' => $fresh->assigned_service?->value ?? $fresh->assigned_service,
            ];

            $fresh->forceFill([
                'status' => FacilityRequestStatus::PersetujuanDibatalkan,
                'cancelled_at' => now(),
                'cancelled_by' => $admin->id,
                'cancellation_reason' => $reason,
                'assigned_vehicle_id' => null,
                'assigned_driver_id' => null,
                'assigned_service' => null,
            ])->save();

            $this->audit->log(
                $admin,
                AuditAction::Update,
                $fresh,
                $previousAssignment,
                [
                    'event' => 'vehicle_approval_cancelled',
                    'cancellation_reason' => $reason,
                    'previous_assignment' => $previousAssignment,
                ],
                'Admin membatalkan persetujuan peminjaman kendaraan dalam jendela 24 jam.',
            );

            return $fresh->fresh(['campus', 'route', 'vehicle', 'driver', 'approvedBy', 'cancelledBy']);
        });

        $this->mailer->send(
            $saved->borrower_email,
            new VehicleBorrowingCancellationMail($saved),
            'Email pembatalan peminjaman kendaraan gagal dikirim.',
            $saved->id,
        );

        $this->notifications->notifyVehicleBorrowingApprovalCancelled($saved);

        return $saved;
    }

    public function vehicleOverlaps(int $vehicleId, CarbonInterface $start, CarbonInterface $end, ?int $ignoreId = null): bool
    {
        return $this->vehicleConflictSummaries($vehicleId, $start, $end, $ignoreId) !== [];
    }

    public function driverOverlaps(int $driverId, CarbonInterface $start, CarbonInterface $end, ?int $ignoreId = null): bool
    {
        return $this->driverConflictSummaries($driverId, $start, $end, $ignoreId) !== [];
    }

    /**
     * Operational conflict labels only (approval number, destination, schedule) — no borrower PII.
     *
     * @return list<string>
     */
    public function vehicleConflictSummaries(int $vehicleId, CarbonInterface $start, CarbonInterface $end, ?int $ignoreId = null): array
    {
        return $this->conflictSummaries(
            VehicleBorrowing::query()->where('assigned_vehicle_id', $vehicleId),
            $start,
            $end,
            $ignoreId,
        );
    }

    /**
     * @return list<string>
     */
    public function driverConflictSummaries(int $driverId, CarbonInterface $start, CarbonInterface $end, ?int $ignoreId = null): array
    {
        return $this->conflictSummaries(
            VehicleBorrowing::query()->where('assigned_driver_id', $driverId),
            $start,
            $end,
            $ignoreId,
        );
    }

    /**
     * Map of vehicle_id / driver_id => conflict labels for the approval form UI.
     *
     * @return array{vehicles: array<int, list<string>>, drivers: array<int, list<string>>}
     */
    public function conflictMapForSchedule(CarbonInterface $start, CarbonInterface $end, ?int $ignoreId = null): array
    {
        $query = VehicleBorrowing::query()
            ->where('status', FacilityRequestStatus::Disetujui->value)
            ->where(function ($q) {
                $q->whereNotNull('assigned_vehicle_id')->orWhereNotNull('assigned_driver_id');
            });

        if ($ignoreId) {
            $query->whereKeyNot($ignoreId);
        }

        $rows = ScheduleOverlap::apply($query, 'starts_at', 'ends_at', $start, $end)
            ->get(['id', 'approval_number', 'destination', 'starts_at', 'ends_at', 'assigned_vehicle_id', 'assigned_driver_id']);

        $vehicles = [];
        $drivers = [];
        foreach ($rows as $row) {
            $label = $this->conflictLabel($row);
            if ($row->assigned_vehicle_id) {
                $vehicles[(int) $row->assigned_vehicle_id][] = $label;
            }
            if ($row->assigned_driver_id) {
                $drivers[(int) $row->assigned_driver_id][] = $label;
            }
        }

        return ['vehicles' => $vehicles, 'drivers' => $drivers];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\VehicleBorrowing>  $query
     * @return list<string>
     */
    private function conflictSummaries($query, CarbonInterface $start, CarbonInterface $end, ?int $ignoreId = null): array
    {
        $query->where('status', FacilityRequestStatus::Disetujui->value);

        if ($ignoreId) {
            $query->whereKeyNot($ignoreId);
        }

        return ScheduleOverlap::apply($query, 'starts_at', 'ends_at', $start, $end)
            ->orderBy('starts_at')
            ->get(['id', 'approval_number', 'destination', 'starts_at', 'ends_at'])
            ->map(fn (VehicleBorrowing $row) => $this->conflictLabel($row))
            ->values()
            ->all();
    }

    private function conflictLabel(VehicleBorrowing $row): string
    {
        $number = $row->approval_number ?: ('PKD-'.str_pad((string) $row->id, 6, '0', STR_PAD_LEFT));
        $destination = trim((string) $row->destination) !== '' ? trim((string) $row->destination) : 'tanpa tujuan';
        $start = $row->starts_at?->timezone((string) config('app.timezone'))->format('d/m/Y H:i') ?? '—';
        $end = $row->ends_at?->timezone((string) config('app.timezone'))->format('d/m/Y H:i') ?? '—';

        return $number.' · '.$destination.' · '.$start.'–'.$end;
    }

    private function normalizeMapsUrl(mixed $value): ?string
    {
        $url = trim(strip_tags((string) ($value ?? '')));

        return $url !== '' ? mb_substr($url, 0, 2048) : null;
    }

    /**
     * @return array{supporting_document_path: string, supporting_document_original_name: string, supporting_document_size: int}
     */
    private function storeFile(VehicleBorrowing $borrowing, UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs('vehicle-borrowings/'.$borrowing->id, $filename, self::DISK);

        if ($path === false || $path === '') {
            throw new RuntimeException('Gagal menyimpan dokumen pendukung.');
        }

        $original = trim(str_replace(['\\', '/'], '', $file->getClientOriginalName()));
        if ($original === '') {
            $original = $filename;
        }

        return [
            'supporting_document_path' => $path,
            'supporting_document_original_name' => mb_substr($original, 0, 255),
            'supporting_document_size' => (int) $file->getSize(),
        ];
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<array{name: string, unit: string, title: string}>
     */
    private function passengers(array $rows): array
    {
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
                'name' => mb_substr($name, 0, 255),
                'unit' => mb_substr(trim(strip_tags((string) ($row['unit'] ?? ''))), 0, 255),
                'title' => mb_substr(trim(strip_tags((string) ($row['title'] ?? ''))), 0, 255),
            ];
        }

        return $clean;
    }

    private function nextNumber(): string
    {
        do {
            $number = 'KND-'.now()->format('Ymd-His').'-'.random_int(100, 999);
        } while (VehicleBorrowing::query()->where('approval_number', $number)->exists());

        return $number;
    }
}
