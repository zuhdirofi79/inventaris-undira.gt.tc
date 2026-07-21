<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProcurementTypeRequest;
use App\Models\ProcurementType;
use App\Services\TrashCascadeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProcurementTypeController extends Controller
{
    public function __construct(
        protected TrashCascadeService $trash,
    ) {}

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

    public function destroy(Request $request, ProcurementType $procurementType): RedirectResponse
    {
        $this->trash->softDelete($procurementType, $request->user());

        return redirect()
            ->route('categories.index', ['tab' => 'pengadaan'])
            ->with('success', 'Jenis pengadaan dipindahkan ke Trash.');
    }
}
