<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\StoreOrgUnitRequest;
use App\Models\OrgUnit;
use App\Services\AuditLogService;
use App\Services\TrashCascadeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrgUnitController extends Controller
{
    public function __construct(
        protected TrashCascadeService $trash,
        protected AuditLogService $audit,
    ) {}

    public function store(StoreOrgUnitRequest $request): RedirectResponse
    {
        $orgUnit = OrgUnit::query()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->audit->log(
            $request->user(),
            AuditAction::Create,
            $orgUnit,
            null,
            $orgUnit->only(['code', 'name', 'is_active']),
            'ORG_UNIT_CREATED',
        );

        return redirect()
            ->route('categories.index', ['tab' => 'units'])
            ->with('success', 'Unit / Divisi berhasil ditambahkan.');
    }

    public function update(StoreOrgUnitRequest $request, OrgUnit $orgUnit): RedirectResponse
    {
        $old = $orgUnit->only(['code', 'name', 'is_active']);
        $orgUnit->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->audit->log(
            $request->user(),
            AuditAction::Update,
            $orgUnit,
            $old,
            $orgUnit->only(['code', 'name', 'is_active']),
            'ORG_UNIT_UPDATED',
        );

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
