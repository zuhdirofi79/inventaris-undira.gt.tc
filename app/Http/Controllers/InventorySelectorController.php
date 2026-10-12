<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventorySelectorSearchRequest;
use App\Models\InventoryItem;
use App\Services\BorrowingItemResolver;
use App\Services\InventorySearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
            'results' => $this->searchService->categoryOptionsFor($request->filters()),
        ]);
    }

    public function subCategories(InventorySelectorSearchRequest $request): JsonResponse
    {
        return response()->json([
            'results' => $this->searchService->subCategoryOptionsFor($request->filters()),
        ]);
    }

    public function items(InventorySelectorSearchRequest $request): JsonResponse
    {
        return response()->json([
            'results' => $this->searchService->locationItems(
                $request->filters(),
                trim((string) $request->input('q', '')),
                $request->boolean('partial')
            ),
        ]);
    }

    public function search(InventorySelectorSearchRequest $request): JsonResponse
    {
        return response()->json([
            'results' => $this->searchService->search(
                trim((string) $request->input('q', '')),
                $request->filters()
            ),
        ]);
    }

    public function show(InventoryItem $item): JsonResponse
    {
        $detail = $this->searchService->detail($item->id);

        return response()->json($detail ?? ['message' => 'Not found'], $detail ? 200 : 404);
    }

    public function borrowSiblings(Request $request, InventoryItem $item): JsonResponse
    {
        $qty = max(1, min(500, (int) $request->input('qty', 1)));
        $exclude = array_values(array_filter(array_map(
            'intval',
            array_filter(explode(',', (string) $request->input('exclude', '')))
        )));

        try {
            $resolver = app(BorrowingItemResolver::class);
            $ids = $resolver->resolveIds((int) $item->id, $qty, $exclude);
            $items = [];
            foreach ($ids as $id) {
                $detail = $this->searchService->detail($id);
                if ($detail) {
                    $items[] = $detail;
                }
            }

            return response()->json([
                'items' => $items,
                'count' => count($items),
            ]);
        } catch (ValidationException $e) {
            $messages = collect($e->errors())->flatten()->all();

            return response()->json([
                'message' => $messages[0] ?? 'Tidak dapat menambahkan barang.',
                'errors' => $e->errors(),
            ], 422);
        }
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
