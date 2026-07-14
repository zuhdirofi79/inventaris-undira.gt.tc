<?php

namespace App\Http\Controllers;

use App\Enums\InventoryStatus;
use App\Enums\MaintenanceStatus;
use App\Http\Requests\StoreMaintenanceRequest;
use App\Models\InventoryItem;
use App\Models\Maintenance;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function index(): View
    {
        $maintenances = Maintenance::query()
            ->with(['inventoryItem', 'vendor'])
            ->latest('service_date')
            ->paginate(20);

        return view('operations.maintenances.index', compact('maintenances'));
    }

    public function create(): View
    {
        return view('operations.maintenances.create', [
            'items' => InventoryItem::query()->orderBy('name')->get(['id', 'inventory_number', 'name']),
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreMaintenanceRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $maintenance = Maintenance::query()->create([
                ...$request->validated(),
                'status' => $request->input('status', MaintenanceStatus::Scheduled),
            ]);

            InventoryItem::query()
                ->where('id', $maintenance->inventory_item_id)
                ->update(['status' => InventoryStatus::Maintenance]);
        });

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
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Maintenance $maintenance): RedirectResponse
    {
        $request->validate([
            'vendor_id' => ['nullable', 'exists:vendors,id'],
            'service_date' => ['sometimes', 'required', 'date'],
            'next_schedule' => ['nullable', 'date'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required'],
        ]);

        DB::transaction(function () use ($request, $maintenance) {
            $maintenance->update($request->only([
                'vendor_id', 'service_date', 'next_schedule', 'cost', 'description', 'status',
            ]));

            if ($maintenance->status === MaintenanceStatus::Completed) {
                InventoryItem::query()
                    ->where('id', $maintenance->inventory_item_id)
                    ->update(['status' => InventoryStatus::Aktif]);
            }
        });

        return redirect()->route('maintenances.show', $maintenance)->with('success', 'Maintenance berhasil diperbarui.');
    }

    public function destroy(Maintenance $maintenance): RedirectResponse
    {
        $maintenance->delete();

        return back()->with('success', 'Data maintenance berhasil dihapus.');
    }
}
