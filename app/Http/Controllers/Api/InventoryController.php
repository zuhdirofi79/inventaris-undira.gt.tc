<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryRequest;
use App\Http\Requests\UpdateInventoryRequest;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\ProcurementType;
use App\Models\Room;
use App\Models\SubCategory;
use App\Services\InventoryCodeService;
use App\Services\InventoryFilterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryFilterService $filterService,
        protected InventoryCodeService $codeService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->filterService
            ->apply($request)
            ->with(['category', 'subCategory', 'room.floor.building.campus', 'orgUnit', 'procurementType', 'pic'])
            ->orderBy('inventory_number')
            ->paginate($request->input('per_page', 15));

        return response()->json($items);
    }

    public function show(InventoryItem $inventoryItem): JsonResponse
    {
        $inventoryItem->load([
            'category', 'subCategory', 'room.floor.building.campus',
            'orgUnit', 'procurementType', 'supplier', 'vendor', 'photos', 'pic',
        ]);

        return response()->json($inventoryItem);
    }

    public function store(StoreInventoryRequest $request): JsonResponse
    {
        $data = $this->syncProcurementFunding($request->validated());
        $room = Room::query()->with('floor.building.campus')->findOrFail($data['room_id']);
        $campus = $room->floor?->building?->campus;
        if (! $campus) {
            return response()->json(['message' => 'Ruangan tidak terhubung ke kampus.'], 422);
        }

        $category = Category::query()->findOrFail($data['category_id']);
        $subCategory = SubCategory::query()->findOrFail($data['sub_category_id']);
        $procurement = ProcurementType::query()->findOrFail($data['procurement_type_id']);
        $purchaseYear = (int) $data['purchase_year'];
        $quantity = (int) ($data['quantity'] ?? 1);
        unset($data['quantity']);

        $groupKey = $this->codeService->generateGroupKey($data['name'], $room->id, $category->id);

        if (! empty($data['pic_id'])) {
            $data['responsible_person'] = \App\Models\User::query()->whereKey($data['pic_id'])->value('name');
        }

        $items = DB::transaction(function () use ($data, $campus, $category, $subCategory, $procurement, $purchaseYear, $quantity, $groupKey, $request) {
            $created = [];

            for ($i = 0; $i < $quantity; $i++) {
                $inventoryNumber = $this->codeService->generateInventoryNumber(
                    $campus,
                    $procurement,
                    $purchaseYear,
                    $category,
                    $subCategory,
                );

                $item = InventoryItem::query()->create([
                    ...$data,
                    'inventory_number' => $inventoryNumber,
                    'group_key' => $groupKey,
                    'unit_sequence' => $i + 1,
                    'quantity' => 1,
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);

                $created[] = $this->codeService->generateAndSaveCodes($item)->load(['category', 'room']);
            }

            return $created;
        });

        return response()->json([
            'message' => count($items) > 1
                ? count($items).' inventory units created (1 asset = 1 record).'
                : 'Inventory created.',
            'count' => count($items),
            'data' => count($items) === 1 ? $items[0] : $items,
        ], 201);
    }

    public function update(UpdateInventoryRequest $request, InventoryItem $inventoryItem): JsonResponse
    {
        $data = $this->syncProcurementFunding($request->validated());
        unset($data['quantity']);

        if (array_key_exists('pic_id', $data)) {
            $picId = $data['pic_id'] ?: null;
            $data['pic_id'] = $picId;
            $data['responsible_person'] = $picId
                ? \App\Models\User::query()->whereKey($picId)->value('name')
                : null;
        }

        $inventoryItem->update([
            ...$data,
            'quantity' => 1,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json($inventoryItem->fresh()->load(['category', 'room', 'orgUnit', 'procurementType', 'pic']));
    }

    public function destroy(InventoryItem $inventoryItem): JsonResponse
    {
        if (! request()->user()?->can('inventory.delete')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $inventoryItem->delete();

        return response()->json(['message' => 'Inventory deleted.']);
    }

    public function scan(string $inventoryNumber): JsonResponse
    {
        $item = InventoryItem::query()
            ->where(function ($query) use ($inventoryNumber) {
                $query->where('inventory_number', $inventoryNumber)
                    ->orWhere('barcode', $inventoryNumber);
            })
            ->with(['category', 'subCategory', 'room.floor.building.campus', 'orgUnit', 'photos'])
            ->firstOrFail();

        return response()->json($item);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function syncProcurementFunding(array $data): array
    {
        if (! empty($data['procurement_type_id'])) {
            $type = ProcurementType::query()->find($data['procurement_type_id']);
            if ($type) {
                $data['funding_source'] = $type->name;
            }
        }

        return $data;
    }
}
