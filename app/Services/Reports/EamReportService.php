<?php

namespace App\Services\Reports;

use App\Enums\InventoryCondition;
use App\Enums\InventoryReportType;
use App\Enums\InventoryStatus;
use App\Models\Borrowing;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\InventoryMutation;
use App\Models\Maintenance;
use App\Models\ProcurementType;
use App\Models\Room;
use App\Models\SubCategory;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vendor;
use App\Services\InventoryFilterService;
use App\Services\InventoryReportService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Enterprise Asset Management report builder.
 * Aggregations use SQL groupBy — never load 100k rows into memory for dashboards.
 */
class EamReportService
{
    public const PREVIEW_PER_PAGE = 50;

    public const EXPORT_MAX_ROWS = 10000;

    public function __construct(
        protected InventoryFilterService $filters,
        protected InventoryReportService $hierarchy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function filterOptions(): array
    {
        return [
            'campuses' => Campus::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'pics' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
            'procurementTypes' => ProcurementType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'statuses' => InventoryStatus::cases(),
            'conditions' => InventoryCondition::cases(),
            'subCategories' => SubCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'category_id', 'name', 'code']),
        ];
    }

    public function inventoryQuery(Request $request, InventoryReportType $type): Builder
    {
        $query = $this->filters->apply($request, InventoryItem::query());

        return match ($type) {
            InventoryReportType::Borrowed => $query->where(function (Builder $q) {
                $q->where('status', InventoryStatus::Dipinjam)
                    ->orWhereHas('borrowings', fn (Builder $b) => $b->whereIn('status', [
                        \App\Enums\BorrowingStatus::Dipinjam,
                        \App\Enums\BorrowingStatus::Disetujui,
                        \App\Enums\BorrowingStatus::Terlambat,
                    ]));
            }),
            InventoryReportType::Maintenance => $query->whereIn('status', [
                InventoryStatus::Maintenance->value,
                InventoryStatus::MenungguApproval->value,
                InventoryStatus::RevisiPerbaikan->value,
            ]),
            InventoryReportType::Damaged => $query->where(function (Builder $q) {
                $q->whereIn('status', InventoryStatus::damagedValues())
                    ->orWhereIn('condition', [
                        InventoryCondition::RusakRingan->value,
                        InventoryCondition::RusakBerat->value,
                    ]);
            }),
            InventoryReportType::Inactive => $query->whereIn('status', array_merge(
                InventoryStatus::terminalValues(),
                [
                    InventoryStatus::TidakDapatDiperbaiki->value,
                    InventoryStatus::PerluPenggantian->value,
                ]
            )),
            InventoryReportType::Deleted => $this->filters->apply(
                Request::create('/', 'GET', array_merge(
                    $request->query(),
                    $request->request->all(),
                    ['include_trash' => false, 'only_trash' => false]
                )),
                InventoryItem::onlyTrashed()
            ),
            InventoryReportType::Mutated => $query->whereHas('mutations'),
            default => $query,
        };
    }

    /**
     * Eager-loaded inventory base for list/export.
     */
    public function inventoryWithRelations(Request $request, InventoryReportType $type): Builder
    {
        return $this->inventoryQuery($request, $type)->with([
            'category:id,code,name',
            'subCategory:id,category_id,code,name',
            'room:id,floor_id,code,name',
            'room.floor:id,building_id,code,name,floor_number',
            'room.floor.building:id,campus_id,code,name',
            'room.floor.building.campus:id,code,name',
            'pic:id,name',
            'supplier:id,name',
            'vendor:id,name',
            'procurementType:id,name,code',
        ]);
    }

    public function paginateInventory(Request $request, InventoryReportType $type): LengthAwarePaginator
    {
        $perPage = max(10, min(100, (int) $request->input('per_page', self::PREVIEW_PER_PAGE)));

        return $this->inventoryWithRelations($request, $type)
            ->orderBy('inventory_number')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Collection<int, InventoryItem>
     */
    public function exportInventoryItems(Request $request, InventoryReportType $type): Collection
    {
        return $this->inventoryWithRelations($request, $type)
            ->orderBy('inventory_number')
            ->limit(self::EXPORT_MAX_ROWS)
            ->get();
    }

    /**
     * @return array{tree: list<array<string, mixed>>, summary: array<string, mixed>, filters: array<string, string>, meta: array<string, mixed>, items: Collection}
     */
    public function buildInventoryDocument(Request $request, InventoryReportType $type, string $orientation = 'portrait'): array
    {
        $items = $this->exportInventoryItems($request, $type);
        $tree = $this->hierarchy->buildTree($items);
        $summary = $this->summarizeCollection($items);
        $meta = $this->meta($request, $type, $orientation);

        return [
            'items' => $items,
            'tree' => $tree,
            'summary' => $summary,
            'filters' => $this->hierarchy->describeFilters($request),
            'meta' => $meta,
            'type' => $type,
        ];
    }

    public function borrowingQuery(Request $request): Builder
    {
        return Borrowing::query()
            ->with([
                'inventoryItem:id,inventory_number,name,room_id,category_id,pic_id,status,condition,brand,serial_number',
                'inventoryItem.room:id,floor_id,code,name',
                'inventoryItem.room.floor:id,building_id,code,name',
                'inventoryItem.room.floor.building:id,campus_id,code,name',
                'inventoryItem.room.floor.building.campus:id,code,name',
                'inventoryItem.category:id,name',
                'approvedBy:id,name',
            ])
            ->whereHas('inventoryItem', fn (Builder $q) => $this->filters->apply($request, $q))
            ->when($request->filled('tanggal_awal') || $request->filled('date_from') || $request->filled('purchase_date_from'), function (Builder $q) use ($request) {
                $from = $request->input('tanggal_awal') ?? $request->input('date_from') ?? $request->input('purchase_date_from');
                $q->whereDate('borrow_date', '>=', $from);
            })
            ->when($request->filled('tanggal_akhir') || $request->filled('date_to') || $request->filled('purchase_date_to'), function (Builder $q) use ($request) {
                $to = $request->input('tanggal_akhir') ?? $request->input('date_to') ?? $request->input('purchase_date_to');
                $q->whereDate('borrow_date', '<=', $to);
            })
            ->when($request->filled('keyword') || $request->filled('search'), function (Builder $q) use ($request) {
                $term = (string) ($request->input('keyword') ?? $request->input('search'));
                $q->where(function (Builder $inner) use ($term) {
                    $inner->where('borrower_name', 'like', "%{$term}%")
                        ->orWhereHas('inventoryItem', fn (Builder $i) => $i->where('name', 'like', "%{$term}%")
                            ->orWhere('inventory_number', 'like', "%{$term}%"));
                });
            })
            ->latest('borrow_date');
    }

    public function maintenanceQuery(Request $request): Builder
    {
        return Maintenance::query()
            ->with([
                'inventoryItem:id,inventory_number,name,room_id,category_id,status,condition',
                'inventoryItem.room:id,floor_id,code,name',
                'inventoryItem.room.floor:id,building_id,code,name',
                'inventoryItem.room.floor.building:id,campus_id,code,name',
                'inventoryItem.room.floor.building.campus:id,code,name',
                'technicianUser:id,name',
            ])
            ->whereHas('inventoryItem', fn (Builder $q) => $this->filters->apply($request, $q))
            ->when($request->filled('tanggal_awal') || $request->filled('date_from') || $request->filled('purchase_date_from'), function (Builder $q) use ($request) {
                $from = $request->input('tanggal_awal') ?? $request->input('date_from') ?? $request->input('purchase_date_from');
                $q->whereDate('service_date', '>=', $from);
            })
            ->when($request->filled('tanggal_akhir') || $request->filled('date_to') || $request->filled('purchase_date_to'), function (Builder $q) use ($request) {
                $to = $request->input('tanggal_akhir') ?? $request->input('date_to') ?? $request->input('purchase_date_to');
                $q->whereDate('service_date', '<=', $to);
            })
            ->when($request->filled('keyword') || $request->filled('search'), function (Builder $q) use ($request) {
                $term = (string) ($request->input('keyword') ?? $request->input('search'));
                $q->where(function (Builder $inner) use ($term) {
                    $inner->where('maintenance_number', 'like', "%{$term}%")
                        ->orWhere('technician', 'like', "%{$term}%")
                        ->orWhereHas('inventoryItem', fn (Builder $i) => $i->where('name', 'like', "%{$term}%")
                            ->orWhere('inventory_number', 'like', "%{$term}%"));
                });
            })
            ->latest('service_date');
    }

    public function mutationQuery(Request $request): Builder
    {
        $roomEager = fn ($q) => $q->select('id', 'floor_id', 'code', 'name')
            ->with(['floor:id,building_id,code,name', 'floor.building:id,campus_id,code,name', 'floor.building.campus:id,code,name']);

        return InventoryMutation::query()
            ->with([
                'inventoryItem:id,inventory_number,name,pic_id,room_id',
                'inventoryItem.pic:id,name',
                'fromRoom' => $roomEager,
                'toRoom' => $roomEager,
                'movedBy:id,name',
            ])
            ->whereHas('inventoryItem', fn (Builder $q) => $this->filters->apply($request, $q))
            ->when($request->filled('tanggal_awal') || $request->filled('date_from') || $request->filled('purchase_date_from'), function (Builder $q) use ($request) {
                $from = $request->input('tanggal_awal') ?? $request->input('date_from') ?? $request->input('purchase_date_from');
                $q->whereDate('created_at', '>=', $from);
            })
            ->when($request->filled('tanggal_akhir') || $request->filled('date_to') || $request->filled('purchase_date_to'), function (Builder $q) use ($request) {
                $to = $request->input('tanggal_akhir') ?? $request->input('date_to') ?? $request->input('purchase_date_to');
                $q->whereDate('created_at', '<=', $to);
            })
            ->latest('id');
    }

    /**
     * KPI cards + chart series for dashboard / rekapitulasi (SQL aggregates).
     *
     * @return array<string, mixed>
     */
    public function dashboard(Request $request): array
    {
        $base = $this->filters->apply($request, InventoryItem::query());

        $total = (clone $base)->count();
        $aktif = (clone $base)->where('status', InventoryStatus::Aktif)->count();
        $dipinjam = (clone $base)->where('status', InventoryStatus::Dipinjam)->count();
        $rusak = (clone $base)->where(function (Builder $q) {
            $q->whereIn('status', InventoryStatus::damagedValues())
                ->orWhereIn('condition', [
                    InventoryCondition::RusakRingan->value,
                    InventoryCondition::RusakBerat->value,
                ]);
        })->count();
        $maintenance = (clone $base)->whereIn('status', [
            InventoryStatus::Maintenance->value,
            InventoryStatus::MenungguApproval->value,
            InventoryStatus::RevisiPerbaikan->value,
        ])->count();
        $nonAktif = (clone $base)->whereIn('status', array_merge(
            InventoryStatus::terminalValues(),
            [
                InventoryStatus::TidakDapatDiperbaiki->value,
                InventoryStatus::PerluPenggantian->value,
            ]
        ))->count();

        $byCampus = (clone $base)
            ->join('rooms', 'rooms.id', '=', 'inventory_items.room_id')
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->join('buildings', 'buildings.id', '=', 'floors.building_id')
            ->join('campuses', 'campuses.id', '=', 'buildings.campus_id')
            ->groupBy('campuses.id', 'campuses.name')
            ->orderBy('campuses.name')
            ->select('campuses.name as label', DB::raw('COUNT(*) as total'))
            ->limit(20)
            ->get();

        $byCategory = (clone $base)
            ->leftJoin('categories', 'categories.id', '=', 'inventory_items.category_id')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->select(DB::raw("COALESCE(categories.name, 'Tanpa Kategori') as label"), DB::raw('COUNT(*) as total'))
            ->limit(15)
            ->get();

        $byCondition = (clone $base)
            ->groupBy('condition')
            ->select('condition as label', DB::raw('COUNT(*) as total'))
            ->get()
            ->map(fn ($row) => [
                'label' => InventoryCondition::tryFrom((string) $row->label)?->label() ?? ($row->label ?: '—'),
                'total' => (int) $row->total,
            ]);

        $byYear = (clone $base)
            ->whereNotNull('purchase_year')
            ->groupBy('purchase_year')
            ->orderBy('purchase_year')
            ->select('purchase_year as label', DB::raw('COUNT(*) as total'))
            ->limit(20)
            ->get();

        $byProcurement = (clone $base)
            ->leftJoin('procurement_types', 'procurement_types.id', '=', 'inventory_items.procurement_type_id')
            ->groupBy('procurement_types.id', 'procurement_types.name')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->select(DB::raw("COALESCE(procurement_types.name, 'Tidak ditentukan') as label"), DB::raw('COUNT(*) as total'))
            ->limit(15)
            ->get();

        return [
            'cards' => [
                'total' => $total,
                'aktif' => $aktif,
                'dipinjam' => $dipinjam,
                'rusak' => $rusak,
                'maintenance' => $maintenance,
                'non_aktif' => $nonAktif,
            ],
            'charts' => [
                'campus' => $byCampus,
                'category' => $byCategory,
                'condition' => $byCondition,
                'year' => $byYear,
                'procurement' => $byProcurement,
            ],
            'filters' => $this->hierarchy->describeFilters($request),
            'meta' => $this->meta($request, InventoryReportType::Recap),
            'menu' => InventoryReportType::menuTypes(),
        ];
    }

    /**
     * @param  Collection<int, InventoryItem>  $items
     * @return array<string, mixed>
     */
    public function summarizeCollection(Collection $items): array
    {
        $summary = $this->hierarchy->buildSummary($items);
        $summary['non_aktif'] = $items->filter(function (InventoryItem $i) {
            $status = $i->status;

            return $status instanceof InventoryStatus && (
                $status->isTerminal()
                || in_array($status, [InventoryStatus::TidakDapatDiperbaiki, InventoryStatus::PerluPenggantian], true)
            );
        })->count();

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(Request $request, InventoryReportType $type, string $orientation = 'portrait'): array
    {
        $now = now();

        return [
            'printed_at' => $now->translatedFormat('d F Y H:i'),
            'printed_date' => $now->translatedFormat('d F Y'),
            'printed_time' => $now->format('H:i'),
            'user_name' => $request->user()?->name ?? 'Sistem',
            'title' => $type->label(),
            'report_title' => 'Laporan Inventaris',
            'organization' => 'Universitas Dian Nusantara',
            'system' => 'Sistem Inventaris',
            'orientation' => $orientation === 'landscape' ? 'landscape' : 'portrait',
            'type' => $type->value,
        ];
    }

    public function qrDataUri(?InventoryItem $item): ?string
    {
        if (! $item?->qr_code_path) {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($item->qr_code_path)) {
            return null;
        }

        $raw = $disk->get($item->qr_code_path);
        $ext = strtolower(pathinfo($item->qr_code_path, PATHINFO_EXTENSION));

        return match ($ext) {
            'svg' => 'data:image/svg+xml;base64,'.base64_encode($raw),
            'png' => 'data:image/png;base64,'.base64_encode($raw),
            'jpg', 'jpeg' => 'data:image/jpeg;base64,'.base64_encode($raw),
            default => null,
        };
    }

    /**
     * Flat row for DataTables / Excel (inventory family).
     *
     * @return array<string, mixed>
     */
    public function mapInventoryRow(InventoryItem $item, int $no, bool $withQr = true): array
    {
        return [
            'no' => str_pad((string) $no, 3, '0', STR_PAD_LEFT),
            'qr' => $withQr ? $this->qrDataUri($item) : null,
            'inventory_number' => $item->inventory_number,
            'name' => $item->name,
            'category' => $item->category?->name ?? '—',
            'sub_category' => $item->subCategory?->name ?? '—',
            'brand' => $item->brand ?: '—',
            'serial_number' => $item->serial_number ?: '—',
            'location' => $item->locationDisplayName(),
            'pic' => $item->picDisplayName() ?: '—',
            'status' => $item->status?->label() ?? '—',
            'condition' => $item->condition?->label() ?? '—',
            'purchase_year' => $item->purchase_year ? (string) $item->purchase_year : '-',
            'purchase_date' => $item->purchase_date?->format('d/m/Y') ?? '—',
            'id' => $item->id,
        ];
    }
}
