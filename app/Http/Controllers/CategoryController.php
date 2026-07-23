<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryConditionMaster;
use App\Models\InventoryMasterConfiguration;
use App\Models\InventorySequence;
use App\Models\OrgUnit;
use App\Models\ProcurementType;
use App\Models\PurchaseYear;
use App\Models\Room;
use App\Models\RoomFunction;
use App\Models\Setting;
use App\Enums\AuditAction;
use App\Exports\MasterArrayExport;
use App\Http\Requests\ImportMasterRequest;
use App\Services\AuditLogService;
use App\Services\InventoryCodeService;
use App\Services\TrashCascadeService;
use App\Support\MasterCsvReader;
use App\Support\MasterListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CategoryController extends Controller
{
    public function __construct(
        protected TrashCascadeService $trash,
        protected AuditLogService $audit,
    ) {}
    public function index(Request $request): View
    {
        $tab = $request->input('tab', 'konfigurasi');
        $allowed = ['konfigurasi', 'klasifikasi', 'units', 'pengadaan', 'lokasi'];
        if (! in_array($tab, $allowed, true)) {
            $tab = 'konfigurasi';
        }

        $categories = null;
        $orgUnits = null;
        $procurementTypes = null;
        $configurations = null;
        $configLookups = [];
        $lokasiHub = null;
        $purchaseYears = null;
        $roomFunctions = null;
        $inventoryConditions = null;

        if ($tab === 'klasifikasi') {
            $categoriesQuery = Category::query()->withCount('subCategories', 'inventoryItems');
            MasterListQuery::apply(
                $categoriesQuery,
                $request,
                ['code', 'name', 'slug'],
                ['name' => 'name', 'code' => 'code', 'created_at' => 'created_at'],
            );
            $categories = $categoriesQuery
                ->paginate(20, ['*'], 'categories_page')
                ->withQueryString();
        }

        if ($tab === 'units') {
            $orgUnitsQuery = OrgUnit::query()->withCount('inventoryItems');
            MasterListQuery::apply(
                $orgUnitsQuery,
                $request,
                ['code', 'name'],
                ['name' => 'name', 'code' => 'code', 'created_at' => 'created_at'],
            );
            $orgUnits = $orgUnitsQuery
                ->paginate(20, ['*'], 'units_page')
                ->withQueryString();
        }

        if ($tab === 'pengadaan') {
            $procurementQuery = ProcurementType::query()->withCount('inventoryItems');
            MasterListQuery::apply(
                $procurementQuery,
                $request,
                ['code', 'name'],
                ['name' => 'name', 'code' => 'code', 'created_at' => 'created_at'],
            );
            $procurementTypes = $procurementQuery
                ->paginate(20, ['*'], 'procurement_page')
                ->withQueryString();
        }

        if ($tab === 'konfigurasi') {
            $configurations = $this->configurationsQuery($request)
                ->paginate(20, ['*'], 'config_page')
                ->withQueryString();

            $this->attachSequencePreviews($configurations);

            $configLookups = [
                'campuses' => Campus::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
                'orgUnits' => OrgUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
                'roomFunctions' => RoomFunction::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'code', 'name']),
                'procurementTypes' => ProcurementType::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
                'purchaseYears' => PurchaseYear::query()->where('is_active', true)->orderByDesc('year')->get(['id', 'year']),
                'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
                'conditions' => InventoryConditionMaster::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'code', 'name']),
            ];
        }

        if ($tab === 'lokasi') {
            $lokasiHub = [
                'campuses_total' => Campus::query()->count(),
                'campuses_active' => Campus::query()->where('is_active', true)->count(),
                'buildings_total' => Building::query()->count(),
                'buildings_active' => Building::query()->where('is_active', true)->count(),
                'floors_total' => Floor::query()->count(),
                'floors_active' => Floor::query()->where('is_active', true)->count(),
                'rooms_total' => Room::query()->count(),
                'rooms_active' => Room::query()->where('is_active', true)->count(),
                'room_functions_total' => RoomFunction::query()->count(),
                'room_functions_active' => RoomFunction::query()->where('is_active', true)->count(),
                'purchase_years_total' => PurchaseYear::query()->count(),
                'purchase_years_active' => PurchaseYear::query()->where('is_active', true)->count(),
                'conditions_total' => InventoryConditionMaster::query()->count(),
                'conditions_active' => InventoryConditionMaster::query()->where('is_active', true)->count(),
            ];

            $purchaseYears = PurchaseYear::query()->orderByDesc('year')->paginate(15, ['*'], 'years_page')->withQueryString();
            $roomFunctions = RoomFunction::query()->orderBy('sort_order')->orderBy('name')->paginate(15, ['*'], 'functions_page')->withQueryString();
            $inventoryConditions = InventoryConditionMaster::query()->orderBy('sort_order')->orderBy('name')->paginate(15, ['*'], 'conditions_page')->withQueryString();
        }

        return view('master.categories.index', compact(
            'tab',
            'categories',
            'orgUnits',
            'procurementTypes',
            'configurations',
            'configLookups',
            'lokasiHub',
            'purchaseYears',
            'roomFunctions',
            'inventoryConditions',
        ));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $query = Category::query();
        MasterListQuery::apply(
            $query,
            $request,
            ['code', 'name', 'slug'],
            ['name' => 'name', 'code' => 'code', 'created_at' => 'created_at'],
        );

        $rows = $query->get()->map(fn (Category $c) => [
            $c->code,
            $c->name,
            $c->slug,
            $c->description,
            $c->is_active ? 1 : 0,
        ]);

        $this->audit->log($request->user(), AuditAction::Export, null, null, [
            'entity' => 'category',
            'format' => 'csv',
            'count' => $rows->count(),
        ], 'CATEGORY_EXPORTED');

        return Excel::download(
            new MasterArrayExport(
                ['code', 'name', 'slug', 'description', 'is_active'],
                $rows,
            ),
            'categories_'.now()->format('Ymd_His').'.csv',
            \Maatwebsite\Excel\Excel::CSV,
        );
    }

    public function importTemplate(): BinaryFileResponse
    {
        return Excel::download(
            new MasterArrayExport(
                ['code', 'name', 'slug', 'description', 'is_active'],
                [['FUR', 'Furniture', 'furniture', 'Contoh klasifikasi', 1]],
            ),
            'template_categories.csv',
            \Maatwebsite\Excel\Excel::CSV,
        );
    }

    public function import(ImportMasterRequest $request): RedirectResponse
    {
        $rows = MasterCsvReader::associativeRows($request->file('file'));
        if ($rows === []) {
            return back()->with('error', 'Berkas import kosong atau tidak valid.');
        }

        $imported = 0;

        DB::transaction(function () use ($rows, &$imported) {
            foreach ($rows as $data) {
                if (blank($data['code'] ?? null) || blank($data['name'] ?? null)) {
                    continue;
                }

                $name = (string) $data['name'];
                $slug = filled($data['slug'] ?? null) ? (string) $data['slug'] : Str::slug($name);

                Category::query()->updateOrCreate(
                    ['code' => $data['code']],
                    [
                        'name' => $name,
                        'slug' => $slug,
                        'description' => $data['description'] ?? null,
                        'is_active' => MasterCsvReader::parseActive($data['is_active'] ?? null),
                    ],
                );
                $imported++;
            }
        });

        $this->audit->log($request->user(), AuditAction::Import, null, null, [
            'entity' => 'category',
            'imported' => $imported,
        ], 'CATEGORY_IMPORTED');

        return redirect()
            ->route('categories.index', ['tab' => 'klasifikasi'])
            ->with('success', "Import klasifikasi selesai. {$imported} baris diproses.");
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $category = Category::query()->create([
            ...$request->validated(),
            'slug' => $request->input('slug') ?: Str::slug($request->input('name')),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->audit->log(
            $request->user(),
            AuditAction::Create,
            $category,
            null,
            $category->only(['code', 'name', 'slug', 'is_active']),
            'CATEGORY_CREATED',
        );

        return redirect()
            ->route('categories.index', ['tab' => 'klasifikasi'])
            ->with('success', 'Klasifikasi berhasil ditambahkan.');
    }

    public function update(StoreCategoryRequest $request, Category $category): RedirectResponse
    {
        $old = $category->only(['code', 'name', 'slug', 'is_active']);

        $category->update([
            ...$request->validated(),
            'slug' => $request->input('slug') ?: Str::slug($request->input('name')),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->audit->log(
            $request->user(),
            AuditAction::Update,
            $category,
            $old,
            $category->only(['code', 'name', 'slug', 'is_active']),
            'CATEGORY_UPDATED',
        );

        return redirect()
            ->route('categories.index', ['tab' => 'klasifikasi'])
            ->with('success', 'Klasifikasi berhasil diperbarui.');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $this->trash->softDelete($category, $request->user());

        return redirect()
            ->route('categories.index', ['tab' => 'klasifikasi'])
            ->with('success', 'Klasifikasi dipindahkan ke Trash (sub kategori ikut).');
    }

    protected function configurationsQuery(Request $request)
    {
        $query = InventoryMasterConfiguration::query()
            ->with([
                'campus:id,code,name',
                'building:id,code,name',
                'floor:id,code,name,floor_number',
                'orgUnit:id,code,name',
                'roomFunction:id,code,name',
                'room:id,code,name',
                'procurementType:id,code,name',
                'purchaseYear:id,year',
                'category:id,code,name',
                'subCategory:id,code,name',
                'inventoryCondition:id,code,name',
            ]);

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('generated_location_code', 'like', "%{$search}%")
                    ->orWhereHas('room', fn ($r) => $r->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                    ->orWhereHas('campus', fn ($c) => $c->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                    ->orWhereHas('subCategory', fn ($s) => $s->where('name', 'like', "%{$search}%"));
            });
        }

        foreach (['campus_id', 'building_id', 'floor_id', 'org_unit_id', 'category_id', 'sub_category_id', 'inventory_condition_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->integer($filter));
            }
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $sort = $request->input('sort', 'id');
        $dir = $request->input('dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['id', 'generated_location_code', 'created_at', 'is_active'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'id';
        }

        return $query->orderBy($sort, $dir);
    }

    /**
     * Attach last/next sequence without N+1 allocate (read-only peek, batched).
     *
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, InventoryMasterConfiguration>  $configurations
     */
    protected function attachSequencePreviews($configurations): void
    {
        $padding = (int) Setting::get('inventory.sequence_padding', InventoryCodeService::DEFAULT_PADDING);
        if ($padding < 1) {
            $padding = InventoryCodeService::DEFAULT_PADDING;
        }

        $pairs = $configurations->getCollection()
            ->map(fn (InventoryMasterConfiguration $c) => [$c->campus_id, $c->sub_category_id])
            ->unique(fn ($p) => $p[0].':'.$p[1])
            ->values();

        $seqMap = [];
        foreach ($pairs as [$campusId, $subId]) {
            $row = InventorySequence::query()
                ->where('campus_id', $campusId)
                ->where('classifier_type', 'sub_category')
                ->where('classifier_id', $subId)
                ->first();
            $last = $row ? (int) $row->last_value : 0;
            $seqMap[$campusId.':'.$subId] = $last;
        }

        $configurations->getCollection()->transform(function (InventoryMasterConfiguration $c) use ($seqMap, $padding) {
            $last = $seqMap[$c->campus_id.':'.$c->sub_category_id] ?? 0;
            $next = $last + 1;
            $c->setAttribute('sequence_last', $last);
            $c->setAttribute('sequence_next', $next);
            $c->setAttribute('sequence_next_padded', str_pad((string) $next, $padding, '0', STR_PAD_LEFT));
            $c->setAttribute('sequence_last_padded', str_pad((string) $last, $padding, '0', STR_PAD_LEFT));

            return $c;
        });
    }
}
