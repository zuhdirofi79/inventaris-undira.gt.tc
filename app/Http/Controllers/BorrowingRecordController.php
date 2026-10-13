<?php

namespace App\Http\Controllers;

use App\Enums\BorrowingStatus;
use App\Enums\FacilityRequestStatus;
use App\Models\Borrowing;
use App\Models\Campus;
use App\Models\RoomBorrowing;
use App\Models\VehicleBorrowing;
use App\Policies\BorrowingPolicy;
use App\Support\CampusQuery;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class BorrowingRecordController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->can('borrowing.view') || $user->can('room-borrowing.view') || $user->can('vehicle-borrowing.view')),
            403
        );

        $kind = $this->kind($request->input('kind'));
        $campusId = CampusQuery::id($request->input('campus_id'));
        $status = trim((string) $request->input('status', ''));
        $search = str_replace(['%', '_'], '', trim((string) $request->input('q', '')));
        $ownOnly = app(BorrowingPolicy::class)->limitsToOwnRecords($user);

        $rows = collect();

        if (($kind === null || $kind === 'inventaris') && $user->can('borrowing.view') && $status !== 'selesai') {
            $rows = $rows->concat($this->inventoryRows($user->id, $ownOnly, $campusId, $status, $search));
        }

        if (($kind === null || $kind === 'ruangan') && $user->can('room-borrowing.view')) {
            $rows = $rows->concat($this->roomRows($user->id, $ownOnly, $campusId, $status, $search));
        }

        if (($kind === null || $kind === 'kendaraan') && $user->can('vehicle-borrowing.view')) {
            $rows = $rows->concat($this->vehicleRows($user->id, $ownOnly, $campusId, $status, $search));
        }

        $sorted = $rows->sortBy([
            ['submitted_at', 'desc'],
            ['sort_id', 'desc'],
            ['kind', 'asc'],
        ])->values();

        return view('operations.borrowing-records.index', [
            'records' => $this->paginate($request, $sorted),
            'campuses' => Campus::query()->orderBy('name')->get(['id', 'code', 'name']),
            'kind' => $kind ?? '',
            'campusId' => is_int($campusId) ? $campusId : '',
            'status' => $status,
            'search' => $search,
            'statusOptions' => $this->statusOptions($kind),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function inventoryRows(int $userId, bool $ownOnly, ?int $campusId, string $status, string $search): Collection
    {
        $allowed = array_map(fn (BorrowingStatus $case) => $case->value, BorrowingStatus::cases());
        if ($status !== '' && ! in_array($status, $allowed, true)) {
            return collect();
        }

        return Borrowing::query()
            ->with([
                'inventoryItem:id,name,inventory_number,room_id',
                'inventoryItem.room.floor.building.campus:id,name',
                'items.inventoryItem:id,name,inventory_number,room_id',
                'items.inventoryItem.room.floor.building.campus:id,name',
                'approvedBy:id,name',
            ])
            ->when($ownOnly, fn ($q) => $q->where('requested_by', $userId))
            ->when($campusId === 0, fn ($q) => $q->whereRaw('0 = 1'))
            ->when(is_int($campusId) && $campusId > 0, function ($q) use ($campusId) {
                $q->where(function ($inner) use ($campusId) {
                    $inner->whereHas('items.inventoryItem', fn ($item) => $item->forCampus($campusId))
                        ->orWhere(function ($legacy) use ($campusId) {
                            $legacy->whereDoesntHave('items')
                                ->whereHas('inventoryItem', fn ($item) => $item->forCampus($campusId));
                        });
                });
            })
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(function ($inner) use ($like) {
                    $inner->where('borrower_name', 'like', $like)
                        ->orWhere('approval_number', 'like', $like)
                        ->orWhereHas('items.inventoryItem', fn ($item) => $item->where('name', 'like', $like)->orWhere('inventory_number', 'like', $like))
                        ->orWhereHas('inventoryItem', fn ($item) => $item->where('name', 'like', $like)->orWhere('inventory_number', 'like', $like));
                });
            })
            ->latest()
            ->get()
            ->map(function (Borrowing $borrowing) {
                $item = $borrowing->items->first()?->inventoryItem ?? $borrowing->inventoryItem;
                $count = $borrowing->items->count() ?: ($borrowing->inventoryItem ? 1 : 0);
                $name = $item?->name ?: 'Inventaris';

                return [
                    'kind' => 'inventaris',
                    'sort_id' => $borrowing->id,
                    'kind_label' => 'Peminjaman Inventaris',
                    'number' => $borrowing->approval_number ?: '—',
                    'number_url' => $borrowing->approval_number ? route('borrowing.approval.public', $borrowing->approval_number) : null,
                    'borrower' => $borrowing->borrower_name,
                    'subject' => $count > 1 ? $name.' +'.($count - 1).' barang lain' : $name,
                    'campus' => $borrowing->campusSummary(),
                    'status_label' => $borrowing->status?->label() ?? '—',
                    'status_color' => $borrowing->status?->badgeColor() ?? 'secondary',
                    'starts_at' => $this->inventorySchedule($borrowing, 'borrow_date'),
                    'ends_at' => $this->inventorySchedule($borrowing, 'expected_return_date'),
                    'submitted_at' => $borrowing->created_at,
                    'decided_at' => $borrowing->approved_at ?? $borrowing->rejected_at,
                    'admin' => $borrowing->approvedBy?->name,
                    'reason' => $borrowing->rejection_reason,
                    'url' => route('borrowings.show', $borrowing),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function roomRows(int $userId, bool $ownOnly, ?int $campusId, string $status, string $search): Collection
    {
        if ($this->statusSkipsFacility($status) || ($status !== '' && ! $this->recognizedStatus($status))) {
            return collect();
        }

        return RoomBorrowing::query()
            ->with(['room:id,name,code', 'campus:id,name', 'approvedBy:id,name'])
            ->when($ownOnly, fn ($q) => $q->where('requested_by', $userId))
            ->when($campusId === 0, fn ($q) => $q->whereRaw('0 = 1'))
            ->when(is_int($campusId) && $campusId > 0, fn ($q) => $q->where('campus_id', $campusId))
            ->tap(fn ($q) => $this->applyFacilityStatus($q, $status))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(function ($inner) use ($like) {
                    $inner->where('borrower_name', 'like', $like)
                        ->orWhere('approval_number', 'like', $like)
                        ->orWhere('purpose', 'like', $like)
                        ->orWhere('activity_type', 'like', $like)
                        ->orWhereHas('room', fn ($room) => $room->where('name', 'like', $like)->orWhere('code', 'like', $like));
                });
            })
            ->latest()
            ->get()
            ->map(function (RoomBorrowing $borrowing) {
                $activity = $borrowing->activity_type?->label();
                $purpose = trim((string) $borrowing->purpose);
                $subject = $purpose !== '' ? $purpose : ($borrowing->room?->name ?: 'Ruangan');
                if (is_string($activity) && $activity !== '') {
                    $subject = $activity.' — '.$subject;
                }

                return [
                    'kind' => 'ruangan',
                    'sort_id' => $borrowing->id,
                    'kind_label' => 'Peminjaman Ruangan',
                    'number' => $borrowing->approval_number ?: '—',
                    'number_url' => $borrowing->approval_number ? route('room-borrowings.approval', $borrowing) : null,
                    'borrower' => $borrowing->borrower_name,
                    'subject' => $subject,
                    'campus' => $borrowing->campus?->name ?: '—',
                    'status_label' => $borrowing->displayLabel(),
                    'status_color' => $borrowing->displayColor(),
                    'starts_at' => $this->facilitySchedule($borrowing->starts_at),
                    'ends_at' => $this->facilitySchedule($borrowing->ends_at),
                    'submitted_at' => $borrowing->created_at,
                    'decided_at' => $borrowing->approved_at ?? $borrowing->rejected_at,
                    'admin' => $borrowing->approvedBy?->name,
                    'reason' => $borrowing->rejection_reason,
                    'url' => route('room-borrowings.show', $borrowing),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function vehicleRows(int $userId, bool $ownOnly, ?int $campusId, string $status, string $search): Collection
    {
        if ($this->statusSkipsFacility($status) || ($status !== '' && ! $this->recognizedStatus($status))) {
            return collect();
        }

        return VehicleBorrowing::query()
            ->with(['campus:id,name', 'route:id,name', 'vehicle:id,name,plate_number', 'approvedBy:id,name'])
            ->when($ownOnly, fn ($q) => $q->where('requested_by', $userId))
            ->when($campusId === 0, fn ($q) => $q->whereRaw('0 = 1'))
            ->when(is_int($campusId) && $campusId > 0, fn ($q) => $q->where('campus_id', $campusId))
            ->tap(fn ($q) => $this->applyFacilityStatus($q, $status))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(function ($inner) use ($like) {
                    $inner->where('borrower_name', 'like', $like)
                        ->orWhere('approval_number', 'like', $like)
                        ->orWhere('purpose', 'like', $like)
                        ->orWhere('origin', 'like', $like)
                        ->orWhere('destination', 'like', $like)
                        ->orWhereHas('route', fn ($route) => $route->where('name', 'like', $like))
                        ->orWhereHas('vehicle', fn ($vehicle) => $vehicle->where('name', 'like', $like)->orWhere('plate_number', 'like', $like));
                });
            })
            ->latest()
            ->get()
            ->map(function (VehicleBorrowing $borrowing) {
                $subject = $borrowing->destination
                    ?: ($borrowing->vehicle?->name ?: ($borrowing->assigned_service?->label() ?: $borrowing->service_type?->label() ?: 'Kendaraan'));

                return [
                    'kind' => 'kendaraan',
                    'sort_id' => $borrowing->id,
                    'kind_label' => 'Peminjaman Kendaraan Operasional',
                    'number' => $borrowing->approval_number ?: '—',
                    'number_url' => $borrowing->approval_number ? route('vehicle-borrowings.approval', $borrowing) : null,
                    'borrower' => $borrowing->borrower_name,
                    'subject' => $subject,
                    'campus' => $borrowing->campus?->name ?: '—',
                    'status_label' => $borrowing->displayLabel(),
                    'status_color' => $borrowing->displayColor(),
                    'starts_at' => $this->facilitySchedule($borrowing->starts_at),
                    'ends_at' => $this->facilitySchedule($borrowing->ends_at),
                    'submitted_at' => $borrowing->created_at,
                    'decided_at' => $borrowing->approved_at ?? $borrowing->rejected_at,
                    'admin' => $borrowing->approvedBy?->name,
                    'reason' => $borrowing->rejection_reason,
                    'url' => route('vehicle-borrowings.show', $borrowing),
                ];
            });
    }

    private function inventorySchedule(Borrowing $borrowing, string $attribute): string
    {
        $date = $borrowing->plannedScheduleDate($attribute);
        if ($date === '—') {
            return '—';
        }

        $time = $borrowing->plannedScheduleTime($attribute);

        return $time ? $date.' '.$time : $date;
    }

    private function facilitySchedule(mixed $value): string
    {
        if (! $value instanceof CarbonInterface) {
            return '—';
        }

        return $value->timezone((string) config('app.timezone'))->format('d/m/Y H:i');
    }

    private function statusSkipsFacility(string $status): bool
    {
        return in_array($status, [
            BorrowingStatus::Dipinjam->value,
            BorrowingStatus::Dikembalikan->value,
            BorrowingStatus::Terlambat->value,
        ], true);
    }

    /**
     * Status tampilan fasilitas tidak mengubah kolom status.
     * Dipinjam, Dikembalikan, dan Terlambat hanya milik inventaris.
     * Selesai adalah pengajuan disetujui yang jadwalnya sudah berakhir.
     * Disetujui pada fasilitas adalah pengajuan disetujui yang belum berakhir.
     */
    private function applyFacilityStatus(mixed $query, string $status): void
    {
        if ($status === 'selesai') {
            $query->where('status', FacilityRequestStatus::Disetujui->value)->where('ends_at', '<', now());

            return;
        }

        if ($status === FacilityRequestStatus::Disetujui->value) {
            $query->where('status', FacilityRequestStatus::Disetujui->value)
                ->where(function ($inner) {
                    $inner->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                });

            return;
        }

        if (in_array($status, [FacilityRequestStatus::MenungguPersetujuan->value, FacilityRequestStatus::Ditolak->value], true)) {
            $query->where('status', $status);
        }
    }

    private function recognizedStatus(string $status): bool
    {
        if ($status === '' || $status === 'selesai') {
            return true;
        }

        return in_array($status, array_map(fn (BorrowingStatus $case) => $case->value, BorrowingStatus::cases()), true);
    }

    private function kind(mixed $raw): ?string
    {
        $kind = is_string($raw) ? $raw : '';

        return in_array($kind, ['inventaris', 'ruangan', 'kendaraan'], true) ? $kind : null;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(?string $kind): array
    {
        $inventory = [
            ['value' => BorrowingStatus::MenungguPersetujuan->value, 'label' => 'Menunggu Persetujuan'],
            ['value' => BorrowingStatus::Disetujui->value, 'label' => 'Disetujui'],
            ['value' => BorrowingStatus::Ditolak->value, 'label' => 'Ditolak'],
            ['value' => BorrowingStatus::Dipinjam->value, 'label' => 'Dipinjam'],
            ['value' => BorrowingStatus::Dikembalikan->value, 'label' => 'Dikembalikan'],
            ['value' => BorrowingStatus::Terlambat->value, 'label' => 'Terlambat'],
        ];
        $facility = [
            ['value' => FacilityRequestStatus::MenungguPersetujuan->value, 'label' => 'Menunggu Persetujuan'],
            ['value' => FacilityRequestStatus::Disetujui->value, 'label' => 'Disetujui'],
            ['value' => FacilityRequestStatus::Ditolak->value, 'label' => 'Ditolak'],
            ['value' => 'selesai', 'label' => 'Selesai'],
        ];

        return match ($kind) {
            'inventaris' => $inventory,
            'ruangan', 'kendaraan' => $facility,
            default => array_merge($facility, array_slice($inventory, 3)),
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function paginate(Request $request, Collection $rows): LengthAwarePaginator
    {
        $perPage = 10;
        $page = max(1, (int) $request->input('page', 1));

        return new Paginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
