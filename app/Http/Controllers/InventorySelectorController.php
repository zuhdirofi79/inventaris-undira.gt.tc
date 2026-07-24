<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventorySelectorSearchRequest;
use App\Models\InventoryItem;
use App\Services\InventorySearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventorySelectorController extends Controller
{
    public function __construct(
        protected InventorySearchService $searchService,
    ) {}

    public function campuses(): JsonResponse
    {
        return response()->json(['results' => $this->searchService->campusOptions()]);
    }

    public function buildings(InventorySelectorSearchRequest $request): JsonResponse
    {
        return response()->json([
            'results' => $this->searchService->buildingOptions($request->integer('campus_id')),
        ]);
    }

    public function floors(InventorySelectorSearchRequest $request): JsonResponse
    {
        return response()->json([
            'results' => $this->searchService->floorOptions($request->integer('building_id')),
        ]);
    }

    public function rooms(InventorySelectorSearchRequest $request): JsonResponse
    {
        return response()->json([
            'results' => $this->searchService->roomOptions($request->integer('floor_id')),
        ]);
    }

    public function categories(InventorySelectorSearchRequest $request): JsonResponse
    {
        return response()->json([
            'results' => $this->searchService->categoryOptions(
                $request->integer('room_id'),
                $request->statusFilter()
            ),
        ]);
    }

    public function subCategories(InventorySelectorSearchRequest $request): JsonResponse
    {
        return response()->json([
            'results' => $this->searchService->subCategoryOptions(
                $request->integer('room_id'),
                $request->integer('category_id'),
                $request->statusFilter()
            ),
        ]);
    }

    public function items(InventorySelectorSearchRequest $request): JsonResponse
    {
        $filters = $request->filters();

        return response()->json([
            'results' => $this->searchService->locationItems(
                $filters,
                trim((string) $request->input('q', ''))
            ),
        ]);
    }

    public function search(InventorySelectorSearchRequest $request): JsonResponse
    {
        return response()->json([
            'results' => $this->searchService->search(
                trim((string) $request->input('q', '')),
                array_filter(['status' => $request->statusFilter()], fn ($v) => $v !== null)
            ),
        ]);
    }

    public function show(InventoryItem $item): JsonResponse
    {
        $detail = $this->searchService->detail($item->id);

        return response()->json($detail ?? ['message' => 'Not found'], $detail ? 200 : 404);
    }

    public function maintenanceContext(InventoryItem $item): JsonResponse
    {
        $payload = $this->searchService->maintenanceContext($item->id);

        return response()->json($payload ?? ['message' => 'Not found'], $payload ? 200 : 404);
    }

    public function resolveCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:500'],
        ]);

        $forMaintenance = $request->boolean('maintenance');
        $result = $forMaintenance
            ? $this->searchService->resolveCodeForMaintenance($data['code'])
            : $this->searchService->resolveCode($data['code']);

        if (! ($result['found'] ?? false)) {
            $status = ($result['message'] ?? '') === 'Kode kosong.' ? 422 : 404;

            return response()->json($result, $status);
        }

        return response()->json($result);
    }
}
