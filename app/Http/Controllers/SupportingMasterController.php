<?php

namespace App\Http\Controllers;

use App\Models\InventoryConditionMaster;
use App\Models\PurchaseYear;
use App\Models\RoomFunction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD for supporting masters used by Konfigurasi Inventaris dropdowns.
 * Managed from Master Data → Lokasi & Lainnya (not duplicated location hierarchy).
 */
class SupportingMasterController extends Controller
{
    public function storePurchaseYear(Request $request): RedirectResponse
    {
        $this->authorizeCategoryManage();

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:1990', 'max:2100', Rule::unique('purchase_years', 'year')->whereNull('deleted_at')],
            'is_active' => ['nullable', 'boolean'],
        ]);

        PurchaseYear::query()->create([
            'year' => $data['year'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('categories.index', ['tab' => 'lokasi', 'section' => 'tahun'])
            ->with('success', 'Tahun pembelian berhasil ditambahkan.');
    }

    public function updatePurchaseYear(Request $request, PurchaseYear $purchaseYear): RedirectResponse
    {
        $this->authorizeCategoryManage();

        $data = $request->validate([
            'year' => [
                'required', 'integer', 'min:1990', 'max:2100',
                Rule::unique('purchase_years', 'year')->ignore($purchaseYear->id)->whereNull('deleted_at'),
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $purchaseYear->update([
            'year' => $data['year'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('categories.index', ['tab' => 'lokasi', 'section' => 'tahun'])
            ->with('success', 'Tahun pembelian berhasil diperbarui.');
    }

    public function destroyPurchaseYear(PurchaseYear $purchaseYear): RedirectResponse
    {
        $this->authorizeCategoryManage();

        if ($purchaseYear->configurations()->exists()) {
            $purchaseYear->update(['is_active' => false]);

            return redirect()
                ->route('categories.index', ['tab' => 'lokasi', 'section' => 'tahun'])
                ->with('success', 'Tahun sudah dipakai inventaris — dinonaktifkan (tidak dihapus).');
        }

        $purchaseYear->delete();

        return redirect()
            ->route('categories.index', ['tab' => 'lokasi', 'section' => 'tahun'])
            ->with('success', 'Tahun pembelian dihapus.');
    }

    public function storeRoomFunction(Request $request): RedirectResponse
    {
        $this->authorizeCategoryManage();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('room_functions', 'code')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        RoomFunction::query()->create([
            'code' => strtoupper(trim($data['code'])),
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('categories.index', ['tab' => 'lokasi', 'section' => 'fungsi'])
            ->with('success', 'Fungsi ruang berhasil ditambahkan.');
    }

    public function updateRoomFunction(Request $request, RoomFunction $roomFunction): RedirectResponse
    {
        $this->authorizeCategoryManage();

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('room_functions', 'code')->ignore($roomFunction->id)->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $roomFunction->update([
            'code' => strtoupper(trim($data['code'])),
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('categories.index', ['tab' => 'lokasi', 'section' => 'fungsi'])
            ->with('success', 'Fungsi ruang berhasil diperbarui.');
    }

    public function destroyRoomFunction(RoomFunction $roomFunction): RedirectResponse
    {
        $this->authorizeCategoryManage();

        if ($roomFunction->configurations()->exists()) {
            $roomFunction->update(['is_active' => false]);

            return redirect()
                ->route('categories.index', ['tab' => 'lokasi', 'section' => 'fungsi'])
                ->with('success', 'Fungsi ruang sudah dipakai — dinonaktifkan (tidak dihapus).');
        }

        $roomFunction->delete();

        return redirect()
            ->route('categories.index', ['tab' => 'lokasi', 'section' => 'fungsi'])
            ->with('success', 'Fungsi ruang dihapus.');
    }

    public function storeCondition(Request $request): RedirectResponse
    {
        $this->authorizeCategoryManage();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('inventory_conditions', 'code')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        InventoryConditionMaster::query()->create([
            'code' => strtoupper(trim($data['code'])),
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);
        \App\Support\InventoryConditionCatalog::flush();
        \App\Support\InventarisCache::flush();

        return redirect()
            ->route('categories.index', ['tab' => 'lokasi', 'section' => 'kondisi'])
            ->with('success', 'Kondisi berhasil ditambahkan.');
    }

    public function updateCondition(Request $request, InventoryConditionMaster $inventoryCondition): RedirectResponse
    {
        $this->authorizeCategoryManage();

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('inventory_conditions', 'code')->ignore($inventoryCondition->id)->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $inventoryCondition->update([
            'code' => strtoupper(trim($data['code'])),
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);
        \App\Support\InventoryConditionCatalog::flush();
        \App\Support\InventarisCache::flush();

        return redirect()
            ->route('categories.index', ['tab' => 'lokasi', 'section' => 'kondisi'])
            ->with('success', 'Kondisi berhasil diperbarui.');
    }

    public function destroyCondition(InventoryConditionMaster $inventoryCondition): RedirectResponse
    {
        $this->authorizeCategoryManage();

        if ($inventoryCondition->configurations()->exists()) {
            $inventoryCondition->update(['is_active' => false]);
            \App\Support\InventoryConditionCatalog::flush();
            \App\Support\InventarisCache::flush();

            return redirect()
                ->route('categories.index', ['tab' => 'lokasi', 'section' => 'kondisi'])
                ->with('success', 'Kondisi sudah dipakai — dinonaktifkan (tidak dihapus).');
        }

        $inventoryCondition->delete();
        \App\Support\InventoryConditionCatalog::flush();
        \App\Support\InventarisCache::flush();

        return redirect()
            ->route('categories.index', ['tab' => 'lokasi', 'section' => 'kondisi'])
            ->with('success', 'Kondisi dihapus.');
    }

    protected function authorizeCategoryManage(): void
    {
        abort_unless(auth()->user()?->can('category.manage'), 403);
    }
}
