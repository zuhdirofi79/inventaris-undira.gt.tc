<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryMasterConfigurationRequest;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryConditionMaster;
use App\Models\InventoryMasterConfiguration;
use App\Models\OrgUnit;
use App\Models\ProcurementType;
use App\Models\PurchaseYear;
use App\Models\Room;
use App\Models\RoomFunction;
use App\Models\SubCategory;
use App\Services\InventoryCodeService;
use App\Services\InventoryCreationService;
use App\Services\LocationCodeGeneratorService;
use App\Services\TrashCascadeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryMasterConfigurationController extends Controller
{
    public function __construct(
        protected LocationCodeGeneratorService $locationCodes,
        protected InventoryCodeService $inventoryCodes,
        protected InventoryCreationService $inventoryCreation,
        protected TrashCascadeService $trash,
    ) {}

    public function store(StoreInventoryMasterConfigurationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $createAssets = $request->boolean('create_assets', true) && filled($data['asset_name'] ?? null);

        if ($createAssets) {
            $items = $this->inventoryCreation->createFromConfiguration(
                $this->masterPayload($data),
                $this->assetPayload($data),
                $request->user(),
            );

            $roomId = (int) $data['room_id'];
            $count = $items->count();

            return redirect()
                ->route('rooms.show', $roomId)
                ->with('success', $count > 1
                    ? "{$count} unit inventaris berhasil ditambahkan ke ruangan (QR Code otomatis)."
                    : 'Inventaris berhasil ditambahkan ke ruangan (QR Code otomatis).');
        }

        // Legacy: simpan template konfigurasi saja (tanpa aset fisik)
        $data['generated_location_code'] = $this->buildLocationCode($data);
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;
        $data['is_active'] = $request->boolean('is_active', true);
        unset($data['asset_name'], $data['quantity'], $data['unit'], $data['brand'], $data['price'], $data['notes'], $data['create_assets']);

        InventoryMasterConfiguration::query()->create($data);

        return redirect()
            ->route('categories.index', ['tab' => 'konfigurasi'])
            ->with('success', 'Konfigurasi inventaris berhasil ditambahkan.');
    }

    public function update(
        StoreInventoryMasterConfigurationRequest $request,
        InventoryMasterConfiguration $configuration,
    ): RedirectResponse {
        $data = $request->validated();
        $data['generated_location_code'] = $this->buildLocationCode($data);
        $data['updated_by'] = $request->user()->id;
        $data['is_active'] = $request->boolean('is_active', true);
        unset($data['asset_name'], $data['quantity'], $data['unit'], $data['brand'], $data['price'], $data['notes'], $data['create_assets']);

        $configuration->update($data);

        return redirect()
            ->route('categories.index', ['tab' => 'konfigurasi'])
            ->with('success', 'Konfigurasi inventaris berhasil diperbarui.');
    }

    public function destroy(Request $request, InventoryMasterConfiguration $configuration): RedirectResponse
    {
        $this->authorizeManage();

        $this->trash->softDelete($configuration, $request->user());

        return redirect()
            ->route('categories.index', ['tab' => 'konfigurasi'])
            ->with('success', 'Konfigurasi dipindahkan ke Trash.');
    }

    public function preview(Request $request): JsonResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'campus_id' => ['nullable', 'integer'],
            'org_unit_id' => ['nullable', 'integer'],
            'room_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'sub_category_id' => ['nullable', 'integer'],
        ]);

        $campus = isset($data['campus_id']) ? Campus::query()->find($data['campus_id']) : null;
        $orgUnit = isset($data['org_unit_id']) ? OrgUnit::query()->find($data['org_unit_id']) : null;
        $room = isset($data['room_id']) ? Room::query()->find($data['room_id']) : null;

        $locationCode = $this->locationCodes->tryGenerate($campus, $orgUnit, $room);
        $locationError = null;
        if ($campus && $orgUnit && $room && $locationCode === null) {
            try {
                $this->locationCodes->generate($campus, $orgUnit, $room);
            } catch (\Illuminate\Validation\ValidationException $e) {
                $locationError = $e->errors()['generated_location_code'][0] ?? 'Kode lokasi tidak valid.';
            }
        }

        $sequence = null;
        if (! empty($data['campus_id']) && ! empty($data['sub_category_id'])) {
            $sequence = $this->inventoryCodes->peekNextSequence(
                (int) $data['campus_id'],
                (int) $data['sub_category_id'],
            );
        }

        return response()->json([
            'generated_location_code' => $locationCode,
            'location_error' => $locationError,
            'room_code' => $room?->code,
            'sequence' => $sequence,
        ]);
    }

    public function buildingsByCampus(Campus $campus): JsonResponse
    {
        $this->authorizeView();

        $items = Building::query()
            ->where('campus_id', $campus->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'campus_id']);

        return response()->json(['data' => $items]);
    }

    public function floorsByBuilding(Building $building): JsonResponse
    {
        $this->authorizeView();

        $items = Floor::query()
            ->where('building_id', $building->id)
            ->where('is_active', true)
            ->orderBy('floor_number')
            ->get(['id', 'code', 'name', 'building_id', 'floor_number']);

        return response()->json(['data' => $items]);
    }

    public function roomsByFloor(Floor $floor): JsonResponse
    {
        $this->authorizeView();

        $items = Room::query()
            ->where('floor_id', $floor->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'floor_id'])
            ->each(fn (Room $room) => $room->setAppends([]));

        return response()->json(['data' => $items]);
    }

    public function subclassificationsByClassification(Category $category): JsonResponse
    {
        $this->authorizeView();

        $items = SubCategory::query()
            ->where('category_id', $category->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'category_id']);

        return response()->json(['data' => $items]);
    }

    public function storeOption(Request $request): JsonResponse
    {
        $this->authorizeManage();

        $type = $request->input('type');
        if (! in_array($type, ['room_function', 'purchase_year', 'inventory_condition'], true)) {
            return response()->json(['message' => 'Tipe opsi tidak valid.'], 422);
        }

        if ($type === 'purchase_year') {
            $data = $request->validate([
                'year' => ['required', 'integer', 'min:1990', 'max:2100', Rule::unique('purchase_years', 'year')->whereNull('deleted_at')],
                'is_active' => ['nullable', 'boolean'],
            ]);
            $row = PurchaseYear::query()->create([
                'year' => $data['year'],
                'is_active' => $request->boolean('is_active', true),
            ]);

            return response()->json([
                'data' => ['id' => $row->id, 'label' => (string) $row->year, 'year' => $row->year],
            ], 201);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($type === 'room_function') {
            $request->validate([
                'code' => [Rule::unique('room_functions', 'code')->whereNull('deleted_at')],
            ]);
            $row = RoomFunction::query()->create([
                'code' => strtoupper(trim($data['code'])),
                'name' => $data['name'],
                'is_active' => $request->boolean('is_active', true),
                'sort_order' => $data['sort_order'] ?? 0,
            ]);
        } else {
            $request->validate([
                'code' => [Rule::unique('inventory_conditions', 'code')->whereNull('deleted_at')],
            ]);
            $row = InventoryConditionMaster::query()->create([
                'code' => strtoupper(trim($data['code'])),
                'name' => $data['name'],
                'is_active' => $request->boolean('is_active', true),
                'sort_order' => $data['sort_order'] ?? 0,
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'label' => $row->name.' ('.$row->code.')',
            ],
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function masterPayload(array $data): array
    {
        return [
            'campus_id' => $data['campus_id'],
            'building_id' => $data['building_id'],
            'floor_id' => $data['floor_id'],
            'org_unit_id' => $data['org_unit_id'],
            'room_function_id' => $data['room_function_id'] ?? null,
            'room_id' => $data['room_id'],
            'procurement_type_id' => $data['procurement_type_id'],
            'purchase_year_id' => $data['purchase_year_id'],
            'category_id' => $data['category_id'],
            'sub_category_id' => $data['sub_category_id'],
            'inventory_condition_id' => $data['inventory_condition_id'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function assetPayload(array $data): array
    {
        return [
            'name' => $data['asset_name'],
            'quantity' => $data['quantity'] ?? 1,
            'unit' => $data['unit'] ?? 'Unit',
            'brand' => $data['brand'] ?? null,
            'price' => array_key_exists('price', $data) && $data['price'] !== null && $data['price'] !== ''
                ? $data['price']
                : null,
            'notes' => $data['notes'] ?? null,
            'pic_id' => ! empty($data['pic_id']) ? (int) $data['pic_id'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function buildLocationCode(array $data): string
    {
        $campus = Campus::query()->findOrFail($data['campus_id']);
        $orgUnit = OrgUnit::query()->findOrFail($data['org_unit_id']);
        $room = Room::query()->findOrFail($data['room_id']);

        return $this->locationCodes->generate($campus, $orgUnit, $room);
    }

    protected function authorizeManage(): void
    {
        abort_unless(
            auth()->user()?->can('category.manage') || auth()->user()?->can('inventory.create'),
            403
        );
    }

    protected function authorizeView(): void
    {
        abort_unless(
            auth()->user()?->can('category.manage')
            || auth()->user()?->can('location.manage')
            || auth()->user()?->can('inventory.view')
            || auth()->user()?->can('inventory.create'),
            403
        );
    }
}
