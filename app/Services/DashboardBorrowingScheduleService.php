<?php

namespace App\Services;

use App\Enums\BorrowingItemStatus;
use App\Enums\BorrowingStatus;
use App\Enums\FacilityRequestStatus;
use App\Models\BorrowingItem;
use App\Models\RoomBorrowing;
use App\Models\VehicleBorrowing;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardBorrowingScheduleService
{
    private const TODAY_LIMIT = 40;

    private const UPCOMING_LIMIT = 50;

    private const UPCOMING_DAYS = 7;

    /**
     * @return list<array<string, mixed>>
     */
    public function today(?CarbonInterface $moment = null): array
    {
        $tz = (string) config('app.timezone', 'Asia/Jakarta');
        $now = ($moment ?? now())->copy()->timezone($tz);
        $dayStart = $now->copy()->startOfDay();
        $dayEnd = $now->copy()->endOfDay();

        return $this->collectEntries($dayStart, $dayEnd, $now)
            ->sortBy([
                ['starts_at', 'asc'],
                ['type', 'asc'],
                ['title', 'asc'],
            ])
            ->take(self::TODAY_LIMIT)
            ->values()
            ->all();
    }

    /**
     * @return list<array{date_key: string, date_label: string, entries: list<array<string, mixed>>}>
     */
    public function upcoming(?CarbonInterface $moment = null): array
    {
        $tz = (string) config('app.timezone', 'Asia/Jakarta');
        $now = ($moment ?? now())->copy()->timezone($tz);
        $rangeStart = $now->copy()->addDay()->startOfDay();
        $rangeEnd = $now->copy()->addDays(self::UPCOMING_DAYS)->endOfDay();

        $entries = $this->collectEntries($rangeStart, $rangeEnd, $now)
            ->sortBy([
                ['starts_at', 'asc'],
                ['type', 'asc'],
                ['title', 'asc'],
            ])
            ->take(self::UPCOMING_LIMIT)
            ->values();

        /** @var Collection<string, Collection<int, array<string, mixed>>> $grouped */
        $grouped = $entries->groupBy(function (array $entry) use ($tz) {
            /** @var CarbonInterface $start */
            $start = $entry['starts_at'];

            return $start->copy()->timezone($tz)->toDateString();
        });

        $days = [];
        foreach ($grouped as $dateKey => $dayEntries) {
            $label = Carbon::parse((string) $dateKey, $tz)->locale('id')->translatedFormat('d F Y');
            $days[] = [
                'date_key' => (string) $dateKey,
                'date_label' => $label,
                'entries' => $dayEntries->values()->all(),
            ];
        }

        return $days;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function collectEntries(CarbonInterface $rangeStart, CarbonInterface $rangeEnd, CarbonInterface $now): Collection
    {
        return $this->inventoryEntries($rangeStart, $rangeEnd, $now)
            ->concat($this->roomEntries($rangeStart, $rangeEnd, $now))
            ->concat($this->vehicleEntries($rangeStart, $rangeEnd, $now));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function inventoryEntries(CarbonInterface $rangeStart, CarbonInterface $rangeEnd, CarbonInterface $now): Collection
    {
        $approvedItemStatuses = [
            BorrowingItemStatus::Disetujui->value,
            BorrowingItemStatus::Dipinjam->value,
            BorrowingItemStatus::Dikembalikan->value,
        ];

        $lines = BorrowingItem::query()
            ->whereIn('status', $approvedItemStatuses)
            ->whereHas('borrowing', function ($query) use ($rangeStart, $rangeEnd): void {
                $query
                    ->where('status', '!=', BorrowingStatus::Ditolak->value)
                    ->where('borrow_date', '<=', $rangeEnd)
                    ->where('expected_return_date', '>=', $rangeStart);
            })
            ->with([
                'borrowing:id,borrower_name,borrow_date,expected_return_date,status',
                'inventoryItem:id,name,inventory_number,room_id',
                'inventoryItem.room:id,name,floor_id',
                'inventoryItem.room.floor:id,name,building_id',
                'inventoryItem.room.floor.building:id,name,campus_id',
                'inventoryItem.room.floor.building.campus:id,name,code',
            ])
            ->limit(80)
            ->get();

        return $lines->map(function (BorrowingItem $line) use ($now): ?array {
            $borrowing = $line->borrowing;
            $item = $line->inventoryItem;
            if ($borrowing === null || $item === null || $borrowing->borrow_date === null || $borrowing->expected_return_date === null) {
                return null;
            }

            $room = $item->room;
            $campus = $room?->floor?->building?->campus;
            $location = collect([
                $campus?->name,
                $room?->name,
            ])->filter()->implode(' · ') ?: '—';

            $phase = $this->phase($borrowing->borrow_date, $borrowing->expected_return_date, $now);
            if ($line->status === BorrowingItemStatus::Dikembalikan) {
                $phase = 'done';
            }

            return [
                'type' => 'inventaris',
                'type_label' => 'Inventaris',
                'title' => trim(($item->inventory_number ? $item->inventory_number.' — ' : '').$item->name),
                'subtitle' => $location,
                'meta' => $borrowing->borrower_name ?: null,
                'starts_at' => $borrowing->borrow_date,
                'ends_at' => $borrowing->expected_return_date,
                'time_text' => $this->timeRange($borrowing->borrow_date, $borrowing->expected_return_date),
                'status_label' => $this->phaseLabel($phase),
                'status_tone' => $this->phaseTone($phase),
                'phase' => $phase,
            ];
        })->filter()->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function roomEntries(CarbonInterface $rangeStart, CarbonInterface $rangeEnd, CarbonInterface $now): Collection
    {
        $rows = RoomBorrowing::query()
            ->where('status', FacilityRequestStatus::Disetujui)
            ->where('starts_at', '<=', $rangeEnd)
            ->where('ends_at', '>=', $rangeStart)
            ->with([
                'room:id,name,floor_id',
                'room.floor:id,name,building_id',
                'room.floor.building:id,name,campus_id',
                'room.floor.building.campus:id,name,code',
                'campus:id,name,code',
            ])
            ->orderBy('starts_at')
            ->limit(80)
            ->get();

        return $rows->map(function (RoomBorrowing $row) use ($now): ?array {
            if ($row->starts_at === null || $row->ends_at === null) {
                return null;
            }

            $campusName = $row->campus?->name
                ?: $row->room?->floor?->building?->campus?->name;
            $building = $row->room?->floor?->building?->name;
            $floor = $row->room?->floor?->name;
            $location = collect([$campusName, $building, $floor])->filter()->implode(' · ') ?: '—';
            $participants = $row->participantText();
            $phase = $this->phase($row->starts_at, $row->ends_at, $now);

            return [
                'type' => 'ruangan',
                'type_label' => 'Ruangan',
                'title' => $row->room?->name ?: 'Ruangan',
                'subtitle' => $location,
                'meta' => collect([
                    $row->activity_type?->label(),
                    $row->borrower_name,
                    $participants !== '—' ? $participants : null,
                ])->filter()->implode(' · ') ?: null,
                'starts_at' => $row->starts_at,
                'ends_at' => $row->ends_at,
                'time_text' => $this->timeRange($row->starts_at, $row->ends_at),
                'status_label' => $this->phaseLabel($phase),
                'status_tone' => $this->phaseTone($phase),
                'phase' => $phase,
            ];
        })->filter()->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function vehicleEntries(CarbonInterface $rangeStart, CarbonInterface $rangeEnd, CarbonInterface $now): Collection
    {
        $rows = VehicleBorrowing::query()
            ->where('status', FacilityRequestStatus::Disetujui)
            ->where('starts_at', '<=', $rangeEnd)
            ->where('ends_at', '>=', $rangeStart)
            ->with([
                'campus:id,name,code',
                'vehicle:id,name,plate_number',
                'driver:id,name',
            ])
            ->orderBy('starts_at')
            ->limit(80)
            ->get();

        return $rows->map(function (VehicleBorrowing $row) use ($now): ?array {
            if ($row->starts_at === null || $row->ends_at === null) {
                return null;
            }

            $assignedService = $row->assigned_service;
            $vehicle = $row->vehicle;
            if ($vehicle !== null) {
                $title = trim($vehicle->name.($vehicle->plate_number ? ' · '.$vehicle->plate_number : ''));
            } elseif ($assignedService !== null) {
                $title = 'Layanan '.$assignedService->label();
            } else {
                $title = 'Kendaraan operasional';
            }

            $phase = $this->phase($row->starts_at, $row->ends_at, $now);

            return [
                'type' => 'kendaraan',
                'type_label' => 'Kendaraan',
                'title' => $title !== '' ? $title : 'Kendaraan operasional',
                'subtitle' => collect([
                    $row->campus?->name,
                    $row->tripText(),
                ])->filter(fn ($v) => is_string($v) && $v !== '' && $v !== '—')->implode(' · ') ?: '—',
                'meta' => collect([
                    $row->borrower_name,
                    $row->driver?->name ? 'Pengemudi: '.$row->driver->name : null,
                ])->filter()->implode(' · ') ?: null,
                'starts_at' => $row->starts_at,
                'ends_at' => $row->ends_at,
                'time_text' => $this->timeRange($row->starts_at, $row->ends_at),
                'status_label' => $this->phaseLabel($phase),
                'status_tone' => $this->phaseTone($phase),
                'phase' => $phase,
            ];
        })->filter()->values();
    }

    protected function phase(CarbonInterface $start, CarbonInterface $end, CarbonInterface $now): string
    {
        if ($now->lt($start)) {
            return 'upcoming';
        }

        if ($now->gt($end)) {
            return 'done';
        }

        return 'ongoing';
    }

    protected function phaseLabel(string $phase): string
    {
        return match ($phase) {
            'upcoming' => 'Akan dimulai',
            'ongoing' => 'Sedang berlangsung',
            'done' => 'Selesai',
            default => 'Jadwal',
        };
    }

    protected function phaseTone(string $phase): string
    {
        return match ($phase) {
            'upcoming' => 'info',
            'ongoing' => 'success',
            'done' => 'secondary',
            default => 'secondary',
        };
    }

    protected function timeRange(CarbonInterface $start, CarbonInterface $end): string
    {
        $tz = (string) config('app.timezone', 'Asia/Jakarta');
        $startLocal = $start->copy()->timezone($tz);
        $endLocal = $end->copy()->timezone($tz);

        if ($startLocal->isSameDay($endLocal)) {
            return $startLocal->format('H:i').'–'.$endLocal->format('H:i');
        }

        return $startLocal->format('d/m H:i').'–'.$endLocal->format('d/m H:i');
    }
}
