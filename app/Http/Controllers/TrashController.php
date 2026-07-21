<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\InventoryMasterConfiguration;
use App\Models\OrgUnit;
use App\Models\ProcurementType;
use App\Models\Room;
use App\Models\SubCategory;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vendor;
use App\Services\TrashCascadeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    ];

    public function __construct(
        protected TrashCascadeService $trash,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        $tab = $request->input('tab', 'kampus');
        if (! array_key_exists($tab, self::TABS)) {
            $tab = 'kampus';
        }

        $modelClass = self::TABS[$tab];
        $query = $modelClass::onlyTrashed()->latest('deleted_at');

        $this->eagerLoad($query, $tab);
        $this->applyFilters($query, $request, $tab);

        $items = $query->paginate(20)->withQueryString();

        $counts = [];
        foreach (self::TABS as $key => $class) {
            $counts[$key] = $class::onlyTrashed()->count();
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
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        $model = $this->findTrashed($type, $id);
        $this->trash->restore($model, $request->user());

        return back()->with('success', 'Data berhasil dipulihkan dari Trash (beserta relasi turunannya).');
    }

    public function forceDelete(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

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
        if (! array_key_exists($type, self::TABS)) {
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
}
