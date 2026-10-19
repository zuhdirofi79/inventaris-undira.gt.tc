<?php

namespace App\Services\Reports;

use App\Enums\FacilityRequestStatus;
use App\Enums\VehicleServiceType;
use App\Models\RoomActivityType;
use App\Models\RoomBorrowing;
use App\Models\VehicleBorrowing;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Aggregations for room / vehicle borrowing print reports (not inventory composition).
 */
class FacilityBorrowingReportStats
{
    private const TOP_LIMIT = 10;

    /**
     * @param  Collection<int, RoomBorrowing>  $rows
     * @return array<string, mixed>
     */
    public function summarizeRooms(Collection $rows): array
    {
        $approved = $rows->filter(fn (RoomBorrowing $b) => $b->status === FacilityRequestStatus::Disetujui)->values();
        $pending = $rows->filter(fn (RoomBorrowing $b) => $b->status === FacilityRequestStatus::MenungguPersetujuan)->count();
        $rejected = $rows->filter(fn (RoomBorrowing $b) => $b->status === FacilityRequestStatus::Ditolak)->count();
        $completed = $approved->filter(fn (RoomBorrowing $b) => $b->hasEnded())->count();
        $approvedCount = $approved->count();
        $decided = $approvedCount + $rejected;

        $participantInternal = $approved->sum(fn (RoomBorrowing $b) => $b->participant_internal ? $b->participantInternalCount() : 0);
        $participantExternal = $approved->sum(fn (RoomBorrowing $b) => $b->participant_external ? $b->participantExternalCount() : 0);

        return [
            'kind' => 'room',
            'totals' => [
                'total' => $rows->count(),
                'approved' => $approvedCount,
                'pending' => $pending,
                'rejected' => $rejected,
                'completed' => $completed,
                'unique_rooms' => $approved->pluck('room_id')->filter()->unique()->count(),
                'unique_campuses' => $approved->pluck('campus_id')->filter()->unique()->count(),
                'participants_internal' => (int) $participantInternal,
                'participants_external' => (int) $participantExternal,
                'participants_total' => (int) ($participantInternal + $participantExternal),
                'scheduled_hours' => round($this->sumScheduledHours($approved), 1),
                'approval_rate' => $decided > 0 ? round(($approvedCount / $decided) * 100, 1) : null,
            ],
            'top_borrowers' => $this->roomTopBorrowers($approved),
            'top_rooms' => $this->roomTopRooms($approved),
            'by_activity' => $this->roomByActivity($rows, $approved),
            'by_campus' => $this->roomByCampus($rows),
        ];
    }

    /**
     * @param  Collection<int, VehicleBorrowing>  $rows
     * @return array<string, mixed>
     */
    public function summarizeVehicles(Collection $rows): array
    {
        $approved = $rows->filter(fn (VehicleBorrowing $b) => $b->status === FacilityRequestStatus::Disetujui)->values();
        $pending = $rows->filter(fn (VehicleBorrowing $b) => $b->status === FacilityRequestStatus::MenungguPersetujuan)->count();
        $rejected = $rows->filter(fn (VehicleBorrowing $b) => $b->status === FacilityRequestStatus::Ditolak)->count();
        $completed = $approved->filter(fn (VehicleBorrowing $b) => $b->hasEnded())->count();
        $approvedCount = $approved->count();
        $decided = $approvedCount + $rejected;

        $withVehicle = $approved->filter(fn (VehicleBorrowing $b) => $b->assigned_vehicle_id !== null)->count();
        $withGocar = $approved->filter(fn (VehicleBorrowing $b) => $b->assigned_service === VehicleServiceType::GoCar
            || $b->service_type === VehicleServiceType::GoCar)->count();
        $passengers = (int) $approved->sum(fn (VehicleBorrowing $b) => max(0, (int) $b->passenger_count));

        return [
            'kind' => 'vehicle',
            'totals' => [
                'total' => $rows->count(),
                'approved' => $approvedCount,
                'pending' => $pending,
                'rejected' => $rejected,
                'completed' => $completed,
                'unique_vehicles' => $approved->pluck('assigned_vehicle_id')->filter()->unique()->count(),
                'unique_drivers' => $approved->pluck('assigned_driver_id')->filter()->unique()->count(),
                'assigned_vehicle_trips' => $withVehicle,
                'gocar_trips' => $withGocar,
                'passengers_total' => $passengers,
                'scheduled_hours' => round($this->sumScheduledHours($approved), 1),
                'approval_rate' => $decided > 0 ? round(($approvedCount / $decided) * 100, 1) : null,
            ],
            'top_borrowers' => $this->vehicleTopBorrowers($approved),
            'top_vehicles' => $this->vehicleTopVehicles($approved),
            'top_drivers' => $this->vehicleTopDrivers($approved),
            'by_destination' => $this->vehicleByDestination($approved),
            'by_campus' => $this->vehicleByCampus($rows),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function describeRoomFilters(Request $request): array
    {
        $filters = [
            'Kampus' => $this->campusName($request) ?: 'Semua',
            'Gedung' => $this->namedModel(\App\Models\Building::class, $request->input('building_id')) ?: 'Semua',
            'Lantai' => $this->namedModel(\App\Models\Floor::class, $request->input('floor_id')) ?: 'Semua',
            'Ruangan' => $this->namedModel(\App\Models\Room::class, $request->input('room_id')) ?: 'Semua',
            'Status' => $this->facilityStatusLabel($request->input('status') ?: $request->input('approval_status')) ?: 'Semua',
        ];

        if ($request->filled('borrower_name')) {
            $filters['Peminjam'] = trim((string) $request->input('borrower_name'));
        }
        if ($request->filled('approval_number')) {
            $filters['Nomor'] = trim((string) $request->input('approval_number'));
        }
        if ($request->filled('purpose')) {
            $filters['Keperluan'] = trim((string) $request->input('purpose'));
        }
        if ($request->filled('tanggal_awal') || $request->filled('date_from')) {
            $filters['Tanggal Awal'] = (string) ($request->input('tanggal_awal') ?? $request->input('date_from'));
        }
        if ($request->filled('tanggal_akhir') || $request->filled('date_to')) {
            $filters['Tanggal Akhir'] = (string) ($request->input('tanggal_akhir') ?? $request->input('date_to'));
        }
        if ($request->filled('keyword') || $request->filled('search')) {
            $filters['Keyword'] = (string) ($request->input('keyword') ?? $request->input('search'));
        }

        return $filters;
    }

    /**
     * @return array<string, string>
     */
    public function describeVehicleFilters(Request $request): array
    {
        $filters = [
            'Kampus' => $this->campusName($request) ?: 'Semua',
            'Status' => $this->facilityStatusLabel($request->input('status') ?: $request->input('approval_status')) ?: 'Semua',
            'Jenis Layanan' => VehicleServiceType::tryFrom((string) $request->input('service_type'))?->label() ?: 'Semua',
        ];

        if ($request->filled('destination')) {
            $filters['Rute / Tujuan'] = trim((string) $request->input('destination'));
        }
        if ($request->filled('borrower_name')) {
            $filters['Peminjam'] = trim((string) $request->input('borrower_name'));
        }
        if ($request->filled('approval_number')) {
            $filters['Nomor'] = trim((string) $request->input('approval_number'));
        }
        if ($request->filled('tanggal_awal') || $request->filled('date_from')) {
            $filters['Tanggal Awal'] = (string) ($request->input('tanggal_awal') ?? $request->input('date_from'));
        }
        if ($request->filled('tanggal_akhir') || $request->filled('date_to')) {
            $filters['Tanggal Akhir'] = (string) ($request->input('tanggal_akhir') ?? $request->input('date_to'));
        }
        if ($request->filled('keyword') || $request->filled('search')) {
            $filters['Keyword'] = (string) ($request->input('keyword') ?? $request->input('search'));
        }

        return $filters;
    }

    /**
     * @param  Collection<int, RoomBorrowing|VehicleBorrowing>  $rows
     */
    protected function sumScheduledHours(Collection $rows): float
    {
        return (float) $rows->sum(function ($b): float {
            $start = $b->starts_at;
            $end = $b->ends_at;
            if (! $start instanceof CarbonInterface || ! $end instanceof CarbonInterface || $end->lte($start)) {
                return 0.0;
            }

            return $start->diffInMinutes($end) / 60;
        });
    }

    /**
     * @param  Collection<int, RoomBorrowing>  $approved
     * @return list<array<string, mixed>>
     */
    protected function roomTopBorrowers(Collection $approved): array
    {
        return $approved
            ->groupBy(fn (RoomBorrowing $b) => mb_strtolower(trim($b->borrower_name)).'|'.mb_strtolower(trim((string) ($b->borrower_affiliation ?? ''))))
            ->map(function (Collection $group) {
                /** @var RoomBorrowing $first */
                $first = $group->first();
                $unit = trim((string) ($first->borrower_affiliation ?? ''));

                return [
                    'borrower' => $first->borrower_name ?: '—',
                    'unit' => $unit !== '' ? $unit : '—',
                    'approved_count' => $group->count(),
                    'participants' => (int) $group->sum(fn (RoomBorrowing $b) => $b->totalParticipantCount()),
                ];
            })
            ->sortByDesc('approved_count')
            ->take(self::TOP_LIMIT)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, RoomBorrowing>  $approved
     * @return list<array<string, mixed>>
     */
    protected function roomTopRooms(Collection $approved): array
    {
        return $approved
            ->filter(fn (RoomBorrowing $b) => $b->room_id)
            ->groupBy('room_id')
            ->map(function (Collection $group) {
                /** @var RoomBorrowing $first */
                $first = $group->first();
                $room = $first->room;
                $floor = $room?->floor;
                $building = $floor?->building;
                $campus = $first->campus?->name ?: $building?->campus?->name;

                return [
                    'room' => $room ? trim(($room->code ? $room->code.' · ' : '').$room->name) : '—',
                    'building' => $building?->name ?: '—',
                    'floor' => $floor?->name ?: '—',
                    'campus' => $campus ?: '—',
                    'approved_count' => $group->count(),
                    'scheduled_hours' => round($this->sumScheduledHours($group), 1),
                    'participants' => (int) $group->sum(fn (RoomBorrowing $b) => $b->totalParticipantCount()),
                ];
            })
            ->sortByDesc('approved_count')
            ->take(self::TOP_LIMIT)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, RoomBorrowing>  $all
     * @param  Collection<int, RoomBorrowing>  $approved
     * @return list<array<string, mixed>>
     */
    protected function roomByActivity(Collection $all, Collection $approved): array
    {
        $codes = $all->pluck('activity_type')->filter()->unique()->values();
        $labels = RoomActivityType::query()->whereIn('code', $codes)->pluck('name', 'code');

        return $all
            ->groupBy(fn (RoomBorrowing $b) => (string) ($b->activity_type ?: ''))
            ->map(function (Collection $group, string $code) use ($approved, $labels) {
                $approvedGroup = $approved->filter(fn (RoomBorrowing $b) => (string) ($b->activity_type ?: '') === $code);

                return [
                    'activity' => $code !== ''
                        ? (string) ($labels[$code] ?? RoomActivityType::labelFor($code))
                        : 'Tidak dicantumkan',
                    'total' => $group->count(),
                    'approved' => $approvedGroup->count(),
                    'participants' => (int) $approvedGroup->sum(fn (RoomBorrowing $b) => $b->totalParticipantCount()),
                ];
            })
            ->sortByDesc('approved')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, RoomBorrowing>  $rows
     * @return list<array<string, mixed>>
     */
    protected function roomByCampus(Collection $rows): array
    {
        return $rows
            ->groupBy(fn (RoomBorrowing $b) => (int) ($b->campus_id ?: 0))
            ->map(function (Collection $group) {
                /** @var RoomBorrowing $first */
                $first = $group->first();
                $approved = $group->filter(fn (RoomBorrowing $b) => $b->status === FacilityRequestStatus::Disetujui);

                return [
                    'campus' => $first->campus?->name
                        ?: $first->room?->floor?->building?->campus?->name
                        ?: 'Tidak dicantumkan',
                    'total' => $group->count(),
                    'approved' => $approved->count(),
                    'rejected' => $group->filter(fn (RoomBorrowing $b) => $b->status === FacilityRequestStatus::Ditolak)->count(),
                    'pending' => $group->filter(fn (RoomBorrowing $b) => $b->status === FacilityRequestStatus::MenungguPersetujuan)->count(),
                    'unique_rooms' => $approved->pluck('room_id')->filter()->unique()->count(),
                    'participants' => (int) $approved->sum(fn (RoomBorrowing $b) => $b->totalParticipantCount()),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, VehicleBorrowing>  $approved
     * @return list<array<string, mixed>>
     */
    protected function vehicleTopBorrowers(Collection $approved): array
    {
        return $approved
            ->groupBy(fn (VehicleBorrowing $b) => mb_strtolower(trim($b->borrower_name)).'|'.mb_strtolower(trim((string) ($b->unit ?? ''))))
            ->map(function (Collection $group) {
                /** @var VehicleBorrowing $first */
                $first = $group->first();
                $unit = trim((string) ($first->unit ?? ''));

                return [
                    'borrower' => $first->borrower_name ?: '—',
                    'unit' => $unit !== '' ? $unit : '—',
                    'approved_count' => $group->count(),
                    'passengers' => (int) $group->sum(fn (VehicleBorrowing $b) => max(0, (int) $b->passenger_count)),
                ];
            })
            ->sortByDesc('approved_count')
            ->take(self::TOP_LIMIT)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, VehicleBorrowing>  $approved
     * @return list<array<string, mixed>>
     */
    protected function vehicleTopVehicles(Collection $approved): array
    {
        return $approved
            ->filter(fn (VehicleBorrowing $b) => $b->assigned_vehicle_id)
            ->groupBy('assigned_vehicle_id')
            ->map(function (Collection $group) {
                /** @var VehicleBorrowing $first */
                $first = $group->first();
                $vehicle = $first->vehicle;

                return [
                    'vehicle' => $vehicle?->name ?: '—',
                    'category' => $vehicle?->category ?: '—',
                    'plate' => $vehicle?->plate_number ?: '—',
                    'approved_count' => $group->count(),
                    'completed' => $group->filter(fn (VehicleBorrowing $b) => $b->hasEnded())->count(),
                    'scheduled_hours' => round($this->sumScheduledHours($group), 1),
                    'passengers' => (int) $group->sum(fn (VehicleBorrowing $b) => max(0, (int) $b->passenger_count)),
                ];
            })
            ->sortByDesc('approved_count')
            ->take(self::TOP_LIMIT)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, VehicleBorrowing>  $approved
     * @return list<array<string, mixed>>
     */
    protected function vehicleTopDrivers(Collection $approved): array
    {
        return $approved
            ->filter(fn (VehicleBorrowing $b) => $b->assigned_driver_id)
            ->groupBy('assigned_driver_id')
            ->map(function (Collection $group) {
                /** @var VehicleBorrowing $first */
                $first = $group->first();

                return [
                    'driver' => $first->driver?->name ?: '—',
                    'approved_count' => $group->count(),
                    'completed' => $group->filter(fn (VehicleBorrowing $b) => $b->hasEnded())->count(),
                    'scheduled_hours' => round($this->sumScheduledHours($group), 1),
                    'passengers' => (int) $group->sum(fn (VehicleBorrowing $b) => max(0, (int) $b->passenger_count)),
                ];
            })
            ->sortByDesc('approved_count')
            ->take(self::TOP_LIMIT)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, VehicleBorrowing>  $approved
     * @return list<array<string, mixed>>
     */
    protected function vehicleByDestination(Collection $approved): array
    {
        return $approved
            ->groupBy(fn (VehicleBorrowing $b) => mb_strtolower(trim((string) ($b->origin ?: $b->route?->name)).'|'.trim((string) $b->destination)))
            ->map(function (Collection $group) {
                /** @var VehicleBorrowing $first */
                $first = $group->first();

                return [
                    'route' => trim((string) ($first->origin ?: $first->route?->name)) ?: '—',
                    'destination' => trim((string) $first->destination) ?: '—',
                    'approved_count' => $group->count(),
                    'passengers' => (int) $group->sum(fn (VehicleBorrowing $b) => max(0, (int) $b->passenger_count)),
                ];
            })
            ->sortByDesc('approved_count')
            ->take(self::TOP_LIMIT)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, VehicleBorrowing>  $rows
     * @return list<array<string, mixed>>
     */
    protected function vehicleByCampus(Collection $rows): array
    {
        return $rows
            ->groupBy(fn (VehicleBorrowing $b) => (int) ($b->campus_id ?: 0))
            ->map(function (Collection $group) {
                /** @var VehicleBorrowing $first */
                $first = $group->first();
                $approved = $group->filter(fn (VehicleBorrowing $b) => $b->status === FacilityRequestStatus::Disetujui);

                return [
                    'campus' => $first->campus?->name ?: 'Tidak dicantumkan',
                    'total' => $group->count(),
                    'approved' => $approved->count(),
                    'rejected' => $group->filter(fn (VehicleBorrowing $b) => $b->status === FacilityRequestStatus::Ditolak)->count(),
                    'pending' => $group->filter(fn (VehicleBorrowing $b) => $b->status === FacilityRequestStatus::MenungguPersetujuan)->count(),
                    'assigned_vehicles' => $approved->pluck('assigned_vehicle_id')->filter()->unique()->count(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    protected function campusName(Request $request): ?string
    {
        if (! $request->filled('campus_id')) {
            return null;
        }

        return \App\Models\Campus::query()->find((int) $request->input('campus_id'))?->name;
    }

    protected function namedModel(string $class, mixed $id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }

        return $class::query()->find((int) $id)?->name;
    }

    protected function facilityStatusLabel(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return FacilityRequestStatus::tryFrom((string) $value)?->label();
    }
}
