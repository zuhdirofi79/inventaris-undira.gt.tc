<?php

namespace App\Http\Controllers;

use App\Models\Borrowing;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\InventoryMasterConfiguration;
use App\Models\OrgUnit;
use App\Models\ProcurementType;
use App\Models\Room;
use App\Models\RoomBorrowing;
use App\Models\SubCategory;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VehicleBorrowing;
use App\Models\Vendor;
use App\Services\TrashCascadeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use InvalidArgumentException;

class TrashController extends Controller
{
    /**
     * @var array<string, class-string<Model>>
     */
    public const TABS = [
        'kampus' => Campus::class,
        'gedung' => Building::class,
        'lantai' => Floor::class,
        'ruangan' => Room::class,
        'inventaris' => InventoryItem::class,
        'konfigurasi' => InventoryMasterConfiguration::class,
        'kategori' => Category::class,
        'sub_kategori' => SubCategory::class,
        'supplier' => Supplier::class,
        'vendor' => Vendor::class,
        'unit' => OrgUnit::class,
        'pengadaan' => ProcurementType::class,
        'riwayat_peminjaman' => Borrowing::class,
    ];

    /**
     * Restorable/force-deletable borrowing history types (distinct from the combined tab).
     *
     * @var array<string, class-string<Model>>
     */
    public const BORROWING_TYPES = [
        'peminjaman_inventaris' => Borrowing::class,
        'peminjaman_ruangan' => RoomBorrowing::class,
        'peminjaman_kendaraan' => VehicleBorrowing::class,
    ];

    /**
     * @var array<string, string>
     */
    public const TAB_LABELS = [
        'kampus' => 'Kampus',
        'gedung' => 'Gedung',
        'lantai' => 'Lantai',
        'ruangan' => 'Ruangan',
        'inventaris' => 'Inventaris',
        'konfigurasi' => 'Konfigurasi',
        'kategori' => 'Kategori',
        'sub_kategori' => 'Sub Kategori',
        'supplier' => 'Supplier',
        'vendor' => 'Vendor',
        'unit' => 'Unit Kerja',
        'pengadaan' => 'Jenis Pengadaan',
        'riwayat_peminjaman' => 'Riwayat Peminjaman',
    ];

    public function __construct(
        protected TrashCascadeService $trash,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('trash.view'), 403);

        $tab = $request->input('tab', 'kampus');
        if (! array_key_exists($tab, self::TAB_LABELS)) {
            $tab = 'kampus';
        }

        if ($tab === 'riwayat_peminjaman') {
            $items = $this->paginateBorrowingTrash($request);
        } else {
            $modelClass = self::TABS[$tab];
            $query = $modelClass::onlyTrashed()->latest('deleted_at');
            $this->eagerLoad($query, $tab);
            $this->applyFilters($query, $request, $tab);
            $items = $query->paginate(20)->withQueryString();
        }

        $counts = [];
        foreach (self::TAB_LABELS as $key => $label) {
            $counts[$key] = $key === 'riwayat_peminjaman'
                ? $this->borrowingTrashCount()
                : self::TABS[$key]::onlyTrashed()->count();
        }

        return view('admin.trash.index', [
            'tab' => $tab,
            'items' => $items,
            'counts' => $counts,
            'tabLabels' => self::TAB_LABELS,
            'deleters' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function restore(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless($request->user()?->can('trash.restore'), 403);

        $model = $this->findTrashed($type, $id);
        $this->trash->restore($model, $request->user());

        return back()->with('success', 'Data berhasil dipulihkan dari Trash (beserta relasi turunannya).');
    }

    public function forceDelete(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless($request->user()?->can('trash.force-delete'), 403);

        $model = $this->findTrashed($type, $id);

        try {
            $this->trash->forceDelete($model, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Data dihapus permanen dari sistem.');
    }

    protected function findTrashed(string $type, int $id): Model
    {
        if (array_key_exists($type, self::BORROWING_TYPES)) {
            return self::BORROWING_TYPES[$type]::onlyTrashed()->findOrFail($id);
        }

        if (! array_key_exists($type, self::TABS) || $type === 'riwayat_peminjaman') {
            abort(404);
        }

        return self::TABS[$type]::onlyTrashed()->findOrFail($id);
    }

    protected function eagerLoad(Builder $query, string $tab): void
    {
        match ($tab) {
            'inventaris' => $query->with(['category', 'room.floor.building.campus', 'deletedByUser']),
            'konfigurasi' => $query->with(['campus', 'building', 'floor', 'room', 'category', 'deletedByUser']),
            'gedung' => $query->with(['campus', 'deletedByUser']),
            'lantai' => $query->with(['building.campus', 'deletedByUser']),
            'ruangan' => $query->with(['floor.building.campus', 'deletedByUser']),
            'sub_kategori' => $query->with(['category', 'deletedByUser']),
            default => $query->with(['deletedByUser']),
        };
    }

    protected function applyFilters(Builder $query, Request $request, string $tab): void
    {
        if ($search = trim((string) $request->input('q'))) {
            $query->where(function (Builder $q) use ($search, $tab) {
                if ($tab === 'konfigurasi') {
                    $q->where('generated_location_code', 'like', "%{$search}%");

                    return;
                }

                if ($tab === 'inventaris') {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('inventory_number', 'like', "%{$search}%");

                    return;
                }

                $q->where('name', 'like', "%{$search}%");
                if (in_array($tab, ['kampus', 'gedung', 'lantai', 'ruangan', 'kategori', 'sub_kategori', 'unit', 'pengadaan'], true)) {
                    $q->orWhere('code', 'like', "%{$search}%");
                }
            });
        }

        if ($request->filled('deleted_by')) {
            $query->where('deleted_by', $request->integer('deleted_by'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('deleted_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('deleted_at', '<=', $request->input('date_to'));
        }
    }

    protected function borrowingTrashCount(): int
    {
        return Borrowing::onlyTrashed()->count()
            + RoomBorrowing::onlyTrashed()->count()
            + VehicleBorrowing::onlyTrashed()->count();
    }

    protected function paginateBorrowingTrash(Request $request): LengthAwarePaginator
    {
        $search = trim((string) $request->input('q'));
        $deletedBy = $request->filled('deleted_by') ? $request->integer('deleted_by') : null;
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $rows = collect()
            ->concat($this->mapTrashedBorrowings(Borrowing::onlyTrashed()->with(['deletedByUser', 'inventoryItem:id,name,inventory_number', 'items.inventoryItem:id,name']), 'peminjaman_inventaris', 'Peminjaman Inventaris', $search, $deletedBy, $dateFrom, $dateTo))
            ->concat($this->mapTrashedBorrowings(RoomBorrowing::onlyTrashed()->with(['deletedByUser', 'campus:id,name', 'room:id,name,code']), 'peminjaman_ruangan', 'Peminjaman Ruangan', $search, $deletedBy, $dateFrom, $dateTo))
            ->concat($this->mapTrashedBorrowings(VehicleBorrowing::onlyTrashed()->with(['deletedByUser', 'campus:id,name', 'vehicle:id,name,plate_number']), 'peminjaman_kendaraan', 'Peminjaman Kendaraan Operasional', $search, $deletedBy, $dateFrom, $dateTo))
            ->sortByDesc(fn (array $row) => $row['deleted_at']?->timestamp ?? 0)
            ->values();

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 20;
        $slice = $rows->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $slice,
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Collection<int, array<string, mixed>>
     */
    protected function mapTrashedBorrowings(
        Builder $query,
        string $trashType,
        string $kindLabel,
        string $search,
        ?int $deletedBy,
        mixed $dateFrom,
        mixed $dateTo,
    ): Collection {
        $query
            ->when($deletedBy, fn (Builder $q) => $q->where('deleted_by', $deletedBy))
            ->when(filled($dateFrom), fn (Builder $q) => $q->whereDate('deleted_at', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $q) => $q->whereDate('deleted_at', '<=', $dateTo))
            ->when($search !== '', function (Builder $q) use ($search, $trashType) {
                $like = '%'.$search.'%';
                $q->where(function (Builder $inner) use ($like, $trashType) {
                    $inner->where('borrower_name', 'like', $like)
                        ->orWhere('approval_number', 'like', $like);

                    if ($trashType === 'peminjaman_inventaris') {
                        $inner->orWhereHas('inventoryItem', fn (Builder $i) => $i->where('name', 'like', $like)->orWhere('inventory_number', 'like', $like))
                            ->orWhereHas('items.inventoryItem', fn (Builder $i) => $i->where('name', 'like', $like)->orWhere('inventory_number', 'like', $like));
                    }
                    if ($trashType === 'peminjaman_ruangan') {
                        $inner->orWhere('purpose', 'like', $like)
                            ->orWhereHas('room', fn (Builder $r) => $r->where('name', 'like', $like)->orWhere('code', 'like', $like));
                    }
                    if ($trashType === 'peminjaman_kendaraan') {
                        $inner->orWhere('destination', 'like', $like)
                            ->orWhere('purpose', 'like', $like)
                            ->orWhereHas('vehicle', fn (Builder $v) => $v->where('name', 'like', $like)->orWhere('plate_number', 'like', $like));
                    }
                });
            })
            ->latest('deleted_at');

        return $query->get()->map(function (Model $model) use ($trashType, $kindLabel) {
            $subject = match ($trashType) {
                'peminjaman_inventaris' => $this->inventorySubject($model),
                'peminjaman_ruangan' => trim((string) ($model->purpose ?: ($model->room?->name ?? 'Ruangan'))) ?: 'Ruangan',
                'peminjaman_kendaraan' => trim((string) ($model->destination ?: ($model->vehicle?->label() ?? 'Kendaraan'))) ?: 'Kendaraan',
                default => '#'.$model->getKey(),
            };

            $campus = match ($trashType) {
                'peminjaman_inventaris' => method_exists($model, 'campusSummary') ? $model->campusSummary() : '—',
                default => $model->campus?->name ?? '—',
            };

            $status = match ($trashType) {
                'peminjaman_inventaris' => $model->status?->label() ?? '—',
                default => method_exists($model, 'displayLabel') ? $model->displayLabel() : '—',
            };

            return [
                'trash_type' => $trashType,
                'id' => $model->getKey(),
                'kind_label' => $kindLabel,
                'number' => $model->approval_number ?: ('#'.$model->getKey()),
                'borrower' => $model->borrower_name,
                'subject' => $subject,
                'campus' => $campus,
                'status' => $status,
                'deleted_at' => $model->deleted_at,
                'deleted_by' => $model->deletedByUser?->name ?? '—',
            ];
        });
    }

    protected function inventorySubject(Model $model): string
    {
        /** @var Borrowing $model */
        $item = $model->relationLoaded('items')
            ? ($model->items->first()?->inventoryItem ?? $model->inventoryItem)
            : $model->inventoryItem;
        $count = $model->relationLoaded('items') ? $model->items->count() : 0;
        $name = $item?->name ?: 'Inventaris';

        return $count > 1 ? $name.' +'.($count - 1).' barang lain' : $name;
    }
}
