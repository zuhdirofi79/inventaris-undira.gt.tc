<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProcurementTypeRequest;
use App\Models\ProcurementType;
use Illuminate\Http\RedirectResponse;

class ProcurementTypeController extends Controller
{
    public function store(StoreProcurementTypeRequest $request): RedirectResponse
    {
        ProcurementType::query()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('categories.index', ['tab' => 'pengadaan'])
            ->with('success', 'Jenis pengadaan berhasil ditambahkan.');
    }

    public function update(StoreProcurementTypeRequest $request, ProcurementType $procurementType): RedirectResponse
    {
        $procurementType->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('categories.index', ['tab' => 'pengadaan'])
            ->with('success', 'Jenis pengadaan berhasil diperbarui.');
    }

    public function destroy(ProcurementType $procurementType): RedirectResponse
    {
        if ($procurementType->inventoryItems()->exists()) {
            return redirect()
                ->route('categories.index', ['tab' => 'pengadaan'])
                ->with('error', 'Jenis pengadaan tidak dapat dihapus karena masih digunakan inventaris.');
        }

        $procurementType->delete();

        return redirect()
            ->route('categories.index', ['tab' => 'pengadaan'])
            ->with('success', 'Jenis pengadaan berhasil dihapus.');
    }
}
