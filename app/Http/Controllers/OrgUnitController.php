<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrgUnitRequest;
use App\Models\OrgUnit;
use Illuminate\Http\RedirectResponse;

class OrgUnitController extends Controller
{
    public function store(StoreOrgUnitRequest $request): RedirectResponse
    {
        OrgUnit::query()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('categories.index', ['tab' => 'units'])
            ->with('success', 'Unit/Divisi berhasil ditambahkan.');
    }

    public function update(StoreOrgUnitRequest $request, OrgUnit $orgUnit): RedirectResponse
    {
        $orgUnit->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('categories.index', ['tab' => 'units'])
            ->with('success', 'Unit/Divisi berhasil diperbarui.');
    }

    public function destroy(OrgUnit $orgUnit): RedirectResponse
    {
        if ($orgUnit->inventoryItems()->exists()) {
            return redirect()
                ->route('categories.index', ['tab' => 'units'])
                ->with('error', 'Unit/Divisi tidak dapat dihapus karena masih digunakan inventaris.');
        }

        $orgUnit->delete();

        return redirect()
            ->route('categories.index', ['tab' => 'units'])
            ->with('success', 'Unit/Divisi berhasil dihapus.');
    }
}
