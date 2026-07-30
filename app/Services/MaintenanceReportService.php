<?php

namespace App\Services;

use App\Enums\InventoryStatus;
use App\Enums\MaintenanceStatus;
use App\Models\InventoryItem;
use App\Models\Maintenance;
use App\Models\MaintenanceSchedule;
use Illuminate\Database\Eloquent\Builder;

class MaintenanceReportService
{
    public const TYPES = [
        'history' => 'Riwayat Pemeliharaan',
        'cost' => 'Biaya Pemeliharaan',
        'preventive' => 'Pemeliharaan Preventif',
        'vendor' => 'Performa Vendor',
        'technician' => 'Performa Teknisi',
        'damaged' => 'Aset Paling Sering Rusak / Bermasalah',
    ];

    /**
     * @param  array{type?: string, date_from?: string, date_to?: string, campus_id?: int|string|null, status?: string|null}  $filters
     * @return array{type: string, title: string, headings: list<string>, rows: list<list<string|int|float|null>>, meta: array<string, mixed>}
     */
    public function build(array $filters): array
    {
        $type = (string) ($filters['type'] ?? 'history');
        if (! isset(self::TYPES[$type])) {
            $type = 'history';
        }

        return match ($type) {
            'cost' => $this->costReport($filters),
            'preventive' => $this->preventiveReport($filters),
            'vendor' => $this->vendorReport($filters),
            'technician' => $this->technicianReport($filters),
            'damaged' => $this->damagedReport($filters),
            default => $this->historyReport($filters),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{type: string, title: string, headings: list<string>, rows: list<list<string|int|float|null>>, meta: array<string, mixed>}
     */
    protected function historyReport(array $filters): array
    {
        $items = $this->maintenanceQuery($filters)
            ->with([
                'inventoryItem:id,inventory_number,name,category_id,room_id',
                'inventoryItem.category:id,name',
                'inventoryItem.room:id,floor_id',
                'inventoryItem.room.floor:id,building_id',
                'inventoryItem.room.floor.building:id,campus_id',
                'inventoryItem.room.floor.building.campus:id,name',
                'vendor:id,name',
            ])
            ->orderByDesc('service_date')
            ->limit(5000)
            ->get([
                'id', 'maintenance_number', 'service_date', 'inventory_item_id', 'vendor_id',
                'maintenance_type', 'priority', 'technician', 'status', 'result',
                'estimated_cost', 'cost', 'completed_at', 'notes',
            ]);

        $rows = $items->map(function (Maintenance $m) {
            return [
                $m->maintenance_number,
                $m->service_date?->format('Y-m-d'),
                $m->inventoryItem?->inventory_number,
                $m->inventoryItem?->name,
                $m->inventoryItem?->category?->name,
                $m->inventoryItem?->room?->floor?->building?->campus?->name,
                $m->maintenance_type?->label(),
                $m->priority?->label(),
                $m->technician,
                $m->vendor?->name,
                $m->status?->label(),
                $m->result?->label(),
                $m->estimated_cost,
                $m->cost,
                $m->completed_at?->format('Y-m-d H:i'),
                $m->notes,
            ];
        })->all();

        return [
            'type' => 'history',
            'title' => self::TYPES['history'],
            'headings' => [
                'No. WO', 'Tanggal', 'No. Inventaris', 'Nama', 'Kategori', 'Kampus',
                'Jenis', 'Prioritas', 'Teknisi', 'Vendor', 'Status', 'Hasil',
                'Estimasi Biaya', 'Biaya Aktual', 'Selesai', 'Catatan',
            ],
            'rows' => $rows,
            'meta' => ['count' => count($rows), 'filters' => $filters],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{type: string, title: string, headings: list<string>, rows: list<list<string|int|float|null>>, meta: array<string, mixed>}
     */
    protected function costReport(array $filters): array
    {
        $items = $this->maintenanceQuery($filters)
            ->with(['inventoryItem:id,inventory_number,name', 'vendor:id,name'])
            ->orderByDesc('service_date')
            ->limit(5000)
            ->get([
                'id', 'maintenance_number', 'service_date', 'inventory_item_id', 'vendor_id',
                'technician', 'estimated_cost', 'cost', 'status',
            ]);

        $rows = $items->map(fn (Maintenance $m) => [
            $m->maintenance_number,
            $m->service_date?->format('Y-m-d'),
            $m->inventoryItem?->inventory_number,
            $m->inventoryItem?->name,
            $m->vendor?->name,
            $m->technician,
            $m->estimated_cost,
            $m->cost,
            (float) $m->cost - (float) ($m->estimated_cost ?? 0),
            $m->status?->label(),
        ])->all();

        $totalCost = collect($rows)->sum(fn ($r) => (float) ($r[7] ?? 0));

        return [
            'type' => 'cost',
            'title' => self::TYPES['cost'],
            'headings' => [
                'No. WO', 'Tanggal', 'No. Inventaris', 'Nama', 'Vendor', 'Teknisi',
                'Estimasi', 'Aktual', 'Selisih', 'Status',
            ],
            'rows' => $rows,
            'meta' => ['count' => count($rows), 'total_cost' => $totalCost, 'filters' => $filters],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{type: string, title: string, headings: list<string>, rows: list<list<string|int|float|null>>, meta: array<string, mixed>}
     */
    protected function preventiveReport(array $filters): array
    {
        $query = MaintenanceSchedule::query()
            ->with(['inventoryItem.room.floor.building.campus'])
            ->orderBy('next_maintenance_date');

        if (! empty($filters['campus_id'])) {
            $campusId = (int) $filters['campus_id'];
            $query->whereHas('inventoryItem.room.floor.building', fn ($q) => $q->where('campus_id', $campusId));
        }

        $items = $query->limit(5000)->get();

        $rows = $items->map(function (MaintenanceSchedule $s) {
            $state = ! $s->is_active ? 'Nonaktif' : ($s->isOverdue() ? 'Terlambat' : ($s->isDueToday() ? 'Hari ini' : 'Aktif'));

            return [
                $s->inventoryItem?->inventory_number,
                $s->inventoryItem?->name,
                $s->inventoryItem?->room?->floor?->building?->campus?->name,
                $s->maintenance_type?->label(),
                $s->intervalLabel(),
                $s->next_maintenance_date?->format('Y-m-d'),
                $s->remind_before_days,
                $s->responsible_pic,
                $state,
                $s->last_completed_at?->format('Y-m-d H:i'),
            ];
        })->all();

        return [
            'type' => 'preventive',
            'title' => self::TYPES['preventive'],
            'headings' => [
                'No. Inventaris', 'Nama', 'Kampus', 'Jenis', 'Interval',
                'Berikutnya', 'Pengingat (hari)', 'PIC', 'Status', 'Terakhir Selesai',
            ],
            'rows' => $rows,
            'meta' => ['count' => count($rows), 'filters' => $filters],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{type: string, title: string, headings: list<string>, rows: list<list<string|int|float|null>>, meta: array<string, mixed>}
     */
    protected function vendorReport(array $filters): array
    {
        $rows = Maintenance::query()
            ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('service_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('service_date', '<=', $filters['date_to']))
            ->whereNotNull('vendor_id')
            ->join('vendors', 'vendors.id', '=', 'maintenances.vendor_id')
            ->selectRaw(
                'vendors.name as vendor_name, COUNT(*) as wo_count, COALESCE(SUM(maintenances.cost),0) as total_cost, SUM(CASE WHEN maintenances.status = ? THEN 1 ELSE 0 END) as completed',
                [MaintenanceStatus::Completed->value]
            )
            ->groupBy('vendors.id', 'vendors.name')
            ->orderByDesc('wo_count')
            ->get()
            ->map(fn ($r) => [
                $r->vendor_name,
                (int) $r->wo_count,
                (int) $r->completed,
                (float) $r->total_cost,
                (int) $r->wo_count > 0 ? round(((int) $r->completed / (int) $r->wo_count) * 100, 1) : 0,
            ])
            ->all();

        return [
            'type' => 'vendor',
            'title' => self::TYPES['vendor'],
            'headings' => ['Vendor', 'Jumlah WO', 'Selesai', 'Total Biaya', '% Selesai'],
            'rows' => $rows,
            'meta' => ['count' => count($rows), 'filters' => $filters],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{type: string, title: string, headings: list<string>, rows: list<list<string|int|float|null>>, meta: array<string, mixed>}
     */
    protected function technicianReport(array $filters): array
    {
        $rows = $this->maintenanceQuery($filters)
            ->whereNotNull('technician')
            ->where('technician', '!=', '')
            ->selectRaw('technician, COUNT(*) as wo_count, COALESCE(SUM(cost),0) as total_cost, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed', [MaintenanceStatus::Completed->value])
            ->groupBy('technician')
            ->orderByDesc('wo_count')
            ->limit(500)
            ->get()
            ->map(fn ($r) => [
                $r->technician,
                (int) $r->wo_count,
                (int) $r->completed,
                (float) $r->total_cost,
                (int) $r->wo_count > 0 ? round(((int) $r->completed / (int) $r->wo_count) * 100, 1) : 0,
            ])
            ->all();

        return [
            'type' => 'technician',
            'title' => self::TYPES['technician'],
            'headings' => ['Teknisi', 'Jumlah WO', 'Selesai', 'Total Biaya', '% Selesai'],
            'rows' => $rows,
            'meta' => ['count' => count($rows), 'filters' => $filters],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{type: string, title: string, headings: list<string>, rows: list<list<string|int|float|null>>, meta: array<string, mixed>}
     */
    protected function damagedReport(array $filters): array
    {
        $woCounts = Maintenance::query()
            ->selectRaw('inventory_item_id, COUNT(*) as wo_count')
            ->groupBy('inventory_item_id');

        $items = InventoryItem::query()
            ->with(['category', 'room.floor.building.campus', 'pic'])
            ->leftJoinSub($woCounts, 'wo', 'wo.inventory_item_id', '=', 'inventory_items.id')
            ->where(function ($q) {
                $q->whereIn('inventory_items.status', [
                    InventoryStatus::RusakRingan->value,
                    InventoryStatus::RusakBerat->value,
                    InventoryStatus::TidakDapatDiperbaiki->value,
                    InventoryStatus::PerluPenggantian->value,
                ])->orWhere('wo.wo_count', '>', 0);
            })
            ->when(! empty($filters['campus_id']), function ($q) use ($filters) {
                $campusId = (int) $filters['campus_id'];
                $q->whereHas('room.floor.building', fn ($b) => $b->where('campus_id', $campusId));
            })
            ->orderByDesc('wo.wo_count')
            ->select('inventory_items.*', 'wo.wo_count')
            ->limit(1000)
            ->get();

        $rows = $items->map(fn (InventoryItem $item) => [
            $item->inventory_number,
            $item->name,
            $item->category?->name,
            $item->room?->floor?->building?->campus?->name,
            $item->status?->label(),
            (int) ($item->wo_count ?? 0),
            $item->picDisplayName(),
        ])->all();

        return [
            'type' => 'damaged',
            'title' => self::TYPES['damaged'],
            'headings' => [
                'No. Inventaris', 'Nama', 'Kategori', 'Kampus', 'Status', 'Jumlah WO', 'PIC',
            ],
            'rows' => $rows,
            'meta' => ['count' => count($rows), 'filters' => $filters],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Maintenance>
     */
    protected function maintenanceQuery(array $filters): Builder
    {
        return Maintenance::query()
            ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('service_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('service_date', '<=', $filters['date_to']))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['campus_id']), function ($q) use ($filters) {
                $campusId = (int) $filters['campus_id'];
                $q->whereHas('inventoryItem.room.floor.building', fn ($b) => $b->where('campus_id', $campusId));
            });
    }
}
