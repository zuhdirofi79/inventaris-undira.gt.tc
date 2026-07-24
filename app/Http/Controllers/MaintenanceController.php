<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceResult;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Http\Requests\CompleteRepairRequest;
use App\Http\Requests\StartRepairRequest;
use App\Http\Requests\StoreMaintenanceRequest;
use App\Http\Requests\UpdateMaintenanceRequest;
use App\Models\InventoryItem;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AuditLogService;
use App\Services\InventorySearchService;
use App\Services\MaintenanceWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class MaintenanceController extends Controller
{
    public function __construct(
        protected AuditLogService $audit,
        protected MaintenanceWorkflowService $workflow,
        protected InventorySearchService $inventorySearch,
    ) {}

    public function index(Request $request): View
    {
        $maintenances = Maintenance::query()
            ->with(['inventoryItem', 'vendor'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('maintenance_number', 'like', $term)
                        ->orWhere('technician', 'like', $term)
                        ->orWhereHas('inventoryItem', function ($item) use ($term) {
                            $item->where('inventory_number', 'like', $term)
                                ->orWhere('name', 'like', $term);
                        })
                        ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', $term));
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('maintenance_type', $request->string('type')))
            ->latest('service_date')
            ->paginate(20)
            ->withQueryString();

        return view('operations.maintenances.index', [
            'maintenances' => $maintenances,
            'statuses' => MaintenanceStatus::cases(),
            'types' => MaintenanceType::cases(),
        ]);
    }

    public function scan(): View
    {
        return view('operations.maintenances.scan');
    }

    public function scanLookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:255'],
        ]);

        $result = $this->inventorySearch->resolveCodeForMaintenance($data['code']);

        if (! ($result['found'] ?? false)) {
            return response()->json([
                'found' => false,
                'message' => $result['message'] ?? 'Inventaris tidak ditemukan.',
            ], 404);
        }

        $ctx = $result['item'];
        $item = InventoryItem::query()->find($ctx['id']);

        if ($item) {
            $this->audit->log(
                $request->user(),
                AuditAction::ScanQr,
                $item,
                null,
                ['code' => $data['code'], 'context' => 'maintenance_scan'],
                'Scan QR untuk maintenance '.$this->inventorySearch->normalizeCode($data['code']),
                reason: 'maintenance_scan',
                affectedCount: 1,
            );
        }

        return response()->json([
            'found' => true,
            'item' => $this->workflow->assetCardFromContext($ctx),
        ]);
    }

    public function create(Request $request): View
    {
        $selectedId = $request->integer('inventory_item_id') ?: (int) old('inventory_item_id');
        $maintenanceContext = $selectedId > 0
            ? $this->inventorySearch->maintenanceContext($selectedId)
            : null;

        return view('operations.maintenances.create', [
            'maintenanceContext' => $maintenanceContext,
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'types' => MaintenanceType::cases(),
            'priorities' => MaintenancePriority::cases(),
            'statuses' => MaintenanceStatus::cases(),
        ]);
    }

    public function store(StoreMaintenanceRequest $request): RedirectResponse
    {
        $payload = collect($request->validated())
            ->except(['photos_before'])
            ->all();

        try {
            $maintenance = $this->workflow->create(
                $payload,
                $request->user(),
                $request->file('photos_before', []) ?? [],
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('maintenances.show', $maintenance)
            ->with('success', 'Work order pemeliharaan berhasil dibuat. Status inventaris: Dalam Perbaikan.');
    }

    public function startRepairForm(Request $request): View
    {
        $selectedId = $request->integer('inventory_item_id') ?: (int) old('inventory_item_id');
        abort_unless($selectedId > 0, 404);

        $item = InventoryItem::query()
            ->with(['category', 'subCategory', 'room.floor.building.campus', 'pic'])
            ->findOrFail($selectedId);

        return view('operations.maintenances.start-repair', [
            'item' => $item,
            'assetCard' => $this->workflow->assetCard($item),
            'technicians' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function startRepair(StartRepairRequest $request): RedirectResponse
    {
        $payload = collect($request->validated())->except(['photos_before'])->all();

        try {
            $maintenance = $this->workflow->startRepair(
                $payload,
                $request->user(),
                $request->file('photos_before', []) ?? [],
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('maintenances.show', $maintenance)
            ->with('success', 'Perbaikan dimulai. Status inventaris: Dalam Perbaikan.');
    }

    public function completeRepairForm(Maintenance $maintenance): View
    {
        abort_unless($maintenance->status?->isOpen(), 422, 'Work order tidak dapat diselesaikan.');

        $maintenance->load(['inventoryItem', 'photos']);

        return view('operations.maintenances.complete-repair', compact('maintenance'));
    }

    public function completeRepair(CompleteRepairRequest $request, Maintenance $maintenance): RedirectResponse
    {
        try {
            $maintenance = $this->workflow->submitForApproval(
                $maintenance,
                $request->validated(),
                $request->user(),
                $request->file('photos_after', []) ?? [],
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('maintenances.show', $maintenance)
            ->with('success', 'Perbaikan diajukan. Status inventaris: Menunggu Approval Admin.');
    }

    public function show(Maintenance $maintenance): View
    {
        $maintenance->load([
            'inventoryItem.category',
            'inventoryItem.subCategory',
            'inventoryItem.room.floor.building.campus',
            'inventoryItem.photos' => fn ($q) => $q->orderByDesc('is_primary')->limit(1),
            'vendor',
            'photos',
            'statusHistories.changedBy',
            'creator',
            'technicianUser',
            'approver',
        ]);

        return view('operations.maintenances.show', [
            'maintenance' => $maintenance,
            'assetCard' => $this->workflow->assetCard($maintenance->inventoryItem),
        ]);
    }

    public function edit(Maintenance $maintenance): View
    {
        $maintenance->load(['inventoryItem', 'photos']);

        return view('operations.maintenances.edit', [
            'maintenance' => $maintenance,
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'types' => MaintenanceType::cases(),
            'priorities' => MaintenancePriority::cases(),
            'statuses' => MaintenanceStatus::cases(),
            'results' => MaintenanceResult::cases(),
        ]);
    }

    public function update(UpdateMaintenanceRequest $request, Maintenance $maintenance): RedirectResponse
    {
        $payload = collect($request->validated())
            ->except(['photos_during', 'photos_after'])
            ->all();

        try {
            $maintenance = $this->workflow->update(
                $maintenance,
                $payload,
                $request->user(),
                $request->file('photos_during', []) ?? [],
                $request->file('photos_after', []) ?? [],
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('maintenances.show', $maintenance)
            ->with('success', 'Pemeliharaan berhasil diperbarui.');
    }

    public function destroy(Maintenance $maintenance): RedirectResponse
    {
        $this->workflow->delete($maintenance, request()->user());

        return redirect()->route('maintenances.index')->with('success', 'Data pemeliharaan berhasil dihapus.');
    }
}
