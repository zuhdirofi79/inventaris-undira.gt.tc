<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\StoreProcurementTypeRequest;
use App\Models\ProcurementType;
use App\Services\AuditLogService;
use App\Services\TrashCascadeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProcurementTypeController extends Controller
{
    public function __construct(
        protected TrashCascadeService $trash,
        protected AuditLogService $audit,
    ) {}

    public function store(StoreProcurementTypeRequest $request): RedirectResponse
    {
        $procurementType = ProcurementType::query()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->audit->log(
            $request->user(),
            AuditAction::Create,
            $procurementType,
            null,
            $procurementType->only(['code', 'name', 'is_active']),
            'PROCUREMENT_TYPE_CREATED',
        );

        return redirect()
            ->route('categories.index', ['tab' => 'pengadaan'])
            ->with('success', 'Jenis pengadaan berhasil ditambahkan.');
    }

    public function update(StoreProcurementTypeRequest $request, ProcurementType $procurementType): RedirectResponse
    {
        $old = $procurementType->only(['code', 'name', 'is_active']);
        $procurementType->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->audit->log(
            $request->user(),
            AuditAction::Update,
            $procurementType,
            $old,
            $procurementType->only(['code', 'name', 'is_active']),
            'PROCUREMENT_TYPE_UPDATED',
        );

        return redirect()
            ->route('categories.index', ['tab' => 'pengadaan'])
            ->with('success', 'Jenis pengadaan berhasil diperbarui.');
    }

    public function destroy(Request $request, ProcurementType $procurementType): RedirectResponse
    {
        $this->trash->softDelete($procurementType, $request->user());

        return redirect()
            ->route('categories.index', ['tab' => 'pengadaan'])
            ->with('success', 'Jenis pengadaan dipindahkan ke Trash.');
    }
}
