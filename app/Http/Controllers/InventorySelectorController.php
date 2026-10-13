<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventorySelectorSearchRequest;
use App\Models\InventoryItem;
use App\Services\BorrowingItemResolver;
use App\Services\InventoryAvailability;
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
                $request->boolean('partial'),
                40,
                $this->borrowingWindow($request),
            ),
        ]);
    }

    public function search(InventorySelectorSearchRequest $request): JsonResponse
    {
        return response()->json([
            'results' => $this->searchService->search(
                trim((string) $request->input('q', '')),
                $request->filters(),
                30,
                $this->borrowingWindow($request),
            ),
        ]);
    }

    public function show(Request $request, InventoryItem $item): JsonResponse
    {
        $detail = $this->searchService->detail($item->id, $this->borrowingWindow($request));

        return response()->json($detail ?? ['message' => 'Not found'], $detail ? 200 : 404);
    }

    public function borrowSiblings(Request $request, InventoryItem $item): JsonResponse
    {
        $qty = max(1, min(500, (int) $request->input('qty', 1)));
        $exclude = array_values(array_filter(array_map(
            'intval',
            array_filter(explode(',', (string) $request->input('exclude', '')))
        )));

        $window = app(InventoryAvailability::class)->completeWindow(
            $request->input('borrow_date'),
            $request->input('expected_return_date'),
        );
        if ($window === null) {
            return response()->json([
                'message' => 'Isi tanggal dan jam pinjam serta tanggal dan jam kembali sebelum menambahkan inventaris.',
            ], 422);
        }

        try {
            $resolver = app(BorrowingItemResolver::class);
            $ids = $resolver->resolveIds((int) $item->id, $qty, $exclude, $window['start'], $window['end']);
            $items = [];
            foreach ($ids as $id) {
                $detail = $this->searchService->detail($id, $window);
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
            : $this->searchService->resolveCode($data['code'], $this->borrowingWindow($request));

        if (! ($result['found'] ?? false)) {
            $status = ($result['message'] ?? '') === 'Kode kosong.' ? 422 : 404;

            return response()->json($result, $status);
        }

        return response()->json($result);
    }

    /**
     * Present only when the borrowing form sends its schedule fields.
     *
     * @return array{start: ?\Carbon\CarbonInterface, end: ?\Carbon\CarbonInterface}|null
     */
    private function borrowingWindow(Request $request): ?array
    {
        if (! $request->exists('borrow_date') && ! $request->exists('expected_return_date')) {
            return null;
        }

        return app(InventoryAvailability::class)->window(
            $request->input('borrow_date'),
            $request->input('expected_return_date'),
        );
    }
}
