<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryRequest;
use App\Http\Requests\UpdateInventoryRequest;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Room;
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
            ->with(['category', 'subCategory', 'room.floor.building.campus'])
            ->orderBy('inventory_number')
            ->paginate($request->input('per_page', 15));

        return response()->json($items);
    }

    public function show(InventoryItem $inventoryItem): JsonResponse
    {
        $inventoryItem->load([
            'category', 'subCategory', 'room.floor.building.campus',
            'supplier', 'vendor', 'photos',
        ]);

        return response()->json($inventoryItem);
    }

    public function store(StoreInventoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $room = Room::query()->findOrFail($data['room_id']);
        $category = Category::query()->findOrFail($data['category_id']);
        $groupKey = $this->codeService->generateGroupKey($data['name'], $room->id, $category->id);
        $inventoryNumber = $this->codeService->generateInventoryNumber($room, $category, $groupKey);

        $item = DB::transaction(function () use ($data, $groupKey, $inventoryNumber, $request) {
            $item = InventoryItem::query()->create([
                ...$data,
                'inventory_number' => $inventoryNumber,
                'group_key' => $groupKey,
                'unit_sequence' => 1,
                'quantity' => 1,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            return $this->codeService->generateAndSaveCodes($item);
        });

        return response()->json($item->load(['category', 'room']), 201);
    }

    public function update(UpdateInventoryRequest $request, InventoryItem $inventoryItem): JsonResponse
    {
        $inventoryItem->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json($inventoryItem->fresh()->load(['category', 'room']));
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
            ->where('inventory_number', $inventoryNumber)
            ->with(['category', 'room.floor.building.campus', 'photos'])
            ->firstOrFail();

        return response()->json($item);
    }
}
