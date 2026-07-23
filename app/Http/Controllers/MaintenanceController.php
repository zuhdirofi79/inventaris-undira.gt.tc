<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\InventoryStatus;
use App\Enums\MaintenanceStatus;
use App\Http\Requests\StoreMaintenanceRequest;
use App\Http\Requests\UpdateMaintenanceRequest;
use App\Models\InventoryItem;
use App\Models\Maintenance;
use App\Models\Vendor;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function __construct(
        protected AuditLogService $audit,
    ) {}

    public function index(): View
    {
        $maintenances = Maintenance::query()
            ->with(['inventoryItem', 'vendor'])
            ->latest('service_date')
            ->paginate(20);

        return view('operations.maintenances.index', compact('maintenances'));
    }

    public function create(Request $request): View
    {
        $selectedItem = null;
        $selectedId = $request->integer('inventory_item_id') ?: (int) old('inventory_item_id');
        if ($selectedId > 0) {
            $selectedItem = InventoryItem::query()->find($selectedId, ['id', 'inventory_number', 'name']);
        }

        return view('operations.maintenances.create', [
            'selectedItem' => $selectedItem,
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreMaintenanceRequest $request): RedirectResponse
    {
        $maintenance = DB::transaction(function () use ($request) {
            $maintenance = Maintenance::query()->create([
                ...$request->validated(),
                'status' => $request->input('status', MaintenanceStatus::Scheduled),
            ]);

            InventoryItem::query()
                ->where('id', $maintenance->inventory_item_id)
                ->update(['status' => InventoryStatus::Maintenance]);

            return $maintenance;
        });

        $this->audit->log(
            $request->user(),
            AuditAction::Create,
            $maintenance,
            null,
            $maintenance->only(['inventory_item_id', 'vendor_id', 'service_date', 'next_schedule', 'status', 'cost']),
            'MAINTENANCE_CREATED',
        );

        return redirect()->route('maintenances.index')->with('success', 'Maintenance berhasil dicatat.');
    }

    public function show(Maintenance $maintenance): View
    {
        $maintenance->load(['inventoryItem.room', 'vendor']);

        return view('operations.maintenances.show', compact('maintenance'));
    }

    public function edit(Maintenance $maintenance): View
    {
        return view('operations.maintenances.edit', [
            'maintenance' => $maintenance,
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateMaintenanceRequest $request, Maintenance $maintenance): RedirectResponse
    {
        $old = $maintenance->only(['vendor_id', 'service_date', 'next_schedule', 'status', 'cost']);

        DB::transaction(function () use ($request, $maintenance) {
            $maintenance->update($request->validated());

            if ($maintenance->status === MaintenanceStatus::Completed) {
                InventoryItem::query()
                    ->where('id', $maintenance->inventory_item_id)
                    ->update(['status' => InventoryStatus::Aktif]);
            }
        });

        $maintenance->refresh();

        $this->audit->log(
            $request->user(),
            AuditAction::Update,
            $maintenance,
            $old,
            $maintenance->only(['vendor_id', 'service_date', 'next_schedule', 'status', 'cost']),
            'MAINTENANCE_UPDATED',
        );

        return redirect()->route('maintenances.show', $maintenance)->with('success', 'Maintenance berhasil diperbarui.');
    }

    public function destroy(Maintenance $maintenance): RedirectResponse
    {
        $snapshot = $maintenance->only(['inventory_item_id', 'status', 'service_date']);

        DB::transaction(function () use ($maintenance) {
            if (in_array($maintenance->status, [MaintenanceStatus::Scheduled, MaintenanceStatus::InProgress], true)) {
                InventoryItem::query()
                    ->where('id', $maintenance->inventory_item_id)
                    ->update(['status' => InventoryStatus::Aktif]);
            }

            $maintenance->delete();
        });

        $this->audit->log(
            request()->user(),
            AuditAction::Delete,
            null,
            $snapshot,
            null,
            'MAINTENANCE_DELETED',
        );

        return back()->with('success', 'Data maintenance berhasil dihapus.');
    }
}
