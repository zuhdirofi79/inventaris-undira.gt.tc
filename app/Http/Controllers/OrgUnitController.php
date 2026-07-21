<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrgUnitRequest;
use App\Models\OrgUnit;
use App\Services\TrashCascadeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrgUnitController extends Controller
{
    public function __construct(
        protected TrashCascadeService $trash,
    ) {}

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

    public function destroy(Request $request, OrgUnit $orgUnit): RedirectResponse
    {
        $this->trash->softDelete($orgUnit, $request->user());

        return redirect()
            ->route('categories.index', ['tab' => 'units'])
            ->with('success', 'Unit/Divisi dipindahkan ke Trash.');
    }
}
