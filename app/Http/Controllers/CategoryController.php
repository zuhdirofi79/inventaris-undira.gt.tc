<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Models\Campus;
use App\Models\Category;
use App\Models\InventoryConditionMaster;
use App\Models\InventoryMasterConfiguration;
use App\Models\InventorySequence;
use App\Models\OrgUnit;
use App\Models\ProcurementType;
use App\Models\PurchaseYear;
use App\Models\RoomFunction;
use App\Models\Setting;
use App\Services\InventoryCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->input('tab', 'konfigurasi');
        $allowed = ['konfigurasi', 'klasifikasi', 'units', 'pengadaan', 'lokasi'];
        if (! in_array($tab, $allowed, true)) {
            $tab = 'konfigurasi';
        }

        $categories = Category::query()
            ->withCount('subCategories', 'inventoryItems')
            ->orderBy('name')
            ->paginate(20, ['*'], 'categories_page')
            ->withQueryString();

        $orgUnits = OrgUnit::query()
            ->withCount('inventoryItems')
            ->orderBy('name')
            ->paginate(20, ['*'], 'units_page')
            ->withQueryString();

        $procurementTypes = ProcurementType::query()
            ->withCount('inventoryItems')
            ->orderBy('name')
            ->paginate(20, ['*'], 'procurement_page')
            ->withQueryString();

        $configurations = null;
        $configLookups = [];
        $locationData = collect();

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

            $locationData = Campus::query()
                ->where('is_active', true)
                ->with([
                    'buildings' => fn ($q) => $q->where('is_active', true)->orderBy('name')
                        ->with([
                            'floors' => fn ($q) => $q->where('is_active', true)->orderBy('floor_number')
                                ->with([
                                    'rooms' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
                                ]),
                        ]),
                ])
                ->orderBy('name')
                ->get();
        }

        return view('master.categories.index', compact(
            'tab',
            'categories',
            'orgUnits',
            'procurementTypes',
            'configurations',
            'configLookups',
            'locationData',
        ));
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::query()->create([
            ...$request->validated(),
            'slug' => $request->input('slug') ?? Str::slug($request->input('name')),
        ]);

        return redirect()
            ->route('categories.index', ['tab' => 'klasifikasi'])
            ->with('success', 'Klasifikasi berhasil ditambahkan.');
    }

    public function update(StoreCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()
            ->route('categories.index', ['tab' => 'klasifikasi'])
            ->with('success', 'Klasifikasi berhasil diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->subCategories()->exists()) {
            return redirect()
                ->route('categories.index', ['tab' => 'klasifikasi'])
                ->with('error', 'Kategori tidak dapat dihapus karena masih memiliki sub kategori.');
        }

        if ($category->inventoryItems()->exists()) {
            return redirect()
                ->route('categories.index', ['tab' => 'klasifikasi'])
                ->with('error', 'Kategori tidak dapat dihapus karena masih digunakan inventaris.');
        }

        $category->delete();

        return redirect()
            ->route('categories.index', ['tab' => 'klasifikasi'])
            ->with('success', 'Klasifikasi berhasil dihapus.');
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
