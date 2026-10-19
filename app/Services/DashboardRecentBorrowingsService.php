<?php

namespace App\Services;

use App\Enums\BorrowingItemStatus;
use App\Enums\BorrowingStatus;
use App\Enums\FacilityRequestStatus;
use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\RoomBorrowing;
use App\Models\User;
use App\Models\VehicleBorrowing;
use App\Policies\BorrowingPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardRecentBorrowingsService
{
    private const LIMIT = 10;

    private const FETCH_PER_KIND = 12;

    /**
     * @return array{
     *     submissions: list<array<string, mixed>>,
     *     approved: list<array<string, mixed>>,
     *     submissions_url: ?string,
     *     approved_url: ?string
     * }
     */
    public function forUser(?User $user): array
    {
        $canInventory = (bool) $user?->can('borrowing.view');
        $canRoom = (bool) $user?->can('room-borrowing.view');
        $canVehicle = (bool) $user?->can('vehicle-borrowing.view');
        $canAny = $canInventory || $canRoom || $canVehicle;

        $recordsUrl = $canAny ? route('borrowing-records.index') : null;
        $approvedUrl = $canAny
            ? route('borrowing-records.index', ['status' => FacilityRequestStatus::Disetujui->value])
            : null;

        if (! $canAny || $user === null) {
            return [
                'submissions' => [],
                'approved' => [],
                'submissions_url' => null,
                'approved_url' => null,
            ];
        }

        $ownOnly = app(BorrowingPolicy::class)->limitsToOwnRecords($user);

        return [
            'submissions' => $this->recentSubmissions($user, $ownOnly, $canInventory, $canRoom, $canVehicle),
            'approved' => $this->recentApproved($user, $ownOnly, $canInventory, $canRoom, $canVehicle),
            'submissions_url' => $recordsUrl,
            'approved_url' => $approvedUrl,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function recentSubmissions(User $user, bool $ownOnly, bool $canInventory, bool $canRoom, bool $canVehicle): array
    {
        $rows = collect();

        if ($canInventory) {
            $rows = $rows->concat($this->inventorySubmissionRows($user, $ownOnly));
        }
        if ($canRoom) {
            $rows = $rows->concat($this->roomSubmissionRows($user, $ownOnly));
        }
        if ($canVehicle) {
            $rows = $rows->concat($this->vehicleSubmissionRows($user, $ownOnly));
        }

        return $rows
            ->sortByDesc(fn (array $row) => $row['sort_at']?->getTimestamp() ?? 0)
            ->take(self::LIMIT)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function recentApproved(User $user, bool $ownOnly, bool $canInventory, bool $canRoom, bool $canVehicle): array
    {
        $rows = collect();

        if ($canInventory) {
            $rows = $rows->concat($this->inventoryApprovedRows($user, $ownOnly));
        }
        if ($canRoom) {
            $rows = $rows->concat($this->roomApprovedRows($user, $ownOnly));
        }
        if ($canVehicle) {
            $rows = $rows->concat($this->vehicleApprovedRows($user, $ownOnly));
        }

        return $rows
            ->sortByDesc(fn (array $row) => $row['sort_at']?->getTimestamp() ?? 0)
            ->take(self::LIMIT)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function inventorySubmissionRows(User $user, bool $ownOnly): Collection
    {
        return Borrowing::query()
            ->with([
                'items:id,borrowing_id,inventory_item_id,status,qty',
                'items.inventoryItem:id,name,inventory_number,room_id',
                'items.inventoryItem.room:id,name,floor_id',
                'items.inventoryItem.room.floor:id,building_id',
                'items.inventoryItem.room.floor.building:id,campus_id',
                'items.inventoryItem.room.floor.building.campus:id,name',
                'inventoryItem:id,name,inventory_number,room_id',
                'inventoryItem.room.floor.building.campus:id,name',
            ])
            ->when($ownOnly, fn ($q) => $q->where('requested_by', $user->id))
            ->latest('created_at')
            ->limit(self::FETCH_PER_KIND)
            ->get()
            ->map(function (Borrowing $borrowing) use ($user): array {
                $lines = $borrowing->lineItems();
                $summary = $this->inventorySummary($lines, false);

                return $this->baseRow(
                    type: 'inventaris',
                    typeLabel: 'Peminjaman Inventaris',
                    number: $borrowing->approval_number ?: '#'.$borrowing->id,
                    numberUrl: $this->inventoryDetailUrl($user, $borrowing),
                    borrower: $borrowing->borrower_name,
                    unit: $borrowing->borrower_affiliation,
                    detail: $summary['label'],
                    campus: $borrowing->campusSummary(),
                    schedule: $this->inventoryScheduleText($borrowing),
                    statusLabel: $borrowing->status?->label() ?? '—',
                    statusTone: $borrowing->status?->badgeColor() ?? 'secondary',
                    sortAt: $borrowing->created_at,
                    meta: $summary['count'].' item diajukan',
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function inventoryApprovedRows(User $user, bool $ownOnly): Collection
    {
        $approvedItemStatuses = [
            BorrowingItemStatus::Disetujui->value,
            BorrowingItemStatus::Dipinjam->value,
            BorrowingItemStatus::Dikembalikan->value,
        ];

        return Borrowing::query()
            ->with([
                'items:id,borrowing_id,inventory_item_id,status,qty',
                'items.inventoryItem:id,name,inventory_number,room_id',
                'items.inventoryItem.room:id,name,floor_id',
                'items.inventoryItem.room.floor:id,building_id',
                'items.inventoryItem.room.floor.building:id,campus_id',
                'items.inventoryItem.room.floor.building.campus:id,name',
                'inventoryItem:id,name,inventory_number,room_id',
                'inventoryItem.room.floor.building.campus:id,name',
            ])
            ->where(function ($query) use ($approvedItemStatuses): void {
                $query->whereIn('status', [
                    BorrowingStatus::Disetujui->value,
                    BorrowingStatus::Dipinjam->value,
                    BorrowingStatus::Dikembalikan->value,
                    BorrowingStatus::Terlambat->value,
                ])->orWhereHas('items', fn ($items) => $items->whereIn('status', $approvedItemStatuses));
            })
            ->when($ownOnly, fn ($q) => $q->where('requested_by', $user->id))
            ->orderByDesc('approved_at')
            ->orderByDesc('updated_at')
            ->limit(self::FETCH_PER_KIND)
            ->get()
            ->map(function (Borrowing $borrowing) use ($user): ?array {
                $approvedStatuses = [
                    BorrowingItemStatus::Disetujui,
                    BorrowingItemStatus::Dipinjam,
                    BorrowingItemStatus::Dikembalikan,
                ];
                $lines = $borrowing->lineItems()
                    ->filter(fn (BorrowingItem $line) => in_array($line->status, $approvedStatuses, true))
                    ->values();

                if ($lines->isEmpty()) {
                    return null;
                }

                $summary = $this->inventorySummary($lines, true);

                return $this->baseRow(
                    type: 'inventaris',
                    typeLabel: 'Peminjaman Inventaris',
                    number: $borrowing->approval_number ?: '#'.$borrowing->id,
                    numberUrl: $this->inventoryDetailUrl($user, $borrowing),
                    borrower: $borrowing->borrower_name,
                    unit: $borrowing->borrower_affiliation,
                    detail: $summary['label'],
                    campus: $borrowing->campusSummary(),
                    schedule: $this->inventoryScheduleText($borrowing),
                    statusLabel: $borrowing->status?->label() ?? 'Disetujui',
                    statusTone: $borrowing->status?->badgeColor() ?? 'success',
                    sortAt: $borrowing->approved_at ?? $borrowing->updated_at ?? $borrowing->created_at,
                    meta: $summary['count'].' item disetujui',
                );
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function roomSubmissionRows(User $user, bool $ownOnly): Collection
    {
        return RoomBorrowing::query()
            ->with([
                'room:id,name,floor_id',
                'room.floor:id,name,building_id',
                'room.floor.building:id,name,campus_id',
                'room.floor.building.campus:id,name',
                'campus:id,name',
            ])
            ->when($ownOnly, fn ($q) => $q->where('requested_by', $user->id))
            ->latest('created_at')
            ->limit(self::FETCH_PER_KIND)
            ->get()
            ->map(function (RoomBorrowing $row) use ($user): array {
                $location = collect([
                    $row->campus?->name ?: $row->room?->floor?->building?->campus?->name,
                    $row->room?->floor?->building?->name,
                    $row->room?->floor?->name,
                ])->filter()->implode(' · ') ?: '—';

                $detail = collect([
                    $row->room?->name ?: 'Ruangan',
                    $row->activityTypeLabel(),
                ])->filter()->implode(' — ');

                return $this->baseRow(
                    type: 'ruangan',
                    typeLabel: 'Peminjaman Ruangan',
                    number: $row->approval_number ?: '#'.$row->id,
                    numberUrl: $user->can('room-borrowing.view') ? route('room-borrowings.show', $row) : null,
                    borrower: $row->borrower_name,
                    unit: $row->borrower_affiliation,
                    detail: $detail,
                    campus: $location,
                    schedule: $this->facilityScheduleText($row->starts_at, $row->ends_at),
                    statusLabel: $row->displayLabel(),
                    statusTone: $row->displayColor(),
                    sortAt: $row->created_at,
                    meta: $row->participantText() !== '—' ? $row->participantText() : null,
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function roomApprovedRows(User $user, bool $ownOnly): Collection
    {
        return RoomBorrowing::query()
            ->with([
                'room:id,name,floor_id',
                'room.floor:id,name,building_id',
                'room.floor.building:id,name,campus_id',
                'room.floor.building.campus:id,name',
                'campus:id,name',
            ])
            ->where('status', FacilityRequestStatus::Disetujui)
            ->when($ownOnly, fn ($q) => $q->where('requested_by', $user->id))
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->limit(self::FETCH_PER_KIND)
            ->get()
            ->map(function (RoomBorrowing $row) use ($user): array {
                $location = collect([
                    $row->campus?->name ?: $row->room?->floor?->building?->campus?->name,
                    $row->room?->floor?->building?->name,
                    $row->room?->floor?->name,
                ])->filter()->implode(' · ') ?: '—';

                $detail = collect([
                    $row->room?->name ?: 'Ruangan',
                    $row->activityTypeLabel(),
                    $row->participantText() !== '—' ? $row->participantText() : null,
                ])->filter()->implode(' — ');

                return $this->baseRow(
                    type: 'ruangan',
                    typeLabel: 'Peminjaman Ruangan',
                    number: $row->approval_number ?: '#'.$row->id,
                    numberUrl: $user->can('room-borrowing.view') ? route('room-borrowings.show', $row) : null,
                    borrower: $row->borrower_name,
                    unit: $row->borrower_affiliation,
                    detail: $detail,
                    campus: $location,
                    schedule: $this->facilityScheduleText($row->starts_at, $row->ends_at),
                    statusLabel: $row->displayLabel(),
                    statusTone: $row->displayColor(),
                    sortAt: $row->approved_at ?? $row->updated_at ?? $row->created_at,
                    meta: null,
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function vehicleSubmissionRows(User $user, bool $ownOnly): Collection
    {
        return VehicleBorrowing::query()
            ->with(['campus:id,name', 'vehicle:id,name,plate_number', 'driver:id,name'])
            ->when($ownOnly, fn ($q) => $q->where('requested_by', $user->id))
            ->latest('created_at')
            ->limit(self::FETCH_PER_KIND)
            ->get()
            ->map(function (VehicleBorrowing $row) use ($user): array {
                return $this->baseRow(
                    type: 'kendaraan',
                    typeLabel: 'Peminjaman Kendaraan',
                    number: $row->approval_number ?: '#'.$row->id,
                    numberUrl: $user->can('vehicle-borrowing.view') ? route('vehicle-borrowings.show', $row) : null,
                    borrower: $row->borrower_name,
                    unit: $row->unit,
                    detail: $this->vehicleDetail($row, false),
                    campus: $row->campus?->name ?: '—',
                    schedule: $this->facilityScheduleText($row->starts_at, $row->ends_at),
                    statusLabel: $row->displayLabel(),
                    statusTone: $row->displayColor(),
                    sortAt: $row->created_at,
                    meta: $row->tripText() !== '—' ? $row->tripText() : null,
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function vehicleApprovedRows(User $user, bool $ownOnly): Collection
    {
        return VehicleBorrowing::query()
            ->with(['campus:id,name', 'vehicle:id,name,plate_number', 'driver:id,name'])
            ->where('status', FacilityRequestStatus::Disetujui)
            ->when($ownOnly, fn ($q) => $q->where('requested_by', $user->id))
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->limit(self::FETCH_PER_KIND)
            ->get()
            ->map(function (VehicleBorrowing $row) use ($user): array {
                $driver = $row->driver?->name ? 'Pengemudi: '.$row->driver->name : null;

                return $this->baseRow(
                    type: 'kendaraan',
                    typeLabel: 'Peminjaman Kendaraan',
                    number: $row->approval_number ?: '#'.$row->id,
                    numberUrl: $user->can('vehicle-borrowing.view') ? route('vehicle-borrowings.show', $row) : null,
                    borrower: $row->borrower_name,
                    unit: $row->unit,
                    detail: $this->vehicleDetail($row, true),
                    campus: $row->campus?->name ?: '—',
                    schedule: $this->facilityScheduleText($row->starts_at, $row->ends_at),
                    statusLabel: $row->displayLabel(),
                    statusTone: $row->displayColor(),
                    sortAt: $row->approved_at ?? $row->updated_at ?? $row->created_at,
                    meta: collect([$row->tripText() !== '—' ? $row->tripText() : null, $driver])->filter()->implode(' · ') ?: null,
                );
            });
    }

    /**
     * @param  Collection<int, BorrowingItem>  $lines
     * @return array{label: string, count: int}
     */
    protected function inventorySummary(Collection $lines, bool $approvedOnly): array
    {
        $names = $lines
            ->map(fn (BorrowingItem $line) => $line->inventoryItem?->name)
            ->filter()
            ->values();

        $count = $names->count();
        if ($count === 0) {
            return [
                'label' => $approvedOnly ? 'Tidak ada item disetujui' : 'Inventaris',
                'count' => 0,
            ];
        }

        $first = (string) $names->first();
        $label = $count === 1 ? $first : $first.' + '.($count - 1).' item lainnya';

        return ['label' => $label, 'count' => $count];
    }

    protected function vehicleDetail(VehicleBorrowing $row, bool $requireAssignment): string
    {
        if ($row->vehicle !== null) {
            return trim($row->vehicle->name.($row->vehicle->plate_number ? ' · '.$row->vehicle->plate_number : ''));
        }

        if ($row->assigned_service !== null) {
            return 'Layanan '.$row->assigned_service->label();
        }

        if ($requireAssignment) {
            return 'Belum ditetapkan';
        }

        return $row->service_type?->label() ?: 'Belum ditetapkan';
    }

    protected function inventoryScheduleText(Borrowing $borrowing): string
    {
        $tz = (string) config('app.timezone', 'Asia/Jakarta');
        $start = $borrowing->borrow_date?->timezone($tz);
        $end = $borrowing->expected_return_date?->timezone($tz);

        if (! $start && ! $end) {
            return '—';
        }

        $startText = $start?->format('d/m/Y H:i') ?: '—';
        $endText = $end?->format('d/m/Y H:i') ?: '—';

        return $startText.' – '.$endText;
    }

    protected function facilityScheduleText(mixed $start, mixed $end): string
    {
        $tz = (string) config('app.timezone', 'Asia/Jakarta');
        $startAt = $start instanceof Carbon ? $start->copy()->timezone($tz) : null;
        $endAt = $end instanceof Carbon ? $end->copy()->timezone($tz) : null;

        if (! $startAt && ! $endAt) {
            return '—';
        }

        return ($startAt?->format('d/m/Y H:i') ?: '—').' – '.($endAt?->format('d/m/Y H:i') ?: '—');
    }

    protected function inventoryDetailUrl(User $user, Borrowing $borrowing): ?string
    {
        if (! $user->can('view', $borrowing)) {
            return null;
        }

        return route('borrowings.show', $borrowing);
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseRow(
        string $type,
        string $typeLabel,
        string $number,
        ?string $numberUrl,
        string $borrower,
        ?string $unit,
        string $detail,
        string $campus,
        string $schedule,
        string $statusLabel,
        string $statusTone,
        mixed $sortAt,
        ?string $meta,
    ): array {
        $unitLabel = trim((string) ($unit ?? ''));

        return [
            'type' => $type,
            'type_label' => $typeLabel,
            'number' => $number,
            'number_url' => $numberUrl,
            'borrower' => $borrower !== '' ? $borrower : '—',
            'unit' => $unitLabel !== '' ? $unitLabel : '—',
            'detail' => $detail !== '' ? $detail : '—',
            'campus' => $campus !== '' ? $campus : '—',
            'schedule' => $schedule,
            'status_label' => $statusLabel,
            'status_tone' => $statusTone,
            'meta' => $meta,
            'sort_at' => $sortAt instanceof Carbon ? $sortAt : null,
        ];
    }
}
